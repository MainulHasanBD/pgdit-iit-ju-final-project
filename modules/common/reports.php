<?php
$pageTitle = 'Reports & Analytics';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../components/header.php';
require_once '../../components/sidebar.php';
require_once '../../includes/security.php';
require_once '../../includes/export-manager.php';
require_once '../../includes/functions.php';
$auth = new Auth();
$auth->requireAnyRole(['admin', 'hr', 'accounts']);

$db = new Database();
$conn = $db->getConnection();
$exportManager = new ExportManager();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $reportType = $_POST['report_type'] ?? '';
        $format = $_POST['format'] ?? 'excel';
        $filters = $_POST['filters'] ?? [];
        
        try {
            switch ($reportType) {
                case 'teachers':
                    $exportManager->exportTeacherSalaryReport($filters, $format);
                    break;
                    
                case 'salary':
                    $month = (int)$filters['month'];
                    $year = (int)$filters['year'];
                    $exportManager->exportTeacherSalaryReport($month, $year, $format);
                    break;
                    
                case 'attendance':
                    $exportManager->exportTeacherSalaryReport($filters, $format);
                    break;
                    
                case 'applications':
                    $exportManager->exportTeacherSalaryReport($filters, $format);
                    break;
                    
                case 'financial':
                    $exportManager->exportTeacherSalaryReport($filters, $format);
                    break;
                    
                default:
                    $message = 'Invalid report type selected';
                    $messageType = 'danger';
            }
        } catch (Exception $e) {
            $message = 'Error generating report: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

$stats = [];


$generalQuery = "SELECT 
                   (SELECT COUNT(*) FROM teachers WHERE status = 'active') as active_teachers,
                   (SELECT COUNT(*) FROM cv_applications) as total_applications,
                   (SELECT COUNT(*) FROM job_postings WHERE status = 'active') as active_jobs,
                   (SELECT COUNT(*) FROM salary_disbursements WHERE status = 'paid') as paid_salaries";
$generalStmt = $conn->prepare($generalQuery);
$generalStmt->execute();
$stats['general'] = $generalStmt->fetch(PDO::FETCH_ASSOC);

$currentMonth = date('n');
$currentYear = date('Y');

$monthlyQuery = "SELECT 
                   (SELECT COUNT(*) FROM cv_applications WHERE MONTH(application_date) = ? AND YEAR(application_date) = ?) as monthly_applications,
                   (SELECT COUNT(*) FROM teachers WHERE MONTH(hire_date) = ? AND YEAR(hire_date) = ?) as monthly_hires,
                   (SELECT SUM(net_salary) FROM salary_disbursements WHERE month = ? AND year = ? AND status IN ('processed', 'paid')) as monthly_salary_total";
$monthlyStmt = $conn->prepare($monthlyQuery);
$monthlyStmt->execute([$currentMonth, $currentYear, $currentMonth, $currentYear, $currentMonth, $currentYear]);
$stats['monthly'] = $monthlyStmt->fetch(PDO::FETCH_ASSOC);

$popularReports = [
    'teachers' => 'Teachers List',
    'salary' => 'Monthly Salary Report',
    'attendance' => 'Attendance Report',
    'applications' => 'Job Applications',
    'financial' => 'Financial Summary'
];
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Reports & Analytics</h2>
        <div class="text-muted">
            Generate detailed reports and export data
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Statistics Overview -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['general']['active_teachers']; ?></div>
                <div class="stat-label">Active Teachers</div>
                <i class="stat-icon fas fa-chalkboard-teacher"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card success">
                <div class="stat-number"><?php echo $stats['general']['total_applications']; ?></div>
                <div class="stat-label">Total Applications</div>
                <i class="stat-icon fas fa-file-alt"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card info">
                <div class="stat-number"><?php echo $stats['monthly']['monthly_applications']; ?></div>
                <div class="stat-label">This Month Apps</div>
                <i class="stat-icon fas fa-calendar"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card warning">
                <div class="stat-number"><?php echo formatCurrency($stats['monthly']['monthly_salary_total'] ?? 0); ?></div>
                <div class="stat-label">Monthly Salary</div>
                <i class="stat-icon fas fa-money-bill-wave"></i>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Quick Reports -->
        <div class="col-md-8">
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Generate Reports</h5>
                </div>
                <div class="card-body">
                    <div class="report-categories">
                        <!-- Teachers Report -->
                        <div class="report-category">
                            <h6><i class="fas fa-users text-primary"></i> Teachers Reports</h6>
                            <div class="report-options">
                                <button class="btn btn-outline report-btn" onclick="showReportModal('teachers')">
                                    <i class="fas fa-list"></i> Teachers List
                                </button>
                                <button class="btn btn-outline report-btn" onclick="showReportModal('attendance')">
                                    <i class="fas fa-clock"></i> Attendance Report
                                </button>
                            </div>
                        </div>

                        <!-- HR Reports -->
                        <div class="report-category">
                            <h6><i class="fas fa-briefcase text-success"></i> HR Reports</h6>
                            <div class="report-options">
                                <button class="btn btn-outline report-btn" onclick="showReportModal('applications')">
                                    <i class="fas fa-file-alt"></i> Job Applications
                                </button>
                                <button class="btn btn-outline report-btn" onclick="showReportModal('hiring')">
                                    <i class="fas fa-user-plus"></i> Hiring Summary
                                </button>
                            </div>
                        </div>

                        <!-- Financial Reports -->
                        <div class="report-category">
                            <h6><i class="fas fa-chart-line text-warning"></i> Financial Reports</h6>
                            <div class="report-options">
                                <button class="btn btn-outline report-btn" onclick="showReportModal('salary')">
                                    <i class="fas fa-money-bill"></i> Salary Report
                                </button>
                                <button class="btn btn-outline report-btn" onclick="showReportModal('financial')">
                                    <i class="fas fa-calculator"></i> Financial Summary
                                </button>
                            </div>
                        </div>

                        <!-- System Reports -->
                        <div class="report-category">
                            <h6><i class="fas fa-cogs text-info"></i> System Reports</h6>
                            <div class="report-options">
                                <button class="btn btn-outline report-btn" onclick="showReportModal('activity')">
                                    <i class="fas fa-history"></i> Activity Logs
                                </button>
                                <button class="btn btn-outline report-btn" onclick="showReportModal('usage')">
                                    <i class="fas fa-chart-pie"></i> Usage Statistics
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Reports & Quick Stats -->
        <div class="col-md-4">
            <!-- Popular Reports -->
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Popular Reports</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($popularReports as $type => $name): ?>
                        <div class="popular-report">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><?php echo $name; ?></span>
                                <div>
                                    <button class="btn btn-sm btn-outline" onclick="quickExport('<?php echo $type; ?>', 'excel')">
                                        <i class="fas fa-file-excel"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline" onclick="quickExport('<?php echo $type; ?>', 'pdf')">
                                        <i class="fas fa-file-pdf"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Analytics Summary -->
            <div class="material-card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Analytics</h5>
                </div>
                <div class="card-body">
                    <canvas id="quickChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Generation Modal -->
<div class="modal" id="reportModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h5 class="modal-title" id="modalTitle">Generate Report</h5>
            <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST" id="reportForm">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                <input type="hidden" name="report_type" id="reportType">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Export Format</label>
                            <select name="format" class="form-control">
                                <option value="excel">Excel (.xlsx)</option>
                                <option value="pdf">PDF Document</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Date Range</label>
                            <select name="filters[date_range]" class="form-control">
                                <option value="all">All Time</option>
                                <option value="current_month">Current Month</option>
                                <option value="last_month">Last Month</option>
                                <option value="current_year">Current Year</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Dynamic Filters -->
                <div id="dynamicFilters"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-download"></i> Generate Report
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: var(--shadow);
    position: relative;
    margin-bottom: 20px;
}

.stat-card.success { border-left: 4px solid var(--success-color); }
.stat-card.info { border-left: 4px solid var(--info-color); }
.stat-card.warning { border-left: 4px solid var(--warning-color); }

.stat-number {
    font-size: 28px;
    font-weight: bold;
    color: var(--primary-color);
}

.stat-label {
    color: var(--text-muted);
    margin-top: 5px;
}

.stat-icon {
    position: absolute;
    right: 20px;
    top: 20px;
    font-size: 32px;
    color: rgba(0,0,0,0.1);
}

.report-category {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #f0f0f0;
}

.report-category:last-child {
    border-bottom: none;
}

.report-category h6 {
    margin-bottom: 15px;
    font-weight: 600;
}

.report-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
}

.report-btn {
    text-align: left;
    padding: 15px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.report-btn:hover {
    border-color: var(--primary-color);
    background-color: rgba(25, 118, 210, 0.05);
}

.popular-report {
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.popular-report:last-child {
    border-bottom: none;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function showReportModal(reportType) {
    document.getElementById('reportType').value = reportType;
    document.getElementById('modalTitle').textContent = 'Generate ' + reportType.charAt(0).toUpperCase() + reportType.slice(1) + ' Report';
    
    // Load dynamic filters based on report type
    loadDynamicFilters(reportType);
    
    showModal('reportModal');
}

function loadDynamicFilters(reportType) {
    const container = document.getElementById('dynamicFilters');
    let filtersHTML = '';
    
    switch (reportType) {
        case 'salary':
            filtersHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Month</label>
                            <select name="filters[month]" class="form-control">
                                ${Array.from({length: 12}, (_, i) => {
                                    const month = i + 1;
                                    const monthName = new Date(2023, i).toLocaleString('default', { month: 'long' });
                                    const selected = month === new Date().getMonth() + 1 ? 'selected' : '';
                                    return `<option value="${month}" ${selected}>${monthName}</option>`;
                                }).join('')}
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Year</label>
                            <select name="filters[year]" class="form-control">
                                ${Array.from({length: 3}, (_, i) => {
                                    const year = new Date().getFullYear() - i;
                                    const selected = i === 0 ? 'selected' : '';
                                    return `<option value="${year}" ${selected}>${year}</option>`;
                                }).join('')}
                            </select>
                        </div>
                    </div>
                </div>
            `;
            break;
            
        case 'teachers':
            filtersHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="filters[status]" class="form-control">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Salary Range</label>
                            <select name="filters[salary_range]" class="form-control">
                                <option value="">All Ranges</option>
                                <option value="0-30000">BDT 0 - 30,000</option>
                                <option value="30000-50000">BDT 30,000 - 50,000</option>
                                <option value="50000+">BDT 50,000+</option>
                            </select>
                        </div>
                    </div>
                </div>
            `;
            break;
            
        case 'applications':
            filtersHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Application Status</label>
                            <select name="filters[status]" class="form-control">
                                <option value="">All Status</option>
                                <option value="applied">Applied</option>
                                <option value="shortlisted">Shortlisted</option>
                                <option value="interviewed">Interviewed</option>
                                <option value="selected">Selected</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                </div>
            `;
            break;
    }
    
    container.innerHTML = filtersHTML;
}

function quickExport(reportType, format) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
        <input type="hidden" name="report_type" value="${reportType}">
        <input type="hidden" name="format" value="${format}">
        <input type="hidden" name="filters[date_range]" value="current_month">
    `;
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// Quick analytics chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('quickChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Active Teachers', 'Applications', 'Jobs', 'Paid Salaries'],
            datasets: [{
                data: [
                    <?php echo $stats['general']['active_teachers']; ?>,
                    <?php echo $stats['general']['total_applications']; ?>,
                    <?php echo $stats['general']['active_jobs']; ?>,
                    <?php echo $stats['general']['paid_salaries']; ?>
                ],
                backgroundColor: [
                    '#2196f3',
                    '#4caf50',
                    '#ff9800',
                    '#f44336'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: {
                            size: 11
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once '../../components/footer.php'; ?>