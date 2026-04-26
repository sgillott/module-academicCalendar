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

$dateStart = ac_parseCalendarFeedDate($dateStart);
$dateEnd = ac_parseCalendarFeedDate($dateEnd);

if ($dateStart === null || $dateEnd === null || $dateStart >= $dateEnd) {
    echo json_encode([]);
    exit;
}

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
$assessmentClassificationDefinitions = ac_getAssessmentClassificationDefinitions($settingGateway);
$assessmentClassificationStyles = ac_buildAssessmentClassificationStyles(array_map(function ($definition) {
    return (string) $definition['color'];
}, $assessmentClassificationDefinitions));
$useAssessmentClassificationColorInCalendar = ac_getUseAssessmentClassificationColorInCalendar($settingGateway);
$defaultAssessmentFilter = ac_getDefaultAssessmentFilter($settingGateway);
$assessmentDisplayBasis = ac_getAssessmentDisplayBasis($settingGateway);
$mergeSameDayAssessments = ac_getMergeSameDayAssessments($settingGateway);
$enabledYearGroupIDs = ac_getEnabledYearGroupIDs($settingGateway);
$showHomeworkEvents = ac_getShowHomeworkEvents($settingGateway);
$showAssessmentEvents = ac_getShowAssessmentEvents($settingGateway);
$assessmentFilterSource = isset($_GET['assessmentFilter']) && is_array($_GET['assessmentFilter'])
    ? $_GET['assessmentFilter']
    : [];
$assessmentFilterState = ac_normalizeAssessmentFilterState($assessmentClassificationDefinitions, $assessmentFilterSource, $defaultAssessmentFilter);
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
$yearGroupSequenceMap = [];
if ($roleCategory === 'Staff') {
    $criteria = $yearGroupGateway
        ->newQueryCriteria(true)
        ->sortBy(['sequenceNumber']);
    $allYearGroups = $yearGroupGateway->queryYearGroups($criteria)->toArray();
    $allYearGroups = ac_normalizeYearGroupRows($allYearGroups);
    $allYearGroups = ac_filterYearGroupsByEnabled($allYearGroups, $enabledYearGroupIDs);
    $yearGroupMap = ac_buildYearGroupMap($allYearGroups);
    $yearGroupSequenceMap = ac_buildYearGroupSequenceMap($allYearGroups);
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

    $subject = $roleCategory === 'Staff'
        ? ac_getSubjectLabel($row, __('Homework'), $roleCategory)
        : ac_getHomeworkDisplayValue($row, $assessmentDisplayBasis, __('Homework'));
    $classLabel = ac_getAssessmentClassLabel($row);
    $yearGroupsText = $roleCategory === 'Staff'
        ? ac_buildYearGroupsText((string) ($row['gibbonYearGroupIDList'] ?? ''), $yearGroupMap)
        : '';
    $tooltipLines = ac_buildHomeworkTooltipLines($row, $homeworkTitle, $type, $yearGroupsText);

    $title = ac_buildStaffEventTitle($row, $homeworkTitle, $yearGroupsText, $assessmentDisplayBasis);

    $color = $customColors[$type] ?? ac_colorFromPalette($type);
    $classificationClass = 'ac-event-homework';

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
            'classLabel' => $classLabel,
            'yearGroups' => $yearGroupsText,
            'primaryYearGroupID' => $roleCategory === 'Staff'
                ? ac_getPrimaryYearGroupIDForEvent((string) ($row['gibbonYearGroupIDList'] ?? ''), $yearGroupSequenceMap)
                : null,
            'yearGroupSequence' => $roleCategory === 'Staff'
                ? ac_getYearGroupSequenceForEvent((string) ($row['gibbonYearGroupIDList'] ?? ''), $yearGroupSequenceMap)
                : null,
            'type' => $type,
            'classification' => ac_getAssessmentClassificationLabel($classification, $assessmentClassificationDefinitions),
            'source' => 'Planner',
            'tooltipLines' => $tooltipLines,
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
    if ($classification !== '' && !isset($assessmentClassificationDefinitions[$classification])) {
        $classification = '';
    }
    $classificationKey = $classification !== '' ? $classification : 'none';
    if (($assessmentFilterState[$classificationKey] ?? 'Y') !== 'Y') {
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

    $title = ac_buildAssessmentEventTitle(
        $subject,
        $assessmentTitle,
        $roleCategory,
        $assessmentDisplayBasis,
        $yearGroupsText
    );

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

    $classificationClass = 'ac-event-assessment-'.preg_replace('/[^a-z0-9_-]+/', '-', $classificationKey);

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

    $tooltipLines = ac_buildAssessmentTooltipLines($row, $assessmentTitle, $type);
    $description = trim((string) ($row['assessmentDescription'] ?? ''));

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
            'primaryYearGroupID' => $roleCategory === 'Staff'
                ? ac_getPrimaryYearGroupIDForEvent((string) ($row['gibbonYearGroupIDList'] ?? ''), $yearGroupSequenceMap)
                : null,
            'yearGroupSequence' => $roleCategory === 'Staff'
                ? ac_getYearGroupSequenceForEvent((string) ($row['gibbonYearGroupIDList'] ?? ''), $yearGroupSequenceMap)
                : null,
            'type' => $type,
            'classification' => ac_getAssessmentClassificationLabel($classification, $assessmentClassificationDefinitions),
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

    $mergeKey = ac_buildAssessmentMergeKey(
        $row,
        $subject,
        $assessmentTitle,
        $classification,
        $roleCategory === 'Staff' ? $yearGroupsText : ''
    );

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
        $assessmentEvents[$mergeKey]['title'] = ac_buildAssessmentEventTitle(
            $subject,
            $assessmentTitle,
            $roleCategory,
            $assessmentDisplayBasis,
            $yearGroupsText
        );
    } else {
        $assessmentEvents[$mergeKey]['title'] = $subject.' x'.$mergedCount;
    }

    if ($roleCategory === 'Staff') {
        if ($canEditMarkbookData) {
            // Keep the first merged assessment's direct edit URL.
        } elseif ($canViewMarkbook) {
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
