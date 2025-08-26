# Installation & Setup Guide

## System Requirements

### Server Requirements
- **Operating System**: Linux/Windows/macOS
- **Web Server**: Apache 2.4+ or Nginx 1.10+
- **PHP Version**: 7.4 or higher
- **Database**: MySQL 5.7+ or MariaDB 10.2+
- **Web Browser**: Modern browsers (Chrome, Firefox, Safari, Edge)

### PHP Extensions Required
- pdo
- pdo_mysql
- json
- mbstring
- zip
- gd

### Hardware Requirements
- **RAM**: Minimum 2GB (4GB recommended)
- **Disk Space**: Minimum 500MB free space
- **Processor**: 1GHz dual-core or better

## Installation Process

### Step 1: Download and Extract
1. Download the application files
2. Extract to your web server's document root directory

### Step 2: Database Setup
1. Create a new MySQL database:
   ```sql
   CREATE DATABASE coaching_hrms;
   ```
2. Create a database user with appropriate privileges:
   ```sql
   CREATE USER 'hrms_user'@'localhost' IDENTIFIED BY 'secure_password';
   GRANT ALL PRIVILEGES ON coaching_hrms.* TO 'hrms_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

### Step 3: Configure Application Settings
1. Open `config/database.php` and update database connection details:
   ```php
   private $host = 'localhost';
   private $db_name = 'coaching_hrms';
   private $username = 'hrms_user';
   private $password = 'secure_password';
   ```

2. Open `config/config.php` and update application settings:
   ```php
   define('APP_NAME', 'Coaching Center HR');
   define('BASE_URL', 'http://your-domain.com/');
   ```

### Step 4: Run Installation Script
1. Navigate to the installation directory in your terminal
2. Run the setup script:
   ```bash
   php install/setup.php
   ```

This will:
- Create required directories
- Set appropriate permissions
- Create database tables
- Generate a default admin user

### Step 5: Default Admin Credentials
After installation, use these credentials to log in:
- **Username**: admin
- **Email**: admin@coachingcenter.com
- **Password**: admin123

⚠️ **Important**: Change the default password immediately after first login!

### Step 6: Directory Permissions
Ensure the following directories are writable by the web server:
- `assets/uploads/`
- `assets/uploads/cvs/`
- `assets/uploads/profile_pics/`
- `logs/`

Set permissions using:
```bash
chmod 755 assets/uploads
chmod 755 assets/uploads/cvs
chmod 755 assets/uploads/profile_pics
chmod 755 logs
```

## Configuration Options

### Email Configuration
Update email settings in `config/config.php`:
```php
define('SMTP_HOST', 'your-smtp-server.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@domain.com');
define('SMTP_PASSWORD', 'your-email-password');
define('FROM_EMAIL', 'noreply@coachingcenter.com');
define('FROM_NAME', 'Coaching Center HR');
```

### Security Settings
Configure security parameters in `config/config.php`:
```php
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('PASSWORD_MIN_LENGTH', 8);
```

### Upload Settings
Configure file upload paths:
```php
define('UPLOAD_PATH', 'assets/uploads/');
define('CV_UPLOAD_PATH', 'assets/uploads/cvs/');
define('PROFILE_UPLOAD_PATH', 'assets/uploads/profile_pics/');
```

## Deployment Guide

### Local Development Setup
1. Install XAMPP/WAMP/MAMP or LAMP stack
2. Clone the repository to your web directory
3. Follow the installation steps above
4. Access via `http://localhost/your-project-directory`

### Production Deployment

#### Apache Configuration
Add the following to your virtual host configuration:
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/your/project
    
    <Directory /path/to/your/project>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/hrms_error.log
    CustomLog ${APACHE_LOG_DIR}/hrms_access.log combined
</VirtualHost>
```

#### Nginx Configuration
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/your/project;
    index index.php index.html;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    }
    
    location ~ /\.ht {
        deny all;
    }
}
```

### SSL Configuration
For production environments, always use HTTPS:
1. Obtain an SSL certificate (Let's Encrypt, commercial CA)
2. Configure your web server for SSL
3. Update `BASE_URL` in `config/config.php` to use `https://`

### Backup and Recovery

#### Database Backup
```bash
mysqldump -u username -p coaching_hrms > backup.sql
```

#### File Backup
Regularly backup:
- `assets/uploads/` directory
- `config/` directory (excluding sensitive files)
- Database backups

## Troubleshooting

### Common Issues

#### Database Connection Error
- Verify database credentials in `config/database.php`
- Ensure MySQL service is running
- Check database user privileges

#### Permission Denied Errors
- Verify directory permissions for upload directories
- Check web server user permissions

#### Installation Script Failures
- Ensure PHP CLI version meets requirements
- Verify all required PHP extensions are installed
- Check error logs for specific error messages

### Error Logs
Check these logs for troubleshooting:
- Web server error logs
- Application logs in `logs/` directory
- PHP error logs

## System Maintenance

### Regular Maintenance Tasks
1. Database optimization
2. Log file rotation
3. Backup verification
4. Security updates

### Performance Optimization
1. Enable PHP OPcache
2. Configure MySQL query cache
3. Use CDN for static assets
4. Implement database indexing

This guide provides a complete installation and setup process for the Coaching Center HR Management System. Following these steps will ensure a successful deployment of the application.