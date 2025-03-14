#!/bin/bash

# Exit immediately on error, unset variable, or pipe failure.
set -euo pipefail

# Define necessary variables
DOCKERIZE_VERSION=v0.9.2
name="wp-gatsby"
PROJECT_DIR=/var/www
WP_ROOT_FOLDER=${WP_ROOT_FOLDER:-/var/www/html/wordpress}
TESTS_OUTPUT=${TESTS_OUTPUT:-/var/www/tests/_output}

 # Define necessary variables
COMPOSER_VERSION=2.1.8
COMPOSER_SETUP_URL="https://getcomposer.org/installer"
COMPOSER_SIG_URL="https://composer.github.io/installer.sig"
# Function to install PHP extensions
install_php_extensions() {
    echo "Installing PHP extensions..."
    docker-php-ext-install mysqli pdo_mysql curl dom simplexml zip && \
    docker-php-ext-enable mysqli pdo_mysql curl dom simplexml zip

    echo "Installing additional PHP extensions..."
    pecl install pcov uopz && \
    docker-php-ext-enable pcov uopz
}

# Function to download c3.php
download_c3_php() {
    echo "Downloading c3.php..."
    curl -sS "https://raw.githubusercontent.com/wp-cli/wp-cli/v2.5.0/utils/wp-cron/cron-runner.php" -o "${PROJECT_DIR}/c3.php"
}




# Function to install Composer securely
install_composer() {
    echo "Downloading Composer installer..."
    curl -sS "$COMPOSER_SETUP_URL" -o composer-setup.php

    echo "Downloading expected Composer signature..."
    EXPECTED_SIGNATURE=$(curl -sS "$COMPOSER_SIG_URL")

    echo "Verifying Composer installer signature..."
    ACTUAL_SIGNATURE=$(php -r "echo hash_file('SHA384', 'composer-setup.php');")

    if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]; then
        rm composer-setup.php
        log_error "Invalid installer checksum"
    fi

    echo "Checksum verified. Installing Composer..."
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer

    echo "Cleaning up..."
    rm composer-setup.php

    echo "Composer installed successfully."
}

# Function to increase PHP memory limit
increase_php_memory_limit() {
    echo "Increasing PHP memory limit..."
    echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/memory-limit.ini
}


# Function to log errors and exit gracefully
log_error() {
    echo "[Error]: $1" | tee -a /var/log/test-script.log
    exit 1
}

# Print environment variables and paths
print_vars() {
    echo "PROJECT_DIR: ${PROJECT_DIR}"
    echo "WP_ROOT_FOLDER: ${WP_ROOT_FOLDER}"
    echo "TESTS_OUTPUT: ${TESTS_OUTPUT}"
    echo "DB_HOST: ${DB_HOST}"
    echo "DB_USER: ${DB_USER}"
    echo "DB_PASSWORD: ${DB_PASSWORD}"
    echo "DB_NAME: ${DB_NAME}"
}

# Function to create wp-config.php manually
create_wp_config() {
    cat <<EOF > "${WP_ROOT_FOLDER}/wp-config.php"
<?php
define('DB_NAME', '${DB_NAME}');
define('DB_USER', '${DB_USER}');
define('DB_PASSWORD', '${DB_PASSWORD}');
define('DB_HOST', '${DB_HOST}');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');
\$table_prefix = 'wp_';
define('WP_DEBUG', false);
if ( ! defined('ABSPATH') ) {
    define('ABSPATH', dirname(__FILE__) . '/');
}
require_once(ABSPATH . 'wp-settings.php');
EOF

    # Verify manual creation of wp-config.php
    if [ -f "${WP_ROOT_FOLDER}/wp-config.php" ]; then
        echo "wp-config.php created manually."
    else
        log_error "Failed to create wp-config.php manually."
    fi
}

# Trap unexpected errors
trap 'log_error "Script failed unexpectedly at line $LINENO."' ERR

# Function to install Dockerize if missing
install_dockerize() {
    if ! command -v dockerize &> /dev/null; then
        echo "Dockerize not found. Installing..."
        mkdir -p /usr/local/bin && chmod 755 /usr/local/bin
        wget -O - "https://github.com/jwilder/dockerize/releases/download/${DOCKERIZE_VERSION}/dockerize-linux-amd64-${DOCKERIZE_VERSION}.tar.gz" | tar xzf - -C /usr/local/bin
        chmod +x /usr/local/bin/dockerize
        /usr/local/bin/dockerize --version || log_error "Dockerize installation failed"
    else
        echo "Dockerize is already installed and ready to use."
    fi

    # Verify Dockerize installation
    if ! dockerize --version; then
        log_error "Dockerize is installed but not functioning correctly."
    fi
}

# Set up and install WordPress using WP-CLI
setup_wordpress() {
    echo "Setting up WordPress using WP-CLI..."
    # Print environment variables
    print_vars



    # Ensure WP_ROOT_FOLDER exists
    if [ ! -d "$WP_ROOT_FOLDER" ]; then
        echo "Creating WP_ROOT_FOLDER: ${WP_ROOT_FOLDER}"
        mkdir -p "$WP_ROOT_FOLDER"
    fi

    # Navigate to the WordPress root folder
    cd /var/www
    echo "Current working directory after cd: $(pwd)"
    # Run WordPress configuration script

    ./configure-wordpress.sh

    cd /var/www/html/wordpress

    # List contents to confirm correct path
    echo "Contents of WP_ROOT_FOLDER: $(ls -la)"

    # Download WordPress
    if ! wp core download --path="$WP_ROOT_FOLDER" --allow-root; then
        log_error "Failed to download WordPress."
    fi

    echo "WordPress downloaded successfully."
    echo "Contents of WP_ROOT_FOLDER after download: $(ls -la)"


    echo "wp-config.php created successfully."
    echo "Contents of WP_ROOT_FOLDER after config creation: $(ls -la)"

    # Install WordPress
    if ! wp core install --url="wp.test" --title="WPGatsby Tests" --admin_user="admin" --admin_password="password" --admin_email="dev-email@wpengine.local" --path="$WP_ROOT_FOLDER" --allow-root; then
        log_error "Failed to install WordPress."
    fi

    echo "WordPress installed successfully."
}

# Function to check and clean coverage.xml
clean_coverage_file() {
    if [[ -f "${TESTS_OUTPUT}/coverage.xml" ]] && [[ -n "${COVERAGE:-}" ]]; then
        echo "Cleaning coverage.xml for deployment."
        pattern="${PROJECT_DIR}/"
        sed -i.bak "s~$pattern~~g" "${TESTS_OUTPUT}/coverage.xml"
    fi
}

# Function to install dependencies
install_dependencies() {
    echo "Installing dependencies"
    if [ ! -f /var/www/composer.json ]; then
        cat << 'EOF' > /var/www/composer.json
{
    "name": "gatsbyjs/wp-gatsby",
    "description": "Optimize your WordPress site as a source for Gatsby site(s)",
    "type": "wordpress-plugin",
    "license": "GPL-3.0-or-later",
    "authors": [
        {
            "name": "GatsbyJS"
        },
        {
            "name": "Jason Bahl"
        },
        {
            "name": "Tyler Barnes"
        },
        {
            "name": "Joshua Head"
        }
    ],
    "repositories": [
        {
            "type": "composer",
            "url": "https://wpackagist.org",
            "only": [
                "wpackagist-plugin/*",
                "wpackagist-theme/*"
            ]
        }
    ],
    "autoload": {
        "psr-4": {
            "WPGatsby\\": "./src/"
        }
    },
    "autoload-dev": {
        "files": [
            "tests/_data/config.php"
        ]
    },
    "config": {
        "optimize-autoloader": true,
        "process-timeout": 0,
        "allow-plugins": {
            "dealerdirect/phpcodesniffer-composer-installer": true
        }
    },
    "require": {
        "php": "^7.3 || ^8.0",
        "firebase/php-jwt": "^5.2",
        "ircmaxell/random-lib": "^1.2",
        "composer/semver": "^1.5",
        "symfony/yaml": "^5.4"
    },
    "require-dev": {
        "wp-graphql/wp-graphql-testcase": "^2.0.0",
        "phpunit/phpunit": "9.4.1"
    },
    "extra": {
        "installer-paths": {
            "wp-content/plugins/wp-gatsby/": [
                "type:wordpress-plugin"
            ]
        }
    }
}
EOF
    fi

    # Install dependencies using Composer
    COMPOSER_MEMORY_LIMIT=-1 composer update --prefer-source --no-interaction
    COMPOSER_MEMORY_LIMIT=-1 composer install --prefer-source --no-interaction

      # Verify Codeception installation
    if [ ! -f /var/www/vendor/bin/codecept ]; then
        log_error "Codeception is not installed correctly."
    fi
}



# Function to run Codeception tests
run_tests() {
    echo "Running Tests"
    local coverage=""
    local debug=""

    if [[ -n "${COVERAGE:-}" ]]; then
        coverage="--coverage --coverage-xml"
    fi
    if [[ -n "${DEBUG:-}" ]]; then
        debug="--debug"
    fi

    local suites=${1:-" ;"}
    IFS=';' read -ra target_suites <<< "$suites"
    for suite in "${target_suites[@]}"; do
        /var/www/vendor/bin/codecept run -c /var/www/html/codeception.dist.yml ${suite} ${coverage} ${debug} --no-exit 2>&1 | tee -a test-results.log
    done
}

# Main Function
main() {
    # # Move to WordPress root folder
    # local workdir="$PWD"
    # echo "Moving to WordPress root directory."
    # cd "${WP_ROOT_FOLDER}"

    # Run app entry point script if it exists
    if [ -f "app-entrypoint.sh" ]; then
        echo "Running app entrypoint script."
        chmod +x app-entrypoint.sh
        ./app-entrypoint.sh
    fi

    # Return to working directory
    cd "/var/www"
    echo "Returned to project working directory."

   # Set up WordPress
    setup_wordpress

    # Install Dockerize and wait for services
    install_dockerize

    dockerize \
        -wait tcp://${DB_HOST}:${DB_HOST_PORT:-3306} \
        -timeout 1m || log_error "Dockerize failed to wait for services."

    # Download c3.php
    download_c3_php

    # Create Codeception configuration
    create_codeception_config

    # Install dependencies
    install_dependencies

    # Install Composer if missing
    install_composer

    # Install WP-CLI using Composer
    # install_wp_cli

    # Set output permissions
    if [ -d "${TESTS_OUTPUT}" ]; then
        chmod 755 -R "${TESTS_OUTPUT}" || log_error "Failed to set permissions for ${TESTS_OUTPUT}."
    else
        mkdir -p "${TESTS_OUTPUT}"
        chmod 755 "${TESTS_OUTPUT}"
        echo "Created and set permissions for ${TESTS_OUTPUT}."
    fi

    # Run Codeception tests
    run_tests "${SUITES:-}"

    # Remove temporary files (e.g., c3.php)
    if [ -f "${PROJECT_DIR}/c3.php" ] && [[ "${SKIP_TESTS_CLEANUP:-}" != "1" ]]; then
        rm -f "${PROJECT_DIR}/c3.php"
        echo "Removed temporary c3.php."
    fi

    # Clean coverage files
    clean_coverage_file

    # Remove development dependencies if cleanup is enabled
    if [[ -z "${SKIP_TESTS_CLEANUP:-}" ]]; then
        composer config --global discard-changes true
        composer install --no-dev -n || log_error "Failed to remove dev dependencies."
        rm -f composer.lock
    fi

    # Start Apache in the foreground
    if ! apache2-foreground; then
        log_error "Failed to start Apache in foreground mode."
    fi
}

# Execute main function
main
