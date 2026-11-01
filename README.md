# Kanboard Schedule Export

[![Kanboard plugin](https://img.shields.io/badge/Kanboard-plugin-informational)](https://kanboard.org/)

Read-only Kanboard plugin that exports project schedules as **Microsoft Project XML (MSPDI)**. The export can be opened in compatible scheduling applications; interoperability depends on how each importer handles dates, calendars, durations, and milestones.

**Current version:** `0.2.0-rc1` (release candidate). **Validated:** ProjectLibre 1.9.8 on Linux; Open Planner Studio v2026.43.0 MSPDI import, hierarchy, descriptions/subtask estimates, and save/reopen. Full CPM/workload equivalence across applications has not been established.

## Use

1. Install this repository as `plugins/ScheduleExport` in your Kanboard instance. **Do not install it alongside** the older `ProjectLibreExport` plugin.
2. Open an authorized Kanboard project and select **Exportar cronograma (.xml)** from its project menu.
3. Open the downloaded `kanboard-PROJECT_ID-cronograma.xml` in an MSPDI-compatible application.

No personal API token or browser extension is required. The plugin reads Kanboard project data but does not update tasks, use external services, or alter the database.

## Data representation

- The Kanboard database is authoritative for the original dates and hours, estimated work, task relationships, and native subtasks.
- Original datetimes are preserved in XML task fields when provided and in the task description; a special 24/7 calendar permits weekend deadlines.
- Tasks with only a due date become milestones; fully undated tasks are explicitly labeled as unscheduled. These placements are not invented work schedules.
- Card parent/child links form the work breakdown structure (WBS/EAP). Native checklist subtasks without dates are retained in parent task descriptions, *not* fabricated Gantt bars.
- Work estimates are separate from the Gantt `Duration` field. The latter includes a ProjectLibre 1.9.8 compatibility adjustment that other importers may interpret differently.
- Importer-calculated critical path is **not** authoritative without genuine task precedence links.

## Quality and installation

See [README-PTBR.md](README-PTBR.md) for Brazilian Portuguese details, [MIGRACAO.md](MIGRACAO.md) for version migration, and [HOMOLOGACAO-OPS.md](HOMOLOGACAO-OPS.md) for OPS verification.

The test suite lives in `tests/`; GitHub Actions runs PHP lint, XML parsing, synthetic scenario tests, and plugin-identity checks. Private user project exports, credentials, server backups, and IFC files are **not part of this repository**.

License: MIT. Maintained by OrkFlowTech.
