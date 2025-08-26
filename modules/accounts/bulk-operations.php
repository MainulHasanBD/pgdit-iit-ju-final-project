<?php
$pageTitle = 'Bulk Operations';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../components/header.php';
require_once '../../components/sidebar.php';
require_once '../../includes/security.php';
require_once '../../includes/bulk-operations.php';

$auth = new Auth();
$auth->requireRole('accounts');

$bulkOps = new BulkOperations();
$message = '';
$messageType = '';

// Handle bulk operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $operation = $_POST['operation'] ?? '';
        
        switch ($operation) {
            case 'bulk_salary_processing':
                $teacherIds = $_POST['teacher_ids'] ?? [];
                $month = (int)$_POST['month'];
                $year = (int)$_POST['year'];
                $options = [
                    'bonus_amount' => (float)($_POST['bonus_amount'] ?? 0),
                    'additional_deduction' => (float)($_POST['additional_deduction'] ?? 0)
                ];
                
                $result = $bulkOps->bulkProcessSalaries($teacherIds, $month, $year, $options);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'bulk_salary_increase':
                $teacherIds = $_POST['teacher_ids'] ?? [];
                $increaseType = $_POST['increase_type'] ?? 'percentage';
                $increaseValue = (float)$_POST['increase_value'];
                
                $result = $bulkOps->bulkSalaryIncrease($teacherIds, $increaseType, $increaseValue);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'bulk_payment_disbursement':
                $disbursementIds = $_POST['disbursement_ids'] ?? [];
                $paymentMethod = $_POST['payment_method'] ?? 'bank_transfer';
                $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
                
                $result = $bulkOps->bulkPaymentDisbursement($disbursementIds, $paymentMethod, $paymentDate);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
        }
    }
}

// Get teachers for selection
$db = new Database();
$conn = $db->getConnection();

$teachersQuery = "SELECT id, employee_id, CONCAT(first_name, ' ', last_name) as name, salary, status FROM teachers ORDER BY first_name, last_name";
$teachersStmt = $conn->prepare($teachersQuery);
$teachersStmt->execute();
$teachers = $teachersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending disbursements
$disbursementsQuery = "SELECT 
                         sd.*,
                         CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                         t.employee_id
                       FROM salary_disbursements sd
                       LEFT JOIN teachers t ON sd.teacher_id = t.id
                       WHERE sd.status = 'processed'
                       ORDER BY sd.year DESC, sd.month DESC";
$disbursementsStmt = $conn->prepare($disbursementsQuery);
$disbursementsStmt->execute();
$pendingDisbursements = $disbursementsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Bulk Operations</h2>
        <div class="text-muted">
            Perform bulk operations on teacher salaries and payments
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Bulk Salary Processing -->
    <div class="material-card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Bulk Salary Processing</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                <input type="hidden" name="operation" value="bulk_salary_processing">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Select Teachers</label>
                            <div class="teacher-selection" style="max-height: 200px; overflow-y: auto; border: 1px solid #e0e0e0; padding: 10px;">
                                <div class="form-check">
                                    <input type="checkbox" id="selectAllTeachers" class="form-check-input">
                                    <label for="selectAllTeachers" class="form-check-label font-weight-bold">Select All</label>
                                </div>
                                <hr>
                                <?php foreach ($teachers as $teacher): ?>
                                    <div class="form-check">
                                        <input type="checkbox" name="teacher_ids[]" value="<?php echo $teacher['id']; ?>" 
                                               id="teacher_<?php echo $teacher['id']; ?>" class="form-check-input teacher-checkbox">
                                        <label for="teacher_<?php echo $teacher['id']; ?>" class="form-check-label">
                                            <?php echo htmlspecialchars($teacher['name']); ?> 
                                            (<?php echo htmlspecialchars($teacher['employee_id']); ?>) - 
                                            <?php echo formatCurrency($teacher['salary']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Month</label>
                            <select name="month" class="form-control" required>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i == date('n') ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Year</label>
                            <select name="year" class="form-control" required>
                                <?php for ($year = date('Y') - 1; $year <= date('Y') + 1; $year++): ?>
                                    <option value="<?php echo $year; ?>" <?php echo $year == date('Y') ? 'selected' : ''; ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Special Bonus (Optional)</label>
                            <input type="number" name="bonus_amount" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Additional Deduction (Optional)</label>
                            <input type="number" name="additional_deduction" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to process salaries for selected teachers?')">
                    <i class="fas fa-calculator"></i> Process Salaries
                </button>
            </form>
        </div>
    </div>

    <!-- Bulk Payment Disbursement -->
    <?php if (!empty($pendingDisbursements)): ?>
        <div class="material-card">
            <div class="card-header">
                <h5 class="mb-0">Bulk Payment Disbursement</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <input type="hidden" name="operation" value="bulk_payment_disbursement">
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Payment Method</label>
                                <select name="payment_method" class="form-control">
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="cash">Cash</option>
                                    <option value="cheque">Cheque</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Payment Date</label>
                                <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary" onclick="return confirm('Process selected payments?')">
                                        <i class="fas fa-money-bill-wave"></i> Process Payments
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAllDisbursements" onchange="toggleAllDisbursements()">
                                    </th>
                                    <th>Employee</th>
                                    <th>Period</th>
                                    <th>Net Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingDisbursements as $disbursement): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="disbursement_ids[]" value="<?php echo $disbursement['id']; ?>" class="disbursement-checkbox">
                                        </td>
                                        <td>
                                            <div class="font-weight-bold"><?php echo htmlspecialchars($disbursement['teacher_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($disbursement['employee_id']); ?></small>
                                        </td>
                                        <td><?php echo date('F Y', mktime(0, 0, 0, $disbursement['month'], 1, $disbursement['year'])); ?></td>
                                        <td class="font-weight-bold"><?php echo formatCurrency($disbursement['net_salary']); ?></td>
                                        <td><?php echo getStatusBadge($disbursement['status']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Select all teachers
document.getElementById('selectAllTeachers').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.teacher-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Select all disbursements
function toggleAllDisbursements() {
    const selectAll = document.getElementById('selectAllDisbursements');
    const checkboxes = document.querySelectorAll('.disbursement-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}
</script>

<?php require_once '../../components/footer.php'; ?>