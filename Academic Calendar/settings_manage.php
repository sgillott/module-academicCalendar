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
    $assessmentDisplayBasis = ac_getAssessmentDisplayBasis($settingGateway);
    $mergeSameDayAssessments = ac_getMergeSameDayAssessments($settingGateway) ? 'Y' : 'N';
    $assessmentClassificationColors = ac_getAssessmentClassificationColors($settingGateway);
    $assessmentClassificationStyles = ac_buildAssessmentClassificationStyles($assessmentClassificationColors);
    $useAssessmentClassificationColorInCalendar = ac_getUseAssessmentClassificationColorInCalendar($settingGateway) ? 'Y' : 'N';
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

    $allYearGroupCriteria = $yearGroupGateway->newQueryCriteria(true)->sortBy(['sequenceNumber']);
    $allYearGroupRows = $yearGroupGateway->queryYearGroups($allYearGroupCriteria)->toArray();
    $allYearGroupRows = ac_normalizeYearGroupRows($allYearGroupRows);
    $yearGroupMap = ac_buildYearGroupMap($allYearGroupRows);

    $sampleHomeworkRow = $pdo->select("
        SELECT
            p.name AS homeworkName,
            c.name AS courseName,
            c.nameShort AS courseNameShort,
            cc.nameShort AS classNameShort,
            c.gibbonYearGroupIDList
        FROM gibbonPlannerEntry p
        JOIN gibbonCourseClass cc ON (p.gibbonCourseClassID = cc.gibbonCourseClassID)
        JOIN gibbonCourse c ON (cc.gibbonCourseID = c.gibbonCourseID)
        WHERE p.homework = 'Y'
          AND p.homeworkDueDateTime IS NOT NULL
        ORDER BY p.homeworkDueDateTime DESC
        LIMIT 1
    ")->fetch(\PDO::FETCH_ASSOC);
    $usingHomeworkFallback = false;
    if (!is_array($sampleHomeworkRow) || empty($sampleHomeworkRow)) {
        $usingHomeworkFallback = true;
        $sampleHomeworkRow = [
            'homeworkName' => __('Essay draft'),
            'courseName' => __('English'),
            'courseNameShort' => '10Eng',
            'classNameShort' => '1',
            'gibbonYearGroupIDList' => '010',
        ];
    }

    $sampleAssessmentRow = $pdo->select("
        SELECT
            mc.name AS assessmentName,
            c.name AS courseName,
            c.nameShort AS courseNameShort,
            cc.nameShort AS classNameShort,
            COALESCE(d.name, d.nameShort, '') AS learningArea,
            c.gibbonYearGroupIDList
        FROM gibbonMarkbookColumn mc
        JOIN gibbonCourseClass cc ON (mc.gibbonCourseClassID = cc.gibbonCourseClassID)
        JOIN gibbonCourse c ON (cc.gibbonCourseID = c.gibbonCourseID)
        LEFT JOIN gibbonDepartment d ON (c.gibbonDepartmentID = d.gibbonDepartmentID)
        WHERE mc.date IS NOT NULL
        ORDER BY mc.date DESC, mc.gibbonMarkbookColumnID DESC
        LIMIT 1
    ")->fetch(\PDO::FETCH_ASSOC);
    $usingAssessmentFallback = false;
    if (!is_array($sampleAssessmentRow) || empty($sampleAssessmentRow)) {
        $usingAssessmentFallback = true;
        $sampleAssessmentRow = [
            'assessmentName' => __('Mock exam'),
            'courseName' => __('Chemistry'),
            'courseNameShort' => '11ChemSL',
            'classNameShort' => '1',
            'learningArea' => __('Chemistry'),
            'gibbonYearGroupIDList' => '011',
        ];
    }

    $sampleHomeworkTitle = trim((string) ($sampleHomeworkRow['homeworkName'] ?? __('Homework')));
    $sampleHomeworkYearGroupsText = ac_buildYearGroupsText((string) ($sampleHomeworkRow['gibbonYearGroupIDList'] ?? ''), $yearGroupMap);
    $staffEventFormatPreviewOptions = [
        'codeTitle' => ac_buildStaffEventTitle($sampleHomeworkRow, $sampleHomeworkTitle, $sampleHomeworkYearGroupsText, 'codeTitle'),
        'yearGroupCodeTitle' => ac_buildStaffEventTitle($sampleHomeworkRow, $sampleHomeworkTitle, $sampleHomeworkYearGroupsText, 'yearGroupCodeTitle'),
        'subjectCodeTitle' => ac_buildStaffEventTitle($sampleHomeworkRow, $sampleHomeworkTitle, $sampleHomeworkYearGroupsText, 'subjectCodeTitle'),
    ];

    $sampleAssessmentTitle = trim((string) ($sampleAssessmentRow['assessmentName'] ?? __('Assessment')));
    $sampleAssessmentYearGroupsText = ac_buildYearGroupsText((string) ($sampleAssessmentRow['gibbonYearGroupIDList'] ?? ''), $yearGroupMap);
    $assessmentDisplayPreviewOptions = [];
    foreach (['classCode', 'courseShortName', 'courseName', 'learningArea'] as $basis) {
        $subject = ac_getAssessmentDisplayValue($sampleAssessmentRow, $basis, __('Assessment'));
        $preview = $subject;
        if ($basis === 'learningArea' && $sampleAssessmentYearGroupsText !== '') {
            $preview = '('.$sampleAssessmentYearGroupsText.') '.$preview;
        }
        if ($sampleAssessmentTitle !== '' && mb_strtolower($sampleAssessmentTitle) !== mb_strtolower($subject)) {
            $preview .= ' - '.$sampleAssessmentTitle;
        }
        $assessmentDisplayPreviewOptions[$basis] = $preview;
    }

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
    $row->addLabel('staffEventFormat', __('Homework Format in Staff Calendar'))
        ->description(__('Choose how homework titles appear for staff in the calendar. This controls how course, class code, year group, and title are combined.'));
    $row->addSelect('staffEventFormat')->fromArray([
        'codeTitle' => __('Class Code - Title'),
        'yearGroupCodeTitle' => __('Year Group + Class Code - Title'),
        'subjectCodeTitle' => __('Course (Class Code) - Title'),
    ])->selected($staffEventFormat);

    $row = $form->addRow();
    $row->addLabel('staffEventFormatPreview', __('Preview'))
        ->description($usingHomeworkFallback
            ? __('Preview uses an example homework because no recent homework was found in this environment.')
            : __('Preview uses your latest homework event from the database.'));
    $row->addContent(
        '<div class="acSettingsPreview" id="acStaffEventFormatPreview" data-preview-map="'.htmlspecialchars(json_encode($staffEventFormatPreviewOptions), ENT_QUOTES, 'UTF-8').'">'
        .htmlspecialchars($staffEventFormatPreviewOptions[$staffEventFormat], ENT_QUOTES, 'UTF-8')
        .'</div>'
    );

    $row = $form->addRow();
    $row->addLabel('assessmentDisplayBasis', __('Assessment Format in Staff Calendar'))
        ->description(__('Choose which course field is used when naming assessment events for staff. This also controls how same-day assessment merges are labelled.'));
    $row->addSelect('assessmentDisplayBasis')->fromArray([
        'classCode' => __('Class Code'),
        'courseShortName' => __('Course Short Name'),
        'courseName' => __('Course Name'),
        'learningArea' => __('Learning Area'),
    ])->selected($assessmentDisplayBasis);

    $row = $form->addRow();
    $row->addLabel('assessmentDisplayBasisPreview', __('Preview'))
        ->description($usingAssessmentFallback
            ? __('Preview uses an example assessment because no recent markbook assessment was found in this environment.')
            : __('Preview uses your latest markbook assessment from the database.'));
    $row->addContent(
        '<div class="acSettingsPreview" id="acAssessmentDisplayBasisPreview" data-preview-map="'.htmlspecialchars(json_encode($assessmentDisplayPreviewOptions), ENT_QUOTES, 'UTF-8').'">'
        .htmlspecialchars($assessmentDisplayPreviewOptions[$assessmentDisplayBasis], ENT_QUOTES, 'UTF-8')
        .'</div>'
    );

    $row = $form->addRow();
    $row->addLabel('mergeSameDayAssessments', __('Merge Same-Day Assessments'))
        ->description(__('When enabled, assessment rows with the same display value on the same date are shown as one grouped event.'));
    $row->addYesNo('mergeSameDayAssessments')->selected($mergeSameDayAssessments);

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

    $enabledYearGroupIDs = ac_parseIDList($enabledYearGroupIDList);
    $overviewYearGroups = ac_filterYearGroupsByEnabled($allYearGroupRows, $enabledYearGroupIDs);

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

    $row = $form->addRow();
    $row->addLabel('assessmentClassificationColor_formative', __('Formative Colour'))
        ->description(__('Used for formative filter controls, settings-row highlighting, and calendar events when classification colours are enabled.'));
    $row->addColor('assessmentClassificationColor_formative')
        ->setPalette('background')
        ->setOuterClass('acColorSetting')
        ->addClass('acColorSettingInput')
        ->setValue($assessmentClassificationColors['formative']);

    $row = $form->addRow();
    $row->addLabel('assessmentClassificationColor_summative', __('Summative Colour'))
        ->description(__('Used for summative filter controls, settings-row highlighting, and calendar events when classification colours are enabled.'));
    $row->addColor('assessmentClassificationColor_summative')
        ->setPalette('background')
        ->setOuterClass('acColorSetting')
        ->addClass('acColorSettingInput')
        ->setValue($assessmentClassificationColors['summative']);

    $row = $form->addRow();
    $row->addLabel('assessmentClassificationColor_none', __('Not Classified Colour'))
        ->description(__('Used for not-classified filter controls, settings-row highlighting, and calendar events when classification colours are enabled.'));
    $row->addColor('assessmentClassificationColor_none')
        ->setPalette('background')
        ->setOuterClass('acColorSetting')
        ->addClass('acColorSettingInput')
        ->setValue($assessmentClassificationColors['none']);

    $row = $form->addRow();
    $row->addLabel('useAssessmentClassificationColorInCalendar', __('Use Assessment Classification Colour in Calendar'))
        ->description(__('When enabled, assessment events on the Homework/Assessment Calendar use the formative, summative, or not classified colours instead of the event type colour.'));
    $row->addYesNo('useAssessmentClassificationColorInCalendar')->selected($useAssessmentClassificationColorInCalendar);

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

    echo '<style>
        #acEventTypesTable {
            --acClassificationNoneBorder: '.htmlspecialchars($assessmentClassificationStyles['none']['border']).';
            --acClassificationNoneHighlight: '.htmlspecialchars($assessmentClassificationStyles['none']['highlight']).';
            --acClassificationFormativeBorder: '.htmlspecialchars($assessmentClassificationStyles['formative']['border']).';
            --acClassificationFormativeHighlight: '.htmlspecialchars($assessmentClassificationStyles['formative']['highlight']).';
            --acClassificationSummativeBorder: '.htmlspecialchars($assessmentClassificationStyles['summative']['border']).';
            --acClassificationSummativeHighlight: '.htmlspecialchars($assessmentClassificationStyles['summative']['highlight']).';
        }
        .acSettingsPreview {
            display: inline-block;
            min-width: 28rem;
            max-width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            background: #f8fafc;
            color: #1f2937;
            font-weight: 600;
            line-height: 1.5;
        }
    </style>';

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
        var updatePreview = function (selectName, previewId) {
            var selectEl = document.querySelector('select[name="' + selectName + '"]');
            var previewEl = document.getElementById(previewId);
            if (!selectEl || !previewEl) return;

            var previewMap = {};
            try {
                previewMap = JSON.parse(previewEl.getAttribute('data-preview-map') || '{}');
            } catch (error) {
                previewMap = {};
            }

            var render = function () {
                var value = selectEl.value;
                previewEl.textContent = previewMap[value] || '';
            };

            selectEl.addEventListener('change', render);
            render();
        };

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

        updatePreview('staffEventFormat', 'acStaffEventFormatPreview');
        updatePreview('assessmentDisplayBasis', 'acAssessmentDisplayBasisPreview');
    })();
    </script>
    <?php
}
