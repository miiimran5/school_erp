<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
access_require_roles(array('Admin'));
$conn->set_charset('utf8mb4');

function report_subject_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';
$classes = array();
$result = $conn->query('SELECT id, class_name FROM classes WHERE status=1 ORDER BY class_order, id');
while ($result && ($row = $result->fetch_assoc())) {
    $classes[] = $row;
}

$class_id = (int)($_POST['class_id'] ?? $_GET['class_id'] ?? ($classes[0]['id'] ?? 0));
$valid_class_ids = array_map('intval', array_column($classes, 'id'));
if (!in_array($class_id, $valid_class_ids, true)) {
    $class_id = (int)($classes[0]['id'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $class_id > 0) {
    $selected = array_values(array_unique(array_filter(
        array_map('intval', (array)($_POST['subject_ids'] ?? array())),
        function ($id) { return $id > 0; }
    )));
    $valid_subject_ids = array();
    if ($selected) {
        $stmt = $conn->prepare('SELECT s.id FROM subjects s INNER JOIN class_subjects cs ON cs.subject_id=s.id AND cs.class_id=? AND cs.status=1 WHERE s.status=1 AND s.id=?');
        foreach ($selected as $subject_id) {
            $stmt->bind_param('ii', $class_id, $subject_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 1) {
                $valid_subject_ids[] = $subject_id;
            }
        }
        $stmt->close();
    }

    if (count($selected) !== count($valid_subject_ids)) {
        $error = 'One or more selected subjects are invalid or inactive. Refresh and try again.';
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('INSERT INTO report_card_class_settings (class_id) VALUES (?) ON DUPLICATE KEY UPDATE updated_at=CURRENT_TIMESTAMP');
            $stmt->bind_param('i', $class_id);
            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error);
            }
            $stmt->close();

            $stmt = $conn->prepare('DELETE FROM report_card_class_subjects WHERE class_id=?');
            $stmt->bind_param('i', $class_id);
            if (!$stmt->execute()) {
                throw new RuntimeException($stmt->error);
            }
            $stmt->close();

            if ($valid_subject_ids) {
                $stmt = $conn->prepare('INSERT INTO report_card_class_subjects (class_id, subject_id, display_order) VALUES (?, ?, ?)');
                foreach ($valid_subject_ids as $order => $subject_id) {
                    $stmt->bind_param('iii', $class_id, $subject_id, $order);
                    if (!$stmt->execute()) {
                        throw new RuntimeException($stmt->error);
                    }
                }
                $stmt->close();
            }
            $conn->commit();
            $message = 'Report-card subjects saved for the selected class. You can save an empty selection to show no subjects.';
        } catch (Throwable $exception) {
            $conn->rollback();
            $error = 'Could not save report-card subjects: ' . $exception->getMessage();
        }
    }
}

$subjects = array();
$stmt = $conn->prepare('SELECT s.id, s.subject_name, s.subject_type, cs.role FROM subjects s INNER JOIN class_subjects cs ON cs.subject_id=s.id AND cs.class_id=? AND cs.status=1 WHERE s.status=1 ORDER BY cs.sort_order, s.subject_name');
$stmt->bind_param('i', $class_id);
$stmt->execute();
$result = $stmt->get_result();
while ($result && ($row = $result->fetch_assoc())) {
    $subjects[] = $row;
}
$stmt->close();

$is_configured = false;
$selected = array();
$stmt = $conn->prepare('SELECT 1 FROM report_card_class_settings WHERE class_id=?');
$stmt->bind_param('i', $class_id);
$stmt->execute();
$is_configured = $stmt->get_result()->num_rows > 0;
$stmt->close();
if ($is_configured) {
    $stmt = $conn->prepare('SELECT subject_id FROM report_card_class_subjects WHERE class_id=? ORDER BY display_order, id');
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $selected[] = (int)$row['subject_id'];
    }
    $stmt->close();
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-layer-group"></i> Report Card Subjects</h1></div></section>
    <section class="content"><div class="container-fluid">
        <?php if ($message !== ''): ?><div class="alert alert-success"><?= report_subject_e($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?= report_subject_e($error); ?></div><?php endif; ?>
        <div class="alert alert-info">Choose subjects separately for each class. This allows GK in Class I without adding GK to Classes II–XII. Only subjects already assigned to the class are listed. If a class has no custom report-card selection yet, existing class subjects remain the default.</div>
        <form method="get" class="form-inline mb-3">
            <label for="class_id" class="mr-2">Select class</label>
            <select id="class_id" name="class_id" class="form-control mr-2" onchange="this.form.submit()">
                <?php foreach ($classes as $class): ?><option value="<?= (int)$class['id']; ?>" <?= $class_id === (int)$class['id'] ? 'selected' : ''; ?>><?= report_subject_e($class['class_name']); ?></option><?php endforeach; ?>
            </select>
            <noscript><button class="btn btn-primary">View</button></noscript>
        </form>
        <form method="post">
            <input type="hidden" name="class_id" value="<?= $class_id; ?>">
            <div class="card">
                <div class="card-header bg-primary text-white">Class <?= report_subject_e($classes[array_search($class_id, $valid_class_ids, true)]['class_name'] ?? 'Selected'); ?> Report Card Subjects</div>
                <div class="card-body">
                    <p class="text-muted"><?= $is_configured ? 'This class has its own saved subject selection.' : 'No custom selection saved yet; existing class subjects are currently used.'; ?></p>
                    <?php if ($subjects): ?><div class="row">
                        <?php foreach ($subjects as $subject): ?>
                        <div class="col-md-4 mb-2"><label class="d-block border rounded p-2">
                            <input type="checkbox" name="subject_ids[]" value="<?= (int)$subject['id']; ?>" <?= (!$is_configured || in_array((int)$subject['id'], $selected, true)) ? 'checked' : ''; ?>>
                            <?= report_subject_e($subject['subject_name']); ?>
                            <small class="text-muted">(<?= report_subject_e($subject['role']); ?>)</small>
                        </label></div>
                        <?php endforeach; ?>
                    </div><?php else: ?><div class="alert alert-warning">No subjects are assigned to this class yet. Assign the class subjects first in Examinations &gt; Subjects.</div><?php endif; ?>
                </div>
                <div class="card-footer"><button class="btn btn-success" type="submit"><i class="fas fa-save mr-1"></i>Save This Class</button></div>
            </div>
        </form>
    </div></section>
</div>
<?php include '../../includes/footer.php'; ?>
