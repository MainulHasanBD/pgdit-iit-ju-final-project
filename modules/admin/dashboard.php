<?php
$pageTitle = 'Admin Dashboard';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../components/header.php';
require_once '../../components/sidebar.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->requireRole('admin');

$db = new Database();
$conn = $db->getConnection();

// Function to format dates
function formatDate($date, $format = 'M j, Y g:i A') {
    return date($format, strtotime($date));
}

// Get dashboard statistics with error handling
$stats = [];

try {
    // Total Teachers
    $query = "SELECT COUNT(*) as total FROM teachers WHERE status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total_teachers'] = $stmt->fetchColumn() ?: 0;

    // Total Applications
    $query = "SELECT COUNT(*) as total FROM cv_applications";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total_applications'] = $stmt->fetchColumn() ?: 0;

    // Total Subjects
    $query = "SELECT COUNT(*) as total FROM subjects";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total_subjects'] = $stmt->fetchColumn() ?: 0;

    // Total Classrooms
    $query = "SELECT COUNT(*) as total FROM classrooms WHERE status = 'active'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total_classrooms'] = $stmt->fetchColumn() ?: 0;

    // Recent activities
    $query = "SELECT sl.*, u.username FROM system_logs sl 
              LEFT JOIN users u ON sl.user_id = u.id 
              ORDER BY sl.created_at DESC LIMIT 10";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    $stats = ['total_teachers' => 0, 'total_applications' => 0, 'total_subjects' => 0, 'total_classrooms' => 0];
    $recent_activities = [];
}
?>

<div class="main-content">
    <div class="dashboard-header">
        <h2>Admin Dashboard</h2>
        <div class="last-updated">
            <span>Last updated: <?php echo date('M j, Y g:i A'); ?></span>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="dashboard-stats">
        <div class="stat-card primary">
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($stats['total_teachers']); ?></div>
                <div class="stat-label">Active Teachers</div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($stats['total_applications']); ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-file-alt"></i>
            </div>
        </div>
        
        <div class="stat-card info">
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($stats['total_subjects']); ?></div>
                <div class="stat-label">Subjects</div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-book"></i>
            </div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($stats['total_classrooms']); ?></div>
                <div class="stat-label">Classrooms</div>
            </div>
            <div class="stat-icon">
                <i class="fas fa-door-open"></i>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="classrooms.php?action=add" class="quick-action">
            <div class="quick-action-icon">
               <i class="fas fa-plus"></i>
            </div>
            <div class="quick-action-content">
                <div class="quick-action-title">Add Class Room</div>
                <div class="quick-action-description">Create new Class Room</div>
            </div>
        </a>
        
        <a href="subjects.php?action=add" class="quick-action">
            <div class="quick-action-icon">
                <i class="fas fa-plus"></i>
            </div>
            <div class="quick-action-content">
                <div class="quick-action-title">Add Subject</div>
                <div class="quick-action-description">Create new subject</div>
            </div>
        </a>
        
        <a href="schedule.php" class="quick-action">
            <div class="quick-action-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="quick-action-content">
                <div class="quick-action-title">Manage Schedule</div>
                <div class="quick-action-description">View and edit class schedule</div>
            </div>
        </a>
        
        <a href="../common/reports.php" class="quick-action">
            <div class="quick-action-icon">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="quick-action-content">
                <div class="quick-action-title">View Reports</div>
                <div class="quick-action-description">Generate system reports</div>
            </div>
        </a>
    </div>

    <!-- Recent Activities -->
    <div class="material-card">
        <div class="card-header">
            <h5>Recent System Activities</h5>
        </div>
        <div class="card-body">
            <?php if (empty($recent_activities)): ?>
                <div class="no-data">
                    <i class="fas fa-info-circle"></i>
                    <p>No recent activities found</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Table</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_activities as $activity): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($activity['username'] ?? 'System'); ?></td>
                                    <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                    <td><?php echo htmlspecialchars($activity['table_name'] ?? '-'); ?></td>
                                    <td><?php echo formatDate($activity['created_at'], 'M j, g:i A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* CSS Variables Definition */
:root {
    --primary-color: #007bff;
    --success-color: #28a745;
    --info-color: #17a2b8;
    --warning-color: #ffc107;
    --danger-color: #dc3545;
    --text-color: #333;
    --text-muted: #6c757d;
    --shadow: 0 2px 4px rgba(0,0,0,0.1);
    --shadow-lg: 0 4px 8px rgba(0,0,0,0.15);
    --border-radius: 8px;
}

.main-content {
    padding: 20px;
    background-color: #f8f9fa;
    min-height: 100vh;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 10px;
}

.dashboard-header h2 {
    color: var(--text-color);
    margin: 0;
    font-weight: 600;
}

.last-updated span {
    color: var(--text-muted);
    font-size: 14px;
}

/* Dashboard Statistics */
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px 20px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border-left: 4px solid #ddd;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.stat-card.primary { border-left-color: var(--primary-color); }
.stat-card.success { border-left-color: var(--success-color); }
.stat-card.info { border-left-color: var(--info-color); }
.stat-card.warning { border-left-color: var(--warning-color); }

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: var(--text-color);
    line-height: 1;
    margin-bottom: 5px;
}

.stat-label {
    color: var(--text-muted);
    font-size: 14px;
    font-weight: 500;
}

.stat-icon {
    font-size: 40px;
    color: rgba(0,0,0,0.1);
    margin-left: 15px;
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.quick-action {
    background: white;
    padding: 20px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    text-decoration: none;
    color: inherit;
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
    border: 1px solid transparent;
}

.quick-action:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-color);
    text-decoration: none;
    color: inherit;
}

.quick-action-icon {
    width: 50px;
    height: 50px;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    flex-shrink: 0;
}

.quick-action-content {
    flex: 1;
}

.quick-action-title {
    font-weight: 600;
    color: var(--text-color);
    margin-bottom: 2px;
    font-size: 16px;
}

.quick-action-description {
    font-size: 13px;
    color: var(--text-muted);
    line-height: 1.3;
}

/* Material Card */
.material-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.material-card .card-header {
    background: #f8f9fa;
    padding: 20px;
    border-bottom: 1px solid #eee;
}

.material-card .card-header h5 {
    margin: 0;
    color: var(--text-color);
    font-weight: 600;
}

.material-card .card-body {
    padding: 0;
}

/* Table Styles */
.table-responsive {
    border-radius: 0 0 var(--border-radius) var(--border-radius);
}

.table {
    margin: 0;
    border-collapse: collapse;
}

.table thead th {
    background: #f8f9fa;
    color: var(--text-color);
    font-weight: 600;
    border: none;
    padding: 15px 20px;
    font-size: 14px;
}

.table tbody td {
    padding: 15px 20px;
    border-top: 1px solid #eee;
    color: var(--text-color);
    font-size: 14px;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
}

.no-data {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}

.no-data i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.no-data p {
    margin: 0;
    font-size: 16px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .main-content {
        padding: 15px;
    }
    
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .dashboard-stats,
    .quick-actions {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .stat-card,
    .quick-action {
        padding: 20px 15px;
    }
    
    .stat-number {
        font-size: 28px;
    }
    
    .table-responsive {
        font-size: 13px;
    }
    
    .table thead th,
    .table tbody td {
        padding: 12px 15px;
    }
}

@media (max-width: 480px) {
    .stat-icon {
        display: none;
    }
    
    .quick-action-icon {
        width: 40px;
        height: 40px;
        margin-right: 12px;
    }
    
    .quick-action-title {
        font-size: 15px;
    }
}
</style>

<?php require_once '../../components/footer.php'; ?>
