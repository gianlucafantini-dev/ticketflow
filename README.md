# TicketFlow 🎫

Simple and efficient helpdesk ticketing system built with PHP and MySQL.


---

## 🎯 About

TicketFlow is a lightweight helpdesk solution inspired by 7+ years of technical support experience. Built to learn PHP fundamentals before moving to frameworks like Laravel.

**Why This Project?**
- Learn by building a real system
- Master PHP/MySQL fundamentals
- Demonstrate problem-solving skills
- Career transition: Technical Support → PHP Developer

---

## Features

### User Features
- ✅ Secure authentication (bcrypt password hashing)
- ✅ Create and manage support tickets
- ✅ Priority levels (Low, Medium, High, Urgent)
- ✅ Status tracking (New, In Progress, Resolved, Closed)
- ✅ Comments system with real-time updates
- ✅ Personal dashboard with filtering
- ✅ Responsive design (mobile, tablet, desktop)

### Admin Features
- Admin dashboard with system-wide ticket view
- Ticket assignment to agents/admins
- Priority and status management
- User management (create, edit, delete, role changes)
- Real-time statistics (total, open, closed, unassigned, urgent)
- Role-based access control (User, Agent, Admin)

### Security
- SQL injection protection (prepared statements)
- Password security (bcrypt hashing)
- XSS protection (htmlspecialchars on all output)
- Secure session management

---

## Tech Stack

- **Backend:** PHP 8.3 (pure PHP, no framework)
- **Database:** MySQL 5.7+ / MariaDB 10.3+
- **Frontend:** HTML5, CSS3, Bootstrap 5
- **JavaScript:** Vanilla JS
- **Security:** MySQLi prepared statements, bcrypt

**Why No Framework?**  
Built without Laravel/Symfony to deeply understand PHP fundamentals and how frameworks work internally.

---

## Installation

### 1. Clone Repository
```bash
git clone https://github.com/gianlucafantini-dev/ticketflow.git
cd ticketflow
```

### 2. Configure Database
```bash
cp config/database.example.php config/conf_db.php
```

Edit `config/conf_db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'ticketflow');
```

### 3. Import Database
```bash
mysql -u root -p ticketflow < database/schema.sql
```

Or via phpMyAdmin: Import → `database/schema.sql`

### 4. Access Application
```
http://localhost/ticketflow
```

### 5. Create Admin User
Register normally, then promote via database:
```sql
UPDATE users SET role = 'admin' WHERE email = 'your@email.com';
```

---

## 📁 Project Structure
```
ticketflow/
├── index.php              # Homepage
├── config/
│   ├── conf_db.php       # Database config (not in Git)
│   └── database.example.php  # Public template
├── includes/
│   └── auth_check.php    # Authentication middleware
├── auth/                  # Login, register, logout
├── tickets/               # Ticket CRUD operations
├── admin/                 # Admin dashboard & management
└── database/
    └── schema.sql        # Database schema
```

---

##  User Roles

- **User:** Create tickets, view own tickets, add comments
- **Agent:** All User permissions + view ALL tickets, assign tickets
- **Admin:** All Agent permissions + user management, full access

---

##  Why This Project?

After 7 years in Technical Support using Jira, FreshDesk, and Zendesk, I wanted to:
1. Understand ticketing systems from the inside
2. Build only essential features (no bloat)
3. Master PHP fundamentals before frameworks
4. Create portfolio demonstrating real-world skills

---

##  What I Learned

- PHP OOP principles and best practices
- Database design (relationships, foreign keys, indexes)
- Security (SQL injection, XSS, password hashing)
- Authentication and session management
- MVC architecture concepts
- Responsive UI/UX with Bootstrap

---

## Future Enhancements

Planned for v2.0:
- File attachments for tickets
- Email notifications
- Advanced search and filtering
- Analytics dashboard with charts
- REST API for mobile integration

---

## Author

**Gian Luca Fantini**

- Technical Support Engineer → PHP Developer
- Biella, Italy
- LinkedIn](https://linkedin.com/in/gian-lucafantini)
- [GitHub](https://github.com/gianlucafantini-dev)

---

## 📝 License

MIT License - Free for learning and portfolio use.

---


*This project represents my journey from Technical Support to PHP Developer, combining operational experience with technical skills.*
