# Deployment Guide

This document describes how to deploy the PDE‑B2B‑WEB‑ID application from a development environment to a production (or staging) environment. It covers container‑based deployments, configuration management, scaling, and operational best practices.

## Table of Contents

1. [Deployment Overview](#deployment-overview)
2. [Prerequisites for Production](#prerequisites-for-production)
3. [Building a Production Image](#building-a-production-image)
4. [Configuring Environment‑Specific Settings](#configuring-environment-specific-settings)
5. [Running the Stack in Production](#running-the-stack-in-production)
6. [Scaling and High Availability](#scaling-and-high-availability)
7. [Database Considerations in Production](#database-considerations-in-production)
8. [Monitoring, Logging, and Alerting](#monitoring-logging-and-alerting)
9. [Backup and Disaster Recovery](#backup-and-disaster-recovery)
10. [Security Hardening](#security-hardening)
11. [Rollback Procedures](#rollback-procedures)
12. [Continuous Integration / Continuous Delivery (CI/CD) Tips](#continuous-integration--continuous-delivery-ci-cd-tips)
13. [Troubleshooting Common Deployment Issues](#troubleshooting-common-deployment-issues)
14. [References](#references)

---


## 1. Deployment Overview

The recommended deployment strategy uses **Docker** (or a container orchestration platform such as Kubernetes or Docker Swarm) to package the application and its dependencies into immutable images. This ensures consistency across development, testing, and production environments.

The core services are:

- **Application (`php`)**: Yii 2 web application running under Apache (or PHP‑FPM with NGINX).
- **Database (`db`)**: MySQL (or a compatible drop‑in such as MariaDB, Percona Server, or a managed cloud SQL service).
- **Optional auxiliary services**:
  - **Cache**: Redis or Memcached.
  - **Message Queue**: RabbitMQ, Apache Kafka, or a DB‑based queue.
  - **Object Storage**: AWS S3, MinIO, or Azure Blob Storage for large files and backups.
  - **Reverse Proxy / Load Balancer**: NGINX, Traefik, HAProxy, or an AWS ALB.

## 2. Prerequisites for Production

| Item | Requirement |
|------|-------------|
| **Host OS** | A modern Linux distribution (Ubuntu 20.04 LTS, Debian 11, RHEL 8/CentOS Stream, or Amazon Linux 2). |
| **Container Runtime** | Docker Engine ≥ 20.10 **or** a Kubernetes cluster (v1.22+). |
| **Orchestration** | For simple setups, Docker Compose is sufficient. For HA and scaling, prefer Kubernetes or Docker Swarm. |
| **Network** | A private VPC or subnet where only the application servers and database can communicate; no direct public access to the database port. |
| **Storage** | Persistent volumes backed by reliable storage (SSD, network‑attached storage, or cloud block storage) for MySQL data. |
| **TLS Certificate** | Valid SSL/TLS certificate for the domain(s) you will serve (e.g., from Let’s Encrypt, ACM, or a private PKI). |
| **Monitoring** | Prometheus node exporter, cAdvisor, or similar for host/container metrics; plus application‑level metrics via Yii’s logging or a custom exporter. |
| **Logging Centralisation** | Aggregate container logs to a system like Elasticsearch + Kibana, Loki, Splunk, or CloudWatch Logs. |
| **Secrets Management** | Use Docker secrets, Kubernetes Secrets, HashiCorp Vault, AWS Secrets Manager, or Azure Key Vault to store DB passwords, API keys, etc. |

## 3. Building a Production Image

Instead of bind‑mounting source code (as we do in `docker-compose.yml` for development), production images should contain the application code, making the image self‑contained and portable.

### 3.1 Create a `Dockerfile` (based on the official Yii image)

Create a file named `Dockerfile.php` in the repository root:

```dockerfile
# ----- Base image -----
FROM yiisoftware/yii2-php:7.1-apache

# ----- Install system dependencies (if any) -----
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        && rm -rf /var/lib/apt/lists/*

# ----- Copy application code -----
WORKDIR /app
COPY pde-idx-app/ /app/

# ----- Install Composer dependencies (production only) -----
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# ----- Set proper permissions for runtime and web assets -----
RUN chown -R www-data:www-data /app/runtime /app/web/assets

# ----- Expose the HTTP port (optional, can be defined in compose/k8s) -----
EXPOSE 80

# ----- Use the default entrypoint from the base image -----
# (it runs apache2ctl start)
```

### 3.2 Build the image

```bash
docker build -t pde-b2b-web-id:php -f Dockerfile.php .
```

### 3.3 (Optional) Multi‑stage build for smaller image

If you want to exclude build‑time tools (like git) from the final image, you can use a multi‑stage approach, but the base image is already relatively small.

## 4. Configuring Environment‑Specific Settings

### 4.1 Environment Variables

The `php` image supports several environment variables to tweak behaviour without rebuilding the image:

| Variable | Purpose | Example |
|----------|---------|---------|
| `APACHE_DOCUMENT_ROOT` | Sets the Apache DocumentRoot (defaults to `/var/www/html`). Set to `/app/web` to serve the Yii `web` directory. | `APACHE_DOCUMENT_ROOT=/app/web` |
| `YII_DEBUG` | Boolean (`true`/`false`) to enable debug mode and detailed error messages. | `YII_DEBUG=false` |
| `YII_ENV` | Environment identifier (`dev`, `prod`, `test`). Affects which config files are loaded (`web.php`, `web-prod.php`, etc.). | `YII_ENV=prod` |
| `DB_HOST` | MySQL hostname (if using external DB). | `db-prod.internal` |
| `DB_NAME` | Database name. | `pde_prod` |
| `DB_USER` | Database username. | `pde_prod_user` |
| `DB_PASSWORD` | Database password (prefer using secrets). | `s3cr3t` |
| `REDIS_HOST` | Hostname for Redis cache (if used). | `redis-prod.internal` |
| `REDIS_PASSWORD` | Password for Redis (if auth enabled). |  |
| `MAILER_TRANSPORT` | SMTP transport for email notifications (e.g., `smtp`). | `smtp` |
| `MAILER_HOST` | SMTP host. | `smtp.example.com` |
| `MAILER_USERNAME` | SMTP username. | `smtp_user` |
| `MAILER_PASSWORD` | SMTP password. |  |
| `TIMEZONE` | Default PHP timezone (e.g., `Asia/Jakarta`). | `Asia/Jakarta` |

You can pass these variables at container runtime (`docker run -e ...`) or define them in an orchestration file (Compose, Kubernetes).

### 4.2 External Configuration Files

If you prefer not to bake configuration into the image, you can mount configuration files at runtime:

- **Custom `web.php`** (or `web-prod.php`): mount over `/app/config/web.php`.
- **Custom `params.php`**: mount over `/app/config/params.php`.
- **Custom `params-local.php`** (not committed): for secrets.

Example bind‑mount in compose:

```yaml
services:
  php:
    image: pde-b2b-web-id:php
    volumes:
      - ./pde-idx-app:/app:ro          # read‑only code
      - ./config/prod/web.php:/app/config/web.php:ro
      - ./config/prod/params.php:/app/config/params.php:ro
      - ./ секреты:/app/config/secret.php:ro   # if you have a separate secret file
```

## 5. Running the Stack in Production

### 5.1 Using Docker Compose (suitable for small‑to‑medium staging)

Create a `docker-compose.prod.yml` that overrides the development settings:

```yaml
version: '3.8'

services:
  db:
    image: mysql:5.7
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:-root}
      MYSQL_DATABASE: ${MYSQL_DATABASE:-pde}
      MYSQL_USER: ${MYSQL_USER:-pde}
      MYSQL_PASSWORD: ${MYSQL_PASSWORD:-pde}
    volumes:
      - db_data:/var/lib/mysql
    # No ports exposed to the host in production

  php:
    image: pde-b2b-web-id:php   # built as per section 3
    restart: unless-stopped
    environment:
      APACHE_DOCUMENT_ROOT: /app/web
      YII_DEBUG: false
      YII_ENV: prod
      # DB credentials (can also come from a .env file or Docker secrets)
      MYSQL_HOST: db
      MYSQL_DATABASE: ${MYSQL_DATABASE:-pde}
      MYSQL_USER: ${MYSQL_USER:-pde}
      MYSQL_PASSWORD: ${MYSQL_PASSWORD:-pde}
    depends_on:
      - db
    # Expose only what you need (e.g., via a reverse proxy)
    ports:
      - "80:80"   # if you expose directly; otherwise omit and use a proxy

  # Optional Redis cache
  redis:
    image: redis:7-alpine
    restart: unless-stopped
    # ports: - "6379:6379"   # expose only if needed internally

volumes:
  db_data:
```

Deploy with:

```bash
docker compose -f docker-compose.prod.yml up -d
```

### 5.2 Using Kubernetes

A minimal manifest set (Deployment + Service) for the php and db components:

**db-deployment.yaml**

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: mysql
  labels:
    app: mysql
spec:
  replicas: 1
  selector:
    matchLabels:
      app: mysql
  template:
    metadata:
      labels:
        app: mysql
    spec:
      containers:
        - name: mysql
          image: mysql:5.7
          env:
            - name: MYSQL_ROOT_PASSWORD
              valueFrom:
                secretKeyRef:
                  name: mysql-secrets
                  key: root-password
            - name: MYSQL_DATABASE
              value: pde
            - name: MYSQL_USER
              valueFrom:
                secretKeyRef:
                  name: mysql-secrets
                  key: user
            - name: MYSQL_PASSWORD
              valueFrom:
                secretKeyRef:
                  name: mysql-secrets
                  key: password
          ports:
            - containerPort: 3306
          volumeMounts:
            - name: data
              mountPath: /var/lib/mysql
          readinessProbe:
            exec:
              command: ["mysql", "-u$$MYSQL_USER", "-p$$MYSQL_PASSWORD", "-e", "SELECT 1"]
            initialDelaySeconds: 5
            periodSeconds: 10
          livenessProbe:
            exec:
              command: ["mysql", "-u$$MYSQL_USER", "-p$$MYSQL_PASSWORD", "-e", "SELECT 1"]
            initialDelaySeconds: 15
            periodSeconds: 20
      volumes:
        - name: data
          persistentVolumeClaim:
            claimName: mysql-pvc
```

**php-deployment.yaml** (similar structure, using the custom image, exposing port 80, referencing the same secrets for DB credentials, and optionally adding environment variables for `APACHE_DOCUMENT_ROOT`, `YII_ENV`, etc.)

Create a **Service** for each to allow internal communication (`ClusterIP`) and an **Ingress** (or `LoadBalancer` Service) to expose the php service externally via HTTPS.

Apply with:

```bash
kubectl apply -f k8s/
```

## 6. Scaling and High Availability

### 6.1 Scaling the Application Layer

Since the PHP‑Apache (or PHP‑FPM) processes are stateless (all state lives in DB, cache, or object storage), you can scale horizontally:

- **Docker Compose**: increase the `replicas` count via `docker-compose.yml` using the `deploy:` section (only works with Docker Swarm) or manually run multiple containers with a load balancer in front.
- **Kubernetes**: set `spec.replicas` to a number >1 (e.g., 3) and use a `Service` of type `ClusterIP` backed by an `Ingress` or `Service` of type `LoadBalancer`.
- **Swarm**: use `replicas` under `deploy`.

Ensure that the reverse proxy (NGINX, Traefik, ALB) is configured to distribute traffic evenly and to preserve session affinity if your application relies on in‑memory sessions (better to use Redis or DB‑based sessions).

### 6.2 Database High Availability

- **MySQL Replication**: Set up a primary‑replica topology (one master, one or more slaves) using MySQL Group Replication or Percona XtraDB Cluster for synchronous replication.
- **Managed Services**: Use AWS Aurora, Google Cloud SQL, or Azure Database for MySQL, which provide built‑in HA and automated backups.
- **Proxy Layer**: Use ProxySQL or HAProxy in front of the MySQL instances to route writes to the primary and reads to the replicas.

### 6.3 Cache and Queue Scaling

- **Redis**: Use Redis Cluster or AWS Elasticache with multiple shards.
- **Message Queue**: Deploy a RabbitMQ cluster or use a managed service like Amazon MQ or Azure Service Bus.

## 7. Database Considerations in Production

### 7.1 Connection Pooling

Yii’s DB component creates a new PDO connection per request by default. In high‑traffic scenarios, enable persistent connections:

```php
'components' => [
    'db' => [
        'class' => yii\db\Connection::class,
        'dsn' => 'mysql:host=db;dbname=pde;charset=utf8',
        'username' => 'pde',
        'password' => 'pde',
        'charset' => 'utf8',
        'attributes' => [
            PDO::ATTR_PERSISTENT => true,
        ],
    ],
],
```

### 7.2 Schema Caching

Turn on schema caching in production (see the `db.php` snippet in the database guide). Ensure the cache component (e.g., Redis) is configured and reachable.

### 7.3 Backup Strategy

Schedule regular logical dumps or use your cloud provider’s automated backup feature. Verify restore procedures quarterly.

### 7.4 Performance Tuning

- Adjust `innodb_buffer_pool_size` to about 60‑70% of available RAM on the DB host.
- Enable the slow query log and use `pt-query-digest` (from Percona Toolkit) to identify problematic queries.
- Consider partitioning large fact tables (e.g., daily price data) by date.

## 8. Monitoring, Logging, and Alerting

### 8.1 Application Logging

Yii logs to `runtime/logs/app.log` by default. In production, you can:

- Mount a host directory or a Docker volume for logs and ship them via a sidecar (fluentd/filebeat) to a central system.
- Alternatively, configure Yii to use Syslog or a UDP/TCP logger (e.g., `yii\logging\SyslogTarget`, `yii\logging\MailTarget`, or a custom target that writes to Elasticsearch).

### 8.2 Metrics

Export key metrics via Prometheus:

- Use the `yii2-prometheus` extension or a custom middleware to expose `/metrics`.
- Scrape the endpoint with Prometheus and create Grafana dashboards for:
  - Request rate (`http_requests_total`)
  - Response latency (`http_request_duration_seconds`)
  - Database query count and duration
  - Cache hit/miss ratio
  - Queue depth

### 8.3 Health Checks

- **Liveness**: a simple HTTP GET to `/` (or `/health`) returning 200.
- **Readiness**: verify DB connectivity and cache availability (e.g., a custom endpoint that runs `SELECT 1` and pings Redis).

Configure your orchestrator (Kubernetes, Docker Swarm) to use these probes.

## 9. Backup and Disaster Recovery

### 9.1 Regular Backups

- **Logical**: nightly `mysqldump` (or `mydumper` for faster dumps) retained for 30 days.
- **Physical**: hourly snapshots of the Docker volume or EBS volume (if using AWS EC2) using LVM snapshots or cloud-native snapshot APIs.
- **Object Storage**: back up uploaded files and exported reports to a version‑enabled bucket (e.g., AWS S3 with versioning).

### 9.2 Restore Testing

Perform a restore drill at least quarterly:
1. Spin up a temporary MySQL instance from the latest backup.
2. Point a test copy of the application to it.
3. Run a smoke test (login, run a report) to confirm integrity.

### 9.3 Disaster Recovery Site

If you require an active‑passive DR site:
- Replicate the MySQL binary logs to a standby server using asynchronous replication.
- Keep a copy of the container images and configuration in a separate registry.
- Automate failover via DNS or a load balancer health check.

## 10. Security Hardening

### 10.1 Network Segmentation

- Place the MySQL container/service in a private subnet with no inbound internet access.
- Allow only the application subnet (or specific security groups) to connect to port 3306.

### 10.2 Least Privilege

- Create a dedicated MySQL user with only the necessary privileges (`SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE TEMPORARY TABLES`, `EXECUTE` for stored procedures). Avoid granting `SUPER`, `FILE`, `SHUTDOWN`.
- Rotate passwords every 90 days (or use short‑lived credentials from a secrets manager).

### 10.3 Application Hardening

- Ensure `YII_DEBUG` is **false** in production.
- Set `YII_ENV` to `prod`.
- Disable PHP functions that are dangerous (`disable_functions` in php.ini: `exec,system,shell_exec,passthru,proc_open,popen`).
- Enable `open_basedir` restriction to limit PHP file access to `/app` and `/tmp`.
- Use HTTPS everywhere; enforce HSTS.
- Implement Content Security Policy (CSP) headers to mitigate XSS.
- Regularly run dependency scanners (e.g., `composer audit`, `npm audit`) and apply patches.

### 10.4 Secrets Management

- Do **not** commit passwords or API keys to the repository.
- Use Docker secrets (`docker secret create`) or Kubernetes Secrets.
- In the compose file, reference secrets:

  ```yaml
  secrets:
    - db_password
    - redis_password

  services:
    php:
      secrets:
        - db_password
        - redis_password
  ```

### 10.5 Vulnerability Scanning

- Scan container images with `trivy`, `clair`, or `anchore` before pushing to a registry.
- Keep base images up‑to‑date (`docker compose pull`).

## 11. Rollback Procedures

### 11.1 Application Rollback

If you deployed a new image and observe issues:

- **Docker Compose**: `docker compose pull php` to get the previous image (if you keep old tags) or `docker compose up -d --no-deps --build php` after changing the image tag back.
- **Kubernetes**: `kubectl rollout undo deployment/php-deployment` or manually set the image to the previous version.

### 11.2 Database Rollback

- **Logical backup**: restore the latest known‑good dump.
- **Physical snapshots**: revert the volume to a previous snapshot (this will discard any changes made after the snapshot).
- **Point‑in‑time recovery (PITR)**: if you have enabled MySQL binary logs, you can restore to a specific timestamp using `mysqlbinlog`.

### 11.3 Database Migration Rollback

If a newly applied migration causes issues, you can revert it:

```bash
docker compose exec php php yii migrate/down --interactive=0 1
```

Then fix the migration and reapply.

## 12. CI/CD Tips

- **Build Stage**: compile the Docker image, run unit tests inside the container (use a separate test stage that mounts the code and runs `vendor/bin/codecept run unit`).
- **Test Stage**: run acceptance tests against a temporary environment (spin up compose with a test DB, seed with fixtures, run Codeception).
- **Publish Stage**: push the image to a private registry (Harbor, AWS ECR, GCR, Azure Container Registry) with tags like `v1.2.3` or the git SHA.
- **Deploy Stage**: use your orchestrator’s rolling update feature (e.g., `kubectl set image deployment/php php=pde-b2b-web-id:php:<sha>`).
- **Approval Gates**: require manual approval before promoting to production.
- **Notification**: send Slack/email alerts on deployment success/failure.

## 13. Troubleshooting Common Deployment Issues

| Symptom | Likely Cause | Fix |
|---|---|---|
| 502 Bad Gateway from reverse proxy | PHP container not responding (crashed or not started). | Check `docker compose logs php` or `kubectl logs pod/php-xxx`. Look for PHP fatal errors (often missing extensions or permission issues). |
| Application shows “Error 500 – Internal Server Error” but logs show nothing | PHP’s `display_errors` is Off; check `runtime/logs/app.log`. | Ensure logging is enabled and the `runtime` directory is writable. |
| Database connection timeout | MySQL container not ready, or network policy blocks traffic. | Wait for MySQL to be healthy (`docker compose logs db` shows “MySQL init process done”). Verify that the `php` service can resolve `db` (use `docker compose exec php getent hosts db`). |
| High CPU on MySQL | Missing indexes or runaway query. | Enable slow‑query log, identify offending query, add appropriate index or limit result set. |
| Cache misses causing DB load | Redis not reachable or memory exhausted. | Check `docker compose logs redis` for OOM errors; increase `maxmemory` or eviction policy. |
| Disk space full on host | Docker volumes filling up with container logs or images. | Implement log rotation (`docker container prune`, `docker image prune`), configure logging driver to syslog or a remote endpoint. |
| “Address already in use” when starting containers | Another process bound to the same host port (e.g., another web server). | Stop the conflicting process or change the host port mapping (e.g., `"8080:80"`). |

## 14. References

- Yii 2 Guide: https://www.yiiframework.com/doc/guide/2.0/en
- Docker Documentation: https://docs.docker.com/
- Docker Compose Reference: https://docs.docker.com/compose/compose-file/
- Kubernetes Documentation: https://kubernetes.io/docs/home/
- MySQL Reference Manual: https://dev.mysql.com/doc/
- Redis Documentation: https://redis.io/documentation
- Prometheus: https://prometheus.io/docs/introduction/overview/
- OWASP Docker Security Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html
- Securing Containers (CIS Docker Benchmark): https://www.cisecurity.org/controls/docker-benchmark

---


*Keep this guide in line with any changes to your deployment architecture (e.g., adding a service mesh, switching to a managed database, or adopting a serverless approach).*