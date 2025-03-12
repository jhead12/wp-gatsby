#!/bin/bash

# Exit immediately on error, unset variable, or pipe failure.
set -euo pipefail

# Define necessary variables
DOCKERIZE_VERSION=v0.9.2
name="wp-gatsby"
PROJECT_DIR=/var/www
WP_ROOT_FOLDER=${WP_ROOT_FOLDER:-/var/www/html}
TESTS_OUTPUT=${TESTS_OUTPUT:-/var/www/tests/_output}

# Function to log errors and exit gracefully
log_error() {
    echo "[Error]: $1" | tee -a /var/log/test-script.log
    exit 1
}

# Trap unexpected errors
trap 'log_error "Script failed unexpectedly at line $LINENO."' ERR

# Function to compare version numbers
version_gt() {
    test "$(printf '%s\n' "$@" | sort -V | head -n 1)" != "$1"
}

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

# Function to install Composer if missing
install_composer() {
    if ! command -v composer &> /dev/null; then
        echo "Composer not found. Installing..."
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
        chmod +x /usr/local/bin/composer
        composer --version || log_error "Composer installation failed"
    else
        echo "Composer is already installed and ready to use."
    fi

    # Verify Composer installation
    if ! composer --version; then
        log_error "Composer is installed but not functioning correctly."
    fi
}

# Function to install WP-CLI using Composer
install_wp_cli() {
    echo "Installing WP-CLI..."
    composer global require wp-cli/wp-cli-bundle
    export PATH="/root/.composer/vendor/bin:${PATH}"
}

# Function to download c3.php for testing
download_c3_php() {
    if [ ! -f "${PROJECT_DIR}/c3.php" ]; then
        echo "Downloading Codeception's c3.php"
        curl -fsSL 'https://raw.githubusercontent.com/Codeception/c3/2.0/c3.php' -o "${PROJECT_DIR}/c3.php" || log_error "Failed to download c3.php."
    else
        echo "c3.php already exists."
    fi
}

# Function to create codeception.dist.yml
create_codeception_config() {
    echo "Creating codeception.dist.yml"
    cat << 'EOF' > /var/www/codeception.dist.yml
actor: Tester
paths:
    tests: tests
    log: tests/_log
    data: tests/_data
    support: tests/_support
settings:
    bootstrap: _bootstrap.php
    suite_class: \Codeception\Test\Unit
    modules:
    enabled:
        WPLoader:
            wpRootFolder: "/var/www"  # Path to WordPress
            dbName: "wordpress_tests"     # Test database name
            dbHost: "localhost:3306"      # Database host
            dbUser: "root"                # Database user
            dbPassword: "wordpress"       # Database password

EOF
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
        /var/www/vendor/bin/codecept run -c /var/www/codeception.dist.yml ${suite} ${coverage} ${debug} --no-exit 2>&1 | tee -a test-results.log
    done
}
    # Start Apache in the foreground
if ! apache2-foreground; then
    log_error "Failed to start Apache in foreground mode."
fi


# Main Function
main() {
    # Move to WordPress root folder
    local workdir="$PWD"
    echo "Moving to WordPress root directory."
    cd "${WP_ROOT_FOLDER}"

    # Run app entry point script if it exists
    if [ -f "app-entrypoint.sh" ]; then
        echo "Running app entrypoint script."
        chmod +x app-entrypoint.sh
        ./app-entrypoint.sh
    fi

    # Return to working directory
    cd "/var/www"
    echo "Returned to project working directory."
       # Start the web server
    if ! apache2-foreground; then
        log_error "Failed to start Apache in foreground mode."
    fi


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
    install_wp_cli

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
}

# Execute main function
main
