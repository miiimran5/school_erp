<?php
/**
 * SCHOOL ERP
 * T-037 - CBSE Result Generator
 * Step 3 - Report Card Preview
 *
 * File:
 * modules/results/report_card.php
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/access.php';
require_once '../../includes/grading.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}
access_require_roles(array('Admin', 'Teacher', 'Student'));
access_require_feature($conn, 'reports');
if (access_is_student() && (int)($_GET['student_id'] ?? 0) !== access_student_id()) {
    http_response_code(403);
    exit('You can only view your own report card.');
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
}

$conn->set_charset("utf8mb4");


/* =========================================================
   HELPER
   ========================================================= */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   GRADE FUNCTION
   ========================================================= */

function getGrade($percentage, $class_id = 0)
{
    global $conn;
    return grading_grade_for_percentage($conn, $class_id, $percentage);
}


/* =========================================================
   GET PARAMETERS
   ========================================================= */

$student_id = isset($_GET['student_id'])
    ? (int)$_GET['student_id']
    : 0;

$class_id = isset($_GET['class_id'])
    ? (int)$_GET['class_id']
    : 0;

$section_id = isset($_GET['section_id'])
    ? (int)$_GET['section_id']
    : 0;

$exam_id = isset($_GET['exam_id'])
    ? (int)$_GET['exam_id']
    : 0;


if (
    $student_id <= 0 ||
    $class_id <= 0 ||
    $section_id <= 0 ||
    $exam_id <= 0
) {

    die("
        <div style='
            font-family:Arial;
            padding:40px;
            text-align:center;
        '>

            <h2>Invalid Report Card Request</h2>

            <p>
                Required student, class, section and examination
                information is missing.
            </p>

            <a href='index.php'>
                ← Back to Result Generator
            </a>

        </div>
    ");
}


/* =========================================================
   STUDENT
   ========================================================= */

$student_stmt = $conn->prepare("
    SELECT
        id,
        admission_no,
        admission_date,
        class_id,
        section_id,
        roll_no,
        student_name,
        gender,
        dob,
        father_name,
        mother_name,
        additional_subject_id,
        photo
    FROM students
    WHERE id = ?
      AND class_id = ?
      AND section_id = ?
      AND status = 1
    LIMIT 1
");

$student_stmt->bind_param(
    "iii",
    $student_id,
    $class_id,
    $section_id
);

$student_stmt->execute();

$student_result = $student_stmt->get_result();

if ($student_result->num_rows === 0) {
    die("Student not found.");
}

$student = $student_result->fetch_assoc();

$student_stmt->close();


/* =========================================================
   CLASS
   ========================================================= */

$class_stmt = $conn->prepare("
    SELECT class_name
    FROM classes
    WHERE id = ?
    LIMIT 1
");

$class_stmt->bind_param(
    "i",
    $class_id
);

$class_stmt->execute();

$class_data =
    $class_stmt
        ->get_result()
        ->fetch_assoc();

$class_stmt->close();

$class_name =
    $class_data['class_name'] ?? '';


/* =========================================================
   SECTION
   ========================================================= */

$section_stmt = $conn->prepare("
    SELECT section_name
    FROM sections
    WHERE id = ?
    LIMIT 1
");

$section_stmt->bind_param(
    "i",
    $section_id
);

$section_stmt->execute();

$section_data =
    $section_stmt
        ->get_result()
        ->fetch_assoc();

$section_stmt->close();

$section_name =
    $section_data['section_name'] ?? '';


/* =========================================================
   EXAM
   ========================================================= */

$exam_stmt = $conn->prepare("
    SELECT
        id,
        exam_name,
        start_date,
        end_date
    FROM exams
    WHERE id = ?
    LIMIT 1
");

$exam_stmt->bind_param(
    "i",
    $exam_id
);

$exam_stmt->execute();

$exam_data =
    $exam_stmt
        ->get_result()
        ->fetch_assoc();

$exam_stmt->close();

if (!$exam_data) {
    die("Examination not found.");
}

$exam_name =
    $exam_data['exam_name'];


/* =========================================================
   MARKS
   ========================================================= */

$marks = [];

$marks_stmt = $conn->prepare("
    SELECT
        m.subject_id,
        m.max_marks,
        m.obtained_marks,
        m.grade,
        m.remarks,

        s.subject_name,
        s.subject_code,
        s.subject_type

    FROM marks m

    INNER JOIN subjects s
        ON s.id = m.subject_id

    WHERE m.student_id = ?
      AND m.class_id = ?
      AND m.section_id = ?
      AND m.exam_id = ?

    ORDER BY
        s.subject_type ASC,
        s.id ASC
");

$marks_stmt->bind_param(
    "iiii",
    $student_id,
    $class_id,
    $section_id,
    $exam_id
);

$marks_stmt->execute();

$marks_result =
    $marks_stmt->get_result();

while ($row = $marks_result->fetch_assoc()) {

    $marks[] = $row;
}

$marks_stmt->close();

$configured_ids = array();
$class_setting_stmt = $conn->prepare('SELECT 1 FROM report_card_class_settings WHERE class_id=?');
$class_setting_stmt->bind_param('i', $class_id);
$class_setting_stmt->execute();
$has_class_subject_config = $class_setting_stmt->get_result()->num_rows > 0;
$class_setting_stmt->close();
if ($has_class_subject_config) {
    $class_subject_stmt = $conn->prepare('SELECT subject_id FROM report_card_class_subjects WHERE class_id=?');
    $class_subject_stmt->bind_param('i', $class_id);
    $class_subject_stmt->execute();
    $class_subject_result = $class_subject_stmt->get_result();
    while ($class_subject_row = $class_subject_result->fetch_assoc()) {
        $configured_ids[(int)$class_subject_row['subject_id']] = true;
    }
    $class_subject_stmt->close();
} else {
    $group_order_stmt = $conn->prepare('SELECT class_order FROM classes WHERE id=?');
    $group_order_stmt->bind_param('i', $class_id);
    $group_order_stmt->execute();
    $group_order = (int)($group_order_stmt->get_result()->fetch_assoc()['class_order'] ?? 0);
    $group_order_stmt->close();
    $report_group = $group_order <= 3 ? 'nursery-ukg' : ($group_order <= 6 ? 'class-1-3' : ($group_order <= 8 ? 'class-4-5' : ($group_order <= 11 ? 'class-6-8' : ($group_order <= 13 ? 'class-9-10' : 'class-11-12'))));
    $group_stmt = $conn->prepare('SELECT subject_id FROM report_card_group_subjects WHERE group_key=? AND status=1');
    $group_stmt->bind_param('s', $report_group);
    $group_stmt->execute();
    $group_result = $group_stmt->get_result();
    while ($group_row = $group_result->fetch_assoc()) {
        $configured_ids[(int)$group_row['subject_id']] = true;
    }
    $group_stmt->close();
}
if ($has_class_subject_config || !empty($configured_ids)) {
    $marks = array_values(array_filter($marks, function ($mark) use ($configured_ids) {
        return isset($configured_ids[(int)$mark['subject_id']]);
    }));
}


/* =========================================================
   CALCULATIONS
   ========================================================= */

$total_max = 0;
$total_obtained = 0;

$subject_count = 0;
$failed_subjects = 0;

foreach ($marks as &$mark) {

    $mark['is_additional'] = (int)$mark['subject_id'] === (int)($student['additional_subject_id'] ?? 0);
    $max =
        (float)$mark['max_marks'];

    $obtained =
        (float)$mark['obtained_marks'];

    $percentage = 0;

    if ($max > 0) {

        $percentage =
            ($obtained / $max) * 100;
    }

    $mark['percentage'] =
        $percentage;

    if (!empty($mark['grade']) && $max <= 0) {
        $mark['display_grade'] = $mark['grade'];
        $mark['percentage'] = null;
        $mark['result'] = $mark['grade'] === 'E' ? 'FAIL' : 'PASS';
        if (!$mark['is_additional'] && $mark['result'] === 'FAIL') {
            $failed_subjects++;
        }
        if (!$mark['is_additional']) {
            $subject_count++;
        }
        continue;
    }
    $mark['display_grade'] = getGrade($percentage, $class_id);
    $mark['grade'] = $mark['display_grade'];
    if ($percentage < 33) {

        $mark['result'] = 'FAIL';

        if (!$mark['is_additional']) {
            $failed_subjects++;
        }

    } else {

        $mark['result'] = 'PASS';
    }

    if (!$mark['is_additional']) {
        $total_max += $max;
        $total_obtained += $obtained;
        $subject_count++;
    }
}

unset($mark);


/* =========================================================
   OVERALL CALCULATIONS
   ========================================================= */

$overall_percentage = null;

if ($total_max > 0) {

    $overall_percentage =
        ($total_obtained / $total_max) * 100;
}

$overall_grade = $total_max > 0 ? getGrade($overall_percentage, $class_id) : ($subject_count > 0 ? 'Graded' : 'N/A');


/*
 * Overall result
 */

if (
    $subject_count > 0 &&
    $failed_subjects === 0
) {

    $overall_result = 'PASS';

} else {

    $overall_result = 'FAIL';
}


/*
 * No marks
 */

$has_marks =
    count($marks) > 0;


/* =========================================================
   PHOTO
   ========================================================= */

$photo_path = '';

if (!empty($student['photo'])) {

    $possible_path =
        '../../uploads/students/' .
        $student['photo'];

    if (file_exists($possible_path)) {

        $photo_path =
            $possible_path;
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
        Report Card - <?= e($student['student_name']); ?>
    </title>

    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/app.css">


    <style>

        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            background: #e9ecef;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            color: #111;
        }


        .toolbar {

            background: #343a40;

            padding: 15px;

            text-align: center;

            position: sticky;

            top: 0;

            z-index: 1000;
        }


        .toolbar a,
        .toolbar button {

            display: inline-block;

            padding: 10px 18px;

            margin: 0 4px;

            border: 0;

            border-radius: 4px;

            text-decoration: none;

            cursor: pointer;

            font-size: 14px;
        }


        .btn-back {

            background: #6c757d;

            color: white;
        }


        .btn-print {

            background: #007bff;

            color: white;
        }


        .btn-pdf {

            background: #dc3545;

            color: white;
        }


        .btn-excel {

            background: #28a745;

            color: white;
        }


        .btn-word {

            background: #17a2b8;

            color: white;
        }


        .report-card {

            width: 210mm;

            min-height: 297mm;

            margin: 25px auto;

            background: white;

            padding: 18mm;

            box-shadow:
                0 2px 12px
                rgba(0,0,0,0.18);
        }


        .school-header {

            text-align: center;

            border-bottom:
                2px solid #000;

            padding-bottom: 12px;

            margin-bottom: 15px;
        }


        .school-name {

            font-size: 26px;

            font-weight: bold;

            text-transform: uppercase;
        }


        .school-address {

            font-size: 13px;

            margin-top: 5px;
        }


        .report-title {

            font-size: 20px;

            font-weight: bold;

            margin-top: 12px;

            text-transform: uppercase;
        }


        .exam-name {

            font-size: 16px;

            font-weight: bold;

            margin-top: 5px;
        }


        .student-section {

            display: flex;

            justify-content: space-between;

            border: 1px solid #000;

            padding: 12px;

            margin-bottom: 15px;
        }


        .student-details {

            width: 80%;

            line-height: 1.8;

            font-size: 14px;
        }


        .student-photo {

            width: 90px;

            height: 105px;

            border: 1px solid #555;

            display: flex;

            align-items: center;

            justify-content: center;

            overflow: hidden;
        }


        .student-photo img {

            width: 100%;

            height: 100%;

            object-fit: cover;
        }


        .marks-table {

            width: 100%;

            border-collapse: collapse;

            margin-top: 10px;

            font-size: 12px;
        }


        .marks-table th,
        .marks-table td {

            border: 1px solid #000;

            padding: 7px;

            text-align: center;
        }


        .marks-table th {

            background: #f0f0f0;

            font-weight: bold;
        }


        .marks-table td:first-child {

            text-align: left;
        }


        .fail {

            color: #b00000;

            font-weight: bold;
        }


        .pass {

            color: #006400;

            font-weight: bold;
        }


        .summary-table {

            width: 100%;

            border-collapse: collapse;

            margin-top: 18px;

            font-size: 13px;
        }


        .summary-table th,
        .summary-table td {

            border: 1px solid #000;

            padding: 9px;

            text-align: center;
        }


        .summary-table th {

            background: #f0f0f0;
        }


        .result-box {

            margin-top: 18px;

            padding: 12px;

            border: 2px solid #000;

            text-align: center;

            font-size: 17px;

            font-weight: bold;
        }


        .result-pass {

            color: #006400;
        }


        .result-fail {

            color: #b00000;
        }


        .grading-table {

            width: 100%;

            border-collapse: collapse;

            margin-top: 18px;

            font-size: 11px;
        }


        .grading-table th,
        .grading-table td {

            border: 1px solid #000;

            padding: 5px;

            text-align: center;
        }


        .grading-table th {

            background: #f0f0f0;
        }


        .signature-area {

            display: flex;

            justify-content: space-between;

            margin-top: 70px;

            font-size: 13px;
        }


        .signature {

            width: 25%;

            text-align: center;

            border-top: 1px solid #000;

            padding-top: 7px;
        }


        .footer-note {

            text-align: center;

            margin-top: 30px;

            font-size: 10px;

            color: #555;
        }


        .no-marks {

            padding: 30px;

            text-align: center;

            border: 1px solid #000;

            margin-top: 15px;
        }


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


            .report-card {

                width: 210mm;

                min-height: 297mm;

                margin: 0;

                padding: 15mm;

                box-shadow: none;
            }
        }


        @media screen and (max-width: 900px) {

            .report-card {

                width: 95%;

                min-height: auto;

                padding: 20px;
            }


            .student-section {

                flex-direction: column;
            }


            .student-details {

                width: 100%;
            }


            .student-photo {

                margin-top: 10px;
            }
        }

    </style>

</head>


<body>


<!-- =====================================================
     TOOLBAR
     ===================================================== -->

<div class="toolbar">

    <a
        href="generate.php?class_id=<?= $class_id; ?>&section_id=<?= $section_id; ?>&exam_id=<?= $exam_id; ?>"
        class="btn-back"
    >
        ← Back
    </a>


    <button
        onclick="window.print()"
        class="btn-print"
    >
        🖨 Print Report Card
    </button>


    <!-- Future exporters -->

    <a
        href="#"
        class="btn-pdf"
        onclick="alert('PDF export will be connected in the next export step.'); return false;"
    >
        PDF
    </a>


    <a
        href="#"
        class="btn-excel"
        onclick="alert('Excel export will be connected in the next export step.'); return false;"
    >
        Excel
    </a>


    <a
        href="#"
        class="btn-word"
        onclick="alert('Word export will be connected in the next export step.'); return false;"
    >
        Word
    </a>

</div>


<!-- =====================================================
     REPORT CARD
     ===================================================== -->

<div class="report-card">


    <!-- SCHOOL HEADER -->

    <div class="school-header">

        <div class="school-name">

            OASIS PUBLIC SCHOOL

        </div>


        <div class="school-address">

            MALERKOTLA

        </div>


        <div class="report-title">

            STUDENT REPORT CARD

        </div>


        <div class="exam-name">

            <?= e($exam_name); ?>

        </div>

    </div>


    <!-- STUDENT INFORMATION -->

    <div class="student-section">


        <div class="student-details">

            <div>

                <strong>
                    Student Name:
                </strong>
                <?= e(
                    $student['student_name']
                ); ?>

            </div>


            <div>

                <strong>
                    Admission No:
                </strong>

                <?= e(
                    $student['admission_no']
                ); ?>

                &nbsp;&nbsp;&nbsp;

                <strong>
                    Roll No:
                </strong>

                <?= e(
                    $student['roll_no']
                ); ?>

            </div>


            <div>

                <strong>
                    Class:
                </strong>

                <?= e($class_name); ?>

                &nbsp;&nbsp;&nbsp;

                <strong>
                    Section:
                </strong>

                <?= e($section_name); ?>

            </div>


            <div>

                <strong>
                    Father's Name:
                </strong>

                <?= e(
                    $student['father_name']
                ); ?>

            </div>


            <div>

                <strong>
                    Mother's Name:
                </strong>

                <?= e(
                    $student['mother_name']
                ); ?>

            </div>


            <div>

                <strong>
                    Date of Birth:
                </strong>

                <?= e(
                    $student['dob']
                ); ?>

            </div>

        </div>


        <?php if (!empty($photo_path)): ?>

            <div class="student-photo">

                <img
                    src="<?= e($photo_path); ?>"
                    alt="Student Photo"
                >

            </div>

        <?php else: ?>

            <div class="student-photo">

                Photo

            </div>

        <?php endif; ?>


    </div>


    <!-- =====================================================
         MARKS
         ===================================================== -->

    <?php if (!$has_marks): ?>

        <div class="no-marks">

            <strong>
                No marks have been entered for this student
                for the selected examination.
            </strong>

        </div>

    <?php else: ?>


        <table class="marks-table">

            <thead>

            <tr>

                <th style="width:30px;">
                    S.No.
                </th>

                <th>
                    Subject
                </th>

                <th>
                    Max Marks
                </th>

                <th>
                    Marks Obtained
                </th>

                <th>
                    %
                </th>

                <th>
                    Grade
                </th>

                <th>
                    Result
                </th>

                <th>
                    Remarks
                </th>

            </tr>

            </thead>


            <tbody>


            <?php

            $serial = 1;

            foreach ($marks as $mark):

            ?>


                <tr>

                    <td>
                        <?= $serial++; ?>
                    </td>


                    <td>

                        <strong>

                            <?= e(
                                $mark['subject_name']
                            ); ?>

                        </strong>
                        <?php if (!empty($mark['is_additional'])): ?><span class="badge badge-info">Additional - excluded from total</span><?php endif; ?>


                    </td>


                    <td>

                        <?= (float)$mark['max_marks'] > 0 ? number_format((float)$mark['max_marks'], 2) : '-'; ?>

                    </td>


                    <td>

                        <?= (float)$mark['max_marks'] > 0 ? number_format((float)$mark['obtained_marks'], 2) : '-'; ?>

                    </td>


                    <td>

                        <?= $mark['percentage'] === null ? '-' : number_format((float)$mark['percentage'], 2) . '%'; ?>

                    </td>


                    <td>

                        <strong>

                            <?= e(
                                $mark['grade']
                            ); ?>

                        </strong>

                    </td>


                    <td>

                        <?php if (
                            $mark['result']
                            === 'PASS'
                        ): ?>

                            <span class="pass">

                                PASS

                            </span>

                        <?php else: ?>

                            <span class="fail">

                                FAIL

                            </span>

                        <?php endif; ?>

                    </td>


                    <td>

                        <?= e(
                            $mark['remarks']
                        ); ?>

                    </td>

                </tr>


            <?php endforeach; ?>


            <!-- TOTAL -->

            <tr>

                <th colspan="2">
                    TOTAL
                </th>

                <th>

                    <?= $total_max > 0 ? number_format($total_max, 2) : '-'; ?>

                </th>

                <th>

                    <?= $total_max > 0 ? number_format($total_obtained, 2) : '-'; ?>

                </th>

                <th>

                    <?= $overall_percentage === null ? '-' : number_format((float)$overall_percentage, 2) . '%'; ?>

                </th>

                <th>

                    <?= e(
                        $overall_grade
                    ); ?>

                </th>

                <th
                    colspan="2"
                    class="<?= $overall_result === 'PASS'
                        ? 'pass'
                        : 'fail'; ?>"
                >

                    <?= $overall_result; ?>

                </th>

            </tr>


            </tbody>

        </table>


        <!-- =================================================
             RESULT SUMMARY
             ================================================= -->

        <table class="summary-table">

            <tr>

                <th>
                    Total Subjects
                </th>

                <th>
                    Maximum Marks
                </th>

                <th>
                    Marks Obtained
                </th>

                <th>
                    Percentage
                </th>

                <th>
                    Overall Grade
                </th>

            </tr>


            <tr>

                <td>
                    <?= $subject_count; ?>
                </td>

                <td>
                    <?= number_format(
                        $total_max,
                        2
                    ); ?>
                </td>

                <td>
                    <?= number_format(
                        $total_obtained,
                        2
                    ); ?>
                </td>

                <td>
                    <?= number_format(
                        $overall_percentage,
                        2
                    ); ?>%
                </td>

                <td>
                    <?= e(
                        $overall_grade
                    ); ?>
                </td>

            </tr>

        </table>


        <!-- =================================================
             FINAL RESULT
             ================================================= -->

        <div class="result-box">

            FINAL RESULT:

            <?php if (
                $overall_result === 'PASS'
            ): ?>

                <span class="result-pass">

                    PASS

                </span>

            <?php else: ?>

                <span class="result-fail">

                    FAIL

                </span>

            <?php endif; ?>

        </div>


    <?php endif; ?>


    <!-- =====================================================
         GRADING SCALE
         ===================================================== -->

    <table class="grading-table">

        <thead>

        <tr>

            <th>
                Percentage
            </th>

            <th>
                91–100
            </th>

            <th>
                81–90
            </th>

            <th>
                71–80
            </th>

            <th>
                61–70
            </th>

            <th>
                51–60
            </th>

            <th>
                41–50
            </th>

            <th>
                33–40
            </th>

            <th>
                Below 33
            </th>

        </tr>

        </thead>


        <tbody>

        <tr>

            <th>
                Grade
            </th>

            <td>A1</td>

            <td>A2</td>

            <td>B1</td>

            <td>B2</td>

            <td>C1</td>

            <td>C2</td>

            <td>D</td>

            <td>E</td>

        </tr>

        </tbody>

    </table>


    <!-- =====================================================
         SIGNATURES
         ===================================================== -->

    <div class="signature-area">

        <div class="signature">

            Class Teacher

        </div>


        <div class="signature">

            Examination Incharge

        </div>


        <div class="signature">

            Principal

        </div>

    </div>


    <div class="footer-note">

        This is a computer-generated result report.

    </div>


</div>


</body>

</html>