<?php
/**
 * ============================================================
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

$stmt = $conn->prepare("
    SELECT class_name
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
}

$stmt->close();


if ($class_name === '') {
    die("Class not found.");
}


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


/*
|--------------------------------------------------------------------------
| GET SUBJECTS
|--------------------------------------------------------------------------
|
| First get subjects appearing in marks for this examination.
| If none exist, get all active Scholastic subjects.
|--------------------------------------------------------------------------
*/

$subjects = [];

$stmt = $conn->prepare("
    SELECT DISTINCT
        s.id,
        s.subject_name,
        s.subject_code,
        s.subject_type
    FROM marks m
    INNER JOIN subjects s
        ON s.id = m.subject_id
    WHERE m.class_id = ?
    AND m.section_id = ?
    AND m.exam_id = ?
    ORDER BY s.id
");

if ($stmt) {

    $stmt->bind_param(
        "iii",
        $class_id,
        $section_id,
        $exam_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| IF NO MARKS SUBJECTS FOUND
|--------------------------------------------------------------------------
*/

if (count($subjects) === 0) {

    $result = $conn->query("
        SELECT
            id,
            subject_name,
            subject_code,
            subject_type
        FROM subjects
        WHERE status = 1
        ORDER BY id
    ");

    if ($result) {

        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
    }
}


/*
|--------------------------------------------------------------------------
| SEPARATE SCHOLASTIC / OTHER SUBJECTS
|--------------------------------------------------------------------------
*/

$scholasticSubjects = [];
$otherSubjects = [];

foreach ($subjects as $subject) {

    $type = strtolower(
        trim(
            (string)$subject['subject_type']
        )
    );

    if (
        $type === '' ||
        $type === 'scholastic'
    ) {

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
        $pa_sql AS pa_marks,
        $se_sql AS se_marks,
        $nb_sql AS nb_marks,
        $written_sql AS written_marks
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

    if (!isset($studentMarks[$student_id])) {
        $studentMarks[$student_id] = [];
    }

    $studentMarks[$student_id][$subject_id] = $row;
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| HPC OBSERVATION ITEMS
|--------------------------------------------------------------------------
*/

$lifeSkills = [
    'Critical Thinking',
    'Problem Solving',
    'Communication'
];

$sel = [
    'Respect for Others',
    'Responsibility',
    'Honesty',
    'Empathy'
];

$positiveHabits = [
    'Work Habits',
    'Time Management',
    'Organization',
    'Initiative',
    'Perseverance'
];

$coCurricular = [
    'Leadership Role',
    'Team Work Collaboration'
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

$default_observation = 'PF';


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

    $obtained = 0;

    $max_marks = 100;

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
    }


    /*
    |--------------------------------------------------------------------------
    | COMPONENT TOTAL
    |--------------------------------------------------------------------------
    */

    $has_components =
        (
            $pa !== null ||
            $se !== null ||
            $nb !== null ||
            $written !== null
        );


    if ($has_components) {

        $display_obtained =
            (float)($pa ?? 0)
            +
            (float)($se ?? 0)
            +
            (float)($nb ?? 0)
            +
            (float)($written ?? 0);

    } else {

        $display_obtained =
            $obtained;
    }


    /*
    |--------------------------------------------------------------------------
    | PERCENTAGE
    |--------------------------------------------------------------------------
    */

    $percentage =
        $max_marks > 0
        ? (
            $display_obtained /
            $max_marks
        ) * 100
        : 0;


    $grade =
        gradeFromPercentage(
            $percentage
        );


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


$overall_grade =
    gradeFromPercentage(
        $overall_percentage
    );


$result_status =
    resultStatus(
        $overall_percentage
    );

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

<th rowspan="2" class="subject">

Subject

</th>


<th colspan="5">

TERM-I

</th>


<?php if ($show_term2): ?>
<th colspan="5">TERM-II</th>
<?php endif; ?>

</tr>


<tr>

<th class="term-column">
P.A.<br>(10)
</th>

<th class="term-column">
S.E.<br>(5)
</th>

<th class="term-column">
N.B.<br>(5)
</th>

<th class="term-column">
Written<br>(80)
</th>

<th class="term-column">
TOTAL<br>(100)<br>Grade
</th>


<?php if ($show_term2): ?><th class="term-column">
P.A.<br>(10)
</th>

<th class="term-column">
S.E.<br>(5)
</th>

<th class="term-column">
N.B.<br>(5)
</th>

<th class="term-column">
Written<br>(80)
</th>

<th class="term-column">
TOTAL<br>(100)<br>Grade
</th>

<?php endif; ?>

</tr>

</thead>


<tbody>


<?php foreach ($subject_rows as $row): ?>


<tr>


<td class="subject">

<?= h(
    $row['subject']['subject_name']
); ?>


</td>


<?php if ($current_term === 'TERM-I'): ?>


<!-- TERM-I -->

<td>

<?= $row['pa'] !== null
    ? cleanNumber($row['pa'])
    : '—';
?>

</td>


<td>

<?= $row['se'] !== null
    ? cleanNumber($row['se'])
    : '—';
?>

</td>


<td>

<?= $row['nb'] !== null
    ? cleanNumber($row['nb'])
    : '—';
?>

</td>


<td>

<?php

if ($row['written'] !== null) {

    echo cleanNumber(
        $row['written']
    );

} else {

    echo cleanNumber(
        $row['obtained']
    );

}

?>

</td>


<td>

<?= cleanNumber(
    $row['obtained']
); ?>

<br>

<strong>

<?= h(
    $row['grade']
); ?>

</strong>

</td>


<?php if ($show_term2): ?>
<td>—</td><td>—</td><td>—</td><td>—</td><td>—</td>
<?php endif; ?>


<?php else: ?>


<?php if ($show_term2): ?>
<td>—</td><td>—</td><td>—</td><td>—</td><td>—</td>


<!-- TERM-II -->

<td>

<?= $row['pa'] !== null
    ? cleanNumber($row['pa'])
    : '—';
?>

</td>


<td>

<?= $row['se'] !== null
    ? cleanNumber($row['se'])
    : '—';
?>

</td>


<td>

<?= $row['nb'] !== null
    ? cleanNumber($row['nb'])
    : '—';
?>

</td>


<td>

<?php

if ($row['written'] !== null) {

    echo cleanNumber(
        $row['written']
    );

} else {

    echo cleanNumber(
        $row['obtained']
    );

}

?>

</td>


<td>

<?= cleanNumber(
    $row['obtained']
); ?>

<br>

<strong>

<?= h(
    $row['grade']
); ?>

</strong>

</td>


<?php endif; ?>

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

<?= cleanNumber(
    $overall_percentage
); ?>

%

</strong>

</td>


<td style="width:15%;">

<strong>

<?= h(
    $overall_grade
); ?>

</strong>

</td>

</tr>

</table>


<!-- ==========================================================
     OTHER SUBJECTS
     ========================================================== -->

<div class="section-title">

OTHER SCHOLASTIC / CO-SCHOLASTIC SUBJECTS

</div>


<table class="small-table">

<tr>


<?php

/*
|--------------------------------------------------------------------------
| REQUIRED DEFAULT OTHER SUBJECTS
|--------------------------------------------------------------------------
*/

$requiredOther = [

    'COMPUTER',

    'PHYSICAL EDUCATION',

    'URDU',

    'G.K.'
];


/*
|--------------------------------------------------------------------------
| BUILD DISPLAY
|--------------------------------------------------------------------------
*/

$otherDisplay = [];


foreach ($requiredOther as $requiredName) {

    $found = false;

    foreach ($otherSubjects as $subject) {

        $subjectName =
            strtoupper(
                trim(
                    $subject['subject_name']
                )
            );

        if (
            $subjectName ===
            strtoupper($requiredName)
        ) {

            $sid =
                (int)$subject['id'];

            $mark =
                isset(
                    $marksForStudent[$sid]
                )
                ? $marksForStudent[$sid]
                : null;


            $grade = '';


            if ($mark) {

                $grade =
                    trim(
                        (string)(
                            $mark['remarks']
                            ?? ''
                        )
                    );


                if (
                    $grade === ''
                ) {

                    $obt =
                        (float)(
                            $mark[
                                'obtained_marks'
                            ]
                        );

                    $max =
                        (float)(
                            $mark[
                                'max_marks'
                            ]
                        );

                    if ($max > 0) {

                        $grade =
                            gradeFromPercentage(
                                (
                                    $obt /
                                    $max
                                ) * 100
                            );
                    }
                }
            }


            $otherDisplay[] = [

                'name' =>
                    $requiredName,

                'grade' =>
                    $grade
            ];

            $found = true;

            break;
        }
    }


    if (!$found) {

        $otherDisplay[] = [

            'name' =>
                $requiredName,

            'grade' =>
                ''
        ];
    }
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

<div class="observation-panel">

<!-- ==========================================================
     LIFE SKILLS
     ========================================================== -->

<div class="section-title">

LIFE SKILLS

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


<?php foreach ($lifeSkills as $item): ?>

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

SOCIAL EMOTIONAL &amp; ETHICAL LEARNING

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


<?php foreach ($sel as $item): ?>

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
     POSITIVE LEARNING HABITS
     ========================================================== -->

<div class="section-title">

POSITIVE LEARNING HABITS

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


<?php foreach ($positiveHabits as $item): ?>

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

CO-CURRICULAR ACTIVITIES

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


<?php foreach ($coCurricular as $item): ?>

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

<?= cleanNumber(
    $overall_percentage
); ?>

%

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