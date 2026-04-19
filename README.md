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
  - Parents: selected child
  - Staff: visible events with optional year-group filtering
- Staff permission to filter by all year groups (`Homework Calendar_allYearGroups`)
- Year-group rollout control (`gibbonYearGroupIDList`)
- Event color customization with Gibbon color picker + hex input
- Assessment events rendered as all-day entries
- Event click-through to Planner/Markbook pages based on role and access
- Mobile-friendly toolbar behavior

## Module Structure

Main files:

- `manifest.php` - module metadata, actions, settings, hooks
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
- `eventTypeColors` (JSON map of event type -> hex color)
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
  - Per-user tab visibility cannot be fully suppressed from module hook include code alone.
  - For non-enabled year groups, the hook can show a warning message instead of calendar content.
- Assessment entries use date-only values from Markbook and are rendered as all-day events.
- To avoid duplicates, assessment rows linked to Planner entries are excluded from the assessment feed.

## Version

Current module version: `0.2.0`

## License

This module follows Gibbon's GPLv3 ecosystem conventions.
