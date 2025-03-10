#!/bin/bash

# Load WordPress docker entrypoint
. app-entrypoint.sh 'apache2'

set +u

# Ensure MySQL is running
dockerize -wait tcp://${DB_HOST}:${DB_HOST_PORT:-3306} -timeout 1m || exit 1
echo "MySQL service is up and running."

configure_wp() {
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
}

install_wp() {
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
}

install_plugin() {
    local plugin_url=$1
    local plugin_name=$2
    echo "Installing $plugin_name plugin."
    wp plugin install $plugin_url --allow-root || exit 1
    echo "$plugin_name plugin installed."
}

activate_plugin() {
    local plugin_name=$1
    echo "Activating $plugin_name plugin."
    wp plugin activate $plugin_name --allow-root || exit 1
    echo "$plugin_name plugin activated."
}

test_graphql() {
    echo "Testing WPGraphQL functionality..."
    GRAPHQL_QUERY_RESULT=$(curl -s -o /dev/null -w "%{http_code}" "${WP_URL}/graphql")
    if [[ "$GRAPHQL_QUERY_RESULT" -eq 200 ]]; then
        echo "GraphQL endpoint is responding correctly."
    else
        echo "Error: GraphQL endpoint is not responding. Please check your setup."
        exit 1
    fi
}

set_pretty_permalinks() {
    echo "Setting pretty permalinks structure."
    wp rewrite structure '/%year%/%monthnum%/%postname%/' --allow-root || exit 1
    echo "Pretty permalinks set."
}

export_db() {
    echo "Exporting the database to ${PROJECT_DIR}/tests/_data/dump.sql"
    wp db export "${PROJECT_DIR}/tests/_data/dump.sql" --allow-root || exit 1
    echo "Database exported successfully."
}

# Configure WordPress
if [ ! -f "${WP_ROOT_FOLDER}/wp-config.php" ]; then
    configure_wp
fi

# Install WordPress if not already installed
if ! wp core is-installed --allow-root; then
    install_wp
fi

# Install and activate WPGraphQL
if ! wp plugin is-installed wp-graphql --allow-root; then
    install_plugin "https://github.com/wp-graphql/wp-graphql/archive/${WPGRAPHQL_VERSION}.zip" "WPGraphQL"
fi

if ! wp plugin is-active wp-graphql --allow-root; then
    activate_plugin "wp-graphql"
else
    echo "WPGraphQL is already active."
fi

# Test WPGraphQL functionality
test_graphql

# Install and activate WPGatsby
if ! wp plugin is-active wp-gatsby --allow-root; then
    activate_plugin "wp-gatsby"
else
    echo "WPGatsby is already active."
fi

# Set pretty permalinks
set_pretty_permalinks

# Export the database
export_db

# Proceed with command execution
exec "$@"
