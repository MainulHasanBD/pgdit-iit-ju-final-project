<?php
require_once 'database.php';

class Installer {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function createTables() {
        $conn = $this->db->getConnection();
        
        $tables = [
            // Users table
            "CREATE TABLE IF NOT EXISTS users (
                id INT PRIMARY KEY AUTO_INCREMENT,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM('admin', 'hr', 'teacher', 'accounts') NOT NULL,
                status ENUM('active', 'inactive') DEFAULT 'active',
                last_login TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            
            // Job postings table
            "CREATE TABLE IF NOT EXISTS job_postings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                requirements TEXT,
                salary_range VARCHAR(100),
                posted_date DATE NOT NULL,
                deadline DATE,
                status ENUM('active', 'closed') DEFAULT 'active',
                posted_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (posted_by) REFERENCES users(id) ON DELETE SET NULL
            )",
            
            // CV applications table
            "CREATE TABLE IF NOT EXISTS cv_applications (
                id INT PRIMARY KEY AUTO_INCREMENT,
                job_posting_id INT NOT NULL,
                candidate_name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(20),
                address TEXT,
                cv_file_path VARCHAR(500),
                cover_letter TEXT,
                application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('applied', 'shortlisted', 'interviewed', 'selected', 'rejected') DEFAULT 'applied',
                notes TEXT,
                FOREIGN KEY (job_posting_id) REFERENCES job_postings(id) ON DELETE CASCADE
            )",
            
            // Teachers table
            "CREATE TABLE IF NOT EXISTS teachers (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT UNIQUE,
                employee_id VARCHAR(50) UNIQUE,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                phone VARCHAR(20),
                address TEXT,
                hire_date DATE,
                qualification TEXT,
                subjects JSON,
                salary DECIMAL(10,2),
                status ENUM('active', 'inactive') DEFAULT 'active',
                profile_picture VARCHAR(500),
                created_from_cv_id INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (created_from_cv_id) REFERENCES cv_applications(id) ON DELETE SET NULL
            )",
            
            // Subjects table
            "CREATE TABLE IF NOT EXISTS subjects (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                code VARCHAR(20) UNIQUE NOT NULL,
                description TEXT,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )",
            
            // Classrooms table
            "CREATE TABLE IF NOT EXISTS classrooms (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                capacity INT DEFAULT 30,
                location VARCHAR(255),
                equipment TEXT,
                status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )",
            
            // Class schedule table
            "CREATE TABLE IF NOT EXISTS class_schedule (
                id INT PRIMARY KEY AUTO_INCREMENT,
                subject_id INT NOT NULL,
                teacher_id INT NOT NULL,
                classroom_id INT NOT NULL,
                day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
                FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
                FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                UNIQUE KEY unique_schedule (teacher_id, day_of_week, start_time, end_time),
                UNIQUE KEY unique_classroom_schedule (classroom_id, day_of_week, start_time, end_time)
            )",
            
            // Teacher attendance table
            "CREATE TABLE IF NOT EXISTS teacher_attendance (
                id INT PRIMARY KEY AUTO_INCREMENT,
                teacher_id INT NOT NULL,
                schedule_id INT NOT NULL,
                date DATE NOT NULL,
                check_in_time TIMESTAMP NULL,
                check_out_time TIMESTAMP NULL,
                status ENUM('present', 'absent', 'late', 'partial') DEFAULT 'present',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
                FOREIGN KEY (schedule_id) REFERENCES class_schedule(id) ON DELETE CASCADE,
                UNIQUE KEY unique_attendance (teacher_id, schedule_id, date)
            )",
            
            // Salary configuration table
            "CREATE TABLE IF NOT EXISTS salary_config (
                id INT PRIMARY KEY AUTO_INCREMENT,
                teacher_id INT NOT NULL,
                basic_salary DECIMAL(10,2) NOT NULL,
                allowances DECIMAL(10,2) DEFAULT 0,
                deductions DECIMAL(10,2) DEFAULT 0,
                effective_from DATE NOT NULL,
                effective_to DATE NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )",
            
            // Salary disbursements table
            "CREATE TABLE IF NOT EXISTS salary_disbursements (
                id INT PRIMARY KEY AUTO_INCREMENT,
                teacher_id INT NOT NULL,
                month INT NOT NULL CHECK (month BETWEEN 1 AND 12),
                year INT NOT NULL CHECK (year >= 2020),
                basic_salary DECIMAL(10,2) NOT NULL,
                allowances DECIMAL(10,2) DEFAULT 0,
                deductions DECIMAL(10,2) DEFAULT 0,
                attendance_bonus DECIMAL(10,2) DEFAULT 0,
                attendance_penalty DECIMAL(10,2) DEFAULT 0,
                net_salary DECIMAL(10,2) NOT NULL,
                payment_date DATE NULL,
                payment_method ENUM('cash', 'bank_transfer', 'cheque') DEFAULT 'bank_transfer',
                status ENUM('pending', 'processed', 'paid') DEFAULT 'pending',
                processed_by INT,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
                FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
                UNIQUE KEY unique_salary (teacher_id, month, year)
            )",
            
            // Employee Onboarding table
            "CREATE TABLE IF NOT EXISTS employee_onboarding (
                id INT PRIMARY KEY AUTO_INCREMENT,
                application_id INT,
                candidate_name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(20),
                position VARCHAR(255) NOT NULL,
                department VARCHAR(100),
                salary DECIMAL(10,2),
                start_date DATE NOT NULL,
                status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
                completed_at TIMESTAMP NULL,
                notes TEXT,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (application_id) REFERENCES cv_applications(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )",
            
            // Onboarding Tasks table
            "CREATE TABLE IF NOT EXISTS onboarding_tasks (
                id INT PRIMARY KEY AUTO_INCREMENT,
                onboarding_id INT NOT NULL,
                task_name VARCHAR(255) NOT NULL,
                task_description TEXT,
                task_order INT DEFAULT 0,
                status ENUM('pending', 'in_progress', 'completed', 'skipped') DEFAULT 'pending',
                due_date DATE NULL,
                completed_by INT,
                completed_at TIMESTAMP NULL,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (onboarding_id) REFERENCES employee_onboarding(id) ON DELETE CASCADE,
                FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL
            )",
            
            // System logs table
            "CREATE TABLE IF NOT EXISTS system_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT,
                action VARCHAR(255) NOT NULL,
                table_name VARCHAR(100),
                record_id INT,
                old_values JSON,
                new_values JSON,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )"
        ];
        
        try {
            foreach ($tables as $table) {
                $conn->exec($table);
            }
            return true;
        } catch (PDOException $e) {
            error_log("Database installation error: " . $e->getMessage());
            return false;
        }
    }
    
    public function createDefaultAdmin() {
        $conn = $this->db->getConnection();
        
        $query = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        
        $username = 'admin';
        $email = 'admin@coachingcenter.com';
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $role = 'admin';
        
        return $stmt->execute([$username, $email, $password, $role]);
    }
}
?>