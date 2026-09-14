<?php
/**
 * ============================================================
 * SCHOOL ERP
 * Students Module
 * File: modules/students/index.php
 * ============================================================
 */

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/access.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}
access_require_roles(array('Admin','Teacher'));

$conn->set_charset("utf8mb4");


/* ============================================================
   BASE URL
   ============================================================ */

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
        if (access_is_teacher()) {
            http_response_code(403);
            exit('Teachers cannot delete student records.');
        }

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

        if (access_is_teacher()) {
            $students = array_values(array_filter($students, function ($row) use ($conn) {
                return access_teacher_can_class($conn, (int)$row['class_id'], (int)$row['section_id']);
            }));
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
            s.class_id,
            s.section_id,

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
            s.class_id,
            s.section_id,

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
<?php include '../../includes/header.php'; ?>
<?php include '../../includes/navbar.php'; ?>
<?php include '../../includes/sidebar.php'; ?>


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
            href="<?= BASE_URL; ?>/modules/fees/index.php"
            class="btn btn-info mr-2"
        >
            <i class="fas fa-rupee-sign"></i>
            Student Fees
        </a>

        <a
            href="<?= BASE_URL; ?>/modules/students/import.php"
            class="btn btn-success mr-2"
        >
            <i class="fas fa-file-upload"></i>
            Import CSV
        </a>

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
                            <a href="<?= BASE_URL; ?>/modules/results/student_reports.php?student_id=<?= (int)$student['id']; ?>" class="btn btn-sm btn-success action-btn" title="View Report Cards"><i class="fas fa-file-alt"></i></a>


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


<?php include '../../includes/footer.php'; ?>