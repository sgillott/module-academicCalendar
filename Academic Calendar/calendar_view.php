<?php

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
    $defaultAssessmentFilter = ac_getDefaultAssessmentFilter($settingGateway);
    $assessmentClassificationColors = ac_getAssessmentClassificationColors($settingGateway);
    $assessmentClassificationStyles = ac_buildAssessmentClassificationStyles($assessmentClassificationColors);
    $availableAssessmentClassifications = ac_getAvailableAssessmentClassifications($eventTypeMeta, true);
    $showAssessmentFilterOptions = $showAssessmentEvents
        && ($availableAssessmentClassifications['formative'] || $availableAssessmentClassifications['summative'] || $availableAssessmentClassifications['none']);

    $assessmentFormative = strtoupper((string) ($_GET['assessmentFormative'] ?? $defaultAssessmentFilter['formative']));
    $assessmentSummative = strtoupper((string) ($_GET['assessmentSummative'] ?? $defaultAssessmentFilter['summative']));
    $assessmentNone = strtoupper((string) ($_GET['assessmentNone'] ?? $defaultAssessmentFilter['none']));
    $assessmentFormative = $assessmentFormative === 'N' ? 'N' : 'Y';
    $assessmentSummative = $assessmentSummative === 'N' ? 'N' : 'Y';
    $assessmentNone = $assessmentNone === 'N' ? 'N' : 'Y';
    if (!$availableAssessmentClassifications['formative']) {
        $assessmentFormative = 'N';
    }
    if (!$availableAssessmentClassifications['summative']) {
        $assessmentSummative = 'N';
    }
    if (!$availableAssessmentClassifications['none']) {
        $assessmentNone = 'N';
    }

    $yearGroupID = '';
    $yearGroups = [];
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
            echo '<input type="hidden" name="assessmentFormative" value="'.htmlspecialchars($assessmentFormative).'">';
            echo '<input type="hidden" name="assessmentSummative" value="'.htmlspecialchars($assessmentSummative).'">';
            echo '<input type="hidden" name="assessmentNone" value="'.htmlspecialchars($assessmentNone).'">';
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
            echo '<input type="hidden" name="assessmentFormative" value="'.htmlspecialchars($assessmentFormative).'">';
            echo '<input type="hidden" name="assessmentSummative" value="'.htmlspecialchars($assessmentSummative).'">';
            echo '<input type="hidden" name="assessmentNone" value="'.htmlspecialchars($assessmentNone).'">';
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
        echo '<input type="hidden" name="assessmentFormative" value="'.htmlspecialchars($assessmentFormative).'">';
        echo '<input type="hidden" name="assessmentSummative" value="'.htmlspecialchars($assessmentSummative).'">';
        echo '<input type="hidden" name="assessmentNone" value="'.htmlspecialchars($assessmentNone).'">';

        echo '<span class="acAssessmentFilterLabel"><strong>'.__('Assessments').':</strong></span> ';
        if ($availableAssessmentClassifications['formative']) {
            echo '<label class="acAssessmentFilterOption"><input type="checkbox" class="acAssessmentFilterCheckbox acAssessmentFilterCheckboxFormative" '.($assessmentFormative === 'Y' ? 'checked ' : '').'onchange="this.form.assessmentFormative.value=this.checked?\'Y\':\'N\'; window.acSubmitCalendarFilters(this.form)"> <span>'.__('Formative').'</span></label> ';
        }
        if ($availableAssessmentClassifications['summative']) {
            echo '<label class="acAssessmentFilterOption"><input type="checkbox" class="acAssessmentFilterCheckbox acAssessmentFilterCheckboxSummative" '.($assessmentSummative === 'Y' ? 'checked ' : '').'onchange="this.form.assessmentSummative.value=this.checked?\'Y\':\'N\'; window.acSubmitCalendarFilters(this.form)"> <span>'.__('Summative').'</span></label>';
        }
        if ($availableAssessmentClassifications['none']) {
            echo '<label class="acAssessmentFilterOption"><input type="checkbox" class="acAssessmentFilterCheckbox acAssessmentFilterCheckboxNone" '.($assessmentNone === 'Y' ? 'checked ' : '').'onchange="this.form.assessmentNone.value=this.checked?\'Y\':\'N\'; window.acSubmitCalendarFilters(this.form)"> <span>'.__('Not Classified').'</span></label>';
        }
        echo '</form>';
    }

    echo '<div id="academicCalendar"></div>';
    echo '<style>
        #academicCalendar {
            --acClassificationNoneBorder: '.htmlspecialchars($assessmentClassificationStyles['none']['border']).';
            --acClassificationFormativeBorder: '.htmlspecialchars($assessmentClassificationStyles['formative']['border']).';
            --acClassificationSummativeBorder: '.htmlspecialchars($assessmentClassificationStyles['summative']['border']).';
        }
    </style>';
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
                childPersonID: '<?= htmlspecialchars($childPersonID, ENT_QUOTES); ?>',
                assessmentFormative: '<?= htmlspecialchars($assessmentFormative, ENT_QUOTES); ?>',
                assessmentSummative: '<?= htmlspecialchars($assessmentSummative, ENT_QUOTES); ?>',
                assessmentNone: '<?= htmlspecialchars($assessmentNone, ENT_QUOTES); ?>'
            };
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
                            if (props.source === 'Markbook') {
                                const useClassificationBorder = info.el.classList.contains('ac-event-assessment-classification-color') && info.event.borderColor;
                                if (useClassificationBorder) {
                                    info.el.style.borderStyle = 'solid';
                                    info.el.style.borderWidth = '2px';
                                    info.el.style.borderColor = info.event.borderColor;
                                    info.el.style.boxShadow = 'none';
                                } else {
                                    info.el.style.border = 'none';
                                    info.el.style.borderWidth = '0';
                                    info.el.style.boxShadow = 'none';
                                }

                                const main = info.el.querySelector('.fc-event-main');
                                if (main) {
                                    main.style.border = 'none';
                                    main.style.borderWidth = '0';
                                    main.style.boxShadow = 'none';
                                }
                            }
                            if (props.source === 'Planner') {
                                info.el.style.color = '#111827';

                                const main = info.el.querySelector('.fc-event-main');
                                if (main) {
                                    main.style.color = '#111827';
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
