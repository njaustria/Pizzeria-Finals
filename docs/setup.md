# Pizzeria Project Setup Guide

This guide will help you set up and run the Pizzeria web application on your local machine using XAMPP.

## Prerequisites
- [XAMPP](https://www.apachefriends.org/index.html) installed (includes Apache, PHP, and MySQL)
- Basic knowledge of PHP and MySQL

## 1. Clone or Copy the Project
- Place the `pizzeria` folder inside your XAMPP `htdocs` directory (e.g., `C:/xampp/htdocs/pizzeria`).

## 2. Start XAMPP Services
- Open XAMPP Control Panel.
- Start **Apache** and **MySQL** modules.

## 3. Create the Database
1. Open your browser and go to [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
2. Create a new database (e.g., `pizzeria_db`).
3. Import the database:
   - Click the new database.
   - Go to the **Import** tab.
   - Import `database/setup.sql` for sample data.

## 4. Configure Database Connection
- Open `config/database.php`.
- Update the database name, username, and password if needed:
  ```php
  $dbHost = 'localhost';
  $dbName = 'pizzeria_db';
  $dbUser = 'root';
  $dbPass = '';
  ```

## 5. Configure Other Settings (Optional)
- Check `config/config.php` for site-wide settings.
- For chatbot or SMS features, update `config/chatbot_config.php` and `config/sms_config.php` as needed.

## 6. Access the Application
- In your browser, go to [http://localhost/pizzeria](http://localhost/pizzeria)
- Admin panel: [http://localhost/pizzeria/admin/login.php](http://localhost/pizzeria/admin/login.php)

## 7. Logs
- Application logs are in the `logs/` folder (e.g., `chatbot_log.txt`, `email_log.txt`).

## 8. Assets
- Place pizza images in `assets/images/pizzas/`.
- Update `assets/css/style.css` for custom styles.

## 9. Troubleshooting
- Ensure Apache and MySQL are running.
- Check file permissions if you encounter errors.
- Review logs in the `logs/` folder for debugging.

---

For further help, see the `docs/` folder or contact the project maintainer.
