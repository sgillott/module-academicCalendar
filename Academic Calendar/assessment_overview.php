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

use Gibbon\Domain\School\SchoolYearGateway;
use Gibbon\Domain\School\SchoolYearSpecialDayGateway;
use Gibbon\Domain\School\SchoolYearTermGateway;
use Gibbon\Domain\School\YearGroupGateway;
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\AcademicCalendar\Domain\AcademicCalendarEventGateway;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/Academic Calendar/assessment_overview.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Assessment Overview'));

    $gibbonSchoolYearID = (string) $session->get('gibbonSchoolYearID');
    $firstDayOfTheWeek = (string) $session->get('firstDayOfTheWeek', 'Monday');
    $weekStartsOn = $firstDayOfTheWeek === 'Sunday' ? 0 : ($firstDayOfTheWeek === 'Saturday' ? 6 : 1);

    $settingGateway = $container->get(SettingGateway::class);
    $schoolYearGateway = $container->get(SchoolYearGateway::class);
    $schoolYearTermGateway = $container->get(SchoolYearTermGateway::class);
    $specialDayGateway = $container->get(SchoolYearSpecialDayGateway::class);
    $yearGroupGateway = $container->get(YearGroupGateway::class);
    $studentGateway = $container->get(StudentGateway::class);
    $eventGateway = $container->get(AcademicCalendarEventGateway::class);

    $enabledYearGroupIDs = ac_getEnabledYearGroupIDs($settingGateway);
    $defaultThreshold = ac_getSummativeThresholdDefault($settingGateway);
    $thresholdByYearGroup = ac_getSummativeThresholdByYearGroup($settingGateway);
    $overviewWeekNumberMode = ac_getOverviewWeekNumberMode($settingGateway);
    $assessmentDisplayBasis = ac_getAssessmentDisplayBasis($settingGateway);
    $mergeSameDayAssessments = ac_getMergeSameDayAssessments($settingGateway);
    $eventTypeMeta = ac_getEventTypeMeta($settingGateway);
    $assessmentClassificationDefinitions = ac_getAssessmentClassificationDefinitions($settingGateway);
    $eventTypeColors = ac_getColorMap($settingGateway);
    $assessmentClassificationStyles = ac_buildAssessmentClassificationStyles(ac_getAssessmentClassificationColors($settingGateway));

    $schoolYear = $schoolYearGateway->getSchoolYearByID((int) $gibbonSchoolYearID);
    if (empty($schoolYear['firstDay']) || empty($schoolYear['lastDay'])) {
        $page->addError(__('Unable to load the current school year date range.'));
        return;
    }

    $criteria = $yearGroupGateway->newQueryCriteria(true)->sortBy(['sequenceNumber']);
    $yearGroups = $yearGroupGateway->queryYearGroups($criteria)->toArray();
    $yearGroups = ac_normalizeYearGroupRows($yearGroups);
    $yearGroups = ac_filterYearGroupsByEnabled($yearGroups, $enabledYearGroupIDs);
    $yearGroupMap = ac_buildYearGroupMap($yearGroups);

    $roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
    $restrictedYearGroupIDs = [];

    if ($roleCategory === 'Student') {
        $student = $studentGateway
            ->selectActiveStudentByPerson($gibbonSchoolYearID, (string) $session->get('gibbonPersonID'))
            ->fetch();

        $studentYearGroupID = (string) ($student['gibbonYearGroupID'] ?? '');
        if ($studentYearGroupID !== '') {
            $restrictedYearGroupIDs[] = $studentYearGroupID;
        }
    } elseif ($roleCategory === 'Parent') {
        $children = $studentGateway
            ->selectActiveStudentsByFamilyAdult($gibbonSchoolYearID, (string) $session->get('gibbonPersonID'))
            ->fetchAll();

        if (is_array($children)) {
            foreach ($children as $child) {
                $childYearGroupID = (string) ($child['gibbonYearGroupID'] ?? '');
                if ($childYearGroupID !== '') {
                    $restrictedYearGroupIDs[] = $childYearGroupID;
                }
            }
        }
    }

    if ($roleCategory === 'Student' || $roleCategory === 'Parent') {
        $yearGroups = ac_filterYearGroupsByEnabled(
            $yearGroups,
            array_values(array_unique($restrictedYearGroupIDs))
        );
    }

    if (empty($yearGroups)) {
        if ($roleCategory === 'Student' || $roleCategory === 'Parent') {
            $page->addWarning(__('No enabled year groups are available for your account.'));
        } else {
            $page->addWarning(__('No enabled year groups are available in Academic Calendar settings.'));
        }
        return;
    }

    $terms = $schoolYearTermGateway->selectTermDetailsBySchoolYear((int) $gibbonSchoolYearID)->fetchAll();
    $termRows = array_map(function ($row) {
        $name = trim((string) ($row['name'] ?? ''));
        $nameShort = trim((string) ($row['nameShort'] ?? ''));
        if ($name === '') {
            $name = $nameShort;
        }

        return [
            'id' => (string) ($row['gibbonSchoolYearTermID'] ?? ''),
            'name' => $name,
            'nameShort' => $nameShort,
            'firstDay' => (string) ($row['firstDay'] ?? ''),
            'lastDay' => (string) ($row['lastDay'] ?? ''),
            'sequenceNumber' => (int) ($row['sequenceNumber'] ?? 0),
        ];
    }, is_array($terms) ? $terms : []);

    usort($termRows, function ($a, $b) {
        return ($a['sequenceNumber'] <=> $b['sequenceNumber']);
    });

    $specialDays = $specialDayGateway
        ->selectSpecialDaysByDateRange((string) $schoolYear['firstDay'], (string) $schoolYear['lastDay'])
        ->fetchAll();

    $specialDayRows = array_map(function ($row) {
        return [
            'name' => (string) ($row['name'] ?? ''),
            'type' => (string) ($row['type'] ?? ''),
            'date' => (string) ($row['date'] ?? ''),
        ];
    }, is_array($specialDays) ? $specialDays : []);

    $schoolClosureDates = [];
    foreach ($specialDayRows as $specialDay) {
        $specialDayType = strtolower(trim((string) ($specialDay['type'] ?? '')));
        $specialDayDate = (string) ($specialDay['date'] ?? '');
        if ($specialDayType === 'school closure' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $specialDayDate)) {
            $schoolClosureDates[$specialDayDate] = true;
        }
    }

    $selectedTermID = ac_sanitizeNumericID($_GET['termID'] ?? '');
    $displayYearGroupParam = $_GET['displayYearGroupIDs'] ?? [];
    if (is_array($displayYearGroupParam)) {
        $selectedDisplayYearGroupIDs = ac_parseIDList(implode(',', array_map('strval', $displayYearGroupParam)));
    } else {
        $selectedDisplayYearGroupIDs = ac_parseIDList((string) $displayYearGroupParam);
    }
    $yearGroupIDs = array_column($yearGroups, 'gibbonYearGroupID');
    $yearGroupLookup = array_fill_keys(array_map('strval', $yearGroupIDs), true);
    $selectedDisplayYearGroupIDs = array_values(array_filter($selectedDisplayYearGroupIDs, function ($yearGroupID) use ($yearGroupLookup) {
        return isset($yearGroupLookup[$yearGroupID]);
    }));

    $displayYearGroups = $yearGroups;
    if (!empty($selectedDisplayYearGroupIDs)) {
        $displayLookup = array_fill_keys($selectedDisplayYearGroupIDs, true);
        $displayYearGroups = array_values(array_filter($yearGroups, function ($group) use ($displayLookup) {
            return isset($displayLookup[(string) ($group['gibbonYearGroupID'] ?? '')]);
        }));
    }

    $overviewClassificationKeys = [];
    foreach ($assessmentClassificationDefinitions as $classificationKey => $definition) {
        if ($classificationKey === 'none') {
            continue;
        }

        if ((string) ($definition['displayInOverview'] ?? 'N') === 'Y') {
            $overviewClassificationKeys[$classificationKey] = true;
        }
    }

    $overviewTypes = [];
    foreach ($eventTypeMeta as $type => $meta) {
        $classification = ac_normalizeAssessmentClassificationKey((string) ($meta['classification'] ?? ''));
        if ($classification !== '' && isset($overviewClassificationKeys[$classification])) {
            $overviewTypes[(string) $type] = $classification;
        }
    }

    $schoolYearStart = new DateTimeImmutable((string) $schoolYear['firstDay']);
    $schoolYearEnd = new DateTimeImmutable((string) $schoolYear['lastDay']);
    $assessmentDateEndExclusive = $schoolYearEnd->modify('+1 day');

    $assessmentRows = [];
    if (!empty($overviewTypes)) {
        $assessmentRows = $eventGateway->selectStaffAssessmentEvents(
            $gibbonSchoolYearID,
            $schoolYearStart->format('Y-m-d 00:00:00'),
            $assessmentDateEndExclusive->format('Y-m-d 00:00:00')
        );
        $assessmentRows = ac_filterEventRowsByEnabledYearGroups($assessmentRows, $enabledYearGroupIDs);
    }

    $dayOfWeek = (int) $schoolYearStart->format('w');
    $daysBack = ($dayOfWeek - $weekStartsOn + 7) % 7;
    $cursor = $schoolYearStart->modify('-'.$daysBack.' days');
    $academicWeekCounter = 0;

    $weeklyCounts = [];
    $weeklyAssessmentGroups = [];

    foreach ($assessmentRows as $assessmentRow) {
        $type = trim((string) ($assessmentRow['assessmentType'] ?? ''));
        if ($type === '' || !isset($overviewTypes[$type])) {
            continue;
        }

        $assessmentDateRaw = (string) ($assessmentRow['assessmentDate'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $assessmentDateRaw)) {
            continue;
        }

        $assessmentDate = new DateTimeImmutable($assessmentDateRaw);
        $assessmentDayOfWeek = (int) $assessmentDate->format('w');
        $assessmentDaysBack = ($assessmentDayOfWeek - $weekStartsOn + 7) % 7;
        $weekStart = $assessmentDate->modify('-'.$assessmentDaysBack.' days');
        $weekKey = $weekStart->format('Y-m-d');

        $courseYearGroupIDs = ac_parseIDList((string) ($assessmentRow['gibbonYearGroupIDList'] ?? ''));
        $matchingYearGroupIDs = array_values(array_filter($courseYearGroupIDs, function ($yearGroupID) use ($yearGroupLookup) {
            return isset($yearGroupLookup[$yearGroupID]);
        }));

        if (empty($matchingYearGroupIDs)) {
            continue;
        }

        $subject = ac_getAssessmentDisplayValue($assessmentRow, $assessmentDisplayBasis, __('Assessment'));
        $assessmentTitle = trim((string) ($assessmentRow['assessmentName'] ?? ''));
        if ($assessmentTitle === '') {
            $assessmentTitle = __('Assessment');
        }

        foreach ($matchingYearGroupIDs as $yearGroupID) {
            $assessmentType = trim((string) ($assessmentRow['assessmentType'] ?? ''));
            $classification = (string) ($overviewTypes[$assessmentType] ?? '');
            $cellYearGroupsText = (string) ($yearGroupMap[$yearGroupID] ?? '');
            $mergeYearGroupsText = $roleCategory === 'Staff' ? $cellYearGroupsText : '';
            $mergeKey = $mergeSameDayAssessments
                ? ac_buildAssessmentMergeKey(
                    $assessmentRow,
                    $subject,
                    $assessmentTitle,
                    $classification,
                    $mergeYearGroupsText
                )
                : implode('|', [
                    'assessment',
                    (string) ($assessmentRow['gibbonMarkbookColumnID'] ?? ''),
                    $yearGroupID,
                ]);

            if (!isset($weeklyAssessmentGroups[$weekKey][$yearGroupID][$mergeKey])) {
                $weeklyCounts[$weekKey][$yearGroupID] = (int) ($weeklyCounts[$weekKey][$yearGroupID] ?? 0) + 1;
                $weeklyAssessmentGroups[$weekKey][$yearGroupID][$mergeKey] = [
                    'subject' => $subject,
                    'assessmentTitle' => $assessmentTitle,
                    'classification' => $classification,
                    'count' => 1,
                    'label' => $subject,
                    'tooltipBlocks' => [
                        ac_buildAssessmentTooltipLines($assessmentRow, $assessmentTitle, $assessmentType),
                    ],
                    'colorCounts' => [],
                ];
            } else {
                $weeklyAssessmentGroups[$weekKey][$yearGroupID][$mergeKey]['count']++;

                $weeklyAssessmentGroups[$weekKey][$yearGroupID][$mergeKey]['tooltipBlocks'][] =
                    ac_buildAssessmentTooltipLines($assessmentRow, $assessmentTitle, $assessmentType);
            }

            $subjectColor = ac_resolveAssessmentEventColor(
                (string) ($assessmentRow['assessmentColor'] ?? ''),
                $assessmentType,
                $classification,
                $eventTypeColors,
                $assessmentClassificationStyles,
                false
            );
            if ($subjectColor !== null) {
                $weeklyAssessmentGroups[$weekKey][$yearGroupID][$mergeKey]['colorCounts'][$subjectColor] =
                    (int) ($weeklyAssessmentGroups[$weekKey][$yearGroupID][$mergeKey]['colorCounts'][$subjectColor] ?? 0) + 1;
            }
        }
    }

    $weeksByTerm = [];
    $unassignedWeeks = [];
    while ($cursor <= $schoolYearEnd) {
        $weekStart = $cursor;
        $weekEnd = $weekStart->modify('+6 days');
        $weekKey = $weekStart->format('Y-m-d');

        $weekSpecialDays = [];
        foreach ($specialDayRows as $specialDay) {
            if (empty($specialDay['date'])) {
                continue;
            }

            $date = new DateTimeImmutable($specialDay['date']);
            if ($date >= $weekStart && $date <= $weekEnd) {
                $label = trim((string) ($specialDay['name'] ?? ''));
                if ($label === '') {
                    $label = trim((string) ($specialDay['type'] ?? ''));
                }
                if ($label !== '') {
                    $weekSpecialDays[$label] = $label;
                }
            }
        }

        $schoolDaysInWeek = 0;
        $schoolClosureDaysInWeek = 0;
        for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
            $currentDay = $weekStart->modify('+'.$dayOffset.' days');
            if ($currentDay < $schoolYearStart || $currentDay > $schoolYearEnd) {
                continue;
            }

            // School days are Monday-Friday for week-level closure logic.
            if ((int) $currentDay->format('N') >= 6) {
                continue;
            }

            $schoolDaysInWeek++;
            $currentDate = $currentDay->format('Y-m-d');
            if (!empty($schoolClosureDates[$currentDate])) {
                $schoolClosureDaysInWeek++;
            }
        }

        $containsSchoolClosure = $schoolDaysInWeek > 0 && $schoolClosureDaysInWeek === $schoolDaysInWeek;

        $matchedTerm = null;
        foreach ($termRows as $term) {
            if (empty($term['firstDay']) || empty($term['lastDay'])) {
                continue;
            }

            $termStart = new DateTimeImmutable($term['firstDay']);
            $termEnd = new DateTimeImmutable($term['lastDay']);
            if ($weekStart <= $termEnd && $weekEnd >= $termStart) {
                $matchedTerm = $term;
                break;
            }
        }

        $weekRow = [
            'calendarWeekNumber' => (int) $weekStart->format('W'),
            'academicWeekNumber' => null,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weekKey' => $weekKey,
            'specialDays' => array_values($weekSpecialDays),
            'containsSchoolClosure' => $containsSchoolClosure,
        ];

        if (!$containsSchoolClosure) {
            $academicWeekCounter++;
            $weekRow['academicWeekNumber'] = $academicWeekCounter;
        }

        if (!empty($matchedTerm['id'])) {
            $weeksByTerm[$matchedTerm['id']][] = $weekRow;
        } else {
            $unassignedWeeks[] = $weekRow;
        }

        $cursor = $cursor->modify('+7 days');
    }

    echo '<h2 class="acOverviewTitle">'.htmlspecialchars((string) ($schoolYear['name'] ?? __('Academic Year'))).'</h2>';

    $filtersOpen = $selectedTermID !== '' || !empty($selectedDisplayYearGroupIDs);
    echo '<details class="acOverviewFilterPanel"'.($filtersOpen ? ' open' : '').'>';
    echo '<summary class="acOverviewFilterSummary">'.__('Filter options').'</summary>';
    echo '<div class="acOverviewFilterBody">';
    echo '<form method="get" action="index.php" class="acOverviewFilters acFilterRow">';
    echo '<input type="hidden" name="q" value="/modules/Academic Calendar/assessment_overview.php">';

    echo '<div class="acOverviewField">';
    echo '<label for="termID"><strong>'.__('Term').'</strong></label>';
    echo '<select id="termID" name="termID" class="acOverviewSelect">';
    echo '<option value="">'.__('All Terms').'</option>';
    foreach ($termRows as $term) {
        $termID = (string) ($term['id'] ?? '');
        if ($termID === '') {
            continue;
        }
        $selected = $selectedTermID === $termID ? ' selected' : '';
        $termLabel = (string) ($term['name'] ?? '');
        $termShort = trim((string) ($term['nameShort'] ?? ''));
        if ($termShort !== '' && strcasecmp($termShort, $termLabel) !== 0) {
            $termLabel .= ' ('.$termShort.')';
        }
        echo '<option value="'.htmlspecialchars($termID).'"'.$selected.'>'.htmlspecialchars($termLabel).'</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<div class="acOverviewField">';
    echo '<label for="displayYearGroupIDs"><strong>'.__('Year Groups').'</strong></label>';
    echo '<select id="displayYearGroupIDs" name="displayYearGroupIDs[]" class="acOverviewSelect acOverviewSelectMulti" multiple size="6">';
    foreach ($yearGroups as $group) {
        $groupID = (string) ($group['gibbonYearGroupID'] ?? '');
        $selected = in_array($groupID, $selectedDisplayYearGroupIDs, true) ? ' selected' : '';
        $label = (string) (($group['nameShort'] ?? '') ?: ($group['name'] ?? ''));
        echo '<option value="'.htmlspecialchars($groupID).'"'.$selected.'>'.htmlspecialchars($label).'</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<div class="acOverviewField acOverviewFieldButton">';
    echo '<button type="submit" class="acOverviewFilterButton">'.__('Go').'</button>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
    echo '</details>';

    if (empty($overviewTypes)) {
        echo '<div class="warning">';
        echo __('No markbook event types are currently assigned to an assessment classification that is set to display in the overview.');
        echo '</div>';
    }

    $renderTermTable = function (string $heading, string $dateRange, array $weeks) use ($displayYearGroups, $thresholdByYearGroup, $defaultThreshold, $weeklyCounts, $weeklyAssessmentGroups, $overviewWeekNumberMode) {
        if (empty($weeks)) {
            return;
        }

        $yearGroupCount = max(1, count($displayYearGroups));
        $weekColumnWidth = 5.0;
        $weekBeginningWidth = 9.0;
        $schoolEventsWidth = 20.0;
        $yearGroupWidth = max(6.5, (100.0 - $weekColumnWidth - $weekBeginningWidth - $schoolEventsWidth) / $yearGroupCount);

        echo '<h3 class="acOverviewTermTitle">'.htmlspecialchars($heading).' <span>('.htmlspecialchars($dateRange).')</span></h3>';
        echo '<div class="overflow-x-auto">';
        echo '<table class="acOverviewTable">';
        echo '<colgroup>';
        echo '<col style="width: '.$weekColumnWidth.'%;">';
        echo '<col style="width: '.$weekBeginningWidth.'%;">';
        foreach ($displayYearGroups as $group) {
            echo '<col style="width: '.$yearGroupWidth.'%;">';
        }
        echo '<col style="width: '.$schoolEventsWidth.'%;">';
        echo '</colgroup>';
        echo '<thead><tr>';
        echo '<th class="acOverviewWeekColumn">'.__('Week').'</th>';
        echo '<th>'.__('Week Beginning').'</th>';
        foreach ($displayYearGroups as $group) {
            $yearGroupID = (string) $group['gibbonYearGroupID'];
            $label = (string) ($group['nameShort'] ?: $group['name']);
            $threshold = (int) ($thresholdByYearGroup[$yearGroupID] ?? $defaultThreshold);
            echo '<th>'.htmlspecialchars($label).' <span class="acOverviewThreshold">(MAX '.(int) $threshold.')</span></th>';
        }
        echo '<th class="acOverviewSchoolEventsColumn">'.__('School Events').'</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($weeks as $week) {
            $weekKey = (string) $week['weekKey'];
            $rowClass = !empty($week['containsSchoolClosure']) ? ' class="acOverviewWeekClosure"' : '';
            $weekNumberDisplay = $overviewWeekNumberMode === 'academic'
                ? ($week['academicWeekNumber'] !== null ? (string) $week['academicWeekNumber'] : '&ndash;')
                : (string) (int) $week['calendarWeekNumber'];

            echo '<tr'.$rowClass.'>';
            echo '<td class="acOverviewWeekNumber acOverviewWeekColumn">'.$weekNumberDisplay.'</td>';
            echo '<td>'.htmlspecialchars($week['weekStart']->format('j M')).'</td>';

            foreach ($displayYearGroups as $group) {
                $yearGroupID = (string) $group['gibbonYearGroupID'];
                $count = (int) ($weeklyCounts[$weekKey][$yearGroupID] ?? 0);
                $threshold = (int) ($thresholdByYearGroup[$yearGroupID] ?? $defaultThreshold);
                $assessmentGroups = array_values($weeklyAssessmentGroups[$weekKey][$yearGroupID] ?? []);

                if ($count < 1 || empty($assessmentGroups)) {
                    echo '<td class="acOverviewPlaceholder acOverviewEmptyCell">&ndash;</td>';
                    continue;
                }

                $cellClass = 'acOverviewSubjectsOk';
                if ($count > $threshold) {
                    $cellClass = 'acOverviewSubjectsExceed';
                } elseif ($count === $threshold) {
                    $cellClass = 'acOverviewSubjectsAtThreshold';
                }

                usort($assessmentGroups, function (array $a, array $b): int {
                    return strnatcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
                });

                echo '<td class="acOverviewSubjectsCell '.$cellClass.'">';
                foreach ($assessmentGroups as $groupLine) {
                    $colorCounts = is_array($groupLine['colorCounts'] ?? null) ? $groupLine['colorCounts'] : [];
                    $chipStyle = '';
                    if (!empty($colorCounts)) {
                        arsort($colorCounts, SORT_NUMERIC);
                        $backgroundColor = (string) array_key_first($colorCounts);
                        $textColor = ac_getContrastingTextColor($backgroundColor);
                        $chipStyle = ' style="background-color: '.htmlspecialchars($backgroundColor, ENT_QUOTES).'; border-color: '.htmlspecialchars($backgroundColor, ENT_QUOTES).'; color: '.htmlspecialchars($textColor, ENT_QUOTES).';"';
                    }

                    $tooltipAttr = ac_formatAssessmentTooltipBlocks(
                        is_array($groupLine['tooltipBlocks'] ?? null) ? $groupLine['tooltipBlocks'] : []
                    );
                    $tooltipDataAttr = $tooltipAttr !== '' ? ' data-tooltip="'.htmlspecialchars($tooltipAttr, ENT_QUOTES).'"' : '';
                    echo '<div class="acOverviewSubjectLine"'.$tooltipDataAttr.$chipStyle.'>'.htmlspecialchars((string) ($groupLine['label'] ?? '')).'</div>';
                }
                echo '</td>';
            }

            echo '<td class="acOverviewSchoolEventsColumn">';
            if (!empty($week['specialDays'])) {
                echo htmlspecialchars(implode(', ', $week['specialDays']));
            } else {
                echo '&ndash;';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    };

    $renderedAny = false;
    foreach ($termRows as $term) {
        $termID = (string) ($term['id'] ?? '');
        if ($termID === '') {
            continue;
        }

        if ($selectedTermID !== '' && $selectedTermID !== $termID) {
            continue;
        }

        $weeks = $weeksByTerm[$termID] ?? [];
        if (empty($weeks)) {
            continue;
        }

        $renderTermTable(
            (string) ($term['name'] ?: __('Term')),
            (new DateTimeImmutable((string) $term['firstDay']))->format('j F Y').' - '.(new DateTimeImmutable((string) $term['lastDay']))->format('j F Y'),
            $weeks
        );
        $renderedAny = true;
    }

    if ($selectedTermID === '' && !empty($unassignedWeeks)) {
        $renderTermTable(__('Other Weeks'), '', $unassignedWeeks);
        $renderedAny = true;
    }

    if (!$renderedAny) {
        echo '<div class="warning">'.__('No weeks match the selected filters.').'</div>';
    }

    echo '<script src="'.$session->get('absoluteURL').'/modules/'.rawurlencode($session->get('module')).'/js/module.js"></script>';
    echo '<script>window.AcademicCalendarModule.initOverviewTooltips();</script>';
}
