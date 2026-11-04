from pathlib import Path
p=Path(__file__).resolve().parents[1]
plugin=(p/'Plugin.php').read_text()
controller=(p/'Controller/SubtaskPlanController.php').read_text()
view=(p/'Template/task/edit-plan.php').read_text()
links=(p/'Template/subtask/period-link.php').read_text()
exporter=(p/'Exporter/ProjectXmlExporter.php').read_text()
assert "return '0.6.1-dev'" in plugin
assert "'template:subtask:table:header:before-timetracking'" in plugin
assert "'template:subtask:table:rows'" in plugin
assert "'SubtaskPlanController', 'save', Role::PROJECT_MEMBER" in plugin
assert "'SubtaskPlanController', 'remove', Role::PROJECT_MEMBER" in plugin
assert 'checkCSRFForm()' in controller
creation = (p/'Controller/PlannedSubtaskCreationController.php').read_text()
assert 'checkCSRFForm()' in creation
assert 'subtaskValidator->validateCreation' in creation
assert 'PlannedSubtaskCreationService' in creation
assert 'getAssignableUsersList' in creation
assert "template:subtask:form:create" in plugin
assert (p/'Template/subtask/create-entry.php').is_file()
assert (p/'Template/subtask/create-planned.php').is_file()
assert "getTask()" in controller and "getById($subtaskId)" in controller
assert "task['id']" in controller
assert "layout->task(" in controller
assert "form->csrf()" in view
assert "workdays[]" in view and "planned_start" in view and "planned_end" in view
assert "subtask_id" in links and "task_id" in links
assert "Milestone'=>0" in exporter
assert "array $persistedPlans" in exporter
for db in ['Sqlite','Mysql','Postgres']:
    schema=(p/f'Schema/{db}.php').read_text()
    assert 'schedule_export_subtask_plans' in schema
print('PASS: v0.4 planning controller UI, ACL, CSRF, DB schema and exporter wiring')
