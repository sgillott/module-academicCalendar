<?php

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\School\YearGroupGateway;
use Gibbon\Module\AcademicCalendar\Domain\AcademicCalendarEventGateway;

require_once '../../gibbon.php';
require_once './moduleFunctions.php';

/**
 * FullCalendar JSON feed endpoint.
 *
 * Returns role-aware homework events for the requested date window.
 */
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

$yearGroupID = ac_sanitizeNumericID($_GET['yearGroupID'] ?? '');
$childPersonID = ac_sanitizeNumericID($_GET['childPersonID'] ?? '');

$eventGateway = $container->get(AcademicCalendarEventGateway::class);
$settingGateway = $container->get(SettingGateway::class);
$yearGroupGateway = $container->get(YearGroupGateway::class);
$customColors = ac_getColorMap($settingGateway);
$eventTypeMeta = ac_getEventTypeMeta($settingGateway);
$defaultAssessmentFilter = ac_getDefaultAssessmentFilter($settingGateway);
$enabledYearGroupIDs = ac_getEnabledYearGroupIDs($settingGateway);
$showHomeworkEvents = ac_getShowHomeworkEvents($settingGateway);
$showAssessmentEvents = ac_getShowAssessmentEvents($settingGateway);
$assessmentFormative = strtoupper((string) ($_GET['assessmentFormative'] ?? $defaultAssessmentFilter['formative']));
$assessmentSummative = strtoupper((string) ($_GET['assessmentSummative'] ?? $defaultAssessmentFilter['summative']));
$assessmentNone = strtoupper((string) ($_GET['assessmentNone'] ?? $defaultAssessmentFilter['none']));
$assessmentFormative = $assessmentFormative === 'N' ? 'N' : 'Y';
$assessmentSummative = $assessmentSummative === 'N' ? 'N' : 'Y';
$assessmentNone = $assessmentNone === 'N' ? 'N' : 'Y';
$canViewMarkbook = isActionAccessible($guid, $connection2, '/modules/Markbook/markbook_view.php');
$canEditMarkbookData = isActionAccessible($guid, $connection2, '/modules/Markbook/markbook_edit_data.php');

$homeworkRows = [];
$assessmentRows = [];
if ($roleCategory === 'Student') {
    if ($showHomeworkEvents) {
        $homeworkRows = $eventGateway->selectStudentEvents($gibbonSchoolYearID, $gibbonPersonID, $dateStart, $dateEnd);
    }
    if ($showAssessmentEvents) {
        $assessmentRows = $eventGateway->selectStudentAssessmentEvents($gibbonSchoolYearID, $gibbonPersonID, $dateStart, $dateEnd);
    }
} elseif ($roleCategory === 'Parent' && $childPersonID !== '') {
    if ($showHomeworkEvents) {
        $homeworkRows = $eventGateway->selectParentEvents($gibbonSchoolYearID, $gibbonPersonID, $childPersonID, $dateStart, $dateEnd);
    }
    if ($showAssessmentEvents) {
        $assessmentRows = $eventGateway->selectParentAssessmentEvents($gibbonSchoolYearID, $gibbonPersonID, $childPersonID, $dateStart, $dateEnd);
    }
} elseif ($roleCategory === 'Staff') {
    if ($showHomeworkEvents) {
        $homeworkRows = $eventGateway->selectStaffEvents($gibbonSchoolYearID, $dateStart, $dateEnd, $yearGroupID !== '' ? $yearGroupID : null);
    }
    if ($showAssessmentEvents) {
        $assessmentRows = $eventGateway->selectStaffAssessmentEvents($gibbonSchoolYearID, $dateStart, $dateEnd, $yearGroupID !== '' ? $yearGroupID : null);
    }
}
$homeworkRows = ac_filterEventRowsByEnabledYearGroups($homeworkRows, $enabledYearGroupIDs);
$assessmentRows = ac_filterEventRowsByEnabledYearGroups($assessmentRows, $enabledYearGroupIDs);

$events = [];
$absoluteURL = $session->get('absoluteURL');
$yearGroupMap = [];
if ($roleCategory === 'Staff') {
    $criteria = $yearGroupGateway
        ->newQueryCriteria(true)
        ->sortBy(['sequenceNumber']);
    $allYearGroups = $yearGroupGateway->queryYearGroups($criteria)->toArray();
    $allYearGroups = ac_normalizeYearGroupRows($allYearGroups);
    $allYearGroups = ac_filterYearGroupsByEnabled($allYearGroups, $enabledYearGroupIDs);
    $yearGroupMap = ac_buildYearGroupMap($allYearGroups);
}

$buildSubjectParts = function (array $row) {
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
};

$buildSubjectLabel = function (array $row, string $fallbackLabel) use ($buildSubjectParts, $roleCategory) {
    [$subjectName, $subjectCode] = $buildSubjectParts($row);

    if ($subjectName === '' && $subjectCode === '') {
        return $fallbackLabel;
    }

    if ($roleCategory === 'Staff') {
        return $subjectCode !== '' ? $subjectCode : $subjectName;
    }

    return $subjectName !== '' ? $subjectName : $subjectCode;
};

foreach ($homeworkRows as $row) {
    $type = trim((string) ($row['markbookType'] ?? ''));
    if ($type === '') {
        $type = __('Homework');
    }
    $meta = $eventTypeMeta[$type] ?? null;
    $classification = is_array($meta) ? (string) ($meta['classification'] ?? '') : '';

    $homeworkTitle = trim((string) ($row['markbookName'] ?? ''));
    if ($homeworkTitle === '') {
        $homeworkTitle = trim((string) ($row['homeworkName'] ?? __('Homework')));
    }

    $subject = $buildSubjectLabel($row, __('Homework'));

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
    $classificationClass = 'ac-event-homework-none';
    if ($classification === 'formative') {
        $classificationClass = 'ac-event-homework-formative';
    } elseif ($classification === 'summative') {
        $classificationClass = 'ac-event-homework-summative';
    }

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
        'classNames' => ['ac-event-homework', $classificationClass],
        'url' => $absoluteURL.'/index.php?'.http_build_query($query),
        'backgroundColor' => $color,
        'extendedProps' => [
            'subject' => $subject,
            'homeworkTitle' => $homeworkTitle,
            'yearGroups' => $yearGroupsText,
            'type' => $type,
            'classification' => $classification,
            'source' => 'Planner',
        ],
    ];
}

foreach ($assessmentRows as $row) {
    $type = trim((string) ($row['assessmentType'] ?? ''));
    if ($type === '') {
        $type = __('Assessment');
    }
    $meta = $eventTypeMeta[$type] ?? null;
    if (is_array($meta) && (($meta['visible'] ?? 'Y') === 'N')) {
        continue;
    }
    $classification = is_array($meta) ? (string) ($meta['classification'] ?? '') : '';
    if ($classification === 'formative' && $assessmentFormative !== 'Y') {
        continue;
    }
    if ($classification === 'summative' && $assessmentSummative !== 'Y') {
        continue;
    }
    if ($classification === '' && $assessmentNone !== 'Y') {
        continue;
    }

    $assessmentTitle = trim((string) ($row['assessmentName'] ?? ''));
    if ($assessmentTitle === '') {
        $assessmentTitle = __('Assessment');
    }

    $subject = $buildSubjectLabel($row, __('Assessment'));

    $title = $subject;
    if ($assessmentTitle !== '' && mb_strtolower($assessmentTitle) !== mb_strtolower($subject)) {
        $title .= ' - '.$assessmentTitle;
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

    $color = ac_normalizeHexColor((string) ($row['assessmentColor'] ?? ''));
    if ($color === null) {
        $color = $customColors[$type] ?? ac_colorFromPalette($type);
    }

    $classificationClass = 'ac-event-assessment-none';
    if ($classification === 'formative') {
        $classificationClass = 'ac-event-assessment-formative';
    } elseif ($classification === 'summative') {
        $classificationClass = 'ac-event-assessment-summative';
    }

    $url = null;
    if ($roleCategory === 'Staff') {
        if ($canEditMarkbookData) {
            $query = [
                'q' => '/modules/Markbook/markbook_edit_data.php',
                'gibbonCourseClassID' => (string) $row['gibbonCourseClassID'],
                'gibbonMarkbookColumnID' => (string) $row['gibbonMarkbookColumnID'],
            ];
            $url = $absoluteURL.'/index.php?'.http_build_query($query);
        } elseif ($canViewMarkbook) {
            $url = $absoluteURL.'/index.php?q='.rawurlencode('/modules/Markbook/markbook_view.php');
        }
    } elseif ($roleCategory === 'Student' && $canViewMarkbook) {
        $url = $absoluteURL.'/index.php?q='.rawurlencode('/modules/Markbook/markbook_view.php');
    } elseif ($roleCategory === 'Parent' && $canViewMarkbook) {
        $query = [
            'q' => '/modules/Markbook/markbook_view.php',
        ];
        if ($childPersonID !== '') {
            $query['search'] = $childPersonID;
        }
        $url = $absoluteURL.'/index.php?'.http_build_query($query);
    }

    $event = [
        'id' => 'mbc-'.(string) $row['gibbonMarkbookColumnID'],
        'title' => $title,
        'start' => date('Y-m-d', strtotime((string) $row['assessmentDate'])),
        'allDay' => true,
        'classNames' => ['ac-event-assessment', $classificationClass],
        'backgroundColor' => $color,
        'extendedProps' => [
            'subject' => $subject,
            'homeworkTitle' => $assessmentTitle,
            'yearGroups' => $yearGroupsText,
            'type' => $type,
            'classification' => $classification,
            'source' => 'Markbook',
            'description' => trim((string) ($row['assessmentDescription'] ?? '')),
        ],
    ];

    if (!empty($url)) {
        $event['url'] = $url;
    }

    $events[] = $event;
}

echo json_encode($events);
