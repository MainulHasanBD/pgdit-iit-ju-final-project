<div class="sidebar" id="sidebar">
    <div class="sidebar-nav">
        <?php
        $currentRole = $_SESSION['role'];
        $currentPage = basename($_SERVER['PHP_SELF']);
        
        // Define menu items for each role
        $menuItems = [
            'admin' => [
                ['url' => 'dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'title' => 'Dashboard'],
                ['url' => 'users.php', 'icon' => 'fas fa-users', 'title' => 'Users'],
                ['url' => 'teachers.php', 'icon' => 'fas fa-chalkboard-teacher', 'title' => 'Teachers'],
                ['url' => 'subjects.php', 'icon' => 'fas fa-book', 'title' => 'Subjects'],
                ['url' => 'classrooms.php', 'icon' => 'fas fa-door-open', 'title' => 'Classrooms'],
                ['url' => 'schedule.php', 'icon' => 'fas fa-calendar-alt', 'title' => 'Schedule'],
                ['url' => '../common/reports.php', 'icon' => 'fas fa-chart-bar', 'title' => 'Reports'],
                ['url' => 'settings.php', 'icon' => 'fas fa-cog', 'title' => 'Settings']
            ],
            'hr' => [
                ['url' => 'dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'title' => 'Dashboard'],
                ['url' => 'job-postings.php', 'icon' => 'fas fa-briefcase', 'title' => 'Job Postings'],
                ['url' => 'applications.php', 'icon' => 'fas fa-file-alt', 'title' => 'Applications'],
                ['url' => 'onboarding.php', 'icon' => 'fas fa-user-plus', 'title' => 'Onboarding'],
                ['url' => 'teachers.php', 'icon' => 'fas fa-chalkboard-teacher', 'title' => 'Teachers'],
                ['url' => 'attendance.php', 'icon' => 'fas fa-clock', 'title' => 'Attendance'],
                ['url' => '../common/reports.php', 'icon' => 'fas fa-chart-bar', 'title' => 'Reports']
            ],
            'teacher' => [
                ['url' => 'dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'title' => 'Dashboard'],
                ['url' => 'schedule.php', 'icon' => 'fas fa-calendar-alt', 'title' => 'My Schedule'],
                ['url' => 'attendance.php', 'icon' => 'fas fa-clock', 'title' => 'Attendance'],
                ['url' => 'salary.php', 'icon' => 'fas fa-money-bill', 'title' => 'Salary'],
                ['url' => 'profile.php', 'icon' => 'fas fa-user', 'title' => 'Profile']
            ],
            'accounts' => [
                ['url' => 'dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'title' => 'Dashboard'],
                ['url' => 'salary-management.php', 'icon' => 'fas fa-money-bill-wave', 'title' => 'Salary Management'],
                ['url' => 'disbursements.php', 'icon' => 'fas fa-hand-holding-usd', 'title' => 'Disbursements'],
                ['url' => 'bulk-operations.php', 'icon' => 'fas fa-cogs', 'title' => 'Bulk Operations'],
                ['url' => '../common/reports.php', 'icon' => 'fas fa-chart-line', 'title' => 'Reports'],
                ['url' => 'settings.php', 'icon' => 'fas fa-cog', 'title' => 'Settings']
            ]
        ];
        
        $items = $menuItems[$currentRole] ?? [];
        
        foreach ($items as $item) {
            $isActive = ($currentPage === basename($item['url'])) ? 'active' : '';
            echo '<div class="nav-item">';
            echo '<a href="' . $item['url'] . '" class="nav-link ' . $isActive . '">';
            echo '<i class="nav-icon ' . $item['icon'] . '"></i>';
            echo $item['title'];
            echo '</a>';
            echo '</div>';
        }
        ?>
    </div>
</div>