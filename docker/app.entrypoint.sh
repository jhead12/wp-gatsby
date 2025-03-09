#!/bin/bash

# Run WordPress docker entrypoint.
. docker-entrypoint.sh 'apache2'

set +u

# Ensure MySQL is loaded.
dockerize -wait tcp://${DB_HOST}:${DB_HOST_PORT:-3306} -timeout 1m || exit 1

echo "MySQL service is up and running."

# Config WordPress.
if [ ! -f "${WP_ROOT_FOLDER}/wp-config.php" ]; then
    echo "Creating wp-config.php file."
    wp config create \
        --path="${WP_ROOT_FOLDER}" \
        --dbname="${DB_NAME}" \
        --dbuser="${DB_USER}" \
        --dbpass="${DB_PASSWORD}" \
        --dbhost="${DB_HOST}" \
        --dbprefix="${WP_TABLE_PREFIX}" \
        --skip-check \
        --quiet \
        --allow-root || exit 1
    echo "wp-config.php file created."
fi

# Install WP if not yet installed.
if ! wp core is-installed --allow-root; then
    echo "Installing WordPress core."
    wp core install \
        --path="${WP_ROOT_FOLDER}" \
        --url="${WP_URL}" \
        --title='Test' \
        --admin_user="${ADMIN_USERNAME}" \
        --admin_password="${ADMIN_PASSWORD}" \
        --admin_email="${ADMIN_EMAIL}" \
        --allow-root || exit 1
    echo "WordPress core installed."
fi

# Install and activate WPGraphQL.
if [ ! -f "${PLUGINS_DIR}/wp-graphql/wp-graphql.php" ]; then
    echo "Installing and activating WPGraphQL plugin from version ${WPGRAPHQL_VERSION}."
    wp plugin install \
        https://github.com/wp-graphql/wp-graphql/archive/${WPGRAPHQL_VERSION}.zip \
        --activate --allow-root || exit 1
    echo "WPGraphQL plugin installed and activated."
else
    echo "Activating WPGraphQL plugin."
    wp plugin activate wp-graphql --allow-root
    echo "WPGraphQL plugin activated."
fi

# Install and activate WPGatsby.
echo "Activating WPGatsby plugin."
wp plugin activate wp-gatsby --allow-root
echo "WPGatsby plugin activated."

# Set pretty permalinks.
echo "Setting pretty permalinks structure."
wp rewrite structure '/%year%/%monthnum%/%postname%/' --allow-root || exit 1
echo "Pretty permalinks set."

# Export the database.
echo "Exporting the database to ${PROJECT_DIR}/tests/_data/dump.sql"
wp db export "${PROJECT_DIR}/tests/_data/dump.sql" --allow-root || exit 1
echo "Database exported successfully."

# Add test logic to verify functionality.
echo "Running WP-CLI test to verify functionality."
if wp core is-installed --allow-root; then

    echo "WordPress installation verified successfully."
else
    echo "Error: WordPress installation verification failed."
    exit 1
fi

if wp plugin is-active wp-graphql --allow-root; then
    composer require wpackagist-plugin/wp-graphql

    echo "WPGraphQL plugin is active."
else
    echo "Error: WPGraphQL plugin is not active."
    exit 1
fi

if wp plugin is-active wp-gatsby --allow-root; then
    echo "WPGatsby plugin is active."
else
    echo "Error: WPGatsby plugin is not active."
    exit 1
fi

# Proceed with command execution.
exec "$@"
