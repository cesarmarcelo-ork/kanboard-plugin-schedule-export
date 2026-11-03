"""Ensure English source labels have Brazilian Portuguese translations."""
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
locale = (root / 'Locale/pt_BR/translations.php').read_text(encoding='utf-8')
keys = set(re.findall(r"^\s*'([^']+)'\s*=>", locale, re.M))
views = [
    'Template/task/edit-completion.php',
    'Template/subtask/completion-header.php',
    'Template/subtask/completion-link.php',
    'Template/task/edit-plan.php',
    'Template/task/planning.php',
    'Template/subtask/period-header.php',
    'Template/subtask/period-link.php',
    'Template/subtask/create-entry.php',
    'Template/subtask/create-planned.php',
    'Template/project/dropdown.php',
]
core_keys = {'Cancel'}
found = set()
for path in views:
    source = (root / path).read_text(encoding='utf-8')
    found.update(re.findall(r"t\('([^']+)'", source))
    assert 'Planejamento de subtarefa</h2>' not in source, path
    assert '>Salvar planejamento<' not in source, path
    assert "'Exportar cronograma (.xml)'" not in source, path

missing = found - keys - core_keys
assert not missing, f"Missing Portuguese translations: {sorted(missing)}"
assert "t($name)" in (root / 'Template/task/edit-plan.php').read_text(encoding='utf-8')
assert "t($name)" in (root / 'Template/subtask/create-planned.php').read_text(encoding='utf-8')
assert "'template:subtask:form:create'" in (root / 'Plugin.php').read_text(encoding='utf-8')
print('PASS: English labels, PT-BR translations and creation form hook')
