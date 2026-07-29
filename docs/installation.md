# Installation Guide

This guide walks you through setting up the PDE‑B2B‑WEB‑ID project for the first time, whether you plan to run it locally with Docker or on a traditional LAMP stack.

## Prerequisites

| Tool | Minimum version | Why it’s needed |
|------|----------------|-----------------|
| Git | 2.20+ | To clone the repository and manage submodules |
| Docker Engine | 20.10+ | Provides isolated MySQL and PHP‑Apache services (recommended) |
| Docker Compose | v2+ | Orchestrates the multi‑container setup |
| PHP | 7.1+ | Required if you run PHP directly (optional with Docker) |
| Composer | 2.0+ | PHP dependency manager |
| MySQL client | 5.7+ | Useful for manual DB inspection (optional) |

> **Tip:** If you prefer not to install Docker, you can install PHP, Composer, and MySQL locally and follow the “Manual installation” section at the end of this document.

## Step‑by‑Step Installation (Docker‑based)

### 1. Clone the repository

```bash
git clone https://github.com/your-username/pde-b2b-web-id.git
cd pde-b2b-web-id
```

### 2. Initialise and update the Git submodule

The project relies on a submodule (`pde-idx-app`) that holds the Yii2 application.

```bash
git submodule update --init --recursive
```

Verify that the submodule directory is populated:

```bash
ls -l pde-idx-app
```

You should see the Yii2 basic application structure (folders like `commands`, `config`, `controllers`, `models`, `web`, etc.).

### 3. Start the Docker environment

```bash
docker compose up -d
```

This command:

- Pulls the `mysql:5.7` and `yiisoftware/yii2-php:7.1-apache` images (if not cached).
- Creates a dedicated Docker network (`pde-b2b-web-id_default`) so the containers can talk to each other via the service names `db` and `php`.
- Starts a MySQL container with:
  - Database: `pde`
  - User: `pde`
  - Password: `pde`
  - Root password: `root`
- Starts a PHP‑Apache container, mounting the host directory `./pde-idx-app` into `/app` inside the container.
- Exposes the PHP container’s port 80 to the host’s port 80 (so you can reach the app at `http://localhost/`).

### 4. Verify the containers are running

```bash
docker compose ps
```

Expected output (truncated):

```
NAME                     COMMAND                  STATE          PORTS
pde-b2b-web-id-db-1      docker-entrypoint.sh mysqld   Up      0.0.0.0:3306->3306/tcp, 33060/tcp
pde-b2b-web-id-php-1     docker-php-entrypoint apacherestart   Up      0.0.0.0:80->80/tcp
```

### 5. Install PHP dependencies

Run Composer **inside** the PHP container to guarantee the correct PHP version and extensions:

```bash
docker compose exec php composer install --no-dev --ignore-platform-reqs
```

- `--no-dev` skips packages required only for development/testing (e.g., PHPUnit, Codeception). If you plan to run tests, omit this flag or add the dev dependencies later.
- `--ignore-platform-reqs` silences warnings about missing PHP extensions that are provided by the Docker image (e.g., the `pq.so` warning you might see).

### 6. Initialise the database (optional)

The provided `docker-compose.yml` does **not** expose MySQL ports to the host, but the MySQL container is already initialized with the database and user defined in the `mysql/init-db.sql` script. If you need to reset or inspect the database, you can exec into the MySQL container:

```bash
docker compose exec db mysql -upde -ppde pde
```

You should see the `mysql>` prompt. Run `SHOW TABLES;` to verify tables exist (they will be created later by Yii migrations if any).

### 7. Run Yii migrations (if applicable)

If the project includes database migrations, apply them now:

```bash
docker compose exec php php yii migrate/up --interactive=0
```

You should see output like:

```
Yii Migration Tool (based on Yii v2.0.40)

Total 1 new migration to be applied:
    m230801_120000_create_users_table

Apply the above migration? (yes|no) [no]:yes
*** applying m230801_120000_create_users_table (time: 0.015s)
```

### 8. Access the application

Open a web browser and navigate to:

```
http://localhost/
```

You should see the Yii2 welcome page (or the custom landing page if the project has overridden the default view).

## Manual Installation (without Docker)

If you prefer to run the stack locally, follow these steps:

### 1. Install MySQL

- On Ubuntu/Debian: `sudo apt-get install mysql-server`
- On macOS (with Homebrew): `brew install mysql`
- Start the service and secure it (`mysql_secure_installation`).

### 2. Create the database and user

```sql
CREATE DATABASE pde CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pde'@'localhost' IDENTIFIED BY 'pde';
GRANT ALL PRIVILEGES ON pde.* TO 'pde'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Import the initial schema (optional)

If you have an SQL dump (e.g., `mysql/init-db.sql`), load it:

```bash
mysql -upde -ppde pde < mysql/init-db.sql
```

### 4. Install PHP dependencies

```bash
composer install --no-dev --ignore-platform-reqs
```

### 5. Configure the database connection

Edit `pde-idx-app/config/db.php` to match your local MySQL credentials:

```php
return [
    'class' => yii\db\Connection::class,
    'dsn' => 'mysql:host=localhost;dbname=pde;charset=utf8',
    'username' => 'pde',
    'password' => 'pde',
    'charset' => 'utf8',
];
```

### 6. Run migrations

```bash
php yii migrate/up --interactive=0
```

### 7. Serve the application

Using PHP’s built‑in server (for quick testing):

```bash
cd pde-idx-app/web
php -S localhost:8000
```

Then visit `http://localhost:8000`.

Or configure a virtual host in Apache/Nginx pointing to the `web` directory.

## Post‑installation Checklist

- [ ] Containers are up (`docker compose ps` shows both services as `Up`).
- [ ] You can reach the application at `http://localhost/`.
- [ ] The debugger toolbar appears when you add `?debug` to any URL (if `YII_DEBUG` is true).
- [ ] You can run `docker compose exec php php yii migrate/status` and see no pending migrations.
- [ ] You are able to run the test suite: `docker compose exec php vendor/bin/codecept run`.

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| `docker compose up` fails with “port is already allocated” | Another service (e.g., local MySQL) is using port 3306. | Either stop the conflicting service or change the host port in `docker-compose.yml` (e.g., `"3307:3306"`). |
| Application shows a blank page or 500 error | Missing PHP extensions or permissions on `runtime/` and `web/assets`. | Ensure the `runtime` and `web/assets` directories are writable by the web server user (inside the container, user `www-data`). You can fix permissions on the host: `chmod -R 775 pde-idx-app/runtime pde-idx-app/web/assets && chown -R $USER:$USER pde-idx-app/runtime pde-idx-app/web/assets`. |
| Composer reports “Could not open input file: composer” | You are running `composer` from the host but the `composer` executable lives inside the container. | Use `docker compose exec php composer …` as shown. |
| Database connection errors (`SQLSTATE[HY000] [1045] Access denied`) | Wrong credentials in `db.php` or the MySQL container not ready yet. | Wait a few seconds for MySQL to finish initializing (check `docker compose logs db`). Verify the credentials match those in the Docker environment (`pde`/`pde`). |
| “No such file or directory” for `/app/vendor/autoload.php` | Vendor directory missing or not installed. | Run `docker compose exec php composer install`. |

## Next Steps

- Read **development.md** for tips on day‑to‑day coding, testing, and debugging.
- Review **architecture.md** to understand the high‑level components of the system.
- Consult **deployment.md** for guidance on moving from a development setup to a production environment.
- Refer to **database.md** for deeper details on schema, backups, and scaling.

---


*This document is part of the project documentation. Keep it up‑to‑date when the installation process changes.*