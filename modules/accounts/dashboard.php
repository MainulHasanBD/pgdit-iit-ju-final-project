<?php
$pageTitle = 'Accounts Dashboard';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../components/header.php';
require_once '../../components/sidebar.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireRole('accounts');

$db = new Database();
$conn = $db->getConnection();

// Get financial statistics
$stats = [];

// Total monthly salary disbursements
$currentMonth = date('n');
$currentYear = date('Y');

$monthlyQuery = "SELECT 
                   COUNT(*) as total_disbursements,
                   SUM(net_salary) as total_amount,
                   COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
                   COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
                 FROM salary_disbursements 
                 WHERE month = ? AND year = ?";
$monthlyStmt = $conn->prepare($monthlyQuery);
$monthlyStmt->execute([$currentMonth, $currentYear]);
$monthlyStats = $monthlyStmt->fetch(PDO::FETCH_ASSOC);

// Year-to-date statistics
$ytdQuery = "SELECT 
               SUM(net_salary) as ytd_amount,
               COUNT(*) as ytd_disbursements
             FROM salary_disbursements 
             WHERE year = ? AND status IN ('processed', 'paid')";
$ytdStmt = $conn->prepare($ytdQuery);
$ytdStmt->execute([$currentYear]);
$ytdStats = $ytdStmt->fetch(PDO::FETCH_ASSOC);

// Average salary
$avgQuery = "SELECT AVG(basic_salary) as avg_salary FROM salary_config WHERE is_active = 1";
$avgStmt = $conn->prepare($avgQuery);
$avgStmt->execute();
$avgSalary = $avgStmt->fetchColumn();

// Payment status breakdown
$statusQuery = "SELECT status, COUNT(*) as count FROM salary_disbursements WHERE month = ? AND year = ? GROUP BY status";
$statusStmt = $conn->prepare($statusQuery);
$statusStmt->execute([$currentMonth, $currentYear]);
$statusBreakdown = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Recent transactions
$recentQuery = "SELECT 
                  sd.*,
                  CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                  t.employee_id
                FROM salary_disbursements sd
                LEFT JOIN teachers t ON sd.teacher_id = t.id
                ORDER BY sd.created_at DESC 
                LIMIT 10";
$recentStmt = $conn->prepare($recentQuery);
$recentStmt->execute();
$recentTransactions = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly trend (last 6 months)
$trendQuery = "SELECT 
                 CONCAT(year, '-', LPAD(month, 2, '0')) as period,
                 SUM(net_salary) as total_amount,
                 COUNT(*) as transaction_count
               FROM salary_disbursements 
               WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
               GROUP BY year, month
               ORDER BY year, month";
$trendStmt = $conn->prepare($trendQuery);
$trendStmt->execute();
$monthlyTrend = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

// Pending approvals
$pendingQuery = "SELECT COUNT(*) FROM salary_disbursements WHERE status = 'pending'";
$pendingStmt = $conn->prepare($pendingQuery);
$pendingStmt->execute();
$pendingApprovals = $pendingStmt->fetchColumn();
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Accounts Dashboard</h2>
        <div>
            <span class="text-muted">Financial Overview - <?php echo date('F Y'); ?></span>
        </div>
    </div>

    <!-- Alert for pending approvals -->
    <?php if ($pendingApprovals > 0): ?>
        <div class="alert alert-warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-exclamation-triangle"></i>
                    You have <strong><?php echo $pendingApprovals; ?></strong> salary disbursement(s) pending approval.
                </div>
                <a href="disbursements.php?status=pending" class="btn btn-warning btn-sm">Review Now</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Financial Statistics -->
    <div class="dashboard-stats">
        <div class="stat-card primary">
            <div class="stat-number"><?php echo formatCurrency($monthlyStats['total_amount'] ?? 0); ?></div>
            <div class="stat-label">Monthly Disbursements</div>
            <div class="stat-sublabel"><?php echo $monthlyStats['total_disbursements'] ?? 0; ?> transactions</div>
            <i class="stat-icon fas fa-money-bill-wave"></i>
        </div>
        
        <div class="stat-card success">
            <div class="stat-number"><?php echo formatCurrency($ytdStats['ytd_amount'] ?? 0); ?></div>
            <div class="stat-label">Year-to-Date Total</div>
            <div class="stat-sublabel"><?php echo $ytdStats['ytd_disbursements'] ?? 0; ?> payments</div>
            <i class="stat-icon fas fa-chart-line"></i>
        </div>
        
        <div class="stat-card info">
            <div class="stat-number"><?php echo formatCurrency($avgSalary ?? 0); ?></div>
            <div class="stat-label">Average Salary</div>
            <div class="stat-sublabel">per teacher</div>
            <i class="stat-icon fas fa-calculator"></i>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-number"><?php echo $monthlyStats['pending_count'] ?? 0; ?></div>
            <div class="stat-label">Pending Payments</div>
            <div class="stat-sublabel"><?php echo $monthlyStats['paid_count'] ?? 0; ?> completed</div>
            <i class="stat-icon fas fa-clock"></i>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="salary-management.php" class="quick-action">
            <div class="quick-action-icon bg-primary">
                <i class="fas fa-cog"></i>
            </div>
            <div class="quick-action-content">
                <div class="quick-action-title">Manage Salaries</div>
                <div class="quick-action-description">Configure teacher salaries</div>
            </div>
        </a>
        
        <a href="disbursements.php?action=process" class="quick-action">
            <div class="quick-action-icon bg-success">
                <i class="fas fa-play"></i>
            </div>
            <div class="quick-action-content">
                <div class="quick-action-title">Process Salaries</div>
                <div class="quick-action-description">Generate monthly payments</div>
            </div>
        </a>
        
        <a href="bulk-operations.php" class="quick-action">
            <div class="quick-action-icon bg-warning">
                <i class="fas fa-tasks"></i>
            </div>
            <div class="quick-action-content">
                <div class="quick-action-title">Bulk Operations</div>
                <div class="quick-action-description">Mass updates and processing</div>
            </div>
        </a>
        
        <a href="../common/reports.php?type=financial" class="quick-action">
            <div class="quick-action-icon bg-info">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="quick-action-content">
                <div class="quick-action-title">Financial Reports</div>
                <div class="quick-action-description">Generate reports</div>
            </div>
        </a>
    </div>

    <div class="row">
        <!-- Recent Transactions -->
        <div class="col-md-8">
            <div class="material-card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Transactions</h5>
                        <a href="disbursements.php" class="btn btn-sm btn-outline">View All</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Period</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentTransactions as $transaction): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <div class="font-weight-bold"><?php echo htmlspecialchars($transaction['teacher_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($transaction['employee_id']); ?></small>
                                            </div>
                                        </td>
                                        <td><?php echo date('M Y', mktime(0, 0, 0, $transaction['month'], 1, $transaction['year'])); ?></td>
                                        <td class="font-weight-bold"><?php echo formatCurrency($transaction['net_salary']); ?></td>
                                        <td><?php echo getStatusBadge($transaction['status']); ?></td>
                                        <td><?php echo formatDate($transaction['created_at'], 'M j'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Analytics -->
        <div class="col-md-4">
            <!-- Payment Status Distribution -->
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Payment Status</h5>
                </div>
                <div class="card-body">
                    <canvas id="statusChart" height="200"></canvas>
                </div>
            </div>

            <!-- Monthly Trend -->
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">6-Month Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="trendChart" height="150"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
}

.stat-card.primary { border-left: 4px solid var(--primary-color); }
.stat-card.success { border-left: 4px solid var(--success-color); }
.stat-card.info { border-left: 4px solid var(--info-color); }
.stat-card.warning { border-left: 4px solid var(--warning-color); }

.stat-number {
    font-size: 28px;
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 8px;
}

.stat-label {
    color: var(--text-color);
    font-weight: 500;
    margin-bottom: 4px;
}

.stat-sublabel {
    color: var(--text-muted);
    font-size: 14px;
}

.stat-icon {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 48px;
    color: rgba(0,0,0,0.1);
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.quick-action {
    background: white;
    border-radius: 8px;
    box-shadow: var(--shadow);
    padding: 20px;
    text-decoration: none;
    color: inherit;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.3s ease;
}

.quick-action:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.quick-action-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
}

.quick-action-title {
    font-weight: 500;
    color: var(--text-color);
}

.quick-action-description {
    font-size: 12px;
    color: var(--text-muted);
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Payment Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_keys($statusBreakdown)); ?>,
        datasets: [{
            data: <?php echo json_encode(array_values($statusBreakdown)); ?>,
            backgroundColor: ['#4caf50', '#ff9800', '#f44336', '#2196f3']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Monthly Trend Chart
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($monthlyTrend, 'period')); ?>,
        datasets: [{
            label: 'Total Amount',
            data: <?php echo json_encode(array_column($monthlyTrend, 'total_amount')); ?>,
            borderColor: '#2196f3',
            backgroundColor: 'rgba(33, 150, 243, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
</script>

<?php require_once '../../components/footer.php'; ?>