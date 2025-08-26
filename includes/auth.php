<?php
require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/../config/database.php');
require_once(__DIR__ . '/security.php');

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function login($username, $password) {
        $conn = $this->db->getConnection();
        
        $query = "SELECT id, username, email, password, role, status FROM users WHERE (username = ? OR email = ?) AND status = 'active'";
        $stmt = $conn->prepare($query);
        $stmt->execute([$username, $username]);
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $user['password'])) {
                // Update last login
                $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->execute([$user['id']]);
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                
                // Log the login
                $this->logActivity($user['id'], 'User Login', 'users', $user['id']);
                
                return ['success' => true, 'user' => $user];
            }
        }
        
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'User Logout', 'users', $_SESSION['user_id']);
        }
        
        session_unset();
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
                $this->logout();
                return false;
            }
            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }
    
    public function hasRole($role) {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }
    
    public function hasAnyRole($roles) {
        return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
    }
    
    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . BASE_URL . 'login.php');
            exit();
        }
    }
    
    public function requireRole($role) {
        $this->requireAuth();
        if (!$this->hasRole($role)) {
            header('HTTP/1.0 403 Forbidden');
            die('Access denied');
        }
    }
    
    public function requireAnyRole($roles) {
        $this->requireAuth();
        if (!$this->hasAnyRole($roles)) {
            header('HTTP/1.0 403 Forbidden');
            die('Access denied');
        }
    }
    
    private function logActivity($userId, $action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null) {
        $conn = $this->db->getConnection();
        
        $query = "INSERT INTO system_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        
        $stmt->execute([
            $userId,
            $action,
            $tableName,
            $recordId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
}
?>