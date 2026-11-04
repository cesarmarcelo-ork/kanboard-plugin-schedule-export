# Kanboard Schedule Export

[![Kanboard plugin](https://img.shields.io/badge/Kanboard-plugin-informational)](https://kanboard.org/)

Kanboard plugin for persistent native-subtask scheduling and export of project schedules as **Microsoft Project XML (MSPDI)**. The export can be opened in compatible scheduling applications; interoperability depends on how each importer handles dates, calendars, durations, and milestones.

**Current release:** `0.6.1`. **Minimum Kanboard:** `1.2.50`. Validated with Kanboard 1.2.50 and 1.2.57, PHP 8.1–8.4, ProjectLibre 1.9.8 on Linux and Open Planner Studio v2026.43.0. Full CPM/workload equivalence across applications is outside the plugin's scope; Kanboard remains authoritative for source dates and native time tracking.

## Kanboard compatibility

Version 0.6.1 declares **Kanboard >= 1.2.50**. The automated matrix exercises Kanboard 1.2.50 and 1.2.57, while functional acceptance was completed in the isolated 1.2.50 notebook environment and in the homelab. See the [v0.6.1 release notes](docs/RELEASE-V061.md).

## v0.6.1: planned and actual subtask dates

- Plugin-specific labels now use English source strings and Brazilian Portuguese translations; they follow Kanboard's selected language without changing saved dates.
- The native **Add a sub-task** form now offers **Create one sub-task with planning**, linking to a dedicated single-subtask form. Enter a title, assignee and estimated time; optionally tick **Plan this sub-task now** and enter planned start/end dates, daily minutes and working days.
- The native bulk-subtask form is deliberately untouched; dates cannot be silently associated with the wrong subtask when multiple titles are entered.
- Both the native subtask and its optional plan are persisted together by subtask ID. Failed dates or plan writes do not leave an orphan creation. The existing **Dates** editor, native conversion and MSPDI export continue to work.
- The task view presents estimated start, estimated finish and actual completion separately; native time tracking remains unchanged.
- Actual completion follows the native completed/reopened state and can be corrected under project permissions with history retained.
- MSPDI export preserves planned `Finish` and records actual completion separately in `ActualFinish`.

## Use

1. Install this repository as `plugins/ScheduleExport` in your Kanboard instance. **Do not install it alongside** the older `ProjectLibreExport` plugin.
2. Open an authorized Kanboard project and select **Exportar cronograma (.xml)** from its project menu.
3. Open the downloaded `kanboard-PROJECT_ID-cronograma.xml` in an MSPDI-compatible application.

No personal API token or browser extension is required. The plugin stores subtask planning in its own database table. Version 0.4.1 also copies scheduled subtask dates to a new Kanboard task during native subtask-to-task conversion; external services are not used.

## Data representation

- The Kanboard database is authoritative for the original dates and hours, estimated work, task relationships, and native subtasks.
- Original datetimes are preserved in XML task fields when provided and in the task description; a special 24/7 calendar permits weekend deadlines.
- Tasks with only a due date become milestones; fully undated tasks are explicitly labeled as unscheduled. These placements are not invented work schedules.
- Card parent/child links form the work breakdown structure (WBS/EAP). Native checklist subtasks without dates are retained in parent task descriptions, *not* fabricated Gantt bars.
- Work estimates are separate from the Gantt `Duration` field. Calendar intervals use MSPDI elapsed-day metadata; applications may display the same interval in days or hours.
- Importer-calculated critical path is **not** authoritative without genuine task precedence links.

## Quality and installation

See [README-PTBR.md](README-PTBR.md) for Brazilian Portuguese details, [MIGRACAO.md](MIGRACAO.md) for version migration, and [HOMOLOGACAO-OPS.md](HOMOLOGACAO-OPS.md) for OPS verification.

The test suite lives in `tests/`; GitHub Actions runs PHP lint, XML parsing, synthetic scenario tests, and plugin-identity checks. Private user project exports, credentials, server backups, and IFC files are **not part of this repository**.

License: MIT. Maintained by OrkFlowTech.


## Novidades da v0.4.0

A exportação XML detalhada é o padrão em uma única entrada no menu. Subtarefas nativas podem ter períodos cadastrados no Kanboard pela coluna **Planejamento / Datas**, sem modificar o rastreamento de tempo. O modo anterior está disponível pelo parâmetro `subtasks=legacy`. Veja [notas da versão](docs/RELEASE-V040.md) para migração, limitações visuais dos importadores e homologação.


## v0.4.1 conversion fix (stable release)

When Kanboard converts a native subtask into a task, its Schedule Export dates are transferred to the new task's native start/due fields before the original subtask is removed. Additional workday parameters are recorded in task metadata; native time-tracking values are left unchanged. This is a release validated in the homelab. See [v0.4.1 test plan](docs/TESTE-CONVERSAO-V041.md).
