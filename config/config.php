<?php

// ===============================
// EduCare ERP
// Configuration File
// ===============================

// Project Name
define('APP_NAME', 'EduCare ERP');

// Prefer an explicitly configured URL. For local development, allow only localhost
// and private IPv4 hosts so phones on the same Wi-Fi can use the PC's LAN address.
$erp_base_url = trim((string)(getenv('ERP_BASE_URL') ?: ''));
if ($erp_base_url === '') {
    $request_host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    $host_parts = array();
    $host_is_local = false;
    if (preg_match('/^([A-Za-z0-9.-]+)(?::([0-9]{1,5}))?$/', $request_host, $host_parts)) {
        $request_hostname = strtolower($host_parts[1]);
        $request_port = isset($host_parts[2]) ? (int)$host_parts[2] : 0;
        if (!isset($host_parts[2]) || ($request_port >= 1 && $request_port <= 65535)) {
            $host_is_local = $request_hostname === 'localhost' || substr($request_hostname, -10) === '.localhost';
            if (filter_var($request_hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ip = ip2long($request_hostname);
                $private_ranges = array(
                    array(ip2long('10.0.0.0'), ip2long('10.255.255.255')),
                    array(ip2long('172.16.0.0'), ip2long('172.31.255.255')),
                    array(ip2long('192.168.0.0'), ip2long('192.168.255.255')),
                    array(ip2long('127.0.0.0'), ip2long('127.255.255.255'))
                );
                foreach ($private_ranges as $range) {
                    if ($ip >= $range[0] && $ip <= $range[1]) {
                        $host_is_local = true;
                        break;
                    }
                }
            }
            if ($host_is_local) {
                $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || $request_port === 443 ? 'https' : 'http';
                $request_host = $request_hostname . ($request_port > 0 ? ':' . $request_port : '');
                $project_root = realpath(dirname(__DIR__));
                $document_root = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
                $base_path = '/school_erp';
                if ($project_root !== false && $document_root !== false) {
                    $normalized_project = str_replace('\\', '/', $project_root);
                    $normalized_document = rtrim(str_replace('\\', '/', $document_root), '/');
                    if (stripos($normalized_project, $normalized_document . '/') === 0) {
                        $relative_path = substr($normalized_project, strlen($normalized_document));
                        $base_path = '/' . trim($relative_path, '/');
                    } elseif (strcasecmp($normalized_project, $normalized_document) === 0) {
                        $base_path = '';
                    }
                }
                $erp_base_url = $scheme . '://' . $request_host . rtrim($base_path, '/');
            }
        }
    }
    if ($erp_base_url === '') {
        $erp_base_url = 'http://localhost/school_erp';
    }
}
define('BASE_URL', rtrim($erp_base_url, '/'));

// Time Zone
date_default_timezone_set('Asia/Kolkata');

?>