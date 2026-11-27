<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }
access_require_roles(array('Admin'));
if (!($conn instanceof mysqli)) { die('Database connection failed.'); }
$conn->set_charset('utf8mb4');
function assignment_e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$message = ''; $error = '';
if (empty($_SESSION['teacher_assignments_csrf'])) {
    $_SESSION['teacher_assignments_csrf'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if (in_array($action, array('add_class', 'add_section', 'delete_class', 'delete_section'), true)) {
        if (!isset($_POST['csrf_token'], $_SESSION['teacher_assignments_csrf'])
            || !hash_equals((string)$_SESSION['teacher_assignments_csrf'], (string)$_POST['csrf_token'])) {
            $error = 'This request could not be verified. Refresh the page and try again.';
        } elseif ($action === 'delete_class' || $action === 'delete_section') {
            $record_id = filter_var($_POST['record_id'] ?? 0, FILTER_VALIDATE_INT);
            if ($record_id === false || $record_id <= 0) {
                $error = 'Choose a valid class or section.';
            } else {
                if ($action === 'delete_class') {
                    $delete = $conn->prepare('UPDATE classes SET status=0 WHERE id=? AND status=1');
                    $item_name = 'class';
                } else {
                    $delete = $conn->prepare('UPDATE sections SET status=0 WHERE id=? AND status=1');
                    $item_name = 'section';
                }
                if (!$delete) {
                    $error = 'Unable to prepare the ' . $item_name . ' removal: ' . $conn->error;
                } else {
                    $delete->bind_param('i', $record_id);
                    if ($delete->execute() && $delete->affected_rows === 1) {
                        $message = ucfirst($item_name) . ' removed from active lists. Existing student and historical records were preserved.';
                    } else {
                        $error = 'The ' . $item_name . ' could not be removed. It may already be inactive or no longer exist.';
                    }
                    $delete->close();
                }
            }
        } elseif ($action === 'add_class') {
            $class_name = trim((string)($_POST['class_name'] ?? ''));
            $class_order = filter_var($_POST['class_order'] ?? null, FILTER_VALIDATE_INT);
            if ($class_name === '' || strlen($class_name) > 50 || $class_order === false || $class_order < 1 || $class_order > 999) {
                $error = 'Enter a class name (up to 50 characters) and a class order from 1 to 999.';
            } else {
                $check = $conn->prepare('SELECT id,status FROM classes WHERE class_name=? LIMIT 1');
                if (!$check) {
                    $error = 'Unable to check for an existing class: ' . $conn->error;
                } else {
                    $check->bind_param('s', $class_name);
                    if (!$check->execute()) {
                        $error = 'Unable to check for an existing class: ' . $check->error;
                    } else {
                        $existing_class = $check->get_result()->fetch_assoc();
                        if ($existing_class && (int)$existing_class['status'] === 1) {
                            $error = 'A class with this name already exists.';
                        } elseif ($existing_class) {
                            $restore = $conn->prepare('UPDATE classes SET status=1 WHERE id=?');
                            if (!$restore) {
                                $error = 'Unable to restore the existing class: ' . $conn->error;
                            } else {
                                $restore->bind_param('i', $existing_class['id']);
                                if ($restore->execute()) {
                                    $message = 'The existing class was reactivated.';
                                } else {
                                    $error = 'Unable to restore the existing class: ' . $restore->error;
                                }
                                $restore->close();
                            }
                        }
                    }
                    $check->close();
                }
                if ($error === '' && $message === '') {
                    $insert = $conn->prepare('INSERT INTO classes (class_name,class_order,status) VALUES (?,?,1)');
                    if (!$insert) {
                        $error = 'Unable to prepare the new class: ' . $conn->error;
                    } else {
                        $insert->bind_param('si', $class_name, $class_order);
                        if ($insert->execute()) {
                            $message = 'Class added successfully.';
                        } else {
                            $error = 'Unable to add the class: ' . $insert->error;
                        }
                        $insert->close();
                    }
                }
            }
        } else {
            $section_name = trim((string)($_POST['section_name'] ?? ''));
            if ($section_name === '' || strlen($section_name) > 10) {
                $error = 'Enter a section name of up to 10 characters.';
            } else {
                $check = $conn->prepare('SELECT id,status FROM sections WHERE section_name=? LIMIT 1');
                if (!$check) {
                    $error = 'Unable to check for an existing section: ' . $conn->error;
                } else {
                    $check->bind_param('s', $section_name);
                    if (!$check->execute()) {
                        $error = 'Unable to check for an existing section: ' . $check->error;
                    } else {
                        $existing_section = $check->get_result()->fetch_assoc();
                        if ($existing_section && (int)$existing_section['status'] === 1) {
                            $error = 'A section with this name already exists.';
                        } elseif ($existing_section) {
                            $restore = $conn->prepare('UPDATE sections SET status=1 WHERE id=?');
                            if (!$restore) {
                                $error = 'Unable to restore the existing section: ' . $conn->error;
                            } else {
                                $restore->bind_param('i', $existing_section['id']);
                                if ($restore->execute()) {
                                    $message = 'The existing section was reactivated.';
                                } else {
                                    $error = 'Unable to restore the existing section: ' . $restore->error;
                                }
                                $restore->close();
                            }
                        }
                    }
                    $check->close();
                }
                if ($error === '' && $message === '') {
                    $insert = $conn->prepare('INSERT INTO sections (section_name,status) VALUES (?,1)');
                    if (!$insert) {
                        $error = 'Unable to prepare the new section: ' . $conn->error;
                    } else {
                        $insert->bind_param('s', $section_name);
                        if ($insert->execute()) {
                            $message = 'Section added successfully.';
                        } else {
                            $error = 'Unable to add the section: ' . $insert->error;
                        }
                        $insert->close();
                    }
                }
            }
        }
    } elseif ($action === 'subject') {
        $teacher_id = (int)($_POST['teacher_id'] ?? 0); $subject_id = (int)($_POST['subject_id'] ?? 0);
        $stmt = $conn->prepare('INSERT INTO teacher_subjects (teacher_id, subject_id, status) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE status = 1');
        if ($teacher_id <= 0 || $subject_id <= 0 || !$stmt) { $error = 'Select an employee and subject.'; }
        else { $stmt->bind_param('ii', $teacher_id, $subject_id); $message = $stmt->execute() ? 'Subject assigned to employee.' : $stmt->error; $stmt->close(); }
    } elseif ($action === 'class') {
        $teacher_id = (int)($_POST['teacher_id'] ?? 0); $subject_id = (int)($_POST['subject_id'] ?? 0); $class_id = (int)($_POST['class_id'] ?? 0); $section_id = (int)($_POST['section_id'] ?? 0);
        $check = $conn->prepare('SELECT 1 FROM teacher_subjects WHERE teacher_id = ? AND subject_id = ? AND status = 1');
        if ($check) { $check->bind_param('ii', $teacher_id, $subject_id); $check->execute(); $allowed = $check->get_result()->num_rows > 0; $check->close(); } else { $allowed = false; }
        if (!$allowed || $class_id <= 0) { $error = 'Assign the subject to this employee first, then select a class.'; }
        else {
            $stmt = $conn->prepare('INSERT INTO teacher_class_assignments (teacher_id, subject_id, class_id, section_id, status) VALUES (?, ?, ?, NULLIF(?, 0), 1) ON DUPLICATE KEY UPDATE status = 1');
            if (!$stmt) { $error = $conn->error; } else { $stmt->bind_param('iiii', $teacher_id, $subject_id, $class_id, $section_id); $message = $stmt->execute() ? 'Class assigned according to the subject.' : $stmt->error; $stmt->close(); }
        }
    } elseif ($action === 'responsibility') {
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        $class_id = (int)($_POST['class_id'] ?? 0);
        $section_id = (int)($_POST['section_id'] ?? 0);
        $stmt = $conn->prepare('INSERT INTO teacher_class_responsibilities (teacher_id, class_id, section_id, status) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE status=1');
        if ($teacher_id <= 0 || $class_id <= 0 || $section_id <= 0 || !$stmt) {
            $error = 'Select an employee, class, and section.';
        } else {
            $stmt->bind_param('iii', $teacher_id, $class_id, $section_id);
            $message = $stmt->execute() ? 'Class and section responsibility assigned. The employee can now post homework and notices for this class-section.' : 'Unable to assign responsibility: ' . $stmt->error;
            $stmt->close();
        }
    }
}
if (isset($_GET['remove_subject']) || isset($_GET['remove_class']) || isset($_GET['remove_responsibility'])) {
    $table = isset($_GET['remove_subject']) ? 'teacher_subjects' : (isset($_GET['remove_class']) ? 'teacher_class_assignments' : 'teacher_class_responsibilities'); $id = (int)($_GET['remove_subject'] ?? $_GET['remove_class'] ?? $_GET['remove_responsibility']);
    $stmt = $conn->prepare("UPDATE {$table} SET status = 0 WHERE id = ?"); if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
    header('Location: assignments.php?updated=1'); exit;
}
if (isset($_GET['updated'])) { $message = 'Assignment removed.'; }
$teachers = array(); $subjects = array(); $classes = array(); $sections = array();
$r = $conn->query("SELECT id, full_name, designation FROM teachers WHERE status = 1 ORDER BY full_name"); while ($r && ($row = $r->fetch_assoc())) { $teachers[] = $row; }
$r = $conn->query("SELECT id, subject_name FROM subjects WHERE status = 1 ORDER BY subject_name"); while ($r && ($row = $r->fetch_assoc())) { $subjects[] = $row; }
$r = $conn->query("SELECT id, class_name, class_order FROM classes WHERE status = 1 ORDER BY class_order, id"); while ($r && ($row = $r->fetch_assoc())) { $classes[] = $row; }
$r = $conn->query("SELECT id, section_name FROM sections WHERE status = 1 ORDER BY section_name"); while ($r && ($row = $r->fetch_assoc())) { $sections[] = $row; }
$next_class_order = 1;
foreach ($classes as $class) {
    $next_class_order = max($next_class_order, (int)$class['class_order'] + 1);
}
?>
<?php include '../../includes/header.php'; ?><?php include '../../includes/navbar.php'; ?><?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper"><section class="content-header"><div class="container-fluid d-flex justify-content-between align-items-center"><h1><i class="fas fa-chalkboard-teacher"></i> Employee Teaching Assignments</h1><a href="assignments_view.php" class="btn btn-info"><i class="fas fa-list mr-1"></i>View Assignment Data</a></div></section><section class="content"><div class="container-fluid">
<?php if ($message !== ''): ?><div class="alert alert-success"><?= assignment_e($message); ?></div><?php endif; ?><?php if ($error !== ''): ?><div class="alert alert-danger"><?= assignment_e($error); ?></div><?php endif; ?>
<div class="card"><div class="card-header bg-dark text-white"><i class="fas fa-plus-circle mr-1"></i> Classes and Sections</div><div class="card-body"><div class="row">
<div class="col-lg-6"><h5>Add Class</h5><p class="text-muted">Add a new class to the class lists used throughout the ERP. Class order controls its position in reports and promotion.</p><form method="post" class="form-row align-items-end"><input type="hidden" name="action" value="add_class"><input type="hidden" name="csrf_token" value="<?= assignment_e($_SESSION['teacher_assignments_csrf']); ?>"><div class="form-group col-md-6"><label>Class name</label><input name="class_name" class="form-control" maxlength="50" placeholder="For example: Class XIII" required></div><div class="form-group col-md-3"><label>Class order</label><input type="number" name="class_order" class="form-control" min="1" max="999" value="<?= (int)$next_class_order; ?>" required></div><div class="form-group col-md-3"><button class="btn btn-success btn-block"><i class="fas fa-plus mr-1"></i>Add Class</button></div></form></div>
<div class="col-lg-6"><h5>Add Section</h5><p class="text-muted">Sections are shared options available to every class, such as A, B, or C.</p><form method="post" class="form-row align-items-end"><input type="hidden" name="action" value="add_section"><input type="hidden" name="csrf_token" value="<?= assignment_e($_SESSION['teacher_assignments_csrf']); ?>"><div class="form-group col-md-8"><label>Section name</label><input name="section_name" class="form-control" maxlength="10" placeholder="For example: A" required></div><div class="form-group col-md-4"><button class="btn btn-success btn-block"><i class="fas fa-plus mr-1"></i>Add Section</button></div></form></div>
</div><hr><div class="row"><div class="col-lg-6"><strong>Active classes:</strong> <?php if ($classes): ?><?php foreach ($classes as $class): ?><span class="d-inline-flex align-items-center border rounded px-2 py-1 mr-1 mb-1"><?= assignment_e($class['class_name']); ?> <small class="ml-1">(<?= (int)$class['class_order']; ?>)</small><form method="post" class="ml-2" onsubmit="return confirm('Remove this class from active lists? Existing student and historical records will be preserved.');"><input type="hidden" name="action" value="delete_class"><input type="hidden" name="record_id" value="<?= (int)$class['id']; ?>"><input type="hidden" name="csrf_token" value="<?= assignment_e($_SESSION['teacher_assignments_csrf']); ?>"><button type="submit" class="btn btn-sm btn-outline-danger py-0" aria-label="Delete class <?= assignment_e($class['class_name']); ?>" title="Delete class"><i class="fas fa-trash"></i></button></form></span><?php endforeach; ?><?php else: ?><span class="text-muted">None</span><?php endif; ?></div><div class="col-lg-6"><strong>Active sections:</strong> <?php if ($sections): ?><?php foreach ($sections as $section): ?><span class="d-inline-flex align-items-center border rounded px-2 py-1 mr-1 mb-1"><?= assignment_e($section['section_name']); ?><form method="post" class="ml-2" onsubmit="return confirm('Remove this section from active lists? Existing student and historical records will be preserved.');"><input type="hidden" name="action" value="delete_section"><input type="hidden" name="record_id" value="<?= (int)$section['id']; ?>"><input type="hidden" name="csrf_token" value="<?= assignment_e($_SESSION['teacher_assignments_csrf']); ?>"><button type="submit" class="btn btn-sm btn-outline-danger py-0" aria-label="Delete section <?= assignment_e($section['section_name']); ?>" title="Delete section"><i class="fas fa-trash"></i></button></form></span><?php endforeach; ?><?php else: ?><span class="text-muted">None</span><?php endif; ?></div></div></div></div>
<div class="row"><div class="col-lg-6"><div class="card"><div class="card-header bg-primary text-white">1. Assign Subjects to Employee</div><div class="card-body"><form method="post"><input type="hidden" name="action" value="subject"><div class="form-group"><label>Employee</label><select name="teacher_id" class="form-control" required><option value="">Select employee</option><?php foreach ($teachers as $teacher): ?><option value="<?= (int)$teacher['id']; ?>"><?= assignment_e($teacher['full_name'] . ($teacher['designation'] ? ' - ' . $teacher['designation'] : '')); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Subject</label><select name="subject_id" class="form-control" required><option value="">Select subject</option><?php foreach ($subjects as $subject): ?><option value="<?= (int)$subject['id']; ?>"><?= assignment_e($subject['subject_name']); ?></option><?php endforeach; ?></select></div><button class="btn btn-success"><i class="fas fa-link"></i> Assign Subject</button></form></div></div></div>
<div class="col-lg-6"><div class="card"><div class="card-header bg-success text-white">2. Assign Classes According to Subject</div><div class="card-body"><form method="post"><input type="hidden" name="action" value="class"><div class="form-group"><label>Employee</label><select name="teacher_id" id="class_teacher" class="form-control" required><option value="">Select employee</option><?php foreach ($teachers as $teacher): ?><option value="<?= (int)$teacher['id']; ?>"><?= assignment_e($teacher['full_name']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Assigned Subject</label><select name="subject_id" id="class_subject" class="form-control" required><option value="">Select employee first</option></select></div><div class="form-group"><label>Class</label><select name="class_id" class="form-control" required><option value="">Select class</option><?php foreach ($classes as $class): ?><option value="<?= (int)$class['id']; ?>"><?= assignment_e($class['class_name']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Section (optional)</label><select name="section_id" class="form-control"><option value="">All sections / class responsibility</option><?php foreach ($sections as $section): ?><option value="<?= (int)$section['id']; ?>"><?= assignment_e($section['section_name']); ?></option><?php endforeach; ?></select></div><button class="btn btn-success"><i class="fas fa-school"></i> Assign Class</button></form></div></div></div></div>
<div class="card"><div class="card-header bg-warning">3. Assign Class/Section Responsibility (class teacher)</div><div class="card-body"><p>Assign a specific class and section, for example Class II - Section A. The employee can then post homework and notices for that group.</p><form method="post" class="form-row align-items-end"><input type="hidden" name="action" value="responsibility"><div class="form-group col-md-4"><label>Employee</label><select name="teacher_id" class="form-control" required><option value="">Select employee</option><?php foreach ($teachers as $teacher): ?><option value="<?= (int)$teacher['id']; ?>"><?= assignment_e($teacher['full_name']); ?></option><?php endforeach; ?></select></div><div class="form-group col-md-3"><label>Class</label><select name="class_id" class="form-control" required><option value="">Select class</option><?php foreach ($classes as $class): ?><option value="<?= (int)$class['id']; ?>"><?= assignment_e($class['class_name']); ?></option><?php endforeach; ?></select></div><div class="form-group col-md-3"><label>Section</label><select name="section_id" class="form-control" required><option value="">Select section</option><?php foreach ($sections as $section): ?><option value="<?= (int)$section['id']; ?>"><?= assignment_e($section['section_name']); ?></option><?php endforeach; ?></select></div><div class="form-group col-md-2"><button class="btn btn-warning btn-block"><i class="fas fa-user-check"></i> Assign</button></div></form></div></div>
<script>
var teacherSubjects = <?= json_encode(array_map(function ($row) { return array('teacher_id'=>(int)$row['teacher_id'], 'subject_id'=>(int)$row['subject_id'], 'subject_name'=>$row['subject_name']); }, (function () use ($conn) { $items=array(); $q=$conn->query("SELECT ts.teacher_id, ts.subject_id, s.subject_name FROM teacher_subjects ts INNER JOIN subjects s ON s.id=ts.subject_id WHERE ts.status=1 ORDER BY s.subject_name"); while($q && ($x=$q->fetch_assoc())){$items[]=$x;} return $items; })()), JSON_UNESCAPED_UNICODE); ?>;
var teacherSelect=document.getElementById('class_teacher'), subjectSelect=document.getElementById('class_subject'); teacherSelect.addEventListener('change',function(){subjectSelect.innerHTML='<option value="">Select subject</option>'; teacherSubjects.forEach(function(item){if(item.teacher_id===parseInt(teacherSelect.value,10)){var o=document.createElement('option');o.value=item.subject_id;o.textContent=item.subject_name;subjectSelect.appendChild(o);}});});
</script></div></section></div><?php include '../../includes/footer.php'; ?>
