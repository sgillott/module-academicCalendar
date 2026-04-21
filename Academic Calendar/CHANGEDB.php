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
