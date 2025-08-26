<?php
class Security
{

    public static function sanitizeInput($data)
    {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }

    public static function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function validatePassword($password)
    {
        return strlen($password) >= PASSWORD_MIN_LENGTH;
    }

    public static function generateCSRFToken()
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function validateCSRFToken($token)
    {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function uploadFile($file, $uploadDir, $allowedExtensions = [])
    {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions)) {
            return ['success' => false, 'message' => 'Invalid file type.'];
        }

        // Ensure upload dir exists
        $uploadPath = $_SERVER['DOCUMENT_ROOT'] . '/hrms' . $uploadDir;
        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        // Unique filename
        $newFilename = uniqid() . '.' . $ext;
        $targetFile = $uploadPath . $newFilename;

        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            // ✅ Return the relative path for web access
            return ['success' => true, 'path' => $uploadDir . $newFilename];
        }

        return ['success' => false, 'message' => 'Failed to upload file.'];
    }
}
