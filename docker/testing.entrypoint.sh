#!/bin/bash

# Exit immediately on error, unset variable, or pipe failure.
set -euo pipefail

# Function to log errors and exit gracefully
log_error() {
    echo "[Error]: $1" | tee -a /var/log/test-script.log
    exit 1
}

# Trap any unexpected errors.
trap 'log_error "Script failed unexpectedly at line $LINENO."' ERR

# Function to install Dockerize if missing
install_dockerize() {
    if ! command -v dockerize &> /dev/null; then
        echo "Dockerize not found. Installing..."
        local download_url="https://github.com/jwilder/dockerize/releases/download/v0.9.2/"
        case "$(uname -s)" in
            Linux) curl -fsSL "${download_url}/dockerize-linux-amd64-v0.9.2.tar.gz" -o /usr/local/bin/dockerize ;;
            Darwin) curl -fsSL "${download_url}/dockerize-darwin-amd64-v0.9.2.tar.gz" -o /usr/local/bin/dockerize ;;
            *) log_error "Unsupported OS. Please install Dockerize manually." ;;
        esac
        chmod +x /usr/local/bin/dockerize
        if ! command -v dockerize &> /dev/null; then
            log_error "Failed to install Dockerize."
        else
            echo "Dockerize successfully installed."
        fi
    else
        echo "Dockerize is already installed and ready to use."
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
        vendor/bin/codecept run -c codeception.dist.yml ${suite} ${coverage} ${debug} --no-exit 2>&1 | tee -a test-results.log
    done
}

# Function to write WordPress .htaccess
write_htaccess() {
    echo "<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
SetEnvIf Authorization \"(.*)\" HTTP_AUTHORIZATION=\$1
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>" > "${WP_ROOT_FOLDER}/.htaccess"
    echo ".htaccess file written."
}

# Function to check and clean coverage.xml
clean_coverage_file() {
    if [[ -f "${TESTS_OUTPUT}/coverage.xml" ]] && [[ -n "${COVERAGE:-}" ]]; then
        echo "Cleaning coverage.xml for deployment."
        pattern="${PROJECT_DIR}/"
        sed -i.bak "s~$pattern~~g" "${TESTS_OUTPUT}/coverage.xml"
    fi
}

# Move to WordPress root folder
workdir="$PWD"
echo "Moving to WordPress root directory."
cd "${WP_ROOT_FOLDER}"

# Run app entry point script
if [ -f app-entrypoint.sh  ]; then
    . app-entrypoint.sh
else
    echo  "ls -la ${PWD}"
    log_error "App entrypoint script not found."
fi

write_htaccess

# Return to working directory
cd "${workdir}"
echo "Returned to project working directory."

# Ensure Apache is running
service apache2 start || log_error "Failed to start Apache."

# Install and ensure Dockerize is running
install_dockerize

dockerize \
    -wait tcp://${DB_HOST}:${DB_HOST_PORT:-3306} \
    -wait "${WP_URL}" \
    -timeout 1m || log_error "Dockerize failed to wait for services."

# Download c3.php for testing
if [ ! -f "${PROJECT_DIR}/c3.php" ]; then
    echo "Downloading Codeception's c3.php"
    curl -fsSL 'https://raw.githubusercontent.com/Codeception/c3/2.0/c3.php' -o "${PROJECT_DIR}/c3.php" || log_error "Failed to download c3.php."
fi

# Install dependencies
COMPOSER_MEMORY_LIMIT=-1 composer update --prefer-source --no-interaction
COMPOSER_MEMORY_LIMIT=-1 composer install --prefer-source --no-interaction

# Install pcov/clobber if PHP 8.1+ and Coverage enabled
if version_gt "${PHP_VERSION}" "8.1" && [[ -n "${COVERAGE:-}" ]] && [[ -z "${USING_XDEBUG:-}" ]]; then
    echo "Installing pcov/clobber"
    COMPOSER_MEMORY_LIMIT=-1 composer require --dev pcov/clobber
    vendor/bin/pcov clobber || log_error "Failed to configure pcov."
elif [[ -n "${COVERAGE:-}" ]]; then
    echo "Using XDebug for code coverage."
fi

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
    echo "Cleaned up composer.lock and dev dependencies."
fi

# Check results and exit accordingly
if [ -f "${TESTS_OUTPUT}/failed" ]; then
    log_error "Some tests failed. Check the logs for details."
else
    echo "Woohoo! All tests passed successfully!"
    exit 0
fi
