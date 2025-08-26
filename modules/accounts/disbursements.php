<?php
$pageTitle = 'Salary Disbursements';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../components/header.php';
require_once '../../components/sidebar.php';
require_once '../../includes/security.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireRole('accounts');

$db = new Database();
$conn = $db->getConnection();

$message = '';
$messageType = '';

// Handle disbursement operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_status':
                $disbursementId = (int)$_POST['disbursement_id'];
                $status = $_POST['status'] ?? '';
                $paymentMethod = $_POST['payment_method'] ?? '';
                $paymentDate = $_POST['payment_date'] ?? null;
                
                if (in_array($status, ['pending', 'processed', 'paid'])) {
                    try {
                        $query = "UPDATE salary_disbursements SET status = ?, payment_method = ?, payment_date = ? WHERE id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$status, $paymentMethod, $paymentDate, $disbursementId]);
                        
                        $message = 'Disbursement status updated successfully!';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Error updating disbursement status';
                        $messageType = 'danger';
                    }
                }
                break;
                
            case 'bulk_approve':
                $disbursementIds = $_POST['disbursement_ids'] ?? [];
                if (!empty($disbursementIds)) {
                    try {
                        $placeholders = str_repeat('?,', count($disbursementIds) - 1) . '?';
                        $query = "UPDATE salary_disbursements SET status = 'processed' WHERE id IN ($placeholders) AND status = 'pending'";
                        $stmt = $conn->prepare($query);
                        $stmt->execute($disbursementIds);
                        
                        $approvedCount = $stmt->rowCount();
                        $message = "Successfully approved {$approvedCount} disbursement(s)";
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Error in bulk approval';
                        $messageType = 'danger';
                    }
                }
                break;
        }
    }
}

// Get disbursements with filters
$page = (int)($_GET['page'] ?? 1);
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$monthFilter = $_GET['month'] ?? '';
$yearFilter = $_GET['year'] ?? '';

$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(CONCAT(t.first_name, ' ', t.last_name) LIKE ? OR t.employee_id LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%"]);
}

if ($statusFilter) {
    $whereConditions[] = "sd.status = ?";
    $params[] = $statusFilter;
}

if ($monthFilter) {
    $whereConditions[] = "sd.month = ?";
    $params[] = $monthFilter;
}

if ($yearFilter) {
    $whereConditions[] = "sd.year = ?";
    $params[] = $yearFilter;
}

$whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

$countQuery = "SELECT COUNT(*) FROM salary_disbursements sd LEFT JOIN teachers t ON sd.teacher_id = t.id $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();

$pagination = Pagination::paginate($totalRecords, $page);
$offset = $pagination['offset'];

$query = "SELECT 
            sd.*,
            CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
            t.employee_id,
            u.username as processed_by_name
          FROM salary_disbursements sd
          LEFT JOIN teachers t ON sd.teacher_id = t.id
          LEFT JOIN users u ON sd.processed_by = u.id
          $whereClause
          ORDER BY sd.year DESC, sd.month DESC, sd.created_at DESC
          LIMIT $offset, " . RECORDS_PER_PAGE;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$disbursements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$summaryQuery = "SELECT 
                   COUNT(*) as total_count,
                   SUM(sd.net_salary) as total_amount,
                   COUNT(CASE WHEN sd.status = 'pending' THEN 1 END) as pending_count,
                   COUNT(CASE WHEN sd.status = 'processed' THEN 1 END) as processed_count,
                   COUNT(CASE WHEN sd.status = 'paid' THEN 1 END) as paid_count
                 FROM salary_disbursements sd 
                 LEFT JOIN teachers t ON sd.teacher_id = t.id 
                 $whereClause";

$summaryStmt = $conn->prepare($summaryQuery);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Salary Disbursements</h2>
        <div>
            <button class="btn btn-warning" onclick="showModal('bulkApproveModal')">
                <i class="fas fa-check-double"></i> Bulk Approve
            </button>
            <a href="../common/reports.php?type=disbursements" class="btn btn-info">
                <i class="fas fa-download"></i> Export Report
            </a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="summary-card">
                <div class="summary-number"><?php echo $summary['total_count']; ?></div>
                <div class="summary-label">Total Disbursements</div>
                <div class="summary-amount"><?php echo formatCurrency($summary['total_amount']); ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card pending">
                <div class="summary-number"><?php echo $summary['pending_count']; ?></div>
                <div class="summary-label">Pending</div>
                <i class="summary-icon fas fa-clock"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card processing">
                <div class="summary-number"><?php echo $summary['processed_count']; ?></div>
                <div class="summary-label">Processed</div>
                <i class="summary-icon fas fa-cog"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="summary-card completed">
                <div class="summary-number"><?php echo $summary['paid_count']; ?></div>
                <div class="summary-label">Paid</div>
                <i class="summary-icon fas fa-check-circle"></i>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="material-card mb-4">
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search employees..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="processed" <?php echo $statusFilter === 'processed' ? 'selected' : ''; ?>>Processed</option>
                        <option value="paid" <?php echo $statusFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="month" class="form-control">
                        <option value="">All Months</option>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $monthFilter == $i ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="year" class="form-control">
                        <option value="">All Years</option>
                        <?php for ($year = date('Y'); $year >= date('Y') - 3; $year--): ?>
                            <option value="<?php echo $year; ?>" <?php echo $yearFilter == $year ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
                <div class="col-md-1">
                    <a href="disbursements.php" class="btn btn-secondary w-100">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Disbursements Table -->
    <div class="material-card">
        <div class="card-header">
            <h5 class="mb-0">Disbursements List (<?php echo $totalRecords; ?> total)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll" onchange="toggleAllDisbursements()">
                            </th>
                            <th>Employee</th>
                            <th>Period</th>
                            <th>Basic Salary</th>
                            <th>Allowances</th>
                            <th>Deductions</th>
                            <th>Net Amount</th>
                            <th>Status</th>
                            <th>Payment Date</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($disbursements as $disbursement): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="disbursement_ids[]" value="<?php echo $disbursement['id']; ?>" class="disbursement-checkbox">
                                </td>
                                <td>
                                    <div>
                                        <div class="font-weight-bold"><?php echo htmlspecialchars($disbursement['teacher_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($disbursement['employee_id']); ?></small>
                                    </div>
                                </td>
                                <td><?php echo date('F Y', mktime(0, 0, 0, $disbursement['month'], 1, $disbursement['year'])); ?></td>
                                <td><?php echo formatCurrency($disbursement['basic_salary']); ?></td>
                                <td class="text-success">
                                    <?php if ($disbursement['allowances'] > 0): ?>
                                        +<?php echo formatCurrency($disbursement['allowances']); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-danger">
                                    <?php if ($disbursement['deductions'] > 0): ?>
                                        -<?php echo formatCurrency($disbursement['deductions']); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="font-weight-bold text-primary"><?php echo formatCurrency($disbursement['net_salary']); ?></td>
                                <td><?php echo getStatusBadge($disbursement['status']); ?></td>
                                <td>
                                    <?php echo $disbursement['payment_date'] ? formatDate($disbursement['payment_date'], 'M j, Y') : '-'; ?>
                                </td>
                                <td class="table-actions">
                                    <button class="btn btn-sm btn-warning" onclick="updateDisbursement(<?php echo $disbursement['id']; ?>, '<?php echo $disbursement['status']; ?>', '<?php echo $disbursement['payment_method']; ?>', '<?php echo $disbursement['payment_date']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-info" onclick="viewDetails(<?php echo $disbursement['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="card-footer">
                <?php echo Pagination::generatePaginationHTML($pagination, '?search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '&month=' . urlencode($monthFilter) . '&year=' . urlencode($yearFilter)); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Update Disbursement Modal -->
<div class="modal" id="updateDisbursementModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 class="modal-title">Update Disbursement</h5>
            <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="disbursement_id" id="modalDisbursementId">
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control" required>
                        <option value="pending">Pending</option>
                        <option value="processed">Processed</option>
                        <option value="paid">Paid</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-control">
                        <option value="">Select Method</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cash">Cash</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Payment Date</label>
                    <input type="date" name="payment_date" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Disbursement</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Approve Modal -->
<div class="modal" id="bulkApproveModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 class="modal-title">Bulk Approve Disbursements</h5>
            <button type="button" class="modal-close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST" id="bulkApproveForm">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="bulk_approve">
                
                <div class="alert alert-info">
                    <strong>Note:</strong> This will approve all selected pending disbursements.
                </div>
                
                <div id="selectedDisbursementsCount" class="text-muted">
                    No disbursements selected
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning" id="bulkApproveBtn" disabled>Approve Selected</button>
            </div>
        </form>
    </div>
</div>

<style>
.summary-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: var(--shadow);
    position: relative;
    margin-bottom: 20px;
}

.summary-card.pending { border-left: 4px solid var(--warning-color); }
.summary-card.processing { border-left: 4px solid var(--info-color); }
.summary-card.completed { border-left: 4px solid var(--success-color); }

.summary-number {
    font-size: 28px;
    font-weight: bold;
    color: var(--primary-color);
    margin-bottom: 8px;
}

.summary-label {
    color: var(--text-muted);
    margin-bottom: 4px;
}

.summary-amount {
    color: var(--success-color);
    font-weight: 500;
}

.summary-icon {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 32px;
    color: rgba(0,0,0,0.1);
}

.table-actions .btn {
    margin-right: 5px;
}
</style>

<script>
function updateDisbursement(id, status, paymentMethod, paymentDate) {
    document.getElementById('modalDisbursementId').value = id;
    document.querySelector('#updateDisbursementModal select[name="status"]').value = status;
    document.querySelector('#updateDisbursementModal select[name="payment_method"]').value = paymentMethod;
    document.querySelector('#updateDisbursementModal input[name="payment_date"]').value = paymentDate;
    showModal('updateDisbursementModal');
}

function toggleAllDisbursements() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.disbursement-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
    
    updateBulkApproveButton();
}

function updateBulkApproveButton() {
    const selectedCheckboxes = document.querySelectorAll('.disbursement-checkbox:checked');
    const count = selectedCheckboxes.length;
    const button = document.getElementById('bulkApproveBtn');
    const countDiv = document.getElementById('selectedDisbursementsCount');
    
    if (count > 0) {
        button.disabled = false;
        countDiv.textContent = `${count} disbursement(s) selected`;
        
        // Add selected IDs to form
        const form = document.getElementById('bulkApproveForm');
        const existingInputs = form.querySelectorAll('input[name="disbursement_ids[]"]');
        existingInputs.forEach(input => input.remove());
        
        selectedCheckboxes.forEach(checkbox => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'disbursement_ids[]';
            input.value = checkbox.value;
            form.appendChild(input);
        });
    } else {
        button.disabled = true;
        countDiv.textContent = 'No disbursements selected';
    }
}

// Add event listeners
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.disbursement-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkApproveButton);
    });
});
</script>

<?php require_once '../../components/footer.php'; ?>