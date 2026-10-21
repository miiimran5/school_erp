<?php
if (!isset($conn) || !($conn instanceof mysqli)) {
    return;
}

if (!isset($GLOBALS['school_settings'])) {
    $GLOBALS['school_settings'] = array(
        'app_name' => APP_NAME,
        'app_logo' => 'educare-erp-logo.png',
        'login_background' => 'educare-erp-login-background.png',
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

function school_save_brand_asset($conn, $upload, $setting_key, $filename_prefix)
{
    if (!isset($upload) || $upload['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 5242880 || !is_uploaded_file($upload['tmp_name'])) {
        throw new RuntimeException('The selected image must be a valid image no larger than 5 MB.');
    }
    $image_info = getimagesize($upload['tmp_name']);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $allowed = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
    if ($image_info === false || !isset($allowed[$mime])) {
        throw new RuntimeException('Choose a JPG, PNG, or WEBP image.');
    }

    $filename = $filename_prefix . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $directory = __DIR__ . '/../uploads/branding';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the branding image folder.');
    }
    $path = $directory . '/' . $filename;
    if (!move_uploaded_file($upload['tmp_name'], $path)) {
        throw new RuntimeException('Unable to save the selected image.');
    }

    $stmt = $conn->prepare('INSERT INTO school_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    if (!$stmt) {
        unlink($path);
        throw new RuntimeException('Unable to prepare the branding setting update.');
    }
    $stmt->bind_param('ss', $setting_key, $filename);
    if (!$stmt->execute()) {
        $stmt->close();
        unlink($path);
        throw new RuntimeException('Unable to save the branding setting.');
    }
    $stmt->close();
    $GLOBALS['school_settings'][$setting_key] = $filename;
    return $filename;
}
