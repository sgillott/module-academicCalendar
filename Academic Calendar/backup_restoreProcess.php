<?php

require_once '../../gibbon.php';
require_once './moduleFunctions.php';

$URL = $session->get('absoluteURL').'/index.php?q=/modules/'.getModuleName($_POST['address'] ?? '').'/backup_restore.php';
$action = trim((string) ($_GET['action'] ?? ''));

if (!isActionAccessible($guid, $connection2, '/modules/Academic Calendar/backup_restore.php')) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

if ($action === 'export') {
    $backup = ac_buildSettingsBackup($connection2);
    $filename = 'academic-calendar-settings-'.date('Ymd-His').'.json';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action !== 'import') {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

if (empty($_FILES['settingsBackupFile']['tmp_name'])) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

$raw = file_get_contents($_FILES['settingsBackupFile']['tmp_name']);
if ($raw === false || trim($raw) === '') {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

$payload = json_decode($raw, true);
$settings = ac_validateSettingsBackup($payload);
if ($settings === null) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

$partialFail = false;
foreach ($settings as $setting) {
    $partialFail = !ac_upsertSettingRow($connection2, $setting) || $partialFail;
}

$URL .= $partialFail ? '&return=error2' : '&return=success0';
header("Location: {$URL}");
exit;
