<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

$class = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
$section = filter_input(INPUT_POST, 'section_id', FILTER_VALIDATE_INT);
$subject = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
$exam = filter_input(INPUT_POST, 'exam_id', FILTER_VALIDATE_INT);
$marks = isset($_POST['marks']) && is_array($_POST['marks']) ? $_POST['marks'] : array();
$grade_values = isset($_POST['grades']) && is_array($_POST['grades']) ? $_POST['grades'] : array();

if (!$class || !$section || !$subject || !$exam || (empty($marks) && empty($grade_values))) {
    header('Location: index.php?error=invalid');
    exit;
}

$assignment_stmt = $conn->prepare("SELECT id FROM class_subjects WHERE class_id = ? AND subject_id = ? AND status = 1 LIMIT 1");
if (!$assignment_stmt) {
    header('Location: index.php?error=database');
    exit;
}
$assignment_stmt->bind_param('ii', $class, $subject);
$assignment_stmt->execute();
$assignment_exists = $assignment_stmt->get_result()->num_rows === 1;
$assignment_stmt->close();
if (!$assignment_exists) {
    header("Location: index.php?class_id=$class&section_id=$section&exam_id=$exam&error=subject");
    exit;
}

$class_stmt = $conn->prepare("SELECT class_order FROM classes WHERE id=? AND status=1");
$class_stmt->bind_param('i', $class); $class_stmt->execute();
$class_order = (int)($class_stmt->get_result()->fetch_assoc()['class_order'] ?? 0); $class_stmt->close();
$grade_only = $class_order > 0 && $class_order <= 8;
$limit_stmt = $conn->prepare("SELECT COALESCE(et.max_marks, 100) AS max_marks FROM exams e LEFT JOIN exam_types et ON et.id=e.exam_type_id WHERE e.id=? AND e.status=1 LIMIT 1");
$limit_stmt->bind_param('i', $exam);
$limit_stmt->execute();
$limit_row = $limit_stmt->get_result()->fetch_assoc();
$limit_stmt->close();
$max_marks = $limit_row ? (float)$limit_row['max_marks'] : 100;

$stmt = $conn->prepare(
    "INSERT INTO marks (student_id, class_id, section_id, subject_id, exam_id, max_marks, obtained_marks)
     SELECT ?, ?, ?, ?, ?, ?, ?
     FROM students
     WHERE id = ? AND class_id = ? AND section_id = ? AND status = 1
     ON DUPLICATE KEY UPDATE
       class_id = VALUES(class_id),
       section_id = VALUES(section_id),
       max_marks = VALUES(max_marks),
       obtained_marks = VALUES(obtained_marks),
       updated_at = CURRENT_TIMESTAMP"
);

if (!$stmt) {
    header('Location: index.php?error=database');
    exit;
}

$conn->begin_transaction();
$ok = true;
if ($grade_only) {
    $grade_stmt = $conn->prepare("INSERT INTO marks (student_id, class_id, section_id, subject_id, exam_id, max_marks, obtained_marks, grade) SELECT ?, ?, ?, ?, ?, 0, 0, ? FROM students WHERE id=? AND class_id=? AND section_id=? AND status=1 ON DUPLICATE KEY UPDATE class_id=VALUES(class_id), section_id=VALUES(section_id), grade=VALUES(grade), max_marks=0, obtained_marks=0, updated_at=CURRENT_TIMESTAMP");
    $allowed_grades = array('A1','A2','B1','B2','C1','C2','D','E');
    foreach ($grade_values as $student_id => $value) {
        $student_id = (int)$student_id; $value = strtoupper(trim((string)$value));
        if ($student_id <= 0 || $value === '') continue;
        if (!in_array($value, $allowed_grades, true)) { $ok = false; break; }
        $grade_stmt->bind_param('iiiiisiii', $student_id, $class, $section, $subject, $exam, $value, $student_id, $class, $section);
        if (!$grade_stmt->execute()) { $ok = false; break; }
    }
    $grade_stmt->close();
} else foreach ($marks as $student_id => $value) {
    $student_id = (int)$student_id;
    if ($student_id <= 0 || $value === '') {
        continue;
    }
    if (!is_numeric($value) || (float)$value < 0 || (float)$value > $max_marks) {
        $ok = false;
        break;
    }
    $obtained = (float)$value;
    $stmt->bind_param('iiiiidddii', $student_id, $class, $section, $subject, $exam, $max_marks, $obtained, $student_id, $class, $section);
    if (!$stmt->execute()) {
        $ok = false;
        break;
    }
}
$stmt->close();

if ($ok && $conn->commit()) {
    header("Location: index.php?class_id=$class&section_id=$section&subject_id=$subject&exam_id=$exam&saved=1");
} else {
    $conn->rollback();
    header("Location: index.php?class_id=$class&section_id=$section&subject_id=$subject&exam_id=$exam&error=save");
}
exit;
