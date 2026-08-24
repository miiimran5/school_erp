<?php
/**
 * SCHOOL ERP
 * T-037 - CBSE Result Generator
 * Step 2 - Generate Result
 *
 * File:
 * modules/results/generate.php
 */

require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
}

$conn->set_charset("utf8mb4");


/* =========================================================
   HELPER FUNCTION
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
   GET PARAMETERS
   ========================================================= */

$class_id = isset($_GET['class_id'])
    ? (int)$_GET['class_id']
    : 0;

$section_id = isset($_GET['section_id'])
    ? (int)$_GET['section_id']
    : 0;

$exam_id = isset($_GET['exam_id'])
    ? (int)$_GET['exam_id']
    : 0;


/* =========================================================
   VALIDATE PARAMETERS
   ========================================================= */

if ($class_id <= 0 || $section_id <= 0 || $exam_id <= 0) {

    die("
        <div style='
            font-family:Arial;
            padding:30px;
            text-align:center;
        '>

            <h2>Invalid Result Request</h2>

            <p>
                Please select Class, Section and Examination.
            </p>

            <a href='index.php'>
                ← Back to Result Generator
            </a>

        </div>
    ");
}


/* =========================================================
   GET CLASS
   ========================================================= */

$class_name = '';

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

$class_result = $class_stmt->get_result();

if ($class_result->num_rows === 0) {

    die("Selected class was not found.");
}

$class_data = $class_result->fetch_assoc();

$class_name = $class_data['class_name'];

$class_stmt->close();


/* =========================================================
   GET SECTION
   ========================================================= */

$section_name = '';

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

$section_result = $section_stmt->get_result();

if ($section_result->num_rows === 0) {

    die("Selected section was not found.");
}

$section_data = $section_result->fetch_assoc();

$section_name = $section_data['section_name'];

$section_stmt->close();


/* =========================================================
   GET EXAM
   ========================================================= */

$exam_name = '';

$exam_stmt = $conn->prepare("
    SELECT exam_name
    FROM exams
    WHERE id = ?
    LIMIT 1
");

$exam_stmt->bind_param(
    "i",
    $exam_id
);

$exam_stmt->execute();

$exam_result = $exam_stmt->get_result();

if ($exam_result->num_rows === 0) {

    die("Selected examination was not found.");
}

$exam_data = $exam_result->fetch_assoc();

$exam_name = $exam_data['exam_name'];

$exam_stmt->close();


/* =========================================================
   GET STUDENTS + MARKS
   ========================================================= */

$students = [];

/*
 * We use LEFT JOIN so students still appear even if
 * marks are not entered for every subject.
 */

$student_stmt = $conn->prepare("
    SELECT
        s.id AS student_id,
        s.admission_no,
        s.roll_no,
        s.student_name,
        s.father_name,

        m.id AS mark_id,
        m.subject_id,
        m.max_marks,
        m.obtained_marks,
        m.remarks,

        sub.subject_name,
        sub.subject_code,
        sub.subject_type

    FROM students s

    LEFT JOIN marks m
        ON m.student_id = s.id
        AND m.class_id = s.class_id
        AND m.section_id = s.section_id
        AND m.exam_id = ?

    LEFT JOIN subjects sub
        ON sub.id = m.subject_id

    WHERE s.class_id = ?
      AND s.section_id = ?
      AND s.status = 1

    ORDER BY
        CASE
            WHEN s.roll_no REGEXP '^[0-9]+$'
            THEN CAST(s.roll_no AS UNSIGNED)
            ELSE 999999
        END,
        s.student_name ASC
");


$student_stmt->bind_param(
    "iii",
    $exam_id,
    $class_id,
    $section_id
);

$student_stmt->execute();

$student_result = $student_stmt->get_result();


/* =========================================================
   BUILD STUDENT RESULT ARRAY
   ========================================================= */

while ($row = $student_result->fetch_assoc()) {

    $student_id = (int)$row['student_id'];

    if (!isset($students[$student_id])) {

        $students[$student_id] = [

            'student_id' => $student_id,

            'admission_no' =>
                $row['admission_no'],

            'roll_no' =>
                $row['roll_no'],

            'student_name' =>
                $row['student_name'],

            'father_name' =>
                $row['father_name'],

            'subjects' => [],

            'total_marks' => 0,

            'total_max_marks' => 0,

            'percentage' => 0,

            'grade' => '',

            'result' => 'PASS'
        ];
    }


    /*
     * Add subject only when a mark record exists.
     */

    if (!empty($row['mark_id'])) {

        $max_marks =
            (float)$row['max_marks'];

        $obtained_marks =
            (float)$row['obtained_marks'];

        $students[$student_id]['subjects'][] = [

            'subject_id' =>
                $row['subject_id'],

            'subject_name' =>
                $row['subject_name'],

            'subject_code' =>
                $row['subject_code'],

            'subject_type' =>
                $row['subject_type'],

            'max_marks' =>
                $max_marks,

            'obtained_marks' =>
                $obtained_marks,

            'remarks' =>
                $row['remarks']
        ];

        /*
         * Calculate totals
         */

        $students[$student_id]['total_marks']
            += $obtained_marks;

        $students[$student_id]['total_max_marks']
            += $max_marks;
    }
}

$student_stmt->close();


/* =========================================================
   CALCULATE PERCENTAGE + GRADE + RESULT
   ========================================================= */

foreach ($students as &$student) {

    $total =
        $student['total_marks'];

    $maximum =
        $student['total_max_marks'];


    /*
     * Percentage
     */

    if ($maximum > 0) {

        $student['percentage'] =
            ($total / $maximum) * 100;

    } else {

        $student['percentage'] = 0;
    }


    /*
     * Overall Grade
     *
     * CBSE-style grading scale
     */

    $percentage =
        $student['percentage'];


    if ($percentage >= 91) {

        $student['grade'] = 'A1';

    } elseif ($percentage >= 81) {

        $student['grade'] = 'A2';

    } elseif ($percentage >= 71) {

        $student['grade'] = 'B1';

    } elseif ($percentage >= 61) {

        $student['grade'] = 'B2';

    } elseif ($percentage >= 51) {

        $student['grade'] = 'C1';

    } elseif ($percentage >= 41) {

        $student['grade'] = 'C2';

    } elseif ($percentage >= 33) {

        $student['grade'] = 'D';

    } else {

        $student['grade'] = 'E';
    }


    /*
     * Pass / Fail
     *
     * Current rule:
     * Overall percentage must be at least 33%.
     *
     * Subject-wise fail checking will be added
     * in the next stage.
     */

    if ($percentage < 33) {

        $student['result'] = 'FAIL';

    } else {

        $student['result'] = 'PASS';
    }
}

unset($student);


/* =========================================================
   COUNT STUDENTS
   ========================================================= */

$total_students =
    count($students);


/* =========================================================
   COUNT PASS / FAIL
   ========================================================= */

$pass_count = 0;
$fail_count = 0;

foreach ($students as $student) {

    if ($student['result'] === 'PASS') {

        $pass_count++;

    } else {

        $fail_count++;
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
        Generate Result - School ERP
    </title>


    <!-- Bootstrap -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
    >


    <!-- Font Awesome -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

        body {

            background: #f4f6f9;

            font-family:
                Arial,
                Helvetica,
                sans-serif;
        }


        .page-wrapper {

            padding: 25px;

            min-height: 100vh;
        }


        .result-header {

            background: white;

            border-radius: 8px;

            padding: 20px;

            margin-bottom: 20px;

            box-shadow:
                0 2px 10px
                rgba(0,0,0,0.08);
        }


        .result-header h2 {

            margin-bottom: 5px;

            font-weight: 600;
        }


        .selection-info {

            color: #6c757d;

            font-size: 15px;
        }


        .summary-card {

            border: none;

            box-shadow:
                0 2px 8px
                rgba(0,0,0,0.08);
        }


        .summary-number {

            font-size: 28px;

            font-weight: bold;
        }


        .student-card {

            background: white;

            border-radius: 8px;

            margin-bottom: 25px;

            box-shadow:
                0 2px 10px
                rgba(0,0,0,0.08);

            overflow: hidden;
        }


        .student-header {

            padding: 18px;

            background: #343a40;

            color: white;
        }


        .student-name {

            font-size: 20px;

            font-weight: 600;
        }


        .student-info {

            font-size: 14px;

            margin-top: 5px;
        }


        .student-body {

            padding: 20px;
        }


        .marks-table th {

            background: #f1f3f5;

            text-align: center;

            vertical-align: middle;
        }


        .marks-table td {

            vertical-align: middle;

            text-align: center;
        }


        .marks-table td:first-child {

            text-align: left;
        }


        .total-row {

            font-weight: bold;

            background: #f8f9fa;
        }


        .pass-badge {

            background: #28a745;

            color: white;

            padding: 6px 12px;

            border-radius: 4px;

            font-weight: bold;
        }


        .fail-badge {

            background: #dc3545;

            color: white;

            padding: 6px 12px;

            border-radius: 4px;

            font-weight: bold;
        }


        .grade-badge {

            background: #007bff;

            color: white;

            padding: 6px 12px;

            border-radius: 4px;

            font-weight: bold;
        }


        .no-data {

            text-align: center;

            padding: 50px;

            background: white;

            border-radius: 8px;

            box-shadow:
                0 2px 10px
                rgba(0,0,0,0.08);
        }


        @media print {

            .no-print {

                display: none !important;
            }

            body {

                background: white;
            }

            .page-wrapper {

                padding: 0;
            }

            .student-card {

                box-shadow: none;

                border:
                    1px solid #ddd;

                page-break-inside: avoid;
            }
        }

    </style>

</head>


<body>


<div class="page-wrapper">


    <!-- =====================================================
         HEADER
         ===================================================== -->

    <div class="result-header">

        <div
            class="d-flex
                   justify-content-between
                   align-items-center"
        >

            <div>

                <h2>

                    <i class="fas fa-chart-line"></i>

                    CBSE Result Generator

                </h2>

                <div class="selection-info">

                    <strong>
                        Class:
                    </strong>

                    <?= e($class_name); ?>

                    &nbsp; | &nbsp;

                    <strong>
                        Section:
                    </strong>

                    <?= e($section_name); ?>

                    &nbsp; | &nbsp;

                    <strong>
                        Examination:
                    </strong>

                    <?= e($exam_name); ?>

                </div>

            </div>


            <div class="no-print">

                <a
                    href="index.php"
                    class="btn btn-secondary"
                >

                    <i class="fas fa-arrow-left"></i>

                    Back

                </a>


                <button
                    type="button"
                    onclick="window.print()"
                    class="btn btn-primary"
                >

                    <i class="fas fa-print"></i>

                    Print

                </button>

            </div>

        </div>

    </div>


    <!-- =====================================================
         SUMMARY
         ===================================================== -->

    <div class="row mb-4">


        <!-- TOTAL STUDENTS -->

        <div class="col-md-4">

            <div class="card summary-card">

                <div class="card-body">

                    <div class="text-muted">

                        Total Students

                    </div>

                    <div class="summary-number">

                        <?= $total_students; ?>

                    </div>

                </div>

            </div>

        </div>


        <!-- PASS -->

        <div class="col-md-4">

            <div class="card summary-card">

                <div class="card-body">

                    <div class="text-muted">

                        Pass

                    </div>

                    <div
                        class="summary-number
                               text-success"
                    >

                        <?= $pass_count; ?>

                    </div>

                </div>

            </div>

        </div>


        <!-- FAIL -->

        <div class="col-md-4">

            <div class="card summary-card">

                <div class="card-body">

                    <div class="text-muted">

                        Fail

                    </div>

                    <div
                        class="summary-number
                               text-danger"
                    >

                        <?= $fail_count; ?>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         STUDENT RESULTS
         ===================================================== -->

    <?php if (empty($students)): ?>

        <div class="no-data">

            <i
                class="fas fa-users-slash fa-3x text-muted mb-3"
            ></i>

            <h4>
                No Students Found
            </h4>

            <p class="text-muted">

                No active students were found for:

            </p>

            <strong>

                <?= e($class_name); ?>

                -

                <?= e($section_name); ?>

            </strong>

        </div>

    <?php else: ?>


        <?php foreach ($students as $student): ?>

            <div class="student-card">


                <!-- STUDENT HEADER -->

                <div class="student-header">

                    <div class="student-name">

                        <?= e(
                            $student['student_name']
                        ); ?>

                    </div>


                    <div class="student-info">

                        <strong>
                            Admission No:
                        </strong>

                        <?= e(
                            $student['admission_no']
                        ); ?>

                        &nbsp;&nbsp;

                        <strong>
                            Roll No:
                        </strong>

                        <?= e(
                            $student['roll_no']
                        ); ?>

                        &nbsp;&nbsp;

                        <strong>
                            Father:
                        </strong>

                        <?= e(
                            $student['father_name']
                        ); ?>

                    </div>

                </div>


                <!-- STUDENT BODY -->

                <div class="student-body">


                    <div class="table-responsive">

                        <table
                            class="table
                                   table-bordered
                                   marks-table"
                        >

                            <thead>

                            <tr>

                                <th>
                                    Subject
                                </th>

                                <th>
                                    Max Marks
                                </th>

                                <th>
                                    Obtained
                                </th>

                                <th>
                                    Percentage
                                </th>

                                <th>
                                    Grade
                                </th>

                                <th>
                                    Remarks
                                </th>

                            </tr>

                            </thead>


                            <tbody>


                            <?php if (
                                empty(
                                    $student['subjects']
                                )
                            ): ?>

                                <tr>

                                    <td
                                        colspan="6"
                                        class="text-center
                                               text-muted"
                                    >

                                        No marks entered for
                                        this examination.

                                    </td>

                                </tr>

                            <?php else: ?>


                                <?php foreach (
                                    $student['subjects']
                                    as $subject
                                ): ?>


                                    <?php

                                    $subject_percentage = 0;

                                    if (
                                        $subject['max_marks'] > 0
                                    ) {

                                        $subject_percentage =
                                            (
                                                $subject['obtained_marks']
                                                /
                                                $subject['max_marks']
                                            ) * 100;
                                    }


                                    if (
                                        $subject_percentage >= 91
                                    ) {

                                        $subject_grade = 'A1';

                                    } elseif (
                                        $subject_percentage >= 81
                                    ) {

                                        $subject_grade = 'A2';

                                    } elseif (
                                        $subject_percentage >= 71
                                    ) {

                                        $subject_grade = 'B1';

                                    } elseif (
                                        $subject_percentage >= 61
                                    ) {

                                        $subject_grade = 'B2';

                                    } elseif (
                                        $subject_percentage >= 51
                                    ) {

                                        $subject_grade = 'C1';

                                    } elseif (
                                        $subject_percentage >= 41
                                    ) {

                                        $subject_grade = 'C2';

                                    } elseif (
                                        $subject_percentage >= 33
                                    ) {

                                        $subject_grade = 'D';

                                    } else {

                                        $subject_grade = 'E';
                                    }

                                    ?>


                                    <tr>

                                        <td>

                                            <strong>

                                                <?= e(
                                                    $subject['subject_name']
                                                ); ?>

                                            </strong>


                                            <?php if (
                                                !empty(
                                                    $subject['subject_code']
                                                )
                                            ): ?>

                                                <small
                                                    class="text-muted"
                                                >

                                                    (
                                                    <?= e(
                                                        $subject['subject_code']
                                                    ); ?>
                                                    )

                                                </small>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <?= number_format(
                                                $subject['max_marks'],
                                                2
                                            ); ?>

                                        </td>


                                        <td>

                                            <strong>

                                                <?= number_format(
                                                    $subject['obtained_marks'],
                                                    2
                                                ); ?>

                                            </strong>

                                        </td>


                                        <td>

                                            <?= number_format(
                                                $subject_percentage,
                                                2
                                            ); ?>%

                                        </td>


                                        <td>

                                            <span
                                                class="grade-badge"
                                            >

                                                <?= e(
                                                    $subject_grade
                                                ); ?>

                                            </span>

                                        </td>


                                        <td>

                                            <?= e(
                                                $subject['remarks']
                                            ); ?>

                                        </td>

                                    </tr>


                                <?php endforeach; ?>


                            <?php endif; ?>


                            <!-- TOTAL -->

                            <tr class="total-row">

                                <td>
                                    TOTAL
                                </td>

                                <td>

                                    <?= number_format(
                                        $student['total_max_marks'],
                                        2
                                    ); ?>

                                </td>

                                <td>

                                    <?= number_format(
                                        $student['total_marks'],
                                        2
                                    ); ?>

                                </td>

                                <td>

                                    <?= number_format(
                                        $student['percentage'],
                                        2
                                    ); ?>%

                                </td>

                                <td>

                                    <span
                                        class="grade-badge"
                                    >

                                        <?= e(
                                            $student['grade']
                                        ); ?>

                                    </span>

                                </td>

                                <td>

                                    <?php if (
                                        $student['result']
                                        === 'PASS'
                                    ): ?>

                                        <span
                                            class="pass-badge"
                                        >

                                            PASS

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="fail-badge"
                                        >

                                            FAIL

                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>


                            </tbody>

                        </table>

                    </div>

                </div>

            </div>


        <?php endforeach; ?>


    <?php endif; ?>


</div>


<!-- Bootstrap -->

<script
    src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js">
</script>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>