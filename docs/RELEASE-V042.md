# Kanboard Schedule Export v0.4.2 — Release notes (draft)

## Scope

Compatibility policy maintenance only. The plugin's XML exporter, subtask persistence model, conversion logic, and database schema are unchanged from v0.4.1.

- Plugin display name: **Kanboard Schedule Export**
- Package directory: `ScheduleExport/`
- Version: **0.4.2**
- Minimum declared Kanboard version: **>=1.2.50** (formerly >=1.2.30)
- License: MIT

## Validation status

- The project maintainer reports successful functional tests on **Kanboard 1.2.50**, including planned subtask persistence, native conversion into task cards, and MSPDI XML export.
- Independent laboratory verification previously documented for **Kanboard 1.2.57** remains applicable to its tested scope.
- Release approval is conditional on successful automated CI for v0.4.2 and a correctly structured release package; the test evidence for Kanboard 1.2.50 was not independently examined during this update.

## Release checklist

- [ ] Confirm GitHub Actions tests on the final v0.4.2 commit.
- [ ] Merge the release branch into `main`.
- [ ] Create GitHub Release tag `v0.4.2` from the merged release commit.
- [ ] Upload ZIP `ScheduleExport-0.4.2.zip` with a top-level `ScheduleExport/Plugin.php`.
- [ ] Check download availability and that metadata reports v0.4.2 and >=1.2.50.
- [ ] Submit separate update to `kanboard/website/plugins.json` with name, version, minimum compatibility and release download link.

**Do not modify the historical v0.4.1 release or overwrite its ZIP.**
