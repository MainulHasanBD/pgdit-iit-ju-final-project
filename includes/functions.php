<?php
require_once(__DIR__ . '/../config/config.php');
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
class EmailService {
    private $mailer;

    public function __construct() {
        $this->mailer = new PHPMailer(true);

        try {
            $this->mailer->isSMTP();
            $this->mailer->Host       = 'smtp-relay.brevo.com'; // or another SMTP server
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = '8b274d001@smtp-brevo.com'; // change this
            $this->mailer->Password   = 'j8wVhnBQDsObyE29';    // use App Password
            $this->mailer->SMTPSecure = 'tls';
            $this->mailer->Port       = 587;

            $this->mailer->setFrom('mainul9396@gmail.com', FROM_NAME); // or hardcoded name
            $this->mailer->isHTML(true);
        } catch (Exception $e) {
            error_log('Mailer setup failed: ' . $e->getMessage());
        }
    }

    public function sendEmail($to, $subject, $body, $isHTML = true) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $body;

            if (!$isHTML) {
                $this->mailer->isHTML(false);
            }

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log('Email send error: ' . $e->getMessage());
            return false;
        }
    }

    public function sendJobApplicationNotification($email, $jobTitle, $candidateName) {
        $subject = "New Job Application - " . $jobTitle;
        $body = "
            <h2>New Job Application Received</h2>
            <p>A new application has been submitted for the position: <strong>{$jobTitle}</strong></p>
            <p>Candidate: <strong>{$candidateName}</strong></p>
            <p>Please log in to the HR system to review the application.</p>
        ";
        return $this->sendEmail($email, $subject, $body);
    }

    public function sendWelcomeEmail($email, $name, $temporaryPassword) {
        $subject = "Welcome to " . APP_NAME;
        $body = "
            <h2>Welcome to " . APP_NAME . "</h2>
            <p>Dear {$name},</p>
            <p>Your account has been created successfully.</p>
            <p>Login Details:</p>
            <ul>
                <li>Email: {$email}</li>
                <li>Temporary Password: {$temporaryPassword}</li>
            </ul>
            <p>Please change your password after first login.</p>
            <p>Login URL: " . BASE_URL . "login.php</p>
        ";
        return $this->sendEmail($email, $subject, $body);
    }
}


class Pagination {
    public static function paginate($totalRecords, $currentPage, $recordsPerPage = RECORDS_PER_PAGE) {
        $totalPages = ceil($totalRecords / $recordsPerPage);
        $offset = ($currentPage - 1) * $recordsPerPage;
        
        return [
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'current_page' => $currentPage,
            'records_per_page' => $recordsPerPage,
            'offset' => $offset,
            'has_previous' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages
        ];
    }
    
    public static function generatePaginationHTML($pagination, $baseUrl) {
        $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
        
        // Previous button
        if ($pagination['has_previous']) {
            $prevPage = $pagination['current_page'] - 1;
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . $prevPage . '">Previous</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
        }
        
        // Page numbers
        $startPage = max(1, $pagination['current_page'] - 2);
        $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);
        
        for ($i = $startPage; $i <= $endPage; $i++) {
            if ($i == $pagination['current_page']) {
                $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a></li>';
            }
        }
        
        // Next button
        if ($pagination['has_next']) {
            $nextPage = $pagination['current_page'] + 1;
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . $nextPage . '">Next</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
        }
        
        $html .= '</ul></nav>';
        
        return $html;
    }
}

function formatDate($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

function formatCurrency($amount, $currency = 'BDT') {
    return $currency . ' ' . number_format($amount, 2);
}

function generateEmployeeId($prefix = 'EMP') {
    return $prefix . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function getStatusBadge($status) {
    $badges = [
        'active' => 'badge-success',
        'inactive' => 'badge-secondary',
        'pending' => 'badge-warning',
        'approved' => 'badge-success',
        'rejected' => 'badge-danger',
        'applied' => 'badge-info',
        'shortlisted' => 'badge-primary',
        'interviewed' => 'badge-warning',
        'selected' => 'badge-success'
    ];
    
    $badgeClass = $badges[$status] ?? 'badge-secondary';
    return '<span class="badge ' . $badgeClass . '">' . ucfirst($status) . '</span>';
}
?>