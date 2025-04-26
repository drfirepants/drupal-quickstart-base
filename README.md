# Drupal 10 + Docker + Composer Setup

This repository sets up a local Drupal 10 environment using Docker Compose (with MySQL) and manages Drupal core/contributed modules via Composer.
The composer.json contains some frequently used modules to start with but should be tailored to fit your individual needs.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Project Structure](#project-structure)
- [Installation & Setup](#installation--setup)
    - [1. Clone this Repository](#1-clone-this-repository)
    - [2. Install Dependencies](#2-install-dependencies)
    - [3. Start Docker Containers](#3-start-docker-containers)
    - [4. Run Drupal Installation](#4-run-drupal-installation)
- [Common Commands](#common-commands)
    - [Docker Compose](#docker-compose)
    - [Composer](#composer)
    - [Drush](#drush)
- [Enabling JSON:API and Other Modules](#enabling-jsonapi-and-other-modules)
- [Accessing the Site](#accessing-the-site)
- [Future Production Considerations](#future-production-considerations)
- [Troubleshooting](#troubleshooting)
- [License](#license)

## Prerequisites

- **Docker and Docker Compose**
    - Install [Docker](https://www.docker.com/get-started) on your machine.
    - Docker Compose is now included by default in recent versions of Docker Desktop (or install separately if needed).

- **Composer (version 2.x)**
    - Install [Composer](https://getcomposer.org/download/) globally or locally.

- **Git (optional but recommended)**
    - Install [Git](https://git-scm.com/downloads) for cloning this repo and version control.

- **PHP (optional for local Composer usage)**
    - If you run Composer on your host system, ensure you have PHP 8.1 or higher.
    - Alternatively, you can run Composer inside a Docker container, but this README assumes a local Composer installation.

## Installation & Setup

### 1. Clone this Repository

### 2. Install Dependencies

Install Drupal Core and contributed modules via Composer:

```bash
composer install
```

This will create the `web/` directory containing Drupal.

> **Note:** If you do not have PHP/Composer locally, you can run Composer commands inside the Drupal container after it's spun up. However, this README focuses on a local Composer workflow.

### 3. Start Docker Containers

```bash
docker-compose up -d
```

- `drupal_app` (Drupal 10 + Apache) will be running on http://localhost:8080.
- `drupal_db` (MySQL) holds the database for Drupal.

### 4. Run Drupal Installation

- Open your browser at http://localhost:8080.
- Follow the on-screen wizard:
    - **Installation profile:** Standard
    - **Database settings:**
        - Database type: MySQL
        - Database name: drupal
        - Database username: drupal
        - Database password: drupal
        - Host: db (the Docker Compose service name)
- Complete the installation.

## Common Commands

### Docker Compose

- **Start containers** (in background):
  ```bash
  docker-compose up -d
  ```

- **Stop containers**:
  ```bash
  docker-compose down
  ```

- **View logs**:
  ```bash
  docker-compose logs -f
  ```

### Composer

- **Install dependencies** (from composer.json):
  ```bash
  composer install
  ```

- **Add a new Drupal module**:
  ```bash
  composer require drupal/module_name
  ```

- **Update dependencies**:
  ```bash
  composer update
  ```

### Drush

Drush is installed via Composer. You can use it to perform many Drupal tasks.

- **Enable a module**:
  ```bash
  ./vendor/bin/drush en module_name -y
  ```

- **Clear cache**:
  ```bash
  ./vendor/bin/drush cr
  ```

- **Run database updates**:
  ```bash
  ./vendor/bin/drush updb -y
  ```

If you want to run Drush from inside the container:

```bash
docker-compose exec drupal_app bash
vendor/bin/drush <command>
```

## Enabling JSON:API and Other Modules

This project includes several commonly used modules in the composer.json, such as jsonapi_extras, admin_toolbar, pathauto, etc.

To enable any of them, run:

```bash
./vendor/bin/drush en jsonapi jsonapi_extras admin_toolbar pathauto token metatag redirect -y
```

Once JSON:API is enabled, you can test it by visiting:

http://localhost:8080/jsonapi/node/article

(assuming you have the Article content type installed and some articles created).

## Accessing the Site

- **Frontend URL:** http://localhost:8080
- **Admin Dashboard:** http://localhost:8080/user/login
- **Credentials:** Chosen during the Drupal installation wizard.

## Future Production Considerations

- **Managed Hosting**
    - You can deploy these containers to a cloud or VPS. Just install Docker/Compose on your server, clone the repo, and run `docker-compose up -d`.

- **Security & SSL**
    - Use a reverse proxy (e.g., Nginx, Traefik) with HTTPS certificates for a production environment.

- **Database Storage**
    - For production, consider using a managed MySQL service or set up persistent storage that's backed up regularly.

- **Scaling**
    - Depending on traffic, you might move to container orchestration (e.g., Kubernetes, AWS ECS) or a PaaS.

## Troubleshooting

- **Docker container fails to start**
    - Check for port conflicts (e.g., if port 8080 is in use). Change the port mapping in docker-compose.yml.

- **Cannot connect to the database**
    - Confirm the .env or environment variables match your Docker Compose settings.
    - Make sure the database host is set to `db` in Drupal's settings.

- **Permissions issues**
    - On some systems, you may need to adjust file permissions for the `web/sites/default/files` directory.

## License

This project is licensed under the GPL-2.0-or-later license, following Drupal's licensing requirements.
