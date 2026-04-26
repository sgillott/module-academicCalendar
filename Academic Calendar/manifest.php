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

// Basic module information
// This module was based on work from module "HW Calendar" by JC Rozo
$name        = 'Academic Calendar';
$description = 'Homework calendar for students, parents and staff.';
$entryURL    = 'calendar_view.php';
$type        = 'Additional';
$category    = 'Learn';
$version     = '1.0.00';
$author      = 'Steve Gillott';
$url         = '';

// No database tables yet
$moduleTables = [];

// Module settings
$gibbonSetting = [];
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'eventTypeColors', 'Event Type Colours', 'JSON map of event type to hex colour for Homework Calendar.', '{}');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'showWeekends', 'Show Weekends on Calendar', 'Show weekends in the Homework Calendar.', 'Y');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'showHomeworkEvents', 'Show Homework Events on Calendar', 'Show homework events in the Homework Calendar.', 'Y');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'showAssessmentEvents', 'Show Assessment Events on Calendar', 'Show markbook assessment events in the Homework Calendar.', 'N');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'eventTypeMeta', 'Event Type Meta', 'JSON map of event type visibility and classification metadata.', '{}');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'defaultAssessmentFilter', 'Default Assessment Filter', 'Default user filter for assessment classifications.', '{\"formative\":\"Y\",\"summative\":\"Y\",\"none\":\"Y\"}');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'defaultStaffView', 'Default Staff View', 'Default filter for staff in Homework Calendar.', 'all');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'gibbonYearGroupIDList', 'Year Groups', 'Academic Calendar is enabled for these year groups.', '');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'summativeWeeklyThresholdDefault', 'Default Overview Weekly Threshold', 'Fallback threshold used when a year group does not have its own setting.', '3');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'summativeWeeklyThresholdByYearGroup', 'Overview Threshold by Year Group', 'JSON map of gibbonYearGroupID to weekly threshold.', '{}');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'overviewWeekNumberMode', 'Overview Week Number Mode', 'Choose whether the overview shows calendar weeks or academic weeks.', 'academic');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'assessmentDisplayBasis', 'Calendar Event Display Basis', 'Choose which course field is used when naming homework and assessment events in the calendar.', 'classCode');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'mergeSameDayAssessments', 'Merge Same-Day Assessments', 'Merge assessment rows that share the same display value on the same day in the calendar and overview.', 'N');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'assessmentClassificationColors', 'Assessment Classification Metadata', 'JSON map of assessment classification labels, colours, and overview display metadata.', '{\"formative\":{\"label\":\"Formative\",\"color\":\"#F97316\",\"displayInOverview\":\"N\"},\"summative\":{\"label\":\"Summative\",\"color\":\"#1D4ED8\",\"displayInOverview\":\"Y\"},\"none\":{\"label\":\"Not Classified\",\"color\":\"#9CA3AF\",\"displayInOverview\":\"N\"}}');";
$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`gibbonSettingID`, `scope`, `name`, `nameDisplay`, `description`, `value`) VALUES (NULL, 'Academic Calendar', 'useAssessmentClassificationColorInCalendar', 'Override and Use Assessment Classification Colour in Calendar', 'When enabled, assessment events on the Homework/Assessment Calendar use assessment classification colours.', 'N');";

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
    'name'                      => 'Assessment Overview',
    'precedence'                => '0',
    'category'                  => 'Calendar',
    'description'               => 'View weekly assessment overview by year group.',
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
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'Y',
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
    'name'                      => 'Backup and Restore Settings',
    'precedence'                => '0',
    'category'                  => 'Settings',
    'description'               => 'Export and restore Academic Calendar gibbonSetting values.',
    'URLList'                   => 'backup_restore.php,backup_restoreProcess.php',
    'entryURL'                  => 'backup_restore.php',
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
$array['sourceModuleAction'] = 'Homework/Assessment Calendar';
$array['sourceModuleInclude'] = 'hook_staffDashboard_homeworkView.php';
$hooks[] = "INSERT INTO `gibbonHook` (`gibbonHookID`, `name`, `type`, `options`, gibbonModuleID) VALUES (NULL, 'Homework', 'Staff Dashboard', '".serialize($array)."', (SELECT gibbonModuleID FROM gibbonModule WHERE name='$name'));";

$array = [];
$array['sourceModuleName'] = 'Academic Calendar';
$array['sourceModuleAction'] = 'Homework/Assessment Calendar';
$array['sourceModuleInclude'] = 'hook_studentDashboard_homeworkView.php';
$hooks[] = "INSERT INTO `gibbonHook` (`gibbonHookID`, `name`, `type`, `options`, gibbonModuleID) VALUES (NULL, 'Homework', 'Student Dashboard', '".serialize($array)."', (SELECT gibbonModuleID FROM gibbonModule WHERE name='$name'));";

$array = [];
$array['sourceModuleName'] = 'Academic Calendar';
$array['sourceModuleAction'] = 'Homework/Assessment Calendar';
$array['sourceModuleInclude'] = 'hook_parentalDashboard_homeworkView.php';
$hooks[] = "INSERT INTO `gibbonHook` (`gibbonHookID`, `name`, `type`, `options`, gibbonModuleID) VALUES (NULL, 'Homework', 'Parental Dashboard', '".serialize($array)."', (SELECT gibbonModuleID FROM gibbonModule WHERE name='$name'));";
