<?php

// ===============================
// Oasis Public School ERP
// Configuration File
// ===============================

// Project Name
define('APP_NAME', 'EduCare ERP');

// Base URL
define('BASE_URL', rtrim((string)(getenv('ERP_BASE_URL') ?: 'http://localhost/school_erp'), '/'));

// Time Zone
date_default_timezone_set('Asia/Kolkata');

?>