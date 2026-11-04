# Kanboard Schedule Export v0.6.1

## Highlights

- Separate **Estimated start date** and **Estimated end date** columns for native subtasks.
- Automatic and manually correctable **Actual completion** date synchronized with the native Kanboard completed/reopened state.
- Native time tracking remains under Kanboard control.
- MSPDI export keeps planned `Finish` separate from `ActualFinish` and exports completed subtasks with 100% progress and zero remaining duration.
- Calendar intervals use MSPDI elapsed-day metadata for consistent interpretation by ProjectLibre, Open Planner Studio and compatible readers.

## Compatibility

- Kanboard: **>= 1.2.50**
- PHP: **8.1–8.4** in the automated matrix
- Database migrations: SQLite, MySQL and PostgreSQL definitions included

## Validation

- Automated integration: Kanboard 1.2.50 and 1.2.57.
- Functional acceptance: isolated Kanboard 1.2.50 notebook environment and homelab.
- XML import checked in ProjectLibre 1.9.8 and Open Planner Studio 2026.43.0.

The installable asset must be named `ScheduleExport-0.6.1.zip` and contain the top-level directory `ScheduleExport/`.
