# Technical Documentation

## Codebase Structure & Guidelines

### Directory Structure

```
coaching-hr-system/
├── assets/
│   ├── css/              # Stylesheets
│   ├── js/               # JavaScript files
│   └── uploads/          # User uploaded files
├── components/           # Reusable UI components
├── config/               # Configuration files
├── includes/             # Core functionality classes
├── install/              # Installation scripts
├── modules/              # Role-specific modules
│   ├── admin/            # Admin functionality
│   ├── hr/               # HR functionality
│   ├── teacher/          # Teacher functionality
│   ├── accounts/         # Accounts functionality
│   └── common/           # Shared functionality
├── public/               # Publicly accessible files
└── vendor/               # Third-party libraries
```

### Module Structure

Each module follows a consistent structure:
```
module-name/
├── dashboard.php         # Module dashboard
├── list-pages.php        # List/view pages
├── form-pages.php        # Create/edit forms
├── process-pages.php     # Data processing scripts
└── module-specific.php   # Module-specific functionality
```

### Coding Standards

#### PHP Standards
- Follow PSR-12 coding standards
- Use meaningful variable and function names
- Comment complex logic
- Validate and sanitize all user inputs
- Use prepared statements for database queries

#### File Naming Conventions
- Use lowercase with hyphens for filenames
- Use descriptive names that reflect content
- PHP files: `.php` extension
- CSS files: `.css` extension
- JavaScript files: `.js` extension

#### Database Naming Conventions
- Use lowercase with underscores
- Use plural form for table names
- Use singular form for column names
- Foreign key columns: `referenced_table_id`

## API Documentation

### Authentication API

#### Login Endpoint
```
POST /login.php
Content-Type: application/x-www-form-urlencoded

Parameters:
- username (string, required)
- password (string, required)
- csrf_token (string, required)

Response:
- Success: Redirect to dashboard
- Error: JSON with error message
```

#### Logout Endpoint
```
GET /logout.php

Response:
- Redirect to login page
```

### User Management API

#### Get User Details
```
GET /modules/admin/users.php?action=view&id={user_id}

Response:
- Success: User details in HTML format
- Error: Error message
```

#### Create User
```
POST /modules/admin/users.php?action=add

Parameters:
- username (string, required)
- email (string, required)
- password (string, required)
- role (string, required)
- csrf_token (string, required)

Response:
- Success: Redirect to user list
- Error: Error message with form
```

#### Update User
```
POST /modules/admin/users.php?action=edit&id={user_id}

Parameters:
- username (string, required)
- email (string, required)
- role (string, required)
- status (string, optional)
- csrf_token (string, required)

Response:
- Success: Redirect to user list
- Error: Error message with form
```

#### Delete User
```
POST /modules/admin/users.php?action=delete&id={user_id}
Content-Type: application/x-www-form-urlencoded

Parameters:
- csrf_token (string, required)

Response:
- Success: JSON success response
- Error: JSON error response
```

### Job Posting API

#### Get Job Postings
```
GET /modules/hr/job-postings.php

Response:
- HTML page with job postings table
```

#### Create Job Posting
```
POST /modules/hr/job-postings.php?action=add

Parameters:
- title (string, required)
- description (text, required)
- requirements (text, optional)
- salary_range (string, optional)
- deadline (date, optional)
- csrf_token (string, required)

Response:
- Success: Redirect to job postings list
- Error: Error message with form
```

### Application API

#### Submit Application
```
POST /public/apply.php

Parameters:
- job_id (integer, required)
- name (string, required)
- email (string, required)
- phone (string, required)
- address (text, optional)
- cover_letter (text, optional)
- cv (file, required)
- csrf_token (string, required)

Response:
- Success: Success message
- Error: Error message with form
```

#### Get Applications
```
GET /modules/hr/applications.php

Parameters:
- status (string, optional)
- job_id (integer, optional)

Response:
- HTML page with applications table
```

## Database Schema & ERD

### Entity Relationship Diagram

```
users
├── id (PK)
├── username
├── email
├── password
├── role
├── status
├── last_login
├── created_at
└── updated_at

job_postings
├── id (PK)
├── title
├── description
├── requirements
├── salary_range
├── posted_date
├── deadline
├── status
├── posted_by (FK: users.id)
└── created_at

cv_applications
├── id (PK)
├── job_posting_id (FK: job_postings.id)
├── candidate_name
├── email
├── phone
├── address
├── cv_file_path
├── cover_letter
├── application_date
├── status
├── notes
└── created_at

teachers
├── id (PK)
├── user_id (FK: users.id, UNIQUE)
├── employee_id (UNIQUE)
├── first_name
├── last_name
├── email
├── phone
├── address
├── hire_date
├── qualification
├── subjects (JSON)
├── salary
├── status
├── profile_picture
├── created_from_cv_id (FK: cv_applications.id)
├── created_at
└── updated_at

subjects
├── id (PK)
├── name
├── code (UNIQUE)
├── description
├── created_by (FK: users.id)
└── created_at

classrooms
├── id (PK)
├── name
├── capacity
├── location
├── equipment
├── status
├── created_by (FK: users.id)
└── created_at

class_schedule
├── id (PK)
├── subject_id (FK: subjects.id)
├── teacher_id (FK: teachers.id)
├── classroom_id (FK: classrooms.id)
├── day_of_week
├── start_time
├── end_time
├── is_active
├── created_by (FK: users.id)
├── created_at
├── UNIQUE: teacher_id, day_of_week, start_time, end_time
└── UNIQUE: classroom_id, day_of_week, start_time, end_time

teacher_attendance
├── id (PK)
├── teacher_id (FK: teachers.id)
├── schedule_id (FK: class_schedule.id)
├── date
├── check_in_time
├── check_out_time
├── status
├── notes
├── created_at
├── updated_at
└── UNIQUE: teacher_id, schedule_id, date

salary_config
├── id (PK)
├── teacher_id (FK: teachers.id)
├── basic_salary
├── allowances
├── deductions
├── effective_from
├── effective_to
├── is_active
├── created_by (FK: users.id)
└── created_at

salary_disbursements
├── id (PK)
├── teacher_id (FK: teachers.id)
├── month
├── year
├── basic_salary
├── allowances
├── deductions
├── attendance_bonus
├── attendance_penalty
├── net_salary
├── payment_date
├── payment_method
├── status
├── processed_by (FK: users.id)
├── notes
├── created_at
├── updated_at
└── UNIQUE: teacher_id, month, year

employee_onboarding
├── id (PK)
├── application_id (FK: cv_applications.id)
├── candidate_name
├── email
├── phone
├── position
├── department
├── salary
├── start_date
├── status
├── completed_at
├── notes
├── created_by (FK: users.id)
├── created_at
└── updated_at

onboarding_tasks
├── id (PK)
├── onboarding_id (FK: employee_onboarding.id)
├── task_name
├── task_description
├── task_order
├── status
├── due_date
├── completed_by (FK: users.id)
├── completed_at
├── notes
├── created_at
└── updated_at

system_logs
├── id (PK)
├── user_id (FK: users.id)
├── action
├── table_name
├── record_id
├── old_values (JSON)
├── new_values (JSON)
├── ip_address
├── user_agent
└── created_at
```

## Development Setup

### Local Environment Setup

#### Prerequisites
1. Install PHP 7.4 or higher
2. Install MySQL 5.7 or higher
3. Install a web server (Apache/Nginx)
4. Install Composer (optional, for dependency management)

#### Setting Up Development Environment

1. Clone the repository:
   ```bash
   git clone https://your-repo-url/coaching-hr-system.git
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Configure database:
   - Create database
   - Update `config/database.php` with connection details

4. Run installation script:
   ```bash
   php install/setup.php
   ```

5. Start development server:
   ```bash
   php -S localhost:8000
   ```

### Development Workflow

#### Creating New Features
1. Create a new branch for the feature
2. Implement the feature following coding standards
3. Write necessary documentation
4. Test thoroughly
5. Create pull request for review

#### Database Changes
1. Update `config/install.php` with new table definitions
2. Create migration scripts if needed
3. Test schema changes in development environment
4. Document changes in release notes

## Testing Guidelines

### Unit Testing

#### PHP Unit Tests
Create test files in a `tests/` directory:
```php
// Example test structure
class UserTest extends PHPUnit\Framework\TestCase
{
    public function testUserCreation()
    {
        // Test implementation
    }
}
```

#### Testing Authentication
- Test valid login scenarios
- Test invalid credentials
- Test session timeout
- Test role-based access control

#### Testing Data Operations
- Test CRUD operations for each entity
- Test data validation
- Test error handling
- Test edge cases

### Integration Testing

#### API Testing
- Test all API endpoints
- Verify response codes
- Validate response data
- Test error scenarios

#### UI Testing
- Test form submissions
- Verify page navigation
- Check responsive design
- Test cross-browser compatibility

### Testing Tools

#### PHP Testing Frameworks
- PHPUnit for unit testing
- Codeception for integration testing

#### Browser Testing
- Selenium for automated browser testing
- Manual testing on multiple browsers

#### Performance Testing
- Apache Bench (ab) for load testing
- MySQL performance tuning

## CI/CD Pipeline

### Continuous Integration

#### GitHub Actions Workflow
```yaml
name: CI
on: [push, pull_request]
jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '7.4'
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: phpunit
```

#### Pre-commit Hooks
- Code linting
- Security scanning
- Unit test execution

### Deployment Process

#### Staging Deployment
1. Merge to staging branch
2. Automated tests run
3. Deploy to staging environment
4. Manual testing
5. Approve for production

#### Production Deployment
1. Merge to production branch
2. Automated tests run
3. Deploy to production environment
4. Monitor for issues
5. Rollback if necessary

### Monitoring and Logging

#### Application Monitoring
- Error logging
- Performance metrics
- User activity tracking
- System resource usage

#### Database Monitoring
- Query performance
- Connection pooling
- Storage usage
- Backup status

This technical documentation provides developers with comprehensive information about the Coaching Center HR Management System, including code structure, APIs, database schema, and development guidelines.