# Open Planner Studio (OPS) compatibility tests

**Environment already used in OrkFlowTech**: https://gantt.orkflowtech.com.br/ (OPS running in browser). Importing an MSPDI file is a **file operation**, not an automatic Kanboard/OPS synchronization.

## Test sequence

1. Export project 1 in Kanboard using **Exportar cronograma (.xml)**. Expected downloaded filename: `kanboard-1-cronograma.xml`.
2. In OPS, choose **File/Ficheiro → Open/Abrir**, select the downloaded XML. If an importer asks whether to recalculate, inspect stored dates first; use **Dates as recorded** mode when available.
3. In the task hierarchy, compare 36 source cards and 13 parent-child EAP connections with the Kanboard source snapshot. Expect 5 additional deadline milestones and (if importer retains root) 1 project root, i.e. 42 MSPDI Task records in the raw XML; OPS may display a different total because summary/milestone/root counting differs.
4. Check representative timestamps:
   - [PL-01] due 28/09/2026 **19:21**;
   - [DOC-01] due 27/09/2026 **19:21** (Sunday);
   - [INF-01-C] due 24/09/2026 **19:22**;
   - [ENC-01] due 30/11/2026 **19:22**.
5. Verify cards with only due dates appear as milestones, and cards with neither date show explicit **sem datas** warnings. Native checklist items (116 in source) must not become fake dated rows. Read parent Notes if OPS supports preserving them; record an import-loss warning if Notes are not imported.
6. Check calendar 24/7, whether Start/Finish move from their imported values, Duration handling, and whether project-specific summary due markers remain distinct from the rolled-up summary bars.
7. Before any save, note the imported results and any import-loss notices. Save as a **NEW** IFC file, e.g. `KANBOARD-PROJETO-01-SCHEDULE-TESTE.ifc`, without overwriting `KANBOARD-PROJETO-01-VINCULADO.ifc`.
8. Reopen that NEW IFC and check same values. Do not call `./kops consultar` or invoke the OPS Bridge publisher for this test; its v0.9 baseline is for a separate linked IFC flow.

## Evaluation criteria

- **PASS**: project opens; 36 cards, parent links, all source deadlines and known start stamps checked; any importer omissions documented.
- **PARTIAL**: file opens but saved dates, minutiae, parent due / Notes, or milestone treatment differ. Assess importer-specific changes before editing the plugin.
- **FAIL**: unsupported XML, parse error, fatal import loss or corrupt hierarchy.

The ProjectLibre minute compensation is a compatibility workaround, **not proof** that OPS will display exact minutes. Keep this test separate from ProjectLibre validation.

## Observed results (2026-11-01)

The user verified successful MSPDI import in OPS v2026.43.0, WBS structure, schedule display, task descriptions, and save/reopen persistence. From a **live Kanboard plugin export**, card `[TI-01-B]` retained 16 h estimated effort with native subtasks estimated at 4, 6, 4, and 2 hours, shown in the imported task description. These subtasks were intentionally not scheduled as separate dated activities. The UI does not directly expose imported minute-level internal date fields; CPM and exact minute fidelity in the OPS engine remain open checks.
