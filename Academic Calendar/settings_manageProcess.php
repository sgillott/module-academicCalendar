<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

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

require_once '../../gibbon.php';
require_once './moduleFunctions.php';

/**
 * Manage Settings process endpoint.
 *
 * Persists module settings and redirects back with standard return codes.
 */
$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address']).'/settings_manage.php';

if (!isActionAccessible($guid, $connection2, '/modules/Academic Calendar/settings_manage.php')) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

$settingGateway = $container->get(SettingGateway::class);
$partialFail = false;

$showWeekends = (string) ($_POST['showWeekends'] ?? 'Y');
$showWeekends = $showWeekends === 'N' ? 'N' : 'Y';

$showHomeworkEvents = (string) ($_POST['showHomeworkEvents'] ?? 'Y');
$showHomeworkEvents = $showHomeworkEvents === 'N' ? 'N' : 'Y';

$showAssessmentEvents = (string) ($_POST['showAssessmentEvents'] ?? 'N');
$showAssessmentEvents = $showAssessmentEvents === 'N' ? 'N' : 'Y';

$defaultStaffView = (string) ($_POST['defaultStaffView'] ?? 'all');
$defaultStaffView = $defaultStaffView === 'yearGroup' ? 'yearGroup' : 'all';
$assessmentDisplayBasis = ac_normalizeAssessmentDisplayBasis((string) ($_POST['assessmentDisplayBasis'] ?? 'classCode'));
$mergeSameDayAssessments = ac_normalizeYesNo((string) ($_POST['mergeSameDayAssessments'] ?? 'N'));
$useAssessmentClassificationColorInCalendar = ac_normalizeYesNo((string) ($_POST['useAssessmentClassificationColorInCalendar'] ?? 'N'));
$addAssessmentClassification = (string) ($_POST['addAssessmentClassification'] ?? '') === 'Y';

$currentClassificationDefinitions = ac_getAssessmentClassificationDefinitions($settingGateway);
$assessmentClassificationDefinitions = [];
$postedClassificationLabels = $_POST['assessmentClassificationLabel'] ?? [];
if (!is_array($postedClassificationLabels)) {
    $postedClassificationLabels = [];
}
$postedClassificationColors = $_POST['assessmentClassificationColor'] ?? [];
if (!is_array($postedClassificationColors)) {
    $postedClassificationColors = [];
}
$postedClassificationOverview = $_POST['assessmentClassificationDisplayInOverview'] ?? [];
if (!is_array($postedClassificationOverview)) {
    $postedClassificationOverview = [];
}
$postedClassificationDeletes = $_POST['assessmentClassificationDelete'] ?? [];
if (!is_array($postedClassificationDeletes)) {
    $postedClassificationDeletes = [];
}
$deleteAssessmentClassification = ac_normalizeAssessmentClassificationKey((string) ($_POST['deleteAssessmentClassification'] ?? ''));
if ($deleteAssessmentClassification !== '' && $deleteAssessmentClassification !== 'none') {
    $postedClassificationDeletes[$deleteAssessmentClassification] = 'Y';
}

foreach (array_keys($postedClassificationLabels) as $rawKey) {
    $key = ac_normalizeAssessmentClassificationKey((string) $rawKey);
    if ($key === '' || $key === 'none' || isset($postedClassificationDeletes[$key])) {
        continue;
    }

    $label = trim((string) ($postedClassificationLabels[$key] ?? ($currentClassificationDefinitions[$key]['label'] ?? '')));
    $color = ac_normalizeHexColor((string) ($postedClassificationColors[$key] ?? ($currentClassificationDefinitions[$key]['color'] ?? '')));
    if ($label === '' || $color === null) {
        continue;
    }

    $assessmentClassificationDefinitions[$key] = [
        'label' => $label,
        'color' => $color,
        'locked' => false,
        'displayInOverview' => in_array($key, $postedClassificationOverview, true) ? 'Y' : 'N',
    ];
}

$newClassificationKeys = [];
if ($addAssessmentClassification) {
    $baseLabel = __('New Classification');
    $baseKey = 'new_classification';
    $key = $baseKey;
    $suffix = 2;
    while (isset($assessmentClassificationDefinitions[$key])) {
        $key = $baseKey.'_'.$suffix;
        $suffix++;
    }

    $label = $baseLabel;
    if ($suffix > 2) {
        $label .= ' '.($suffix - 1);
    }

    $assessmentClassificationDefinitions[$key] = [
        'label' => $label,
        'color' => '#64748B',
        'locked' => false,
        'displayInOverview' => 'N',
    ];
    $newClassificationKeys[$key] = true;
}

$assessmentClassificationDefinitions['none'] = [
    'label' => 'Not Classified',
    'color' => ac_normalizeHexColor((string) ($postedClassificationColors['none'] ?? ($currentClassificationDefinitions['none']['color'] ?? ''))) ?? '#9CA3AF',
    'locked' => true,
    'displayInOverview' => 'N',
];

$defaultAssessmentFilterPosted = $_POST['defaultAssessmentFilter'] ?? [];
if (!is_array($defaultAssessmentFilterPosted)) {
    $defaultAssessmentFilterPosted = [];
}
$defaultAssessmentFilter = [];
foreach (array_keys($assessmentClassificationDefinitions) as $key) {
    $defaultAssessmentFilter[$key] = in_array($key, $defaultAssessmentFilterPosted, true) || isset($newClassificationKeys[$key]) ? 'Y' : 'N';
}
$enabledYearGroups = $_POST['gibbonYearGroupIDList'] ?? [];
if (!is_array($enabledYearGroups)) {
    $enabledYearGroups = [];
}
$enabledYearGroups = array_values(array_unique(array_filter(array_map('strval', $enabledYearGroups), function ($id) {
    return ctype_digit($id);
})));
$enabledYearGroupIDList = implode(',', $enabledYearGroups);
$summativeWeeklyThresholdDefault = trim((string) ($_POST['summativeWeeklyThresholdDefault'] ?? '3'));
if ($summativeWeeklyThresholdDefault === '' || !ctype_digit($summativeWeeklyThresholdDefault) || (int) $summativeWeeklyThresholdDefault < 1) {
    $summativeWeeklyThresholdDefault = '3';
}
$overviewWeekNumberMode = ac_normalizeOverviewWeekNumberMode((string) ($_POST['overviewWeekNumberMode'] ?? 'academic'));
$postedThresholdByYearGroup = $_POST['summativeWeeklyThresholdByYearGroup'] ?? [];
if (!is_array($postedThresholdByYearGroup)) {
    $postedThresholdByYearGroup = [];
}
$summativeWeeklyThresholdByYearGroup = [];
foreach ($postedThresholdByYearGroup as $yearGroupID => $threshold) {
    $yearGroupID = trim((string) $yearGroupID);
    if ($yearGroupID === '' || !ctype_digit($yearGroupID)) {
        continue;
    }

    $threshold = trim((string) $threshold);
    if ($threshold === '') {
        continue;
    }

    if (!ctype_digit($threshold) || (int) $threshold < 1) {
        continue;
    }

    $summativeWeeklyThresholdByYearGroup[$yearGroupID] = (int) $threshold;
}

$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'showWeekends', $showWeekends) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'defaultStaffView', $defaultStaffView) || $partialFail;

// Ensure settings added in later versions exist on already-installed local modules.
$homeworkSetting = $settingGateway->getSettingByScope('Academic Calendar', 'showHomeworkEvents', true);
if (empty($homeworkSetting)) {
    $insertSuccess = $pdo->statement(
        "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
         VALUES (:scope, :name, :nameDisplay, :description, :value)",
        [
            'scope' => 'Academic Calendar',
            'name' => 'showHomeworkEvents',
            'nameDisplay' => 'Show Homework Events on Calendar',
            'description' => 'Show homework events in the Homework Calendar.',
            'value' => 'Y',
        ]
    );
    $partialFail = !$insertSuccess || $partialFail;
}

$assessmentSetting = $settingGateway->getSettingByScope('Academic Calendar', 'showAssessmentEvents', true);
if (empty($assessmentSetting)) {
    $insertSuccess = $pdo->statement(
        "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
         VALUES (:scope, :name, :nameDisplay, :description, :value)",
        [
            'scope' => 'Academic Calendar',
            'name' => 'showAssessmentEvents',
            'nameDisplay' => 'Show Assessment Events on Calendar',
            'description' => 'Show markbook assessment events in the Homework Calendar.',
            'value' => 'N',
        ]
    );
    $partialFail = !$insertSuccess || $partialFail;
}

$eventTypeMetaSetting = $settingGateway->getSettingByScope('Academic Calendar', 'eventTypeMeta', true);
if (empty($eventTypeMetaSetting)) {
    $insertSuccess = $pdo->statement(
        "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
         VALUES (:scope, :name, :nameDisplay, :description, :value)",
        [
            'scope' => 'Academic Calendar',
            'name' => 'eventTypeMeta',
            'nameDisplay' => 'Event Type Meta',
            'description' => 'JSON map of event type visibility and classification metadata.',
            'value' => '{}',
        ]
    );
    $partialFail = !$insertSuccess || $partialFail;
}

$defaultAssessmentFilterSetting = $settingGateway->getSettingByScope('Academic Calendar', 'defaultAssessmentFilter', true);
if (empty($defaultAssessmentFilterSetting)) {
    $insertSuccess = $pdo->statement(
        "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
         VALUES (:scope, :name, :nameDisplay, :description, :value)",
        [
            'scope' => 'Academic Calendar',
            'name' => 'defaultAssessmentFilter',
            'nameDisplay' => 'Default Assessment Filter',
            'description' => 'Default user filter for assessment classifications.',
            'value' => '{"formative":"Y","summative":"Y","none":"Y"}',
        ]
    );
    $partialFail = !$insertSuccess || $partialFail;
}

$yearGroupSetting = $settingGateway->getSettingByScope('Academic Calendar', 'gibbonYearGroupIDList', true);
if (empty($yearGroupSetting)) {
    $insertSuccess = $pdo->statement(
        "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
         VALUES (:scope, :name, :nameDisplay, :description, :value)",
        [
            'scope' => 'Academic Calendar',
            'name' => 'gibbonYearGroupIDList',
            'nameDisplay' => 'Year Groups',
            'description' => 'Academic Calendar is enabled for these year groups.',
            'value' => '',
        ]
    );
    $partialFail = !$insertSuccess || $partialFail;
}

$defaultThresholdSetting = $settingGateway->getSettingByScope('Academic Calendar', 'summativeWeeklyThresholdDefault', true);
if (empty($defaultThresholdSetting)) {
    $insertSuccess = $pdo->statement(
        "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
         VALUES (:scope, :name, :nameDisplay, :description, :value)",
        [
            'scope' => 'Academic Calendar',
            'name' => 'summativeWeeklyThresholdDefault',
            'nameDisplay' => 'Default Overview Weekly Threshold',
            'description' => 'Fallback threshold used when a year group does not have its own setting.',
            'value' => '3',
        ]
    );
    $partialFail = !$insertSuccess || $partialFail;
}

$thresholdByYearGroupSetting = $settingGateway->getSettingByScope('Academic Calendar', 'summativeWeeklyThresholdByYearGroup', true);
if (empty($thresholdByYearGroupSetting)) {
    $insertSuccess = $pdo->statement(
        "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
         VALUES (:scope, :name, :nameDisplay, :description, :value)",
        [
            'scope' => 'Academic Calendar',
            'name' => 'summativeWeeklyThresholdByYearGroup',
            'nameDisplay' => 'Overview Threshold by Year Group',
            'description' => 'JSON map of gibbonYearGroupID to weekly threshold.',
            'value' => '{}',
        ]
    );
    $partialFail = !$insertSuccess || $partialFail;
}

$overviewWeekNumberModeSetting = $settingGateway->getSettingByScope('Academic Calendar', 'overviewWeekNumberMode', true);
if (empty($overviewWeekNumberModeSetting)) {
    $insertSuccess = $pdo->statement(
        "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
         VALUES (:scope, :name, :nameDisplay, :description, :value)",
        [
            'scope' => 'Academic Calendar',
            'name' => 'overviewWeekNumberMode',
            'nameDisplay' => 'Overview Week Number Mode',
            'description' => 'Choose whether the overview shows calendar weeks or academic weeks.',
            'value' => 'academic',
        ]
    );
    $partialFail = !$insertSuccess || $partialFail;
}

$assessmentDisplayBasisSetting = $settingGateway->getSettingByScope('Academic Calendar', 'assessmentDisplayBasis', true);
if (empty($assessmentDisplayBasisSetting)) {
    $insertSuccess = $pdo->statement(
        "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
         VALUES (:scope, :name, :nameDisplay, :description, :value)",
        [
            'scope' => 'Academic Calendar',
            'name' => 'assessmentDisplayBasis',
            'nameDisplay' => 'Calendar Event Display Basis',
            'description' => 'Choose which course field is used when naming homework and assessment events in the calendar.',
            'value' => 'classCode',
        ]
    );
    $partialFail = !$insertSuccess || $partialFail;
}

$mergeSameDayAssessmentsSetting = $settingGateway->getSettingByScope('Academic Calendar', 'mergeSameDayAssessments', true);
if (empty($mergeSameDayAssessmentsSetting)) {
    $insertSuccess = $pdo->statement(
        "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
         VALUES (:scope, :name, :nameDisplay, :description, :value)",
        [
            'scope' => 'Academic Calendar',
            'name' => 'mergeSameDayAssessments',
            'nameDisplay' => 'Merge Same-Day Assessments',
            'description' => 'Merge assessment rows that share the same display value on the same day in the calendar and overview.',
            'value' => 'N',
        ]
    );
    $partialFail = !$insertSuccess || $partialFail;
}

$assessmentClassificationColorsSetting = $settingGateway->getSettingByScope('Academic Calendar', 'assessmentClassificationColors', true);
if (empty($assessmentClassificationColorsSetting)) {
    $insertSuccess = $pdo->statement(
        "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
         VALUES (:scope, :name, :nameDisplay, :description, :value)",
        [
            'scope' => 'Academic Calendar',
            'name' => 'assessmentClassificationColors',
            'nameDisplay' => 'Assessment Classification Metadata',
            'description' => 'JSON map of assessment classification labels, colours, and overview display metadata.',
            'value' => json_encode(ac_encodeAssessmentClassificationDefinitions(ac_getDefaultAssessmentClassificationDefinitions())),
        ]
    );
    $partialFail = !$insertSuccess || $partialFail;
}

$useAssessmentClassificationColorInCalendarSetting = $settingGateway->getSettingByScope('Academic Calendar', 'useAssessmentClassificationColorInCalendar', true);
if (empty($useAssessmentClassificationColorInCalendarSetting)) {
    $insertSuccess = $pdo->statement(
        "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
         VALUES (:scope, :name, :nameDisplay, :description, :value)",
        [
            'scope' => 'Academic Calendar',
            'name' => 'useAssessmentClassificationColorInCalendar',
            'nameDisplay' => 'Override and Use Assessment Classification Colour in Calendar',
            'description' => 'When enabled, assessment events on the Homework/Assessment Calendar use assessment classification colours.',
            'value' => 'N',
        ]
    );
    $partialFail = !$insertSuccess || $partialFail;
}

$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'showHomeworkEvents', $showHomeworkEvents) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'showAssessmentEvents', $showAssessmentEvents) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'defaultAssessmentFilter', json_encode($defaultAssessmentFilter)) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'gibbonYearGroupIDList', $enabledYearGroupIDList) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'summativeWeeklyThresholdDefault', $summativeWeeklyThresholdDefault) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'summativeWeeklyThresholdByYearGroup', json_encode($summativeWeeklyThresholdByYearGroup)) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'overviewWeekNumberMode', $overviewWeekNumberMode) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'assessmentDisplayBasis', $assessmentDisplayBasis) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'mergeSameDayAssessments', $mergeSameDayAssessments) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'assessmentClassificationColors', json_encode(ac_encodeAssessmentClassificationDefinitions($assessmentClassificationDefinitions))) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'useAssessmentClassificationColorInCalendar', $useAssessmentClassificationColorInCalendar) || $partialFail;

$types = $pdo->select("
    SELECT DISTINCT type
    FROM gibbonMarkbookColumn
    WHERE type IS NOT NULL AND type <> ''
    ORDER BY type
")->fetchAll(\PDO::FETCH_COLUMN);

$postedColors = $_POST['typeColor'] ?? [];
if (!is_array($postedColors)) {
    $postedColors = [];
}

$postedVisible = $_POST['typeVisible'] ?? [];
if (!is_array($postedVisible)) {
    $postedVisible = [];
}

$postedClassifications = $_POST['typeClassification'] ?? [];
if (!is_array($postedClassifications)) {
    $postedClassifications = [];
}

$colors = [];
$meta = [];
foreach ($types as $type) {
    $type = (string) $type;
    $hash = md5($type);

    $color = ac_normalizeHexColor((string) ($postedColors[$hash] ?? ''));
    if ($color !== null) {
        $colors[$type] = $color;
    }

    $visible = isset($postedVisible[$hash]) && (string) $postedVisible[$hash] === 'Y' ? 'Y' : 'N';
    $classification = ac_normalizeAssessmentClassificationKey((string) ($postedClassifications[$hash] ?? ''));
    if ($classification === 'none' || !isset($assessmentClassificationDefinitions[$classification])) {
        $classification = '';
    }
    $meta[$type] = [
        'visible' => $visible,
        'classification' => $classification,
    ];
}

$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'eventTypeColors', json_encode($colors)) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'eventTypeMeta', json_encode($meta)) || $partialFail;

$URL .= $partialFail ? '&return=error2' : '&return=success0';
if ($addAssessmentClassification) {
    $URL .= '#acClassificationManageTable';
}
header("Location: {$URL}");
