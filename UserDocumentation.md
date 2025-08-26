# User Documentation

## User Roles & Permissions

The Coaching Center HR Management System supports multiple user roles, each with specific permissions and access levels.

### Administrator
**Permissions**:
- Full system access
- User management (create, edit, delete users)
- Role assignment
- System configuration
- All HR, Teacher, and Accounts functionalities

### HR Manager
**Permissions**:
- Job posting management
- Application review and evaluation
- Employee onboarding process
- Teacher management
- Attendance tracking
- Report generation

### Teacher
**Permissions**:
- Personal profile management
- View class schedule
- Mark attendance
- View salary information
- Update personal details

### Accounts Personnel
**Permissions**:
- Salary configuration
- Salary processing and disbursement
- Financial reporting
- Bulk operations
- Payment management

## Login & Authentication Process

### Accessing the System
1. Open your web browser
2. Navigate to the application URL
3. You will be automatically redirected to the login page

### Login Procedure
1. Enter your username or email address
2. Enter your password
3. Click the "Login" button
4. If credentials are valid, you will be redirected to your dashboard

### Security Features
- **Session Timeout**: Sessions automatically expire after 1 hour of inactivity
- **CSRF Protection**: All forms include security tokens
- **Password Requirements**: Minimum 8 characters
- **Account Lockout**: Accounts locked after 5 failed attempts

### Password Recovery
If you forget your password:
1. Contact your system administrator
2. Admin can reset your password from the user management section

## Navigation Guide

### Main Interface Components

#### Navigation Bar
Located at the top of the screen, containing:
- Application logo and name
- User profile dropdown with settings and logout

#### Sidebar Menu
Located on the left side, showing role-specific navigation options:
- Dashboard
- Role-specific modules (varies by user role)
- Reports
- Settings (admin only)

#### Main Content Area
Central area where pages and forms are displayed

### Dashboard Overview

#### Administrator Dashboard
- System statistics (teachers, applications, subjects, classrooms)
- Quick action buttons (add classroom, add subject, manage schedule, view reports)
- Recent system activities

#### HR Manager Dashboard
- Job posting statistics
- Application tracking
- Onboarding progress
- Quick actions (add job, manage applications, onboarding, reports)

#### Teacher Dashboard
- Today's class schedule
- Weekly attendance summary
- Teaching subjects and hours
- Quick actions (mark attendance, view schedule, salary info, update profile)

#### Accounts Dashboard
- Salary processing overview
- Payment status tracking
- Financial summaries
- Quick actions (salary management, disbursements, bulk operations)

## Feature Walkthroughs

### Job Management (HR Role)

#### Creating a Job Posting
1. Navigate to "Job Postings" in the sidebar
2. Click "Add New Job" button
3. Fill in job details:
   - Title
   - Description
   - Requirements
   - Salary range
   - Deadline
4. Click "Save" to create the posting

#### Managing Applications
1. Navigate to "Applications" in the sidebar
2. View all applications in the table
3. Filter by status (applied, shortlisted, interviewed, selected, rejected)
4. Click on an application to view details
5. Update application status as needed
6. Add notes for future reference

### Employee Management (HR Role)

#### Adding a Teacher
1. Navigate to "Teachers" in the sidebar
2. Click "Add New Teacher" button
3. Fill in teacher details:
   - Personal information
   - Contact details
   - Qualifications
   - Salary information
4. Assign subjects (if applicable)
5. Click "Save" to create the teacher profile

#### Onboarding Process
1. Navigate to "Onboarding" in the sidebar
2. Create new onboarding process:
   - Select application or enter candidate details
   - Enter position and department
   - Set start date
   - Add onboarding tasks
3. Track progress of onboarding tasks
4. Mark tasks as completed when finished

### Attendance Management (HR/Teacher Roles)

#### Marking Attendance
1. Navigate to "Attendance" in the sidebar
2. Select date and class schedule
3. Mark attendance status (present, absent, late)
4. Add notes if necessary
5. Save attendance record

#### Viewing Attendance Reports
1. Navigate to "Reports" in the sidebar
2. Select "Attendance Reports"
3. Filter by date range, teacher, or subject
4. View attendance statistics
5. Export reports if needed

### Salary Management (Accounts Role)

#### Configuring Salary
1. Navigate to "Salary Management" in the sidebar
2. Select a teacher
3. Set basic salary, allowances, and deductions
4. Set effective date range
5. Save configuration

#### Processing Monthly Salaries
1. Navigate to "Disbursements" in the sidebar
2. Select month and year
3. System automatically calculates salaries based on:
   - Basic salary configuration
   - Attendance records
   - Allowances and deductions
4. Review calculated amounts
5. Process payments
6. Update payment status

### Schedule Management (Admin Role)

#### Creating Class Schedule
1. Navigate to "Schedule" in the sidebar
2. Click "Add New Schedule" button
3. Select subject, teacher, and classroom
4. Choose day of week and time slot
5. Ensure no scheduling conflicts
6. Save schedule

#### Managing Classrooms
1. Navigate to "Classrooms" in the sidebar
2. View all classrooms in the table
3. Add new classrooms with capacity and equipment details
4. Edit existing classroom information
5. Update classroom status (active, inactive, maintenance)

## FAQ / Troubleshooting

### Common Issues and Solutions

#### Issue: Unable to login
**Solution**:
1. Verify username/email and password
2. Check if account is active
3. Reset password if forgotten
4. Contact administrator if account is locked

#### Issue: Cannot upload CV/resume
**Solution**:
1. Check file format (PDF, DOC, DOCX only)
2. Verify file size (maximum 5MB)
3. Ensure file name doesn't contain special characters
4. Try a different browser

#### Issue: Attendance not saving
**Solution**:
1. Verify internet connection
2. Check if all required fields are filled
3. Ensure you're marking attendance for the correct date
4. Refresh page and try again

#### Issue: Salary calculation incorrect
**Solution**:
1. Verify salary configuration
2. Check attendance records for the month
3. Confirm allowances and deductions
4. Contact accounts personnel for review

### Browser Compatibility

The system is tested and works with:
- Google Chrome (latest version)
- Mozilla Firefox (latest version)
- Microsoft Edge (latest version)
- Safari (latest version)

### Mobile Access

The system is responsive and works on mobile devices:
- Smartphones (Android and iOS)
- Tablets
- Note that some features may be easier to use on desktop computers

### Performance Tips

1. **Browser Cache**: Clear browser cache periodically
2. **Internet Connection**: Ensure stable internet connection
3. **File Sizes**: Keep uploaded files under 5MB
4. **Session Management**: Log out when finished to free system resources

This user documentation provides comprehensive guidance for all user roles in the Coaching Center HR Management System. Following these instructions will help users effectively utilize all system features.