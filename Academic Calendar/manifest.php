<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

// Basic module information
// This module was based on module "HomeworkCalendar" by JC
$name        = 'Academic Calendar';
$description = 'Homework calendar for students, parents and staff.';
$entryURL    = 'calendar_view.php';
$type        = 'Additional';
$category    = 'Learn';
$version     = '0.4.00';
$author      = 'Steve Gillott';
$url         = '';

// No database tables yet
$moduleTables = [];

// Module settings
$gibbonSetting = [];
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'eventTypeColors', 'Event Type Colours', 'JSON map of event type to hex colour for Homework Calendar.', '{}');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'showWeekends', 'Show Weekends on Calendar', 'Show weekends in the Homework Calendar.', 'Y');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'showHomeworkEvents', 'Show Homework Events on Calendar', 'Show homework events in the Homework Calendar.', 'Y');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'showAssessmentEvents', 'Show Assessment Events on Calendar', 'Show markbook assessment events in the Homework Calendar.', 'Y');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'eventTypeMeta', 'Event Type Meta', 'JSON map of event type visibility and classification metadata.', '{}');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'defaultAssessmentFilter', 'Default Assessment Filter', 'Default user filter for formative and summative assessment events.', '{\"formative\":\"Y\",\"summative\":\"Y\",\"none\":\"Y\"}');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'defaultStaffView', 'Default Staff View', 'Default filter for staff in Homework Calendar.', 'all');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'gibbonYearGroupIDList', 'Year Groups', 'Academic Calendar is enabled for these year groups.', '');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'summativeWeeklyThresholdDefault', 'Default Summative Weekly Threshold', 'Fallback threshold used when a year group does not have its own setting.', '3');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'summativeWeeklyThresholdByYearGroup', 'Summative Weekly Threshold by Year Group', 'JSON map of gibbonYearGroupID to weekly threshold.', '{}');";

// Action definitions
$actionRows = [];

$actionRows[] = [
    'name'                      => 'Homework/Assessment Calendar',
    'precedence'                => '0',
    'category'                  => 'Calendar',
    'description'               => 'View homework deadlines in a calendar format.',
    'URLList'                   => 'index.php,calendar_view.php',
    'entryURL'                  => 'calendar_view.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'Y',
    'defaultPermissionParent'   => 'Y',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'Y',
];

$actionRows[] = [
    'name'                      => 'Summative Assessment Overview',
    'precedence'                => '0',
    'category'                  => 'Calendar',
    'description'               => 'View weekly summative assessment overview by year group.',
    'URLList'                   => 'assessment_overview.php',
    'entryURL'                  => 'assessment_overview.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Manage Settings',
    'precedence'                => '0',
    'category'                  => 'Settings',
    'description'               => 'Configure Homework Calendar settings.',
    'URLList'                   => 'settings_manage.php,settings_manageProcess.php',
    'entryURL'                  => 'settings_manage.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Homework Calendar_allYearGroups',
    'precedence'                => '0',
    'category'                  => '',
    'description'               => 'Allows staff to filter Homework Calendar by all year groups.',
    'URLList'                   => 'calendar_view.php',
    'entryURL'                  => 'calendar_view.php',
    'entrySidebar'              => 'N',
    'menuShow'                  => 'N',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

// HOOKS
$hooks = [];

$array = [];
$array['sourceModuleName'] = 'Academic Calendar';
$array['sourceModuleAction'] = 'Homework Calendar';
$array['sourceModuleInclude'] = 'hook_staffDashboard_homeworkView.php';
$hooks[] = "INSERT INTO `gibbonHook` (`gibbonHookID`, `name`, `type`, `options`, gibbonModuleID) VALUES (NULL, 'Homework', 'Staff Dashboard', '".serialize($array)."', (SELECT gibbonModuleID FROM gibbonModule WHERE name='$name'));";

$array = [];
$array['sourceModuleName'] = 'Academic Calendar';
$array['sourceModuleAction'] = 'Homework Calendar';
$array['sourceModuleInclude'] = 'hook_studentDashboard_homeworkView.php';
$hooks[] = "INSERT INTO `gibbonHook` (`gibbonHookID`, `name`, `type`, `options`, gibbonModuleID) VALUES (NULL, 'Homework', 'Student Dashboard', '".serialize($array)."', (SELECT gibbonModuleID FROM gibbonModule WHERE name='$name'));";

$array = [];
$array['sourceModuleName'] = 'Academic Calendar';
$array['sourceModuleAction'] = 'Homework Calendar';
$array['sourceModuleInclude'] = 'hook_parentalDashboard_homeworkView.php';
$hooks[] = "INSERT INTO `gibbonHook` (`gibbonHookID`, `name`, `type`, `options`, gibbonModuleID) VALUES (NULL, 'Homework', 'Parental Dashboard', '".serialize($array)."', (SELECT gibbonModuleID FROM gibbonModule WHERE name='$name'));";
