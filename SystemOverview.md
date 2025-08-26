# Complete System Overview

## Introduction

The Coaching Center HR Management System is a comprehensive web-based application designed specifically for managing human resources in coaching centers. This system digitizes HR processes, reducing manual paperwork and administrative overhead while improving efficiency in staff management.

## Key Features & Benefits

### Core Features

1. **User Management**
   - Multi-role access control (Admin, HR, Teacher, Accounts)
   - Secure authentication with password hashing
   - Session management with timeout

2. **Job Management**
   - Job posting creation and management
   - Application tracking and status updates
   - Candidate evaluation workflows

3. **Employee Management**
   - Teacher profile management
   - Employee onboarding processes
   - Document storage (CVs, profiles)

4. **Attendance Tracking**
   - Class schedule-based attendance
   - Real-time attendance marking
   - Attendance reports and analytics

5. **Payroll Processing**
   - Salary configuration management
   - Automated salary calculations
   - Payment disbursement tracking

6. **Classroom & Schedule Management**
   - Classroom resource management
   - Class schedule creation and maintenance
   - Teacher-subject-classroom allocation

7. **Reporting & Analytics**
   - Attendance reports
   - Salary reports
   - System activity logs

### Benefits

#### For Administrators
- Centralized control over all system aspects
- Streamlined HR operations
- Enhanced decision-making through analytics

#### For HR Personnel
- Efficient job posting and application management
- Simplified onboarding processes
- Candidate evaluation tools

#### For Teachers
- Easy access to personal information
- Attendance tracking capabilities
- Salary information access

#### For Accounts Team
- Automated salary processing
- Payment management
- Financial reporting tools

## Target Users & Use Cases

### User Roles

1. **Administrator**
   - System-wide configuration
   - User management
   - Overall system oversight

2. **HR Manager**
   - Job postings management
   - Applicant evaluation
   - Employee onboarding coordination

3. **Teacher**
   - Personal profile management
   - Attendance marking
   - Schedule viewing

4. **Accounts Personnel**
   - Salary processing
   - Payment management
   - Financial reporting

### Primary Use Cases

- **Recruitment Process**: Job posting → Application submission → Evaluation → Onboarding
- **Attendance Management**: Schedule creation → Daily attendance marking → Reporting
- **Payroll Processing**: Salary configuration → Monthly calculation → Payment disbursement
- **Resource Management**: Classroom allocation → Schedule optimization → Utilization tracking

## System Architecture

### High-Level Overview

The system follows a three-tier architecture pattern:

```
┌─────────────────────────────────────────────────────────────┐
│                    Presentation Layer                       │
├─────────────────────────────────────────────────────────────┤
│  Web Browser  │  Responsive UI  │  Role-based Dashboards    │
└─────────┬───────────────────────────────────────────────┬───┘
          │                                               │
┌─────────▼───────────────────────────────────────────────▼───┐
│                    Application Layer                        │
├─────────────────────────────────────────────────────────────┤
│  Authentication  │  Business Logic  │  Data Processing      │
│  Access Control  │  Workflow Mgmt   │  Report Generation    │
└─────────┬───────────────────────────────────────────────┬───┘
          │                                               │
┌─────────▼───────────────────────────────────────────────▼───┐
│                      Data Layer                             │
├─────────────────────────────────────────────────────────────┤
│                    MySQL Database                           │
│                                                             │
│  Users  │  Teachers  │  Attendance  │  Payroll  │  Logs     │
└─────────────────────────────────────────────────────────────┘
```

### Technology Stack

#### Frontend
- **HTML5/CSS3** for structure and styling
- **JavaScript** for interactivity
- **Bootstrap** for responsive design
- **Font Awesome** for icons

#### Backend
- **PHP 7.4+** as the primary programming language
- **Custom MVC Architecture** for code organization
- **PDO** for database interactions

#### Database
- **MySQL** for data storage
- **Normalized schema** for data integrity

#### Security
- **Password hashing** using PHP's password_hash()
- **CSRF protection** with tokens
- **SQL injection prevention** with prepared statements
- **Input sanitization** for all user inputs

### Data Flow

1. **User Request**: Users access the system through web browsers
2. **Authentication**: System validates user credentials and role
3. **Request Processing**: Application layer processes business logic
4. **Data Operations**: Database interactions for CRUD operations
5. **Response Generation**: Results formatted and sent to user interface
6. **Activity Logging**: System logs all significant actions

## Database Schema

The system uses a normalized database structure with the following key tables:

### Core Tables

1. **users** - System user accounts with role-based access
2. **teachers** - Teacher profile information
3. **job_postings** - Available positions
4. **cv_applications** - Job applications from candidates
5. **subjects** - Academic subjects offered
6. **classrooms** - Physical classroom resources
7. **class_schedule** - Teacher-subject-classroom scheduling
8. **teacher_attendance** - Attendance records
9. **salary_config** - Salary configuration settings
10. **salary_disbursements** - Monthly salary processing
11. **employee_onboarding** - New employee onboarding process
12. **onboarding_tasks** - Individual onboarding tasks
13. **system_logs** - Activity logging for audit trails

### Key Relationships

- Users can be associated with Teachers (one-to-one)
- Teachers can have multiple attendance records
- Class schedules link Teachers, Subjects, and Classrooms
- Salary configurations apply to individual Teachers
- Applications link to Job Postings (many-to-one)

## Security Architecture

### Authentication
- Secure login with password hashing
- Session management with timeout
- Role-based access control

### Data Protection
- Input validation and sanitization
- Prepared statements to prevent SQL injection
- CSRF token protection for forms
- Secure file upload handling

### Access Control
- Role-based permissions
- Module-level access restrictions
- Function-level authorization checks

## Deployment Architecture

### Server Requirements
- **Web Server**: Apache or Nginx
- **PHP**: Version 7.4 or higher
- **Database**: MySQL 5.7 or higher
- **Storage**: Adequate space for file uploads

### Installation Process
1. Database configuration
2. Directory structure setup
3. Table creation
4. Default user creation
5. Permission settings

### Scalability Considerations
- Modular code structure
- Database indexing for performance
- Caching opportunities
- Load balancing compatibility

## Integration Points

### Email Services
- SMTP configuration for notifications
- Automated email alerts for system events

### File Storage
- CV and profile picture storage
- Secure file access controls

### Reporting
- Export capabilities (PDF, Excel)
- Dashboard analytics
- Custom report generation

This comprehensive system provides coaching centers with a robust, secure, and scalable solution for managing their human resources efficiently.