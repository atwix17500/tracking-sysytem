# NSSF Contribution Tracking System

## Project Overview

The NSSF Contribution Tracking System is a web-based application developed to help track employees' monthly NSSF contributions efficiently. The system provides an easy way to store, manage, and retrieve contribution records while reducing paperwork and improving accuracy.

This project was developed as a simple database application using PHP, MySQL, HTML, CSS, and JavaScript.

---

## Features

- User login and authentication
- Add new employee records
- Record monthly NSSF contributions
- View employee contribution history
- Search employees
- Update employee information
- Delete employee records
- Store contribution data securely in a MySQL database

---

## Technologies Used

- PHP
- MySQL
- HTML5
- CSS3
- JavaScript
- XAMPP (Apache and MySQL)

---

## System Requirements

Before running the project, ensure you have:

- XAMPP installed
- PHP 8.x or later
- MySQL
- A modern web browser (Chrome, Edge, Firefox)

---

## Installation

### 1. Install XAMPP

Download and install XAMPP from:

https://www.apachefriends.org/

### 2. Copy the Project

Copy the project folder into the XAMPP `htdocs` directory.

Example:

```
C:\xampp\htdocs\NSSFContributionSystem
```

### 3. Start XAMPP

Open the XAMPP Control Panel and start:

- Apache
- MySQL

### 4. Create the Database

Open phpMyAdmin:

```
http://localhost/phpmyadmin
```

Create a database named:

```
nssf_contribution_system
```

### 5. Import the Database

Import the provided SQL file into the database.

Example:

```
database.sql
```

### 6. Run the Application

Open your browser and visit:

```
http://localhost/NSSFContributionSystem
```

---

## Folder Structure

```
NSSFContributionSystem/
│
├── css/
├── js/
├── images/
├── includes/
├── database/
│   └── database.sql
├── index.php
├── login.php
├── dashboard.php
├── employees.php
├── contributions.php
└── README.md
```

---

## Future Improvements

- Email notifications
- PDF report generation
- Contribution analytics
- Mobile responsiveness
- Role-based access control
- Automatic contribution calculations

---

## Author

**Name:** Atwijukire Anna Prudence

Bachelor of Science in Computer Science

Makerere University

---

## License

This project was developed for educational purposes and may be modified for learning and research.
