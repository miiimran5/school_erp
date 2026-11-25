<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
require_once '../../includes/office.php';

access_require_roles(array('Admin', 'Teacher', 'Student', 'Parent'));
$conn->set_charset('utf8mb4');
office_ensure_schema($conn);

$year = (int)($_GET['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

$message = '';
$error = '';
if (empty($_SESSION['calendar_csrf'])) {
    $_SESSION['calendar_csrf'] = bin2hex(random_bytes(32));
}

$can_edit = access_is_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit) {
    if (!isset($_POST['csrf_token']) || !hash_equals((string)$_SESSION['calendar_csrf'], (string)$_POST['csrf_token'])) {
        $error = 'This request could not be verified.';
    } else {
        $action = (string)($_POST['action'] ?? 'add');
        if ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id || $id < 1) {
                $error = 'Choose a valid event to remove.';
            } else {
                $stmt = $conn->prepare('DELETE FROM calendar_events WHERE id=?');
                if (!$stmt) {
                    $error = 'Unable to prepare event deletion: ' . $conn->error;
                } else {
                    $stmt->bind_param('i', $id);
                    if ($stmt->execute() && $stmt->affected_rows === 1) {
                        $message = 'Event removed.';
                    } else {
                        $error = 'The event was not found or could not be removed.';
                    }
                    $stmt->close();
                }
            }
        } else {
            $event_date = (string)($_POST['event_date'] ?? '');
            $title = trim((string)($_POST['title'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));
            $date_parts = array_map('intval', explode('-', $event_date));
            if (count($date_parts) !== 3 || !checkdate($date_parts[1], $date_parts[2], $date_parts[0])
                || $title === '' || strlen($title) > 150 || strlen($notes) > 255) {
                $error = 'Choose a date and enter the event name.';
            } else {
                $created_by = (int)($_SESSION['user_id'] ?? 0);
                $stmt = $conn->prepare('INSERT INTO calendar_events (event_date, title, notes, created_by) VALUES (?,?,?,?)');
                if (!$stmt) {
                    $error = 'Unable to prepare the calendar event: ' . $conn->error;
                } else {
                    $stmt->bind_param('sssi', $event_date, $title, $notes, $created_by);
                    if ($stmt->execute()) {
                        $message = 'Event added.';
                        $year = (int)substr($event_date, 0, 4);
                    } else {
                        $error = $stmt->error ?: 'Unable to save the event.';
                    }
                    $stmt->close();
                }
            }
        }
    }
}

$events = array();
$stmt = $conn->prepare('SELECT id, event_date, title, notes FROM calendar_events WHERE YEAR(event_date)=? ORDER BY event_date, id');
$stmt->bind_param('i', $year);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $events[$row['event_date']][] = $row;
}
$stmt->close();

$holiday_result = $conn->query('SELECT holiday_name, holiday_date FROM portal_holidays WHERE status=1');
while ($holiday_result && ($row = $holiday_result->fetch_assoc())) {
    if ((int)substr($row['holiday_date'], 0, 4) === $year) {
        $events[$row['holiday_date']][] = array('id' => 0, 'event_date' => $row['holiday_date'], 'title' => $row['holiday_name'], 'notes' => 'Holiday');
    }
}

$months = array(1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
<section class="content-header"><div class="container-fluid d-flex justify-content-between align-items-center flex-wrap">
<h1 class="mb-0"><i class="far fa-calendar-alt"></i> School Calendar <?= (int)$year; ?></h1>
<div>
<a class="btn btn-secondary" href="?year=<?= $year - 1; ?>">&laquo; <?= $year - 1; ?></a>
<a class="btn btn-secondary" href="?year=<?= $year + 1; ?>"><?= $year + 1; ?> &raquo;</a>
</div>
</div></section>
<section class="content"><div class="container-fluid">
<?php if ($message): ?><div class="alert alert-success"><?= office_e($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= office_e($error); ?></div><?php endif; ?>
<?php if ($can_edit): ?>
<div class="card"><div class="card-header bg-primary text-white">Add Event / Festival</div><div class="card-body">
<form method="post" class="form-row align-items-end">
<input type="hidden" name="csrf_token" value="<?= office_e($_SESSION['calendar_csrf']); ?>">
<div class="form-group col-md-3"><label>Date</label><input type="date" name="event_date" class="form-control" required value="<?= office_e($year . date('-m-d')); ?>"></div>
<div class="form-group col-md-4"><label>Event name</label><input name="title" class="form-control" required placeholder="Diwali, Sports Day..."></div>
<div class="form-group col-md-3"><label>Note (optional)</label><input name="notes" class="form-control"></div>
<div class="form-group col-md-2"><button class="btn btn-success btn-block">Add Event</button></div>
</form>
<p class="text-muted mb-0">The year view is for looking up dates. Administrators can add named events, similar to a phone calendar.</p>
</div></div>
<?php endif; ?>
<div class="row">
<?php foreach ($months as $month_num => $month_name): ?>
<div class="col-lg-4 col-md-6 mb-3">
<div class="card h-100"><div class="card-header py-2"><?= office_e($month_name); ?></div>
<div class="card-body p-2">
<table class="table table-sm table-bordered mb-0 calendar-month">
<thead><tr><?php foreach (array('Su','Mo','Tu','We','Th','Fr','Sa') as $day): ?><th class="text-center"><?= $day; ?></th><?php endforeach; ?></tr></thead>
<tbody>
<?php
$first = strtotime(sprintf('%04d-%02d-01', $year, $month_num));
$start_w = (int)date('w', $first);
$days = (int)date('t', $first);
echo '<tr>';
for ($pad = 0; $pad < $start_w; $pad++) {
    echo '<td></td>';
}
$cell = $start_w;
for ($day = 1; $day <= $days; $day++) {
    $iso = sprintf('%04d-%02d-%02d', $year, $month_num, $day);
    $day_events = $events[$iso] ?? array();
    $titles = array();
    foreach ($day_events as $event) {
        $titles[] = $event['title'];
    }
    $class = $day_events ? ' table-warning' : '';
    echo '<td class="text-center' . $class . '" title="' . office_e(implode(', ', $titles)) . '"><strong>' . $day . '</strong>';
    foreach ($day_events as $event) {
        echo '<div class="small text-truncate">' . office_e($event['title']) . '</div>';
    }
    echo '</td>';
    $cell++;
    if ($cell % 7 === 0 && $day < $days) {
        echo '</tr><tr>';
    }
}
while ($cell % 7 !== 0) {
    echo '<td></td>';
    $cell++;
}
echo '</tr>';
?>
</tbody>
</table>
</div></div>
</div>
<?php endforeach; ?>
</div>
<?php if ($can_edit): ?>
<div class="card"><div class="card-header">Events this year</div><div class="card-body p-0"><table class="table mb-0"><thead><tr><th>Date</th><th>Event</th><th>Note</th><th></th></tr></thead><tbody>
<?php
$flat = array();
foreach ($events as $list) {
    foreach ($list as $event) {
        $flat[] = $event;
    }
}
usort($flat, function ($a, $b) { return strcmp($a['event_date'], $b['event_date']); });
foreach ($flat as $event):
?>
<tr>
<td><?= office_e(date('d M Y', strtotime($event['event_date']))); ?></td>
<td><?= office_e($event['title']); ?></td>
<td><?= office_e($event['notes']); ?></td>
<td>
<?php if ((int)$event['id'] > 0): ?>
<form method="post" onsubmit="return confirm('Delete this event?');">
<input type="hidden" name="csrf_token" value="<?= office_e($_SESSION['calendar_csrf']); ?>">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?= (int)$event['id']; ?>">
<button class="btn btn-sm btn-danger">Delete</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$flat): ?><tr><td colspan="4" class="text-muted text-center">No events added for <?= (int)$year; ?>.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php endif; ?>
</div></section></div>
<?php include '../../includes/footer.php'; ?>
