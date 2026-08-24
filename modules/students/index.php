<?php
/**
 * ============================================================
 * SCHOOL ERP
 * Students Module
 * File: modules/students/index.php
 * ============================================================
 */

require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");


/* ============================================================
   BASE URL
   ============================================================ */

if (!defined('BASE_URL')) {
    define('BASE_URL', '/school_erp');
}


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


/* ============================================================
   DELETE STUDENT
   ============================================================ */

if (
    isset($_GET['delete']) &&
    is_numeric($_GET['delete'])
) {

    $delete_id = (int)$_GET['delete'];

    if ($delete_id > 0) {

        $stmt = $conn->prepare("
            DELETE FROM students
            WHERE id = ?
            LIMIT 1
        ");

        if ($stmt) {

            $stmt->bind_param(
                "i",
                $delete_id
            );

            $stmt->execute();

            $stmt->close();
        }
    }

    header(
        "Location: " .
        BASE_URL .
        "/modules/students/index.php"
    );

    exit;
}


/* ============================================================
   SEARCH
   ============================================================ */

$search = trim(
    $_GET['search'] ?? ''
);


/* ============================================================
   GET STUDENTS
   ============================================================ */

$students = [];


if ($search !== '') {

    $search_like =
        '%' . $search . '%';


    $stmt = $conn->prepare("
        SELECT
            s.id,
            s.admission_no,
            s.admission_date,
            s.roll_no,
            s.student_name,
            s.gender,
            s.dob,
            s.father_name,
            s.father_mobile,
            s.mother_name,
            s.mother_mobile,
            s.city,
            s.state,
            s.status,

            c.class_name,

            sec.section_name

        FROM students s

        LEFT JOIN classes c
            ON c.id = s.class_id

        LEFT JOIN sections sec
            ON sec.id = s.section_id

        WHERE
            s.admission_no LIKE ?
            OR s.student_name LIKE ?
            OR s.father_name LIKE ?
            OR s.father_mobile LIKE ?
            OR s.mother_name LIKE ?
            OR s.roll_no LIKE ?

        ORDER BY
            s.id DESC
    ");


    if ($stmt) {

        $stmt->bind_param(
            "ssssss",
            $search_like,
            $search_like,
            $search_like,
            $search_like,
            $search_like,
            $search_like
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        while (
            $row =
            $result->fetch_assoc()
        ) {

            $students[] = $row;
        }

        $stmt->close();
    }

} else {

    $query = "

        SELECT
            s.id,
            s.admission_no,
            s.admission_date,
            s.roll_no,
            s.student_name,
            s.gender,
            s.dob,
            s.father_name,
            s.father_mobile,
            s.mother_name,
            s.mother_mobile,
            s.city,
            s.state,
            s.status,

            c.class_name,

            sec.section_name

        FROM students s

        LEFT JOIN classes c
            ON c.id = s.class_id

        LEFT JOIN sections sec
            ON sec.id = s.section_id

        ORDER BY
            s.id DESC
    ";


    $result =
        $conn->query($query);


    if ($result) {

        while (
            $row =
            $result->fetch_assoc()
        ) {

            $students[] = $row;
        }
    }
}


/* ============================================================
   TOTAL STUDENTS
   ============================================================ */

$total_students = count($students);

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
    Students - Oasis Public School ERP
</title>


<!-- Bootstrap -->

<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
>


<!-- Font Awesome -->

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
>


<style>

body {

    background: #f4f6f9;

    font-family:
        Arial,
        Helvetica,
        sans-serif;
}


.content-wrapper {

    min-height: 100vh;

    padding: 20px;
}


.card {

    border-radius: 6px;
}


.card-header {

    font-weight: bold;
}


.table th {

    white-space: nowrap;

    vertical-align: middle;
}


.table td {

    vertical-align: middle;
}


.student-name {

    font-weight: 600;
}


.action-btn {

    margin-right: 3px;
}


.empty-box {

    padding: 50px;

    text-align: center;

    color: #777;
}


.search-box {

    max-width: 450px;
}


.badge-active {

    background: #28a745;

    color: white;
}


.badge-inactive {

    background: #dc3545;

    color: white;
}


@media(max-width: 768px) {

    .content-wrapper {

        padding: 10px;
    }

}

</style>

</head>


<body>


<div class="content-wrapper">


<!-- =========================================================
     PAGE HEADER
     ========================================================= -->

<div
    class="d-flex justify-content-between
           align-items-center mb-3"
>

    <div>

        <h3 class="mb-1">

            <i class="fas fa-user-graduate"></i>

            Students

        </h3>

        <small class="text-muted">

            Student Management

        </small>

    </div>


    <div>

        <a
            href="<?= BASE_URL; ?>/modules/students/add.php"
            class="btn btn-primary"
        >

            <i class="fas fa-plus"></i>

            Add Student

        </a>

    </div>

</div>


<!-- =========================================================
     SEARCH / SUMMARY
     ========================================================= -->

<div class="card mb-3">

    <div class="card-body">


        <div class="row">


            <div class="col-md-8">

                <form
                    method="GET"
                    action=""
                    class="form-inline"
                >

                    <div class="input-group search-box">

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="Search student, admission no, father mobile..."
                            value="<?= e($search); ?>"
                        >


                        <div class="input-group-append">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >

                                <i class="fas fa-search"></i>

                                Search

                            </button>

                        </div>

                    </div>


                    <?php if ($search !== ''): ?>

                        <a
                            href="<?= BASE_URL; ?>/modules/students/index.php"
                            class="btn btn-secondary ml-2"
                        >

                            Clear

                        </a>

                    <?php endif; ?>

                </form>

            </div>


            <div class="col-md-4 text-md-right mt-3 mt-md-0">

                <strong>

                    Total Students:

                    <span class="badge badge-primary">

                        <?= $total_students; ?>

                    </span>

                </strong>

            </div>


        </div>

    </div>

</div>


<!-- =========================================================
     STUDENT TABLE
     ========================================================= -->

<div class="card">


    <div
        class="card-header
               bg-primary
               text-white"
    >

        <i class="fas fa-users"></i>

        Student List

    </div>


    <div class="card-body p-0">


        <?php if (
            empty($students)
        ): ?>


            <div class="empty-box">

                <i
                    class="fas fa-user-slash
                           fa-3x
                           mb-3"
                ></i>


                <h5>
                    No Students Found
                </h5>


                <?php if ($search !== ''): ?>

                    <p>

                        No student matches:

                        <strong>
                            <?= e($search); ?>
                        </strong>

                    </p>

                <?php else: ?>

                    <p>
                        No students have been added yet.
                    </p>

                <?php endif; ?>


                <a
                    href="<?= BASE_URL; ?>/modules/students/add.php"
                    class="btn btn-primary"
                >

                    <i class="fas fa-plus"></i>

                    Add First Student

                </a>

            </div>


        <?php else: ?>


            <div class="table-responsive">


                <table
                    class="table
                           table-bordered
                           table-hover
                           mb-0"
                >


                    <thead
                        class="thead-light"
                    >

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            Admission No.
                        </th>

                        <th>
                            Student Name
                        </th>

                        <th>
                            Class
                        </th>

                        <th>
                            Section
                        </th>

                        <th>
                            Roll No.
                        </th>

                        <th>
                            Gender
                        </th>

                        <th>
                            Father's Name
                        </th>

                        <th>
                            Father Mobile
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                    </thead>


                    <tbody>


                    <?php
                    $serial = 1;

                    foreach (
                        $students
                        as $student
                    ):
                    ?>


                    <tr>


                        <td>

                            <?= $serial++; ?>

                        </td>


                        <td>

                            <strong>

                                <?= e(
                                    $student['admission_no']
                                ); ?>

                            </strong>

                        </td>


                        <td>

                            <span class="student-name">

                                <?= e(
                                    $student['student_name']
                                ); ?>

                            </span>

                        </td>


                        <td>

                            <?= e(
                                $student['class_name']
                                ?? ''
                            ); ?>

                        </td>


                        <td>

                            <?= e(
                                $student['section_name']
                                ?? ''
                            ); ?>

                        </td>


                        <td>

                            <?= e(
                                $student['roll_no']
                                ?? ''
                            ); ?>

                        </td>


                        <td>

                            <?= e(
                                $student['gender']
                            ); ?>

                        </td>


                        <td>

                            <?= e(
                                $student['father_name']
                            ); ?>

                        </td>


                        <td>

                            <?= e(
                                $student['father_mobile']
                                ?? ''
                            ); ?>

                        </td>


                        <td>


                            <?php if (
                                (int)$student['status']
                                === 1
                            ): ?>


                                <span
                                    class="
                                    badge
                                    badge-active
                                    "
                                >

                                    Active

                                </span>


                            <?php else: ?>


                                <span
                                    class="
                                    badge
                                    badge-inactive
                                    "
                                >

                                    Inactive

                                </span>


                            <?php endif; ?>


                        </td>


                        <td>

                            <!-- VIEW -->

                            <a
                                href="<?= BASE_URL; ?>/modules/students/view.php?id=<?= (int)$student['id']; ?>"
                                class="
                                btn
                                btn-sm
                                btn-info
                                action-btn
                                "
                                title="View"
                            >

                                <i class="fas fa-eye"></i>

                            </a>


                            <!-- EDIT -->

                            <a
                                href="<?= BASE_URL; ?>/modules/students/edit.php?id=<?= (int)$student['id']; ?>"
                                class="
                                btn
                                btn-sm
                                btn-warning
                                action-btn
                                "
                                title="Edit"
                            >

                                <i class="fas fa-edit"></i>

                            </a>


                            <!-- DELETE -->

                            <a
                                href="<?= BASE_URL; ?>/modules/students/index.php?delete=<?= (int)$student['id']; ?>"
                                class="
                                btn
                                btn-sm
                                btn-danger
                                action-btn
                                "
                                title="Delete"
                                onclick="
                                    return confirm(
                                        'Are you sure you want to delete this student?'
                                    );
                                "
                            >

                                <i class="fas fa-trash"></i>

                            </a>


                        </td>


                    </tr>


                    <?php endforeach; ?>


                    </tbody>


                </table>

            </div>


        <?php endif; ?>


    </div>

</div>


<!-- =========================================================
     FOOTER
     ========================================================= -->

<div class="text-center text-muted mt-3">

    Oasis Public School ERP

</div>


</div>


<!-- Bootstrap JS -->

<script
    src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"
></script>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>