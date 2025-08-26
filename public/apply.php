<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

$db = new Database();
$conn = $db->getConnection();
$emailService = new EmailService();

$message = '';
$messageType = '';

// Get active job postings
$jobQuery = "SELECT id, title, description, requirements, salary_range, deadline FROM job_postings WHERE status = 'active' AND (deadline IS NULL OR deadline >= CURDATE()) ORDER BY posted_date DESC";
$jobStmt = $conn->prepare($jobQuery);
$jobStmt->execute();
$jobs = $jobStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $jobId = (int)($_POST['job_id'] ?? 0);
        $name = Security::sanitizeInput($_POST['name'] ?? '');
        $email = Security::sanitizeInput($_POST['email'] ?? '');
        $phone = Security::sanitizeInput($_POST['phone'] ?? '');
        $address = Security::sanitizeInput($_POST['address'] ?? '');
        $coverLetter = Security::sanitizeInput($_POST['cover_letter'] ?? '');
        
        $errors = [];
        
        // Validation
        if (empty($name)) $errors[] = 'Name is required';
        if (empty($email) || !Security::validateEmail($email)) $errors[] = 'Valid email is required';
        if (empty($phone)) $errors[] = 'Phone number is required';
        if ($jobId <= 0) $errors[] = 'Please select a valid job position';
        
        // CV file upload
        $cvPath = '';
        if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = Security::uploadFile($_FILES['cv'], '/assets/uploads/cvs/', ['pdf', 'doc', 'docx']);
            if ($uploadResult['success']) {
                $cvPath = $uploadResult['path'];
            } else {
                $errors[] = 'CV upload failed: ' . $uploadResult['message'];
            }
        } else {
            $errors[] = 'CV file is required';
        }
        
        if (empty($errors)) {
            try {
                $query = "INSERT INTO cv_applications (job_posting_id, candidate_name, email, phone, address, cv_file_path, cover_letter) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->execute([$jobId, $name, $email, $phone, $address, $cvPath, $coverLetter]);
                
                // Get job title for notification
                $jobTitleQuery = "SELECT title FROM job_postings WHERE id = ?";
                $jobTitleStmt = $conn->prepare($jobTitleQuery);
                $jobTitleStmt->execute([$jobId]);
                $jobTitle = $jobTitleStmt->fetchColumn();
                
                // Send notification email to HR
                $hrEmails = ['hr@coachingcenter.com']; // You can get this from settings
                foreach ($hrEmails as $hrEmail) {
                    $emailService->sendJobApplicationNotification($hrEmail, $jobTitle, $name);
                }
                
                $message = 'Your application has been submitted successfully! We will contact you soon.';
                $messageType = 'success';
                
                // Clear form data
                $_POST = [];
                
            } catch (PDOException $e) {
                $message = 'An error occurred while submitting your application. Please try again.';
                $messageType = 'danger';
                error_log("Application submission error: " . $e->getMessage());
            }
        } else {
            $message = implode('<br>', $errors);
            $messageType = 'danger';
        }
    } else {
        $message = 'Invalid request. Please try again.';
        $messageType = 'danger';
    }
}

$pageTitle = 'Apply for Position';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/material-theme.css" rel="stylesheet">
    <style>
        .file-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.3s ease;
        }
        
        .file-upload-area:hover {
            border-color: var(--primary-color);
        }
        
        .file-upload-area.dragover {
            border-color: var(--primary-color);
            background-color: rgba(25, 118, 210, 0.1);
        }
        
        .file-upload-icon {
            font-size: 48px;
            color: var(--primary-color);
            margin-bottom: 16px;
        }
        
        .job-posting {
            border-left: 4px solid var(--primary-color);
            padding-left: 16px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="d-flex align-items-center w-100">
            <a href="../" class="navbar-brand">
                <i class="fas fa-graduation-cap"></i>
                <?php echo APP_NAME; ?>
            </a>
            <div class="navbar-nav" style="margin-left: auto;">
                <a href="../login.php" class="nav-link">
                    <i class="fas fa-sign-in-alt"></i>
                    Staff Login
                </a>
            </div>
        </div>
    </nav>

    <div class="main-content" style="margin-left: 0; margin-top: 64px;">
        <div class="container" style="max-width: 800px; margin: 0 auto;">
            <div class="material-card">
                <div class="card-header">
                    <h4 class="mb-0">Apply for a Position</h4>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?>">
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($jobs)): ?>
                        <div class="alert alert-info">
                            <h5>No Open Positions</h5>
                            <p>There are currently no open positions. Please check back later or contact us directly.</p>
                        </div>
                    <?php else: ?>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                            
                            <div class="form-group">
                                <label class="form-label">Position Applied For *</label>
                                <select name="job_id" class="form-control" required>
                                    <option value="">Select a position</option>
                                    <?php foreach ($jobs as $job): ?>
                                        <option value="<?php echo $job['id']; ?>" <?php echo ($_POST['job_id'] ?? '') == $job['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($job['title']); ?>
                                            <?php if ($job['salary_range']): ?>
                                                - <?php echo htmlspecialchars($job['salary_range']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Email Address *</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Upload CV/Resume *</label>
                                <div class="file-upload-area" onclick="document.getElementById('cvFile').click()">
                                    <div class="file-upload-icon">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                    </div>
                                    <div class="file-upload-text">
                                        Click to upload your CV/Resume
                                    </div>
                                    <div class="file-upload-info text-muted">
                                        Supported formats: PDF, DOC, DOCX (Max 5MB)
                                    </div>
                                </div>
                                <input type="file" id="cvFile" name="cv" accept=".pdf,.doc,.docx" required style="display: none;">
                                <div id="fileName" class="mt-2 text-muted"></div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Cover Letter</label>
                                <textarea name="cover_letter" class="form-control" rows="5" placeholder="Tell us why you're the perfect fit for this position..."><?php echo htmlspecialchars($_POST['cover_letter'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane"></i>
                                    Submit Application
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($jobs)): ?>
                <div class="material-card">
                    <div class="card-header">
                        <h5 class="mb-0">Available Positions</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($jobs as $job): ?>
                            <div class="job-posting mb-4">
                                <h6 class="text-primary"><?php echo htmlspecialchars($job['title']); ?></h6>
                                <?php if ($job['salary_range']): ?>
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-money-bill"></i>
                                        <?php echo htmlspecialchars($job['salary_range']); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($job['deadline']): ?>
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-calendar"></i>
                                        Application Deadline: <?php echo date('F j, Y', strtotime($job['deadline'])); ?>
                                    </p>
                                <?php endif; ?>
                                <p><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
                                <?php if ($job['requirements']): ?>
                                    <div class="mt-2">
                                        <strong>Requirements:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($job['requirements'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if ($job !== end($jobs)): ?>
                                    <hr>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('cvFile').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            const fileNameDiv = document.getElementById('fileName');
            if (fileName) {
                fileNameDiv.innerHTML = '<i class="fas fa-file"></i> ' + fileName;
                fileNameDiv.style.color = 'var(--success-color)';
            }
        });
    </script>
</body>
</html>