# 🐝 CampusHive - University Management System

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

CampusHive is a comprehensive, all-in-one University Management System designed to streamline operations across all university departments. Built like a beehive — organized, smart, and buzzing with features — it connects students, faculty, staff, and administrators under one unified platform.

![CampusHive Dashboard]<img width="1920" height="971" alt="Screenshot 2026-02-22 190638" src="https://github.com/user-attachments/assets/cd2ca1ab-3a88-4c4f-a6b4-c90f54205f00" />
<img width="1920" height="978" alt="Screenshot 2026-02-22 190737" src="https://github.com/user-attachments/assets/5e3550b9-ab7c-4501-8f9f-619e75e8c1a9" />
<img width="1920" height="952" alt="Screenshot 2026-02-22 190800" src="https://github.com/user-attachments/assets/e34e43bc-1550-4ef8-b31f-5e6e25ea3e71" />


## 🌟 Overview

CampusHive serves as a complete ERP solution for universities, providing role-based dashboards for different user types:

- **Students** - Access academics, finances, campus services, and submit requests
- **Faculty** - Manage courses, grade students, upload resources, track advisees
- **Administrators** - Oversee users, courses, departments, and system settings
- **HR Staff** - Manage employee profiles, recruitment, attendance, and payroll
- **Finance Staff** - Handle transactions, fee structures, and financial reports
- **Campus Staff** - Manage hostel, transport, library, medical, and canteen services

## ✨ Key Features

### 🎓 Student Portal
- View academic records, grades, and enrolled courses
- Access financial information and make payments
- Request campus services (hostel, transport, library, medical, canteen)
- Download study materials and resources
- Track personal profile and progress

### 👨‍🏫 Faculty Dashboard
- Manage assigned courses and class schedules
- Grade student assignments and submit final grades
- View and advise assigned students
- Upload course materials and resources
- Track attendance and performance

### 👑 Admin Panel
- Complete user management across all roles
- Course and department management
- System settings and configuration
- Generate reports and view analytics
- Monitor system activity and logs
- Database backup and restore functionality

### 👥 HR Module
- Staff profile management (faculty, HR, finance, campus staff)
- Recruitment management with job postings and applications
- Attendance tracking with check-in/check-out
- Payroll processing and history
- Performance review scheduling and management
- Bulk data upload via CSV

### 💰 Finance Module
- Financial transaction management
- Fee structure configuration
- Invoice generation and payment recording
- Financial aid tracking (scholarships, aid)
- Revenue reports and analytics
- Student balance tracking

### 🏢 Campus Services
- **Hostel Management**: Room allocation, requests, maintenance
- **Transport Management**: Vehicle tracking, route allocation, requests
- **Library Management**: Book catalog, loans, returns, requests
- **Medical Center**: Appointments, staff, inventory, medicine requests
- **Canteen Management**: Menu items, orders, staff management

## 🛠️ Tech Stack

### Backend
- **PHP** (>=7.4) - Core application logic
- **MySQL** - Database management
- **PDO** - Secure database connections with prepared statements

### Frontend
- **HTML5** - Structure
- **CSS3** - Styling with custom properties and responsive design
- **JavaScript** - Interactive features, modals, and AJAX calls
- **Font Awesome** - Icons for better UI

### Security Features
- Password hashing with bcrypt
- Session-based authentication
- Role-based access control
- Input sanitization and validation
- Prepared statements for SQL injection prevention
- Secure file upload handling

## 📁 Project Structure

university-system/

├── assets/ # CSS and JS files

│ ├── css/ # Stylesheets for each role

│ │ ├── admin.css

│ │ ├── campus.css

│ │ ├── faculty.css

│ │ ├── finance.css

│ │ ├── hr.css

│ │ ├── student.css

│ │ └── style.css

│ └── js/ # JavaScript files

│ ├── admin.js

│ ├── auth.js

│ ├── campus.js

│ ├── faculty.js

│ ├── finance.js

│ ├── hr.js

│ ├── main.js

│ └── student.js

├── php/ # PHP backend files

│ ├── admin/ # Administrator modules

│ │ ├── backup.php

│ │ ├── courses.php

│ │ ├── dashboard.php

│ │ ├── departments.php

│ │ ├── logs.php

│ │ ├── reports.php

│ │ ├── settings.php

│ │ └── users.php

│ ├── auth/ # Authentication

│ │ ├── login.php

│ │ ├── logout.php

│ │ └── register.php

│ ├── campus/ # Campus services modules

│ │ ├── canteen_management.php

│ │ ├── dashboard.php

│ │ ├── hostel_management.php

│ │ ├── library_management.php

│ │ ├── medical_management.php

│ │ └── transport_management.php

│ ├── faculty/ # Faculty modules

│ │ ├── courses.php

│ │ ├── dashboard.php

│ │ ├── grading.php

│ │ ├── resources.php

│ │ └── students.php

│ ├── finance/ # Finance modules

│ │ ├── dashboard.php

│ │ ├── fees.php

│ │ ├── reports.php

│ │ ├── settings.php

│ │ └── transactions.php

│ ├── hr/ # HR modules

│ │ ├── admin_utilities.php

│ │ ├── attendance_payroll.php

│ │ ├── dashboard.php

│ │ ├── employee_profiles.php

│ │ ├── performance.php

│ │ └── recruitment.php

│ ├── public/ # Public facing pages

│ │ ├── dashboard.php

│ │ ├── hostel.php

│ │ ├── library.php

│ │ ├── medical.php

│ │ ├── recruitment.php

│ │ └── transport.php

│ ├── student/ # Student modules

│ │ ├── academics.php

│ │ ├── dashboard.php

│ │ ├── finances.php

│ │ ├── requests.php

│ │ └── resources.php

│ ├── db_connect.php # Database connection

│ ├── financial_functions.php # Finance helper functions

│ ├── process_payment.php # Payment processing

│ ├── secure_session.php # Session security

│ └── update_attendance.php # Attendance tracking

├── uploads/ # File uploads directory

│ ├── assignments/ # Student assignments

│ └── resumes/ # Job application resumes

├── index.html # Landing page

├── login.html # Login page

├── register.html # Registration page

├── schema.sql # Database schema

└── README.md # This file


## 🗄️ Database Schema

The database consists of 40+ tables organized by module:

### Core Tables
- `users` - User authentication and basic info
- `activity_log` - System activity tracking
- `system_settings` - Configuration settings

### Academic Tables
- `students` - Student-specific information
- `faculty` - Faculty-specific information
- `courses` - Course catalog
- `classes` - Class sessions
- `enrollment` - Student enrollment records
- `assignments` - Course assignments
- `assignment_submissions` - Student submissions
- `attendance` - Class attendance
- `resources` - Course materials

### Finance Tables
- `financial_transactions` - All financial records
- `fee_structure` - Fee configuration
- `payment_methods` - Student payment methods
- `finance_staff` - Finance department staff

### HR Tables
- `hr_staff` - HR department staff
- `recruitment` - Job postings
- `job_applications` - Applicant records
- `performance_reviews` - Staff performance
- `payroll` - Salary records
- `leave_requests` - Staff leave
- `staff_attendance` - Staff attendance tracking

### Campus Services Tables
- `hostel_rooms` - Room inventory
- `hostel_allocations` - Room assignments
- `hostel_requests` - Room change requests
- `transport_vehicles` - Vehicle inventory
- `transport_routes` - Route management
- `transport_allocations` - Student allocations
- `transport_requests` - Transport requests
- `library_books` - Book catalog
- `library_loans` - Book loans
- `book_requests` - Book requests
- `medical_staff` - Medical center staff
- `medical_appointments` - Patient appointments
- `medical_inventory` - Medicine inventory
- `medicine_requests` - Medicine requests
- `canteen_menu` - Food menu items
- `canteen_orders` - Food orders
- `canteen_staff` - Canteen staff
- `campus_activities` - Activity logs

## 🚀 Installation & Setup

### Prerequisites
- Web server (Apache/Nginx) or XAMPP/WAMP/MAMP
- PHP >= 7.4
- MySQL
- Git

### Step 1: Clone the Repository
git clone https://github.com/Varsha-012005/campushive.git
cd campushive

Step 2: Set Up the Database
Create a MySQL database named university_management

Import the schema:


mysql -u root -p university_management < schema.sql
Step 3: Configure Database Connection
Edit php/db_connect.php with your database credentials:

$host = 'localhost';
$dbname = 'university_management';
$username = 'root';
$password = '';
Step 4: Configure Base URL
Edit php/config.php to match your local setup:


define('BASE_URL', 'http://localhost/university-system/');
Step 5: Set Up File Upload Directories
Ensure the uploads/ directory and its subdirectories have write permissions:

chmod -R 755 uploads/
Step 6: Run the Application
Start your web server and MySQL

Navigate to http://localhost/university-system

Register a new account or use default credentials

Default User Roles
Role	Username	Password
Admin	admin	password
Faculty	faculty	password
Student	student	password
HR	hr	password
Finance	finance	password
Campus	campus	password

Sample Features by Role
Administrator Dashboard
System statistics overview

User management across all roles

Course and department management

System settings configuration

Activity log monitoring

Database backup and restore

Student Portal
View enrolled courses and grades

Check financial balance and make payments

Request hostel room allocation

Book library books

Schedule medical appointments

Order from canteen

Apply for transport routes

Faculty Dashboard
View assigned courses

Grade student assignments

Track attendance

Upload course materials

View advisee information

HR Module
Manage employee profiles

Post job openings

Process job applications

Track staff attendance

Run payroll

Schedule performance reviews

Finance Module
Process student payments

Manage fee structures

Generate invoices

Track financial aid

Create revenue reports

View transaction history

Campus Services
Manage hostel rooms and allocations

Configure transport routes and vehicles

Maintain library catalog

Schedule medical appointments

Manage canteen menu and orders

 Future Enhancements
Mobile App - React Native or Flutter version

Email Notifications - Automated alerts and reminders

Real-time Chat - Communication between users

Analytics Dashboard - Advanced data visualization

Payment Gateway Integration - Online fee payment

QR Code Attendance - Scan-based attendance tracking

Push Notifications - Browser push alerts

Multi-language Support - Internationalization

API Development - RESTful API for integrations

License
This project is licensed under the MIT License - see the LICENSE file for details.
