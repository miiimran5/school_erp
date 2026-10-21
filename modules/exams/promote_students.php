<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
access_require_roles(array('Admin'));

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}

$conn->set_charset('utf8mb4');
if (empty($_SESSION['student_promotion_csrf'])) {
    $_SESSION['student_promotion_csrf'] = bin2hex(random_bytes(32));
}

try {
    $conn->query("CREATE TABLE IF NOT EXISTS `student_promotions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `from_class_id` int(11) NOT NULL,
        `from_section_id` int(11) NOT NULL,
        `to_class_id` int(11) NOT NULL,
        `to_section_id` int(11) NOT NULL,
        `promoted_by` int(11) DEFAULT NULL,
        `promoted_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_student_promotion_student` (`student_id`,`promoted_at`),
        KEY `idx_student_promotion_class` (`from_class_id`,`to_class_id`,`promoted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (mysqli_sql_exception $exception) {
    http_response_code(500);
    exit('Promotion history setup failed. Contact the database administrator.');
}

function promotion_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$classes = array();
$result = $conn->query('SELECT id, class_name, class_order FROM classes WHERE status=1 ORDER BY class_order, id');
if (!$result) {
    die('Unable to load classes: ' . promotion_e($conn->error));
}
while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}

$sections = array();
$result = $conn->query('SELECT id, section_name FROM sections WHERE status=1 ORDER BY section_name');
if (!$result) {
    die('Unable to load sections: ' . promotion_e($conn->error));
}
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}

$class_by_id = array();
$class_by_order = array();
foreach ($classes as $class) {
    $class_by_id[(int)$class['id']] = $class;
    $class_by_order[(int)$class['class_order']] = $class;
}

$source_class_id = (int)($_POST['source_class_id'] ?? $_GET['source_class_id'] ?? 0);
$source_section_id = (int)($_POST['source_section_id'] ?? $_GET['source_section_id'] ?? 0);
$target_section_id = (int)($_POST['target_section_id'] ?? 0);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'promote') {
    $token = (string)($_POST['csrf_token'] ?? '');
    $student_ids = array_values(array_unique(array_filter(
        array_map('intval', (array)($_POST['student_ids'] ?? array())),
        function ($id) { return $id > 0; }
    )));

    if (!hash_equals((string)$_SESSION['student_promotion_csrf'], $token)) {
        $error = 'This form expired. Refresh the page and try again.';
    } elseif (!isset($class_by_id[$source_class_id]) || !isset($class_by_order[(int)$class_by_id[$source_class_id]['class_order'] + 1])) {
        $error = 'This class has no next class available for promotion.';
    } else {
        $active_section_ids = array_map('intval', array_column($sections, 'id'));
        if (!in_array($target_section_id, $active_section_ids, true)) {
            $error = 'Select a valid destination section.';
        }
    }

    if ($error === '' && $source_section_id <= 0) {
        $error = 'Select the current section.';
    }
    if ($error === '' && empty($student_ids)) {
        $error = 'Select at least one student to promote.';
    }

    $target_class_id = isset($class_by_id[$source_class_id])
        ? (int)($class_by_order[(int)$class_by_id[$source_class_id]['class_order'] + 1]['id'] ?? 0)
        : 0;
    if ($error === '' && (int)$class_by_id[$target_class_id]['class_order'] !== (int)$class_by_id[$source_class_id]['class_order'] + 1) {
        $error = 'The next class order is not configured. Ask an administrator to correct Classes settings.';
    }

    if ($error === '') {
        try {
            if (!$conn->begin_transaction()) {
                throw new RuntimeException('Unable to start the promotion transaction: ' . $conn->error);
            }
            $source_stmt = $conn->prepare('SELECT id FROM students WHERE id=? AND class_id=? AND section_id=? AND status=1 FOR UPDATE');
            $history_stmt = $conn->prepare('INSERT INTO student_promotions (student_id, from_class_id, from_section_id, to_class_id, to_section_id, promoted_by) VALUES (?, ?, ?, ?, ?, ?)');
            $update_stmt = $conn->prepare('UPDATE students SET class_id=?, section_id=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND class_id=? AND section_id=? AND status=1');
            if (!$source_stmt || !$history_stmt || !$update_stmt) {
                throw new RuntimeException('Unable to prepare promotion queries: ' . $conn->error);
            }

            $valid_students = array();
            foreach ($student_ids as $student_id) {
                $source_stmt->bind_param('iii', $student_id, $source_class_id, $source_section_id);
                if (!$source_stmt->execute()) {
                    throw new RuntimeException('Unable to verify selected students: ' . $source_stmt->error);
                }
                if ($source_stmt->get_result()->num_rows !== 1) {
                    throw new RuntimeException('A selected student is no longer active in the chosen class and section. Nothing was changed; refresh and review the list.');
                }
                $valid_students[] = $student_id;
            }

            $promoted_by = (int)($_SESSION['user_id'] ?? 0);
            foreach ($valid_students as $student_id) {
                $history_stmt->bind_param('iiiiii', $student_id, $source_class_id, $source_section_id, $target_class_id, $target_section_id, $promoted_by);
                if (!$history_stmt->execute()) {
                    throw new RuntimeException('Unable to record promotion history: ' . $history_stmt->error);
                }
                $update_stmt->bind_param('iiiii', $target_class_id, $target_section_id, $student_id, $source_class_id, $source_section_id);
                if (!$update_stmt->execute() || $update_stmt->affected_rows !== 1) {
                    throw new RuntimeException('A student record could not be updated. No promotions were saved.');
                }
            }
            $source_stmt->close();
            $history_stmt->close();
            $update_stmt->close();
            if (!$conn->commit()) {
                throw new RuntimeException('Unable to commit the promotion transaction: ' . $conn->error);
            }
            $message = count($valid_students) . ' student(s) promoted to ' . $class_by_id[$target_class_id]['class_name'] . '.';
        } catch (Throwable $exception) {
            $conn->rollback();
            if (isset($source_stmt) && $source_stmt instanceof mysqli_stmt) $source_stmt->close();
            if (isset($history_stmt) && $history_stmt instanceof mysqli_stmt) $history_stmt->close();
            if (isset($update_stmt) && $update_stmt instanceof mysqli_stmt) $update_stmt->close();
            $error = 'Promotion failed: ' . $exception->getMessage();
        }
    }
}

$source_students = array();
$target_class = null;
if (isset($class_by_id[$source_class_id])) {
    $target_class = $class_by_order[(int)$class_by_id[$source_class_id]['class_order'] + 1] ?? null;
}
if ($source_class_id > 0 && $source_section_id > 0 && $target_class) {
    $stmt = $conn->prepare('SELECT id, admission_no, roll_no, student_name FROM students WHERE class_id=? AND section_id=? AND status=1 ORDER BY CASE WHEN roll_no IS NULL OR roll_no="" THEN 999999 ELSE CAST(roll_no AS UNSIGNED) END, student_name');
    if (!$stmt) {
        die('Unable to prepare student list: ' . promotion_e($conn->error));
    }
    $stmt->bind_param('ii', $source_class_id, $source_section_id);
    if (!$stmt->execute()) {
        die('Unable to load students: ' . promotion_e($stmt->error));
    }
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $source_students[] = $row;
    }
    $stmt->close();
}

$source_section_name = '';
foreach ($sections as $section) {
    if ((int)$section['id'] === $source_section_id) {
        $source_section_name = $section['section_name'];
    }
}
$promotion_history = array();
$history_result = $conn->query("SELECT p.promoted_at, s.admission_no, s.student_name, fc.class_name AS from_class, fs.section_name AS from_section, tc.class_name AS to_class, ts.section_name AS to_section, u.full_name AS promoted_by_name FROM student_promotions p LEFT JOIN students s ON s.id=p.student_id LEFT JOIN classes fc ON fc.id=p.from_class_id LEFT JOIN sections fs ON fs.id=p.from_section_id LEFT JOIN classes tc ON tc.id=p.to_class_id LEFT JOIN sections ts ON ts.id=p.to_section_id LEFT JOIN users u ON u.id=p.promoted_by ORDER BY p.id DESC LIMIT 50");
if (!$history_result) {
    die('Unable to load promotion history: ' . promotion_e($conn->error));
}
while ($row = $history_result->fetch_assoc()) {
    $promotion_history[] = $row;
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-user-graduate"></i> Promote Students</h1></div></section>
    <section class="content"><div class="container-fluid">
        <?php if ($message !== ''): ?><div class="alert alert-success"><?= promotion_e($message); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?= promotion_e($error); ?></div><?php endif; ?>
        <div class="alert alert-info">After reviewing results, choose the students who passed/are approved for promotion. Their admission records will move to the next class and chosen section; past exam marks stay available. Every transfer is recorded in promotion history.</div>
        <div class="card">
            <div class="card-header bg-primary text-white">Choose the current class and section</div>
            <div class="card-body">
                <form method="get" class="form-row align-items-end">
                    <div class="form-group col-md-4"><label for="source_class_id">Current class</label><select class="form-control" name="source_class_id" id="source_class_id" required><option value="">Select class</option><?php foreach ($classes as $class): ?><option value="<?= (int)$class['id']; ?>" <?= $source_class_id === (int)$class['id'] ? 'selected' : ''; ?>><?= promotion_e($class['class_name']); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group col-md-4"><label for="source_section_id">Current section</label><select class="form-control" name="source_section_id" id="source_section_id" required><option value="">Select section</option><?php foreach ($sections as $section): ?><option value="<?= (int)$section['id']; ?>" <?= $source_section_id === (int)$section['id'] ? 'selected' : ''; ?>><?= promotion_e($section['section_name']); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group col-md-4"><button class="btn btn-primary">Load students</button></div>
                </form>
            </div>
        </div>
        <?php if ($source_class_id > 0 && $source_section_id > 0): ?>
            <?php if (!$target_class): ?>
                <div class="alert alert-warning">This is the last configured class; there is no next class to promote into.</div>
            <?php else: ?>
                <form method="post" id="promotion-form">
                    <input type="hidden" name="action" value="promote">
                    <input type="hidden" name="csrf_token" value="<?= promotion_e($_SESSION['student_promotion_csrf']); ?>">
                    <input type="hidden" name="source_class_id" value="<?= $source_class_id; ?>">
                    <input type="hidden" name="source_section_id" value="<?= $source_section_id; ?>">
                    <div class="card">
                        <div class="card-header bg-success text-white">Promote from <?= promotion_e($class_by_id[$source_class_id]['class_name']); ?> - <?= promotion_e($source_section_name); ?> to <?= promotion_e($target_class['class_name']); ?></div>
                        <div class="card-body">
                            <div class="form-group col-md-5 pl-0"><label for="target_section_id">Destination section</label><select class="form-control" name="target_section_id" id="target_section_id" required><option value="">Select destination section</option><?php foreach ($sections as $section): ?><option value="<?= (int)$section['id']; ?>" <?= $target_section_id === (int)$section['id'] ? 'selected' : ''; ?>><?= promotion_e($section['section_name']); ?></option><?php endforeach; ?></select></div>
                            <div class="d-flex justify-content-between align-items-center mb-2"><span><?= count($source_students); ?> active student(s)</span><label class="mb-0"><input type="checkbox" id="select-all"> Select all</label></div>
                            <div class="table-responsive"><table class="table table-bordered table-striped"><thead><tr><th>Select</th><th>Roll No.</th><th>Admission No.</th><th>Student Name</th></tr></thead><tbody>
                            <?php foreach ($source_students as $student): ?><tr><td><input type="checkbox" class="student-check" name="student_ids[]" value="<?= (int)$student['id']; ?>"></td><td><?= promotion_e($student['roll_no']); ?></td><td><?= promotion_e($student['admission_no']); ?></td><td><?= promotion_e($student['student_name']); ?></td></tr><?php endforeach; ?>
                            <?php if (!$source_students): ?><tr><td colspan="4" class="text-center text-muted">No active students in this class and section.</td></tr><?php endif; ?>
                            </tbody></table></div>
                        </div>
                        <?php if ($source_students): ?><div class="card-footer"><button type="submit" class="btn btn-success" <?= $target_section_id <= 0 ? 'disabled' : ''; ?>><i class="fas fa-arrow-up"></i> Promote selected students</button></div><?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
        <div class="card">
            <div class="card-header bg-secondary text-white">Recent promotion history</div>
            <div class="card-body p-0"><div class="table-responsive"><table class="table table-bordered table-striped mb-0"><thead><tr><th>Date</th><th>Admission No.</th><th>Student</th><th>From</th><th>To</th><th>Processed by</th></tr></thead><tbody>
            <?php foreach ($promotion_history as $record): ?><tr><td><?= promotion_e($record['promoted_at']); ?></td><td><?= promotion_e($record['admission_no'] ?? 'Student record removed'); ?></td><td><?= promotion_e($record['student_name'] ?? 'Student record removed'); ?></td><td><?= promotion_e(trim(($record['from_class'] ?? '') . ' - ' . ($record['from_section'] ?? ''))); ?></td><td><?= promotion_e(trim(($record['to_class'] ?? '') . ' - ' . ($record['to_section'] ?? ''))); ?></td><td><?= promotion_e($record['promoted_by_name'] ?? ''); ?></td></tr><?php endforeach; ?>
            <?php if (!$promotion_history): ?><tr><td colspan="6" class="text-center text-muted">No promotions recorded yet.</td></tr><?php endif; ?>
            </tbody></table></div></div>
        </div>
    </div></section>
</div>
<script>
var selectAll = document.getElementById('select-all');
if (selectAll) {
    selectAll.addEventListener('change', function () {
        document.querySelectorAll('.student-check').forEach(function (checkbox) {
            checkbox.checked = selectAll.checked;
        });
    });
}
var promotionForm = document.getElementById('promotion-form');
if (promotionForm) {
    promotionForm.addEventListener('submit', function (event) {
        if (!confirm('Promote only the selected students to the chosen next class and section?')) event.preventDefault();
    });
}
var targetSection = document.getElementById('target_section_id');
if (targetSection) {
    targetSection.addEventListener('change', function () {
        this.form.querySelector('button[type="submit"]').disabled = this.value === '';
    });
}
</script>
<?php include '../../includes/footer.php'; ?>
