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
$enabledYearGroups = $_POST['gibbonYearGroupIDList'] ?? [];
if (!is_array($enabledYearGroups)) {
    $enabledYearGroups = [];
}
$enabledYearGroups = array_values(array_unique(array_filter(array_map('strval', $enabledYearGroups), function ($id) {
    return ctype_digit($id);
})));
$enabledYearGroupIDList = implode(',', $enabledYearGroups);

$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'showWeekends', $showWeekends) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'defaultStaffView', $defaultStaffView) || $partialFail;

// Backward compatibility: ensure new setting exists for already-installed modules.
$homeworkSetting = $settingGateway->getSettingByScope('Academic Calendar', 'showHomeworkEvents', true);
if (empty($homeworkSetting)) {
    $insertSuccess = $pdo->statement(
        "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
         VALUES (:scope, :name, :nameDisplay, :description, :value)",
        [
            'scope' => 'Academic Calendar',
            'name' => 'showHomeworkEvents',
            'nameDisplay' => 'Show Homework Events',
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
            'nameDisplay' => 'Show Assessment Events',
            'description' => 'Show markbook assessment events in the Homework Calendar.',
            'value' => 'Y',
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

$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'showHomeworkEvents', $showHomeworkEvents) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'showAssessmentEvents', $showAssessmentEvents) || $partialFail;
$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'gibbonYearGroupIDList', $enabledYearGroupIDList) || $partialFail;

$types = $pdo->select("
    SELECT DISTINCT type
    FROM gibbonMarkbookColumn
    WHERE type IS NOT NULL AND type <> ''
    ORDER BY type
")->fetchAll(\PDO::FETCH_COLUMN);

$colors = [];
foreach ($types as $type) {
    $type = (string) $type;
    $field = 'typeColor_'.md5($type);
    $color = ac_normalizeHexColor((string) ($_POST[$field] ?? ''));
    if ($color !== null) {
        $colors[$type] = $color;
    }
}

$partialFail = !$settingGateway->updateSettingByScope('Academic Calendar', 'eventTypeColors', json_encode($colors)) || $partialFail;

$URL .= $partialFail ? '&return=error2' : '&return=success0';
header("Location: {$URL}");
