<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';

access_require_roles(array('Admin'));
$conn->set_charset('utf8mb4');
$grade_defaults = array(
    array('A1', 91.00, 100.00, 'Outstanding'),
    array('A2', 81.00, 90.99, 'Excellent'),
    array('B1', 71.00, 80.99, 'Very Good'),
    array('B2', 61.00, 70.99, 'Good'),
    array('C1', 51.00, 60.99, 'Satisfactory'),
    array('C2', 41.00, 50.99, 'Needs Improvement'),
    array('D', 33.00, 40.99, 'Basic'),
    array('E', 0.00, 32.99, 'Needs Support')
);
if (empty($_SESSION['grading_csrf'])) {
    $_SESSION['grading_csrf'] = bin2hex(random_bytes(32));
}
$classes = array();
$class_result = $conn->query('SELECT id,class_name FROM classes WHERE status=1 ORDER BY class_order,id');
while ($class_result && ($row = $class_result->fetch_assoc())) {
    $classes[] = $row;
}
$class_id = (int)($_REQUEST['class_id'] ?? ($classes[0]['id'] ?? 0));
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token'], $_SESSION['grading_csrf'])
        || !hash_equals((string)$_SESSION['grading_csrf'], (string)$_POST['csrf_token'])) {
        $error = 'This request could not be verified. Refresh the page and try again.';
    } else {
        $posted_grades = $_POST['grade'] ?? array();
        $mins = $_POST['minimum_percent'] ?? array();
        $maxes = $_POST['maximum_percent'] ?? array();
        $clean_bands = array();
        foreach ($grade_defaults as $default) {
            $grade = $default[0];
            $minimum = $mins[$grade] ?? '';
            $maximum = $maxes[$grade] ?? '';
            if (!isset($posted_grades[$grade]) || $posted_grades[$grade] !== $grade
                || !is_numeric($minimum) || !is_numeric($maximum)) {
                $error = 'Enter a minimum and maximum percentage for every grade.';
                break;
            }
            $minimum = (float)$minimum;
            $maximum = (float)$maximum;
            if ($minimum < 0 || $maximum > 100 || $minimum > $maximum) {
                $error = 'Grade percentage ranges must stay between 0 and 100, with minimum not above maximum.';
                break;
            }
            $clean_bands[] = array($grade, $minimum, $maximum, $default[3]);
        }
        usort($clean_bands, function ($a, $b) { return $a[1] <=> $b[1]; });
        if ($error === '' && $clean_bands) {
            if ((int)round($clean_bands[0][1] * 100) !== 0) {
                $error = 'The scale must start at 0%.';
            } else {
                $previous_max = -1;
                foreach ($clean_bands as $band) {
                    $minimum_cents = (int)round($band[1] * 100);
                    $maximum_cents = (int)round($band[2] * 100);
                    if ($minimum_cents !== $previous_max + 1) {
                        $error = 'Grade ranges must not overlap or leave gaps; use consecutive percentage steps.';
                        break;
                    }
                    $previous_max = $maximum_cents;
                }
                if ($error === '' && $previous_max !== 10000) {
                    $error = 'The scale must end at 100%.';
                }
            }
        }
        if ($error === '') {
            $conn->begin_transaction();
            $delete = $conn->prepare('DELETE FROM grading_scale_bands WHERE class_id=?');
            $insert = $conn->prepare('INSERT INTO grading_scale_bands (class_id,grade,minimum_percent,maximum_percent,description) VALUES (?,?,?,?,?)');
            if (!$delete || !$insert) {
                $error = 'Unable to prepare grading scale updates: ' . $conn->error;
                $conn->rollback();
            } else {
                $delete->bind_param('i', $class_id);
                $ok = $delete->execute();
                foreach ($clean_bands as $band) {
                    if (!$ok) {
                        break;
                    }
                    $insert->bind_param('isdds', $class_id, $band[0], $band[1], $band[2], $band[3]);
                    $ok = $insert->execute();
                }
                $delete->close();
                $insert->close();
                if ($ok && $conn->commit()) {
                    $message = 'The grading scale was saved for the selected class.';
                } else {
                    $conn->rollback();
                    $error = 'Unable to save the grading scale: ' . $conn->error;
                }
            }
        }
    }
}

$bands = array();
if ($class_id > 0) {
    $stmt = $conn->prepare('SELECT grade,minimum_percent,maximum_percent,description FROM grading_scale_bands WHERE class_id=? ORDER BY minimum_percent DESC');
    if (!$stmt) {
        error_log('Grading scale load failed: ' . $conn->error);
        http_response_code(500);
        exit('Unable to load the grading scale. Verify migration 019 is installed.');
    }
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $bands[$row['grade']] = $row;
    }
    $stmt->close();
}
foreach ($grade_defaults as $default) {
    if (!isset($bands[$default[0]])) {
        $bands[$default[0]] = array('grade' => $default[0], 'minimum_percent' => $default[1], 'maximum_percent' => $default[2], 'description' => $default[3]);
    }
}
?>
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
    <section class="content-header"><div class="container-fluid"><h1><i class="fas fa-chart-line"></i> Grading System</h1><p class="text-muted mb-0">The default CBSE-style scale can be adjusted for each class. Numeric result grades use this scale.</p></div></section>
    <section class="content"><div class="container-fluid">
        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <div class="card"><div class="card-body"><form method="get" class="form-inline"><label for="class_id" class="mr-2">Class</label><select id="class_id" name="class_id" class="form-control mr-2" onchange="this.form.submit()"><?php foreach ($classes as $class): ?><option value="<?= (int)$class['id']; ?>" <?= $class_id === (int)$class['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($class['class_name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></form></div></div>
        <div class="card"><div class="card-header bg-primary text-white">Grade ranges for <?= htmlspecialchars((string)($classes[array_search($class_id, array_column($classes, 'id'))]['class_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div><div class="card-body">
            <p>Each mark is converted to a percentage of that assessment's maximum. The corresponding band below determines the displayed grade.</p>
            <form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['grading_csrf'], ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="class_id" value="<?= $class_id; ?>">
                <table class="table table-bordered"><thead><tr><th>Grade</th><th>Minimum percentage</th><th>Maximum percentage</th><th>Meaning</th></tr></thead><tbody>
                <?php foreach ($grade_defaults as $default): $band = $bands[$default[0]]; ?>
                    <tr><td><strong><?= htmlspecialchars($default[0], ENT_QUOTES, 'UTF-8'); ?></strong><input type="hidden" name="grade[<?= $default[0]; ?>]" value="<?= $default[0]; ?>"></td><td><input type="number" min="0" max="100" step="0.01" name="minimum_percent[<?= $default[0]; ?>]" value="<?= number_format((float)$band['minimum_percent'], 2, '.', ''); ?>" class="form-control" required></td><td><input type="number" min="0" max="100" step="0.01" name="maximum_percent[<?= $default[0]; ?>]" value="<?= number_format((float)$band['maximum_percent'], 2, '.', ''); ?>" class="form-control" required></td><td><?= htmlspecialchars($default[3], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <?php endforeach; ?>
                </tbody></table><button class="btn btn-success"><i class="fas fa-save mr-1"></i>Save Grading Scale</button>
            </form>
        </div></div>
    </div></section>
</div>
<?php include '../../includes/footer.php'; ?>
