<?php

use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;

require_once __DIR__.'/moduleFunctions.php';

if (!isActionAccessible($guid, $connection2, '/modules/Academic Calendar/backup_restore.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    $page->breadcrumbs->add(__('Backup and Restore Settings'));

    $exportForm = Form::create(
        'settings_export',
        $session->get('absoluteURL').'/modules/'.$session->get('module').'/backup_restoreProcess.php?action=export'
    );
    $exportForm->setFactory(DatabaseFormFactory::create($pdo));
    $exportForm->addHiddenValue('address', $session->get('address'));

    $row = $exportForm->addRow();
    $row->addHeading(__('Export Settings'));

    $row = $exportForm->addRow();
    $row->addContent(__('Download a JSON backup of all Academic Calendar rows stored in gibbonSetting.'));

    $row = $exportForm->addRow();
    $row->addContent(__('This backup includes module settings only. It does not include permissions, hooks, or other core Gibbon data.'));

    $row = $exportForm->addRow();
    $row->addFooter();
    $row->addSubmit(__('Download Backup'));

    echo $exportForm->getOutput();

    $importForm = Form::create(
        'settings_import',
        $session->get('absoluteURL').'/modules/'.$session->get('module').'/backup_restoreProcess.php?action=import'
    );
    $importForm->setFactory(DatabaseFormFactory::create($pdo));
    $importForm->addHiddenValue('address', $session->get('address'));

    $row = $importForm->addRow();
    $row->addHeading(__('Restore Settings'));

    $row = $importForm->addRow();
    $row->addContent(__('Upload a previously exported JSON backup to restore Academic Calendar settings after reinstalling the module or when debugging another instance.'));

    $row = $importForm->addRow();
    $row->addContent(__('If a setting already exists, restore will update it. If it is missing, restore will recreate it.'));

    $row = $importForm->addRow();
    $row->addLabel('settingsBackupFile', __('Backup File'))
        ->description(__('Accepted format: JSON exported by this module.'));
    $row->addFileUpload('settingsBackupFile')->required()->accepts(['.json']);

    $row = $importForm->addRow();
    $row->addFooter();
    $row->addSubmit(__('Restore Backup'));

    echo $importForm->getOutput();
}
