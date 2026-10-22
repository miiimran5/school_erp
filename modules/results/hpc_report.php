<?php
/**
 * ============================================================
 * SCHOOL ERP
 * T-037 - CBSE HOLISTIC PROGRESS CARD
 * STEP 5 - REPORT CARD PREVIEW
 * ============================================================
 *
 * File:
 * modules/results/hpc_report.php
 *
 * Uses:
 * students
 * academic_sessions
 * classes
 * sections
 * subjects
 * hpc_student_details
 * hpc_attendance
 * hpc_scholastic
 * hpc_observations
 *
 * ============================================================
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/access.php';
access_require_roles(array('Admin', 'Teacher'));
access_require_feature($conn, 'hpc_reports');

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
}

$conn->set_charset("utf8mb4");


/* ============================================================
   HELPER
   ============================================================ */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function observationGradeLabel($value)
{
    $grade = strtoupper(trim((string)$value));
    if ($grade === 'AD') {
        return 'Ad';
    }
    if ($grade === 'PF') {
        return 'Pf';
    }
    return $grade;
}


/* ============================================================
   GRADE FUNCTION
   ============================================================ */

function getGrade($percentage)
{
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


/* ============================================================
   GET PARAMETERS
   ============================================================ */

$student_id = isset($_GET['student_id'])
    ? (int)$_GET['student_id']
    : 0;

$session_id = isset($_GET['session_id'])
    ? (int)$_GET['session_id']
    : 0;

$class_id = isset($_GET['class_id'])
    ? (int)$_GET['class_id']
    : 0;

$section_id = isset($_GET['section_id'])
    ? (int)$_GET['section_id']
    : 0;


if (
    $student_id <= 0 ||
    $session_id <= 0 ||
    $class_id <= 0 ||
    $section_id <= 0
) {
    die("
        <div style='
            font-family:Arial;
            padding:40px;
            text-align:center;
        '>

            <h2>Invalid Report Card Request</h2>

            <p>
                Student, Session, Class and Section
                are required.
            </p>

            <a href='index.php'>
                ← Back
            </a>

        </div>
    ");
}
if (access_is_teacher() && !access_teacher_can_class($conn, $class_id, $section_id)) {
    http_response_code(403);
    exit('You are not assigned to this class and section.');
}


/* ============================================================
   GET STUDENT
   ============================================================ */

$stmt = $conn->prepare("
    SELECT
        id,
        admission_no,
        student_name,
        gender,
        dob,
        father_name,
        mother_name,
        photo,
        roll_no,
        class_id,
        section_id
    FROM students
    WHERE id = ?
      AND class_id = ?
      AND section_id = ?
      AND status = 1
    LIMIT 1
");

$stmt->bind_param(
    "iii",
    $student_id,
    $class_id,
    $section_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Student not found.");
}

$student = $result->fetch_assoc();

$stmt->close();


/* ============================================================
   GET SESSION
   ============================================================ */

$stmt = $conn->prepare("
    SELECT
        id,
        session_name,
        start_date,
        end_date
    FROM academic_sessions
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $session_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Academic session not found.");
}

$session = $result->fetch_assoc();

$stmt->close();

$session_name =
    $session['session_name'];


/* ============================================================
   GET CLASS
   ============================================================ */

$stmt = $conn->prepare("
    SELECT class_name
    FROM classes
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $class_id
);

$stmt->execute();

$result = $stmt->get_result();

$class = $result->fetch_assoc();

$stmt->close();

$class_name =
    $class['class_name'] ?? '';


/* ============================================================
   GET SECTION
   ============================================================ */

$stmt = $conn->prepare("
    SELECT section_name
    FROM sections
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param(
    "i",
    $section_id
);

$stmt->execute();

$result = $stmt->get_result();

$section = $result->fetch_assoc();

$stmt->close();

$section_name =
    $section['section_name'] ?? '';


/* ============================================================
   HPC STUDENT DETAILS
   ============================================================ */

$hpc_details = [

    'class_teacher' => '',

    'term1_height' => '',

    'term1_weight' => '',

    'term2_height' => '',

    'term2_weight' => ''
];


$stmt = $conn->prepare("
    SELECT
        class_teacher,
        term1_height,
        term1_weight,
        term2_height,
        term2_weight
    FROM hpc_student_details
    WHERE student_id = ?
      AND session_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "ii",
    $student_id,
    $session_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {

    $data = $result->fetch_assoc();

    $hpc_details = array_merge(
        $hpc_details,
        $data
    );
}

$stmt->close();


/* ============================================================
   ATTENDANCE
   ============================================================ */

$attendance = [

    'maximum_days' => '',

    'term1_days' => '',

    'term2_days' => '',

    'attended_days' => ''
];


$stmt = $conn->prepare("
    SELECT
        maximum_days,
        term1_days,
        term2_days,
        attended_days
    FROM hpc_attendance
    WHERE student_id = ?
      AND session_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "ii",
    $student_id,
    $session_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {

    $attendance =
        array_merge(
            $attendance,
            $result->fetch_assoc()
        );
}

$stmt->close();


/* ============================================================
   SCHOLASTIC DATA
   ============================================================ */

$scholastic = [

    'Term-I' => [],

    'Term-II' => []
];


$stmt = $conn->prepare("
    SELECT
        h.subject_id,
        h.term,
        h.pa_marks,
        h.se_marks,
        h.nb_marks,
        h.exam_marks,
        h.remarks,

        s.subject_name,
        s.subject_code,
        s.subject_type,
        cs.role AS class_subject_role

    FROM hpc_scholastic h

    INNER JOIN subjects s
        ON s.id = h.subject_id
    INNER JOIN class_subjects cs
        ON cs.class_id = ?
       AND cs.subject_id = h.subject_id
       AND cs.status = 1

    WHERE h.student_id = ?
      AND h.session_id = ?

    ORDER BY
        s.id ASC
");

$stmt->bind_param(
    "iii",
    $class_id,
    $student_id,
    $session_id
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $term = $row['term'];

    $pa =
        (float)$row['pa_marks'];

    $se =
        (float)$row['se_marks'];

    $nb =
        (float)$row['nb_marks'];

    $exam =
        (float)$row['exam_marks'];

    $total =
        $pa + $se + $nb + $exam;

    /*
     * Total maximum = 100
     */

    $percentage = $total;

    $grade =
        getGrade($percentage);


    $row['total'] =
        $total;

    $row['percentage'] =
        $percentage;

    $row['grade'] =
        $grade;


    if (!isset($scholastic[$term])) {

        $scholastic[$term] = [];
    }


    $scholastic[$term][$row['subject_id']]
        = $row;
}

$stmt->close();


/* ============================================================
   OBSERVATIONS
   ============================================================ */

$observations = [];

$stmt = $conn->prepare("
    SELECT
        category,
        observation,
        term1_value,
        term2_value
    FROM hpc_observations
    WHERE student_id = ?
      AND session_id = ?
    ORDER BY id ASC
");

$stmt->bind_param(
    "ii",
    $student_id,
    $session_id
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $category =
        $row['category'];

    if (!isset($observations[$category])) {

        $observations[$category] = [];
    }

    $observations[$category][] =
        $row;
}

$stmt->close();


/* ============================================================
   REQUIRED OBSERVATION CATEGORIES
   ============================================================ */

$observation_categories = [

    'Cognitive Skills' => [

        'Critical Thinking',

        'Problem Solving',

        'Decision Making'
    ],


    'Social and Interpersonal Skills' => [

        'Civic Responsibility',

        'Empathy',
        'Integrity'
    ],


    'Aesthetic and Cultural Development' => [

        'Awareness',
        'Creativity',
        'Participation',
        'Expression'
    ],


    'Self-Management Skills' => [

        'Coping With Stress',
        'Coping With Emotions',
        'Self-Care and Hygiene'
    ]
];


/* ============================================================
   OBSERVATION LOOKUP
   ============================================================ */

$observation_values = [];

foreach (
    $observation_categories
    as $category => $items
) {

    foreach ($items as $item) {

        $observation_values[$category][$item] = [

            'term1' => '',

            'term2' => ''
        ];
    }
}


foreach ($observations as $category => $rows) {

    foreach ($rows as $row) {

        $observation =
            $row['observation'];

        if (
            isset(
                $observation_values
                    [$category]
                    [$observation]
            )
        ) {

            $observation_values
                [$category]
                [$observation]
                ['term1']
                =
                observationGradeLabel($row['term1_value']);

            $observation_values
                [$category]
                [$observation]
                ['term2']
                =
                observationGradeLabel($row['term2_value']);
        }
    }
}


/* ============================================================
   SUBJECTS
   ============================================================ */

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
    ORDER BY cs.sort_order, s.id ASC
");

$stmt->bind_param("i", $class_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $subjects[] = $row;
}

$stmt->close();


/* ============================================================
   SCHOLASTIC SUBJECT LIST
   ============================================================ */

$scholastic_subject_names = [];


/* ============================================================
   ADDITIONAL SUBJECTS
   ============================================================ */

$additional_subject_names = [];

foreach ($subjects as $subject) {
    $subject_name = strtoupper(trim((string)$subject['subject_name']));
    if (($subject['class_subject_role'] ?? '') === 'Main') {
        $scholastic_subject_names[] = $subject_name;
    } else {
        $additional_subject_names[] = $subject_name;
    }
}


/* ============================================================
   FIND SUBJECT DATA BY NAME
   ============================================================ */

function findSubjectData(
    $subjects,
    $subject_name,
    $term_data
) {

    foreach ($subjects as $subject) {

        if (
            strtoupper(
                trim($subject['subject_name'])
            )
            ===
            strtoupper(
                trim($subject_name)
            )
        ) {

            $id =
                $subject['id'];

            if (
                isset(
                    $term_data[$id]
                )
            ) {

                return $term_data[$id];
            }

            return null;
        }
    }

    return null;
}


/* ============================================================
   TERM TOTALS
   ============================================================ */

$term_totals = [

    'Term-I' => [

        'maximum' => 0,

        'obtained' => 0,

        'percentage' => 0,

        'grade' => ''
    ],

    'Term-II' => [

        'maximum' => 0,

        'obtained' => 0,

        'percentage' => 0,

        'grade' => ''
    ]
];


foreach (
    ['Term-I', 'Term-II']
    as $term
) {

    foreach (
        $scholastic[$term]
        as $row
    ) {

        /*
         * Only 6 main scholastic subjects
         * contribute to Part-A total.
         */

        $name =
            strtoupper(
                trim($row['subject_name'])
            );

        if (
            in_array(
                $name,
                $scholastic_subject_names
            )
        ) {

            $term_totals[$term]['maximum']
                += 100;

            $term_totals[$term]['obtained']
                += $row['total'];
        }
    }


    if (
        $term_totals[$term]['maximum'] > 0
    ) {

        $term_totals[$term]['percentage']
            =
            (
                $term_totals[$term]['obtained']
                /
                $term_totals[$term]['maximum']
            )
            * 100;
    }


    $term_totals[$term]['grade']
        =
        getGrade(
            $term_totals[$term]['percentage']
        );
}


/* ============================================================
   ADDITIONAL SUBJECT DATA
   ============================================================ */

function findAdditionalSubject(
    $subjects,
    $subject_name,
    $term_data
) {

    return findSubjectData(
        $subjects,
        $subject_name,
        $term_data
    );
}


/* ============================================================
   PHOTO
   ============================================================ */

$photo_url = '';

if (!empty($student['photo'])) {

    $possible_paths = [

        '../../uploads/students/' .
        $student['photo'],

        '../../uploads/' .
        $student['photo'],

        '../../assets/uploads/students/' .
        $student['photo']
    ];


    foreach ($possible_paths as $path) {

        if (file_exists($path)) {

            $photo_url = $path;

            break;
        }
    }
}

?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Holistic Progress Card -
    <?= e($student['student_name']); ?>
</title>

<link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/app.css">


<style>

/* =========================================================
   GENERAL
   ========================================================= */

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    background: #e5e5e5;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: #000;

    font-size: 12px;
}


/* =========================================================
   TOOLBAR
   ========================================================= */

.toolbar {

    position: sticky;

    top: 0;

    z-index: 1000;

    background: #343a40;

    padding: 12px;

    text-align: center;
}


.toolbar a,
.toolbar button {

    border: 0;

    padding: 9px 16px;

    margin: 3px;

    border-radius: 4px;

    color: white;

    text-decoration: none;

    cursor: pointer;

    font-size: 13px;
}


.btn-back {
    background: #6c757d;
}

.btn-print {
    background: #007bff;
}

.btn-edit {
    background: #28a745;
}


/* =========================================================
   A4 PAGE
   ========================================================= */

.page {

    width: 210mm;

    min-height: 297mm;

    margin: 20px auto;

    background: white;

    padding: 10mm;

    box-shadow:
        0 2px 12px
        rgba(0,0,0,0.2);
}


/* =========================================================
   HEADER
   ========================================================= */

.school-header {

    text-align: center;

    border: 2px solid #000;

    padding: 10px;

    margin-bottom: 8px;
}


.school-name {

    font-size: 22px;

    font-weight: bold;

    text-transform: uppercase;
}


.school-location {

    font-size: 13px;

    margin-top: 3px;
}


.hpc-title {

    font-size: 18px;

    font-weight: bold;

    margin-top: 8px;

    text-transform: uppercase;
}


/* =========================================================
   STUDENT DETAILS
   ========================================================= */

.info-table {

    width: 100%;

    border-collapse: collapse;

    margin-bottom: 8px;
}


.info-table td {

    border: 1px solid #000;

    padding: 6px;
}


.info-label {

    font-weight: bold;

    background: #f1f1f1;
}


.photo-cell {

    width: 90px;

    text-align: center;

    vertical-align: middle;
}


.student-photo {

    width: 75px;

    height: 85px;

    border: 1px solid #000;

    object-fit: cover;
}


.photo-placeholder {

    width: 75px;

    height: 85px;

    border: 1px solid #000;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    font-size: 10px;
}


/* =========================================================
   SECTION TITLE
   ========================================================= */

.section-title {

    background: #d9e2f3;

    border: 1px solid #000;

    padding: 6px;

    text-align: center;

    font-size: 14px;

    font-weight: bold;

    margin-top: 8px;
}


/* =========================================================
   TERM HEALTH
   ========================================================= */

.health-table {

    width: 100%;

    border-collapse: collapse;
}


.health-table th,
.health-table td {

    border: 1px solid #000;

    padding: 6px;

    text-align: center;
}


.health-table th {

    background: #f1f1f1;
}


/* =========================================================
   ATTENDANCE
   ========================================================= */

.attendance-table {

    width: 100%;

    border-collapse: collapse;

    margin-top: 8px;
}


.attendance-table th,
.attendance-table td {

    border: 1px solid #000;

    padding: 7px;

    text-align: center;
}


.attendance-table th {

    background: #f1f1f1;
}


/* =========================================================
   SCHOLASTIC TABLE
   ========================================================= */

.scholastic-table {

    width: 100%;

    border-collapse: collapse;

    font-size: 9px;

    table-layout: fixed;
}


.scholastic-table th,
.scholastic-table td {

    border: 1px solid #000;

    padding: 4px 2px;

    text-align: center;

    vertical-align: middle;
}


.scholastic-table th {

    background: #f1f1f1;

    font-weight: bold;
}


.scholastic-table .subject {

    text-align: left;

    width: 90px;

    font-weight: bold;
}


.term-header {

    background: #d9e2f3 !important;

    font-size: 10px;
}


/* =========================================================
   TOTAL
   ========================================================= */

.total-row {

    font-weight: bold;

    background: #eeeeee;
}


/* =========================================================
   ADDITIONAL SUBJECTS
   ========================================================= */

.additional-table {

    width: 100%;

    border-collapse: collapse;

    margin-top: 7px;
}


.additional-table th,
.additional-table td {

    border: 1px solid #000;

    padding: 6px;

    text-align: center;
}


.additional-table th {

    background: #f1f1f1;
}


.additional-table td:first-child {

    text-align: left;

    font-weight: bold;
}


/* =========================================================
   OBSERVATIONS
   ========================================================= */

.observation-table {

    width: 100%;

    border-collapse: collapse;

    margin-top: 5px;

    margin-bottom: 7px;
}


.observation-table th,
.observation-table td {

    border: 1px solid #000;

    padding: 5px;

    text-align: center;
}


.observation-table th {

    background: #f1f1f1;
}


.observation-table td:first-child {

    text-align: left;

    font-weight: bold;
}


/* =========================================================
   SIGNATURE
   ========================================================= */

.signature-table {

    width: 100%;

    border-collapse: collapse;

    margin-top: 65px;
}


.signature-table td {

    width: 33.33%;

    text-align: center;

    padding-top: 30px;

    border-top: 1px solid #000;

    font-weight: bold;
}


/* =========================================================
   FOOTER
   ========================================================= */

.footer {

    text-align: center;

    margin-top: 15px;

    font-size: 9px;

    color: #555;
}


/* =========================================================
   PRINT
   ========================================================= */

@media print {

    @page {

        size: A4;

        margin: 0;
    }


    body {

        background: white;
    }


    .toolbar {

        display: none !important;
    }


    .page {

        width: 210mm;

        min-height: 297mm;

        margin: 0;

        padding: 8mm;

        box-shadow: none;
    }
}


/* =========================================================
   MOBILE
   ========================================================= */

@media screen and (max-width: 900px) {

    .page {

        width: 96%;

        padding: 10px;

        overflow-x: auto;
    }
}

</style>

</head>


<body>


<!-- =========================================================
     TOOLBAR
     ========================================================= -->

<div class="toolbar">

    <a
        href="hpc_report_selector.php"
        class="btn-back"
    >
        ← Back
    </a>


    <button
        onclick="window.print()"
        class="btn-print"
    >
        🖨 Print
    </button>


    <a
        href="#"
        class="btn-edit"
        onclick="
            alert(
                'Editing will be connected to the HPC data-entry form.'
            );
            return false;
        "
    >
        ✎ Edit HPC Data
    </a>

</div>


<!-- =========================================================
     REPORT CARD PAGE
     ========================================================= -->

<div class="page">


<!-- =========================================================
     SCHOOL HEADER
     ========================================================= -->

<div class="school-header">

    <div class="school-name">

        OASIS PUBLIC SCHOOL

    </div>


    <div class="school-location">

        MALERKOTLA

    </div>


    <div class="hpc-title">

        HOLISTIC PROGRESS CARD -
        (<?= e($session_name); ?>)

    </div>

</div>


<!-- =========================================================
     STUDENT INFORMATION
     ========================================================= -->

<table class="info-table">

<tr>

    <td class="info-label">
        Name of Learner
    </td>

    <td>
        <?= e($student['student_name']); ?>
    </td>


    <td
        rowspan="4"
        class="photo-cell"
    >

        <?php if (!empty($photo_url)): ?>

            <img
                src="<?= e($photo_url); ?>"
                class="student-photo"
                alt="Student Photo"
            >

        <?php else: ?>

            <div class="photo-placeholder">

                PHOTO

            </div>

        <?php endif; ?>

    </td>

</tr>


<tr>

    <td class="info-label">
        Father's Name
    </td>

    <td>
        <?= e($student['father_name']); ?>
    </td>

</tr>


<tr>

    <td class="info-label">
        Class / Section
    </td>

    <td>

        <?= e($class_name); ?>

        /

        <?= e($section_name); ?>

    </td>

</tr>


<tr>

    <td class="info-label">
        Admission No.
    </td>

    <td>
        <?= e($student['admission_no']); ?>
    </td>

</tr>


<tr>

    <td class="info-label">
        D.O.B
    </td>

    <td>
        <?= e($student['dob']); ?>
    </td>


    <td class="info-label">
        Class Teacher
    </td>

    <td>
        <?= e($hpc_details['class_teacher']); ?>
    </td>

</tr>

</table>


<!-- =========================================================
     HEIGHT / WEIGHT
     ========================================================= -->

<div class="section-title">

    HEALTH & PHYSICAL DEVELOPMENT

</div>


<table class="health-table">

<tr>

    <th>
        TERM-I
    </th>

    <th>
        TERM-II
    </th>

</tr>


<tr>

    <td>

        <strong>
            MY HEIGHT IS:
        </strong>

        <?= e(
            $hpc_details['term1_height']
        ); ?>

        <br><br>

        <strong>
            MY WEIGHT IS:
        </strong>

        <?= e(
            $hpc_details['term1_weight']
        ); ?>

    </td>


    <td>

        <strong>
            MY HEIGHT IS:
        </strong>

        <?= e(
            $hpc_details['term2_height']
        ); ?>

        <br><br>

        <strong>
            MY WEIGHT IS:
        </strong>

        <?= e(
            $hpc_details['term2_weight']
        ); ?>

    </td>

</tr>

</table>


<!-- =========================================================
     ATTENDANCE
     ========================================================= -->

<div class="section-title">

    ATTENDANCE

</div>


<table class="attendance-table">

<tr>

    <th>
        Maximum
    </th>

    <th>
        Term-I
    </th>

    <th>
        Term-II
    </th>

    <th>
        Attended
    </th>

</tr>


<tr>

    <td>
        <?= e(
            $attendance['maximum_days']
        ); ?>
    </td>

    <td>
        <?= e(
            $attendance['term1_days']
        ); ?>
    </td>

    <td>
        <?= e(
            $attendance['term2_days']
        ); ?>
    </td>

    <td>
        <?= e(
            $attendance['attended_days']
        ); ?>
    </td>

</tr>

</table>


<!-- =========================================================
     PART-A
     ========================================================= -->

<div class="section-title">

    PART-A — SCHOLASTIC AREAS

</div>


<div style="
    text-align:center;
    font-weight:bold;
    margin:5px 0;
">

    Scholastic Summary For The Academic Year
    (<?= e($session_name); ?>)

</div>


<table class="scholastic-table">

<thead>

<tr>

    <th
        rowspan="2"
        class="subject"
    >
        Subject
    </th>


    <th
        colspan="7"
        class="term-header"
    >
        TERM-I
    </th>


    <th
        colspan="7"
        class="term-header"
    >
        TERM-II
    </th>

</tr>


<tr>

    <th>PA<br>(10)</th>

    <th>SE<br>(5)</th>

    <th>NB<br>(5)</th>

    <th>Exam<br>(80)</th>

    <th>Total<br>(100)</th>

    <th>Grade</th>

    <th>Result</th>


    <th>PA<br>(10)</th>

    <th>SE<br>(5)</th>

    <th>NB<br>(5)</th>

    <th>Exam<br>(80)</th>

    <th>Total<br>(100)</th>

    <th>Grade</th>

    <th>Result</th>

</tr>

</thead>


<tbody>


<?php foreach (
    $scholastic_subject_names
    as $subject_name
): ?>


<?php

$term1 =
    findSubjectData(
        $subjects,
        $subject_name,
        $scholastic['Term-I']
    );


$term2 =
    findSubjectData(
        $subjects,
        $subject_name,
        $scholastic['Term-II']
    );

?>


<tr>

    <td class="subject">

        <?= e($subject_name); ?>

    </td>


    <!-- TERM-I -->

    <?php if ($term1): ?>

        <td>
            <?= number_format(
                $term1['pa_marks'],
                1
            ); ?>
        </td>

        <td>
            <?= number_format(
                $term1['se_marks'],
                1
            ); ?>
        </td>

        <td>
            <?= number_format(
                $term1['nb_marks'],
                1
            ); ?>
        </td>

        <td>
            <?= number_format(
                $term1['exam_marks'],
                1
            ); ?>
        </td>

        <td>
            <?= number_format(
                $term1['total'],
                1
            ); ?>
        </td>

        <td>
            <strong>
                <?= e(
                    $term1['grade']
                ); ?>
            </strong>
        </td>

        <td>
            <?= $term1['total'] >= 33
                ? 'PASS'
                : 'FAIL'; ?>
        </td>

    <?php else: ?>

        <td colspan="7">
            —
        </td>

    <?php endif; ?>


    <!-- TERM-II -->

    <?php if ($term2): ?>

        <td>
            <?= number_format(
                $term2['pa_marks'],
                1
            ); ?>
        </td>

        <td>
            <?= number_format(
                $term2['se_marks'],
                1
            ); ?>
        </td>

        <td>
            <?= number_format(
                $term2['nb_marks'],
                1
            ); ?>
        </td>

        <td>
            <?= number_format(
                $term2['exam_marks'],
                1
            ); ?>
        </td>

        <td>
            <?= number_format(
                $term2['total'],
                1
            ); ?>
        </td>

        <td>
            <strong>
                <?= e(
                    $term2['grade']
                ); ?>
            </strong>
        </td>

        <td>
            <?= $term2['total'] >= 33
                ? 'PASS'
                : 'FAIL'; ?>
        </td>

    <?php else: ?>

        <td colspan="7">
            —
        </td>

    <?php endif; ?>

</tr>


<?php endforeach; ?>


<!-- =======================================================
     TERM TOTAL
     ======================================================= -->

<tr class="total-row">

    <td>
        TERM-I TOTAL MARKS
    </td>


    <td colspan="3">
        —
    </td>


    <td>

        <?= number_format(
            $term_totals['Term-I']['obtained'],
            1
        ); ?>

        /

        <?= number_format(
            $term_totals['Term-I']['maximum'],
            0
        ); ?>

    </td>


    <td>

        <?= number_format(
            $term_totals['Term-I']['percentage'],
            2
        ); ?>%

    </td>


    <td>

        <?= e(
            $term_totals['Term-I']['grade']
        ); ?>

    </td>


    <td>

        <?=
            $term_totals['Term-I']['percentage'] >= 33
            ? 'PASS'
            : 'FAIL';
        ?>

    </td>


    <td colspan="3">
        —
    </td>


    <td>

        <?= number_format(
            $term_totals['Term-II']['obtained'],
            1
        ); ?>

        /

        <?= number_format(
            $term_totals['Term-II']['maximum'],
            0
        ); ?>

    </td>


    <td>

        <?= number_format(
            $term_totals['Term-II']['percentage'],
            2
        ); ?>%

    </td>


    <td>

        <?= e(
            $term_totals['Term-II']['grade']
        ); ?>

    </td>


    <td>

        <?=
            $term_totals['Term-II']['percentage'] >= 33
            ? 'PASS'
            : 'FAIL';
        ?>

    </td>

</tr>


</tbody>

</table>


<!-- =========================================================
     ADDITIONAL SUBJECTS
     ========================================================= -->

<div class="section-title">

    ADDITIONAL SUBJECTS

</div>


<table class="additional-table">

<tr>

    <th>
        Subject
    </th>

    <th>
        Term-I
    </th>

    <th>
        Term-II
    </th>

</tr>


<?php foreach (
    $additional_subject_names
    as $subject_name
): ?>


<?php

$term1 =
    findAdditionalSubject(
        $subjects,
        $subject_name,
        $scholastic['Term-I']
    );


$term2 =
    findAdditionalSubject(
        $subjects,
        $subject_name,
        $scholastic['Term-II']
    );

?>


<tr>

    <td>
        <?= e($subject_name); ?>
    </td>


    <td>

        <?php if ($term1): ?>

            <strong>
                <?= e(
                    $term1['grade']
                ); ?>
            </strong>

        <?php else: ?>

            —

        <?php endif; ?>

    </td>


    <td>

        <?php if ($term2): ?>

            <strong>
                <?= e(
                    $term2['grade']
                ); ?>
            </strong>

        <?php else: ?>

            —

        <?php endif; ?>

    </td>

</tr>


<?php endforeach; ?>


</table>


<!-- =========================================================
     COGNITIVE SKILLS
     ========================================================= -->

<div class="section-title">

    LIFE SKILLS

</div>


<table class="observation-table">

<tr>

    <th>
        Observation
    </th>

    <th>
        Term-I
    </th>

    <th>
        Term-II
    </th>

</tr>


<?php foreach (
    $observation_categories['Cognitive Skills']
    as $item
): ?>

<tr>

    <td>
        <?= e($item); ?>
    </td>

    <td>

        <?= e(
            $observation_values
                ['Cognitive Skills']
                [$item]
                ['term1']
        ); ?>

    </td>

    <td>

        <?= e(
            $observation_values
                ['Cognitive Skills']
                [$item]
                ['term2']
        ); ?>

    </td>

</tr>

<?php endforeach; ?>


</table>


<!-- =========================================================
     SOCIAL EMOTIONAL ETHICS
     ========================================================= -->

<div class="section-title">

    SOCIAL AND INTERPERSONAL SKILLS

</div>


<table class="observation-table">

<tr>

    <th>
        Observation
    </th>

    <th>
        Term-I
    </th>

    <th>
        Term-II
    </th>

</tr>


<?php foreach (
    $observation_categories
        ['Social and Interpersonal Skills']
    as $item
): ?>

<tr>

    <td>
        <?= e($item); ?>
    </td>

    <td>

        <?= e(
            $observation_values
                ['Social and Interpersonal Skills']
                [$item]
                ['term1']
        ); ?>

    </td>

    <td>

        <?= e(
            $observation_values
                ['Social and Interpersonal Skills']
                [$item]
                ['term2']
        ); ?>

    </td>

</tr>

<?php endforeach; ?>


</table>


<!-- =========================================================
     AESTHETIC AND CULTURAL DEVELOPMENT
     ========================================================= -->

<div class="section-title">

    AESTHETIC AND CULTURAL DEVELOPMENT

</div>


<table class="observation-table">

<tr>

    <th>
        Observation
    </th>

    <th>
        Term-I
    </th>

    <th>
        Term-II
    </th>

</tr>


<?php foreach (
    $observation_categories
        ['Aesthetic and Cultural Development']
    as $item
): ?>

<tr>

    <td>
        <?= e($item); ?>
    </td>

    <td>

        <?= e(
            $observation_values
                ['Aesthetic and Cultural Development']
                [$item]
                ['term1']
        ); ?>

    </td>

    <td>

        <?= e(
            $observation_values
                ['Aesthetic and Cultural Development']
                [$item]
                ['term2']
        ); ?>

    </td>

</tr>

<?php endforeach; ?>


</table>


<!-- =========================================================
     SELF-MANAGEMENT SKILLS
     ========================================================= -->

<div class="section-title">

    SELF-MANAGEMENT SKILLS

</div>


<table class="observation-table">

<tr>

    <th>
        Observation
    </th>

    <th>
        Term-I
    </th>

    <th>
        Term-II
    </th>

</tr>


<?php foreach (
    $observation_categories
        ['Self-Management Skills']
    as $item
): ?>

<tr>

    <td>

        <?= e(
            $item
        ); ?>

    </td>

    <td>

        <?= e(
            $observation_values
                ['Self-Management Skills']
                [$item]
                ['term1']
        ); ?>

    </td>

    <td>

        <?= e(
            $observation_values
                ['Self-Management Skills']
                [$item]
                ['term2']
        ); ?>

    </td>

</tr>

<?php endforeach; ?>


</table>


<!-- =========================================================
     SIGNATURES
     ========================================================= -->

<table class="signature-table">

<tr>

    <td>
        Teacher Sign
    </td>

    <td>
        Examination Sign
    </td>

    <td>
        Principal Sign
    </td>

</tr>

</table>


<!-- =========================================================
     FOOTER
     ========================================================= -->

<div class="footer">

    This is a computer-generated Holistic Progress Card.

    <br>

    Academic Session:
    <?= e($session_name); ?>

</div>


</div>


</body>

</html>