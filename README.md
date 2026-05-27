# AuthSystem — PHP + MySQL Authentication

A complete, production-ready authentication system with registration, login, and a dashboard.

---

## Features
- ✅ User Registration with client-side password strength meter
- ✅ Secure Login with CSRF token protection
- ✅ BCrypt password hashing (cost 12)
- ✅ Session-based authentication
- ✅ Remember Me cookie
- ✅ Activity logging (IP address + action)
- ✅ Flash messages (success / error)
- ✅ Dashboard with stats and activity timeline
- ✅ Logout with session destruction
- ✅ Dark mode, polished UI (no frameworks needed)

---

## Requirements
- PHP ≥ 8.0
- MySQL ≥ 5.7 / MariaDB ≥ 10.3
- Apache or Nginx with mod_rewrite

---

## Quick Start

### 1. Import the Database
```bash
mysql -u root -p < setup.sql
```
Or open `setup.sql` in phpMyAdmin and execute it.

### 2. Configure Database Credentials
Edit `includes/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // ← your MySQL user
define('DB_PASS', '');           // ← your MySQL password
define('DB_NAME', 'auth_system');
```

### 3. Place Files on Your Server
Copy the entire folder into your web root:
```
htdocs/auth_system/   (XAMPP)
www/auth_system/      (WAMP)
/var/www/html/auth_system/  (Linux Apache)
```

### 4. Visit in Browser
```
http://localhost/auth_system/register.php   → Create account
http://localhost/auth_system/login.php      → Sign in
http://localhost/auth_system/dashboard.php  → Dashboard (requires login)
```

---

## File Structure
```
auth_system/
├── includes/
│   ├── db.php        ← Database connection
│   └── auth.php      ← Session helpers, CSRF, sanitization
├── register.php      ← Registration form + handler
├── login.php         ← Login form + handler
├── dashboard.php     ← Protected dashboard
├── logout.php        ← Session destruction + redirect
├── setup.sql         ← Database + table creation
└── README.md
```

---

## Security Notes
- Passwords are hashed with `password_hash()` using BCRYPT (cost 12)
- All forms use CSRF tokens verified server-side
- User input is sanitized with `htmlspecialchars` + `strip_tags`
- Prepared statements prevent SQL injection
- `session_destroy()` and cookie clearing on logout
- For production: enable HTTPS, set `secure` flag on cookies, tune session lifetime
