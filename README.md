# Academic Calendar (Gibbon Module)

Academic Calendar adds a homework and assessment calendar to Gibbon for Students, Parents, and Staff, combining Planner homework deadlines and Markbook assessment dates.

It is built for Gibbon `v31.0.00+`, uses FullCalendar, and is based on a module called "HomeworkCalendar" by JC Rozo.

## Features

- Calendar view for:
  - Staff
  - Students
  - Parents
- Dashboard hook tabs for:
  - Staff Dashboard
  - Student Dashboard
  - Parental Dashboard
- Event sources:
  - Planner homework due dates
  - Markbook assessment dates (`gibbonMarkbookColumn`)
- Role-aware filtering:
  - Students: own classes
  - Parents: selected student / child context
  - Staff: optional year-group filtering, with permission-based access to all year groups
- Year-group rollout control (`gibbonYearGroupIDList`)
- Event type configuration in settings:
  - Colour (Gibbon colour picker + hex)
  - Visible (`Y|N`) for assessment event types
  - Assessment classification (`None|Formative|Summative`)
- Assessment filter controls in calendar view:
  - Formative
  - Summative
  - Not Classified
- Assessment display controls:
  - Homework format in staff calendar
  - Assessment format in staff calendar
  - Merge same-day assessments
  - Learning Area support for grouped assessment labels
- Assessment colour controls:
  - Formative colour
  - Summative colour
  - Not classified colour
  - Optional use of assessment classification colours in the calendar
- Filter state persistence when changing dropdowns/checkboxes:
  - keeps current month/view (`month|week|list`) rather than jumping to today
- Role-aware click-through to Planner/Markbook pages based on access
- Mobile-friendly toolbar behaviour
- Backup and restore of module settings via JSON export/import

## Summative Overview

- Weekly overview grouped by term
- Year-group columns with configurable summative thresholds
- Calendar week or academic week numbering
- Student and Parent views restricted to their own available year groups
- School-event column from the school year calendar
- Full-week school-closure highlighting
- Tooltip details on hover for grouped assessment lines
- Subject/course grouping for weekly overview display

## Module Structure

Main files:

- `manifest.php` - module metadata, actions, settings, hooks
- `CHANGEDB.php` - incremental database setting changes for released versions
- `calendar_view.php` - primary homework/assessment calendar page
- `calendar_eventsJSON.php` - JSON event endpoint for FullCalendar
- `assessment_overview.php` - weekly summative assessment overview
- `backup_restore.php` / `backup_restoreProcess.php` - settings export/import
- `moduleFunctions.php` - shared helpers
- `settings_manage.php` / `settings_manageProcess.php` - module settings
- `hook_staffDashboard_homeworkView.php`
- `hook_studentDashboard_homeworkView.php`
- `hook_parentalDashboard_homeworkView.php`
- `src/Domain/AcademicCalendarEventGateway.php` - gateway queries

## Settings

`Academic Calendar` scope settings include:

- `showWeekends`
- `showHomeworkEvents`
- `showAssessmentEvents`
- `defaultStaffView`
- `staffEventFormat`
- `assessmentDisplayBasis`
- `mergeSameDayAssessments`
- `defaultAssessmentFilter`
- `useAssessmentClassificationColorInCalendar`
- `formativeColor`
- `summativeColor`
- `notClassifiedColor`
- `eventTypeColors`
- `eventTypeMeta`
- `gibbonYearGroupIDList`
- `overviewWeekNumberMode`
- `summativeWeeklyThresholdDefault`
- `summativeWeeklyThresholdByYearGroup`

## Permissions and Actions

Actions defined in `manifest.php` include:

- `Homework Calendar`
- `Summative Overview`
- `Manage Settings`
- `Backup and Restore Settings`
- `Homework Calendar_allYearGroups` (hidden action for permission-based staff scope)

## Installation

1. Place the module folder as:
   - `modules/Academic Calendar`
2. Install from Gibbon Module Admin.
3. Ensure action permissions are configured for target roles.
4. Configure settings under:
   - `Academic Calendar > Manage Settings`

## Notes

- Dashboard hook tabs are created by Gibbon core when the hook is accessible.
- Assessment entries use date-only values from Markbook and are rendered as all-day events.
- Homework visibility is not suppressed by assessment-type visibility settings.
- Linked Planner/Markbook assessments are included and can be compacted using the assessment display settings.
- Settings backup/export covers module `gibbonSetting` rows only, not permissions, hooks, or other core data.

## Version

Current working version: `0.7.00`

## License

This module follows Gibbon's GPLv3 ecosystem conventions.
