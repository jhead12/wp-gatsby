# Manual for Using Docker Scripts and Setup

## Introduction

This manual will guide you through using the provided Docker setup and scripts to build and run a WordPress installation or automated tests.

## Prerequisites

Before you begin, ensure you have the following installed on your system:

- Docker
- Docker Compose
- Composer

## Setup Instructions

### Step 1: Clone the Repository

```sh
git clone https://github.com/your-repo.git
cd your-repo
```

### Step 2: Install Composer Dependencies

```sh
composer install --no-dev
```

### Step 3: Prepare Environment File

Ensure the `.env.dist` file exists in the repository root. This file contains the necessary environment variables for the Docker setup.

## Using the Bash Script

Script File: `build-and-run.sh`

Command: `composer build-and-run -- [options]`

Options:

- `-a`: Spin up a WordPress installation.
- `-t`: Run the automated tests.
- `-e path/to/custom.env`: Use a custom environment file.

Example Usage:

1. **Build and Run WordPress Installation:**

```sh
composer build-and-run -- -a
```

2. **Build and Run Automated Tests:**

```sh
composer build-and-run -- -t
```

3. **Using a Custom Environment File:**

```sh
composer build-and-run -- -e path/to/custom.env -a
```

## Additional Docker Functions

### Log into the Terminal of the App Container

To access the terminal of the running app container and customize it further, use the following command:

```sh
docker exec -it <container_name> /bin/bash
```

Replace `<container_name>` with the actual name or ID of the running container.

Example:

1. **List all running containers to find the container name or ID:**

```sh
docker ps
```

2. **Access the terminal of the app container:**

```sh
docker exec -it app_container /bin/bash
```

### Customizing the App Container

Once inside the container, you can perform various tasks such as:

- Installing additional packages
- Modifying configuration files
- Running commands

Example Commands Inside the Container:

1. **Install additional packages:**

```sh
apt-get update && apt-get install -y nano
```

2. **Modify configuration files:**

```sh
nano /path/to/config/file
```

3. **Restart services:**

```sh
service apache2 restart
```

## Detailed Steps and Prompts

### Prompt 1: Check if Docker is Installed

If Docker is not installed, the script will prompt:


### Prompt 2: Check if Docker Daemon is Running

If the Docker daemon is not running, the script will prompt:


### Prompt 3: Build Docker Images

When running the build command with the `-a` or `-t` option, the script will build the necessary Docker images:


### Prompt 4: Run Docker Containers

When running the run command with the `-a` or `-t` option, the script will start the Docker containers:


### Prompt 5: Error Handling

If any errors occur, such as missing environment files or incorrect paths, the script will provide appropriate error messages.

## Dockerfile for WordPress Installation

The `docker/app.Dockerfile` contains the configuration for the WordPress installation with WPGraphQL and WPGatsby. Below is a summary of key sections:

### Base Image:

```Dockerfile
FROM wordpress:beta-6.8-php8.3-apache


### Prompt 5: Error Handling

If any errors occur, such as missing environment files or incorrect paths, the script will provide appropriate error messages.

## Dockerfile for WordPress Installation

The `docker/app.Dockerfile` contains the configuration for the WordPress installation with WPGraphQL and WPGatsby. Below is a summary of key sections:

### Base Image:

```Dockerfile
FROM wordpress:beta-6.8-php8.3-apache
