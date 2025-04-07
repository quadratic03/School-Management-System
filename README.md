# School Management System

A comprehensive School Management System built with PHP and MySQL that provides tools for managing students, teachers, courses, grades, attendance, and more.

## Features

- **User Management**: Admin, Teacher, and Student roles with appropriate access control
- **Student Management**: Add, edit, view, and delete student records
- **Teacher Management**: Manage teacher information and assignments
- **Class & Subject Management**: Create and manage classes and subjects
- **Enrollment System**: Enroll students in classes
- **Attendance Tracking**: Record and monitor student attendance
- **Grade Management**: Record and analyze student grades
- **Assignment Management**: Create, submit, and grade assignments
- **Reporting System**: Generate various reports on students, grades, attendance, etc.
- **Communication System**: Internal messaging between administrators, teachers, and students
- **Dashboard**: Customized dashboards for each user role with relevant information

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- XAMPP/WAMP/MAMP (for local development)

## Installation

1. **Clone the repository**

   ```bash
   git clone https://github.com/yourusername/SchoolManagementSystem.git
   ```

2. **Create the database**

   - Open phpMyAdmin or any MySQL client
   - Create a new database named `school_management`
   - Import the `database/setup.sql` file to create the tables and insert initial data

3. **Configure the application**

   - Open `config/config.php`
   - Update the database connection details if needed
   - Configure other settings as necessary

4. **Place the project in your web server directory**

   - For XAMPP: `htdocs/SchoolManagementSystem`
   - For WAMP: `www/SchoolManagementSystem`
   - For MAMP: `htdocs/SchoolManagementSystem`

5. **Access the application**

   - Open your browser and navigate to:
   - `http://localhost/SchoolManagementSystem`

## Default Login Credentials

| Role    | Username           | Password |
|---------|--------------------|----------|
| Admin   | admin@gmail.com    | password |
| Teacher | -                  | -        |
| Student | -                  | -        |

**Note**: After the first login with admin credentials, it is highly recommended to change the default password.

## Project Structure

```
SchoolManagementSystem/
│
├── assets/                     # Frontend assets
│   ├── css/                    # CSS files
│   ├── js/                     # JavaScript files
│   └── images/                 # Image files
│
├── config/                     # Configuration files
│   └── config.php              # Main configuration file
│
├── database/                   # Database related files
│   └── setup.sql               # SQL setup script
│
├── includes/                   # PHP includes
│   ├── database.php            # Database connection
│   ├── functions.php           # Helper functions
│   ├── header.php              # Header template
│   ├── footer.php              # Footer template
│   ├── session.php             # Session management
│   └── sidebar.php             # Sidebar navigation
│
├── modules/                    # Functional modules
│   ├── admin/                  # Admin module
│   ├── teacher/                # Teacher module
│   └── student/                # Student module
│
├── uploads/                    # Uploaded files
│
├── index.php                   # Main entry point
├── login.php                   # Login page
├── logout.php                  # Logout script
├── forgot-password.php         # Password recovery
├── reset-password.php          # Password reset
├── unauthorized.php            # Access denied page
└── README.md                   # This file
```

## Customization

### School Information

- Log in as admin
- Go to Settings
- Update school name, address, contact information, etc.

### Academic Year

- Log in as admin
- Go to Settings
- Set the current academic year

## Security Recommendations

1. **Change default credentials** immediately after installation
2. **Keep the system updated** with the latest security patches
3. **Use HTTPS** to encrypt data transmission
4. **Implement regular backups** of the database
5. **Limit access** to the server and database

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support, please open an issue in the GitHub repository or contact the development team at support@schoolmanagementsystem.com.

## Credits

- Bootstrap - https://getbootstrap.com/
- Font Awesome - https://fontawesome.com/
- Chart.js - https://www.chartjs.org/ 