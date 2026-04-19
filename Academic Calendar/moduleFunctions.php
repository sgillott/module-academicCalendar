<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Domain\System\SettingGateway;

/**
 * Validate and normalize a hex color value.
 *
 * Accepts a full 6-digit hex color (for example `#1F77B4`) and returns
 * an uppercase normalized value, otherwise `null`.
 *
 * @param string $color Raw color value.
 *
 * @return string|null Normalized hex color or `null` when invalid.
 */
function ac_normalizeHexColor(string $color): ?string
{
    $color = trim($color);
    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color) !== 1) {
        return null;
    }

    return strtoupper($color);
}

/**
 * Decode and sanitize the event-type color map JSON.
 *
 * Expected format is a JSON object of `{eventType: "#RRGGBB"}`.
 * Invalid keys or color values are ignored.
 *
 * @param string|null $json JSON string from module settings.
 *
 * @return array<string, string> Clean map of type => hex color.
 */
function ac_decodeColorMap(?string $json): array
{
    if (empty($json)) {
        return [];
    }

    $map = json_decode($json, true);
    if (!is_array($map)) {
        return [];
    }

    $clean = [];
    foreach ($map as $key => $value) {
        $type = trim((string) $key);
        if ($type === '') {
            continue;
        }

        $color = ac_normalizeHexColor((string) $value);
        if ($color !== null) {
            $clean[$type] = $color;
        }
    }

    return $clean;
}

/**
 * Retrieve saved event-type colors from module settings.
 *
 * @param SettingGateway $settingGateway Core setting gateway service.
 *
 * @return array<string, string> Map of event type to hex color.
 */
function ac_getColorMap(SettingGateway $settingGateway): array
{
    $value = $settingGateway->getSettingByScope('Academic Calendar', 'eventTypeColors');

    return ac_decodeColorMap($value ?: '');
}

/**
 * Pick a deterministic fallback color for an event key.
 *
 * Uses a stable hash so each key always maps to the same color
 * from the shared palette.
 *
 * @param string $key Event category/type key.
 *
 * @return string Hex color.
 */
function ac_colorFromPalette(string $key): string
{
    static $palette = [
        '#1F77B4', '#FF7F0E', '#2CA02C', '#D62728', '#9467BD',
        '#8C564B', '#E377C2', '#7F7F7F', '#BCBD22', '#17BECF',
    ];

    $index = abs(crc32($key)) % count($palette);

    return $palette[$index];
}

/**
 * Check whether weekends should be shown in FullCalendar.
 *
 * @param SettingGateway $settingGateway Core setting gateway service.
 *
 * @return bool `true` unless setting explicitly equals `N`.
 */
function ac_getShowWeekends(SettingGateway $settingGateway): bool
{
    $value = (string) $settingGateway->getSettingByScope('Academic Calendar', 'showWeekends');

    return $value !== 'N';
}

/**
 * Parse a CSV list of numeric IDs into a unique normalized array.
 *
 * @param string|null $list Comma-separated IDs.
 *
 * @return string[] Array of digit-only IDs.
 */
function ac_parseIDList(?string $list): array
{
    if (empty($list)) {
        return [];
    }

    $ids = array_filter(array_map('trim', explode(',', (string) $list)), function ($id) {
        return ctype_digit((string) $id);
    });

    return array_values(array_unique($ids));
}

/**
 * Sanitize a numeric request parameter value.
 *
 * Returns a trimmed digit-only string, or an empty string if invalid.
 *
 * @param string|null $value Raw request value.
 *
 * @return string Sanitized numeric ID or empty string.
 */
function ac_sanitizeNumericID(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '' || !ctype_digit($value)) {
        return '';
    }

    return $value;
}

/**
 * Get enabled year-group IDs from module settings.
 *
 * Empty setting means "all year groups enabled".
 *
 * @param SettingGateway $settingGateway Core setting gateway service.
 *
 * @return string[] Enabled year-group IDs.
 */
function ac_getEnabledYearGroupIDs(SettingGateway $settingGateway): array
{
    $value = $settingGateway->getSettingByScope('Academic Calendar', 'gibbonYearGroupIDList');

    return ac_parseIDList(is_string($value) ? $value : '');
}

/**
 * Get enabled year-group IDs using a raw PDO connection.
 *
 * Used by hook includes where service-container context may not be available.
 *
 * @param \PDO $connection2 Legacy core PDO connection.
 *
 * @return string[] Enabled year-group IDs.
 */
function ac_getEnabledYearGroupIDsFromConnection(\PDO $connection2): array
{
    $sql = "SELECT value FROM gibbonSetting WHERE scope = :scope AND name = :name";
    $stmt = $connection2->prepare($sql);
    $stmt->execute([
        'scope' => 'Academic Calendar',
        'name' => 'gibbonYearGroupIDList',
    ]);
    $value = $stmt->fetchColumn();

    return ac_parseIDList(is_string($value) ? $value : '');
}

/**
 * Filter a list of year groups by enabled settings.
 *
 * If no enabled list is configured, all rows are returned unchanged.
 *
 * @param array<int, array<string, mixed>> $yearGroups Year-group rows.
 * @param string[] $enabledYearGroupIDs Enabled year-group IDs.
 *
 * @return array<int, array<string, mixed>> Filtered year-group rows.
 */
function ac_filterYearGroupsByEnabled(array $yearGroups, array $enabledYearGroupIDs): array
{
    if (empty($enabledYearGroupIDs)) {
        return $yearGroups;
    }

    $enabledLookup = array_flip($enabledYearGroupIDs);

    return array_values(array_filter($yearGroups, function ($group) use ($enabledLookup) {
        $groupID = (string) ($group['gibbonYearGroupID'] ?? ($group['value'] ?? ''));
        return $groupID !== '' && isset($enabledLookup[$groupID]);
    }));
}

/**
 * Normalize year-group rows from mixed gateway shapes.
 *
 * Supports rows keyed by either `gibbonYearGroupID` (module query style)
 * or `value` (core YearGroupGateway style).
 *
 * @param array<int, array<string, mixed>> $rows Raw year-group rows.
 *
 * @return array<int, array<string, mixed>> Normalized rows.
 */
function ac_normalizeYearGroupRows(array $rows): array
{
    $normalized = [];

    foreach ($rows as $row) {
        $id = (string) ($row['gibbonYearGroupID'] ?? ($row['value'] ?? ''));
        if ($id === '') {
            continue;
        }

        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $nameShort = trim((string) ($row['nameShort'] ?? ''));
        if ($nameShort === '') {
            $nameShort = $name;
        }

        $normalized[] = [
            'gibbonYearGroupID' => $id,
            'name' => $name,
            'nameShort' => $nameShort,
            'sequenceNumber' => $row['sequenceNumber'] ?? null,
        ];
    }

    return $normalized;
}

/**
 * Build a map of year-group ID => display label.
 *
 * @param array<int, array<string, mixed>> $rows Normalized year-group rows.
 *
 * @return array<string, string> Lookup map.
 */
function ac_buildYearGroupMap(array $rows): array
{
    $map = [];

    foreach ($rows as $row) {
        $id = (string) ($row['gibbonYearGroupID'] ?? '');
        if ($id === '') {
            continue;
        }

        $label = trim((string) ($row['nameShort'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($row['name'] ?? ''));
        }

        if ($label !== '') {
            $map[$id] = $label;
        }
    }

    return $map;
}

/**
 * Normalize student rows to child selector row shape.
 *
 * Supports rows keyed by either `childPersonID` (module query style)
 * or `gibbonPersonID` (core StudentGateway style).
 *
 * @param array<int, array<string, mixed>> $rows Raw child/student rows.
 *
 * @return array<int, array<string, mixed>> Normalized child rows.
 */
function ac_normalizeChildRows(array $rows): array
{
    $normalized = [];

    foreach ($rows as $row) {
        $childPersonID = (string) ($row['childPersonID'] ?? ($row['gibbonPersonID'] ?? ''));
        if ($childPersonID === '') {
            continue;
        }

        $normalized[] = [
            'childPersonID' => $childPersonID,
            'preferredName' => (string) ($row['preferredName'] ?? ''),
            'surname' => (string) ($row['surname'] ?? ''),
        ];
    }

    return $normalized;
}

/**
 * Filter planner event rows by enabled year groups.
 *
 * Rows without a course year-group list are kept to avoid accidental data loss.
 *
 * @param array<int, array<string, mixed>> $rows Planner event rows.
 * @param string[] $enabledYearGroupIDs Enabled year-group IDs.
 *
 * @return array<int, array<string, mixed>> Filtered planner event rows.
 */
function ac_filterEventRowsByEnabledYearGroups(array $rows, array $enabledYearGroupIDs): array
{
    if (empty($enabledYearGroupIDs)) {
        return $rows;
    }

    $enabledLookup = array_flip($enabledYearGroupIDs);

    return array_values(array_filter($rows, function ($row) use ($enabledLookup) {
        $courseYearGroups = ac_parseIDList((string) ($row['gibbonYearGroupIDList'] ?? ''));

        if (empty($courseYearGroups)) {
            return true;
        }

        foreach ($courseYearGroups as $yearGroupID) {
            if (isset($enabledLookup[$yearGroupID])) {
                return true;
            }
        }

        return false;
    }));
}

/**
 * Check whether a student belongs to at least one enabled year group.
 *
 * Used by dashboard hooks to decide whether calendar content should be shown.
 *
 * @param \PDO $pdo Legacy core PDO connection.
 * @param string $gibbonSchoolYearID Active school-year ID.
 * @param string $gibbonPersonID Student person ID.
 * @param string[] $enabledYearGroupIDs Enabled year-group IDs.
 *
 * @return bool `true` when student has enrolment in an enabled group.
 */
function ac_isPersonInEnabledYearGroups(\PDO $pdo, string $gibbonSchoolYearID, string $gibbonPersonID, array $enabledYearGroupIDs): bool
{
    if (empty($enabledYearGroupIDs)) {
        return true;
    }

    if ($gibbonSchoolYearID === '' || $gibbonPersonID === '') {
        return false;
    }

    $sql = "
        SELECT COUNT(*) 
        FROM gibbonStudentEnrolment
        WHERE gibbonSchoolYearID = :gibbonSchoolYearID
          AND gibbonPersonID = :gibbonPersonID
          AND FIND_IN_SET(gibbonYearGroupID, :enabledYearGroupIDs)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'gibbonSchoolYearID' => $gibbonSchoolYearID,
        'gibbonPersonID' => $gibbonPersonID,
        'enabledYearGroupIDs' => implode(',', $enabledYearGroupIDs),
    ]);
    $count = $stmt->fetchColumn();

    return (int) $count > 0;
}
