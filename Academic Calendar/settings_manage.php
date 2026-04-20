<?php

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;

require_once __DIR__.'/moduleFunctions.php';

/**
 * Manage Settings page.
 *
 * Builds the module settings form, including:
 * - display settings
 * - enabled year groups
 * - per-event-type color mapping.
 */
if (!isActionAccessible($guid, $connection2, '/modules/Academic Calendar/settings_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Manage Settings'));

    $settingGateway = $container->get(SettingGateway::class);

    $colors = ac_getColorMap($settingGateway);
    $typeMeta = ac_getEventTypeMeta($settingGateway);
    $showWeekends = (string) $settingGateway->getSettingByScope('Academic Calendar', 'showWeekends');
    $showHomeworkEvents = (string) $settingGateway->getSettingByScope('Academic Calendar', 'showHomeworkEvents');
    $showAssessmentEvents = (string) $settingGateway->getSettingByScope('Academic Calendar', 'showAssessmentEvents');
    $defaultAssessmentFilter = ac_getDefaultAssessmentFilter($settingGateway);
    $defaultStaffView = (string) $settingGateway->getSettingByScope('Academic Calendar', 'defaultStaffView');
    $enabledYearGroupIDList = (string) ($settingGateway->getSettingByScope('Academic Calendar', 'gibbonYearGroupIDList') ?: '');

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
    $row->addHeading(__('Display'));

    $row = $form->addRow();
    $row->addLabel('showWeekends', __('Show Weekends'));
    $row->addYesNo('showWeekends')->selected($showWeekends === 'N' ? 'N' : 'Y');

    $row = $form->addRow();
    $row->addLabel('showHomeworkEvents', __('Show Homework Events'));
    $row->addYesNo('showHomeworkEvents')->selected($showHomeworkEvents === 'N' ? 'N' : 'Y');

    $row = $form->addRow();
    $row->addLabel('showAssessmentEvents', __('Show Assessment Events'));
    $row->addYesNo('showAssessmentEvents')->selected($showAssessmentEvents === 'N' ? 'N' : 'Y');

    $row = $form->addRow();
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
    $row->addLabel('defaultStaffView', __('Default Staff View'));
    $row->addSelect('defaultStaffView')->fromArray([
        'all' => __('All Homework'),
        'yearGroup' => __('Filter by Year Group'),
    ])->selected($defaultStaffView === 'yearGroup' ? 'yearGroup' : 'all');

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
