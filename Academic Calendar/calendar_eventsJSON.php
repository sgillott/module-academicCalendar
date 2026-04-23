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
$assessmentClassificationStyles = ac_buildAssessmentClassificationStyles(ac_getAssessmentClassificationColors($settingGateway));
$useAssessmentClassificationColorInCalendar = ac_getUseAssessmentClassificationColorInCalendar($settingGateway);
$defaultAssessmentFilter = ac_getDefaultAssessmentFilter($settingGateway);
$staffEventFormat = ac_getStaffEventFormat($settingGateway);
$assessmentDisplayBasis = ac_getAssessmentDisplayBasis($settingGateway);
$mergeSameDayAssessments = ac_getMergeSameDayAssessments($settingGateway);
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

$buildParentMarkbookViewURL = function () use ($absoluteURL, $childPersonID, $canViewMarkbook) {
    if (!$canViewMarkbook) {
        return null;
    }

    $query = [
        'q' => '/modules/Markbook/markbook_view.php',
    ];
    if ($childPersonID !== '') {
        $query['search'] = $childPersonID;
    }

    return $absoluteURL.'/index.php?'.http_build_query($query);
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

    $subject = ac_getSubjectLabel($row, __('Homework'), $roleCategory);
    $yearGroupsText = $roleCategory === 'Staff'
        ? ac_buildYearGroupsText((string) ($row['gibbonYearGroupIDList'] ?? ''), $yearGroupMap)
        : '';

    $title = $roleCategory === 'Staff'
        ? ac_buildStaffEventTitle($row, $homeworkTitle, $yearGroupsText, $staffEventFormat)
        : $subject;
    if ($roleCategory !== 'Staff' && $homeworkTitle !== '' && mb_strtolower($homeworkTitle) !== mb_strtolower($subject)) {
        $title .= ' - '.$homeworkTitle;
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
        'textColor' => '#111827',
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

$assessmentEvents = [];
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

    $subject = ac_getAssessmentDisplayValue($row, $assessmentDisplayBasis, __('Assessment'));

    $yearGroupsText = $roleCategory === 'Staff'
        ? ac_buildYearGroupsText((string) ($row['gibbonYearGroupIDList'] ?? ''), $yearGroupMap)
        : '';

    $title = $subject;
    if ($roleCategory === 'Staff' && $assessmentDisplayBasis === 'learningArea' && $yearGroupsText !== '') {
        $title = '('.$yearGroupsText.') '.$subject;
    }
    if ($assessmentTitle !== '' && mb_strtolower($assessmentTitle) !== mb_strtolower($subject)) {
        $title .= ' - '.$assessmentTitle;
    }

    $color = ac_resolveAssessmentEventColor(
        (string) ($row['assessmentColor'] ?? ''),
        $type,
        $classification,
        $customColors,
        $assessmentClassificationStyles,
        $useAssessmentClassificationColorInCalendar
    );
    $borderColor = $useAssessmentClassificationColorInCalendar
        ? ac_resolveAssessmentClassificationBorderColor($classification, $assessmentClassificationStyles)
        : null;

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
        $url = $buildParentMarkbookViewURL();
    }

    $tooltipLines = [];
    $tooltipLines[] = $assessmentTitle;
    $courseShortCode = trim((string) ($row['courseNameShort'] ?? ''));
    $classShortCode = trim((string) ($row['classNameShort'] ?? ''));
    $classLabel = '';
    if ($courseShortCode !== '' && $classShortCode !== '') {
        $classLabel = $courseShortCode.'.'.$classShortCode;
    } elseif ($courseShortCode !== '' || $classShortCode !== '') {
        $classLabel = trim($courseShortCode.$classShortCode);
    } else {
        $classLabel = trim((string) ($row['className'] ?? ''));
    }
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

    $event = [
        'id' => 'mbc-'.(string) $row['gibbonMarkbookColumnID'],
        'title' => $title,
        'start' => date('Y-m-d', strtotime((string) $row['assessmentDate'])),
        'allDay' => true,
        'classNames' => array_values(array_filter([
            'ac-event-assessment',
            $classificationClass,
            $useAssessmentClassificationColorInCalendar ? 'ac-event-assessment-classification-color' : '',
        ])),
        'backgroundColor' => $color,
        'borderColor' => $borderColor,
        'textColor' => ac_getContrastingTextColor($color),
        'extendedProps' => [
            'subject' => $subject,
            'homeworkTitle' => $assessmentTitle,
            'yearGroups' => $yearGroupsText,
            'type' => $type,
            'classification' => $classification,
            'source' => 'Markbook',
            'description' => $description,
            'tooltipLines' => $tooltipLines,
        ],
    ];

    if (!empty($url)) {
        $event['url'] = $url;
    }

    if (!$mergeSameDayAssessments) {
        $assessmentEvents[] = $event;
        continue;
    }

    $mergeKey = implode('|', [
        date('Y-m-d', strtotime((string) $row['assessmentDate'])),
        $subject,
        mb_strtolower($assessmentTitle),
        $classification,
        $roleCategory === 'Staff' ? $yearGroupsText : '',
    ]);

    if (!isset($assessmentEvents[$mergeKey])) {
        $event['extendedProps']['mergedCount'] = 1;
        $event['extendedProps']['mergedTitles'] = [$assessmentTitle];
        $event['extendedProps']['mergedColumnIDs'] = [(string) $row['gibbonMarkbookColumnID']];
        $event['extendedProps']['mergedTooltipLines'] = [$tooltipLines];
        $assessmentEvents[$mergeKey] = $event;
        continue;
    }

    $assessmentEvents[$mergeKey]['extendedProps']['mergedCount']++;
    $assessmentEvents[$mergeKey]['extendedProps']['mergedTitles'][] = $assessmentTitle;
    $assessmentEvents[$mergeKey]['extendedProps']['mergedColumnIDs'][] = (string) $row['gibbonMarkbookColumnID'];
    $assessmentEvents[$mergeKey]['extendedProps']['mergedTooltipLines'][] = $tooltipLines;

    $mergedCount = (int) $assessmentEvents[$mergeKey]['extendedProps']['mergedCount'];
    if ($roleCategory === 'Staff' && $assessmentDisplayBasis === 'learningArea') {
        $assessmentEvents[$mergeKey]['title'] = ($yearGroupsText !== '' ? '('.$yearGroupsText.') ' : '').$subject;
        if ($assessmentTitle !== '' && mb_strtolower($assessmentTitle) !== mb_strtolower($subject)) {
            $assessmentEvents[$mergeKey]['title'] .= ' - '.$assessmentTitle;
        }
    } else {
        $assessmentEvents[$mergeKey]['title'] = $subject.' x'.$mergedCount;
    }

    if ($roleCategory === 'Staff') {
        if ($canViewMarkbook) {
            $assessmentEvents[$mergeKey]['url'] = $absoluteURL.'/index.php?q='.rawurlencode('/modules/Markbook/markbook_view.php');
        } else {
            unset($assessmentEvents[$mergeKey]['url']);
        }
    } elseif ($roleCategory === 'Student' && $canViewMarkbook) {
        $assessmentEvents[$mergeKey]['url'] = $absoluteURL.'/index.php?q='.rawurlencode('/modules/Markbook/markbook_view.php');
    } elseif ($roleCategory === 'Parent') {
        $parentURL = $buildParentMarkbookViewURL();
        if ($parentURL !== null) {
            $assessmentEvents[$mergeKey]['url'] = $parentURL;
        } else {
            unset($assessmentEvents[$mergeKey]['url']);
        }
    } else {
        unset($assessmentEvents[$mergeKey]['url']);
    }
}

$events = array_merge($events, array_values($assessmentEvents));

echo json_encode($events);
