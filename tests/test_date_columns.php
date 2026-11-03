<?php
// Render the real templates with the same helper contracts as Kanboard.
function t($label) { return $label; }
function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
class DateColumnView
{
    public $user;
    public $date;
    public $text;
    public $url;
    public function __construct($view, $edit)
    {
        $this->user = new class($view, $edit) {
            private $view; private $edit;
            public function __construct($view, $edit) { $this->view=$view; $this->edit=$edit; }
            public function hasProjectAccess($controller, $action, $projectId) {
                return $action === 'save' ? $this->edit : $this->view;
            }
        };
        $this->date = new class {
            public function date($value) { return date('d/m/Y', strtotime($value)); }
        };
        $this->text = new class {
            public function e($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
        };
        $this->url = new class {
            public function link($label, $controller, $action, $params) {
                return '<a href="?'.htmlspecialchars(http_build_query(array_merge(
                    array('controller'=>$controller, 'action'=>$action), $params
                )), ENT_QUOTES, 'UTF-8').'">'.$label.'</a>';
            }
        };
    }
    public function render($plan, $projectId = 1)
    {
        $subtask = array('id'=>30, 'task_id'=>9);
        $schedule_export_project_id = $projectId;
        $schedule_export_plan = $plan;
        ob_start();
        include __DIR__.'/../Template/subtask/period-link.php';
        return ob_get_clean();
    }
}
$plan = array('planned_start'=>'2026-11-03T00:30:00', 'planned_end'=>'2026-11-06T17:00:00');
foreach (array('UTC', 'America/Sao_Paulo', 'Asia/Tokyo') as $timezone) {
    date_default_timezone_set($timezone);
    $html = (new DateColumnView(true, true))->render($plan);
    check(substr_count($html, '<td ') === 2, 'Exactly two date cells');
    check(strpos($html, '03/11/2026') !== false && strpos($html, '06/11/2026') !== false, 'Stored dates remain on their calendar day');
    check(substr_count($html, '<a ') === 2, 'Both dates open planning');
    check(strpos($html, 'subtask_id=30') !== false && strpos($html, 'task_id=9') !== false, 'Links retain subtask and card identity');
}
$member = new DateColumnView(true, true);
$empty = $member->render(null);
check(substr_count($empty, '>Set date</a>') === 2, 'Unplanned rows provide two planning links');
check(strpos($empty, '1970') === false, 'Missing dates never become epoch dates');
$viewer = new DateColumnView(true, false);
$html = $viewer->render($plan);
check(strpos($html, '03/11/2026') !== false && strpos($html, '<a ') === false, 'Viewer sees dates without edit actions');
check(substr_count($viewer->render(null), 'Not scheduled') === 2, 'Viewer sees unplanned state');
foreach (array((new DateColumnView(false, false))->render($plan), $member->render($plan, 0)) as $html) {
    check(substr_count($html, '<td ') === 2, 'Restricted rows keep table alignment');
    check(strpos($html, '2026') === false && strpos($html, '<a ') === false, 'Restricted rows reveal no dates or links');
}
ob_start();
include __DIR__.'/../Template/subtask/period-header.php';
$header = ob_get_clean();
check(substr_count($header, '<th ') === 2 && strpos($header, 'Estimated start date') !== false && strpos($header, 'Estimated end date') !== false, 'Two matching headers');
echo "PASS: date columns, saved/unscheduled rows, links, access and timezone behavior\n";
