# Changelog

## 0.2.0-rc1 — Kanboard Schedule Export (31/10/2026)

- Plugin renamed from `ProjectLibreExport` to **`ScheduleExport`** including directory, namespaces, controller, Kanboard role map, view hook and user-facing label.
- The export endpoint now downloads `kanboard-ID-cronograma.xml`. Format remains **MSPDI**; no new importer or external dependency.
- Exporter logic retained from the Linux-homologated `0.1.4-rc2`; no change to Kanboard input dates/times, parent-child EAP, native subtasks, duration compensation or MSPDI calendar.
- Added migration guidance with backup/rollback, OPS test plan, and identity regression tests.
- Open Planner Studio v2026.43.0: imported XML, EAP, descriptions, native-subtask estimates, and save/reopen persistence verified on 01/11/2026. CPM and minute-level semantics remain additional checks.

## 0.1.4-rc2 — ProjectLibre 1.9.8 minute compatibility (31/10/2026)

- Subtracted 1 minute from MSPDI `Duration` for each complete 24h period. `Start`, `Finish` and `Deadline` unchanged.
- Passed offline source JSON checks and user ProjectLibre Linux import test.

## 0.1.4-rc1 — Source fidelity (31/10/2026)

- Exact source dates, EAP, native checklist in Notes, no synthetic task schedules for missing dates, warnings for contradictions and valid blocker-only dependencies.
