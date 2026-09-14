<!-- Main Footer -->
<footer class="main-footer">

    <strong>
        Copyright &copy; 2026
        <?= school_e(school_setting('school_name', APP_NAME)); ?>.
    </strong>

    <div class="float-right d-none d-sm-inline-block">
        Version 0.1.0
    </div>

</footer>

</div>

<script src="<?= BASE_URL; ?>/assets/plugins/jquery/jquery.min.js"></script>

<script src="<?= BASE_URL; ?>/assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<script src="<?= BASE_URL; ?>/assets/js/adminlte.min.js"></script>

<script src="<?= BASE_URL; ?>/assets/js/app.js"></script>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('<?= BASE_URL; ?>/sw.js').catch(function () {});
    });
}
</script>

</body>
</html>