# Academic Calendar (Gibbon Module)

Academic Calendar adds a calendar view to Gibbon for Students, Parents, and Staff, combining Planner homework deadlines and Markbook assessment dates.

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
  - Parents: selected student
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
- Filter state persistence when changing dropdowns/checkboxes:
  - keeps current month/view (`month|week|list`) rather than jumping to today
- Role-aware click-through to Planner/Markbook pages based on access
- Mobile-friendly toolbar behavior

## Module Structure

Main files:

- `manifest.php` - module metadata, actions, settings, hooks
- `CHANGEDB.php` - incremental database setting changes for released versions
- `calendar_view.php` - primary calendar page
- `calendar_eventsJSON.php` - JSON event endpoint for FullCalendar
- `moduleFunctions.php` - shared helpers
- `settings_manage.php` / `settings_manageProcess.php` - module settings
- `hook_staffDashboard_homeworkView.php`
- `hook_studentDashboard_homeworkView.php`
- `hook_parentalDashboard_homeworkView.php`
- `src/Domain/AcademicCalendarEventGateway.php` - gateway queries

## Settings

`Academic Calendar` scope settings:

- `showWeekends` (`Y|N`)
- `showHomeworkEvents` (`Y|N`)
- `showAssessmentEvents` (`Y|N`)
- `defaultStaffView` (`all|yearGroup`)
- `defaultAssessmentFilter` (JSON: `formative|summative|none` => `Y|N`)
- `eventTypeColors` (JSON map: event type -> hex color)
- `eventTypeMeta` (JSON map: event type -> `{visible, classification}`)
- `gibbonYearGroupIDList` (CSV list of enabled year groups)

## Permissions and Actions

Actions defined in `manifest.php`:

- `Homework Calendar`
- `Manage Settings`
- `Homework Calendar_allYearGroups` (hidden action for permission-based staff scope)

## Installation

1. Place the module folder as:
   - `modules/Academic Calendar`
2. Install from Gibbon Module Admin.
3. Ensure action permissions are configured for target roles.
4. Configure settings under:
   - `Academic Calendar > Manage Settings`

## Notes and Known Behavior

- Dashboard hook tabs are created by Gibbon core when the hook is accessible.
- Assessment entries use date-only values from Markbook and are rendered as all-day events.
- Homework visibility is not suppressed by assessment-type visibility settings.
- To avoid duplicates, assessment rows linked to Planner entries are excluded from the assessment feed.

## Version

Current module version: `0.3.00`

## License

This module follows Gibbon's GPLv3 ecosystem conventions.
