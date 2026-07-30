<?php
session_start();

// User must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

include('../../includes/header.php');
include('../../includes/navbar.php');
include('../../includes/sidebar.php');
?>

<!-- Content Wrapper -->
<div class="content-wrapper">

    <!-- Page Header -->
    <section class="content-header">
        <div class="container-fluid">

            <h1>Dashboard</h1>

        </div>
    </section>

    <!-- Main Content -->
    <section class="content">

        <div class="container-fluid">

            <div class="alert alert-success">

                <h4>
                    Welcome,
                    <?php echo $_SESSION['full_name']; ?>
                </h4>

                <p>
                    Oasis Public School ERP Dashboard is working successfully.
                </p>

            </div>

        </div>

    </section>

</div>

<?php
include('../../includes/footer.php');
?>