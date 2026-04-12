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

function ac_normalizeHexColor(string $color): ?string
{
    $color = trim($color);
    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color) !== 1) {
        return null;
    }

    return strtoupper($color);
}

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

function ac_getColorMap(SettingGateway $settingGateway): array
{
    $value = $settingGateway->getSettingByScope('Academic Calendar', 'eventTypeColors');

    return ac_decodeColorMap($value ?: '');
}

function ac_colorFromPalette(string $key): string
{
    static $palette = [
        '#1F77B4', '#FF7F0E', '#2CA02C', '#D62728', '#9467BD',
        '#8C564B', '#E377C2', '#7F7F7F', '#BCBD22', '#17BECF',
    ];

    $index = abs(crc32($key)) % count($palette);

    return $palette[$index];
}

function ac_getShowWeekends(SettingGateway $settingGateway): bool
{
    $value = (string) $settingGateway->getSettingByScope('Academic Calendar', 'showWeekends');

    return $value !== 'N';
}

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

function ac_getEnabledYearGroupIDs(SettingGateway $settingGateway): array
{
    $value = $settingGateway->getSettingByScope('Academic Calendar', 'gibbonYearGroupIDList');

    return ac_parseIDList(is_string($value) ? $value : '');
}

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

function ac_filterYearGroupsByEnabled(array $yearGroups, array $enabledYearGroupIDs): array
{
    if (empty($enabledYearGroupIDs)) {
        return $yearGroups;
    }

    $enabledLookup = array_flip($enabledYearGroupIDs);

    return array_values(array_filter($yearGroups, function ($group) use ($enabledLookup) {
        $groupID = (string) ($group['gibbonYearGroupID'] ?? '');
        return $groupID !== '' && isset($enabledLookup[$groupID]);
    }));
}

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
