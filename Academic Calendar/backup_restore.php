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
