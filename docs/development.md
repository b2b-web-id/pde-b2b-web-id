# Development Guide

This document outlines the recommended development workflow for the PDE-B2B-WEB-ID project, including setting up a local development environment, running tests, using debugging tools, and contributing code.

## Table of Contents

1. [Getting the Code](#getting-the-code)
2. [Setting up the Development Environment](#setting-up-the-development-environment)
3. [Running the Application](#running-the-application)
4. [Running Tests](#running-tests)
5. [Debugging and Profiling](#debugging-and-profiling)
6. **Code Style and Linting**
7. **Making Changes and Submitting Pull Requests**
8. **Working with Database Migrations**
9. **Updating Dependencies**
10. **Useful Commands Cheat‑Sheet**

---


## Getting the Code

Make sure you have forked the main repository (or cloned it if you already have push rights).

```bash
git clone https://github.com/<your‑username>/pde-b2b-web-id.git
cd pde-b2b-web-id
```

Initialize and update the submodule:

```bash
git submodule update --init --recursive
```

## Setting up the Development Environment

### Prerequisites

- **Git** – for version control.
- **Docker Engine** + **Docker Compose** – recommended for isolated services (MySQL, PHP‑Apache).
- **Composer** – PHP dependency manager (can be run inside the container).
- **Optional**: Node.js / npm (if you plan to work on frontend assets).

### Using Docker (recommended)

The provided `docker-compose.yml` defines two services:

- `db`: MySQL 5.7 (no host ports exposed)
- `php`: `yiisoftware/yii2-php:7.1-apache` with the application code mounted at `/app`

To start the environment:

```bash
docker compose up -d
```

This will:

- Pull the MySQL and PHP images (if not already present).
- Create a Docker network (`pde-b2b-web-id_default`) where both services can talk to each other using the service names `db` and `php`.
- Start the MySQL container with the database `pde`, user `pde`, password `pde`.
- Start the PHP-Apache container, mounting `./pde-idx-app` into `/app` inside the container.

### Verifying the containers

```bash
docker compose ps
```

You should see both services with a `State` of `Up`.

### Accessing the application

Open your browser to <http://localhost/> (port 80 is mapped from the PHP container). You should see the Yii2 welcome page.

### Installing PHP dependencies

Run Composer inside the PHP container (this ensures the correct PHP version and extensions):

```bash
docker compose exec php composer install --no-dev --ignore-platform-reqs
```

> The `--no-dev` flag skips packages required only for development/testing (e.g., PHPUnit, Codeception). If you want to run tests, omit `--no-dev` or add the dev dependencies later.

### Environment variables for development

You can enable debugging and detailed error messages by setting environment variables in the `php` service. For example, add to `docker-compose.yml` under the `php` service:

```yaml
environment:
  - YII_DEBUG=true
  - YII_ENV=dev
```

Or, if you prefer not to edit compose each time, you can override at runtime:

```bash
YII_DEBUG=true YII_ENV=dev docker compose up -d
```

Note: The default `pde-idx-app/web/index.php` already contains:

```php
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');
```

So you do not strictly need to set them unless you want to override.

## Running the Application

### Via Docker Compose (recommended)

```bash
# Start everything (if not already running)
docker compose up -d

# Then visit http://localhost/ in your browser.
```

### Using PHP's built‑in server (quick test)

```bash
cd pde-idx-app/web
php -S localhost:8080
```

Then browse to <http://localhost:8080>.

## Running Tests

The project uses **Codeception** for acceptance, functional, and unit tests (located in `pde-idx-app/tests`). To run the test suite, you need the dev dependencies installed.

### Install test dependencies (if you previously used `--no-dev`)

```bash
docker compose exec php composer install
```

### Run all tests

```bash
docker compose exec php vendor/bin/codecept run
```

### Run specific test suites

```bash
# Unit tests
docker compose exec php vendor/bin/codecept run unit

# Functional tests
docker compose exec php vendor/bin/codecept run functional

# Acceptance tests (requires a running web server)
docker compose exec php vendor/bin/codecept run acceptance
```

### Writing tests

- **Unit tests**: Place under `tests/unit`. Use PHPUnit assertions.
- **Functional tests**: Use `tests/_support/FunctionalTester.php` to interact with the application via Yii’s testing tools.
- **Acceptance tests**: Use the WebDriver or PhpBrowser module; ensure the web server is running (you can start it with `docker compose up -d php`).

Refer to the Codeception documentation for more details: <https://codeception.com/>

## Debugging and Profiling

### Enabling debug toolbar

Yii 2 ships with a debug module and toolbar. It is already enabled in `config/web.php` when `YII_DEBUG` is true.

To access the debugger:

1. Ensure `YII_DEBUG` is true (default in development).
2. Append `?debug` to any URL, e.g., `http://localhost/?debug`.
3. The debug toolbar will appear at the bottom of the page, providing:
   - Logger messages
   - Database queries
   - PHP version and extensions
   - Request/response details
   - Performance profiling

### Logging

The application uses Yii’s logging mechanism. Logs are written to `runtime/logs/app.log` by default.

You can view them inside the container:

```bash
docker compose exec php tail -f runtime/logs/app.log
```

Or, if you prefer to see them on the host, you can mount the `runtime/logs` directory as a volume (add to `docker-compose.yml` under the `php` service):

```yaml
volumes:
  - ./pde-idx-app/runtime/logs:/app/runtime/logs
```

### Xdebug (step debugging)

The base image `yiisoftware/yii2-php:7.1-apache` does **not** include Xdebug by default. To add it, you can create a custom Dockerfile based on that image:

```dockerfile
FROM yiisoftware/yii2-php:7.1-apache

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && { \
        echo "xdebug.mode=debug"; \
        echo "xdebug.start_with_request=yes"; \
        echo "xdebug.client_host=host.docker.internal"; \
        echo "xdebug.client_port=9003"; \
    } > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
```

Then rebuild the service:

```bash
docker compose build php
docker compose up -d
```

Configure your IDE (VS Code, PHPStorm, etc.) to listen for Xdebug connections on port 9003 and map the path `/app` in the container to your local `pde-idx-app` directory.

## Code Style and Linting

We follow **PSR‑12** coding style. To check and fix code style, we use **PHP_CS_FIXER**.

Install it as a dev dependency:

```bash
docker compose exec php composer require --dev friendsofphp/php-cs-fixer
```

### Run the linter

```bash
docker compose exec php vendor/bin/php-cs-fixer fix --diff --verbose
```

You can also add a pre‑commit hook (see `.githooks/pre-commit` if present) to run the formatter automatically.

### Static analysis

For static analysis we recommend **PHPStan** or **Psalm**. Install as needed:

```bash
docker compose exec php composer require --dev phpstan/phpstan
```

Run:

```bash
docker compose exec php vendor/bin/phpstan analyse -l max -c phpstan.neon src
```

## Making Changes and Submitting Pull Requests

1. **Create a feature branch** off `main` (or `dev` if the project uses a development branch):

   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes**, adhering to PSR‑12 and writing clear commit messages.

3. **Run tests** locally to ensure nothing is broken:

   ```bash
   docker compose exec php vendor/bin/codecept run
   ```

4. **Lint and fix**:

   ```bash
   docker compose exec php vendor/bin/php-cs-fixer fix
   ```

5. **Commit** your changes:

   ```bash
   git add .
   git commit -m "feat: add description of what the feature does"
   ```

6. **Push** to your fork and open a Pull Request (PR) against the `main` branch of the upstream repository.

7. In the PR description, include:
   - A short summary of the change.
   - Reasoning / motivation.
   - Any relevant issue numbers.
   - Screenshots or demo GIFs if UI changes.
   - Instructions for testing the change.

8. Respond to review comments and keep the branch up‑to‑date with `git pull --rebase upstream main` if needed.

## Working with Database Migrations

If you need to modify the database schema, create a new migration using Yii’s migration tool:

```bash
docker compose exec php php yii migrate/create create_post_table
```

This will generate a file under `pde-idx-app/migrations/mxxxxxx_xxxxxx_create_post_table.php`.

Edit the generated file to define the `up()` and `down()` methods. Then apply it:

```bash
docker compose exec php php yii migrate/up --interactive=0
```

To revert the last applied migration:

```bash
docker compose exec php php yii migrate/down --interactive=0 1
```

Never modify existing migration files after they have been committed; instead, create new migrations for further changes.

## Updating Dependencies

To update Composer packages to their latest permissible versions (according to `composer.json` constraints):

```bash
docker compose exec php composer update
```

After updating, run the test suite to ensure compatibility.

If you need to upgrade a specific package:

```bash
docker compose exec php composer require "vendor/package:^2.0" --update-with-dependencies
```

Commit the updated `composer.lock` file.

## Useful Commands Cheat‑Sheet

| Command | Purpose |
|---|---|
| `docker compose up -d` | Start all services in background |
| `docker compose ps` | List running containers |
| `docker compose logs -f php` | Follow logs of the PHP container |
| `docker compose exec php bash` | Open a shell inside the PHP container |
| `docker compose exec php composer install` (or `update`) | Manage PHP dependencies |
| `docker compose exec php php yii migrate/up` | Apply pending DB migrations |
| `docker compose exec php vendor/bin/codecept run` | Run the full test suite |
| `docker compose exec php vendor/bin/php-cs-fixer fix` | Auto‑fix code style |
| `docker compose exec php vendor/bin/phpstan analyse` | Run static analysis |
| `git submodule update --remote --merge` | Pull latest changes from the submodule repository |
| `docker compose down -v` | Stop containers and delete volumes (use with caution — deletes DB data) |

---


*This document is maintained alongside the codebase. If you find anything missing or outdated, please open an issue or submit a pull request to improve it.*