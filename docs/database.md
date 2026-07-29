# Database Guide

This document provides detailed information about the database setup, schema management, backup strategies, and performance tuning for the PDE‑B2B‑WEB‑ID project.

## Overview

The application uses a single MySQL database named **`pde`** to store all persistent data. The schema consists of:

- Core Yii 2 tables (e.g., `user`, `migration`) if the default user module is used.
- Application‑specific tables for storing IDX (Indonesia Stock Exchange) data such as company listings, historical price data, financial statements, news, etc.
- Auxiliary tables for caching, logging, and session handling (depending on the configured components).

The database can be run either via Docker Compose (recommended for development) or on a dedicated MySQL server (production).

## 1. Database Connection Configuration

The connection parameters are defined in `pde-idx-app/config/db.php`. A typical configuration for the Docker‑based setup looks like:

```php
return [
    'class' => yii\db\Connection::class,
    'dsn' => 'mysql:host=db;dbname=pde;charset=utf8',
    'username' => 'pde',
    'password' => 'pde',
    'charset' => 'utf8',

    // Optional: enable schema caching for production
    // 'enableSchemaCache' => true,
    // 'schemaCacheDuration' => 3600,
    // 'schemaCache' => 'cache',
];
```

### Connection options explained

| Option | Description |
|--------|-------------|
| `dsn` | Data Source Name. `host` points to the MySQL service name (`db`) when using Docker; replace with `127.0.0.1` or a hostname/IP for external databases. |
| `username` / `password` | Credentials for the MySQL user that the application will use. |
| `charset` | Character set for the connection; `utf8` ensures proper Unicode handling. |
| `enableSchemaCache` | When set to `true`, Yii caches the table schema, reducing the number of `DESCRIBE` queries. Recommended for production. |
| `schemaCache` | The cache component to use for schema caching (must be defined in the application config). |
| `schemaCacheDuration` | How long (in seconds) the schema cache is valid. |

## 2. Initialising the Database

### 2.1 Using Docker Compose (recommended)

When you start the `db` service with `docker compose up -d db`, the official MySQL 5.7 image automatically:

1. Creates the database specified by `MYSQL_DATABASE` (`pde`).
2. Creates a user with the name given by `MYSQL_USER` (`pde`) and the password from `MYSQL_PASSWORD` (`pde`).
3. Grants that user all privileges on the database.
4. Executes any `*.sql` or `*.sh` files found in `/docker-entrypoint-initdb.d/` inside the container.

Our repository provides `mysql/init-db.sql` which is **not** automatically copied into the container by the default compose file. To have it executed on container start, you can either:

- **Bind‑mount the SQL file** into the init directory:

  Add to the `db` service in `docker-compose.yml`:

  ```yaml
  volumes:
    - db_data:/var/lib/mysql
    - ./mysql/init-db.sql:/docker-entrypoint-initdb.d/init-db.sql:ro
  ```

  Then recreate the container (`docker compose up -d --force-recreate db`).

- **Or manually run the script** after the container is up:

  ```bash
  docker compose exec -T db mysql -upde -ppde pde < mysql/init-db.sql
  ```

The script currently contains:

```sql
-- Create database if not exists (Docker already does this, but harmless)
CREATE DATABASE IF NOT EXISTS pde CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Create user if not exists (Docker already does this)
CREATE USER IF NOT EXISTS 'pde'@'%' IDENTIFIED BY 'pde';
GRANT ALL PRIVILEGES ON pde.* TO 'pde'@'%';
FLUSH PRIVILEGES;
-- Example table – replace with your actual schema
CREATE TABLE IF NOT EXISTS company (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(10) NOT NULL,
    name VARCHAR(255) NOT NULL,
    sector VARCHAR(100),
    sub_sector VARCHAR(100),
    listing_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_symbol (symbol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Feel free to extend this file with your actual tables.

### 2.2 Manual MySQL installation

If you are not using Docker, run the following SQL commands (adjust credentials as needed):

```sql
CREATE DATABASE IF NOT EXISTS pde CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'pde'@'localhost' IDENTIFIED BY 'pde';
GRANT ALL PRIVILEGES ON pde.* TO 'pde'@'localhost';
FLUSH PRIVILEGES;
```

Then load the schema:

```bash
mysql -upde -ppde pde < mysql/init-db.sql
```

## 3. Schema Migrations

Yii’s migration system allows you to version‑control database changes.

### 3.1 Creating a migration

```bash
docker compose exec php php yii migrate/create create_stock_price_table
```

This generates a file under `pde-idx-app/migrations/mXXXXXXXX_XXXXXX_create_stock_price_table.php`.

Edit the generated file:

```php
use yii\db\Migration;

class m230901_123456_create_stock_price_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%stock_price}}', [
            'id' => $this->primaryKey(),
            'company_id' => $this->integer()->notNull(),
            'date' => $this->date()->notNull(),
            'open' => $this->decimal(10, 2),
            'high' => $this->decimal(10, 2),
            'low' => $this->decimal(10, 2),
            'close' => $this->decimal(10, 2),
            'volume' => $this->bigInteger(),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        // add foreign key if you have a company table
        $this->addForeignKey(
            'fk_stock_price_company',
            '{{%stock_price}}',
            'company_id',
            '{{%company}}',
            'id',
            'CASCADE',
            'RESTRICT'
        );
    }

    public function safeDown()
    {
        $this->dropTable('{{%stock_price}}');
    }
}
```

### 3.2 Applying migrations

```bash
docker compose exec php php yii migrate/up --interactive=0
```

### 3.3 Reverting migrations

```bash
# Revert the last applied migration
docker compose exec php php yii migrate/down --interactive=0 1

# Revert a specific number of steps
docker compose exec php php yii migrate/down 3

# Show migration history
docker compose exec php php yii migrate/history
```

### 3.4 Best practices

- Never modify an existing migration file after it has been committed; instead, create a new migration.
- Keep migrations **idempotent** where possible (use `createTableIfNotExists`, `addColumnIfNotExists`).
- Wrap multiple related statements in a transaction (the `safeUp`/`safeDown` methods already run inside a transaction).

## 4. Backup and Restore Strategies

### 4.1 Logical backups with `mysqldump`

```bash
# Backup the entire database
docker compose exec db mysqldump -upde -ppde pde > backup_$(date +%F).sql

# Compress on the fly
docker compose exec db mysqldump -upde -ppde pde | gzip > backup_$(date +%F).sql.gz
```

To restore:

```bash
# Uncompressed
cat backup_2024-09-26.sql | docker compose exec -i db mysql -upde -ppde pde

# Compressed
zcat backup_2024-09-26.sql.gz | docker compose exec -i db mysql -upde -ppde pde
```

### 4.2 Physical backup (volume snapshot)

When using Docker named volumes (`db_data`), you can snapshot the volume’s underlying files.

1. Pause the database to ensure a consistent state (optional but recommended for InnoDB with `FLUSH TABLES WITH READ LOCK`):

   ```bash
   docker compose exec db mysql -upde -ppde -e "FLUSH TABLES WITH READ LOCK;"
   ```

2. Determine the volume mount point:

   ```bash
   docker volume inspect pde-b2b-web-id_db_data
   ```

   Look for the `Mountpoint` field, e.g., `/var/lib/docker/volumes/pde-b2b-web-id_db_data/_data`.

3. Copy the data directory to a backup location:

   ```bash
   sudo rsync -a /var/lib/docker/volumes/pde-b2b-web-id_db_data/_data/ /path/to/backup/db_data/
   ```

4. Release the lock:

   ```bash
   docker compose exec db mysql -upde -ppde -e "UNLOCK TABLES;"
   ```

To restore, stop the container, replace the volume contents with the backup, then start the container again.

### 4.3 Automated backups (cron job example)

Add a cron job on the host that runs daily:

```bash
0 2 * * * /usr/bin/docker compose -f /path/to/project/docker-compose.yml exec -T db mysqldump -upde -ppde pde | gzip > /backups/pde-db-$(date +\%F).sql.gz
```

Keep only the last N days:

```bash
find /backups -name "pde-db-*.sql.gz" -mtime +30 -delete
```

## 5. Performance Tuning

### 5.1 MySQL configuration (via `my.cnf`)

If you need to adjust MySQL server parameters, you can mount a custom configuration file into the container:

```yaml
services:
  db:
    image: mysql:5.7
    volumes:
      - db_data:/var/lib/mysql
      - ./my.cnf:/etc/mysql/conf.d/custom.cnf:ro
```

Example `my.cnf`:

```ini
[mysqld]
innodb_buffer_pool_size = 512M
max_connections = 200
query_cache_type = 1
query_cache_size = 64M
log_slow_queries = /var/log/mysql/slow.log
long_query_time = 2
```

Remember to restart the container after changing the file: `docker compose restart db`.

### 5.2 Indexing

- Always index columns used in `WHERE`, `JOIN`, `ORDER BY`, and `GROUP BY` clauses.
- Use `EXPLAIN` to verify query plans.
- For time‑series data (e.g., stock prices), consider a composite index on `(company_id, date)` if you often filter by both.

### 5.3 Query caching

Yii’s query caching can be enabled in the application config:

```php
'components' => [
    'db' => [
        'class' => yii\db\Connection::class,
        'dsn' => 'mysql:host=db;dbname=pde;charset=utf8',
        'username' => 'pde',
        'password' => 'pde',
        'charset' => 'utf8',
    ],
    'cache' => [
        'class' => yii\caching\FileCache::class,
    ],
],
```

Then in your models or DAO wrappers:

```php
$rows = (new \yii\db\Query())
    ->select(['*'])
    ->from('{{%stock_price}}')
    ->where(['company_id' => $companyId])
    ->orderBy(['date' => SORT_DESC])
    ->limit(100)
    ->cache(3600) // cache results for 1 hour
    ->all($db);
```

### 5.4 Monitoring

- Enable the MySQL slow‑query log (see the `my.cnf` example above).
- Use `docker stats` to observe container memory/CPU usage.
- Periodically run `mysqlcheck` to analyse and optimize tables:

  ```bash
  docker compose exec db mysqlcheck -upde -ppde --auto-repair --check --optimize pde
  ```

## 6. Upgrading MySQL Version

If you decide to move from MySQL 5.7 to a newer version (e.g., 8.0):

1. **Backup** your data (see section 4).
2. Update the `image` in `docker-compose.yml`:

   ```yaml
   image: mysql:8.0
   ```

3. Optionally adjust configuration variables (some 5.7 variables are deprecated in 8.0).
4. Run `docker compose up -d --force-recreate db`.
5. Run `mysql_upgrade` inside the container:

   ```bash
   docker compose exec db mysql_upgrade -upde -upde -ppde
   ```

6. Verify the application still works.

## 7. Security Considerations

- **Do not expose MySQL ports to the public internet.** Keep the database accessible only from the application server (or via VPN/SSH tunnel).
- Use a strong, unique password for the `pde` user in production; consider using Docker secrets or environment variables from a secure vault.
- Limit the MySQL user’s privileges to only what the application needs (e.g., `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE TEMPORARY TABLES`). Avoid granting `SUPER` or `FILE` unless absolutely necessary.
- Regularly apply security patches: keep the Docker image up‑to‑date (`docker compose pull db` then `docker compose up -d db`).

## 8. Frequently Asked Questions (FAQ)

**Q: I see the warning “Unable to load dynamic library 'pq.so'” when running Composer.**  
**A:** This is a harmless warning caused by the PostgreSQL extension not being present in the PHP image. It does not affect MySQL connectivity. You can suppress it by setting the environment variable `COMPOSER_DISABLE_XDEBUG_WARN=1` or by installing the `pdo_pgsql` extension if you actually need it.

**Q: My migrations fail with “SQLSTATE[42S01]: Base table already exists”. What should I do?**  
**A:** Either the table was created manually outside of migrations, or you attempted to apply the same migration twice. Check the `migration` table to see which migrations have been applied. If the table already exists and matches the expected schema, you can manually mark the migration as applied:

```bash
docker compose exec php php yii migrate/mark 150101_000000_create_user_table
```

**Q: How can I change the database name without breaking everything?**  
**A:** Update `MYSQL_DATABASE` in the `docker-compose.yml` (or the relevant environment variable if using an external server), update the `dbname` part of the DSN in `db.php`, and then restart the containers. Existing data will be preserved if you keep the same volume.

**Q: I want to use a different character set (e.g., `utf8mb4`).**  
**A:** Modify the DSN to include `charset=utf8mb4`, and ensure the database, tables, and columns are created with that charset. The `init-db.sql` script already creates the database with `utf8mb4`.

## 9. Resources

- MySQL 5.7 Reference Manual: https://dev.mysql.com/doc/refman/5.7/en/
- Yii 2 Database Documentation: https://www.yiiframework.com/doc/guide/2.0/en/db-dao
- Docker MySQL Image: https://hub.docker.com/_/mysql
- Docker Compose File Reference: https://docs.docker.com/compose/compose-file/

---


*Keep this document in sync with any changes to the database schema or connection procedure.*