<?php
require_once(__DIR__ . '/../includes/auth.php');
$auth = new Auth();
$auth->requireAuth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/material-theme.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/custom.css" rel="stylesheet">
    <!-- Fix for responsive design and dropdown -->
    <style>
        /* Navbar fixes */
        .navbar {
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 64px;
            display: flex;
            align-items: center;
        }
        
        .navbar .d-flex {
            width: 100%;
            height: 100%;
            align-items: center;
            padding: 0 20px;
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #1976d2;
            font-size: 20px;
            font-weight: 500;
            padding: 0;
        }
        
        .navbar-brand i {
            margin-right: 10px;
            font-size: 24px;
        }
        
        .navbar-toggle {
            background: none;
            border: none;
            font-size: 18px;
            padding: 10px;
            margin-right: 15px;
            cursor: pointer;
            color: #666;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }
        
        .navbar-toggle:hover {
            background-color: #f5f5f5;
        }
        
        .navbar-nav {
            margin-left: auto;
            display: flex;
            align-items: center;
        }
        
        .nav-item {
            position: relative;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s ease;
            cursor: pointer;
        }
        
        .nav-link:hover {
            background-color: #f5f5f5;
            text-decoration: none;
            color: #333;
        }
        
        .nav-link i {
            margin-right: 8px;
            font-size: 16px;
        }
        
        /* Dropdown Menu Fixes */
        .dropdown {
            position: relative;
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
            min-width: 200px;
            padding: 8px 0;
            z-index: 1050;
            display: none;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }
        
        .dropdown.show .dropdown-menu {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }
        
        .dropdown-item:hover {
            background-color: #f5f5f5;
            color: #333;
            text-decoration: none;
        }
        
        .dropdown-item i {
            margin-right: 10px;
            width: 16px;
            text-align: center;
        }
        
        .dropdown-divider {
            height: 1px;
            background-color: #e0e0e0;
            margin: 8px 0;
            border: none;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .navbar-toggle.d-none {
                display: block !important;
            }
            
            .navbar-brand {
                font-size: 18px;
            }
            
            .nav-link {
                padding: 8px 12px;
                font-size: 14px;
            }
            
            .dropdown-menu {
                right: 10px;
                min-width: 180px;
            }
        }
        
        /* Utility classes */
        .d-flex {
            display: flex;
        }
        
        .align-items-center {
            align-items: center;
        }
        
        .w-100 {
            width: 100%;
        }
        
        .d-none {
            display: none;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="d-flex align-items-center w-100">
            <button class="navbar-toggle d-none" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <a href="<?php echo BASE_URL;?>" class="navbar-brand">
                <i class="fas fa-graduation-cap"></i>
                <?php echo APP_NAME; ?>
            </a>
            <div class="navbar-nav">
                <div class="nav-item dropdown" id="userDropdown">
                    <a href="#" class="nav-link dropdown-toggle">
                        <i class="fas fa-user-circle"></i>
                        <span class="username"><?php echo $_SESSION['username']; ?></span>
                        <i class="fas fa-chevron-down" style="margin-left: 8px; font-size: 12px;"></i>
                    </a>
                    <div class="dropdown-menu">
                        <a href="<?php echo BASE_URL; ?>modules/<?php echo $_SESSION['role']; ?>/profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a href="<?php echo BASE_URL; ?>modules/admin/settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo BASE_URL; ?>modules/common/reports.php" class="dropdown-item">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo BASE_URL; ?>logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- JavaScript for dropdown functionality -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropdown = document.getElementById('userDropdown');
            const dropdownToggle = dropdown.querySelector('.dropdown-toggle');
            
            // Toggle dropdown on click
            dropdownToggle.addEventListener('click', function(e) {
                e.preventDefault();
                dropdown.classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!dropdown.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            });
            
            // Sidebar toggle functionality
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
                
                // Show/hide toggle button based on screen size
                function updateSidebarToggle() {
                    if (window.innerWidth <= 768) {
                        sidebarToggle.classList.remove('d-none');
                    } else {
                        sidebarToggle.classList.add('d-none');
                        sidebar.classList.remove('show');
                    }
                }
                
                window.addEventListener('resize', updateSidebarToggle);
                updateSidebarToggle();
            }
        });
    </script>
