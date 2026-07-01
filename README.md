# Laravel JWT API Starter (Dockerized)

A boilerplate Laravel JWT-based API starter kit, pre-configured with a modern Docker environment (PHP 8.1 FPM, Nginx, MySQL, Redis) and robust API documentation and database tools.

---

## 🚀 Quick Start

Follow these steps to initialize and start the Docker environment:

### Step 1: Set Up Docker Environment File
Before starting the containers, create your Docker-specific environment configuration:
```bash
cp .env.docker.example .env.docker
```

### Step 2: Build and Start the Containers
Run Docker Compose to build and launch the PHP-FPM, Nginx, MySQL, and Redis services:
```bash
docker compose up -d --build
```
*(On first start, wait a few moments for the database to boot and compile).*

#### Verify Containers are Running
Run `docker ps` to ensure all four containers (`web`, `app`, `redis`, `db`) are active:
```bash
docker ps
```

Example Output:
```text
CONTAINER ID   IMAGE                 COMMAND                  CREATED        STATUS         PORTS                                         NAMES
9b59906fe1be   nginx:stable-alpine   "/docker-entrypoint.…"   37 hours ago   Up X minutes   0.0.0.0:80->80/tcp, [::]:80->80/tcp           web
0cc86a0e698e   app:latest            "/usr/local/bin/dock…"   37 hours ago   Up X minutes   9000/tcp                                      app
20fea63458ef   redis:alpine          "docker-entrypoint.s…"   37 hours ago   Up X minutes   0.0.0.0:6379->6379/tcp, [::]:6379->6379/tcp   redis
8cee392e47de   mysql:8.0             "docker-entrypoint.s…"   37 hours ago   Up X minutes   0.0.0.0:3306->3306/tcp, [::]:3306->3306/tcp   db
```

### Step 3: Configure Laravel Application Keys
Create the local Laravel `.env` configuration file and generate the application key and JWT secret:
```bash
# 1. Copy the template .env file in the src directory
cp src/.env.example src/.env

# 2. Generate Laravel App Key inside the container
docker compose exec app php artisan key:generate

# 3. Generate JWT Secret Key inside the container
docker compose exec app php artisan jwt:secret
```

### Step 4: Run Database Migrations
Run the database migrations and seed the database with initial template records:
```bash
docker compose exec app php artisan migrate:fresh --seed
```

### Step 5: Verify the Setup
Open your browser and navigate to `http://localhost`. The application is now fully configured and running!

---

## 🛠️ Running Artisan Commands

Run any standard Artisan command inside the running `app` container using `docker compose exec`:
- **Run migrations:**
  ```bash
  docker compose exec app php artisan migrate
  ```
- **Run migrations with seed data:**
  ```bash
  docker compose exec app php artisan migrate:fresh --seed
  ```
- **Run test suites:**
  ```bash
  docker compose exec app php artisan test
  ```
- **Access Tinker CLI:**
  ```bash
  docker compose exec app php artisan tinker
  ```
- **Open a shell inside the container:**
  ```bash
  docker compose exec app sh
  ```

---

## 📄 API & Database Documentation Tools

This starter includes dynamic generators for API documentation, Postman collections, and database schema layouts, consolidated under a single command.

### 🔄 How to Regenerate All Documentation
To regenerate the OpenAPI spec, Postman collection, markdown overview, and DBML schema at once, run:
```bash
docker compose exec app php artisan docs:generate
```

---

### 1. Interactive API Docs (OpenAPI / Stoplight Elements)
The API documentation is built using OpenAPI and rendered beautifully via **Stoplight Elements**.
- **Interactive UI**: Open [docs/api/index.html](docs/api/index.html) in your local browser to view the interactive API sandbox.
- **Specification File**: The raw OpenAPI schema is located at [docs/api/openapi.json](docs/api/openapi.json).
- **Markdown Overview**: A detailed text list of all endpoints, parameters, responses, and errors is available at [docs/api/overview.md](docs/api/overview.md).

---

### 2. Postman Collection
An automated Postman collection is exported to facilitate immediate API testing and import.
- **Collection File**: Import the pre-configured [docs/api/postman_collection.json](docs/api/postman_collection.json) directly into Postman.
- **Environment Support**: Pre-populated with default variables `{{base_url}}` (defaults to `http://localhost/api`) and `{{token}}` for seamless authentication routing.
- **Dynamic Post Bodies**: Auto-fills post body parameters with simulated mock data based on input schemas.

---

### 3. Database Schema Diagram (DBML)
DBML (Database Markup Language) is a simple syntax used to define and visualize database schemas.
- **DBML Schema File**: Located at [docs/database.dbml](docs/database.dbml).
- **Visualization**: Copy and paste the contents of `database.dbml` into [dbdiagram.io](https://dbdiagram.io) to view an interactive Entity Relationship (ER) Diagram.



