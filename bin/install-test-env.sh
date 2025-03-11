#!/bin/bash

# Ensure necessary directories exist
mkdir -p "$TMPDIR" "$WP_CORE_DIR"

# Function to download files with retries
download() {
    local url=$1
    local destination=$2
    for i in {1..3}; do
        curl --fail --silent --show-error --location --output "$destination" "$url"
        if [ $? -eq 0 ]; then
            break
        fi
        echo "Download failed, retrying ($i/3)..."
    done
}

# Install WordPress Core
install_wordpress() {
    case $WP_VERSION in
        nightly)
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

# Main script execution
install_wordpress
install_db
configure_wordpress