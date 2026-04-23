<?php

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

$showAssessmentEvents = (string) ($_POST['showAssessmentEvents'] ?? 'Y');
$showAssessmentEvents = $showAssessmentEvents === 'N' ? 'N' : 'Y';

$defaultStaffView = (string) ($_POST['defaultStaffView'] ?? 'all');
$defaultStaffView = $defaultStaffView === 'yearGroup' ? 'yearGroup' : 'all';
$staffEventFormat = ac_normalizeStaffEventFormat((string) ($_POST['staffEventFormat'] ?? 'codeTitle'));
$assessmentDisplayBasis = ac_normalizeAssessmentDisplayBasis((string) ($_POST['assessmentDisplayBasis'] ?? 'courseShortName'));
$mergeSameDayAssessments = ac_normalizeYesNo((string) ($_POST['mergeSameDayAssessments'] ?? 'N'));
$useAssessmentClassificationColorInCalendar = ac_normalizeYesNo((string) ($_POST['useAssessmentClassificationColorInCalendar'] ?? 'N'));
$defaultAssessmentFilterPosted = $_POST['defaultAssessmentFilter'] ?? [];
if (!is_array($defaultAssessmentFilterPosted)) {
    $defaultAssessmentFilterPosted = [];
}
$defaultAssessmentFilter = [
    'formative' => in_array('formative', $defaultAssessmentFilterPosted, true) ? 'Y' : 'N',
    'summative' => in_array('summative', $defaultAssessmentFilterPosted, true) ? 'Y' : 'N',
    'none' => in_array('none', $defaultAssessmentFilterPosted, true) ? 'Y' : 'N',
];
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
$assessmentClassificationColors = ac_decodeAssessmentClassificationColors(json_encode([
    'formative' => (string) ($_POST['assessmentClassificationColor_formative'] ?? ''),
    'summative' => (string) ($_POST['assessmentClassificationColor_summative'] ?? ''),
    'none' => (string) ($_POST['assessmentClassificationColor_none'] ?? ''),
]));
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

$staffEventFormatSetting = $settingGateway->getSettingByScope('Academic Calendar', 'staffEventFormat', true);
if (empty($staffEventFormatSetting)) {
    $insertSuccess = $pdo->statement(
        "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
         VALUES (:scope, :name, :nameDisplay, :description, :value)",
        [
            'scope' => 'Academic Calendar',
            'name' => 'staffEventFormat',
            'nameDisplay' => 'Homework Format in Staff Calendar',
            'description' => 'Choose how homework titles appear for staff in the calendar. This controls how course, class code, year group, and title are combined.',
            'value' => 'codeTitle',
        ]
    );
    $partialFail = !$insertSuccess || $partialFail;
}

// Backward compatibility: ensure settings added in later versions exist on already-installed modules.
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
            'value' => 'Y',
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
            'description' => 'Default user filter for formative and summative assessment events.',
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
            'nameDisplay' => 'Default Summative Weekly Threshold',
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
            'nameDisplay' => 'Summative Weekly Threshold by Year Group',
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
            'description' => 'Choose whether the summative overview shows calendar weeks or academic weeks.',
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
            'nameDisplay' => 'Assessment Format in Staff Calendar',
            'description' => 'Choose which course field is used when naming assessment events for staff. This also controls how same-day assessment merges are labelled.',
            'value' => 'courseShortName',
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
            'description' => 'Merge assessment rows that share the same display value on the same day.',
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
            'nameDisplay' => 'Assessment Classification Colours',
            'description' => 'JSON map of formative, summative, and not classified colours.',
            'value' => json_encode(ac_getDefaultAssessmentClassificationColors()),
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
            'nameDisplay' => 'Use Assessment Classification Colour in Calendar',
            'description' => 'When enabled, assessment events on the Homework/Assessment Calendar use the formative, summative, or not classified colours.',
            'value' => 'N',
        ]
    );
    $partialFail = !$insertSuccess || $partialFail;
}

$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'showHomeworkEvents', $showHomeworkEvents) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'showAssessmentEvents', $showAssessmentEvents) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'defaultAssessmentFilter', json_encode($defaultAssessmentFilter)) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'staffEventFormat', $staffEventFormat) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'gibbonYearGroupIDList', $enabledYearGroupIDList) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'summativeWeeklyThresholdDefault', $summativeWeeklyThresholdDefault) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'summativeWeeklyThresholdByYearGroup', json_encode($summativeWeeklyThresholdByYearGroup)) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'overviewWeekNumberMode', $overviewWeekNumberMode) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'assessmentDisplayBasis', $assessmentDisplayBasis) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'mergeSameDayAssessments', $mergeSameDayAssessments) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'assessmentClassificationColors', json_encode($assessmentClassificationColors)) || $partialFail;
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
    $classification = strtolower(trim((string) ($postedClassifications[$hash] ?? '')));
    if (!in_array($classification, ['', 'formative', 'summative'], true)) {
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
header("Location: {$URL}");
