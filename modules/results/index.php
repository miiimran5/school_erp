<?php
/**
 * SCHOOL ERP
 * T-037 - CBSE Result Generator
 *
 * File:
 * modules/results/index.php
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/access.php';
require_once '../../includes/school.php';
access_require_login();
access_require_roles(array('Admin', 'Teacher'));
access_require_feature($conn, 'reports');

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not found.");
}

$conn->set_charset("utf8mb4");


/* =========================================================
   LOAD CLASSES
   ========================================================= */

$classes = [];

$class_query = $conn->query("
    SELECT id, class_name
    FROM classes
    WHERE status = 1
    ORDER BY class_order ASC, id ASC
");

if ($class_query) {
    while ($row = $class_query->fetch_assoc()) {
        if (!access_is_teacher() || access_teacher_can_class($conn, (int)$row['id'], 0)) {
            $classes[] = $row;
        }
    }
}


/* =========================================================
   LOAD SECTIONS
   ========================================================= */

$sections = [];

$section_query = $conn->query("
    SELECT id, section_name
    FROM sections
    WHERE status = 1
    ORDER BY id ASC
");

if ($section_query) {
    while ($row = $section_query->fetch_assoc()) {
        $sections[] = $row;
    }
}


/* =========================================================
   LOAD EXAMS
   ========================================================= */

$exams = [];

$current_session_id = access_current_academic_session($conn);
$exam_query = $conn->prepare('SELECT id,exam_name FROM exams WHERE status=1 AND (?=0 OR session_id=?) ORDER BY id DESC');

if ($exam_query) {
    $exam_query->bind_param('ii', $current_session_id, $current_session_id);
    $exam_query->execute();
    $exam_result = $exam_query->get_result();
    while ($row = $exam_result->fetch_assoc()) {
        $exams[] = $row;
    }
    $exam_query->close();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>CBSE Result Generator - School ERP</title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/adminlte.min.css">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/app.css">

    <style>

        .page-wrapper {
            min-height: 100vh;
            padding: 30px;
        }

        .page-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .page-subtitle {
            color: #6c757d;
            margin-bottom: 25px;
        }

        .generator-card {
            max-width: 850px;
            margin: 0 auto;
            border: none;
            border-radius: 8px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
        }

        .generator-card .card-header {
            background: #007bff;
            color: white;
            font-size: 20px;
            font-weight: 600;
            padding: 18px 22px;
        }

        .generator-card .card-body {
            padding: 30px;
        }

        .form-label {
            font-weight: 600;
        }

        .generate-btn {
            font-size: 17px;
            padding: 11px 30px;
        }

        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px 18px;
            margin-top: 25px;
        }

        .step-number {
            display: inline-flex;
            width: 32px;
            height: 32px;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #007bff;
            color: white;
            font-weight: bold;
            margin-right: 8px;
        }

    </style>

</head>

<body class="hold-transition sidebar-mini layout-fixed app-body">
<div class="wrapper">
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>
<div class="content-wrapper">
<section class="content-header">
<div class="container-fluid">

    <!-- PAGE HEADER -->

    <div class="text-center">

        <h2 class="page-title">

            <i class="fas fa-chart-line"></i>

            CBSE Result Generator

        </h2>

        <div class="page-subtitle">

            School ERP — T-037

        </div>

    </div>

    </div>
    </section>
    <section class="content">
    <div class="container-fluid">

    <!-- GENERATOR CARD -->

    <div class="card generator-card">

        <div class="card-header">

            <i class="fas fa-file-alt"></i>

            Generate Student Results

        </div>


        <div class="card-body">

            <form
                method="GET"
                action="generate.php"
            >

                <!-- CLASS -->

                <div class="form-group">

                    <label
                        for="class_id"
                        class="form-label"
                    >

                        <span class="step-number">1</span>

                        Select Class

                    </label>

                    <select
                        name="class_id"
                        id="class_id"
                        class="form-control form-control-lg"
                        required
                    >

                        <option value="">
                            -- Select Class --
                        </option>

                        <?php foreach ($classes as $class): ?>

                            <option
                                value="<?= (int)$class['id']; ?>"
                            >

                                <?= htmlspecialchars(
                                    $class['class_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- SECTION -->

                <div class="form-group">

                    <label
                        for="section_id"
                        class="form-label"
                    >

                        <span class="step-number">2</span>

                        Select Section

                    </label>

                    <select
                        name="section_id"
                        id="section_id"
                        class="form-control form-control-lg"
                        required
                    >

                        <option value="">
                            -- Select Section --
                        </option>

                        <?php foreach ($sections as $section): ?>

                            <option
                                value="<?= (int)$section['id']; ?>"
                            >

                                <?= htmlspecialchars(
                                    $section['section_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- EXAM -->

                <div class="form-group">

                    <label
                        for="exam_id"
                        class="form-label"
                    >

                        <span class="step-number">3</span>

                        Select Examination

                    </label>

                    <select
                        name="exam_id"
                        id="exam_id"
                        class="form-control form-control-lg"
                        required
                    >

                        <option value="">
                            -- Select Examination --
                        </option>

                        <?php foreach ($exams as $exam): ?>

                            <option
                                value="<?= (int)$exam['id']; ?>"
                            >

                                <?= htmlspecialchars(
                                    $exam['exam_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">
                    <label for="result_scope" class="form-label">
                        <span class="step-number">4</span>
                        Result to Show Parents
                    </label>
                    <select name="result_scope" id="result_scope" class="form-control form-control-lg" required>
                        <option value="term1">Term-1 only (PA-1, PA-2 and Term-1)</option>
                        <option value="both">Both Term-1 and Term-2</option>
                    </select>
                    <small class="form-text text-muted">
                        Keep Term-1 only until Term-2 marks are complete. This prevents incomplete Term-2 marks appearing as zero.
                    </small>
                </div>

                <!-- BUTTON -->

                <div class="text-center mt-4">

                    <button
                        type="submit"
                        class="btn btn-primary generate-btn"
                    >

                        <i class="fas fa-calculator"></i>

                        Generate Result

                    </button>

                </div>

            </form>


            <!-- INFORMATION -->

            <div class="info-box">

                <strong>
                    <i class="fas fa-info-circle"></i>
                    T-037
                </strong>

                <p class="mb-0 mt-2">

                    Select the Class, Section and Examination.
                    The next stage will calculate subject-wise
                    marks, total, percentage, grades and
                    Pass/Fail status.

                </p>

            </div>

        </div>

    </div>

</div>
</div>
</section>
</div>
<?php include '../../includes/footer.php'; ?>