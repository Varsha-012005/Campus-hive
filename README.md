<<<<<<< HEAD
# School Management System Setup

1. Import `schema.sql` into MySQL.
2. Copy `.env.example` to `.env` and update credentials.
3. Set `BASE_URL` in `config.php` to match your localhost path.
4. Ensure `uploads/` folder has write permissions.
=======
# Campus-hive
CampusHive - University Management System
CampusHive is a comprehensive web-based university management system designed for students, faculty, administrators, HR, finance, and campus staff. It features role-based dashboards, campus services integration, and modern UI with dark/light themes.

Key Features
Role-based access: Student, Faculty, Admin, HR, Finance, Campus Staff
Dashboards for academics, attendance, fees, grading, reports
Campus services: Canteen, Hostel, Library, Medical, Transport
Secure authentication with login/register forms
Responsive design with Bootstrap, Font Awesome, Poppins font
Dark/light theme toggle and animated bee cursor
File uploads for assignments/resumes

Project Structure
.
├── index.html (Landing page)
├── login.html & register.html
├── assets/
│   ├── css/ (Admin, faculty, student styles)
│   └── js/ (Role-specific scripts)
├── php/
│   ├── admin/, auth/, campus/, faculty/, finance/, hr/, public/, student/
│   ├── config.php, db_connect.php, setup.php
│   └── securesession.php
├── schema.sql (Database schema)
├── uploads/ (Assignments, resumes)
└── file_structure.txt

Tech Stack
Frontend: HTML5, Bootstrap 5, CSS3, JavaScript
Backend: PHP, MySQL
Styling: Custom CSS with gradients, animations
Security: Secure sessions, form validation


Quick Setup
Import schema.sql into MySQL database.
​
Update config.php with database credentials (host, user, pass, dbname).
​
Ensure uploads/ folder has write permissions (chmod 755 or 777).

Run setup.php if needed for initial config.
​
Access via index.html or set BASEURL in config.php.

Create accounts via register.html (roles: student, faculty, admin, etc.).


Database Setup
Uses MySQL/MariaDB
Run schema.sql to create tables for users, courses, attendance, fees, etc.
Key tables include users, students, faculty, departments, transactions.

Role Dashboards
Role	Key Pages/Modules
Admin::	Backup, courses, departments, reports​
Student: Academics, finances, resources​
Faculty:	Courses, grading, students​
Finance:	Fees, transactions, reports​
HR:	Attendance, payroll, recruitment​
Campus:	Canteen, hostel, library, medical
​
Security Notes
Uses securesession.php for session management
Password hashing (assumed in auth PHP)
Role-based access control
CSRF protection recommended (add if missing)
​

Deployment
Apache/Nginx with PHP 7.4+ and MySQL 5.7+
Enable mod_rewrite for clean URLs (optional)
Set document root to project folder
For production: Use HTTPS, limit file uploads

Contributing
Fork the repo, create a feature branch, submit PR. Focus on security fixes, new modules, or UI improvements.

License
MIT License - Feel free to use and modify.
