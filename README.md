# Academic Calendar (Gibbon Module)

Academic Calendar adds a homework-focused calendar view to Gibbon for Students, Parents, and Staff.

It is built for Gibbon `v31.0.00+` and uses FullCalendar to display Planner homework due dates in month/week/list views.

## Features

- Homework calendar view for:
  - Staff
  - Students
  - Parents
- Dashboard hook tabs for:
  - Staff Dashboard
  - Student Dashboard
  - Parental Dashboard
- Data source from Planner homework due dates
- Role-aware filtering:
  - Students: only their own classes
  - Parents: selected child
  - Staff: all visible homework, with optional year-group filtering
- Staff permission to filter by all year groups (`Homework Calendar_allYearGroups`)
- Year-group enablement setting to scope the module rollout (for example, only G9-G12)
- Event type color customization with Gibbon color picker + hex input
- Mobile-friendly toolbar behavior
- Event click-through to Planner entry

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
- The module relies on Planner homework data (`gibbonPlannerEntry`, `gibbonMarkbookColumn`).

## Version

Current module version: `0.1.0`

## License

This module follows Gibbon's GPLv3 ecosystem conventions.
