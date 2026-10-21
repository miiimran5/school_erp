<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/school.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?= school_e(school_setting('app_name', APP_NAME)); ?> - <?= school_e(school_setting('school_name', APP_NAME)); ?></title>

<link rel="stylesheet" href="<?= BASE_URL; ?>/assets/plugins/fontawesome-free/css/all.min.css">

<link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/adminlte.min.css">

<link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/app.css">
<link rel="manifest" href="<?= BASE_URL; ?>/manifest.json">
<meta name="theme-color" content="#172554">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= school_e(school_setting('school_name', APP_NAME)); ?>">
<link rel="apple-touch-icon" href="<?= BASE_URL; ?>/assets/icons/erp-icon.svg">

</head>

<body class="hold-transition sidebar-mini layout-fixed app-body">

<div class="wrapper">