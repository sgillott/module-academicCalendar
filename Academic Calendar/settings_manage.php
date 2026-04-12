<?php

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\System\SettingGateway;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/Academic Calendar/settings_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Manage Settings'));

    $settingGateway = $container->get(SettingGateway::class);

    $colors = ac_getColorMap($settingGateway);
    $showWeekends = (string) $settingGateway->getSettingByScope('Academic Calendar', 'showWeekends');
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
    $row->addHeading(__('Event Type Colours'));

    foreach ($types as $type) {
        $type = (string) $type;
        $name = 'typeColor_'.md5($type);
        $defaultColor = $colors[$type] ?? ac_colorFromPalette($type);

        $row = $form->addRow();
        $row->addLabel($name, $type);
        $row->addColor($name)
            ->setPalette('background')
            ->setOuterClass('ml-auto acColorSetting')
            ->addClass('acColorSettingInput')
            ->setValue($defaultColor);
    }

    $row = $form->addRow();
    $row->addFooter();
    $row->addSubmit();

    echo $form->getOutput();
}
