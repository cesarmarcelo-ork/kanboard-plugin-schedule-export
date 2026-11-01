# Kanboard Schedule Export

Read-only Kanboard plugin that exports project schedules as **Microsoft Project XML (MSPDI)**. The XML can be opened in compatible scheduling applications; interpretation depends on importer handling of calendars, durations and milestones.

**Current version:** `0.2.0-rc1` (release candidate).

**Validated:** ProjectLibre 1.9.8 on Linux; Open Planner Studio v2026.43.0 MSPDI import, hierarchy, task descriptions/subtask estimates, and save/reopen persistence. Complete CPM/workload equivalence has not been established.

## Usage

1. Install as `plugins/ScheduleExport` in Kanboard. Do **not** run the old `ProjectLibreExport` plugin alongside it.
2. Open a project you are authorized to view and choose **Exportar cronograma (.xml)**.
3. Open the downloaded `kanboard-PROJECT_ID-cronograma.xml` in an MSPDI-compatible application.

The plugin is read-only: it does not change Kanboard tasks or require a personal API token.

## Data conventions

- Original dates, times and estimates come from Kanboard.
- The export preserves task relationships as an EAP/WBS and uses a 24/7 calendar, including weekend deadlines.
- Only-due tasks become milestones; undated cards are explicitly identified. Native subtasks without dates are kept in descriptions, not fabricated as scheduled work.
- Work estimates are separate from Gantt `Duration`. The latter contains a ProjectLibre 1.9.8 compatibility adjustment that other importers may interpret differently.
- CPM/critical-path results should not be treated as validated without explicit precedence relationships.

Additional project documentation and offline tests will be added from the validated source package. Do **not** commit user project snapshots, IFC data, credentials, or server backups.

Maintained by OrkFlowTech · MIT License.