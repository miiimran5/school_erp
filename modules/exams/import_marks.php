<?php
/**
 * School ERP
 * T-037 Step 6.5
 * HPC Marks Centre
 *
 * Features:
 * 1. Teacher Portal Entry
 * 2. Scholastic Marks
 * 3. PA (10), SE (5), NB (5), Written (80)
 * 4. Automatic Total / Grade
 * 5. Co-Scholastic Entry
 * 6. Remarks
 * 7. CSV Template Download
 * 8. CSV Upload / Import
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db_connect.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not available.");
}

$conn->set_charset("utf8mb4");

/* ---------------------------------------------------------
   Helper
--------------------------------------------------------- */
function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* ---------------------------------------------------------
   Grade calculation
--------------------------------------------------------- */
function calculateGrade($marks)
{
    $marks = (float)$marks;

    if ($marks >= 91) return 'A1';
    if ($marks >= 81) return 'A2';
    if ($marks >= 71) return 'B1';
    if ($marks >= 61) return 'B2';
    if ($marks >= 51) return 'C1';
    if ($marks >= 41) return 'C2';
    if ($marks >= 33) return 'D';
    return 'E';
}

/* ---------------------------------------------------------
   CSV Template Download
--------------------------------------------------------- */
if (isset($_GET['download_template'])) {

    $filename = 'HPC_Marks_Template.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    $headers = [
        'admission_no',
        'student_name',
        'class_name',
        'section_name',
        'exam_name',
        'subject_code',
        'subject_name',
        'subject_type',

        'pa_marks',
        'se_marks',
        'nb_marks',
        'written_marks',
        'obtained_marks',
        'grade',
        'remarks',

        'computer_term_i',
        'computer_term_ii',
        'physical_education_term_i',
        'physical_education_term_ii',
        'urdu_term_i',
        'urdu_term_ii',
        'gk_term_i',
        'gk_term_ii',

        'critical_thinking_term_i',
        'critical_thinking_term_ii',
        'problem_solving_term_i',
        'problem_solving_term_ii',
        'communication_term_i',
        'communication_term_ii',

        'respect_for_others_term_i',
        'respect_for_others_term_ii',
        'responsibility_term_i',
        'responsibility_term_ii',
        'honesty_term_i',
        'honesty_term_ii',
        'empathy_term_i',
        'empathy_term_ii',

        'work_habits_term_i',
        'work_habits_term_ii',
        'time_management_term_i',
        'time_management_term_ii',
        'organization_term_i',
        'organization_term_ii',
        'initiative_term_i',
        'initiative_term_ii',
        'perseverance_term_i',
        'perseverance_term_ii',

        'leadership_role_term_i',
        'leadership_role_term_ii',
        'team_work_collaboration_term_i',
        'team_work_collaboration_term_ii'
    ];

    fputcsv($output, $headers);

    fclose($output);
    exit;
}

/* ---------------------------------------------------------
   CSV Import
--------------------------------------------------------- */
$success_message = $success_message ?? '';
$error_message = $error_message ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'csv_import') {

    if (!isset($_FILES['csv_file'])
        || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {

        $error_message = 'Please select a valid CSV file.';
    } else {

        $tmp = $_FILES['csv_file']['tmp_name'];

        $handle = fopen($tmp, 'r');

        if (!$handle) {

            $error_message = 'Unable to open CSV file.';

        } else {

            $headers = fgetcsv($handle);

            if (!$headers) {

                $error_message = 'CSV file is empty.';

            } else {

                $headers = array_map(function ($value) {
                    return trim(strtolower($value));
                }, $headers);

                $header_map = [];

                foreach ($headers as $index => $header) {
                    $header_map[$header] = $index;
                }

                $required = [
                    'admission_no',
                    'subject_name',
                    'pa_marks',
                    'se_marks',
                    'nb_marks',
                    'written_marks'
                ];

                $missing = [];

                foreach ($required as $required_field) {
                    if (!array_key_exists($required_field, $header_map)) {
                        $missing[] = $required_field;
                    }
                }

                if (!empty($missing)) {

                    $error_message =
                        'CSV missing required columns: ' .
                        implode(', ', $missing);

                } else {

                    $imported = 0;
                    $skipped = 0;
                    $errors = [];

                    while (($row = fgetcsv($handle)) !== false) {

                        if (count($row) === 1 && trim($row[0]) === '') {
                            continue;
                        }

                        $get = function ($name) use ($row, $header_map) {
                            if (!isset($header_map[$name])) {
                                return '';
                            }

                            $index = $header_map[$name];

                            return isset($row[$index])
                                ? trim($row[$index])
                                : '';
                        };

                        $admission_no = $get('admission_no');
                        $subject_name = $get('subject_name');

                        if ($admission_no === '' || $subject_name === '') {
                            $skipped++;
                            continue;
                        }

                        /* Student */
                        $stmt = $conn->prepare(
                            "SELECT id, class_id, section_id
                             FROM students
                             WHERE admission_no = ?
                             LIMIT 1"
                        );

                        if (!$stmt) {
                            $errors[] = 'Student query failed.';
                            continue;
                        }

                        $stmt->bind_param('s', $admission_no);
                        $stmt->execute();

                        $student_result = $stmt->get_result();
                        $student = $student_result->fetch_assoc();

                        $stmt->close();

                        if (!$student) {
                            $errors[] =
                                "Admission No. {$admission_no}: student not found.";
                            continue;
                        }

                        $student_id = (int)$student['id'];
                        $class_id = (int)$student['class_id'];
                        $section_id = (int)$student['section_id'];

                        /* Subject */
                        $stmt = $conn->prepare(
                            "SELECT id
                             FROM subjects
                             WHERE subject_name = ?
                             LIMIT 1"
                        );

                        if (!$stmt) {
                            $errors[] = 'Subject query failed.';
                            continue;
                        }

                        $stmt->bind_param('s', $subject_name);
                        $stmt->execute();

                        $subject_result = $stmt->get_result();
                        $subject = $subject_result->fetch_assoc();

                        $stmt->close();

                        if (!$subject) {
                            $errors[] =
                                "Admission No. {$admission_no}: subject '{$subject_name}' not found.";
                            continue;
                        }

                        $subject_id = (int)$subject['id'];

                        /* Exam */
                        $exam_name = $get('exam_name');

                        if ($exam_name === '') {
                            $errors[] =
                                "Admission No. {$admission_no}: exam name missing.";
                            continue;
                        }

                        $stmt = $conn->prepare(
                            "SELECT id
                             FROM exams
                             WHERE exam_name = ?
                             LIMIT 1"
                        );

                        if (!$stmt) {
                            $errors[] = 'Exam query failed.';
                            continue;
                        }

                        $stmt->bind_param('s', $exam_name);
                        $stmt->execute();

                        $exam_result = $stmt->get_result();
                        $exam = $exam_result->fetch_assoc();

                        $stmt->close();

                        if (!$exam) {
                            $errors[] =
                                "Admission No. {$admission_no}: exam '{$exam_name}' not found.";
                            continue;
                        }

                        $exam_id = (int)$exam['id'];

                        /* Marks */
                        $pa = (float)$get('pa_marks');
                        $se = (float)$get('se_marks');
                        $nb = (float)$get('nb_marks');
                        $written = (float)$get('written_marks');

                        if ($pa < 0 || $pa > 10) {
                            $errors[] =
                                "Admission No. {$admission_no}: PA must be 0-10.";
                            continue;
                        }

                        if ($se < 0 || $se > 5) {
                            $errors[] =
                                "Admission No. {$admission_no}: SE must be 0-5.";
                            continue;
                        }

                        if ($nb < 0 || $nb > 5) {
                            $errors[] =
                                "Admission No. {$admission_no}: NB must be 0-5.";
                            continue;
                        }

                        if ($written < 0 || $written > 80) {
                            $errors[] =
                                "Admission No. {$admission_no}: Written must be 0-80.";
                            continue;
                        }

                        $obtained = $pa + $se + $nb + $written;

                        $remarks = $get('remarks');

                        /* Existing record? */
                        $check = $conn->prepare(
                            "SELECT id
                             FROM marks
                             WHERE student_id = ?
                               AND subject_id = ?
                               AND exam_id = ?
                             LIMIT 1"
                        );

                        if (!$check) {
                            $errors[] = 'Marks check query failed.';
                            continue;
                        }

                        $check->bind_param(
                            'iii',
                            $student_id,
                            $subject_id,
                            $exam_id
                        );

                        $check->execute();

                        $existing_result = $check->get_result();
                        $existing = $existing_result->fetch_assoc();

                        $check->close();

                        if ($existing) {

                            $mark_id = (int)$existing['id'];

                            $update = $conn->prepare(
                                "UPDATE marks
                                 SET class_id = ?,
                                     section_id = ?,
                                     max_marks = 100,
                                     pa_marks = ?,
                                     se_marks = ?,
                                     nb_marks = ?,
                                     written_marks = ?,
                                     obtained_marks = ?,
                                     remarks = ?
                                 WHERE id = ?"
                            );

                            if (!$update) {
                                $errors[] = 'Marks update query failed.';
                                continue;
                            }

                            $update->bind_param(
                                'iidddddsi',
                                $class_id,
                                $section_id,
                                $pa,
                                $se,
                                $nb,
                                $written,
                                $obtained,
                                $remarks,
                                $mark_id
                            );

                            if ($update->execute()) {
                                $imported++;
                            } else {
                                $errors[] =
                                    "Admission No. {$admission_no}: marks update failed.";
                            }

                            $update->close();

                        } else {

                            $insert = $conn->prepare(
                                "INSERT INTO marks
                                (
                                    student_id,
                                    class_id,
                                    section_id,
                                    subject_id,
                                    exam_id,
                                    max_marks,
                                    pa_marks,
                                    se_marks,
                                    nb_marks,
                                    written_marks,
                                    obtained_marks,
                                    remarks
                                )
                                VALUES
                                (?, ?, ?, ?, ?, 100, ?, ?, ?, ?, ?, ?)"
                            );

                            if (!$insert) {
                                $errors[] = 'Marks insert query failed.';
                                continue;
                            }

                            $insert->bind_param(
                                'iiiiiddddds',
                                $student_id,
                                $class_id,
                                $section_id,
                                $subject_id,
                                $exam_id,
                                $pa,
                                $se,
                                $nb,
                                $written,
                                $obtained,
                                $remarks
                            );

                            if ($insert->execute()) {
                                $imported++;
                            } else {
                                $errors[] =
                                    "Admission No. {$admission_no}: marks insert failed.";
                            }

                            $insert->close();
                        }
                    }

                    fclose($handle);

                    $success_message =
                        "CSV import completed. {$imported} record(s) imported/updated.";

                    if ($skipped > 0) {
                        $success_message .=
                            " {$skipped} row(s) skipped.";
                    }

                    if (!empty($errors)) {
                        $error_message =
                            implode('<br>', array_map('h', $errors));
                    }
                }
            }
        }
    }
}

/* ---------------------------------------------------------
   Dropdown Data
--------------------------------------------------------- */
$classes = [];

$result = $conn->query(
    "SELECT id, class_name
     FROM classes
     WHERE status = 1
     ORDER BY class_order, id"
);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
}

$sections = [];

$result = $conn->query(
    "SELECT id, section_name
     FROM sections
     WHERE status = 1
     ORDER BY id"
);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sections[] = $row;
    }
}

$exams = [];

$result = $conn->query(
    "SELECT id, exam_name
     FROM exams
     ORDER BY id DESC"
);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
}

/* ---------------------------------------------------------
   Selected values
--------------------------------------------------------- */
$class_id = isset($_GET['class_id'])
    ? (int)$_GET['class_id']
    : 0;

$section_id = isset($_GET['section_id'])
    ? (int)$_GET['section_id']
    : 0;

$exam_id = isset($_GET['exam_id'])
    ? (int)$_GET['exam_id']
    : 0;

$student_id = isset($_GET['student_id'])
    ? (int)$_GET['student_id']
    : 0;

$session_id = 1;
$observation_term = (isset($_GET['term']) && $_GET['term'] === 'term2') ? 'term2' : 'term1';
$observation_values = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_observations') {
    $post_student_id = (int)($_POST['student_id'] ?? 0);
    $post_session_id = (int)($_POST['session_id'] ?? 1);
    $post_term = ($_POST['term'] ?? '') === 'term2' ? 'term2' : 'term1';
    $posted_observations = isset($_POST['observations']) && is_array($_POST['observations']) ? $_POST['observations'] : array();
    if ($post_student_id <= 0 || empty($posted_observations)) {
        $error_message = 'Select a student and enter at least one co-scholastic value.';
    } else {
        $column = $post_term === 'term2' ? 'term2_value' : 'term1_value';
        $conn->begin_transaction();
        $ok = true;
        $stmt = $conn->prepare("INSERT INTO hpc_observations (student_id, session_id, category, observation, {$column}) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE {$column}=VALUES({$column})");
        foreach ($posted_observations as $category => $items) {
            if (!is_array($items)) continue;
            foreach ($items as $observation => $value) {
                $value = strtoupper(trim((string)$value));
                if ($value !== '' && !in_array($value, array('B', 'PF', 'PG'), true)) { $ok = false; break 2; }
                $stmt->bind_param('iisss', $post_student_id, $post_session_id, $category, $observation, $value);
                if (!$stmt->execute()) { $ok = false; break 2; }
            }
        }
        $stmt->close();
        if ($ok) { $conn->commit(); $success_message = strtoupper($post_term) . ' co-scholastic values saved successfully.'; }
        else { $conn->rollback(); $error_message = 'Unable to save co-scholastic values.'; }
        $student_id = $post_student_id;
        $observation_term = $post_term;
    }
}

if ($student_id > 0) {
    $stmt = $conn->prepare('SELECT category, observation, term1_value, term2_value FROM hpc_observations WHERE student_id=? AND session_id=?');
    $stmt->bind_param('ii', $student_id, $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $observation_values[$row['category']][$row['observation']] = array('term1' => $row['term1_value'], 'term2' => $row['term2_value']);
    }
    $stmt->close();
}

/* ---------------------------------------------------------
   Students
--------------------------------------------------------- */
$students = [];

if ($class_id > 0 && $section_id > 0) {

    $stmt = $conn->prepare(
        "SELECT id, admission_no, student_name
         FROM students
         WHERE class_id = ?
           AND section_id = ?
           AND status = 1
         ORDER BY student_name"
    );

    if ($stmt) {

        $stmt->bind_param(
            'ii',
            $class_id,
            $section_id
        );

        $stmt->execute();

        $student_result = $stmt->get_result();

        while ($row = $student_result->fetch_assoc()) {
            $students[] = $row;
        }

        $stmt->close();
    }
}

/* ---------------------------------------------------------
   Selected Student
--------------------------------------------------------- */
$selected_student = null;

if ($student_id > 0) {

    $stmt = $conn->prepare(
        "SELECT
            s.*,
            c.class_name,
            sec.section_name
         FROM students s
         LEFT JOIN classes c
            ON c.id = s.class_id
         LEFT JOIN sections sec
            ON sec.id = s.section_id
         WHERE s.id = ?
         LIMIT 1"
    );

    if ($stmt) {

        $stmt->bind_param('i', $student_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $selected_student = $result->fetch_assoc();

        $stmt->close();
    }
}

/* ---------------------------------------------------------
   Subjects
--------------------------------------------------------- */
$subjects = [];

if ($class_id > 0) {

    $stmt = $conn->prepare(
        "SELECT DISTINCT
            sub.id,
            sub.subject_name,
            sub.subject_code,
            sub.subject_type
         FROM subjects sub
         INNER JOIN class_subjects cs
            ON cs.subject_id = sub.id
         WHERE cs.class_id = ?
           AND sub.status = 1
         ORDER BY sub.subject_type, sub.subject_name"
    );

    if ($stmt) {

        $stmt->bind_param('i', $class_id);
        $stmt->execute();

        $subject_result = $stmt->get_result();

        while ($row = $subject_result->fetch_assoc()) {
            $subjects[] = $row;
        }

        $stmt->close();
    }
}

/* ---------------------------------------------------------
   Existing marks for selected student/exam
--------------------------------------------------------- */
$existing_marks = [];

if ($student_id > 0 && $exam_id > 0) {

    $stmt = $conn->prepare(
        "SELECT *
         FROM marks
         WHERE student_id = ?
           AND exam_id = ?"
    );

    if ($stmt) {

        $stmt->bind_param(
            'ii',
            $student_id,
            $exam_id
        );

        $stmt->execute();

        $marks_result = $stmt->get_result();

        while ($row = $marks_result->fetch_assoc()) {
            $existing_marks[$row['subject_id']] = $row;
        }

        $stmt->close();
    }
}

/* ---------------------------------------------------------
   Save Portal Marks
--------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'save_marks') {

    $post_student_id = (int)($_POST['student_id'] ?? 0);
    $post_class_id = (int)($_POST['class_id'] ?? 0);
    $post_section_id = (int)($_POST['section_id'] ?? 0);
    $post_exam_id = (int)($_POST['exam_id'] ?? 0);

    if (
        $post_student_id <= 0 ||
        $post_class_id <= 0 ||
        $post_section_id <= 0 ||
        $post_exam_id <= 0
    ) {

        $error_message =
            'Please select Class, Section, Exam and Student.';

    } elseif (!empty($_POST['marks'])) {

        foreach ($_POST['marks'] as $subject_id => $values) {

            $subject_id = (int)$subject_id;

            $pa = isset($values['pa'])
                ? (float)$values['pa']
                : 0;

            $se = isset($values['se'])
                ? (float)$values['se']
                : 0;

            $nb = isset($values['nb'])
                ? (float)$values['nb']
                : 0;

            $written = isset($values['written'])
                ? (float)$values['written']
                : 0;

            $remarks = isset($values['remarks'])
                ? trim($values['remarks'])
                : '';

            if ($pa < 0 || $pa > 10) {
                $pa = 0;
            }

            if ($se < 0 || $se > 5) {
                $se = 0;
            }

            if ($nb < 0 || $nb > 5) {
                $nb = 0;
            }

            if ($written < 0 || $written > 80) {
                $written = 0;
            }

            $obtained =
                $pa +
                $se +
                $nb +
                $written;

            $check = $conn->prepare(
                "SELECT id
                 FROM marks
                 WHERE student_id = ?
                   AND subject_id = ?
                   AND exam_id = ?
                 LIMIT 1"
            );

            if (!$check) {
                continue;
            }

            $check->bind_param(
                'iii',
                $post_student_id,
                $subject_id,
                $post_exam_id
            );

            $check->execute();

            $result = $check->get_result();
            $existing = $result->fetch_assoc();

            $check->close();

            if ($existing) {

                $mark_id = (int)$existing['id'];

                $stmt = $conn->prepare(
                    "UPDATE marks
                     SET class_id = ?,
                         section_id = ?,
                         max_marks = 100,
                         pa_marks = ?,
                         se_marks = ?,
                         nb_marks = ?,
                         written_marks = ?,
                         obtained_marks = ?,
                         remarks = ?
                     WHERE id = ?"
                );

                if ($stmt) {

                    $stmt->bind_param(
                        'iidddddsi',
                        $post_class_id,
                        $post_section_id,
                        $pa,
                        $se,
                        $nb,
                        $written,
                        $obtained,
                        $remarks,
                        $mark_id
                    );

                    $stmt->execute();
                    $stmt->close();
                }

            } else {

                $stmt = $conn->prepare(
                    "INSERT INTO marks
                    (
                        student_id,
                        class_id,
                        section_id,
                        subject_id,
                        exam_id,
                        max_marks,
                        pa_marks,
                        se_marks,
                        nb_marks,
                        written_marks,
                        obtained_marks,
                        remarks
                    )
                    VALUES
                    (?, ?, ?, ?, ?, 100, ?, ?, ?, ?, ?, ?)"
                );

                if ($stmt) {

                    $stmt->bind_param(
                        'iiiiiddddds',
                        $post_student_id,
                        $post_class_id,
                        $post_section_id,
                        $subject_id,
                        $post_exam_id,
                        $pa,
                        $se,
                        $nb,
                        $written,
                        $obtained,
                        $remarks
                    );

                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        $success_message =
            'Scholastic marks saved successfully.';

        $student_id = $post_student_id;
        $class_id = $post_class_id;
        $section_id = $post_section_id;
        $exam_id = $post_exam_id;

        /* Reload marks */
        $existing_marks = [];

        $stmt = $conn->prepare(
            "SELECT *
             FROM marks
             WHERE student_id = ?
               AND exam_id = ?"
        );

        if ($stmt) {

            $stmt->bind_param(
                'ii',
                $student_id,
                $exam_id
            );

            $stmt->execute();

            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $existing_marks[$row['subject_id']] = $row;
            }

            $stmt->close();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>HPC Marks Centre</title>

<link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/app.css">

<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>

body {
    background: #f4f7fb;
    font-family: Arial, Helvetica, sans-serif;
}

.page-wrapper {
    max-width: 1450px;
    margin: 25px auto;
    padding: 0 15px 40px;
}

.main-card {
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 8px 30px rgba(0,0,0,.08);
    overflow: hidden;
}

.page-header {
    padding: 22px 25px;
    background: linear-gradient(135deg,#253b80,#4f46e5);
    color: #fff;
}

.page-header h2 {
    margin: 0;
    font-weight: 700;
}

.page-header p {
    margin: 5px 0 0;
    opacity: .9;
}

.content {
    padding: 25px;
}

.section-card {
    border: 1px solid #e4e8f0;
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 22px;
    background: #fff;
}

.section-title {
    font-weight: 700;
    font-size: 18px;
    margin-bottom: 18px;
    color: #26324a;
}

.form-label {
    font-weight: 600;
}

.student-box {
    background: #eef4ff;
    border: 1px solid #d9e5ff;
    border-radius: 12px;
    padding: 16px;
}

.student-name {
    font-size: 22px;
    font-weight: 700;
}

.student-meta {
    color: #596579;
}

.table-wrap {
    overflow-x: auto;
}

.marks-table {
    min-width: 1000px;
}

.marks-table th {
    background: #f0f3f8;
    white-space: nowrap;
    text-align: center;
    vertical-align: middle;
}

.marks-table td {
    vertical-align: middle;
}

.marks-input {
    width: 75px;
    text-align: center;
}

.total-box {
    font-weight: 700;
    background: #f8fafc;
}

.grade-box {
    font-weight: 700;
    text-align: center;
    min-width: 65px;
}

.co-box {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 16px;
    background: #fafbfc;
    margin-bottom: 12px;
}

.co-title {
    font-weight: 700;
    margin-bottom: 12px;
}

.grade-select {
    min-width: 100px;
}

.action-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-modern {
    border-radius: 10px;
    padding: 10px 18px;
    font-weight: 600;
}

.small-note {
    font-size: 13px;
    color: #6b7280;
}

</style>

</head>

<body>

<div class="page-wrapper">

<div class="main-card">

<div class="page-header">

<h2>
<i class="bi bi-journal-check"></i>
HPC Marks Centre
</h2>

<p>
Teacher Portal • Scholastic Marks • Co-Scholastic • CSV Import
</p>

</div>

<div class="content">

<?php if ($success_message): ?>

<div class="alert alert-success">
<i class="bi bi-check-circle"></i>
<?= $success_message ?>
</div>

<?php endif; ?>

<?php if ($error_message): ?>

<div class="alert alert-danger">
<i class="bi bi-exclamation-triangle"></i>
<?= $error_message ?>
</div>

<?php endif; ?>


<!-- =====================================================
     CSV SECTION
====================================================== -->

<div class="section-card">

<div class="section-title">
<i class="bi bi-file-earmark-spreadsheet"></i>
CSV Marks Import
</div>

<div class="action-bar">

<a href="?download_template=1"
   class="btn btn-success btn-modern">

<i class="bi bi-download"></i>
Download HPC CSV Template

</a>

</div>

<hr>

<form method="post"
      enctype="multipart/form-data">

<input type="hidden"
       name="action"
       value="csv_import">

<div class="row align-items-end">

<div class="col-md-8">

<label class="form-label">
Upload Filled HPC CSV
</label>

<input type="file"
       name="csv_file"
       class="form-control"
       accept=".csv"
       required>

<div class="small-note mt-2">
Fill the downloaded template in Excel and save it as CSV UTF-8 before uploading.
</div>

</div>

<div class="col-md-4 mt-3 mt-md-0">

<button type="submit"
        class="btn btn-primary btn-modern w-100">

<i class="bi bi-cloud-arrow-up"></i>
Upload & Import CSV

</button>

</div>

</div>

</form>

</div>


<!-- =====================================================
     TEACHER PORTAL
====================================================== -->

<div class="section-card">

<div class="section-title">
<i class="bi bi-person-workspace"></i>
Teacher Portal Entry
</div>

<form method="get">

<div class="row g-3">

<div class="col-md-3">

<label class="form-label">
Class
</label>

<select name="class_id"
        id="class_id"
        class="form-select"
        onchange="reloadStudents()"
        required>

<option value="">
Select Class
</option>

<?php foreach ($classes as $class): ?>

<option value="<?= (int)$class['id'] ?>"
    <?= $class_id == $class['id'] ? 'selected' : '' ?>>

<?= h($class['class_name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-md-3">

<label class="form-label">
Section
</label>

<select name="section_id"
        id="section_id"
        class="form-select"
        onchange="reloadStudents()"
        required>

<option value="">
Select Section
</option>

<?php foreach ($sections as $section): ?>

<option value="<?= (int)$section['id'] ?>"
    <?= $section_id == $section['id'] ? 'selected' : '' ?>>

<?= h($section['section_name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-md-3">

<label class="form-label">
Examination
</label>

<select name="exam_id"
        id="exam_id"
        class="form-select"
        onchange="reloadStudents()"
        required>

<option value="">
Select Examination
</option>

<?php foreach ($exams as $exam): ?>

<option value="<?= (int)$exam['id'] ?>"
    <?= $exam_id == $exam['id'] ? 'selected' : '' ?>>

<?= h($exam['exam_name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-md-3">

<label class="form-label">
Student
</label>

<select name="student_id"
        id="student_id"
        class="form-select"
        onchange="loadStudent()"
        required>

<option value="">
Select Student
</option>

<?php foreach ($students as $student): ?>

<option value="<?= (int)$student['id'] ?>"
    <?= $student_id == $student['id'] ? 'selected' : '' ?>>

<?= h($student['admission_no']) ?>
-
<?= h($student['student_name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>

</div>

</form>

</div>


<?php if ($selected_student): ?>

<!-- =====================================================
     STUDENT DETAILS
====================================================== -->

<div class="student-box mb-4">

<div class="student-name">

<?= h($selected_student['student_name']) ?>

</div>

<div class="student-meta mt-2">

<strong>Admission No:</strong>
<?= h($selected_student['admission_no']) ?>

&nbsp;&nbsp; | &nbsp;&nbsp;

<strong>Class:</strong>
<?= h($selected_student['class_name']) ?>

&nbsp;&nbsp; | &nbsp;&nbsp;

<strong>Section:</strong>
<?= h($selected_student['section_name']) ?>

<?php if (!empty($selected_student['father_name'])): ?>

&nbsp;&nbsp; | &nbsp;&nbsp;

<strong>Father:</strong>
<?= h($selected_student['father_name']) ?>

<?php endif; ?>

</div>

</div>


<!-- =====================================================
     SCHOLASTIC MARKS
====================================================== -->

<div class="section-card">

<div class="section-title">

<i class="bi bi-pencil-square"></i>

PART-A — Scholastic Areas

</div>

<div class="small-note mb-3">

Maximum marks:
PA = 10 &nbsp; | &nbsp;
SE = 5 &nbsp; | &nbsp;
NB = 5 &nbsp; | &nbsp;
Written = 80 &nbsp; | &nbsp;
Total = 100

</div>

<form method="post">

<input type="hidden"
       name="action"
       value="save_marks">

<input type="hidden"
       name="student_id"
       value="<?= $student_id ?>">

<input type="hidden"
       name="class_id"
       value="<?= $class_id ?>">

<input type="hidden"
       name="section_id"
       value="<?= $section_id ?>">

<input type="hidden"
       name="exam_id"
       value="<?= $exam_id ?>">


<div class="table-wrap">

<table class="table table-bordered marks-table">

<thead>

<tr>

<th rowspan="2">
Subject
</th>

<th colspan="4">
Marks
</th>

<th rowspan="2">
Total
</th>

<th rowspan="2">
Grade
</th>

<th rowspan="2">
Remarks
</th>

</tr>

<tr>

<th>PA<br>10</th>
<th>SE<br>5</th>
<th>NB<br>5</th>
<th>Written<br>80</th>

</tr>

</thead>

<tbody>

<?php foreach ($subjects as $subject): ?>

<?php

$subject_id = (int)$subject['id'];

$current = $existing_marks[$subject_id] ?? null;

$pa = $current
    ? (float)$current['pa_marks']
    : 0;

$se = $current
    ? (float)$current['se_marks']
    : 0;

$nb = $current
    ? (float)$current['nb_marks']
    : 0;

$written = $current
    ? (float)$current['written_marks']
    : 0;

$total =
    $pa +
    $se +
    $nb +
    $written;

$grade = calculateGrade($total);

$remarks = $current
    ? $current['remarks']
    : '';

?>

<tr>

<td>

<strong>
<?= h($subject['subject_name']) ?>
</strong>

<?php if (!empty($subject['subject_code'])): ?>

<div class="small-note">
<?= h($subject['subject_code']) ?>
</div>

<?php endif; ?>

</td>


<td>

<input type="number"
       name="marks[<?= $subject_id ?>][pa]"
       class="form-control marks-input mark-field"
       min="0"
       max="10"
       step="0.01"
       value="<?= h($pa) ?>"
       data-max="10">

</td>


<td>

<input type="number"
       name="marks[<?= $subject_id ?>][se]"
       class="form-control marks-input mark-field"
       min="0"
       max="5"
       step="0.01"
       value="<?= h($se) ?>"
       data-max="5">

</td>


<td>

<input type="number"
       name="marks[<?= $subject_id ?>][nb]"
       class="form-control marks-input mark-field"
       min="0"
       max="5"
       step="0.01"
       value="<?= h($nb) ?>"
       data-max="5">

</td>


<td>

<input type="number"
       name="marks[<?= $subject_id ?>][written]"
       class="form-control marks-input mark-field"
       min="0"
       max="80"
       step="0.01"
       value="<?= h($written) ?>"
       data-max="80">

</td>


<td>

<input type="text"
       class="form-control total-box subject-total"
       value="<?= number_format($total,2) ?>"
       readonly>

</td>


<td>

<input type="text"
       class="form-control grade-box subject-grade"
       value="<?= h($grade) ?>"
       readonly>

</td>


<td>

<input type="text"
       name="marks[<?= $subject_id ?>][remarks]"
       class="form-control"
       maxlength="255"
       value="<?= h($remarks) ?>">

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>


<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">

<div class="small-note">

Grade scale:
A1, A2, B1, B2, C1, C2, D, E

</div>

<button type="submit"
        class="btn btn-primary btn-modern">

<i class="bi bi-save"></i>
Save Scholastic Marks

</button>

</div>

</form>

</div>


<!-- =====================================================
     CO-SCHOLASTIC
====================================================== -->

<div class="section-card">

<div class="section-title">

<i class="bi bi-stars"></i>

PART-B — Co-Scholastic / Life Skills

</div>

<form method="post" id="observations-form">
<input type="hidden" name="action" value="save_observations">
<input type="hidden" name="student_id" value="<?= (int)$student_id ?>">
<input type="hidden" name="session_id" value="<?= (int)$session_id ?>">
<div class="row mb-3">
<div class="col-md-4">
<label class="form-label">Term</label>
<select name="term" id="observation-term" class="form-select">
<option value="term1" <?= $observation_term === 'term1' ? 'selected' : '' ?>>Term-1</option>
<option value="term2" <?= $observation_term === 'term2' ? 'selected' : '' ?>>Term-2</option>
</select>
</div>
</div>

<div class="alert alert-info">

<strong>Teacher Entry:</strong>

Use the CBSE-style values:

<strong>B</strong> = Beginning
&nbsp; | &nbsp;

<strong>PF</strong> = Progressing Further
&nbsp; | &nbsp;

<strong>PG</strong> = Progressing

</div>


<!-- Life Skills -->

<div class="co-box">

<div class="co-title">
Life Skills
</div>

<div class="row g-3">

<?php

$life_skills = [
    'Critical Thinking',
    'Problem Solving',
    'Communication'
];

foreach ($life_skills as $skill):

$key = strtolower(
    str_replace(' ', '_', $skill)
);

?>

<div class="col-md-4">

<label class="form-label">
<?= h($skill) ?>
</label>

<div class="row">

<div class="col-6">

<select class="form-select grade-select">

<option value="">Term-I</option>
<option value="B">B</option>
<option value="PF">PF</option>
<option value="PG">PG</option>

</select>

</div>

<div class="col-6">

<select class="form-select grade-select">

<option value="">Term-II</option>
<option value="B">B</option>
<option value="PF">PF</option>
<option value="PG">PG</option>

</select>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

</div>


<!-- SEL -->

<div class="co-box">

<div class="co-title">
Social Emotional & Ethical Learning
</div>

<div class="row g-3">

<?php

$sel = [
    'Respect for Others',
    'Responsibility',
    'Honesty',
    'Empathy'
];

foreach ($sel as $skill):

?>

<div class="col-md-3">

<label class="form-label">
<?= h($skill) ?>
</label>

<div class="row">

<div class="col-6">

<select class="form-select grade-select">

<option value="">I</option>
<option value="B">B</option>
<option value="PF">PF</option>
<option value="PG">PG</option>

</select>

</div>

<div class="col-6">

<select class="form-select grade-select">

<option value="">II</option>
<option value="B">B</option>
<option value="PF">PF</option>
<option value="PG">PG</option>

</select>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

</div>


<!-- Positive Learning Habits -->

<div class="co-box">

<div class="co-title">
Positive Learning Habits
</div>

<div class="row g-3">

<?php

$habits = [
    'Work Habits',
    'Time Management',
    'Organization',
    'Initiative',
    'Perseverance'
];

foreach ($habits as $habit):

?>

<div class="col-md-4">

<label class="form-label">
<?= h($habit) ?>
</label>

<div class="row">

<div class="col-6">

<select class="form-select grade-select">

<option value="">I</option>
<option value="B">B</option>
<option value="PF">PF</option>
<option value="PG">PG</option>

</select>

</div>

<div class="col-6">

<select class="form-select grade-select">

<option value="">II</option>
<option value="B">B</option>
<option value="PF">PF</option>
<option value="PG">PG</option>

</select>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

</div>


<!-- Co-Curricular -->

<div class="co-box">

<div class="co-title">
Co-Curricular Activities
</div>

<div class="row g-3">

<div class="col-md-6">

<label class="form-label">
Leadership Role
</label>

<div class="row">

<div class="col-6">

<select class="form-select grade-select">

<option value="">Term-I</option>
<option value="B">B</option>
<option value="PF">PF</option>
<option value="PG">PG</option>

</select>

</div>

<div class="col-6">

<select class="form-select grade-select">

<option value="">Term-II</option>
<option value="B">B</option>
<option value="PF">PF</option>
<option value="PG">PG</option>

</select>

</div>

</div>

</div>


<div class="col-md-6">

<label class="form-label">
Team Work Collaboration
</label>

<div class="row">

<div class="col-6">

<select class="form-select grade-select">

<option value="">Term-I</option>
<option value="B">B</option>
<option value="PF">PF</option>
<option value="PG">PG</option>

</select>

</div>

<div class="col-6">

<select class="form-select grade-select">

<option value="">Term-II</option>
<option value="B">B</option>
<option value="PF">PF</option>
<option value="PG">PG</option>

</select>

</div>

</div>

</div>

</div>

</div>


<div class="small-note">

Select Term-1 or Term-2, enter the values, and press Save. Each term is stored separately.

</div>

<button type="submit" class="btn btn-primary mt-3"><i class="bi bi-save"></i> Save Selected Term</button>
</form>

</div>


<?php endif; ?>

</div>

</div>

</div>

<script>
(function () {
    var form = document.getElementById('observations-form');
    if (!form) return;
    var saved = <?= json_encode($observation_values, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    form.querySelectorAll('.co-box').forEach(function (box) {
        var title = box.querySelector('.co-title');
        var category = title ? title.textContent.trim().replace(/\s+/g, ' ') : '';
        box.querySelectorAll('.form-label').forEach(function (label) {
            var selects = label.parentNode.querySelectorAll('select');
            if (!selects.length || label.textContent.trim() === 'Term') return;
            var observation = label.textContent.trim().replace(/\s+/g, ' ');
            selects.forEach(function (select, index) {
                var term = index === 0 ? 'term1' : 'term2';
                select.name = 'observations[' + category + '][' + observation + ']';
                select.setAttribute('data-term', term);
                var current = saved[category] && saved[category][observation] ? saved[category][observation][term] : '';
                if (current) select.value = current;
            });
        });
    });
    function showTerm() {
        var term = document.getElementById('observation-term').value;
        form.querySelectorAll('select[data-term]').forEach(function (select) {
            var visible = select.getAttribute('data-term') === term;
            select.closest('.col-6').style.display = visible ? '' : 'none';
            select.disabled = !visible;
        });
    }
    document.getElementById('observation-term').addEventListener('change', showTerm);
    showTerm();
}());
</script>

<script>

/* ---------------------------------------------------------
   Reload page after Class / Section / Exam change
--------------------------------------------------------- */
function reloadStudents() {

    const classId =
        document.getElementById('class_id').value;

    const sectionId =
        document.getElementById('section_id').value;

    const examId =
        document.getElementById('exam_id').value;

    let url = 'import_marks.php?';

    const params = [];

    if (classId) {
        params.push(
            'class_id=' +
            encodeURIComponent(classId)
        );
    }

    if (sectionId) {
        params.push(
            'section_id=' +
            encodeURIComponent(sectionId)
        );
    }

    if (examId) {
        params.push(
            'exam_id=' +
            encodeURIComponent(examId)
        );
    }

    window.location.href =
        url + params.join('&');
}


/* ---------------------------------------------------------
   Load selected student
--------------------------------------------------------- */
function loadStudent() {

    const studentId =
        document.getElementById('student_id').value;

    const classId =
        document.getElementById('class_id').value;

    const sectionId =
        document.getElementById('section_id').value;

    const examId =
        document.getElementById('exam_id').value;

    let url = 'import_marks.php?';

    const params = [];

    if (classId) {
        params.push('class_id=' + classId);
    }

    if (sectionId) {
        params.push('section_id=' + sectionId);
    }

    if (examId) {
        params.push('exam_id=' + examId);
    }

    if (studentId) {
        params.push('student_id=' + studentId);
    }

    window.location.href =
        url + params.join('&');
}


/* ---------------------------------------------------------
   Calculate totals and grades
--------------------------------------------------------- */
function gradeFromMarks(total) {

    total = parseFloat(total) || 0;

    if (total >= 91) return 'A1';
    if (total >= 81) return 'A2';
    if (total >= 71) return 'B1';
    if (total >= 61) return 'B2';
    if (total >= 51) return 'C1';
    if (total >= 41) return 'C2';
    if (total >= 33) return 'D';

    return 'E';
}


function updateRow(row) {

    const fields =
        row.querySelectorAll('.mark-field');

    let total = 0;

    fields.forEach(function(field) {

        let value =
            parseFloat(field.value) || 0;

        const max =
            parseFloat(field.dataset.max);

        if (value < 0) {
            value = 0;
            field.value = 0;
        }

        if (value > max) {
            value = max;
            field.value = max;
        }

        total += value;
    });

    const totalBox =
        row.querySelector('.subject-total');

    const gradeBox =
        row.querySelector('.subject-grade');

    if (totalBox) {
        totalBox.value =
            total.toFixed(2);
    }

    if (gradeBox) {
        gradeBox.value =
            gradeFromMarks(total);
    }
}


document.querySelectorAll('.marks-table tbody tr')
.forEach(function(row) {

    row.querySelectorAll('.mark-field')
    .forEach(function(field) {

        field.addEventListener(
            'input',
            function() {
                updateRow(row);
            }
        );

    });

    updateRow(row);

});

</script>

</body>
</html>