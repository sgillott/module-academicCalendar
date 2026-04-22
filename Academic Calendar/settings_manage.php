<?php

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\School\YearGroupGateway;

require_once __DIR__.'/moduleFunctions.php';

/**
 * Manage Settings page.
 *
 * Builds the module settings form, including:
 * - enabled year groups
 * - calendar display options
 * - summative overview thresholds
 * - per-event-type display metadata
 * - permissions shortcut.
 */
if (!isActionAccessible($guid, $connection2, '/modules/Academic Calendar/settings_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Manage Settings'));

    $settingGateway = $container->get(SettingGateway::class);
    $yearGroupGateway = $container->get(YearGroupGateway::class);

    $colors = ac_getColorMap($settingGateway);
    $typeMeta = ac_getEventTypeMeta($settingGateway);
    $showWeekends = (string) $settingGateway->getSettingByScope('Academic Calendar', 'showWeekends');
    $showHomeworkEvents = (string) $settingGateway->getSettingByScope('Academic Calendar', 'showHomeworkEvents');
    $showAssessmentEvents = (string) $settingGateway->getSettingByScope('Academic Calendar', 'showAssessmentEvents');
    $defaultAssessmentFilter = ac_getDefaultAssessmentFilter($settingGateway);
    $defaultStaffView = (string) $settingGateway->getSettingByScope('Academic Calendar', 'defaultStaffView');
    $staffEventFormat = ac_getStaffEventFormat($settingGateway);
    $enabledYearGroupIDList = (string) ($settingGateway->getSettingByScope('Academic Calendar', 'gibbonYearGroupIDList') ?: '');
    $defaultSummativeThreshold = ac_getSummativeThresholdDefault($settingGateway);
    $thresholdByYearGroup = ac_getSummativeThresholdByYearGroup($settingGateway);
    $overviewWeekNumberMode = ac_getOverviewWeekNumberMode($settingGateway);
    $isAdminRole = (string) $session->get('gibbonRoleIDCurrent') === '001';
    $moduleID = $pdo->select("
        SELECT gibbonModuleID
        FROM gibbonModule
        WHERE name = 'Academic Calendar'
    ")->fetchColumn();
    $permissionsURL = $session->get('absoluteURL').'/index.php?q=%2Fmodules%2FUser+Admin%2Fpermission_manage.php&gibbonModuleID='
        .urlencode((string) $moduleID).'&gibbonRoleID=&Go=Go';

    $types = $pdo->select("
        SELECT DISTINCT type
        FROM gibbonMarkbookColumn
        WHERE type IS NOT NULL AND type <> ''
        ORDER BY type
    ")->fetchAll(\PDO::FETCH_COLUMN);

    $form = Form::create('settings_manage', $session->get('absoluteURL').'/modules/'.$session->get('module').'/settings_manageProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));

    $row = $form->addRow();
    $row->addHeading(__('Year Groups'));

    $row = $form->addRow();
    $row->addLabel('gibbonYearGroupIDList', __('Year Groups'))->description(__('Academic Calendar is enabled for these year groups.'));
    if (trim($enabledYearGroupIDList) === '') {
        $row->addCheckboxYearGroup('gibbonYearGroupIDList')->addCheckAllNone()->checkAll();
    } else {
        $yearGroupValues = [
            'gibbonYearGroupIDList' => $enabledYearGroupIDList,
        ];
        $row->addCheckboxYearGroup('gibbonYearGroupIDList')->addCheckAllNone()->loadFromCSV($yearGroupValues);
    }

    $row = $form->addRow();
    $row->addHeading(__('Calendar Display'));

    $row = $form->addRow();
    $row->addLabel('showWeekends', __('Show Weekends on Calendar'));
    $row->addYesNo('showWeekends')->selected($showWeekends === 'N' ? 'N' : 'Y');

    $row = $form->addRow();
    $row->addLabel('showHomeworkEvents', __('Show Homework Events on Calendar'));
    $row->addYesNo('showHomeworkEvents')->selected($showHomeworkEvents === 'N' ? 'N' : 'Y');

    $row = $form->addRow();
    $row->addLabel('showAssessmentEvents', __('Show Assessment Events on Calendar'));
    $row->addYesNo('showAssessmentEvents')->selected($showAssessmentEvents === 'N' ? 'N' : 'Y');

    $form->toggleVisibilityByClass('acAssessmentSettings')->onClick('showAssessmentEvents')->when('Y');
    $row = $form->addRow();
    $row->addClass('acAssessmentSettings');
    $row->addLabel('defaultAssessmentFilter', __('Default Assessment Filter'))
        ->description(__('Default user filter for formative and summative assessment events.'));
    $row->addCheckbox('defaultAssessmentFilter')
        ->fromArray([
            'formative' => __('Formative'),
            'summative' => __('Summative'),
            'none' => __('Not Classified'),
        ])
        ->checked(array_keys(array_filter($defaultAssessmentFilter, function ($value) {
            return strtoupper((string) $value) === 'Y';
        })))
        ->addCheckAllNone();

    $row = $form->addRow();
    $row->addLabel('defaultStaffView', __('Default Staff View'))
        ->description(__('Choose whether staff start with events from all available year groups or one year group at a time.'));
    $row->addSelect('defaultStaffView')->fromArray([
        'all' => __('All Homework'),
        'yearGroup' => __('Filter by Year Group'),
    ])->selected($defaultStaffView === 'yearGroup' ? 'yearGroup' : 'all');

    $row = $form->addRow();
    $row->addLabel('staffEventFormat', __('Staff Event Format'))
        ->description(__('Choose how staff homework and assessment labels are shown in the calendar.'));
    $row->addSelect('staffEventFormat')->fromArray([
        'codeTitle' => __('Class Code - Title'),
        'yearGroupCodeTitle' => __('Year Group + Class Code - Title'),
        'subjectCodeTitle' => __('Subject (Class Code) - Title'),
    ])->selected($staffEventFormat);

    $row = $form->addRow();
    $row->addHeading(__('Summative Assessment Overview'));

    $row = $form->addRow();
    $row->addLabel('overviewWeekNumberMode', __('Overview Week Number Mode'))
        ->description(__('Choose whether the overview shows calendar weeks or academic weeks that pause during full-school closure weeks.'));
    $row->addSelect('overviewWeekNumberMode')->fromArray([
        'calendar' => __('Calendar Week'),
        'academic' => __('Academic Week'),
    ])->selected($overviewWeekNumberMode);

    $row = $form->addRow();
    $row->addLabel('summativeWeeklyThresholdDefault', __('Default Summative Assessment Weekly Threshold'))
        ->description(__('Fallback threshold used when a year group does not have its own setting.'));
    $row->addNumber('summativeWeeklyThresholdDefault')
        ->minimum(1)
        ->maximum(99)
        ->required()
        ->setValue($defaultSummativeThreshold)
        ->setClass('w-20');

    $criteria = $yearGroupGateway->newQueryCriteria(true)->sortBy(['sequenceNumber']);
    $allYearGroups = $yearGroupGateway->queryYearGroups($criteria)->toArray();
    $allYearGroups = ac_normalizeYearGroupRows($allYearGroups);
    $enabledYearGroupIDs = ac_parseIDList($enabledYearGroupIDList);
    $overviewYearGroups = ac_filterYearGroupsByEnabled($allYearGroups, $enabledYearGroupIDs);

    if (!empty($overviewYearGroups)) {
        $row = $form->addRow();
        $row->addLabel('summativeWeeklyThresholdByYearGroup', __('Threshold by Year Group'))
            ->description(__('Leave blank to use the default threshold.'));

        $table = $row->addTable('acThresholdTable')->addClass('colorOddEven');
        $header = $table->addHeaderRow();
        $header->addContent(__('Year Group'));
        $header->addContent(__('Weekly Threshold'));

        foreach ($overviewYearGroups as $group) {
            $yearGroupID = (string) $group['gibbonYearGroupID'];
            $yearGroupLabel = (string) ($group['nameShort'] ?: $group['name']);
            $savedThreshold = isset($thresholdByYearGroup[$yearGroupID]) ? (int) $thresholdByYearGroup[$yearGroupID] : '';

            $row = $table->addRow();
            $row->addContent($yearGroupLabel);
            $row->addNumber('summativeWeeklyThresholdByYearGroup['.$yearGroupID.']')
                ->minimum(1)
                ->maximum(99)
                ->setClass('w-20')
                ->setValue($savedThreshold);
        }
    }

    $row = $form->addRow();
    $row->addHeading(__('Event Types'));

    $row = $form->addRow();
    $row->addContent(__('Configure colour, visibility, and assessment classification for each markbook event type.'));

    if (!empty($types)) {
        $table = $form->addRow()->addTable('acEventTypesTable')->addClass('colorOddEven acEventTypesTable');

        $header = $table->addHeaderRow();
        $header->addContent(__('Type'));
        $header->addContent(__('Colour'));
        $header->addContent(__('Assessment Classification'));
        $header->addContent(__('Visible'));
        $header->addCheckbox('acCheckAllEventTypes')->setClass('floatNone textCenter checkall acEventTypesCheckAll');

        foreach ($types as $type) {
            $type = (string) $type;
            $hash = md5($type);
            $colorField = 'typeColor['.$hash.']';
            $visibleField = 'typeVisible['.$hash.']';
            $classificationField = 'typeClassification['.$hash.']';

            $defaultColor = $colors[$type] ?? ac_colorFromPalette($type);
            $visible = strtoupper((string) ($typeMeta[$type]['visible'] ?? 'Y'));
            $classification = strtolower((string) ($typeMeta[$type]['classification'] ?? ''));
            if (!in_array($classification, ['', 'formative', 'summative'], true)) {
                $classification = '';
            }

            $row = $table->addRow();
            $row->addClass('acEventTypeRow');
            $row->addContent($type);
            $row->addColor($colorField)
                ->setPalette('background')
                ->setOuterClass('acColorSetting')
                ->addClass('acColorSettingInput')
                ->setValue($defaultColor);
            $row->addSelect($classificationField)->fromArray([
                '' => __('None'),
                'formative' => __('Formative'),
                'summative' => __('Summative'),
            ])->selected($classification)->addClass('w-48');
            $row->addContent('');
            $row->addCheckbox($visibleField)->setValue('Y')->checked($visible === 'Y' ? 'Y' : '')->addClass('acEventTypeVisible')->alignCenter();
        }
    } else {
        $row = $form->addRow();
        $row->addContent(__('No markbook event types were found.'));
    }

    if ($isAdminRole) {
        $row = $form->addRow();
        $row->addHeading(__('Permissions'));

        $row = $form->addRow();
        $row->addLabel('managePermissions', __('User Permissions'))
            ->description(__('Open User Admin permissions for this module.'));
        $row->addContent('<a class="button buttonAsLink" href="'.htmlspecialchars($permissionsURL).'">'.__('Manage User Permissions').'</a>');
    }

    $row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

    echo $form->getOutput();
    ?>
    <script>
    (function () {
        var table = document.getElementById('acEventTypesTable');
        if (!table) return;

        var checkAll = table.querySelector('input.acEventTypesCheckAll[type="checkbox"]');
        var boxes = function () {
            return Array.prototype.slice.call(table.querySelectorAll('input.acEventTypeVisible[type="checkbox"], .acEventTypeVisible input[type="checkbox"]'));
        };
        var classificationSelects = Array.prototype.slice.call(table.querySelectorAll('select[name^="typeClassification["]'));

        var updateCheckAllState = function () {
            if (!checkAll) return;
            var visibleBoxes = boxes();
            var checkedCount = visibleBoxes.filter(function (box) {
                return box.checked;
            }).length;

            checkAll.checked = visibleBoxes.length > 0 && checkedCount === visibleBoxes.length;
            checkAll.indeterminate = checkedCount > 0 && checkedCount < visibleBoxes.length;
        };

        var updateClassificationStyle = function (selectEl) {
            if (!selectEl) return;

            var row = selectEl.closest('tr');
            if (!row) return;

            row.classList.remove('acClassificationNone', 'acClassificationFormative', 'acClassificationSummative');
            row.classList.add('acClassificationNone');
            if (selectEl.value === 'formative') {
                row.classList.remove('acClassificationNone');
                row.classList.add('acClassificationFormative');
            } else if (selectEl.value === 'summative') {
                row.classList.remove('acClassificationNone');
                row.classList.add('acClassificationSummative');
            }
        };

        if (checkAll) {
            checkAll.addEventListener('change', function () {
                boxes().forEach(function (box) {
                    box.checked = !!checkAll.checked;
                });
                updateCheckAllState();
            });
        }

        boxes().forEach(function (box) {
            box.addEventListener('change', updateCheckAllState);
        });
        updateCheckAllState();

        classificationSelects.forEach(function (selectEl) {
            updateClassificationStyle(selectEl);
            selectEl.addEventListener('change', function () {
                updateClassificationStyle(selectEl);
            });
        });
    })();
    </script>
    <?php
}
