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
 * - overview thresholds
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
    $enabledYearGroupIDList = (string) ($settingGateway->getSettingByScope('Academic Calendar', 'gibbonYearGroupIDList') ?: '');
    $defaultOverviewThreshold = ac_getSummativeThresholdDefault($settingGateway);
    $thresholdByYearGroup = ac_getSummativeThresholdByYearGroup($settingGateway);
    $overviewWeekNumberMode = ac_getOverviewWeekNumberMode($settingGateway);
    $assessmentDisplayBasis = ac_getAssessmentDisplayBasis($settingGateway);
    $mergeSameDayAssessments = ac_getMergeSameDayAssessments($settingGateway) ? 'Y' : 'N';
    $assessmentClassificationDefinitions = ac_getAssessmentClassificationDefinitions($settingGateway);
    $assessmentClassificationColors = array_map(function ($definition) {
        return (string) $definition['color'];
    }, $assessmentClassificationDefinitions);
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
            COALESCE(d.name, d.nameShort, '') AS learningArea,
            c.gibbonYearGroupIDList
        FROM gibbonPlannerEntry p
        JOIN gibbonCourseClass cc ON (p.gibbonCourseClassID = cc.gibbonCourseClassID)
        JOIN gibbonCourse c ON (cc.gibbonCourseID = c.gibbonCourseID)
        LEFT JOIN gibbonDepartment d ON (c.gibbonDepartmentID = d.gibbonDepartmentID)
        WHERE p.homework = 'Y'
          AND p.homeworkDueDateTime IS NOT NULL
        ORDER BY p.homeworkDueDateTime DESC
        LIMIT 1
    ")->fetch(\PDO::FETCH_ASSOC);
    if (!is_array($sampleHomeworkRow) || empty($sampleHomeworkRow)) {
        $sampleHomeworkRow = [
            'homeworkName' => __('Essay draft'),
            'courseName' => __('English'),
            'courseNameShort' => '10Eng',
            'classNameShort' => '1',
            'learningArea' => __('English'),
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
    if (!is_array($sampleAssessmentRow) || empty($sampleAssessmentRow)) {
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
    $sampleAssessmentTitle = trim((string) ($sampleAssessmentRow['assessmentName'] ?? __('Assessment')));
    $sampleAssessmentYearGroupsText = ac_buildYearGroupsText((string) ($sampleAssessmentRow['gibbonYearGroupIDList'] ?? ''), $yearGroupMap);
    $eventDisplayPreviewOptions = [];
    foreach (['classCode', 'courseShortName', 'courseName', 'learningArea'] as $basis) {
        $homeworkPreview = ac_buildStaffEventTitle($sampleHomeworkRow, $sampleHomeworkTitle, $sampleHomeworkYearGroupsText, $basis);
        $assessmentSubject = ac_getAssessmentDisplayValue($sampleAssessmentRow, $basis, __('Assessment'));
        $assessmentPreview = ac_buildAssessmentEventTitle(
            $assessmentSubject,
            $sampleAssessmentTitle,
            'Staff',
            $basis,
            $sampleAssessmentYearGroupsText
        );
        $eventDisplayPreviewOptions[$basis] = __('Homework').': '.$homeworkPreview."\n".__('Assessment').': '.$assessmentPreview;
    }

    $form = Form::create('settings_manage', $session->get('absoluteURL').'/modules/'.$session->get('module').'/settings_manageProcess.php');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('addAssessmentClassification', '');
    $form->addHiddenValue('deleteAssessmentClassification', '');

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
        ->description(__('Default user filter for assessment classifications.'));
    $defaultAssessmentFilterOptions = [];
    foreach ($assessmentClassificationDefinitions as $key => $definition) {
        $defaultAssessmentFilterOptions[$key] = (string) $definition['label'];
    }
    $row->addCheckbox('defaultAssessmentFilter')
        ->fromArray($defaultAssessmentFilterOptions)
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
    $row->addLabel('assessmentDisplayBasis', __('Calendar Event Display Basis'))
        ->description(__('Choose which course field is used when naming homework and assessment events in the calendar.'));
    $row->addSelect('assessmentDisplayBasis')->fromArray([
        'classCode' => __('Class Code'),
        'courseShortName' => __('Course Short Name'),
        'courseName' => __('Course Name'),
        'learningArea' => __('Learning Area'),
    ])->selected($assessmentDisplayBasis);

    $row = $form->addRow();
    $row->addLabel('assessmentDisplayBasisPreview', __('Preview'))
        ->description(__('Preview shows how homework and assessment events will be shown in the calendar.'));
    $row->addContent(
        '<div class="acSettingsPreview" id="acAssessmentDisplayBasisPreview" data-preview-map="'.htmlspecialchars(json_encode($eventDisplayPreviewOptions), ENT_QUOTES, 'UTF-8').'">'
        .nl2br(htmlspecialchars($eventDisplayPreviewOptions[$assessmentDisplayBasis], ENT_QUOTES, 'UTF-8'))
        .'</div>'
    );

    $row = $form->addRow();
    $row->addLabel('mergeSameDayAssessments', __('Merge Same-Day Assessments'))
        ->description(__('When enabled, assessment rows with the same display value on the same date are grouped together in the calendar and overview.'));
    $row->addYesNo('mergeSameDayAssessments')->selected($mergeSameDayAssessments);

    $row = $form->addRow();
    $row->addHeading(__('Assessment Overview'));

    $row = $form->addRow();
    $row->addLabel('overviewWeekNumberMode', __('Overview Week Number Mode'))
        ->description(__('Choose whether the overview shows calendar weeks or academic weeks that pause during full-school closure weeks.'));
    $row->addSelect('overviewWeekNumberMode')->fromArray([
        'calendar' => __('Calendar Week'),
        'academic' => __('Academic Week'),
    ])->selected($overviewWeekNumberMode);

    $row = $form->addRow();
    $row->addLabel('summativeWeeklyThresholdDefault', __('Default Overview Weekly Threshold'))
        ->description(__('Fallback threshold used when a year group does not have its own setting.'));
    $row->addNumber('summativeWeeklyThresholdDefault')
        ->minimum(1)
        ->maximum(99)
        ->required()
        ->setValue($defaultOverviewThreshold)
        ->setClass('w-20');

    $enabledYearGroupIDs = ac_parseIDList($enabledYearGroupIDList);
    $overviewYearGroups = ac_filterYearGroupsByEnabled($allYearGroupRows, $enabledYearGroupIDs);

    if (!empty($overviewYearGroups)) {
        $row = $form->addRow();
        $row->addLabel('summativeWeeklyThresholdByYearGroup', __('Overview Threshold by Year Group'))
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
    $row->addLabel('assessmentClassifications', __('Assessment Classification Metadata'))
        ->description(__('Create the classifications available for markbook event types. Not Classified is a fixed fallback for visible unclassified events.'));
    $classificationTable = $row->addTable('acClassificationManageTable')->addClass('colorOddEven acClassificationManageTable');
    $header = $classificationTable->addHeaderRow();
    $header->addContent(__('Label'));
    $header->addContent(__('Colour'));
    $header->addContent(__('Display in Overview'));
    $header->addContent(__('Delete'));

    foreach ($assessmentClassificationDefinitions as $key => $definition) {
        if ($key === 'none') {
            continue;
        }

        $classificationRow = $classificationTable->addRow();
        $classificationRow->addClass('acClassificationManageRow');
        $classificationRow->addTextField('assessmentClassificationLabel['.$key.']')
            ->setValue((string) $definition['label'])
            ->setClass('w-48');
        $classificationRow->addColor('assessmentClassificationColor['.$key.']')
            ->setPalette('background')
            ->setOuterClass('acColorSetting')
            ->addClass('acColorSettingInput')
            ->setValue((string) $definition['color']);
        $classificationRow->addCheckbox('assessmentClassificationDisplayInOverview[]')
            ->setValue($key)
            ->checked((string) ($definition['displayInOverview'] ?? 'N') === 'Y' ? $key : '')
            ->alignCenter();
        $classificationRow->addContent(
            '<button type="button" data-classification-key="'.htmlspecialchars($key, ENT_QUOTES, 'UTF-8').'" title="'.__('Delete').'" class="acDeleteClassificationButton inline-flex items-center align-middle rounded-md text-sm sm:leading-5 font-semibold px-3 py-2 bg-white shadow-sm border border-gray-400 hover:bg-gray-100 text-gray-600 hover:text-red-700 hover:border-red-700">'
            .icon('solid', 'delete', 'size-6 sm:size-5')
            .'</button>'
        )->addClass('textCenter');
    }

    $classificationFallbackRow = $classificationTable->addRow();
    $classificationFallbackRow->addClass('acClassificationManageRow acClassificationFallbackRow');
    $classificationFallbackRow->addContent('<strong>'.htmlspecialchars((string) $assessmentClassificationDefinitions['none']['label'], ENT_QUOTES, 'UTF-8').'</strong>');
    $classificationFallbackRow->addColor('assessmentClassificationColor[none]')
        ->setPalette('background')
        ->setOuterClass('acColorSetting')
        ->addClass('acColorSettingInput')
        ->setValue((string) $assessmentClassificationDefinitions['none']['color']);
    $classificationFallbackRow->addContent('');
    $classificationFallbackRow->addContent('');

    $classificationAddRow = $classificationTable->addRow();
    $classificationAddRow->addClass('acClassificationAddRow');
    $classificationAddRow->addContent(
        '<button type="button" id="acAddClassificationButton" class="inline-flex items-center align-middle rounded-md text-sm sm:leading-5 font-semibold px-3 py-2 bg-white shadow-sm border border-gray-400 hover:bg-gray-100 hover:text-green-500 hover:border-green-500 text-gray-600">'
        .icon('solid', 'add', 'size-6 sm:size-5 lg:-ml-0.5 lg:mr-1.5')
        .'<span class="hidden lg:block text-gray-800 whitespace-nowrap">'
        .__('Add Classification')
        .'</span></button>'
    )->addClass('textRight');

    $row = $form->addRow();
    $row->addLabel('useAssessmentClassificationColorInCalendar', __('Override and Use Assessment Classification Colour in Calendar'))
        ->description(__('When enabled, assessment events on the Homework/Assessment Calendar use assessment classification colours instead of the event type colour.'));
    $row->addYesNo('useAssessmentClassificationColorInCalendar')->selected($useAssessmentClassificationColorInCalendar);

    if (!empty($types)) {
        $table = $form->addRow()->addTable('acEventTypesTable')->addClass('colorOddEven acEventTypesTable');

        $header = $table->addHeaderRow();
        $header->addContent(__('Type'));
        $header->addContent(__('Colour'));
        $header->addContent(__('Assessment Classification Metadata'));
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
            $classification = ac_normalizeAssessmentClassificationKey((string) ($typeMeta[$type]['classification'] ?? ''));
            if ($classification === 'none' || !isset($assessmentClassificationDefinitions[$classification])) {
                $classification = '';
            }
            $classificationOptions = ['' => __('Not Classified')];
            foreach ($assessmentClassificationDefinitions as $key => $definition) {
                if ($key === 'none') {
                    continue;
                }
                $classificationOptions[$key] = (string) $definition['label'];
            }
            $row = $table->addRow();
            $row->addClass('acEventTypeRow');
            $row->addContent($type);
            $row->addColor($colorField)
                ->setPalette('background')
                ->setOuterClass('acColorSetting')
                ->addClass('acColorSettingInput')
                ->setValue($defaultColor);
            $row->addSelect($classificationField)->fromArray($classificationOptions)->selected($classification)->addClass('w-48 acEventTypeClassification');
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
    <script src="<?= $session->get('absoluteURL'); ?>/modules/<?= rawurlencode($session->get('module')); ?>/js/module.js"></script>
    <script>
    window.AcademicCalendarModule.initSettingsManage({
        classificationStyles: <?= json_encode($assessmentClassificationStyles, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?> || {},
        deleteConfirm: '<?= htmlspecialchars(__('Are you sure you want to delete this record?').' '.__('This operation cannot be undone.'), ENT_QUOTES, 'UTF-8'); ?>',
        previewTargets: [
            { selectName: 'assessmentDisplayBasis', previewId: 'acAssessmentDisplayBasisPreview' }
        ]
    });
    </script>
    <?php
}
