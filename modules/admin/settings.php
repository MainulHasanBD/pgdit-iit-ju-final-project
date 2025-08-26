<?php
$pageTitle = 'System Settings';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../components/header.php';
require_once '../../components/sidebar.php';
require_once '../../includes/security.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = new Database();
$conn = $db->getConnection();

$message = '';
$messageType = '';

// Создание таблицы настроек если не существует
try {
    $settingsTableQuery = "CREATE TABLE IF NOT EXISTS system_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
        description TEXT,
        category VARCHAR(50) DEFAULT 'general',
        updated_by INT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
    )";
    $conn->exec($settingsTableQuery);
} catch (PDOException $e) {
    error_log("Settings table creation error: " . $e->getMessage());
}

// Обработка обновления настроек
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_settings') {
            try {
                $conn->beginTransaction();
                
                $settings = $_POST['settings'] ?? [];
                $updatedCount = 0;
                
                foreach ($settings as $key => $value) {
                    // Определение типа настройки
                    $type = 'text';
                    if (is_numeric($value)) {
                        $type = 'number';
                    } elseif (in_array(strtolower($value), ['true', 'false', '1', '0'])) {
                        $type = 'boolean';
                    }
                    
                    $query = "INSERT INTO system_settings (setting_key, setting_value, setting_type, updated_by) 
                              VALUES (?, ?, ?, ?) 
                              ON DUPLICATE KEY UPDATE 
                              setting_value = VALUES(setting_value),
                              setting_type = VALUES(setting_type),
                              updated_by = VALUES(updated_by)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$key, $value, $type, $_SESSION['user_id']]);
                    $updatedCount++;
                }
                
                $conn->commit();
                $message = "Successfully updated {$updatedCount} setting(s)!";
                $messageType = 'success';
                
            } catch (PDOException $e) {
                $conn->rollBack();
                $message = 'Error updating settings: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Получение текущих настроек
$settingsQuery = "SELECT * FROM system_settings ORDER BY category, setting_key";
$settingsStmt = $conn->prepare($settingsQuery);
$settingsStmt->execute();
$currentSettings = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);

// Организация настроек по категориям
$settingsByCategory = [];
foreach ($currentSettings as $setting) {
    $settingsByCategory[$setting['category']][] = $setting;
}

// Настройки по умолчанию
$defaultSettings = [
    'general' => [
        'app_name' => ['value' => APP_NAME, 'label' => 'Application Name', 'type' => 'text'],
        'app_version' => ['value' => APP_VERSION, 'label' => 'Application Version', 'type' => 'text'],
        'timezone' => ['value' => 'Asia/Dhaka', 'label' => 'Timezone', 'type' => 'text'],
        'default_language' => ['value' => 'en', 'label' => 'Default Language', 'type' => 'text'],
        'records_per_page' => ['value' => RECORDS_PER_PAGE, 'label' => 'Records Per Page', 'type' => 'number'],
        'session_timeout' => ['value' => SESSION_TIMEOUT, 'label' => 'Session Timeout (seconds)', 'type' => 'number']
    ],
    'email' => [
        'smtp_host' => ['value' => SMTP_HOST, 'label' => 'SMTP Host', 'type' => 'text'],
        'smtp_port' => ['value' => SMTP_PORT, 'label' => 'SMTP Port', 'type' => 'number'],
        'smtp_username' => ['value' => SMTP_USERNAME, 'label' => 'SMTP Username', 'type' => 'text'],
        'from_email' => ['value' => FROM_EMAIL, 'label' => 'From Email', 'type' => 'text'],
        'from_name' => ['value' => FROM_NAME, 'label' => 'From Name', 'type' => 'text']
    ],
    'security' => [
        'max_login_attempts' => ['value' => MAX_LOGIN_ATTEMPTS, 'label' => 'Max Login Attempts', 'type' => 'number'],
        'password_min_length' => ['value' => PASSWORD_MIN_LENGTH, 'label' => 'Minimum Password Length', 'type' => 'number'],
        'enable_2fa' => ['value' => 'false', 'label' => 'Enable Two-Factor Auth', 'type' => 'boolean'],
        'force_https' => ['value' => 'false', 'label' => 'Force HTTPS', 'type' => 'boolean']
    ],
    'hr' => [
        'auto_approve_applications' => ['value' => 'false', 'label' => 'Auto Approve Applications', 'type' => 'boolean'],
        'application_expiry_days' => ['value' => '90', 'label' => 'Application Expiry (days)', 'type' => 'number'],
        'send_application_notifications' => ['value' => 'true', 'label' => 'Send Application Notifications', 'type' => 'boolean'],
        'onboarding_task_auto_assign' => ['value' => 'true', 'label' => 'Auto Assign Onboarding Tasks', 'type' => 'boolean']
    ],
    'salary' => [
        'perfect_attendance_bonus' => ['value' => '2000', 'label' => 'Perfect Attendance Bonus', 'type' => 'number'],
        'good_attendance_bonus' => ['value' => '1000', 'label' => 'Good Attendance Bonus (95%+)', 'type' => 'number'],
        'absent_day_penalty' => ['value' => '500', 'label' => 'Absent Day Penalty', 'type' => 'number'],
        'late_day_penalty' => ['value' => '100', 'label' => 'Late Day Penalty', 'type' => 'number'],
        'auto_calculate_bonuses' => ['value' => 'true', 'label' => 'Auto Calculate Bonuses', 'type' => 'boolean']
    ]
];

// Объединение с текущими настройками
$allSettings = $defaultSettings;
foreach ($currentSettings as $setting) {
    $category = $setting['category'];
    $key = $setting['setting_key'];
    
    if (!isset($allSettings[$category])) {
        $allSettings[$category] = [];
    }
    
    $allSettings[$category][$key] = [
        'value' => $setting['setting_value'],
        'label' => ucwords(str_replace('_', ' ', $key)),
        'type' => $setting['setting_type']
    ];
}
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <H2>System Settings</H2>
                <div class="subtitle">Configure application settings and preferences</div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> fade-in"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Settings Form -->
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="update_settings">

        <div class="settings-container">
            <div class="settings-tabs">
                <!-- Tab Navigation -->
                <ul class="nav nav-tabs" id="settingsTabs">
                    <?php $firstTab = true; ?>
                    <?php foreach ($allSettings as $category => $settings): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $firstTab ? 'active' : ''; ?>" 
                               id="<?php echo $category; ?>-tab" 
                               data-toggle="tab" 
                               href="#<?php echo $category; ?>">
                                <span class="icon-wrapper">
                                    <?php
                                    $icons = [
                                        'general' => 'fas fa-cog',
                                        'email' => 'fas fa-envelope',
                                        'security' => 'fas fa-shield-alt',
                                        'hr' => 'fas fa-users',
                                        'salary' => 'fas fa-money-bill-wave'
                                    ];
                                    ?>
                                    <i class="<?php echo $icons[$category] ?? 'fas fa-cog'; ?>"></i>
                                </span>
                                <?php echo ucfirst($category); ?>
                            </a>
                        </li>
                        <?php $firstTab = false; ?>
                    <?php endforeach; ?>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="settingsTabContent">
                    <?php $firstContent = true; ?>
                    <?php foreach ($allSettings as $category => $settings): ?>
                        <div class="tab-pane fade <?php echo $firstContent ? 'show active' : ''; ?>" 
                             id="<?php echo $category; ?>">
                            
                            <h3 class="settings-category-title">
                            <?php echo ucfirst($category); ?> Settings
                            </h3>
                            
                            <div class="settings-grid">
                                <?php foreach ($settings as $key => $setting): ?>
                                    <div class="setting-group">
                                        <div class="form-group">
                                            <label class="form-label"><?php echo $setting['label']; ?></label>
                                            <?php if ($setting['type'] === 'boolean'): ?>
                                                <select name="settings[<?php echo $key; ?>]" class="form-control">
                                                    <option value="true" <?php echo strtolower($setting['value']) === 'true' ? 'selected' : ''; ?>>Yes</option>
                                                    <option value="false" <?php echo strtolower($setting['value']) === 'false' ? 'selected' : ''; ?>>No</option>
                                                </select>
                                            <?php elseif ($setting['type'] === 'number'): ?>
                                                <input type="number" 
                                                       name="settings[<?php echo $key; ?>]" 
                                                       class="form-control" 
                                                       value="<?php echo htmlspecialchars($setting['value']); ?>">
                                            <?php else: ?>
                                                <input type="text" 
                                                       name="settings[<?php echo $key; ?>]" 
                                                       class="form-control" 
                                                       value="<?php echo htmlspecialchars($setting['value']); ?>">
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php $firstContent = false; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="save-button-container">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i>
                    Save all settings
                </button>
            </div>
        </div>
    </form>

    <!-- System Information -->
    <div class="system-info-card">
        <div class="system-info-header">
            <H5>System Information</H5>
        </div>
        <div class="system-info-body">
            <div class="info-grid">
                <div class="info-item">
                    <label>PHP Version:</label>
                    <span><?php echo phpversion(); ?></span>
                </div>
                <div class="info-item">
                    <label>Server software:</label>
                    <span><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'غير معروف'; ?></span>
                </div>
                <div class="info-item">
                    <label>Database version:</label>
                    <span>
                        <?php 
                        try {
                            $version = $conn->query('SELECT VERSION()')->fetchColumn();
                            echo $version;
                        } catch (Exception $e) {
                            echo 'غير معروف';
                        }
                        ?>
                    </span>
                </div>
                <div class="info-item">
                    <label>Memory limit:</label>
                    <span><?php echo ini_get('memory_limit'); ?></span>
                </div>
                <div class="info-item">
                    <label>Maximum lift:</label>
                    <span><?php echo ini_get('upload_max_filesize'); ?></span>
                </div>
                <div class="info-item">
                    <label>Current time:</label>
                    <span><?php echo date('Y-m-d H:i:s'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>


<style>
:root {
    --primary-color: #4f46e5;
    --primary-hover: #4338ca;
    --secondary-color: #6b7280;
    --success-color: #10b981;
    --danger-color: #ef4444;
    --warning-color: #f59e0b;
    --info-color: #3b82f6;
    --light-bg: #f8fafc;
    --card-bg: #ffffff;
    --border-color: #e5e7eb;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
    --border-radius: 8px;
    --border-radius-lg: 12px;
}

.main-content {
    background: var(--light-bg);
    min-height: 100vh;
    padding: 2rem;
}

.page-header {
    background: var(--card-bg);
    padding: 2rem;
    border-radius: var(--border-radius-lg);
    margin-bottom: 2rem;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--border-color);
}

.page-header h2 {
    color: var(--text-primary);
    font-weight: 700;
    font-size: 2rem;
    margin: 0;
}

.page-header .subtitle {
    color: var(--text-secondary);
    font-size: 1rem;
    margin-top: 0.5rem;
}

.settings-container {
    background: var(--card-bg);
    border-radius: var(--border-radius-lg);
    overflow: hidden;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border-color);
}

.settings-tabs .nav-tabs {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
    border: none;
    margin: 0;
    padding: 0 2rem;
    overflow-x: auto;
    white-space: nowrap;
}

.settings-tabs .nav-link {
    border: none;
    color: rgba(255, 255, 255, 0.8);
    font-weight: 600;
    padding: 1.25rem 2rem;
    margin: 0;
    border-radius: 0;
    transition: all 0.3s ease;
    position: relative;
    text-transform: capitalize;
    font-size: 0.95rem;
}

.settings-tabs .nav-link:hover {
    color: white;
    background: rgba(255, 255, 255, 0.1);
}

.settings-tabs .nav-link.active {
    color: white;
    background: rgba(255, 255, 255, 0.15);
    border-bottom: 3px solid white;
}

.settings-tabs .nav-link.active::before {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border-left: 8px solid transparent;
    border-right: 8px solid transparent;
    border-bottom: 8px solid var(--card-bg);
}

.tab-content {
    margin: 0;
    background: var(--card-bg);
}

.tab-pane {
    padding: 2.5rem;
    min-height: 600px;
}

.settings-category-title {
    color: var(--text-primary);
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin-bottom: 2rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.form-control {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid var(--border-color);
    border-radius: var(--border-radius);
    background: var(--card-bg);
    color: var(--text-primary);
    transition: all 0.2s ease;
    font-size: 0.95rem;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    background: white;
}

.form-control:hover {
    border-color: var(--secondary-color);
}


.btn {
    padding: 0.875rem 2rem;
    border-radius: var(--border-radius);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
    color: white;
    box-shadow: var(--shadow-md);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    background: linear-gradient(135deg, var(--primary-hover) 0%, #3730a3 100%);
}

.btn-lg {
    padding: 1.25rem 3rem;
    font-size: 1rem;
}

.save-button-container {
    text-align: center;
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 2px solid var(--border-color);
}

.system-info-card {
    background: var(--card-bg);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border-color);
    margin-top: 2rem;
    overflow: hidden;
}

.system-info-header {
    background: linear-gradient(135deg, var(--secondary-color) 0%, #4b5563 100%);
    color: white;
    padding: 1.5rem 2rem;
    margin: 0;
}

.system-info-header h5 {
    margin: 0;
    font-weight: 700;
    font-size: 1.25rem;
}

.system-info-body {
    padding: 2rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background: var(--light-bg);
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
    transition: all 0.2s ease;
}

.info-item:hover {
    background: white;
    box-shadow: var(--shadow-sm);
}

.info-item label {
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.info-item span {
    color: var(--text-secondary);
    font-weight: 500;
    background: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    border: 1px solid var(--border-color);
    font-size: 0.9rem;
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: var(--border-radius);
    border: none;
    margin-bottom: 1.5rem;
    font-weight: 500;
    box-shadow: var(--shadow-sm);
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success-color);
    border-left: 4px solid var(--success-color);
}

.alert-danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger-color);
    border-left: 4px solid var(--danger-color);
}

@media (max-width: 768px) {
    .main-content {
        padding: 1rem;
    }
    
    .page-header {
        padding: 1.5rem;
        text-align: center;
    }
    
    .settings-tabs .nav-tabs {
        padding: 0 1rem;
    }
    
    .settings-tabs .nav-link {
        padding: 1rem 1.5rem;
        font-size: 0.9rem;
    }
    
    .tab-pane {
        padding: 1.5rem;
    }
    
    .settings-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .btn-lg {
        padding: 1rem 2rem;
        width: 100%;
    }
}

@media (max-width: 480px) {
    .settings-tabs .nav-link {
        padding: 0.875rem 1rem;
        font-size: 0.85rem;
    }
    
    .tab-pane {
        padding: 1rem;
    }
    
    .page-header h2 {
        font-size: 1.5rem;
    }
}

.setting-group {
    background: rgba(79, 70, 229, 0.02);
    padding: 1.5rem;
    border-radius: var(--border-radius);
    border: 1px solid rgba(79, 70, 229, 0.1);
    margin-bottom: 1rem;
}

.icon-wrapper {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
}

.fade-in {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching functionality with smooth animations
    const tabLinks = document.querySelectorAll('[data-toggle="tab"]');
    
    tabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active from all tabs and content
            document.querySelectorAll('.nav-link').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('show', 'active');
            });
            
            // Add active to clicked tab
            this.classList.add('active');
            
            // Show corresponding content with animation
            const targetId = this.getAttribute('href').substring(1);
            const targetPane = document.getElementById(targetId);
            if (targetPane) {
                setTimeout(() => {
                    targetPane.classList.add('show', 'active', 'fade-in');
                }, 100);
            }
        });
    });
    
    // Form validation
    const form = document.querySelector('form');
    const saveButton = document.querySelector('.btn-primary');
    
    form.addEventListener('submit', function(e) {
        saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        saveButton.disabled = true;
    });
    
    // Auto-save indication for inputs
    const inputs = document.querySelectorAll('.form-control');
    inputs.forEach(input => {
        input.addEventListener('change', function() {
            this.style.borderColor = 'var(--warning-color)';
            setTimeout(() => {
                this.style.borderColor = 'var(--border-color)';
            }, 1000);
        });
    });
});
</script>


<?php require_once '../../components/footer.php'; ?>