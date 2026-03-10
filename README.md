# Secure Web Application

A secure PHP web application implementing authentication, account protection, and multiple web security mechanisms.

This project was developed as part of a **Computer Security module** and demonstrates the implementation of common **web security protections**, including authentication, input validation, attack mitigation, and secure file handling.

---

## Features

The application includes a full user authentication system with the following functionality:

- User registration
- Secure login and logout
- Email verification
- Two-factor authentication (2FA)
- Password reset via email
- Security questions
- Account lockout after repeated failed login attempts
- User dashboard
- Request submission and management
- Admin management interface
- Secure file uploads

---

## Security Mechanisms

The system implements several important security protections:

- **Password hashing** using bcrypt
- **CSRF protection** for form submissions
- **SQL injection prevention** using prepared statements
- **XSS mitigation** via output escaping
- **CAPTCHA protection** against automated attacks
- **Account lockout** after multiple failed login attempts
- **Email verification tokens**
- **Two-factor authentication (2FA)**
- **Secure password reset tokens with expiration**
- **File upload validation** including MIME type checks and extension filtering
- **Role-based access control for admin pages**

---

## Technologies

- PHP
- MySQL
- Composer
- HTML / CSS
- JavaScript

---

## Project Structure

```
secure-web-application
│
├── src                 # Main PHP application
│   ├── admin
│   ├── includes
│   ├── uploads
│   ├── login.php
│   ├── register.php
│   ├── dashboard.php
│
├── database            # Database schema
├── report              # Security report
│
├── composer.json
└── README.md
```

---

## Requirements

- PHP 8+
- MySQL / MariaDB
- Composer

---

## Setup

Clone the repository:

git clone https://github.com/Behram484/secure-web-application.git


Install dependencies:
composer install


Configure database credentials in the configuration file.

Import the SQL schema located in the **database/** directory.

Run the application using a local PHP server or deploy using Apache/Nginx.

---

## Report

The full project report explaining the security design and implementation is available in:
report/Computer security report.pdf

---

## Future Improvements

Possible future improvements include:

- Rate limiting for login attempts
- SMS-based two-factor authentication
- Security logging and monitoring
- Containerized deployment (Docker)
