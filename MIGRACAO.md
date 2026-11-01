# Migration — ProjectLibreExport 0.1.4-rc2 → ScheduleExport 0.2.0-rc1

This is **not** a parallel add-on. Kanboard discovers installed plugins by directory and PHP namespace.

1. Back up `/opt/orkflowtech/kanboard/plugins/ProjectLibreExport` intact to `/opt/orkflowtech/kanboard/plugin-backups/`.
2. Stage `ScheduleExport` from the ZIP as a sibling directory.
3. Confirm the old directory has been moved out of `plugins/`, then move `ScheduleExport` into it.
4. Verify `php -l` for Plugin/Controller/Exporter in the running Kanboard container and check the Kanboard Plugins page.
5. Test authorized project export, filename, MSPDI content and ProjectLibre regression before OPS testing.

Do not delete unrelated plugins. Do not modify Docker Compose or bridge v0.9. Do not upload the K-ExportImport snapshot or IFC content to a public GitHub repository.

**Rollback**: Move new `ScheduleExport` out of `plugins/`, restore backed-up `ProjectLibreExport` to `plugins/`, and refresh Kanboard. A PHP opcache/reload may be required for a long-lived process, depending on runtime configuration; only restart the service if the plugin is not detected after refresh.
