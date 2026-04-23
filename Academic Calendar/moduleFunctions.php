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
 * Decode and sanitize the event-type metadata JSON.
 *
 * Expected format:
 * `{eventType: {"visible":"Y|N","classification":"|formative|summative"}}`.
 *
 * @param string|null $json JSON string from module settings.
 *
 * @return array<string, array{visible:string,classification:string}>
 */
function ac_decodeEventTypeMeta(?string $json): array
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
        if ($type === '' || !is_array($value)) {
            continue;
        }

        $visible = strtoupper((string) ($value['visible'] ?? 'Y'));
        $visible = $visible === 'N' ? 'N' : 'Y';

        $classification = strtolower(trim((string) ($value['classification'] ?? '')));
        if (!in_array($classification, ['', 'formative', 'summative'], true)) {
            $classification = '';
        }

        $clean[$type] = [
            'visible' => $visible,
            'classification' => $classification,
        ];
    }

    return $clean;
}

/**
 * Retrieve saved event-type metadata from module settings.
 *
 * @param SettingGateway $settingGateway Core setting gateway service.
 *
 * @return array<string, array{visible:string,classification:string}>
 */
function ac_getEventTypeMeta(SettingGateway $settingGateway): array
{
    $value = $settingGateway->getSettingByScope('Academic Calendar', 'eventTypeMeta');

    return ac_decodeEventTypeMeta($value ?: '');
}

/**
 * Decode and sanitize default assessment filter JSON.
 *
 * Expected format: {"formative":"Y|N","summative":"Y|N","none":"Y|N"}.
 *
 * @param string|null $json JSON string from module settings.
 *
 * @return array{formative:string,summative:string,none:string}
 */
function ac_decodeDefaultAssessmentFilter(?string $json): array
{
    $defaults = [
        'formative' => 'Y',
        'summative' => 'Y',
        'none' => 'Y',
    ];

    if (empty($json)) {
        return $defaults;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return $defaults;
    }

    $formative = strtoupper((string) ($data['formative'] ?? 'Y'));
    $summative = strtoupper((string) ($data['summative'] ?? 'Y'));
    $none = strtoupper((string) ($data['none'] ?? 'Y'));

    $defaults['formative'] = $formative === 'N' ? 'N' : 'Y';
    $defaults['summative'] = $summative === 'N' ? 'N' : 'Y';
    $defaults['none'] = $none === 'N' ? 'N' : 'Y';

    return $defaults;
}

/**
 * Retrieve default assessment filter settings.
 *
 * @param SettingGateway $settingGateway Core setting gateway service.
 *
 * @return array{formative:string,summative:string,none:string}
 */
function ac_getDefaultAssessmentFilter(SettingGateway $settingGateway): array
{
    $value = $settingGateway->getSettingByScope('Academic Calendar', 'defaultAssessmentFilter');

    return ac_decodeDefaultAssessmentFilter($value ?: '');
}

/**
 * Normalize the staff calendar event label format setting.
 *
 * Supported values:
 * - `codeTitle`: class code and homework title
 * - `yearGroupCodeTitle`: year group, class code, and homework title
 * - `subjectCodeTitle`: subject name, class code, and homework title
 *
 * @param string|null $value Raw setting value.
 *
 * @return string Normalized format key.
 */
function ac_normalizeStaffEventFormat(?string $value): string
{
    $value = trim((string) $value);
    $allowed = [
        'codeTitle',
        'yearGroupCodeTitle',
        'subjectCodeTitle',
    ];

    return in_array($value, $allowed, true) ? $value : 'codeTitle';
}

/**
 * Retrieve the configured staff homework event label format.
 *
 * @param SettingGateway $settingGateway Core setting gateway service.
 *
 * @return string Normalized format key.
 */
function ac_getStaffEventFormat(SettingGateway $settingGateway): string
{
    $value = $settingGateway->getSettingByScope('Academic Calendar', 'staffEventFormat');

    return ac_normalizeStaffEventFormat($value ?: '');
}

/**
 * Determine if any event types are classified as formative or summative.
 *
 * @param array<string, array{visible:string,classification:string}> $meta
 *
 * @return bool
 */
function ac_hasAssessmentClassifications(array $meta): bool
{
    foreach ($meta as $row) {
        $classification = (string) ($row['classification'] ?? '');
        if ($classification === 'formative' || $classification === 'summative') {
            return true;
        }
    }

    return false;
}

/**
 * Get available assessment classifications from event-type metadata.
 *
 * By default, hidden types (`visible = N`) are ignored.
 *
 * @param array<string, array{visible:string,classification:string}> $meta
 * @param bool $visibleOnly When true, only include visible types.
 *
 * @return array{formative:bool,summative:bool,none:bool}
 */
function ac_getAvailableAssessmentClassifications(array $meta, bool $visibleOnly = true): array
{
    $available = [
        'formative' => false,
        'summative' => false,
        'none' => false,
    ];

    foreach ($meta as $row) {
        $visible = strtoupper((string) ($row['visible'] ?? 'Y'));
        if ($visibleOnly && $visible === 'N') {
            continue;
        }

        $classification = (string) ($row['classification'] ?? '');
        if ($classification === 'formative') {
            $available['formative'] = true;
        } elseif ($classification === 'summative') {
            $available['summative'] = true;
        } else {
            $available['none'] = true;
        }
    }

    return $available;
}

/**
 * Mix a hex color with white by a given ratio.
 *
 * @param string $color Hex color.
 * @param float $whiteRatio Ratio of white to mix in, from 0 to 1.
 *
 * @return string Mixed hex color.
 */
function ac_mixHexWithWhite(string $color, float $whiteRatio): string
{
    $normalized = ac_normalizeHexColor($color);
    if ($normalized === null) {
        return '#FFFFFF';
    }

    $whiteRatio = max(0.0, min(1.0, $whiteRatio));
    $baseRatio = 1 - $whiteRatio;

    $r = (int) round((hexdec(substr($normalized, 1, 2)) * $baseRatio) + (255 * $whiteRatio));
    $g = (int) round((hexdec(substr($normalized, 3, 2)) * $baseRatio) + (255 * $whiteRatio));
    $b = (int) round((hexdec(substr($normalized, 5, 2)) * $baseRatio) + (255 * $whiteRatio));

    return sprintf('#%02X%02X%02X', $r, $g, $b);
}

/**
 * Get default assessment classification border colors.
 *
 * @return array{formative:string,summative:string,none:string}
 */
function ac_getDefaultAssessmentClassificationColors(): array
{
    return [
        'formative' => '#F97316',
        'summative' => '#1D4ED8',
        'none' => '#9CA3AF',
    ];
}

/**
 * Decode assessment classification color JSON.
 *
 * @param string|null $json Stored JSON map.
 *
 * @return array{formative:string,summative:string,none:string}
 */
function ac_decodeAssessmentClassificationColors(?string $json): array
{
    $defaults = ac_getDefaultAssessmentClassificationColors();
    if (empty($json)) {
        return $defaults;
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return $defaults;
    }

    foreach (array_keys($defaults) as $key) {
        $color = ac_normalizeHexColor((string) ($decoded[$key] ?? ''));
        if ($color !== null) {
            $defaults[$key] = $color;
        }
    }

    return $defaults;
}

/**
 * Retrieve assessment classification border colors from settings.
 *
 * @param SettingGateway $settingGateway Core setting gateway service.
 *
 * @return array{formative:string,summative:string,none:string}
 */
function ac_getAssessmentClassificationColors(SettingGateway $settingGateway): array
{
    $value = $settingGateway->getSettingByScope('Academic Calendar', 'assessmentClassificationColors');

    return ac_decodeAssessmentClassificationColors($value ?: '');
}

/**
 * Get whether the calendar should use assessment classification colours.
 */
function ac_getUseAssessmentClassificationColorInCalendar(SettingGateway $settingGateway): bool
{
    $value = $settingGateway->getSettingByScope('Academic Calendar', 'useAssessmentClassificationColorInCalendar');

    return ac_normalizeYesNo($value ?: 'N') === 'Y';
}

/**
 * Build assessment classification styles from configured border colors.
 *
 * @param array{formative:string,summative:string,none:string} $colors Border colors.
 *
 * @return array<string, array{border:string,highlight:string}>
 */
function ac_buildAssessmentClassificationStyles(array $colors): array
{
    return [
        'formative' => [
            'border' => $colors['formative'],
            'highlight' => ac_mixHexWithWhite($colors['formative'], 0.92),
        ],
        'summative' => [
            'border' => $colors['summative'],
            'highlight' => ac_mixHexWithWhite($colors['summative'], 0.92),
        ],
        'none' => [
            'border' => $colors['none'],
            'highlight' => ac_mixHexWithWhite($colors['none'], 0.96),
        ],
    ];
}

/**
 * Resolve the background colour for a markbook assessment event.
 *
 * Markbook column colours always win. After that, the module can prefer
 * either event type colours or assessment classification colours.
 */
function ac_resolveAssessmentEventColor(
    ?string $assessmentColor,
    string $type,
    string $classification,
    array $eventTypeColors,
    array $classificationStyles,
    bool $useClassificationColor = false
): string {
    $classificationKey = in_array($classification, ['formative', 'summative'], true) ? $classification : 'none';
    $classificationColor = ac_normalizeHexColor((string) ($classificationStyles[$classificationKey]['highlight'] ?? ''));
    $directColor = ac_normalizeHexColor((string) $assessmentColor);

    if ($useClassificationColor) {
        return $classificationColor ?? $directColor ?? ac_colorFromPalette($type);
    }

    if ($directColor !== null) {
        return $directColor;
    }

    $eventTypeColor = ac_normalizeHexColor((string) ($eventTypeColors[$type] ?? ''));
    if ($eventTypeColor === null) {
        $eventTypeColor = ac_colorFromPalette($type);
    }

    return $eventTypeColor ?? $classificationColor ?? ac_colorFromPalette($type);
}

/**
 * Resolve the border colour for a markbook assessment event when classification
 * colours are enabled in the calendar.
 */
function ac_resolveAssessmentClassificationBorderColor(
    string $classification,
    array $classificationStyles
): ?string {
    $classificationKey = in_array($classification, ['formative', 'summative'], true) ? $classification : 'none';

    return ac_normalizeHexColor((string) ($classificationStyles[$classificationKey]['border'] ?? ''));
}

/**
 * Normalize the assessment display basis setting.
 *
 * @param string|null $value Raw setting value.
 *
 * @return string Normalized basis key.
 */
function ac_normalizeAssessmentDisplayBasis(?string $value): string
{
    $value = trim((string) $value);
    $allowed = [
        'classCode',
        'courseShortName',
        'courseName',
        'learningArea',
    ];

    return in_array($value, $allowed, true) ? $value : 'courseShortName';
}

/**
 * Retrieve the configured assessment display basis.
 *
 * @param SettingGateway $settingGateway Core setting gateway service.
 *
 * @return string Normalized basis key.
 */
function ac_getAssessmentDisplayBasis(SettingGateway $settingGateway): string
{
    $value = $settingGateway->getSettingByScope('Academic Calendar', 'assessmentDisplayBasis');

    return ac_normalizeAssessmentDisplayBasis($value ?: '');
}

/**
 * Normalize merge-same-day-assessments setting.
 *
 * @param string|null $value Raw setting value.
 *
 * @return string `Y` or `N`.
 */
function ac_normalizeYesNo(?string $value): string
{
    return strtoupper(trim((string) $value)) === 'N' ? 'N' : 'Y';
}

/**
 * Determine whether same-day assessments with the same display value should merge.
 *
 * @param SettingGateway $settingGateway Core setting gateway service.
 *
 * @return bool
 */
function ac_getMergeSameDayAssessments(SettingGateway $settingGateway): bool
{
    $value = $settingGateway->getSettingByScope('Academic Calendar', 'mergeSameDayAssessments');

    return ac_normalizeYesNo($value ?: 'N') === 'Y';
}

/**
 * Build the available assessment label values from a row.
 *
 * @param array<string, mixed> $row Assessment row data.
 *
 * @return array{classCode:string,courseShortName:string,courseName:string,learningArea:string}
 */
function ac_getAssessmentDisplayOptions(array $row): array
{
    $courseShortName = trim((string) ($row['courseNameShort'] ?? ''));
    $courseName = trim((string) ($row['courseName'] ?? ''));
    $classShortName = trim((string) ($row['classNameShort'] ?? ''));
    $learningArea = trim((string) ($row['learningArea'] ?? ''));

    $classCode = '';
    if ($courseShortName !== '' && $classShortName !== '') {
        $classCode = $courseShortName.'.'.$classShortName;
    } elseif ($courseShortName !== '' || $classShortName !== '') {
        $classCode = trim($courseShortName.$classShortName);
    }

    return [
        'classCode' => $classCode,
        'courseShortName' => $courseShortName,
        'courseName' => $courseName,
        'learningArea' => $learningArea,
    ];
}

/**
 * Resolve an assessment display value from the configured basis.
 *
 * @param array<string, mixed> $row Assessment row data.
 * @param string $basis Display basis.
 * @param string $fallbackLabel Fallback label.
 *
 * @return string
 */
function ac_getAssessmentDisplayValue(array $row, string $basis, string $fallbackLabel): string
{
    $basis = ac_normalizeAssessmentDisplayBasis($basis);
    $options = ac_getAssessmentDisplayOptions($row);

    if (!empty($options[$basis])) {
        return (string) $options[$basis];
    }

    foreach (['learningArea', 'courseShortName', 'courseName', 'classCode'] as $fallbackBasis) {
        if (!empty($options[$fallbackBasis])) {
            return (string) $options[$fallbackBasis];
        }
    }

    return $fallbackLabel;
}

/**
 * Build a class label for assessment tooltips.
 *
 * Prefers the Gibbon-style `courseShort.classShort` format and falls back to
 * the most specific class text available.
 *
 * @param array<string, mixed> $row Assessment row data.
 *
 * @return string
 */
function ac_getAssessmentClassLabel(array $row): string
{
    $courseShortCode = trim((string) ($row['courseNameShort'] ?? ''));
    $classShortCode = trim((string) ($row['classNameShort'] ?? ''));
    if ($courseShortCode !== '' && $classShortCode !== '') {
        return $courseShortCode.'.'.$classShortCode;
    }

    if ($courseShortCode !== '' || $classShortCode !== '') {
        return trim($courseShortCode.$classShortCode);
    }

    return trim((string) ($row['className'] ?? ''));
}

/**
 * Build formatted tooltip lines for a single assessment row.
 *
 * @param array<string, mixed> $row Assessment row data.
 * @param string $assessmentTitle Display title for the assessment.
 * @param string $type Assessment type label.
 *
 * @return array<int, string>
 */
function ac_buildAssessmentTooltipLines(array $row, string $assessmentTitle, string $type): array
{
    $tooltipLines = [$assessmentTitle];

    $assessmentDate = trim((string) ($row['assessmentDate'] ?? ''));
    if ($assessmentDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $assessmentDate) === 1) {
        $tooltipLines[] = __('Assessment Date').': '.date('j F Y', strtotime($assessmentDate));
    }

    $classLabel = ac_getAssessmentClassLabel($row);
    if ($classLabel !== '') {
        $tooltipLines[] = __('Class').': '.$classLabel;
    }

    if ($type !== '') {
        $tooltipLines[] = __('Type').': '.$type;
    }

    $description = trim((string) ($row['assessmentDescription'] ?? ''));
    if ($description !== '') {
        $tooltipLines[] = __('Details').': '.$description;
    }

    return $tooltipLines;
}

/**
 * Build the merge key for same-day assessment grouping.
 *
 * Matching requires the same date, display value, assessment title,
 * classification and optional staff year-group context.
 *
 * @param array<string, mixed> $row Assessment row data.
 * @param string $subject Resolved subject/display value.
 * @param string $assessmentTitle Assessment title.
 * @param string $classification Assessment classification.
 * @param string $yearGroupsText Optional year-group text used in staff view.
 *
 * @return string
 */
function ac_buildAssessmentMergeKey(
    array $row,
    string $subject,
    string $assessmentTitle,
    string $classification,
    string $yearGroupsText = ''
): string {
    return implode('|', [
        date('Y-m-d', strtotime((string) ($row['assessmentDate'] ?? ''))),
        $subject,
        mb_strtolower($assessmentTitle),
        $classification,
        $yearGroupsText,
    ]);
}

/**
 * Build an assessment event title using the same logic as the calendar.
 *
 * @param string $subject Resolved subject/display value.
 * @param string $assessmentTitle Assessment title.
 * @param string $roleCategory Current role category.
 * @param string $assessmentDisplayBasis Normalized display basis.
 * @param string $yearGroupsText Optional rendered year-group text.
 *
 * @return string
 */
function ac_buildAssessmentEventTitle(
    string $subject,
    string $assessmentTitle,
    string $roleCategory,
    string $assessmentDisplayBasis,
    string $yearGroupsText = ''
): string {
    $title = $subject;
    if ($roleCategory === 'Staff' && $assessmentDisplayBasis === 'learningArea' && $yearGroupsText !== '') {
        $title = '('.$yearGroupsText.') '.$subject;
    }

    if ($assessmentTitle !== '' && mb_strtolower($assessmentTitle) !== mb_strtolower($subject)) {
        $title .= ' - '.$assessmentTitle;
    }

    return $title;
}

/**
 * Format one or more assessment tooltip blocks for HTML title attributes.
 *
 * @param array<int, array<int, string>> $tooltipBlocks Tooltip blocks.
 *
 * @return string
 */
function ac_formatAssessmentTooltipBlocks(array $tooltipBlocks): string
{
    $formattedBlocks = [];
    foreach ($tooltipBlocks as $block) {
        $lines = array_values(array_unique(array_filter(array_map('strval', $block), function ($line) {
            return trim($line) !== '';
        })));

        if (!empty($lines)) {
            $formattedBlocks[] = implode("\n", $lines);
        }
    }

    $formattedBlocks = array_values(array_unique($formattedBlocks));

    return implode("\n\n", $formattedBlocks);
}

/**
 * Build the subject name and class-code parts for a planner or assessment row.
 *
 * @param array<string, mixed> $row Source row containing course/class fields.
 *
 * @return array{0:string,1:string} Subject name and subject code.
 */
function ac_getSubjectParts(array $row): array
{
    $courseName = trim((string) ($row['courseName'] ?? ''));
    $courseShort = trim((string) ($row['courseNameShort'] ?? ''));
    $classShort = trim((string) ($row['classNameShort'] ?? ''));

    $subjectName = $courseName !== '' ? $courseName : '';
    $subjectCode = '';
    if ($courseShort !== '' && $classShort !== '') {
        $subjectCode = $courseShort.'.'.$classShort;
    } else {
        $subjectCode = trim($courseShort.$classShort);
    }

    if ($subjectName === '') {
        $subjectName = $subjectCode;
    }
    if ($subjectCode === '') {
        $subjectCode = $subjectName;
    }

    return [$subjectName, $subjectCode];
}

/**
 * Resolve the standard subject label for homework events by role.
 *
 * @param array<string, mixed> $row Source row containing course/class fields.
 * @param string $fallbackLabel Label to use when no subject data is available.
 * @param string $roleCategory Current role category.
 *
 * @return string
 */
function ac_getSubjectLabel(array $row, string $fallbackLabel, string $roleCategory = 'Student'): string
{
    [$subjectName, $subjectCode] = ac_getSubjectParts($row);

    if ($subjectName === '' && $subjectCode === '') {
        return $fallbackLabel;
    }

    if ($roleCategory === 'Staff') {
        return $subjectCode !== '' ? $subjectCode : $subjectName;
    }

    return $subjectName !== '' ? $subjectName : $subjectCode;
}

/**
 * Convert a course year-group ID list into a slash-separated text prefix.
 *
 * @param string $yearGroupIDList CSV list of year-group IDs.
 * @param array<string, string> $yearGroupMap Lookup of year-group ID => label.
 *
 * @return string
 */
function ac_buildYearGroupsText(string $yearGroupIDList, array $yearGroupMap): string
{
    $prefixes = [];
    $yearGroupIDs = array_filter(array_map('trim', explode(',', $yearGroupIDList)));
    foreach ($yearGroupIDs as $groupID) {
        if (isset($yearGroupMap[$groupID])) {
            $prefixes[] = $yearGroupMap[$groupID];
        }
    }

    return !empty($prefixes) ? implode('/', $prefixes) : '';
}

/**
 * Build the staff-facing homework or assessment label.
 *
 * @param array<string, mixed> $row Source row containing course/class fields.
 * @param string $itemTitle Homework or assessment title.
 * @param string $yearGroupsText Rendered year-group prefix text.
 * @param string $staffEventFormat Normalized staff event format setting.
 *
 * @return string
 */
function ac_buildStaffEventTitle(array $row, string $itemTitle, string $yearGroupsText, string $staffEventFormat): string
{
    [$subjectName, $subjectCode] = ac_getSubjectParts($row);

    if ($staffEventFormat === 'subjectCodeTitle') {
        $prefix = $subjectName !== '' ? $subjectName : $subjectCode;
        if ($subjectCode !== '' && $subjectName !== '' && $subjectCode !== $subjectName) {
            $prefix .= ' ('.$subjectCode.')';
        }

        return $itemTitle !== '' && mb_strtolower($itemTitle) !== mb_strtolower($prefix)
            ? $prefix.' - '.$itemTitle
            : $prefix;
    }

    $prefix = $subjectCode !== '' ? $subjectCode : $subjectName;
    if ($staffEventFormat === 'yearGroupCodeTitle' && $yearGroupsText !== '') {
        $prefix = '('.$yearGroupsText.') '.$prefix;
    }

    return $itemTitle !== '' && mb_strtolower($itemTitle) !== mb_strtolower($prefix)
        ? $prefix.' - '.$itemTitle
        : $prefix;
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
 * Build a deterministic year-group color map from ordered year-group rows.
 *
 * Uses a stable sequential palette keyed by year-group ID so the same
 * year-group keeps the same color throughout the current calendar view.
 *
 * @param array<int, array<string, mixed>> $rows Normalized year-group rows sorted by sequence.
 *
 * @return array<string, string> Map of gibbonYearGroupID => hex color.
 */
function ac_buildYearGroupColorMap(array $rows): array
{
    $palette = [
        '#DC2626', // red
        '#EA580C', // orange
        '#CA8A04', // amber
        '#16A34A', // green
        '#0F766E', // teal
        '#2563EB', // blue
        '#4F46E5', // indigo
        '#9333EA', // violet
        '#DB2777', // pink
        '#475569', // slate
    ];

    $map = [];
    $index = 0;

    foreach ($rows as $row) {
        $yearGroupID = (string) ($row['gibbonYearGroupID'] ?? '');
        if ($yearGroupID === '') {
            continue;
        }

        $map[$yearGroupID] = $palette[$index % count($palette)];
        $index++;
    }

    return $map;
}

/**
 * Choose a readable text color for a given background color.
 *
 * Uses WCAG relative luminance and contrast ratio checks to decide whether
 * light or dark text has better contrast on the supplied background.
 *
 * @param string $backgroundColor Hex background color (`#RRGGBB`).
 * @param string $darkTextColor Preferred dark text color.
 * @param string $lightTextColor Preferred light text color.
 *
 * @return string Selected text color.
 */
function ac_getContrastingTextColor(
    string $backgroundColor,
    string $darkTextColor = '#111827',
    string $lightTextColor = '#FFFFFF'
): string {
    $color = ac_normalizeHexColor($backgroundColor);
    if ($color === null) {
        return $darkTextColor;
    }

    $r = hexdec(substr($color, 1, 2)) / 255;
    $g = hexdec(substr($color, 3, 2)) / 255;
    $b = hexdec(substr($color, 5, 2)) / 255;

    $transform = function (float $channel): float {
        if ($channel <= 0.03928) {
            return $channel / 12.92;
        }

        return pow(($channel + 0.055) / 1.055, 2.4);
    };

    $rLinear = $transform($r);
    $gLinear = $transform($g);
    $bLinear = $transform($b);

    $luminance = (0.2126 * $rLinear) + (0.7152 * $gLinear) + (0.0722 * $bLinear);
    $contrastWithWhite = 1.05 / ($luminance + 0.05);
    $contrastWithBlack = ($luminance + 0.05) / 0.05;

    return $contrastWithWhite >= $contrastWithBlack ? $lightTextColor : $darkTextColor;
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
 * Check whether homework events should be shown.
 *
 * @param SettingGateway $settingGateway Core setting gateway service.
 *
 * @return bool `true` unless setting explicitly equals `N`.
 */
function ac_getShowHomeworkEvents(SettingGateway $settingGateway): bool
{
    $value = (string) $settingGateway->getSettingByScope('Academic Calendar', 'showHomeworkEvents');

    return $value !== 'N';
}

/**
 * Check whether assessment events should be shown.
 *
 * @param SettingGateway $settingGateway Core setting gateway service.
 *
 * @return bool `true` unless setting explicitly equals `N`.
 */
function ac_getShowAssessmentEvents(SettingGateway $settingGateway): bool
{
    $value = (string) $settingGateway->getSettingByScope('Academic Calendar', 'showAssessmentEvents');

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
 * Get default weekly summative threshold.
 *
 * Falls back to 3 if setting is missing or invalid.
 *
 * @param SettingGateway $settingGateway Core setting gateway service.
 *
 * @return int Positive default threshold.
 */
function ac_getSummativeThresholdDefault(SettingGateway $settingGateway): int
{
    $raw = trim((string) $settingGateway->getSettingByScope('Academic Calendar', 'summativeWeeklyThresholdDefault'));
    if ($raw === '' || !ctype_digit($raw)) {
        return 3;
    }

    $value = (int) $raw;

    return $value > 0 ? $value : 3;
}

/**
 * Decode per-year-group weekly summative threshold map.
 *
 * Expected JSON format:
 * `{ "001": 3, "002": 4 }`
 *
 * @param SettingGateway $settingGateway Core setting gateway service.
 *
 * @return array<string, int> Map of year-group ID => threshold.
 */
function ac_getSummativeThresholdByYearGroup(SettingGateway $settingGateway): array
{
    $raw = (string) $settingGateway->getSettingByScope('Academic Calendar', 'summativeWeeklyThresholdByYearGroup');
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $clean = [];
    foreach ($decoded as $yearGroupID => $threshold) {
        $id = trim((string) $yearGroupID);
        if ($id === '' || !ctype_digit($id)) {
            continue;
        }

        if (is_string($threshold)) {
            $threshold = trim($threshold);
            if ($threshold === '' || !ctype_digit($threshold)) {
                continue;
            }
            $threshold = (int) $threshold;
        } elseif (is_int($threshold)) {
            $threshold = (int) $threshold;
        } elseif (is_float($threshold)) {
            $threshold = (int) round($threshold);
        } else {
            continue;
        }

        if ($threshold > 0) {
            $clean[$id] = $threshold;
        }
    }

    return $clean;
}

/**
 * Normalize the overview week-number mode setting.
 *
 * Supported values:
 * - `calendar`: ISO/calendar week number
 * - `academic`: week count from the first academic week, excluding full closure weeks
 *
 * @param string|null $value Raw setting value.
 *
 * @return string Normalized mode key.
 */
function ac_normalizeOverviewWeekNumberMode(?string $value): string
{
    $value = trim((string) $value);

    return in_array($value, ['calendar', 'academic'], true) ? $value : 'academic';
}

/**
 * Retrieve the configured week-number mode for the summative overview.
 *
 * @param SettingGateway $settingGateway Core setting gateway service.
 *
 * @return string Normalized mode key.
 */
function ac_getOverviewWeekNumberMode(SettingGateway $settingGateway): string
{
    $value = $settingGateway->getSettingByScope('Academic Calendar', 'overviewWeekNumberMode');

    return ac_normalizeOverviewWeekNumberMode($value ?: '');
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
 * Build a map of year-group ID => sequence number.
 *
 * @param array<int, array<string, mixed>> $rows Normalized year-group rows.
 *
 * @return array<string, int> Lookup map.
 */
function ac_buildYearGroupSequenceMap(array $rows): array
{
    $map = [];

    foreach ($rows as $row) {
        $id = (string) ($row['gibbonYearGroupID'] ?? '');
        if ($id === '') {
            continue;
        }

        $sequenceNumber = $row['sequenceNumber'] ?? null;
        if ($sequenceNumber === null || $sequenceNumber === '') {
            continue;
        }

        $map[$id] = (int) $sequenceNumber;
    }

    return $map;
}

/**
 * Get the lowest sequence number from a course year-group list.
 *
 * @param string $yearGroupIDList CSV year-group list from the course.
 * @param array<string, int> $sequenceMap Lookup map of ID => sequence.
 *
 * @return int|null Lowest matching sequence number or null.
 */
function ac_getYearGroupSequenceForEvent(string $yearGroupIDList, array $sequenceMap): ?int
{
    $matches = [];

    foreach (ac_parseIDList($yearGroupIDList) as $yearGroupID) {
        if (isset($sequenceMap[$yearGroupID])) {
            $matches[] = (int) $sequenceMap[$yearGroupID];
        }
    }

    if (empty($matches)) {
        return null;
    }

    sort($matches, SORT_NUMERIC);

    return $matches[0];
}

/**
 * Get the primary year-group ID for an event based on the lowest sequence number.
 *
 * @param string $yearGroupIDList CSV year-group list from the course.
 * @param array<string, int> $sequenceMap Lookup map of ID => sequence.
 *
 * @return string|null Primary year-group ID or null when none match.
 */
function ac_getPrimaryYearGroupIDForEvent(string $yearGroupIDList, array $sequenceMap): ?string
{
    $bestYearGroupID = null;
    $bestSequence = null;

    foreach (ac_parseIDList($yearGroupIDList) as $yearGroupID) {
        if (!isset($sequenceMap[$yearGroupID])) {
            continue;
        }

        $sequence = (int) $sequenceMap[$yearGroupID];
        if ($bestSequence === null || $sequence < $bestSequence) {
            $bestSequence = $sequence;
            $bestYearGroupID = $yearGroupID;
        }
    }

    return $bestYearGroupID;
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

/**
 * Export all module settings rows for Academic Calendar.
 *
 * The export structure is intentionally simple JSON so it can be used for
 * backup/restore across uninstall and reinstall cycles, as well as for
 * sharing configuration snapshots during debugging.
 *
 * @param \PDO $pdo Legacy core PDO connection.
 *
 * @return array<string, mixed> Backup payload.
 */
function ac_buildSettingsBackup(\PDO $pdo): array
{
    $sql = "
        SELECT scope, name, nameDisplay, description, value
        FROM gibbonSetting
        WHERE scope = :scope
        ORDER BY name
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['scope' => 'Academic Calendar']);
    $settings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    return [
        'module' => 'Academic Calendar',
        'exportedAt' => gmdate('c'),
        'formatVersion' => '1',
        'settings' => is_array($settings) ? $settings : [],
    ];
}

/**
 * Validate and normalize a settings backup payload.
 *
 * @param mixed $payload Decoded JSON payload.
 *
 * @return array<int, array{scope:string,name:string,nameDisplay:string,description:string,value:string}>|null
 */
function ac_validateSettingsBackup($payload): ?array
{
    if (!is_array($payload)) {
        return null;
    }

    if (($payload['module'] ?? '') !== 'Academic Calendar') {
        return null;
    }

    $settings = $payload['settings'] ?? null;
    if (!is_array($settings)) {
        return null;
    }

    $clean = [];

    foreach ($settings as $row) {
        if (!is_array($row)) {
            continue;
        }

        $scope = trim((string) ($row['scope'] ?? ''));
        $name = trim((string) ($row['name'] ?? ''));
        $nameDisplay = trim((string) ($row['nameDisplay'] ?? ''));
        $description = trim((string) ($row['description'] ?? ''));
        $value = (string) ($row['value'] ?? '');

        if ($scope !== 'Academic Calendar' || $name === '') {
            continue;
        }

        $clean[] = [
            'scope' => $scope,
            'name' => $name,
            'nameDisplay' => $nameDisplay,
            'description' => $description,
            'value' => $value,
        ];
    }

    return !empty($clean) ? $clean : null;
}

/**
 * Insert or update one Academic Calendar setting row.
 *
 * @param \PDO $pdo Legacy core PDO connection.
 * @param array{scope:string,name:string,nameDisplay:string,description:string,value:string} $setting
 *
 * @return bool
 */
function ac_upsertSettingRow(\PDO $pdo, array $setting): bool
{
    $check = $pdo->prepare("
        SELECT gibbonSettingID
        FROM gibbonSetting
        WHERE scope = :scope AND name = :name
    ");
    $check->execute([
        'scope' => $setting['scope'],
        'name' => $setting['name'],
    ]);
    $existingID = $check->fetchColumn();

    if ($existingID !== false) {
        $update = $pdo->prepare("
            UPDATE gibbonSetting
            SET nameDisplay = :nameDisplay,
                description = :description,
                value = :value
            WHERE gibbonSettingID = :gibbonSettingID
        ");

        return $update->execute([
            'nameDisplay' => $setting['nameDisplay'],
            'description' => $setting['description'],
            'value' => $setting['value'],
            'gibbonSettingID' => $existingID,
        ]);
    }

    $insert = $pdo->prepare("
        INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
        VALUES (:scope, :name, :nameDisplay, :description, :value)
    ");

    return $insert->execute([
        'scope' => $setting['scope'],
        'name' => $setting['name'],
        'nameDisplay' => $setting['nameDisplay'],
        'description' => $setting['description'],
        'value' => $setting['value'],
    ]);
}
