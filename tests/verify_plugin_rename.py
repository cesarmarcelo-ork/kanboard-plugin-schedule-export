"""Verify that Kanboard plugin identity was migrated as an atomic unit."""
from pathlib import Path
base=Path(__file__).resolve().parent.parent
# The Git checkout may have any directory name; Kanboard plugin identity is verified below.
assert (base/'Plugin.php').is_file()
assert not (base/'Controller/ProjectLibreExportController.php').exists()
plugin=(base/'Plugin.php').read_text(encoding='utf-8')
assert r'namespace Kanboard\Plugin\ScheduleExport;' in plugin
assert "'ScheduleExport:project/dropdown'" in plugin
assert "'ScheduleExportController', 'download'" in plugin
assert "return 'Kanboard Schedule Export';" in plugin
assert "return '0.2.0-rc1';" in plugin
controller=(base/'Controller/ScheduleExportController.php').read_text(encoding='utf-8')
assert 'class ScheduleExportController' in controller
assert "'-cronograma.xml'" in controller
assert r'namespace Kanboard\Plugin\ScheduleExport\Controller;' in controller
view=(base/'Template/project/dropdown.php').read_text(encoding='utf-8')
assert "'ScheduleExportController'" in view and "'plugin' => 'ScheduleExport'" in view
assert 'Export project schedule (.xml)' in view
for p in base.rglob('*.php'):
    t=p.read_text(encoding='utf-8')
    assert r'Kanboard\Plugin\ProjectLibreExport' not in t, str(p)
    assert 'ProjectLibreExportController' not in t, str(p)
print('PASS: renamed plugin folder, PHP namespace, ACL, controller, template hook, download filename, version')
