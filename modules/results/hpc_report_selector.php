<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';

access_require_roles(array('Admin', 'Teacher'));
$conn->set_charset('utf8mb4');
access_require_feature($conn, 'hpc_reports');

function hpc_selector_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$classes = array();
$sections = array();
$sessions = array();
$students = array();
$error = '';

$class_result = $conn->query("SELECT id, class_name FROM classes WHERE status = 1 ORDER BY class_order, id");
if ($class_result) {
    while ($row = $class_result->fetch_assoc()) {
        if (!access_is_teacher() || access_teacher_can_class($conn, (int)$row['id'], 0)) {
            $classes[] = $row;
        }
    }
} else {
    $error = 'Unable to load classes: ' . $conn->error;
}

$section_result = $conn->query("SELECT id, section_name FROM sections WHERE status = 1 ORDER BY section_name, id");
if ($section_result) {
    while ($row = $section_result->fetch_assoc()) {
        $sections[] = $row;
    }
} elseif ($error === '') {
    $error = 'Unable to load sections: ' . $conn->error;
}

$session_result = $conn->query("SELECT id, session_name FROM academic_sessions WHERE status = 1 ORDER BY id DESC");
if ($session_result) {
    while ($row = $session_result->fetch_assoc()) {
        $sessions[] = $row;
    }
} elseif ($error === '') {
    $error = 'Unable to load academic sessions: ' . $conn->error;
}

$selected_class = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selected_section = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$selected_session = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;

if ($selected_class > 0 && $selected_section > 0) {
    if (access_is_teacher() && !access_teacher_can_class($conn, $selected_class, $selected_section)) {
        http_response_code(403);
        exit('You are not assigned to this class and section.');
    }
    $stmt = $conn->prepare(
        "SELECT id, admission_no, student_name, roll_no
         FROM students
         WHERE class_id = ? AND section_id = ? AND status = 1
         ORDER BY roll_no, student_name"
    );
    if ($stmt) {
        $stmt->bind_param('ii', $selected_class, $selected_section);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
            }
        } elseif ($error === '') {
            $error = 'Unable to load students for the selected class and section.';
        }
        $stmt->close();
    } elseif ($error === '') {
        $error = 'Unable to prepare the student list.';
    }
}

include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1><i class="fas fa-address-card"></i> HPC Report Card</h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= hpc_selector_e($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-sliders-h mr-1"></i> Select Report Details
                </div>
                <div class="card-body">
                    <form method="get" class="row">
                        <div class="form-group col-md-4">
                            <label for="class_id">Class</label>
                            <select class="form-control" id="class_id" name="class_id" required>
                                <option value="">Select class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= (int)$class['id']; ?>" <?= $selected_class === (int)$class['id'] ? 'selected' : ''; ?>>
                                        <?= hpc_selector_e($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="section_id">Section</label>
                            <select class="form-control" id="section_id" name="section_id" required>
                                <option value="">Select section</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?= (int)$section['id']; ?>" <?= $selected_section === (int)$section['id'] ? 'selected' : ''; ?>>
                                        <?= hpc_selector_e($section['section_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="session_id">Academic Session</label>
                            <select class="form-control" id="session_id" name="session_id" required>
                                <option value="">Select session</option>
                                <?php foreach ($sessions as $session): ?>
                                    <option value="<?= (int)$session['id']; ?>" <?= $selected_session === (int)$session['id'] ? 'selected' : ''; ?>>
                                        <?= hpc_selector_e($session['session_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search mr-1"></i> Load Students
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($selected_class > 0 && $selected_section > 0): ?>
                <div class="card">
                    <div class="card-header">Students</div>
                    <div class="card-body">
                        <?php if (empty($students)): ?>
                            <div class="empty-state p-4 text-center">No active students found for this class and section.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Roll No.</th>
                                            <th>Admission No.</th>
                                            <th>Student Name</th>
                                            <th class="text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td><?= hpc_selector_e($student['roll_no']); ?></td>
                                                <td><?= hpc_selector_e($student['admission_no']); ?></td>
                                                <td><?= hpc_selector_e($student['student_name']); ?></td>
                                                <td class="text-right">
                                                    <?php if ($selected_session > 0): ?>
                                                        <a class="btn btn-sm btn-primary" href="hpc_report.php?student_id=<?= (int)$student['id']; ?>&session_id=<?= $selected_session; ?>&class_id=<?= $selected_class; ?>&section_id=<?= $selected_section; ?>">
                                                            <i class="fas fa-eye mr-1"></i> View Report
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Select a session</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php include '../../includes/footer.php'; ?>
