# Architecture Overview

This document describes the high‑level architecture of the PDE‑B2B‑WEB‑ID system, including the major components, their responsibilities, data flow, and deployment considerations.

## 1. System Context

PDE‑B2B‑WEB‑ID (Pusat Data Elektronik – Business‑to‑Business Web Indonesia) is a web‑based platform that collects, stores, and serves financial market data sourced primarily from the Indonesia Stock Exchange (IDX). The system enables users (analysts, traders, decision‑makers) to query company profiles, historical prices, financial statements, news, and other derived metrics.

The application is built on the **Yii 2 PHP framework**, follows a **Model‑View‑Controller (MVC)** pattern, and uses a **relational MySQL database** for persistent storage. The typical deployment stacks the application behind a reverse proxy (NGINX/Apache) and optionally uses a caching layer (Redis/Memcached) and a message queue for asynchronous jobs.

## 2. Logical Components

| Component | Responsibility | Technology / Implementation |
|-----------|----------------|-----------------------------|
| **Web Server (Frontend)** | Serves static assets (CSS, JS, images) and routes PHP requests to the application. | Apache 2.4 (via `yiisoftware/yii2-php:7.1-apache` Docker image) or NGINX in production. |
| **Application Core** | Handles HTTP requests, implements business logic, orchestrates data access, and renders responses. | Yii 2 framework (PHP 7.4+). Includes: <br> • **Controllers** – map URLs to actions.<br> • **Models** – ActiveRecord or plain PHP classes representing database tables and encapsulating validation/rules.<br> • **Views** – PHP templates (with optional layouts) that generate HTML. |
| **Data Access Layer** | Abstracts direct SQL via Yii’s DAO/ActiveRecord, provides query building, caching, and transaction support. | Yii 2 DB component, built on PDO. |
| **Database** | Persistent storage for all structured data. | MySQL 5.7 (or 8.0) InnoDB. Schema defined via migrations. |
| **Caching Layer (optional)** | Stores frequently accessed data (query results, configuration, rendered fragments) to reduce DB load. | Redis or Memcached (configured via `yii\caching\RedisCache` or `MemCache`). |
| **Message Queue (optional)** | Offloads long‑running or asynchronous tasks (e.g., importing large CSV/JSON feeds, sending emails). | RabbitMQ, Apache Kafka, or a simple DB‑based queue (`yii\queue\db\Queue`). |
| **Background Workers / Console Commands** | Executes scheduled jobs (data imports, report generation, cleanup). | Yii 2 console controllers, invoked via `yii` command or via cron/systemd timers. |
| **File Storage** | Holds user uploads, exported reports, cached assets, logs. | Local filesystem (mounted volume) or object storage (AWS S3, MinIO) via Yii’s `yii\fs\` abstractions. |
| **External Services** | Consumes data from third‑party sources (IDX website, APIs, financial data vendors). | Custom PHP clients using Guzzle, cURL, or SOAP; may run as periodic jobs. |
| **Monitoring & Logging** | Captures runtime metrics, errors, and audit trails for ops visibility. | Monolog/Yii logger (file, Syslog, Elasticsearch), Prometheus exporter, Grafana dashboards. |
| **Security Layer** | Handles authentication, authorization, input validation, CSRF protection, and encryption. | Yii 2 built‑in auth (`rbac`), password hashing (`password_hash`), CSRF tokens, HTTPS enforcement. |

## 3. Data Flow

1. **User Request** – A browser sends an HTTP GET/POST to the web server (e.g., `GET /company/ABCJ`).
2. **Web Server** – Apache forwards the request to PHP‑FPM (embedded in the `yiisoftware/yii2-php:7.1-apache` image) which bootstraps the Yii application.
3. **Routing** – Yii’s `urlManager` maps the URL to a specific controller action (e.g., `CompanyController::actionView($symbol='ABCJ')`).
4. **Controller Logic** – The controller retrieves any required input parameters, validates them, and calls one or more **service methods** or **models**.
5. **Model Interaction** – Models (ActiveRecord classes) query the database via DAO/ActiveRecord. If query caching is enabled, repeated identical queries may be served from the cache layer.
6. **Business Rules** – Model methods may enforce validation, compute derived values (e.g., price change percentages), or trigger events.
7. **View Rendering** – The controller passes data to a view script (`.php` file) which mixes HTML with PHP to produce the final response.
8. **Response** – The web server returns the generated HTML (or JSON for API endpoints) to the client.
9. **Background Processing** – Separate processes (console commands or queue workers) ingest raw data feeds from external sources, parse them, and persist the results into the relevant tables. These workers run independently of the request‑response cycle.
10. **Caching** – Results of expensive queries or rendered fragments may be stored in Redis/Memcached; subsequent requests retrieve them without hitting the DB.
11. **Logging & Metrics** – Throughout the request lifecycle, the application logs errors, warnings, and debug info. Metrics (request latency, DB query times) are exported to a monitoring system.

## 4. Deployment View (Docker‑Centric)

In the recommended development and staging setup, the system is composed of two containers orchestrated by Docker Compose:

```
+---------------------+          +-------------------+
|  Host (Linux/macOS) |          |  Docker Network   |
|                     |          |  (pde-b2b-web-id_ |
|  +----------------+  |          |   default)        |
|  |  Browser       |<---------->|                   |
|  +----------------+  |          |  +------------+   |
|                     |          |  |  db (MySQL) |   |
|  +----------------+  |          |  +------------+   |
|  |  Docker Host   |----------->|  |  php (Apache+Yii) | |
|  +----------------+  |          |  +------------+   |
|                     |          |                   |
+---------------------+          +-------------------+
```

- The **db** container exposes no ports to the host; only the php container can reach it via `db:3306`.
- The **php** container exposes port 80 (or any host port you map) to allow browser access.
- Persistent data for MySQL lives in a Docker named volume (`db_data`), ensuring data survives container recreation.
- Application source code is bind‑mounted (`./pde-idx-app:/app`) so code changes on the host are immediately visible inside the container—ideal for development.
- In production, you would typically:
  - Build a custom image that includes your application code (rather than bind‑mounting) for immutability.
  - Use a dedicated reverse proxy (NGINX or Traefik) handling TLS termination, rate limiting, and load balancing.
  - Scale the php service horizontally (multiple replicas) behind the proxy, sharing the same database.
  - Optionally add a separate Redis container for caching and a queue service (RabbitMQ) for background jobs.

## 5. Key Design Principles

| Principle | Description |
|-----------|-------------|
| **Separation of Concerns** | UI (views), logic (controllers/models), and data (DB) are cleanly separated, making the codebase easier to test and maintain. |
| **Convention over Configuration** | Yii 2 provides sensible defaults (e.g., table naming, routing) that reduce boilerplate while still allowing customization. |
| **Extensibility** | New functionality can be added by creating new modules, widgets, or extending existing base classes without touching core code. |
| **Testability** | The MVC structure facilitates unit testing of models and controllers; acceptance tests can be written with Codeception. |
| **Scalability** | Stateless PHP workers allow horizontal scaling; state is externalized to the database, cache, and object store. |
| **Security by Default** | Yii provides built‑in protection against SQL injection, XSS, CSRF, and insecure direct object references; password hashing uses `bcrypt`. |
| **Dev‑Prod Parity** | Using Docker for both development and production minimizes “works on my machine” issues; the same image can be promoted through environments with only configuration changes (environment variables). |

## 6. Interaction with External Systems

- **Data Ingestion:** Scheduled cron jobs (or Kubernetes CronJobs) run `yii import/idx-price` or similar commands that fetch CSV/JSON from the IDX website or a vendor’s FTP/SFTP endpoint, parse it, and persist raw and aggregated data.
- **Third‑Party APIs:** For news, analyst ratings, or macro‑economic indicators, the system calls REST endpoints using Guzzle, caches responses, and stores relevant fields in dedicated tables.
- **Export Services:** Users can request CSV/Excel exports; the application generates temporary files in the runtime directory and streams them via `yii\helpers\FileHelper` or a streaming response.
- **Authentication Integrations:** Optional LDAP or OAuth2 clients can be plugged into the Yii `authManager` to support single sign‑on (SSO) with corporate directories.

## 7. Diagram (textual)

```
+----------------+      +----------------+      +-----------------+
|   Browser/     | HTTP |   Web Server   | FastCGI|   PHP‑FPM (Yii) |
|   Client       | <--> | (Apache/Nginx) | <-->   |  (controllers,  |
+----------------+      +----------------+      +-----------------+
                                   |                       |
                                   v                       v
                         +----------------+        +-----------------+
                         |   Router/      |        |   Models (AR)   |
                         |   UrlManager   |        |   <-> DB (MySQL)|
                         +----------------+        +-----------------+
                                   |                       |
                                   v                       v
                         +----------------+        +-----------------+
                         |   Views        |        |   Services/Jobs |
                         |   (templates)  |        |   (queue, cron) |
                         +----------------+        +-----------------+
                                   |                       |
                                   v                       v
                         +----------------+        +-----------------+
                         |   Cache (Redis)|        |   Object Store  |
                         +----------------+        +-----------------+
                                   |                       |
                                   v                       v
                         +----------------+        +-----------------+
                         |   Logs/Metrics |        |   Mail/SMS      |
                         +----------------+        +-----------------+
```

## 8. Extending the Architecture

- **Micro‑services:** If specific domains (e.g., real‑time quote streaming) require different scaling characteristics, they can be extracted into separate services communicating via REST or gRPC.
- **Event‑Driven:** Introduce an event broker (e.g., NATS, Kafka) to decouple data ingestion pipelines from the web UI, enabling real‑time dashboards.
- **Multi‑Tenant:** For serving multiple clients with isolated data, add a tenant identifier column and apply it via a global query scope or use separate databases/schema per tenant.

## 9. Summary

The PDE‑B2B‑WEB‑ID architecture leverages the maturity and robustness of Yii 2, MySQL, and Docker to provide a maintainable, scalable, and secure platform for financial data dissemination. By separating concerns, utilizing proven open‑source components, and allowing for incremental enhancement via modules and queues, the system can evolve from a simple internal dashboard to a full‑featured market data portal serving numerous concurrent users.

---


*Keep this document up‑to‑date whenever you introduce new major components (e.g., adding a message queue, switching to a different web server, or adopting a micro‑service approach).*