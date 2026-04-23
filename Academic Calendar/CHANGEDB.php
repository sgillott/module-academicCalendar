<?php
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = [];
$count = 0;

// v0.1.0
$sql[$count][0] = "0.1.0";
$sql[$count][1] = "";

// v0.2.0
$count++;
$sql[$count][0] = "0.2.0";
$sql[$count][1] = "
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
SELECT 'Academic Calendar', 'showHomeworkEvents', 'Show Homework Events', 'Show homework events in the Homework Calendar.', 'Y'
WHERE NOT EXISTS (
    SELECT 1 FROM gibbonSetting WHERE scope='Academic Calendar' AND name='showHomeworkEvents'
);end
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
SELECT 'Academic Calendar', 'showAssessmentEvents', 'Show Assessment Events', 'Show markbook assessment events in the Homework Calendar.', 'Y'
WHERE NOT EXISTS (
    SELECT 1 FROM gibbonSetting WHERE scope='Academic Calendar' AND name='showAssessmentEvents'
);end
";

// v0.3.00
$count++;
$sql[$count][0] = "0.3.00";
$sql[$count][1] = "
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
SELECT 'Academic Calendar', 'eventTypeMeta', 'Event Type Meta', 'JSON map of event type visibility and classification metadata.', '{}'
WHERE NOT EXISTS (
    SELECT 1 FROM gibbonSetting WHERE scope='Academic Calendar' AND name='eventTypeMeta'
);end
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
SELECT 'Academic Calendar', 'defaultAssessmentFilter', 'Default Assessment Filter', 'Default user filter for formative and summative assessment events.', '{\"formative\":\"Y\",\"summative\":\"Y\"}'
WHERE NOT EXISTS (
    SELECT 1 FROM gibbonSetting WHERE scope='Academic Calendar' AND name='defaultAssessmentFilter'
);end
";

// v0.4.00
$count++;
$sql[$count][0] = "0.4.00";
$sql[$count][1] = "
UPDATE gibbonSetting
SET nameDisplay='Show Weekends on Calendar'
WHERE scope='Academic Calendar' AND name='showWeekends';end
UPDATE gibbonSetting
SET nameDisplay='Show Homework Events on Calendar'
WHERE scope='Academic Calendar' AND name='showHomeworkEvents';end
UPDATE gibbonSetting
SET nameDisplay='Show Assessment Events on Calendar'
WHERE scope='Academic Calendar' AND name='showAssessmentEvents';end
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
SELECT 'Academic Calendar', 'summativeWeeklyThresholdDefault', 'Default Summative Weekly Threshold', 'Fallback threshold used when a year group does not have its own setting.', '3'
WHERE NOT EXISTS (
    SELECT 1 FROM gibbonSetting WHERE scope='Academic Calendar' AND name='summativeWeeklyThresholdDefault'
);end
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
SELECT 'Academic Calendar', 'summativeWeeklyThresholdByYearGroup', 'Summative Weekly Threshold by Year Group', 'JSON map of gibbonYearGroupID to weekly threshold.', '{}'
WHERE NOT EXISTS (
    SELECT 1 FROM gibbonSetting WHERE scope='Academic Calendar' AND name='summativeWeeklyThresholdByYearGroup'
);end
";

// v0.5.00
$count++;
$sql[$count][0] = "0.5.00";

$staffHookOptions = serialize([
    'sourceModuleName' => 'Academic Calendar',
    'sourceModuleAction' => 'Homework/Assessment Calendar',
    'sourceModuleInclude' => 'hook_staffDashboard_homeworkView.php',
]);

$studentHookOptions = serialize([
    'sourceModuleName' => 'Academic Calendar',
    'sourceModuleAction' => 'Homework/Assessment Calendar',
    'sourceModuleInclude' => 'hook_studentDashboard_homeworkView.php',
]);

$parentHookOptions = serialize([
    'sourceModuleName' => 'Academic Calendar',
    'sourceModuleAction' => 'Homework/Assessment Calendar',
    'sourceModuleInclude' => 'hook_parentalDashboard_homeworkView.php',
]);

$staffHookOptions = addslashes($staffHookOptions);
$studentHookOptions = addslashes($studentHookOptions);
$parentHookOptions = addslashes($parentHookOptions);

$sql[$count][1] = "
UPDATE gibbonAction
SET name='Homework/Assessment Calendar'
WHERE gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Academic Calendar')
AND name='Homework Calendar';end
UPDATE gibbonHook
SET
    name='Homework',
    options='{$staffHookOptions}'
WHERE gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Academic Calendar')
AND type='Staff Dashboard'
AND options LIKE '%hook_staffDashboard_homeworkView.php%';end
UPDATE gibbonHook
SET
    name='Homework',
    options='{$studentHookOptions}'
WHERE gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Academic Calendar')
AND type='Student Dashboard'
AND options LIKE '%hook_studentDashboard_homeworkView.php%';end
UPDATE gibbonHook
SET
    name='Homework',
    options='{$parentHookOptions}'
WHERE gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Academic Calendar')
AND type='Parental Dashboard'
AND options LIKE '%hook_parentalDashboard_homeworkView.php%';end
";

// v0.5.01
$count++;
$sql[$count][0] = "0.5.01";
$sql[$count][1] = "
UPDATE gibbonHook
SET name='Homework'
WHERE gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Academic Calendar')
AND type='Staff Dashboard'
AND options LIKE '%hook_staffDashboard_homeworkView.php%';end
UPDATE gibbonHook
SET name='Homework'
WHERE gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Academic Calendar')
AND type='Student Dashboard'
AND options LIKE '%hook_studentDashboard_homeworkView.php%';end
UPDATE gibbonHook
SET name='Homework'
WHERE gibbonModuleID=(SELECT gibbonModuleID FROM gibbonModule WHERE name='Academic Calendar')
AND type='Parental Dashboard'
AND options LIKE '%hook_parentalDashboard_homeworkView.php%';end
";

// v0.5.02
$count++;
$sql[$count][0] = "0.5.02";
$sql[$count][1] = "
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
SELECT 'Academic Calendar', 'staffEventFormat', 'Homework Format in Staff Calendar', 'Choose how homework titles appear for staff in the calendar. This controls how course, class code, year group, and title are combined.', 'codeTitle'
WHERE NOT EXISTS (
    SELECT 1 FROM gibbonSetting WHERE scope='Academic Calendar' AND name='staffEventFormat'
);end
";

// v0.5.03
$count++;
$sql[$count][0] = "0.5.03";
$sql[$count][1] = "
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
SELECT 'Academic Calendar', 'overviewWeekNumberMode', 'Overview Week Number Mode', 'Choose whether the summative overview shows calendar weeks or academic weeks.', 'academic'
WHERE NOT EXISTS (
    SELECT 1 FROM gibbonSetting WHERE scope='Academic Calendar' AND name='overviewWeekNumberMode'
);end
";

// v0.5.04
$count++;
$sql[$count][0] = "0.5.04";
$sql[$count][1] = "
UPDATE gibbonSetting
SET value='academic'
WHERE scope='Academic Calendar' AND name='overviewWeekNumberMode' AND (value='' OR value IS NULL OR value='calendar');end
";

// v0.6.00
$count++;
$sql[$count][0] = "0.6.00";
$sql[$count][1] = "
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
SELECT 'Academic Calendar', 'assessmentDisplayBasis', 'Assessment Format in Staff Calendar', 'Choose which course field is used when naming assessment events for staff. This also controls how same-day assessment merges are labelled.', 'courseShortName'
WHERE NOT EXISTS (
    SELECT 1 FROM gibbonSetting WHERE scope='Academic Calendar' AND name='assessmentDisplayBasis'
);end
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
SELECT 'Academic Calendar', 'mergeSameDayAssessments', 'Merge Same-Day Assessments', 'Merge assessment rows that share the same display value on the same day.', 'N'
WHERE NOT EXISTS (
    SELECT 1 FROM gibbonSetting WHERE scope='Academic Calendar' AND name='mergeSameDayAssessments'
);end
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
SELECT 'Academic Calendar', 'assessmentClassificationColors', 'Assessment Classification Colours', 'JSON map of formative, summative, and not classified colours.', '{\"formative\":\"#F97316\",\"summative\":\"#1D4ED8\",\"none\":\"#9CA3AF\"}'
WHERE NOT EXISTS (
    SELECT 1 FROM gibbonSetting WHERE scope='Academic Calendar' AND name='assessmentClassificationColors'
);end
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
SELECT 'Academic Calendar', 'useAssessmentClassificationColorInCalendar', 'Use Assessment Classification Colour in Calendar', 'When enabled, assessment events on the Homework/Assessment Calendar use the formative, summative, or not classified colours.', 'N'
WHERE NOT EXISTS (
    SELECT 1 FROM gibbonSetting WHERE scope='Academic Calendar' AND name='useAssessmentClassificationColorInCalendar'
);end
DELETE FROM gibbonSetting
WHERE scope='Academic Calendar' AND name='assessmentColorPriority';end
";
