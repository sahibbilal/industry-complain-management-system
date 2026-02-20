# ICMS - Industry Complaint Management System

A comprehensive web-based application for managing and resolving complaints in industrial and corporate environments.

## Features

- **User Registration & Authentication**: Secure login system with role-based access control
- **Complaint Submission**: Submit complaints with categorization and file attachments
- **Automatic Routing**: Intelligent complaint routing based on category and keywords
- **Complaint Tracking**: Real-time status tracking with complete history
- **Priority & Escalation**: SLA-based priority management and automatic escalation
- **Department Management**: Admin interface for managing departments and users
- **Feedback System**: Collect user feedback and ratings after resolution
- **Analytics Dashboard**: Comprehensive reports and statistics with charts
- **Security & Audit**: Complete audit logging and security features

## Technology Stack

- **Frontend**: HTML, CSS, Bootstrap 5
- **Backend**: PHP 8.1+
- **Database**: MySQL/MariaDB
- **Charts**: Chart.js
- **Development**: ddev

## Installation

### Prerequisites

- PHP 8.1 or higher
- MySQL/MariaDB
- ddev (for local development)
- Web server (Apache/Nginx)

### Setup Steps

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd ICMS
   ```

2. **Initialize ddev**
   ```bash
   ddev start
   ```

3. **Import database schema**
   ```bash
   ddev import-db --file=database/schema.sql
   ddev import-db --file=database/sample_data.sql
   ```

4. **Configure database connection**
   Edit `config/database.php` with your database credentials:
   ```php
   define('DB_HOST', 'db');
   define('DB_NAME', 'db');
   define('DB_USER', 'db');
   define('DB_PASS', 'db');
   ```

5. **Set permissions**
   ```bash
   chmod -R 755 uploads/
   ```

6. **Access the application**
   - URL: http://icms.ddev.site
   - Default admin: admin@icms.local / password

## User Roles

- **Complainant**: Submit and track complaints
- **Support Staff**: Handle assigned complaints
- **Manager**: Manage department complaints and escalations
- **QA Officer**: Review resolved complaints
- **Administrator**: Full system access
- **Senior Management**: Executive dashboard access

## Default Credentials

After importing sample data, the following test accounts are available:

### User Accounts

| Role | Name | Email | Password | Department |
|------|------|-------|----------|------------|
| **Administrator** | System Administrator | admin@icms.local | `password` | None (Admin) |
| **Support Staff** | John Support | support@icms.local | `password` | IT Department |
| **Manager** | Jane Manager | manager@icms.local | `password` | IT Department |
| **QA Officer** | Bob QA Officer | qa@icms.local | `password` | Quality Assurance |

### Quick Login Reference

- **Admin Account**: `admin@icms.local` / `password`
- **Support Staff**: `support@icms.local` / `password`
- **Manager**: `manager@icms.local` / `password`
- **QA Officer**: `qa@icms.local` / `password`

> **⚠️ Security Note**: All default passwords are set to `password`. Please change these passwords immediately after first login for security purposes.

## Cron Jobs

Set up automatic escalation:
```bash
*/15 * * * * php /path/to/ICMS/modules/complaints/auto_escalate.php
```

## Project Structure

```
ICMS/
├── config/          # Configuration files
├── includes/        # Common includes (header, footer, functions)
├── modules/         # Feature modules
│   ├── auth/       # Authentication
│   ├── complaints/ # Complaint management
│   ├── admin/      # Admin panel
│   ├── dashboard/  # Analytics
│   └── feedback/   # Feedback system
├── assets/         # CSS, JS, images
├── database/       # SQL scripts
└── uploads/        # User uploads
```

## Security Features

- Password hashing (bcrypt)
- CSRF protection
- SQL injection prevention (PDO prepared statements)
- XSS protection (input sanitization)
- Session timeout
- Role-based access control
- Audit logging
- File upload validation

## License

This project is developed as a Final Year Project (FYP).

## Support

For issues and questions, please contact the development team.
