<?php
// Application configuration
define('APP_NAME', 'Coaching Center HR');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'https://hrms.unaux.com/');
define('UPLOAD_PATH', 'assets/uploads/');
define('CV_UPLOAD_PATH', __DIR__ . '../assets/uploads/cvs/');
define('PROFILE_UPLOAD_PATH', __DIR__ . '../assets/uploads/profile_pics/');
// define('PROFILE_UPLOAD_PATH', 'assets/uploads/profile_pics/');

// Email configuration
define('SMTP_HOST', 'smtp-relay.brevo.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '8b274d001@smtp-brevo.com');
define('SMTP_PASSWORD', 'j8wVhnBQDsObyE29');
define('FROM_EMAIL', 'mainul9396@gmail.com');
define('FROM_NAME', 'Coaching Center HR');

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('PASSWORD_MIN_LENGTH', 8);

// Pagination settings
define('RECORDS_PER_PAGE', 10);

// Time zone
date_default_timezone_set('Asia/Dhaka');
?>