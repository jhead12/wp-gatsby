#!/usr/bin/env bash

# Source environment variables
source .env.dist || { echo "Error: .env file missing. Ensure it's in the project root."; exit 1; }

# Configuration Section
TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo "$TMPDIR" | sed -e "s/\/$//")
DB_HOST=${TEST_DB_HOST-localhost}
DB_USER=${DB_NAME}
DB_NAME=${TEST_DB_NAME-localhost}
DB_PASS=${TEST_DB_PASSWORD-""}
WP_VERSION=${WP_VERSION-latest}
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${TEST_WP_ROOT_FOLDER-$TMPDIR/wordpress/}
PLUGIN_DIR=$(pwd)
SKIP_DB_CREATE=${SKIP_DB_CREATE-false}

# Print Usage Instructions
print_usage_instruction() {
    echo "Missing or invalid configuration. Ensure that .env exists and run:"
    echo "  composer install-wp-tests"
    exit 1
}

# Validate Required Environment Variables
validate_env_variables() {
    [[ -z "$TEST_DB_NAME" ]] && { echo "TEST_DB_NAME not found"; print_usage_instruction; }
    [[ -z "$TEST_DB_USER" ]] && { echo "TEST_DB_USER not found"; print_usage_instruction; }
    DB_NAME=$TEST_DB_NAME
    DB_USER=$TEST_DB_USER
}

# Download Helper Function
download() {
    if command -v curl > /dev/null; then
        curl -s "$1" -o "$2"
    elif command -v wget > /dev/null; then
        wget -nv -O "$2" "$1"
    else
        echo "Error: Neither curl nor wget is available for downloading."
        exit 1
    fi
}

# Determine WordPress Test Tag
determine_wp_test_tag() {
    case "$WP_VERSION" in
        nightly|trunk)
            WP_TESTS_TAG="trunk"
            ;;
        *-beta|*-RC*)
            WP_BRANCH=${WP_VERSION%-*}
            WP_TESTS_TAG="branches/$WP_BRANCH"
            ;;
        [0-9]*.[0-9]*.[0])
            WP_TESTS_TAG="tags/${WP_VERSION%??}"
            ;;
        [0-9]*.[0-9]*)
            WP_TESTS_TAG="branches/$WP_VERSION"
            ;;
        *)
            download http://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
            LATEST_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | sed 's/"version":"//')
            WP_TESTS_TAG="tags/$LATEST_VERSION"
            ;;
    esac
}

# Install WordPress Core
install_wordpress() {
    [[ -d $WP_CORE_DIR ]] && return
    mkdir -p "$WP_CORE_DIR"

    case "$WP_VERSION" in
        nightly|trunk)
            TMPDIR_NIGHTLY="$TMPDIR/wordpress-nightly"
            mkdir -p "$TMPDIR_NIGHTLY"
            download https://wordpress.org/nightly-builds/wordpress-latest.zip "$TMPDIR_NIGHTLY/wordpress-nightly.zip"
            unzip -q "$TMPDIR_NIGHTLY/wordpress-nightly.zip" -d "$TMPDIR_NIGHTLY"
            mv "$TMPDIR_NIGHTLY/wordpress/"* "$WP_CORE_DIR"
            ;;
        latest|[0-9]*)
            ARCHIVE_NAME="wordpress-${WP_VERSION-latest}"
            download https://wordpress.org/${ARCHIVE_NAME}.tar.gz "$TMPDIR/wordpress.tar.gz"
            tar --strip-components=1 -zxf "$TMPDIR/wordpress.tar.gz" -C "$WP_CORE_DIR"
            ;;
    esac
    download https://raw.githubusercontent.com/markoheijnen/wp-mysqli/master/db.php "$WP_CORE_DIR/wp-content/db.php"
}

# Install Database
install_db() {
    [[ "$SKIP_DB_CREATE" == "true" ]] && return
    mysql -u "$DB_USER" --password="$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;"
}

# Configure WordPress
configure_wordpress() {
    cd "$WP_CORE_DIR" || exit
    wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbhost="$DB_HOST" --skip-check --force
    wp core install --url="wp.test" --title="WPGatsby Tests" --admin_user="admin" --admin_password="password" --admin_email="admin@wp.test"
    wp rewrite structure '/%year%/%monthnum%/%postname%/'
}

# Setup Plugins
setup_plugins() {
    # WPGraphQL Plugin
    wp plugin install https://github.com/wp-graphql/wp-graphql/releases/latest/download/wp-graphql.zip --activate

    # Local Plugin
    ln -s "$PLUGIN_DIR" "$WP_CORE_DIR/wp-content/plugins/wp-gatsby"
    wp plugin activate wp-gatsby
    wp rewrite flush
    wp db export "$PLUGIN_DIR/tests/_data/dump.sql"
}

# Main Function
main() {
    validate_env_variables
    determine_wp_test_tag
    install_wordpress
    install_db
    configure_wordpress
    setup_plugins
}

main
