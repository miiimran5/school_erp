<?php
if (!isset($conn) || !($conn instanceof mysqli)) {
    return;
}

if (!isset($GLOBALS['school_settings'])) {
    $GLOBALS['school_settings'] = array(
        'school_name' => 'School',
        'school_tagline' => 'School ERP',
        'school_address' => '',
        'school_phone' => '',
        'school_email' => '',
        'school_logo' => '',
        'school_timezone' => 'Asia/Kolkata',
        'academic_session' => ''
    );
    $result = $conn->query('SELECT setting_key, setting_value FROM school_settings');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (array_key_exists($row['setting_key'], $GLOBALS['school_settings'])) {
                $GLOBALS['school_settings'][$row['setting_key']] = $row['setting_value'];
            }
        }
    }
    if ($GLOBALS['school_settings']['school_timezone'] !== '') {
        date_default_timezone_set($GLOBALS['school_settings']['school_timezone']);
    }
}

function school_setting($key, $fallback = '')
{
    return (string)($GLOBALS['school_settings'][$key] ?? $fallback);
}

function school_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
