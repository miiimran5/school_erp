<?php
/**
 * == ==========================================================
 * SCHOOL ERP - T-037
 * CBSE HOLISTIC PROGRESS CARD (HPC)
 * FINAL generate.php
 * ============================================================
 *
 * File:
 * C:\xampp\htdocs\school_erp\modules\results\generate.php
 *
 * URL Example:
 * http://localhost/school_erp/modules/results/generate.php
 * ?class_id=7&section_id=13&exam_id=4
 *
 * DATABASE:
 * school_erp
 *
 * Connection:
 * ../../config/db.php
 *
 * ============================================================
 */

session_start();

require_once __DIR__ . '/../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
| We created this file:
|
| C:\xampp\htdocs\school_erp\config\db.php
|
*/

require_once __DIR__ . '/../../config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not available.");
}

if ($conn->connect_errno) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

$conn->set_charset("utf8mb4");


/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$class_id   = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$exam_id    = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$result_scope = isset($_GET['result_scope']) && $_GET['result_scope'] === 'both'
    ? 'both'
    : 'term1';

if ($class_id <= 0 || $section_id <= 0 || $exam_id <= 0) {
    die("Invalid Class, Section or Examination.");
}


/*
|--------------------------------------------------------------------------
| PRINT SETTINGS
|--------------------------------------------------------------------------
*/

$left_margin = isset($_GET['left_margin'])
    ? (float)$_GET['left_margin']
    : 0.75;

$right_margin = isset($_GET['right_margin'])
    ? (float)$_GET['right_margin']
    : 0.75;

$top_margin = isset($_GET['top_margin'])
    ? (float)$_GET['top_margin']
    : 0.50;

$bottom_margin = isset($_GET['bottom_margin'])
    ? (float)$_GET['bottom_margin']
    : 0.50;

$font_size = isset($_GET['font_size'])
    ? (float)$_GET['font_size']
    : 8;

$cell_padding = isset($_GET['cell_padding'])
    ? (float)$_GET['cell_padding']
    : 1.5;


/*
|--------------------------------------------------------------------------
| LIMIT VALUES
|--------------------------------------------------------------------------
*/

$left_margin   = max(0.30, min(2.50, $left_margin));
$right_margin  = max(0.30, min(2.50, $right_margin));
$top_margin    = max(0.30, min(2.50, $top_margin));
$bottom_margin = max(0.30, min(2.50, $bottom_margin));
$font_size     = max(6, min(12, $font_size));
$cell_padding  = max(0, min(5, $cell_padding));


/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function cleanNumber($value)
{
    if ($value === null || $value === '') {
        return '';
    }

    $value = (float)$value;

    if (floor($value) == $value) {
        return number_format($value, 0);
    }

    return number_format($value, 2);
}


function gradeFromPercentage($percentage)
{
    $percentage = (float)$percentage;

    if ($percentage >= 91) {
        return 'A1';
    }

    if ($percentage >= 81) {
        return 'A2';
    }

    if ($percentage >= 71) {
        return 'B1';
    }

    if ($percentage >= 61) {
        return 'B2';
    }

    if ($percentage >= 51) {
        return 'C1';
    }

    if ($percentage >= 41) {
        return 'C2';
    }

    if ($percentage >= 33) {
        return 'D';
    }

    return 'E';
}


function resultStatus($percentage)
{
    return ((float)$percentage >= 33)
        ? 'PASS'
        : 'FAIL';
}


function tableExists($conn, $table)
{
    $table = $conn->real_escape_string($table);

    $sql = "
        SELECT COUNT(*) AS cnt
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        AND table_name = '$table'
    ";

    $result = $conn->query($sql);

    if (!$result) {
        return false;
    }

    $row = $result->fetch_assoc();

    return ((int)$row['cnt'] > 0);
}


function columnExists($conn, $table, $column)
{
    $table  = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);

    $sql = "
        SELECT COUNT(*) AS cnt
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
        AND table_name = '$table'
        AND column_name = '$column'
    ";

    $result = $conn->query($sql);

    if (!$result) {
        return false;
    }

    $row = $result->fetch_assoc();

    return ((int)$row['cnt'] > 0);
}


/*
|--------------------------------------------------------------------------
| GET CLASS
|--------------------------------------------------------------------------
*/

$class_name = '';
$class_order = 0;

$stmt = $conn->prepare("
    SELECT class_name, class_order
    FROM classes
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Class query error: " . h($conn->error));
}

$stmt->bind_param("i", $class_id);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $class_name = $row['class_name'];
    $class_order = (int)$row['class_order'];
}

$stmt->close();


if ($class_name === '') {
    die("Class not found.");
}

$report_components = array();
if ($class_order >= 14) {
    $report_components = array(
        'practical_marks' => array('label' => 'Practical', 'max' => null),
        'theory_marks' => array('label' => 'Theory', 'max' => null)
    );
} elseif ($class_order >= 9) {
    $report_components = array(
        'pa_marks' => array('label' => 'P.A.', 'max' => 5),
        'se_marks' => array('label' => 'S.E.A.', 'max' => 5),
        'nb_marks' => array('label' => 'N.B.', 'max' => 5),
        'multiple_assessment_marks' => array('label' => 'Multiple Asst.', 'max' => 5),
        'written_marks' => array('label' => 'Written', 'max' => 80)
    );
} elseif ($class_order >= 4) {
    $report_components = array(
        'pa_marks' => array('label' => 'P.A.', 'max' => 5),
        'se_marks' => array('label' => 'S.E.A.', 'max' => 10),
        'nb_marks' => array('label' => 'N.B.', 'max' => 5),
        'written_marks' => array('label' => 'Written', 'max' => 80)
    );
} else {
    $report_components = array(
        'pa_marks' => array('label' => 'P.A.', 'max' => 10),
        'se_marks' => array('label' => 'S.E.', 'max' => 5),
        'nb_marks' => array('label' => 'N.B.', 'max' => 5),
        'written_marks' => array('label' => 'Written', 'max' => 80)
    );
}
$report_component_column_count = $class_order >= 14
    ? (count($report_components) * 2 + 3)
    : (count($report_components) + 1);


/*
|--------------------------------------------------------------------------
| GET SECTION
|--------------------------------------------------------------------------
*/

$section_name = '';

$stmt = $conn->prepare("
    SELECT section_name
    FROM sections
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Section query error: " . h($conn->error));
}

$stmt->bind_param("i", $section_id);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $section_name = $row['section_name'];
}

$stmt->close();


if ($section_name === '') {
    die("Section not found.");
}


/*
|--------------------------------------------------------------------------
| GET EXAM
|--------------------------------------------------------------------------
| IMPORTANT:
| We do NOT use et.exam_type.
| Your exams table contains exam_type_id.
|--------------------------------------------------------------------------
*/

$exam = null;

$stmt = $conn->prepare("
    SELECT
        id,
        exam_name,
        exam_type_id,
        session_id,
        start_date,
        end_date,
        status
    FROM exams
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Exam query error: " . h($conn->error));
}

$stmt->bind_param("i", $exam_id);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $exam = $row;
}

$stmt->close();


if (!$exam) {
    die("Examination not found.");
}

$exam_name = $exam['exam_name'];


/*
|--------------------------------------------------------------------------
| GET ACADEMIC SESSION
|--------------------------------------------------------------------------
*/

$session_name = '';

if (!empty($exam['session_id'])) {

    $stmt = $conn->prepare("
        SELECT session_name
        FROM academic_sessions
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $exam['session_id']
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $session_name = $row['session_name'];
        }

        $stmt->close();
    }
}


if ($session_name === '') {
    $session_name = '2025-26';
}


/*
|--------------------------------------------------------------------------
| DETERMINE CURRENT TERM
|--------------------------------------------------------------------------
*/

$exam_lower = strtolower($exam_name);

$current_term = 'TERM-I';

if (
    strpos($exam_lower, 'term ii') !== false ||
    strpos($exam_lower, 'term-ii') !== false ||
    strpos($exam_lower, 'term 2') !== false ||
    strpos($exam_lower, 'term-2') !== false ||
    strpos($exam_lower, 'annual') !== false ||
    strpos($exam_lower, 'final') !== false
) {
    $current_term = 'TERM-II';
}

$show_term2 = ($result_scope === 'both');


/*
|--------------------------------------------------------------------------
| CHECK HPC COMPONENT COLUMNS
|--------------------------------------------------------------------------
|
| Current marks table supplied by you contains:
|
| id
| student_id
| class_id
| section_id
| subject_id
| exam_id
| max_marks
| obtained_marks
| remarks
|
| If PA/SE/NB/Written columns are later added, this code
| automatically detects them.
|--------------------------------------------------------------------------
*/

$has_pa = columnExists(
    $conn,
    'marks',
    'pa_marks'
);

$has_se = columnExists(
    $conn,
    'marks',
    'se_marks'
);

$has_nb = columnExists(
    $conn,
    'marks',
    'nb_marks'
);

$has_written = columnExists(
    $conn,
    'marks',
    'written_marks'
);

$has_multiple_assessment = columnExists(
    $conn,
    'marks',
    'multiple_assessment_marks'
);

$has_practical = columnExists(
    $conn,
    'marks',
    'practical_marks'
);

$has_practical_max = columnExists(
    $conn,
    'marks',
    'practical_max_marks'
);

$has_theory = columnExists(
    $conn,
    'marks',
    'theory_marks'
);

$has_theory_max = columnExists(
    $conn,
    'marks',
    'theory_max_marks'
);

$has_grade = columnExists(
    $conn,
    'marks',
    'grade'
);


/*
|--------------------------------------------------------------------------
| MARK FIELD SQL
|--------------------------------------------------------------------------
*/

$pa_sql = $has_pa
    ? "m.pa_marks"
    : "NULL";

$se_sql = $has_se
    ? "m.se_marks"
    : "NULL";

$nb_sql = $has_nb
    ? "m.nb_marks"
    : "NULL";

$written_sql = $has_written
    ? "m.written_marks"
    : "NULL";

$multiple_assessment_sql = $has_multiple_assessment
    ? "m.multiple_assessment_marks"
    : "NULL";

$practical_sql = $has_practical
    ? "m.practical_marks"
    : "NULL";

$practical_max_sql = $has_practical_max
    ? "m.practical_max_marks"
    : "NULL";

$theory_sql = $has_theory
    ? "m.theory_marks"
    : "NULL";

$theory_max_sql = $has_theory_max
    ? "m.theory_max_marks"
    : "NULL";

$grade_sql = $has_grade
    ? "m.grade"
    : "NULL";


/*
|--------------------------------------------------------------------------
| GET STUDENTS
|--------------------------------------------------------------------------
*/

$students = [];

$stmt = $conn->prepare("
    SELECT
        id,
        admission_no,
        admission_date,
        class_id,
        section_id,
        house_id,
        roll_no,
        student_name,
        gender,
        dob,
        blood_group,
        category,
        father_name,
        father_occupation,
        father_mobile,
        mother_name,
        mother_occupation,
        mother_mobile,
        guardian_name,
        guardian_mobile,
        alternate_mobile,
        address,
        city,
        district,
        state,
        pincode,
        house,
        transport,
        game,
        concession,
        medical_information,
        photo,
        status
    FROM students
    WHERE class_id = ?
    AND section_id = ?
    AND status = 1
    ORDER BY
        CASE
            WHEN roll_no IS NULL OR roll_no = ''
            THEN 999999
            ELSE CAST(roll_no AS UNSIGNED)
        END,
        student_name
");

if (!$stmt) {
    die("Student query error: " . h($conn->error));
}

$stmt->bind_param(
    "ii",
    $class_id,
    $section_id
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

$stmt->close();


$subjects = [];

$stmt = $conn->prepare("
    SELECT
        s.id,
        s.subject_name,
        s.subject_code,
        s.subject_type,
        cs.role AS class_subject_role
    FROM subjects s
    INNER JOIN class_subjects cs
        ON cs.subject_id = s.id
       AND cs.class_id = ?
       AND cs.status = 1
    WHERE s.status = 1
    ORDER BY cs.sort_order, s.id
");

if (!$stmt) {
    die("Subject query error: " . h($conn->error));
}
$stmt->bind_param('i', $class_id);
if (!$stmt->execute()) {
    die("Subject query error: " . h($stmt->error));
}
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
$stmt->close();

/* Apply the configured class-group list whether or not marks already exist. */
$report_group = $class_order <= 3 ? 'nursery-ukg' : ($class_order <= 6 ? 'class-1-3' : ($class_order <= 8 ? 'class-4-5' : ($class_order <= 11 ? 'class-6-8' : ($class_order <= 13 ? 'class-9-10' : 'class-11-12'))));
$configured_subjects = array();
$group_stmt = $conn->prepare('SELECT subject_id FROM report_card_group_subjects WHERE group_key=? AND status=1 ORDER BY display_order');
if ($group_stmt) {
    $group_stmt->bind_param('s', $report_group);
    if ($group_stmt->execute()) {
        $group_result = $group_stmt->get_result();
        while ($group_row = $group_result->fetch_assoc()) {
            $configured_subjects[(int)$group_row['subject_id']] = true;
        }
    }
    $group_stmt->close();
}
if (!empty($configured_subjects)) {
    $subjects = array_values(array_filter($subjects, function ($subject) use ($configured_subjects) {
        return isset($configured_subjects[(int)$subject['id']]);
    }));
}


/*
|--------------------------------------------------------------------------
| SEPARATE SCHOLASTIC / OTHER SUBJECTS
|--------------------------------------------------------------------------
*/

$scholasticSubjects = [];
$otherSubjects = [];

foreach ($subjects as $subject) {

    $role = trim((string)($subject['class_subject_role'] ?? ''));
    $type = strtolower(
        trim(
            (string)$subject['subject_type']
        )
    );

    if ($role === 'Main' || ($role === '' && ($type === '' || $type === 'scholastic'))) {

        $scholasticSubjects[] = $subject;

    } else {

        $otherSubjects[] = $subject;
    }
}


/*
|--------------------------------------------------------------------------
| GET MARKS
|--------------------------------------------------------------------------
*/

$studentMarks = [];
$has_grade_only_marks = false;
$has_numeric_marks = false;

$stmt = $conn->prepare("
    SELECT
        m.id,
        m.student_id,
        m.subject_id,
        m.class_id,
        m.section_id,
        m.exam_id,
        m.max_marks,
        m.obtained_marks,
        m.remarks,
        $grade_sql AS stored_grade,
        $pa_sql AS pa_marks,
        $se_sql AS se_marks,
        $nb_sql AS nb_marks,
        $written_sql AS written_marks,
        $multiple_assessment_sql AS multiple_assessment_marks,
        $practical_sql AS practical_marks,
        $practical_max_sql AS practical_max_marks,
        $theory_sql AS theory_marks,
        $theory_max_sql AS theory_max_marks
    FROM marks m
    WHERE m.class_id = ?
    AND m.section_id = ?
    AND m.exam_id = ?
");

if (!$stmt) {
    die("Marks query error: " . h($conn->error));
}

$stmt->bind_param(
    "iii",
    $class_id,
    $section_id,
    $exam_id
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $student_id = (int)$row['student_id'];
    $subject_id = (int)$row['subject_id'];
    if (trim((string)($row['stored_grade'] ?? '')) !== '' && (float)$row['max_marks'] <= 0) {
        $has_grade_only_marks = true;
    } elseif ((float)$row['max_marks'] > 0 || (float)$row['obtained_marks'] > 0) {
        $has_numeric_marks = true;
    }

    if (!isset($studentMarks[$student_id])) {
        $studentMarks[$student_id] = [];
    }

    $studentMarks[$student_id][$subject_id] = $row;
}

$stmt->close();
$grade_only_mode = $class_order <= 8 && $has_grade_only_marks && !$has_numeric_marks;


/*
|--------------------------------------------------------------------------
| HPC OBSERVATION ITEMS
|--------------------------------------------------------------------------
*/

$cognitiveSkills = [
    'Critical Thinking',
    'Problem Solving',
    'Decision Making'
];

$socialSkills = [
    'Civic Responsibility',
    'Empathy',
    'Integrity'
];

$aestheticDevelopment = [
    'Awareness',
    'Creativity',
    'Participation',
    'Expression'
];

$selfManagementSkills = [
    'Coping with Stress',
    'Coping with Emotions',
    'Self-Care and Hygiene'
];


/*
|--------------------------------------------------------------------------
| DEFAULT OBSERVATION
|--------------------------------------------------------------------------
|
| For now these display PF until the teacher-upload/import
| observation storage is connected.
|
*/

$default_observation = '';


/*
|--------------------------------------------------------------------------
| HTML START
|--------------------------------------------------------------------------
*/

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>
HOLISTIC PROGRESS CARD -
<?= h($session_name); ?>
</title>

<meta
    name="viewport"
    content="width=device-width, initial-scale=1"
>

<link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/app.css">

<style>

/* ============================================================
   PAGE
   ============================================================ */

@page {

    size: A4 portrait;

    margin:
        <?= $top_margin; ?>cm
        <?= $right_margin; ?>cm
        <?= $bottom_margin; ?>cm
        <?= $left_margin; ?>cm;
}


/* ============================================================
   GLOBAL
   ============================================================ */

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
}

body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    font-size:
        <?= $font_size; ?>pt;

    color: #000;

    background: #e8e8e8;
}


/* ============================================================
   PRINT SETTINGS
   ============================================================ */

.print-settings {

    width: 100%;

    max-width: 1100px;

    margin: 10px auto;

    padding: 10px;

    background: #fff;

    border: 1px solid #aaa;
}

.print-settings label {

    display: inline-block;

    margin-right: 10px;

    margin-bottom: 5px;
}

.print-settings input {

    width: 65px;

    padding: 3px;
}

.print-settings button {

    padding: 5px 12px;

    cursor: pointer;
}


/* ============================================================
   PAGE CONTAINER
   ============================================================ */

.page {

    width: 100%;

    background: #fff;

    margin: 10px auto;

    padding: 0;

    page-break-after: always;
}


/* ============================================================
   HPC CARD
   ============================================================ */

.hpc-card {

    width: 100%;

    border: 1px solid #000;

    padding: 3px;

    background: #fff;
}


/* ============================================================
   HEADER
   ============================================================ */

.school-header {

    text-align: center;

    font-weight: bold;

    line-height: 1.05;

    margin-bottom: 2px;
}

.school-name {

    font-size:
        <?= $font_size + 3; ?>pt;
}

.school-city {

    font-size:
        <?= $font_size + 1; ?>pt;
}

.hpc-title {

    font-size:
        <?= $font_size + 2; ?>pt;

    font-weight: bold;

    text-align: center;

    margin-top: 2px;
}

.session {

    text-align: center;

    font-weight: bold;

    margin-bottom: 3px;
}


/* ============================================================
   TABLES
   ============================================================ */

table {

    width: 100%;

    border-collapse: collapse;

    table-layout: fixed;

    margin: 0;
}

td,
th {

    border: 1px solid #000;

    padding:
        <?= $cell_padding; ?>px;

    vertical-align: middle;

    line-height: 1.05;
}

th {

    text-align: center;

    font-weight: bold;
}


/* ============================================================
   STUDENT DETAILS
   ============================================================ */

.student-info td {

    width: 25%;

    text-align: left;
}

.label {

    font-weight: bold;
}


/* ============================================================
   HEIGHT / WEIGHT
   ============================================================ */

.term-info td {

    width: 50%;

    line-height: 1.25;
}


/* ============================================================
   SECTION TITLE
   ============================================================ */

.section-title {

    border: 1px solid #000;

    text-align: center;

    font-weight: bold;

    padding: 2px;

    margin-top: 3px;

    line-height: 1.05;
}

.sub-title {

    text-align: center;

    font-weight: bold;

    padding: 2px;

    line-height: 1.05;
}


/* ============================================================
   SCHOLASTIC TABLE
   ============================================================ */

.scholastic-table th,
.scholastic-table td {

    text-align: center;

    font-size:
        <?= max(6, $font_size - 0.4); ?>pt;
}

.scholastic-table .subject {

    text-align: left;

    font-weight: bold;

    width: 17%;
}

.scholastic-table .term-column {

    width: 8.3%;
}

.subject-code {

    font-size:
        <?= max(5.5, $font_size - 1); ?>pt;

    font-weight: normal;
}


/* ============================================================
   SMALL TABLES
   ============================================================ */

.small-table td,
.small-table th {

    text-align: center;

    font-size:
        <?= max(6, $font_size - 0.3); ?>pt;
}

.small-table .left {

    text-align: left;
}


/* ============================================================
   OBSERVATION TABLE
   ============================================================ */

.observation-table td:first-child {

    width: 55%;

    text-align: left;

    font-weight: bold;
}

.observation-table td:nth-child(2),
.observation-table td:nth-child(3) {

    width: 45%;

    text-align: center;
}

.observation-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 7px;
    margin-top: 7px;
}

.observation-panel .section-title {
    margin-top: 0;
}

.observation-panel .small-table {
    margin-top: 4px;
}

@media screen and (max-width: 720px) {
    .observation-grid {
        grid-template-columns: 1fr;
    }
}

@media print {
    .observation-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}


/* ============================================================
   SIGNATURES
   ============================================================ */

.signature-table {

    margin-top: 7px;
}

.signature-table td {

    width: 33.33%;

    text-align: center;

    padding-top: 18px;

    border: none;
}


/* ============================================================
   RESULT
   ============================================================ */

.pass {

    font-weight: bold;
}

.fail {

    font-weight: bold;
}


/* ============================================================
   NO STUDENT
   ============================================================ */

.no-student {

    width: 90%;

    margin: 30px auto;

    padding: 25px;

    background: #fff;

    border: 1px solid #aaa;

    text-align: center;
}


/* ============================================================
   PRINT
   ============================================================ */

@media print {

    body {

        background: #fff;

        -webkit-print-color-adjust: exact;

        print-color-adjust: exact;
    }

    .no-print {

        display: none !important;
    }

    .page {

        margin: 0;

        width: 100%;

        page-break-after: always;
    }

    .hpc-card {

        border: 1px solid #000;
    }

    table {

        page-break-inside: avoid;
    }

    tr {

        page-break-inside: avoid;
    }
}

</style>

</head>


<body>


<!-- ==========================================================
     PRINT SETTINGS
     ========================================================== -->

<div class="print-settings no-print">

<form method="get">

<input
    type="hidden"
    name="class_id"
    value="<?= $class_id; ?>"
>

<input
    type="hidden"
    name="section_id"
    value="<?= $section_id; ?>"
>

<input
    type="hidden"
    name="exam_id"
    value="<?= $exam_id; ?>"
>

<input
    type="hidden"
    name="result_scope"
    value="<?= h($result_scope); ?>"
>

<strong>
HPC PRINT SETTINGS
</strong>

&nbsp;&nbsp;


<label>
Left:
<input
    type="number"
    name="left_margin"
    step="0.05"
    min="0.30"
    max="2.50"
    value="<?= h($left_margin); ?>"
>
cm
</label>


<label>
Right:
<input
    type="number"
    name="right_margin"
    step="0.05"
    min="0.30"
    max="2.50"
    value="<?= h($right_margin); ?>"
>
cm
</label>


<label>
Top:
<input
    type="number"
    name="top_margin"
    step="0.05"
    min="0.30"
    max="2.50"
    value="<?= h($top_margin); ?>"
>
cm
</label>


<label>
Bottom:
<input
    type="number"
    name="bottom_margin"
    step="0.05"
    min="0.30"
    max="2.50"
    value="<?= h($bottom_margin); ?>"
>
cm
</label>


<label>
Font:
<input
    type="number"
    name="font_size"
    step="0.5"
    min="6"
    max="12"
    value="<?= h($font_size); ?>"
>
pt
</label>


<label>
Cell:
<input
    type="number"
    name="cell_padding"
    step="0.5"
    min="0"
    max="5"
    value="<?= h($cell_padding); ?>"
>
px
</label>


<button type="submit">
Apply Settings
</button>


<button
    type="button"
    onclick="window.print()"
>
Print HPC
</button>

</form>

</div>


<?php if (count($students) === 0): ?>

<div class="no-student no-print">

<h2>
No Students Found
</h2>

<p>
No active students were found for:
</p>

<p>

<strong>
Class:
</strong>

<?= h($class_name); ?>

<br>

<strong>
Section:
</strong>

<?= h($section_name); ?>

<br>

<strong>
Examination:
</strong>

<?= h($exam_name); ?>

</p>

<a
    href="<?= htmlspecialchars(
        '/school_erp/modules/students/add.php',
        ENT_QUOTES,
        'UTF-8'
    ); ?>"
>
Add First Student
</a>

</div>

<?php endif; ?>


<?php foreach ($students as $student): ?>


<?php

/*
|--------------------------------------------------------------------------
| STUDENT MARKS
|--------------------------------------------------------------------------
*/

$student_id = (int)$student['id'];

$marksForStudent =
    isset($studentMarks[$student_id])
    ? $studentMarks[$student_id]
    : [];


/*
|--------------------------------------------------------------------------
| TOTALS
|--------------------------------------------------------------------------
*/

$term_total = 0;

$term_max = 0;

$subject_rows = [];


foreach ($scholasticSubjects as $subject) {

    $subject_id = (int)$subject['id'];

    $mark =
        isset($marksForStudent[$subject_id])
        ? $marksForStudent[$subject_id]
        : null;


    /*
    |--------------------------------------------------------------------------
    | DEFAULTS
    |--------------------------------------------------------------------------
    */

    $pa = null;
    $se = null;
    $nb = null;
    $written = null;
    $multiple_assessment = null;
    $practical = null;
    $practical_max = null;
    $theory = null;
    $theory_max = null;

    $obtained = 0;

    $max_marks = 100;

    $stored_grade = '';
    $remarks = '';


    /*
    |--------------------------------------------------------------------------
    | READ MARK
    |--------------------------------------------------------------------------
    */

    if ($mark) {

        if ($mark['max_marks'] !== null) {
            $max_marks =
                (float)$mark['max_marks'];
        }

        $obtained =
            (float)$mark['obtained_marks'];

        $remarks =
            isset($mark['remarks'])
            ? trim((string)$mark['remarks'])
            : '';

        $stored_grade =
            trim((string)($mark['stored_grade'] ?? ''));


        if ($mark['pa_marks'] !== null) {
            $pa =
                (float)$mark['pa_marks'];
        }

        if ($mark['se_marks'] !== null) {
            $se =
                (float)$mark['se_marks'];
        }

        if ($mark['nb_marks'] !== null) {
            $nb =
                (float)$mark['nb_marks'];
        }

        if ($mark['written_marks'] !== null) {
            $written =
                (float)$mark['written_marks'];
        }

        if ($mark['multiple_assessment_marks'] !== null) {
            $multiple_assessment =
                (float)$mark['multiple_assessment_marks'];
        }

        if ($mark['practical_marks'] !== null) {
            $practical =
                (float)$mark['practical_marks'];
        }

        if ($mark['practical_max_marks'] !== null) {
            $practical_max =
                (float)$mark['practical_max_marks'];
        }

        if ($mark['theory_marks'] !== null) {
            $theory =
                (float)$mark['theory_marks'];
        }

        if ($mark['theory_max_marks'] !== null) {
            $theory_max =
                (float)$mark['theory_max_marks'];
        }
    }

    $component_values = array(
        'pa_marks' => $pa,
        'se_marks' => $se,
        'nb_marks' => $nb,
        'multiple_assessment_marks' => $multiple_assessment,
        'written_marks' => $written,
        'practical_marks' => $practical,
        'theory_marks' => $theory
    );
    $component_maxima = array(
        'practical_marks' => $practical_max,
        'theory_marks' => $theory_max
    );
    $has_components = false;
    $display_obtained = 0;
    $component_max_total = 0;
    foreach ($report_components as $component_key => $component) {
        $component_value = $component_values[$component_key] ?? null;
        if ($component_value !== null) {
            $has_components = true;
            $display_obtained += (float)$component_value;
        }
        if ($component['max'] !== null) {
            $component_max_total += (float)$component['max'];
        } elseif (isset($component_maxima[$component_key])) {
            $component_max_total += (float)($component_maxima[$component_key] ?? 0);
        }
    }
    if (!$has_components) {
        $display_obtained = $obtained;
    }
    if ($has_components && $component_max_total > 0) {
        $max_marks = $component_max_total;
    }


    /*
    |--------------------------------------------------------------------------
    | PERCENTAGE
    |--------------------------------------------------------------------------
    */

    if ($grade_only_mode) {
        $max_marks = 0;
    }

    $percentage =
        $max_marks > 0
        ? (
            $display_obtained /
            $max_marks
        ) * 100
        : 0;


    $grade = $max_marks <= 0
        ? $stored_grade
        : gradeFromPercentage($percentage);


    /*
    |--------------------------------------------------------------------------
    | TOTALS
    |--------------------------------------------------------------------------
    */

    $term_total +=
        $display_obtained;

    $term_max +=
        $max_marks;


    /*
    |--------------------------------------------------------------------------
    | SAVE DISPLAY ROW
    |--------------------------------------------------------------------------
    */

    $subject_rows[] = [

        'subject' =>
            $subject,

        'pa' =>
            $pa,

        'se' =>
            $se,

        'nb' =>
            $nb,

        'written' =>
            $written,

        'components' =>
            $component_values,

        'component_maxima' =>
            $component_maxima,

        'obtained' =>
            $display_obtained,

        'max' =>
            $max_marks,

        'percentage' =>
            $percentage,

        'grade' =>
            $grade,

        'remarks' =>
            $remarks
    ];
}


$overall_percentage =
    $term_max > 0
    ? (
        $term_total /
        $term_max
    ) * 100
    : 0;


$overall_grade = $term_max > 0
    ? gradeFromPercentage($overall_percentage)
    : '—';


$result_status = $term_max > 0
    ? resultStatus($overall_percentage)
    : 'NOT CALCULATED';

?>


<!-- ==========================================================
     ONE A4 PAGE PER STUDENT
     ========================================================== -->

<div class="page">

<div class="hpc-card">


<!-- ==========================================================
     SCHOOL HEADER
     ========================================================== -->

<div class="school-header">

<div class="school-name">
OASIS PUBLIC SCHOOL
</div>

<div class="school-city">
MALERKOTLA
</div>

<div class="hpc-title">
HOLISTIC PROGRESS CARD
</div>

<div class="session">
(<?= h($session_name); ?>)
</div>

</div>


<!-- ==========================================================
     STUDENT DETAILS
     ========================================================== -->

<table class="student-info">

<tr>

<td>
<span class="label">
Name of Learner :
</span>

<?= h($student['student_name']); ?>

</td>


<td>
<span class="label">
Father's Name :
</span>

<?= h($student['father_name']); ?>

</td>

</tr>


<tr>

<td>

<span class="label">
Class Section :
</span>

<?= h($class_name); ?>

-

<?= h($section_name); ?>

</td>


<td>

<span class="label">
Admission No. :
</span>

<?= h($student['admission_no']); ?>

</td>

</tr>


<tr>

<td>

<span class="label">
D.O.B :
</span>

<?php

if (!empty($student['dob'])) {

    echo h(
        date(
            'd-m-Y',
            strtotime(
                $student['dob']
            )
        )
    );

}

?>

</td>


<td>

<span class="label">
Class Teacher :
</span>

________________________

</td>

</tr>


<tr>

<td>

<span class="label">
Roll No. :
</span>

<?= h($student['roll_no']); ?>

</td>


<td>

<span class="label">
Examination :
</span>

<?= h($exam_name); ?>

</td>

</tr>

</table>


<!-- ==========================================================
     TERM / HEIGHT / WEIGHT
     ========================================================== -->

<table class="term-info">

<tr>

<td>

<strong>
TERM-I
</strong>

<br>

MY HEIGHT IS:
________________

&nbsp;&nbsp;

MY WEIGHT IS:
________________

</td>


<?php if ($show_term2): ?>
<td>

<strong>
TERM-II
</strong>

<br>

MY HEIGHT IS:
________________

&nbsp;&nbsp;

MY WEIGHT IS:
________________

</td>
<?php endif; ?>

</tr>


<tr>

<td>

<strong>
ATTENDANCE
</strong>

<br>

Maximum:
____________

<br>

TERM-I:
____________

<br>

Attended:
____________

</td>


<?php if ($show_term2): ?>
<td>

<strong>
TERM-II
</strong>

<br>

Maximum:
____________

<br>

Attended:
____________

</td>
<?php endif; ?>

</tr>

</table>


<!-- ==========================================================
     PART A SCHOLASTIC
     ========================================================== -->

<div class="section-title">

PART-A SCHOLASTIC AREAS

</div>


<div class="sub-title">

Scholastic Summary For The Academic Year
(<?= h($session_name); ?>)

</div>


<table class="scholastic-table">

<thead>

<tr>

<th rowspan="<?= $class_order >= 14 ? 3 : 2; ?>" class="subject">Subject</th>
<?php if ($grade_only_mode): ?>
<th><?= $show_term2 ? 'TERM-I' : h($current_term); ?></th>
<?php if ($show_term2): ?><th>TERM-II</th><?php endif; ?>
<?php else: ?>
<th colspan="<?= $report_component_column_count; ?>"><?= $show_term2 ? 'TERM-I' : h($current_term); ?></th>
<?php if ($show_term2): ?><th colspan="<?= $report_component_column_count; ?>">TERM-II</th><?php endif; ?>
<?php endif; ?>
</tr>

<tr>
<?php if ($grade_only_mode): ?>
<th class="term-column">Grade</th>
<?php if ($show_term2): ?><th class="term-column">Grade</th><?php endif; ?>
<?php else: ?>
<?php foreach ($report_components as $component): ?>
<?php if ($class_order >= 14): ?>
<th class="term-column" colspan="2"><?= h($component['label']); ?></th>
<?php else: ?>
<th class="term-column"><?= h($component['label']); ?><?php if ($component['max'] !== null): ?><br>(<?= cleanNumber($component['max']); ?>)<?php endif; ?></th>
<?php endif; ?>
<?php endforeach; ?>
<?php if ($class_order >= 14): ?>
<th class="term-column" colspan="3">TOTAL</th>
<?php else: ?>
<th class="term-column">TOTAL<br>(100)<br>Grade</th>
<?php endif; ?>

<?php if ($show_term2): ?>
<?php foreach ($report_components as $component): ?>
<?php if ($class_order >= 14): ?>
<th class="term-column" colspan="2"><?= h($component['label']); ?></th>
<?php else: ?>
<th class="term-column"><?= h($component['label']); ?><?php if ($component['max'] !== null): ?><br>(<?= cleanNumber($component['max']); ?>)<?php endif; ?></th>
<?php endif; ?>
<?php endforeach; ?>
<?php if ($class_order >= 14): ?>
<th class="term-column" colspan="3">TOTAL</th>
<?php else: ?>
<th class="term-column">TOTAL<br>(100)<br>Grade</th>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
</tr>

<?php if (!$grade_only_mode && $class_order >= 14): ?>
<tr>
<?php foreach ($report_components as $component): ?><th class="term-column">Obt.</th><th class="term-column">Max.</th><?php endforeach; ?>
<th class="term-column">Obt.</th><th class="term-column">Max.</th><th class="term-column">Grade</th>
<?php if ($show_term2): ?>
<?php foreach ($report_components as $component): ?><th class="term-column">Obt.</th><th class="term-column">Max.</th><?php endforeach; ?>
<th class="term-column">Obt.</th><th class="term-column">Max.</th><th class="term-column">Grade</th>
<?php endif; ?>
</tr>
<?php endif; ?>

</thead>


<tbody>


<?php foreach ($subject_rows as $row): ?>


<tr>


<td class="subject">

<?= h(
    $row['subject']['subject_name']
); ?>


</td>


<?php
$has_component_value = false;
foreach ($report_components as $component_key => $component) {
    if (($row['components'][$component_key] ?? null) !== null) {
        $has_component_value = true;
        break;
    }
}
$render_component_cells = function () use ($row, $report_components, $has_component_value, $class_order) {
    foreach ($report_components as $component_key => $component) {
        $value = $row['components'][$component_key] ?? null;
        if ($value === null && !$has_component_value && $component_key === 'written_marks') {
            $value = $row['obtained'];
        }
        if ($class_order >= 14) {
            $maximum = $row['component_maxima'][$component_key] ?? null;
            echo '<td>' . ($value !== null ? cleanNumber($value) : '-') . '</td>';
            echo '<td>' . ($maximum !== null ? cleanNumber($maximum) : '-') . '</td>';
        } else {
            echo '<td>' . ($value !== null ? cleanNumber($value) : '-') . '</td>';
        }
    }
    if ($class_order >= 14) {
        echo '<td>' . cleanNumber($row['obtained']) . '</td>';
        echo '<td>' . cleanNumber($row['max']) . '</td>';
        echo '<td><strong>' . h($row['grade']) . '</strong></td>';
    } else {
        echo '<td>' . cleanNumber($row['obtained']) . '<br><strong>' . h($row['grade']) . '</strong></td>';
    }
};
?>

<?php if ($show_term2 && $current_term === 'TERM-II'): ?>
<?php for ($i = 0; $i < ($grade_only_mode ? 1 : $report_component_column_count); $i++): ?><td>-</td><?php endfor; ?>
<?php endif; ?>

<?php if ($grade_only_mode): ?>
<td><strong><?= h($row['grade'] !== '' ? $row['grade'] : '-'); ?></strong></td>
<?php else: ?>
<?php $render_component_cells(); ?>
<?php endif; ?>

<?php if ($show_term2 && $current_term !== 'TERM-II'): ?>
<?php for ($i = 0; $i < ($grade_only_mode ? 1 : $report_component_column_count); $i++): ?><td>-</td><?php endfor; ?>
<?php endif; ?>


</tr>


<?php endforeach; ?>


</tbody>

</table>


<!-- ==========================================================
     TOTAL
     ========================================================== -->

<table class="small-table">

<tr>

<td style="width:55%;">

<strong>

<?= h(
    $current_term
); ?>

TOTAL MARKS

</strong>

</td>

<?php if ($grade_only_mode): ?>
<td colspan="<?= $show_term2 ? 2 : 1; ?>">Grades are recorded by subject.</td>
<?php else: ?>

<td style="width:15%;">

<strong>

<?= cleanNumber(
    $term_total
); ?>

/

<?= cleanNumber(
    $term_max
); ?>

</strong>

</td>


<td style="width:15%;">

<strong>

<?= $term_max > 0 ? cleanNumber($overall_percentage) . '%' : '—'; ?>

</strong>

</td>


<td style="width:15%;">

<strong>

<?= h(
    $overall_grade
); ?>

</strong>

</td>

<?php endif; ?>

</tr>

</table>


<!-- ==========================================================
     OTHER SUBJECTS
     ========================================================== -->

<?php if ($class_order < 14 && !empty($otherSubjects)): ?>
<div class="section-title">

OTHER SCHOLASTIC / CO-SCHOLASTIC SUBJECTS

</div>


<table class="small-table">

<tr>


<?php

/*
|--------------------------------------------------------------------------
| BUILD DISPLAY FROM THE SELECTED CLASS GROUP
|--------------------------------------------------------------------------
*/

$otherDisplay = [];


foreach ($otherSubjects as $subject) {
    $subjectName = trim($subject['subject_name']);
    $sid = (int)$subject['id'];
    $mark = $marksForStudent[$sid] ?? null;
    $grade = '';
    if ($mark) {
        $grade = trim((string)($mark['stored_grade'] ?? $mark['remarks'] ?? ''));
        if ($grade === '' && (float)$mark['max_marks'] > 0) {
            $grade = gradeFromPercentage(
                ((float)$mark['obtained_marks'] / (float)$mark['max_marks']) * 100
            );
        }
    }
    $otherDisplay[] = array(
        'name' => $subjectName,
        'grade' => $grade
    );
}


foreach ($otherDisplay as $item):

?>

<td>

<strong>

<?= h(
    $item['name']
); ?>

</strong>

<br>

<?= h(
    $item['grade']
); ?>

</td>

<?php endforeach; ?>


</tr>

</table>


<div class="observation-grid">
<?php if ($class_order <= 13): ?>

<div class="observation-panel">

<!-- ==========================================================
     COGNITIVE SKILLS
     ========================================================== -->

<div class="section-title">

COGNITIVE SKILLS

</div>


<table class="small-table observation-table">

<tr>

<th>
Observation
</th>

<th>
TERM-I
</th>

<?php if ($show_term2): ?>
<th>
TERM-II
</th>
<?php endif; ?>

</tr>


<?php foreach ($cognitiveSkills as $item): ?>

<tr>

<td>

<?= h($item); ?>

</td>

<?php if ($show_term2): ?>
<td><?= h($default_observation); ?></td>
<?php endif; ?>

<td>

<?= h(
    $default_observation
); ?>

</td>

</tr>

<?php endforeach; ?>


</table>

</div>

<div class="observation-panel">

<!-- ==========================================================
     SEL
     ========================================================== -->

<div class="section-title">

SOCIAL SKILLS

</div>


<table class="small-table observation-table">

<tr>

<th>
Observation
</th>

<th>
TERM-I
</th>

<?php if ($show_term2): ?>
<th>
TERM-II
</th>
<?php endif; ?>

</tr>


<?php foreach ($socialSkills as $item): ?>

<tr>

<td>

<?= h($item); ?>

</td>

<?php if ($show_term2): ?>
<td><?= h($default_observation); ?></td>
<?php endif; ?>

<td>

<?= h(
    $default_observation
); ?>

</td>

</tr>

<?php endforeach; ?>


</table>

</div>

<div class="observation-panel">

<!-- ==========================================================
     AESTHETIC &amp; CULTURAL DEVELOPMENT
     ========================================================== -->

<div class="section-title">

AESTHETIC &amp; CULTURAL DEVELOPMENT

</div>


<table class="small-table observation-table">

<tr>

<th>
Observation
</th>

<th>
TERM-I
</th>

<?php if ($show_term2): ?>
<th>
TERM-II
</th>
<?php endif; ?>

</tr>


<?php foreach ($aestheticDevelopment as $item): ?>

<tr>

<td>

<?= h($item); ?>

</td>

<?php if ($show_term2): ?>
<td><?= h($default_observation); ?></td>
<?php endif; ?>

<td>

<?= h(
    $default_observation
); ?>

</td>

</tr>

<?php endforeach; ?>


</table>

</div>

<div class="observation-panel">

<!-- ==========================================================
     CO-CURRICULAR
     ========================================================== -->

<div class="section-title">

SELF-MANAGEMENT SKILLS

</div>


<table class="small-table observation-table">

<tr>

<th>
Observation
</th>

<th>
TERM-I
</th>

<?php if ($show_term2): ?>
<th>
TERM-II
</th>
<?php endif; ?>

</tr>


<?php foreach ($selfManagementSkills as $item): ?>

<tr>

<td>

<?= h($item); ?>

</td>

<?php if ($show_term2): ?>
<td><?= h($default_observation); ?></td>
<?php endif; ?>

<td>

<?= h(
    $default_observation
); ?>

</td>

</tr>

<?php endforeach; ?>


</table>

</div>
</div>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- ==========================================================
     FINAL RESULT
     ========================================================== -->

<table class="small-table">

<tr>

<td style="width:35%;">

<strong>
Overall Percentage
</strong>

</td>


<td style="width:20%;">

<?= $term_max > 0 ? cleanNumber($overall_percentage) . '%' : '—'; ?>

</td>


<td style="width:20%;">

<strong>
Grade
</strong>

</td>


<td style="width:25%;">

<strong>

<?= h(
    $overall_grade
); ?>

</strong>

</td>

</tr>


<tr>

<td>

<strong>
Result
</strong>

</td>


<td colspan="3">

<strong class="<?= $result_status === 'PASS'
    ? 'pass'
    : 'fail';
?>">

<?= h(
    $result_status
); ?>

</strong>

</td>

</tr>

</table>


<!-- ==========================================================
     SIGNATURES
     ========================================================== -->

<table class="signature-table">

<tr>

<td>

____________________

<br>

Teacher Sign

</td>


<td>

____________________

<br>

Examination Sign

</td>


<td>

____________________

<br>

Principal Sign

</td>

</tr>

</table>


</div>

</div>


<?php endforeach; ?>


</body>

</html>