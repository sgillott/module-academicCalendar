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
        $page->breadcrumbs->add(__('Homework Calendar'));
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
    $defaultStaffView = (string) $settingGateway->getSettingByScope('Academic Calendar', 'defaultStaffView');
    $enabledYearGroupIDs = ac_getEnabledYearGroupIDs($settingGateway);

    $yearGroupID = '';
    $yearGroups = [];
    if ($roleCategory === 'Staff') {
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
        if ($yearGroupID === '' && $defaultStaffView === 'yearGroup' && !empty($yearGroups)) {
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
        if ($isEmbed) {
            echo '<input type="hidden" name="embed" value="1">';
        }
        echo '<label for="yearGroupID"><strong>'.__('Year Group').':</strong></label> ';
        echo '<select id="yearGroupID" name="yearGroupID" onchange="this.form.submit()">';
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
        if ($isEmbed) {
            echo '<input type="hidden" name="embed" value="1">';
        }
        echo '<label for="childPersonID"><strong>'.__('Student').':</strong></label> ';
        echo '<select id="childPersonID" name="childPersonID" onchange="this.form.submit()">';
        foreach ($children as $child) {
            $id = (string) $child['childPersonID'];
            $name = trim((string) $child['preferredName'].' '.$child['surname']);
            $selected = $id === $childPersonID ? ' selected' : '';
            echo '<option value="'.htmlspecialchars($id).'"'.$selected.'>'.htmlspecialchars($name).'</option>';
        }
        echo '</select>';
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
            let calendar = null;
            let resizeBound = false;
            let bootAttempts = 0;

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
                        initialView: window.innerWidth < 765 ? 'listMonth' : 'dayGridMonth',
                        headerToolbar: headerToolbarForSize(),
                        eventSources: [{
                            url: endpoint,
                            method: 'GET',
                            extraParams: params
                        }],
                        eventDidMount: function (info) {
                            const props = info.event.extendedProps || {};
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

                            if (props.subject) {
                                lines.push(props.subject);
                            }
                            if (props.homeworkTitle) {
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

            bootCalendar();
            window.addEventListener('load', bootCalendar, { once: true });
        })();
    </script>
    <?php
}
