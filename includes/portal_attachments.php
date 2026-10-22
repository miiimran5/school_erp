<?php
function portal_attachment_uploads($conn, $files, $content_type, $content_id, $created_by)
{
    $foreign_key = $content_type === 'announcement' ? 'announcement_id' : ($content_type === 'homework' ? 'homework_id' : '');
    if ($foreign_key === '' || $content_id <= 0 || !is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
        return array('error' => 'The attachment upload request is invalid.', 'paths' => array(), 'count' => 0);
    }

    $pending = array();
    for ($index = 0; $index < count($files['name']); $index++) {
        $name = trim((string)($files['name'][$index] ?? ''));
        $error = (int)($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($name === '' && $error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK || (int)($files['size'][$index] ?? 0) <= 0 || (int)$files['size'][$index] > 10 * 1024 * 1024) {
            return array('error' => 'Each attachment must upload successfully and be no larger than 10 MB.', 'paths' => array(), 'count' => 0);
        }
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($extension, array('jpg', 'jpeg', 'pdf', 'docx'), true)) {
            return array('error' => 'Attachments must be JPEG, PDF, or DOCX files.', 'paths' => array(), 'count' => 0);
        }
        $pending[] = array('index' => $index, 'name' => $name, 'extension' => $extension);
        if (count($pending) > 5) {
            return array('error' => 'Attach no more than five files to one post.', 'paths' => array(), 'count' => 0);
        }
    }
    if (!$pending) {
        return array('error' => '', 'paths' => array(), 'count' => 0);
    }

    $directory = realpath(__DIR__ . '/../uploads/private_resources');
    if ($directory === false) {
        $directory = __DIR__ . '/../uploads/private_resources';
    }
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        return array('error' => 'The private attachment folder could not be created.', 'paths' => array(), 'count' => 0);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $allowed_mimes = array(
        'jpg' => array('image/jpeg'),
        'jpeg' => array('image/jpeg'),
        'pdf' => array('application/pdf'),
        'docx' => array('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip')
    );
    $insert = $conn->prepare("INSERT INTO portal_content_attachments ($foreign_key,stored_name,original_name,mime_type,created_by) VALUES (?,?,?,?,?)");
    if (!$insert) {
        return array('error' => 'Unable to prepare the attachment record: ' . $conn->error, 'paths' => array(), 'count' => 0);
    }

    $paths = array();
    $count = 0;
    $error_message = '';
    foreach ($pending as $file) {
        $index = $file['index'];
        $temporary_path = (string)($files['tmp_name'][$index] ?? '');
        $mime_type = $finfo->file($temporary_path);
        if (!in_array($mime_type, $allowed_mimes[$file['extension']], true)) {
            $error_message = 'An attachment does not match its JPEG, PDF, or DOCX file type.';
            break;
        }
        if ($file['extension'] === 'docx') {
            if (!class_exists('PharData')) {
                $error_message = 'DOCX attachment verification is unavailable on this server. Enable PHP PharData or upload a PDF/JPEG file.';
                break;
            }
            $archive_base = tempnam(sys_get_temp_dir(), 'educare-docx-');
            if ($archive_base === false) {
                $error_message = 'A temporary file could not be created to verify the DOCX attachment.';
                break;
            }
            $archive_path = $archive_base . '.zip';
            if (!rename($archive_base, $archive_path)) {
                if (is_file($archive_base)) {
                    unlink($archive_base);
                }
                $error_message = 'The DOCX attachment could not be verified.';
                break;
            }
            if (!copy($temporary_path, $archive_path)) {
                if (is_file($archive_path)) {
                    unlink($archive_path);
                }
                $error_message = 'The DOCX attachment could not be verified.';
                break;
            }
            try {
                $archive = new PharData($archive_path);
                $is_docx = isset($archive['[Content_Types].xml'])
                    && isset($archive['word/document.xml']);
            } catch (UnexpectedValueException $exception) {
                $is_docx = false;
            } finally {
                unset($archive);
                if (is_file($archive_path) && !unlink($archive_path)) {
                    error_log('Unable to remove temporary DOCX verification file: ' . $archive_path);
                }
            }
            if (!$is_docx) {
                $error_message = 'The selected DOCX file is not a valid Word document.';
                break;
            }
        }
        $stored_name = bin2hex(random_bytes(20)) . '.' . $file['extension'];
        $destination = $directory . DIRECTORY_SEPARATOR . $stored_name;
        if (!move_uploaded_file($temporary_path, $destination)) {
            $error_message = 'A selected attachment could not be stored safely.';
            break;
        }
        $paths[] = $destination;
        $original_name = basename(str_replace('\\', '/', $file['name']));
        $insert->bind_param('isssi', $content_id, $stored_name, $original_name, $mime_type, $created_by);
        if (!$insert->execute()) {
            $error_message = 'Unable to save an attachment record: ' . $insert->error;
            break;
        }
        $count++;
    }
    $insert->close();
    return array('error' => $error_message, 'paths' => $paths, 'count' => $count);
}
