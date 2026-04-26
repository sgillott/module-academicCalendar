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
use Gibbon\Domain\Students\StudentGateway;
use Gibbon\Module\AcademicCalendar\Domain\AcademicCalendarEventGateway;

require_once __DIR__.'/moduleFunctions.php';

/**
 * Homework Calendar view.
 *
 * Renders role-specific filters plus FullCalendar client initialization.
 * Also supports embed mode for dashboard hook iframes.
 */
if (!isActionAccessible($guid, $connection2, '/modules/Academic Calendar/calendar_view.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $isEmbed = (string) ($_GET['embed'] ?? '') === '1';
    if (!$isEmbed) {
        $page->breadcrumbs->add(__('Homework/Assessment Calendar'));
    } else {
        // fullscreen.php does not include the full index stylesheet stack,
        // so we add the base and module styles when rendering in embed mode.
        $page->stylesheets->add('theme-main', 'themes/'.$session->get('gibbonThemeName').'/css/main.css', ['weight' => 1]);
        $page->stylesheets->add('theme-dev', 'resources/assets/css/theme.min.css');
        $page->stylesheets->add('core', 'resources/assets/css/core.min.css', ['weight' => 10]);
        $page->stylesheets->add('module-embed', 'modules/'.$session->get('module').'/css/module.css', ['weight' => 11]);
    }

    $roleCategory = (string) $session->get('gibbonRoleIDCurrentCategory');
    $gibbonPersonID = (string) $session->get('gibbonPersonID');
    $gibbonSchoolYearID = (string) $session->get('gibbonSchoolYearID');

    $settingGateway = $container->get(SettingGateway::class);
    $yearGroupGateway = $container->get(YearGroupGateway::class);
    $studentGateway = $container->get(StudentGateway::class);
    $eventGateway = $container->get(AcademicCalendarEventGateway::class);

    $showWeekends = ac_getShowWeekends($settingGateway);
    $showAssessmentEvents = ac_getShowAssessmentEvents($settingGateway);
    $defaultStaffView = (string) $settingGateway->getSettingByScope('Academic Calendar', 'defaultStaffView');
    $enabledYearGroupIDs = ac_getEnabledYearGroupIDs($settingGateway);
    $eventTypeMeta = ac_getEventTypeMeta($settingGateway);
    $assessmentClassificationDefinitions = ac_getAssessmentClassificationDefinitions($settingGateway);
    $defaultAssessmentFilter = ac_getDefaultAssessmentFilter($settingGateway);
    $assessmentClassificationColors = array_map(function ($definition) {
        return (string) $definition['color'];
    }, $assessmentClassificationDefinitions);
    $assessmentClassificationStyles = ac_buildAssessmentClassificationStyles($assessmentClassificationColors);
    $availableAssessmentClassifications = ac_getAvailableAssessmentClassifications($eventTypeMeta, $assessmentClassificationDefinitions, true);

    $yearGroupID = '';
    $yearGroups = [];
    $yearGroupColorMap = [];
    $yearGroupBadgeMap = [];
    if ($roleCategory === 'Staff') {
        $hasYearGroupParam = array_key_exists('yearGroupID', $_GET);
        $canFilterAllYearGroups = isActionAccessible(
            $guid,
            $connection2,
            '/modules/Academic Calendar/calendar_view.php',
            'Homework Calendar_allYearGroups'
        );

        if ($canFilterAllYearGroups) {
            $criteria = $yearGroupGateway
                ->newQueryCriteria(true)
                ->sortBy(['sequenceNumber']);
            $yearGroups = $yearGroupGateway->queryYearGroups($criteria)->toArray();
        } else {
            $yearGroups = $eventGateway->selectYearGroupsForStaff($gibbonSchoolYearID, $gibbonPersonID);
        }
        $yearGroups = ac_normalizeYearGroupRows($yearGroups);
        $yearGroups = ac_filterYearGroupsByEnabled($yearGroups, $enabledYearGroupIDs);
        $yearGroupID = ac_sanitizeNumericID($_GET['yearGroupID'] ?? '');

        $validYearGroups = array_column($yearGroups, 'gibbonYearGroupID');
        if ($yearGroupID !== '' && !in_array($yearGroupID, $validYearGroups, true)) {
            $yearGroupID = '';
        }
        if (!$hasYearGroupParam && $yearGroupID === '' && $defaultStaffView === 'yearGroup' && !empty($yearGroups)) {
            $yearGroupID = (string) $yearGroups[0]['gibbonYearGroupID'];
        }

        $yearGroupColorMap = ac_buildYearGroupColorMap($yearGroups);
        foreach ($yearGroupColorMap as $groupID => $backgroundColor) {
            $yearGroupBadgeMap[$groupID] = [
                'background' => $backgroundColor,
                'text' => ac_getContrastingTextColor($backgroundColor),
            ];
        }
    }

    $childPersonID = '';
    $children = [];
    if ($roleCategory === 'Parent') {
        $children = $studentGateway->selectActiveStudentsByFamilyAdult($gibbonSchoolYearID, $gibbonPersonID)->fetchAll();
        $children = ac_normalizeChildRows($children);
        $childPersonID = ac_sanitizeNumericID($_GET['childPersonID'] ?? '');

        $validChildren = array_column($children, 'childPersonID');
        if ($childPersonID === '' && !empty($validChildren)) {
            $childPersonID = (string) $validChildren[0];
        }
        if ($childPersonID !== '' && !in_array($childPersonID, $validChildren, true)) {
            $childPersonID = (string) ($validChildren[0] ?? '');
        }
    }

    $firstDayOfTheWeek = $session->get('firstDayOfTheWeek', 'Sunday');
    $firstDay = $firstDayOfTheWeek === 'Monday' ? 1 : ($firstDayOfTheWeek === 'Saturday' ? 6 : 0);
    $viewDate = trim((string) ($_GET['viewDate'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $viewDate)) {
        $viewDate = '';
    }
    $viewType = trim((string) ($_GET['viewType'] ?? ''));
    if (!in_array($viewType, ['dayGridMonth', 'timeGridWeek', 'listMonth'], true)) {
        $viewType = '';
    }

    $calendarWindow = ac_getCalendarViewWindow($viewDate, $viewType, $firstDay);
    $assessmentRowsForFilters = [];
    if ($showAssessmentEvents) {
        if ($roleCategory === 'Student') {
            $assessmentRowsForFilters = $eventGateway->selectStudentAssessmentEvents(
                $gibbonSchoolYearID,
                $gibbonPersonID,
                $calendarWindow['start'],
                $calendarWindow['end']
            );
        } elseif ($roleCategory === 'Parent' && $childPersonID !== '') {
            $assessmentRowsForFilters = $eventGateway->selectParentAssessmentEvents(
                $gibbonSchoolYearID,
                $gibbonPersonID,
                $childPersonID,
                $calendarWindow['start'],
                $calendarWindow['end']
            );
        } elseif ($roleCategory === 'Staff') {
            $assessmentRowsForFilters = $eventGateway->selectStaffAssessmentEvents(
                $gibbonSchoolYearID,
                $calendarWindow['start'],
                $calendarWindow['end'],
                $yearGroupID !== '' ? $yearGroupID : null
            );
        }

        $assessmentRowsForFilters = ac_filterEventRowsByEnabledYearGroups($assessmentRowsForFilters, $enabledYearGroupIDs);
    }

    $hasVisibleUnclassifiedAssessmentEvents = false;
    foreach ($assessmentRowsForFilters as $assessmentRow) {
        $type = trim((string) ($assessmentRow['assessmentType'] ?? ''));
        if ($type === '') {
            $type = __('Assessment');
        }

        $meta = $eventTypeMeta[$type] ?? null;
        if (is_array($meta) && (($meta['visible'] ?? 'Y') === 'N')) {
            continue;
        }

        $classification = is_array($meta) ? (string) ($meta['classification'] ?? '') : '';
        if ($classification === '' || !isset($assessmentClassificationDefinitions[$classification])) {
            $hasVisibleUnclassifiedAssessmentEvents = true;
            break;
        }
    }
    $availableAssessmentClassifications['none'] = $hasVisibleUnclassifiedAssessmentEvents;
    $showAssessmentFilterOptions = $showAssessmentEvents && in_array(true, $availableAssessmentClassifications, true);

    $assessmentFilterSource = [];
    if (isset($_GET['assessmentFilter']) && is_array($_GET['assessmentFilter'])) {
        $assessmentFilterSource = $_GET['assessmentFilter'];
    } else {
        foreach (array_keys($assessmentClassificationDefinitions) as $key) {
            $legacyName = 'assessment'.str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
            if (isset($_GET[$legacyName])) {
                $assessmentFilterSource[$key] = $_GET[$legacyName];
            }
        }
    }
    $assessmentFilterState = ac_normalizeAssessmentFilterState($assessmentClassificationDefinitions, $assessmentFilterSource, $defaultAssessmentFilter);
    foreach ($availableAssessmentClassifications as $key => $available) {
        if (!$available) {
            $assessmentFilterState[$key] = 'N';
        }
    }

    $i18n = $session->get('i18n');
    $direction = ($i18n['rtl'] ?? 'N') === 'Y' ? 'rtl' : 'ltr';
    $localeFull = strtolower(str_replace('_', '-', $i18n['code'] ?? 'en'));
    $localeShort = substr($localeFull, 0, 2);

    $localeFileFull = "/lib/fullcalendar/packages/locales/{$localeFull}.global.min.js";
    $localeFileShort = "/lib/fullcalendar/packages/locales/{$localeShort}.global.min.js";
    $localeFile = '';
    $locale = 'en';

    if (file_exists($session->get('absolutePath').$localeFileFull)) {
        $localeFile = $session->get('absoluteURL').$localeFileFull;
        $locale = $localeFull;
    } elseif (file_exists($session->get('absolutePath').$localeFileShort)) {
        $localeFile = $session->get('absoluteURL').$localeFileShort;
        $locale = $localeShort;
    }

    $route = '/modules/Academic Calendar/calendar_view.php';
    $eventsJSONURL = $session->get('absoluteURL').'/modules/'.rawurlencode($session->get('module')).'/calendar_eventsJSON.php';

    if ($roleCategory === 'Parent' && empty($children)) {
        echo '<div class="warning">';
        echo __('No children were found for your account, or child data access is disabled.');
        echo '</div>';
        return;
    }

    $formAction = $isEmbed ? 'fullscreen.php' : 'index.php';

    if ($roleCategory === 'Staff' && !empty($yearGroups)) {
        echo '<form method="get" action="'.htmlspecialchars($formAction).'" class="acFilterRow">';
        echo '<input type="hidden" name="q" value="'.htmlspecialchars($route).'">';
        echo '<input type="hidden" name="viewDate" value="'.htmlspecialchars($viewDate).'">';
        echo '<input type="hidden" name="viewType" value="'.htmlspecialchars($viewType).'">';
        if ($isEmbed) {
            echo '<input type="hidden" name="embed" value="1">';
        }
        if ($showAssessmentFilterOptions) {
            foreach ($assessmentFilterState as $key => $value) {
                echo '<input type="hidden" name="assessmentFilter['.htmlspecialchars($key).']" value="'.htmlspecialchars($value).'">';
            }
        }
        echo '<label for="yearGroupID"><strong>'.__('Year Group').':</strong></label> ';
        echo '<select id="yearGroupID" name="yearGroupID" onchange="window.acSubmitCalendarFilters(this.form)">';
        echo '<option value="">'.__('All').'</option>';
        foreach ($yearGroups as $group) {
            $id = (string) $group['gibbonYearGroupID'];
            $label = (string) ($group['nameShort'] ?: $group['name']);
            $selected = $id === $yearGroupID ? ' selected' : '';
            echo '<option value="'.htmlspecialchars($id).'"'.$selected.'>'.htmlspecialchars($label).'</option>';
        }
        echo '</select>';
        echo '</form>';
    }

    if ($roleCategory === 'Parent' && !$isEmbed) {
        echo '<form method="get" action="'.htmlspecialchars($formAction).'" class="acFilterRow">';
        echo '<input type="hidden" name="q" value="'.htmlspecialchars($route).'">';
        echo '<input type="hidden" name="viewDate" value="'.htmlspecialchars($viewDate).'">';
        echo '<input type="hidden" name="viewType" value="'.htmlspecialchars($viewType).'">';
        if ($isEmbed) {
            echo '<input type="hidden" name="embed" value="1">';
        }
        if ($showAssessmentFilterOptions) {
            foreach ($assessmentFilterState as $key => $value) {
                echo '<input type="hidden" name="assessmentFilter['.htmlspecialchars($key).']" value="'.htmlspecialchars($value).'">';
            }
        }
        echo '<label for="childPersonID"><strong>'.__('Student').':</strong></label> ';
        echo '<select id="childPersonID" name="childPersonID" onchange="window.acSubmitCalendarFilters(this.form)">';
        foreach ($children as $child) {
            $id = (string) $child['childPersonID'];
            $name = trim((string) $child['preferredName'].' '.$child['surname']);
            $selected = $id === $childPersonID ? ' selected' : '';
            echo '<option value="'.htmlspecialchars($id).'"'.$selected.'>'.htmlspecialchars($name).'</option>';
        }
        echo '</select>';
        echo '</form>';
    }

    if ($showAssessmentFilterOptions) {
        echo '<form id="acAssessmentFilterForm" method="get" action="'.htmlspecialchars($formAction).'" class="acFilterRow acAssessmentFilterRow">';
        echo '<input type="hidden" name="q" value="'.htmlspecialchars($route).'">';
        echo '<input type="hidden" name="viewDate" value="'.htmlspecialchars($viewDate).'">';
        echo '<input type="hidden" name="viewType" value="'.htmlspecialchars($viewType).'">';
        if ($isEmbed) {
            echo '<input type="hidden" name="embed" value="1">';
        }
        if ($roleCategory === 'Staff') {
            echo '<input type="hidden" name="yearGroupID" value="'.htmlspecialchars($yearGroupID).'">';
        }
        if ($childPersonID !== '') {
            echo '<input type="hidden" name="childPersonID" value="'.htmlspecialchars($childPersonID).'">';
        }
        foreach ($assessmentFilterState as $key => $value) {
            echo '<input type="hidden" name="assessmentFilter['.htmlspecialchars($key).']" value="'.htmlspecialchars($value).'">';
        }

        echo '<span class="acAssessmentFilterLabel"><strong>'.__('Assessments').':</strong></span> ';
        foreach ($assessmentClassificationDefinitions as $key => $definition) {
            if (empty($availableAssessmentClassifications[$key])) {
                continue;
            }
            $checked = ($assessmentFilterState[$key] ?? 'Y') === 'Y' ? 'checked ' : '';
            $label = (string) $definition['label'];
            $filterColor = ac_normalizeHexColor((string) ($definition['color'] ?? '')) ?? '#9CA3AF';
            echo '<label class="acAssessmentFilterOption" style="--acFilterColor: '.htmlspecialchars($filterColor).';"><input type="checkbox" class="acAssessmentFilterCheckbox" data-filter-key="'.htmlspecialchars($key).'" '.$checked.'onchange="this.form.elements[\'assessmentFilter['.htmlspecialchars($key).']\'].value=this.checked?\'Y\':\'N\'; window.acSubmitCalendarFilters(this.form)"> <span>'.htmlspecialchars($label).'</span></label> ';
        }
        echo '</form>';
    }

    echo '<div id="academicCalendar"></div>';
    ?>
    <script src="<?= $session->get('absoluteURL'); ?>/lib/fullcalendar/dist/index.global.min.js"></script>
    <?= !empty($localeFile) ? '<script src="'.$localeFile.'"></script>' : '' ?>
    <script>
        (function () {
            const calendarElement = document.getElementById('academicCalendar');
            const endpoint = '<?= $eventsJSONURL; ?>';
            const isEmbed = <?= $isEmbed ? 'true' : 'false' ?>;
            const params = {
                yearGroupID: '<?= htmlspecialchars($yearGroupID, ENT_QUOTES); ?>',
                childPersonID: '<?= htmlspecialchars($childPersonID, ENT_QUOTES); ?>'
            };
            Object.assign(params, <?= json_encode(array_combine(
                array_map(function ($key) {
                    return 'assessmentFilter['.$key.']';
                }, array_keys($assessmentFilterState)),
                array_values($assessmentFilterState)
            ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?> || {});
            const yearGroupBadgeMap = <?= json_encode($yearGroupBadgeMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?> || {};
            const roleCategory = '<?= htmlspecialchars($roleCategory, ENT_QUOTES); ?>';
            let calendar = null;
            let resizeBound = false;
            let resizeTimer = null;
            let resizeObserver = null;
            let bootAttempts = 0;
            let assessmentFiltersMoved = false;

            function runCalendarResize() {
                if (!calendar) {
                    return;
                }

                window.requestAnimationFrame(function () {
                    if (!calendar) {
                        return;
                    }
                    calendar.updateSize();
                });
            }

            function scheduleCalendarResize() {
                if (!calendar) {
                    return;
                }

                if (resizeTimer) {
                    window.clearTimeout(resizeTimer);
                }

                runCalendarResize();

                window.setTimeout(function () {
                    if (!calendar) {
                        return;
                    }
                    calendar.updateSize();
                }, 100);

                window.setTimeout(function () {
                    if (!calendar) {
                        return;
                    }
                    calendar.updateSize();
                }, 300);

                resizeTimer = window.setTimeout(function () {
                    if (!calendar) {
                        return;
                    }
                    calendar.updateSize();
                }, 700);
            }

            function headerToolbarForSize() {
                if (window.innerWidth < 600) {
                    return {
                        start: 'title',
                        center: '',
                        end: 'prev,next today'
                    };
                }

                if (window.innerWidth < 900) {
                    return {
                        start: 'prev,next',
                        center: 'title',
                        end: 'dayGridMonth,listMonth today'
                    };
                }

                return {
                    start: 'prev,next today',
                    center: 'title',
                    end: 'dayGridMonth,timeGridWeek,listMonth'
                };
            }

            function bindResize() {
                if (resizeBound || !calendar) {
                    return;
                }

                resizeBound = true;
                window.addEventListener('resize', function () {
                    if (!calendar) {
                        return;
                    }

                    calendar.setOption('headerToolbar', headerToolbarForSize());
                    calendar.changeView(window.innerWidth < 765 ? 'listMonth' : 'dayGridMonth');
                    window.setTimeout(function () {
                        positionAssessmentFilters();
                        scheduleCalendarResize();
                    }, 0);
                });

                if (typeof ResizeObserver !== 'undefined' && calendarElement && calendarElement.parentElement) {
                    resizeObserver = new ResizeObserver(function () {
                        scheduleCalendarResize();
                    });
                    resizeObserver.observe(calendarElement.parentElement);
                }
            }

            function positionAssessmentFilters() {
                const filterForm = document.getElementById('acAssessmentFilterForm');
                if (!filterForm || !calendarElement) {
                    return;
                }

                const toolbar = calendarElement.querySelector('.fc .fc-header-toolbar');
                if (!toolbar) {
                    return;
                }

                filterForm.classList.add('acAssessmentFilterRowPlaced');
                toolbar.insertAdjacentElement('afterend', filterForm);
                assessmentFiltersMoved = true;
                scheduleCalendarResize();
            }

            function applyYearGroupTokenColor(info) {
                if (roleCategory !== 'Staff') {
                    return;
                }

                const props = info.event.extendedProps || {};
                const primaryYearGroupID = (props.primaryYearGroupID || '').toString();
                const badge = yearGroupBadgeMap[primaryYearGroupID];
                if (!badge || !badge.background || !badge.text) {
                    return;
                }

                const titleNodes = info.el.querySelectorAll('.fc-event-title, .fc-list-event-title, .fc-list-event-title a');
                titleNodes.forEach(function (node) {
                    if (!node || node.dataset.acYearGroupStyled === 'Y') {
                        return;
                    }

                    const text = (node.textContent || '').trim();
                    const match = text.match(/^(\(([^)]+)\))\s+(.*)$/);
                    if (!match) {
                        return;
                    }

                    node.innerHTML = '<span class="acYearGroupToken" style="--acYearGroupBackground: ' + badge.background + '; --acYearGroupText: ' + badge.text + ';">'
                        + match[2]
                        + '</span> '
                        + match[3];
                    node.dataset.acYearGroupStyled = 'Y';
                });
            }

            function initCalendar() {
                if (calendar || !calendarElement || typeof FullCalendar === 'undefined') {
                    return !!calendar;
                }

                try {
                    calendar = new FullCalendar.Calendar(calendarElement, {
                        timeZone: 'local',
                        editable: false,
                        selectable: false,
                        dayMaxEvents: true,
                        displayEventTime: true,
                        eventTimeFormat: {
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: false
                        },
                        firstDay: <?= $firstDay ?>,
                        direction: '<?= $direction ?>',
                        locale: '<?= $locale ?>',
                        weekends: <?= $showWeekends ? 'true' : 'false' ?>,
                        height: 'auto',
                        initialView: '<?= $viewType !== '' ? $viewType : '' ?>' || (window.innerWidth < 765 ? 'listMonth' : 'dayGridMonth'),
                        initialDate: '<?= $viewDate !== '' ? $viewDate : '' ?>' || undefined,
                        headerToolbar: headerToolbarForSize(),
                        eventOrder: function (a, b) {
                            const aProps = a.extendedProps || {};
                            const bProps = b.extendedProps || {};
                            const aSeq = Number.isFinite(Number(aProps.yearGroupSequence)) ? Number(aProps.yearGroupSequence) : Number.MAX_SAFE_INTEGER;
                            const bSeq = Number.isFinite(Number(bProps.yearGroupSequence)) ? Number(bProps.yearGroupSequence) : Number.MAX_SAFE_INTEGER;

                            if (aSeq !== bSeq) {
                                return aSeq - bSeq;
                            }

                            const aTitle = (a.title || '').toString().toLowerCase();
                            const bTitle = (b.title || '').toString().toLowerCase();
                            if (aTitle < bTitle) {
                                return -1;
                            }
                            if (aTitle > bTitle) {
                                return 1;
                            }

                            return 0;
                        },
                        eventSources: [{
                            url: endpoint,
                            method: 'GET',
                            extraParams: params
                        }],
                        datesSet: function () {
                            scheduleCalendarResize();
                        },
                        eventsSet: function () {
                            scheduleCalendarResize();
                        },
                        eventDidMount: function (info) {
                            const props = info.event.extendedProps || {};
                            if (props.source === 'Planner') {
                                info.el.style.color = '#111827';

                                const main = info.el.querySelector('.fc-event-main');
                                if (main) {
                                    main.style.color = '#111827';
                                }

                                const mainFrame = info.el.querySelector('.fc-event-main-frame');
                                if (mainFrame) {
                                    mainFrame.style.color = '#111827';
                                }

                                const title = info.el.querySelector('.fc-event-title');
                                if (title) {
                                    title.style.color = '#111827';
                                }

                                const time = info.el.querySelector('.fc-event-time');
                                if (time) {
                                    time.style.color = '#111827';
                                }
                            }
                            const lines = [];
                            const due = info.event.start ? FullCalendar.formatDate(info.event.start, info.event.allDay ? {
                                year: 'numeric',
                                month: 'short',
                                day: '2-digit'
                            } : {
                                year: 'numeric',
                                month: 'short',
                                day: '2-digit',
                                hour: '2-digit',
                                minute: '2-digit',
                                hour12: false
                            }) : '';

                            if (props.source === 'Markbook' && Array.isArray(props.mergedTooltipLines) && props.mergedTooltipLines.length > 0) {
                                props.mergedTooltipLines.forEach(function (groupLines) {
                                    if (Array.isArray(groupLines)) {
                                        groupLines.forEach(function (line) {
                                            if (line) {
                                                lines.push(line);
                                            }
                                        });
                                        lines.push('');
                                    }
                                });
                                if (lines.length > 0 && lines[lines.length - 1] === '') {
                                    lines.pop();
                                }
                            } else if (Array.isArray(props.tooltipLines) && props.tooltipLines.length > 0) {
                                props.tooltipLines.forEach(function (line) {
                                    if (line) {
                                        lines.push(line);
                                    }
                                });
                            } else {
                                if (props.subject) {
                                    lines.push(props.subject);
                                }
                                if (props.homeworkTitle && props.homeworkTitle !== props.subject) {
                                    lines.push(props.homeworkTitle);
                                }
                                if (props.description) {
                                    lines.push(props.description);
                                }
                                if (props.classLabel) {
                                    lines.push('<?= htmlspecialchars(__('Class'), ENT_QUOTES); ?>: ' + props.classLabel);
                                }
                                if (props.yearGroups) {
                                    lines.push('<?= htmlspecialchars(__('Year Group'), ENT_QUOTES); ?>: ' + props.yearGroups);
                                }
                                if (props.type) {
                                    lines.push('<?= htmlspecialchars(__('Type'), ENT_QUOTES); ?>: ' + props.type);
                                }
                                if (props.classification) {
                                    lines.push('<?= htmlspecialchars(__('Assessment Classification'), ENT_QUOTES); ?>: ' + props.classification);
                                }
                            }
                            if (due) {
                                lines.push('<?= htmlspecialchars(__('Due'), ENT_QUOTES); ?>: ' + due);
                            }

                            if (lines.length > 0) {
                                info.el.setAttribute('title', lines.join('\n'));
                            }

                            applyYearGroupTokenColor(info);
                        },
                        eventClick: function (info) {
                            if (info.event.url) {
                                if (isEmbed && window.top) {
                                    window.top.location.href = info.event.url;
                                } else {
                                    window.location.href = info.event.url;
                                }
                                info.jsEvent.preventDefault();
                            }
                        }
                    });

                    calendar.render();
                    positionAssessmentFilters();
                    scheduleCalendarResize();
                    bindResize();
                    return true;
                } catch (error) {
                    calendar = null;
                    return false;
                }
            }

            function bootCalendar() {
                if (initCalendar()) {
                    return;
                }

                bootAttempts += 1;
                if (bootAttempts < 6) {
                    window.setTimeout(bootCalendar, 150);
                }
            }

            function persistCalendarStateToForm(form) {
                if (!form || !calendar) {
                    return;
                }

                const currentDate = calendar.getDate();
                if (!currentDate) {
                    return;
                }

                const pad = function (value) {
                    return String(value).padStart(2, '0');
                };
                const dateValue = currentDate.getFullYear() + '-' + pad(currentDate.getMonth() + 1) + '-' + pad(currentDate.getDate());

                let viewDateInput = form.querySelector('input[name="viewDate"]');
                if (!viewDateInput) {
                    viewDateInput = document.createElement('input');
                    viewDateInput.type = 'hidden';
                    viewDateInput.name = 'viewDate';
                    form.appendChild(viewDateInput);
                }
                viewDateInput.value = dateValue;

                let viewTypeInput = form.querySelector('input[name="viewType"]');
                if (!viewTypeInput) {
                    viewTypeInput = document.createElement('input');
                    viewTypeInput.type = 'hidden';
                    viewTypeInput.name = 'viewType';
                    form.appendChild(viewTypeInput);
                }
                viewTypeInput.value = calendar.view ? calendar.view.type : '';
            }

            window.acSubmitCalendarFilters = function (form) {
                persistCalendarStateToForm(form);
                if (!form) {
                    return;
                }

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            };

            function bindFilterStatePersistence() {
                const forms = Array.prototype.slice.call(document.querySelectorAll('form.acFilterRow, form#acAssessmentFilterForm'));
                forms.forEach(function (form) {
                    form.addEventListener('submit', function () {
                        persistCalendarStateToForm(form);
                    });
                });
            }

            bootCalendar();
            bindFilterStatePersistence();
            window.addEventListener('load', bootCalendar, { once: true });
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    scheduleCalendarResize();
                }
            });
            if (!assessmentFiltersMoved) {
                window.setTimeout(positionAssessmentFilters, 200);
            }
        })();
    </script>
    <?php
}
