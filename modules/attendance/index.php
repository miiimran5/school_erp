<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection failed.');
}

$conn->set_charset('utf8mb4');

function attendance_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function attendance_valid_date($value)
{
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

$statuses = array('Present', 'Absent', 'Late', 'Half Day', 'Leave');
$selected_date = isset($_REQUEST['attendance_date']) ? trim($_REQUEST['attendance_date']) : date('Y-m-d');
$selected_class = isset($_REQUEST['class_id']) ? (int)$_REQUEST['class_id'] : 0;
$selected_section = isset($_REQUEST['section_id']) ? (int)$_REQUEST['section_id'] : 0;
$success = '';
$error = '';
$students = array();
$summary = array('Present' => 0, 'Absent' => 0, 'Late' => 0, 'Half Day' => 0, 'Leave' => 0, 'Unmarked' => 0);

if (!attendance_valid_date($selected_date)) {
    $selected_date = date('Y-m-d');
    $error = 'Please choose a valid attendance date.';
}

if (empty($_SESSION['attendance_csrf'])) {
    $_SESSION['attendance_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['attendance_csrf']) || !hash_equals($_SESSION['attendance_csrf'], (string)$_POST['attendance_csrf'])) {
        $error = 'Your form session expired. Please reload the page and try again.';
    } elseif ($selected_class <= 0 || $selected_section <= 0) {
        $error = 'Please select a class and section before saving attendance.';
    } elseif (!isset($_POST['status']) || !is_array($_POST['status'])) {
        $error = 'No attendance records were submitted.';
    } else {
        $posted_status = $_POST['status'];
        $posted_remarks = isset($_POST['remarks']) && is_array($_POST['remarks']) ? $_POST['remarks'] : array();
        $student_ids = array();
        foreach ($posted_status as $student_id => $status) {
            $student_id = (int)$student_id;
            if ($student_id > 0 && in_array($status, $statuses, true)) {
                $student_ids[] = $student_id;
            }
        }

        if (empty($student_ids)) {
            $error = 'Please mark at least one student.';
        } else {
            $student_ids = array_values(array_unique($student_ids));
            $safe_student_ids = implode(',', $student_ids);
            $stmt = $conn->prepare(
                "SELECT id FROM students WHERE class_id = ? AND section_id = ? AND status = 1 AND id IN ($safe_student_ids)"
            );

            if (!$stmt) {
                $error = 'Unable to validate the selected students.';
            } else {
                $stmt->bind_param('ii', $selected_class, $selected_section);
                $valid_ids = array();
                if (!$stmt->execute()) {
                    $error = 'Unable to validate the selected students.';
                } else {
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $valid_ids[] = (int)$row['id'];
                    }
                }
                $stmt->close();

                if ($error === '' && count($valid_ids) !== count($student_ids)) {
                    $error = 'One or more selected students are no longer active in this class and section.';
                } elseif ($error === '') {
                    $conn->begin_transaction();
                    $save_stmt = $conn->prepare(
                        "INSERT INTO student_attendance (student_id, attendance_date, status, remarks, created_by)
                         VALUES (?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks), created_by = VALUES(created_by), updated_at = CURRENT_TIMESTAMP"
                    );

                    if (!$save_stmt) {
                        $conn->rollback();
                        $error = 'Unable to prepare the attendance save operation.';
                    } else {
                        $save_ok = true;
                        $user_id = (int)$_SESSION['user_id'];
                        foreach ($valid_ids as $student_id) {
                            $status = (string)$posted_status[$student_id];
                            $remarks = isset($posted_remarks[$student_id]) ? trim((string)$posted_remarks[$student_id]) : '';
                            $remarks = substr($remarks, 0, 500);
                            $save_stmt->bind_param('isssi', $student_id, $selected_date, $status, $remarks, $user_id);
                            if (!$save_stmt->execute()) {
                                $save_ok = false;
                                break;
                            }
                        }
                        $save_stmt->close();

                        if ($save_ok && $conn->commit()) {
                            $success = 'Attendance saved successfully.';
                        } else {
                            $conn->rollback();
                            $error = 'Attendance could not be saved. No changes were made.';
                        }
                    }
                }
            }
        }
    }
}

$classes = array();
$class_result = $conn->query("SELECT id, class_name FROM classes WHERE status = 1 ORDER BY class_order, id");
if ($class_result) {
    while ($row = $class_result->fetch_assoc()) {
        $classes[] = $row;
    }
}

$sections = array();
$section_result = $conn->query("SELECT id, section_name FROM sections WHERE status = 1 ORDER BY section_name, id");
if ($section_result) {
    while ($row = $section_result->fetch_assoc()) {
        $sections[] = $row;
    }
}

if ($selected_class > 0 && $selected_section > 0) {
    $stmt = $conn->prepare(
        "SELECT s.id, s.admission_no, s.roll_no, s.student_name, a.status AS attendance_status, a.remarks
         FROM students s
         LEFT JOIN student_attendance a ON a.student_id = s.id AND a.attendance_date = ?
         WHERE s.class_id = ? AND s.section_id = ? AND s.status = 1
         ORDER BY s.roll_no, s.student_name"
    );
    if ($stmt) {
        $stmt->bind_param('sii', $selected_date, $selected_class, $selected_section);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
                $status = $row['attendance_status'] ?: 'Unmarked';
                if (isset($summary[$status])) {
                    $summary[$status]++;
                }
            }
        } elseif ($error === '') {
            $error = 'Unable to load students for the selected class and section.';
        }
        $stmt->close();
    } elseif ($error === '') {
        $error = 'Unable to load students for the selected class and section.';
    }
}

include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1><i class="fas fa-calendar-check"></i> Student Attendance</h1>
        </div>
    </section>
    <section class="content">
        <div class="container-fluid">
            <?php if ($success !== ''): ?><div class="alert alert-success"><?= attendance_e($success); ?></div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="alert alert-danger"><?= attendance_e($error); ?></div><?php endif; ?>

            <div class="card">
                <div class="card-header bg-primary text-white">Attendance Filters</div>
                <div class="card-body">
                    <form method="get" class="row">
                        <div class="form-group col-md-3">
                            <label for="attendance_date">Date</label>
                            <input id="attendance_date" type="date" name="attendance_date" class="form-control" value="<?= attendance_e($selected_date); ?>" required>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="class_id">Class</label>
                            <select id="class_id" name="class_id" class="form-control" required>
                                <option value="">Select class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= (int)$class['id']; ?>" <?= $selected_class === (int)$class['id'] ? 'selected' : ''; ?>><?= attendance_e($class['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="section_id">Section</label>
                            <select id="section_id" name="section_id" class="form-control" required>
                                <option value="">Select section</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= (int)$section['id']; ?>" <?= $selected_section === (int)$section['id'] ? 'selected' : ''; ?>><?= attendance_e($section['section_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3 d-flex align-items-end">
                            <button class="btn btn-primary btn-block" type="submit"><i class="fas fa-search"></i> Load Students</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($selected_class > 0 && $selected_section > 0): ?>
                <div class="row">
                    <?php foreach (array('Present' => 'success', 'Absent' => 'danger', 'Late' => 'warning', 'Half Day' => 'info', 'Leave' => 'secondary', 'Unmarked' => 'dark') as $label => $colour): ?>
                        <div class="col-sm-2"><div class="small-box bg-<?= $colour; ?>"><div class="inner"><h3><?= (int)$summary[$label]; ?></h3><p><?= $label; ?></p></div><div class="icon"><i class="fas fa-user-check"></i></div></div></div>
                    <?php endforeach; ?>
                </div>
                <form method="post">
                    <input type="hidden" name="attendance_csrf" value="<?= attendance_e($_SESSION['attendance_csrf']); ?>">
                    <input type="hidden" name="attendance_date" value="<?= attendance_e($selected_date); ?>">
                    <input type="hidden" name="class_id" value="<?= $selected_class; ?>">
                    <input type="hidden" name="section_id" value="<?= $selected_section; ?>">
                    <div class="card">
                        <div class="card-header bg-success text-white">Students for <?= attendance_e($selected_date); ?></div>
                        <div class="card-body p-0 table-responsive">
                            <?php if (empty($students)): ?>
                                <div class="p-4 text-center text-muted">No active students found for this class and section.</div>
                            <?php else: ?>
                                <table class="table table-bordered table-striped mb-0">
                                    <thead><tr><th>Roll No.</th><th>Admission No.</th><th>Student Name</th><th>Status</th><th>Remarks</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <?php $current_status = $student['attendance_status'] ?: 'Present'; ?>
                                        <tr>
                                            <td><?= attendance_e($student['roll_no']); ?></td>
                                            <td><?= attendance_e($student['admission_no']); ?></td>
                                            <td><?= attendance_e($student['student_name']); ?></td>
                                            <td><select name="status[<?= (int)$student['id']; ?>]" class="form-control">
                                                <?php foreach ($statuses as $status): ?><option value="<?= attendance_e($status); ?>" <?= $current_status === $status ? 'selected' : ''; ?>><?= attendance_e($status); ?></option><?php endforeach; ?>
                                            </select></td>
                                            <td><input type="text" name="remarks[<?= (int)$student['id']; ?>]" class="form-control" maxlength="500" value="<?= attendance_e($student['remarks']); ?>" placeholder="Optional"></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($students)): ?><div class="card-footer"><button class="btn btn-success" type="submit"><i class="fas fa-save"></i> Save / Update Attendance</button></div><?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php include '../../includes/footer.php'; ?>
