# Bagisto Local Development Setup Guide

This guide explains how to set up Bagisto for local development on **Windows** (or similar environments) — from installing dependencies to running the application.

---

## 1. Prerequisites

Install the following software:

- **[XAMPP](https://www.apachefriends.org/index.html)** (PHP 8.1+ and MySQL)
- **[Composer](https://getcomposer.org/download/)**
- **[Node.js & npm](https://nodejs.org/en/download/)** (for frontend build)
- **[Git](https://git-scm.com/downloads)**
- (Optional) **[VS Code](https://code.visualstudio.com/)** or your preferred editor

### Configure PHP Extensions
Enable the following in `C:\xampp\php\php.ini` by removing `;` at the beginning:
```
extension=intl
extension=zip
extension=fileinfo
```
Restart Apache from the XAMPP Control Panel then Verify:
```
php -m
```
---

## 2. Clone the Repository

```bash
git clone https://github.com/altafjava/bagisto.git
cd bagisto
```

---

## 3. Install Dependencies

```bash
# PHP dependencies
composer install

# Node.js dependencies
npm install
```

---

## 4. Configure Environment

Copy `.env.example` to `.env`:

```bash
copy .env.example .env
```

Edit `.env` and update:

```env
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bagisto
DB_USERNAME=bagisto_user
DB_PASSWORD=bagisto_password
DB_PREFIX=
```

*(Replace credentials if using a custom MySQL user/password.)*

---

## 5. Create the Database

```bash
mysql -u root -p
```

```sql
CREATE DATABASE bagisto;

-- (Optional: create a dedicated user)
CREATE USER 'bagisto_user@localhost' IDENTIFIED BY 'bagisto_password';
GRANT ALL PRIVILEGES ON bagisto.* TO 'bagisto_user@localhost';
FLUSH PRIVILEGES;
EXIT;
```

If you created a user, update `.env` accordingly.

---

## 6. Generate App Key & Link Storage

```bash
php artisan key:generate
# php artisan storage:link
```

---

## 7. Install Bagisto

Run the built-in installer:

```bash
php artisan bagisto:install
```

It will:
- Run migrations
- Seed initial data
- Prompt for admin credentials (default: `admin@example.com` / `admin123`)

---

## 8. Build Frontend Assets

```bash
# npm run dev
# or for production:
# npm run build
```

---

## 9. Run the Application

```bash
php artisan serve
```

Visit: **[http://localhost:8000](http://localhost:8000)**

---

## 10. Access the Admin Panel

**Admin URL:** [http://localhost:8000/admin](http://localhost:8000/admin)  
**Default credentials:**  
```
Email: admin@example.com
Password: admin123
```