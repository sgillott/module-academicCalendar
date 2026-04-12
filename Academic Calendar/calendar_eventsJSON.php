<?php

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\AcademicCalendar\Domain\AcademicCalendarEventGateway;

require_once '../../gibbon.php';
require_once './moduleFunctions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isActionAccessible($guid, $connection2, '/modules/Academic Calendar/calendar_view.php')) {
    echo json_encode([]);
    exit;
}

$dateStart = $_GET['start'] ?? '';
$dateEnd = $_GET['end'] ?? '';

if (empty($dateStart) || empty($dateEnd)) {
    echo json_encode([]);
    exit;
}

$dateStart = date('Y-m-d H:i:s', strtotime($dateStart));
$dateEnd = date('Y-m-d H:i:s', strtotime($dateEnd));

$roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
$gibbonPersonID = (string) $session->get('gibbonPersonID');
$gibbonSchoolYearID = (string) $session->get('gibbonSchoolYearID');

$yearGroupID = trim((string) ($_GET['yearGroupID'] ?? ''));
$childPersonID = trim((string) ($_GET['childPersonID'] ?? ''));

if ($yearGroupID !== '' && !ctype_digit($yearGroupID)) {
    $yearGroupID = '';
}
if ($childPersonID !== '' && !ctype_digit($childPersonID)) {
    $childPersonID = '';
}

$eventGateway = $container->get(AcademicCalendarEventGateway::class);
$settingGateway = $container->get(SettingGateway::class);
$customColors = ac_getColorMap($settingGateway);
$enabledYearGroupIDs = ac_getEnabledYearGroupIDs($settingGateway);

$rows = [];
if ($roleCategory === 'Student') {
    $rows = $eventGateway->selectStudentEvents($gibbonSchoolYearID, $gibbonPersonID, $dateStart, $dateEnd);
} elseif ($roleCategory === 'Parent' && $childPersonID !== '') {
    $rows = $eventGateway->selectParentEvents($gibbonSchoolYearID, $gibbonPersonID, $childPersonID, $dateStart, $dateEnd);
} elseif ($roleCategory === 'Staff') {
    $rows = $eventGateway->selectStaffEvents($gibbonSchoolYearID, $dateStart, $dateEnd, $yearGroupID !== '' ? $yearGroupID : null);
}
$rows = ac_filterEventRowsByEnabledYearGroups($rows, $enabledYearGroupIDs);

$events = [];
$absoluteURL = $session->get('absoluteURL');
$yearGroupMap = [];
if ($roleCategory === 'Staff') {
    $allYearGroups = $eventGateway->selectAllYearGroupsBySchoolYear($gibbonSchoolYearID);
    $allYearGroups = ac_filterYearGroupsByEnabled($allYearGroups, $enabledYearGroupIDs);
    foreach ($allYearGroups as $group) {
        $groupID = (string) ($group['gibbonYearGroupID'] ?? '');
        if ($groupID === '') {
            continue;
        }

        $label = trim((string) ($group['nameShort'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($group['name'] ?? ''));
        }

        if ($label !== '') {
            $yearGroupMap[$groupID] = $label;
        }
    }
}
foreach ($rows as $row) {
    $type = trim((string) ($row['markbookType'] ?? ''));
    if ($type === '') {
        $type = __('Homework');
    }

    $homeworkTitle = trim((string) ($row['markbookName'] ?? ''));
    if ($homeworkTitle === '') {
        $homeworkTitle = trim((string) ($row['homeworkName'] ?? __('Homework')));
    }

    $courseShort = trim((string) ($row['courseNameShort'] ?? ''));
    $classShort = trim((string) ($row['classNameShort'] ?? ''));
    if ($courseShort !== '' && $classShort !== '') {
        $subject = $courseShort.'.'.$classShort;
    } else {
        $subject = trim($courseShort.$classShort);
    }
    if ($subject === '') {
        $subject = __('Homework');
    }

    $title = $subject;
    if ($homeworkTitle !== '' && mb_strtolower($homeworkTitle) !== mb_strtolower($subject)) {
        $title .= ' - '.$homeworkTitle;
    }

    $yearGroupsText = '';
    if ($roleCategory === 'Staff') {
        $prefixes = [];
        $yearGroupIDList = array_filter(array_map('trim', explode(',', (string) ($row['gibbonYearGroupIDList'] ?? ''))));
        foreach ($yearGroupIDList as $groupID) {
            if (isset($yearGroupMap[$groupID])) {
                $prefixes[] = $yearGroupMap[$groupID];
            }
        }

        if (!empty($prefixes)) {
            $yearGroupsText = implode('/', $prefixes);
            $title = '('.$yearGroupsText.') '.$title;
        }
    }

    $color = $customColors[$type] ?? ac_colorFromPalette($type);

    $query = [
        'q' => '/modules/Planner/planner_view_full.php',
        'gibbonPlannerEntryID' => (string) $row['gibbonPlannerEntryID'],
        'gibbonCourseClassID' => (string) $row['gibbonCourseClassID'],
        'viewBy' => 'class',
    ];

    if ($roleCategory === 'Parent' && $childPersonID !== '') {
        $query['gibbonPersonID'] = $childPersonID;
        $query['search'] = $childPersonID;
    } elseif ($roleCategory === 'Student') {
        $query['gibbonPersonID'] = $gibbonPersonID;
        $query['search'] = $gibbonPersonID;
    }

    $events[] = [
        'id' => (string) $row['gibbonPlannerEntryID'],
        'title' => $title,
        'start' => date('c', strtotime((string) $row['homeworkDueDateTime'])),
        'allDay' => false,
        'url' => $absoluteURL.'/index.php?'.http_build_query($query),
        'backgroundColor' => $color,
        'borderColor' => $color,
        'extendedProps' => [
            'subject' => $subject,
            'homeworkTitle' => $homeworkTitle,
            'yearGroups' => $yearGroupsText,
            'type' => $type,
            'source' => 'Planner',
        ],
    ];
}

echo json_encode($events);
