<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/access.php';

access_require_roles(array('Admin', 'Teacher'));
access_require_feature($conn, 'exams');
$conn->set_charset('utf8mb4');

$class_id = filter_var($_POST['class_id'] ?? null, FILTER_VALIDATE_INT);
$section_id = filter_var($_POST['section_id'] ?? null, FILTER_VALIDATE_INT);
$subject_id = filter_var($_POST['subject_id'] ?? null, FILTER_VALIDATE_INT);
$exam_id = filter_var($_POST['exam_id'] ?? null, FILTER_VALIDATE_INT);
$marks = isset($_POST['marks']) && is_array($_POST['marks']) ? $_POST['marks'] : array();
$grades = isset($_POST['grades']) && is_array($_POST['grades']) ? $_POST['grades'] : array();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$class_id || !$section_id || !$subject_id || !$exam_id || (empty($marks) && empty($grades))) {
    header('Location: index.php?error=invalid');
    exit;
}
if (!isset($_POST['csrf_token'], $_SESSION['marks_csrf'])
    || !hash_equals((string)$_SESSION['marks_csrf'], (string)$_POST['csrf_token'])) {
    http_response_code(400);
    exit('The marks form expired. Return to Marks Entry, refresh it, and try again.');
}
if (access_is_teacher()
    && (!access_teacher_can_class($conn, $class_id, $section_id)
        || !access_teacher_can_subject($conn, $class_id, $section_id, $subject_id))) {
    http_response_code(403);
    exit('You are not assigned to enter marks for this class, section, and subject.');
}

$assignment = $conn->prepare('SELECT 1 FROM class_subjects WHERE class_id=? AND subject_id=? AND status=1 LIMIT 1');
$current_session_id = access_current_academic_session($conn);
$exam_check = $conn->prepare('SELECT COALESCE(et.max_marks,100) AS max_marks FROM exams e LEFT JOIN exam_types et ON et.id=e.exam_type_id WHERE e.id=? AND e.status=1 AND (?=0 OR e.session_id=?) LIMIT 1');
$class_check = $conn->prepare('SELECT class_order FROM classes WHERE id=? AND status=1 LIMIT 1');
if (!$assignment || !$exam_check || !$class_check) {
    error_log('Marks validation preparation failed: ' . $conn->error);
    header('Location: index.php?error=database');
    exit;
}
$assignment->bind_param('ii', $class_id, $subject_id);
$assignment->execute();
$assignment_exists = $assignment->get_result()->num_rows === 1;
$assignment->close();
$exam_check->bind_param('iii', $exam_id, $current_session_id, $current_session_id);
$exam_check->execute();
$exam_row = $exam_check->get_result()->fetch_assoc();
$exam_check->close();
$class_check->bind_param('i', $class_id);
$class_check->execute();
$class_order = (int)($class_check->get_result()->fetch_assoc()['class_order'] ?? 0);
$class_check->close();
if (!$assignment_exists || !$exam_row || $class_order <= 0) {
    header('Location: index.php?error=invalid');
    exit;
}
$grade_only = $class_order <= 8;
$max_marks = (float)$exam_row['max_marks'];
$entered_by = access_is_teacher() ? (int)($_SESSION['linked_id'] ?? 0) : null;
$return_url = 'index.php?class_id=' . $class_id . '&section_id=' . $section_id . '&subject_id=' . $subject_id . '&exam_id=' . $exam_id;
$conn->begin_transaction();
$ok = true;
$error_message = '';

if ($grade_only) {
    $statement = $conn->prepare("INSERT INTO marks (student_id,class_id,section_id,subject_id,exam_id,max_marks,obtained_marks,grade,entry_updated_by) SELECT ?,?,?,?, ?,0,0,?,? FROM students WHERE id=? AND class_id=? AND section_id=? AND status=1 ON DUPLICATE KEY UPDATE class_id=VALUES(class_id),section_id=VALUES(section_id),grade=VALUES(grade),max_marks=0,obtained_marks=0,entry_updated_by=VALUES(entry_updated_by),updated_at=CURRENT_TIMESTAMP");
    if (!$statement) {
        $ok = false;
        $error_message = $conn->error;
    } else {
        $allowed_grades = array('A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'D', 'E');
        foreach ($grades as $student_key => $grade) {
            $student_id = filter_var($student_key, FILTER_VALIDATE_INT);
            $grade = strtoupper(trim((string)$grade));
            if (!$student_id || $student_id < 1 || $grade === '') {
                continue;
            }
            if (!in_array($grade, $allowed_grades, true)) {
                $ok = false;
                $error_message = 'One or more grades are invalid.';
                break;
            }
            $statement->bind_param('iiiiisiiii', $student_id, $class_id, $section_id, $subject_id, $exam_id, $grade, $entered_by, $student_id, $class_id, $section_id);
            if (!$statement->execute()) {
                $ok = false;
                $error_message = $statement->error;
                break;
            }
        }
        $statement->close();
    }
} else {
    $statement = $conn->prepare('INSERT INTO marks (student_id,class_id,section_id,subject_id,exam_id,max_marks,obtained_marks,entry_updated_by) SELECT ?,?,?,?,?,?,?,? FROM students WHERE id=? AND class_id=? AND section_id=? AND status=1 ON DUPLICATE KEY UPDATE class_id=VALUES(class_id),section_id=VALUES(section_id),max_marks=VALUES(max_marks),obtained_marks=VALUES(obtained_marks),entry_updated_by=VALUES(entry_updated_by),updated_at=CURRENT_TIMESTAMP');
    if (!$statement) {
        $ok = false;
        $error_message = $conn->error;
    } else {
        foreach ($marks as $student_key => $value) {
            $student_id = filter_var($student_key, FILTER_VALIDATE_INT);
            if (!$student_id || $student_id < 1) {
                $ok = false;
                $error_message = 'One or more student records are invalid.';
                break;
            }
            if ($value === '' || $value === null) {
                continue;
            }
            if (!is_numeric($value) || (float)$value < 0 || (float)$value > $max_marks) {
                $ok = false;
                $error_message = 'Marks must be between 0 and the maximum for this exam.';
                break;
            }
            $obtained = (float)$value;
            $statement->bind_param('iiiiiddiiii', $student_id, $class_id, $section_id, $subject_id, $exam_id, $max_marks, $obtained, $entered_by, $student_id, $class_id, $section_id);
            if (!$statement->execute()) {
                $ok = false;
                $error_message = $statement->error;
                break;
            }
        }
        $statement->close();
    }
}

if ($ok && $conn->commit()) {
    header('Location: ' . $return_url . '&saved=1');
} else {
    $conn->rollback();
    error_log('Marks save failed: ' . $error_message);
    header('Location: ' . $return_url . '&error=save');
}
exit;
