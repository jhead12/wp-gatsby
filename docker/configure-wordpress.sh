#!/bin/bash

WP_PATH=$WP_ROOT_FOLDER
DB_NAME=$DB_NAME # Database name
DB_Host=$DB_HOST # Database host
 

# Ensure the WordPress root folder exists
if [ ! -d "$WP_PATH" ]; then
    echo "Creating WP_ROOT_FOLDER: ${WP_PATH}"
    mkdir -p "$WP_PATH"
fi

# Check if WordPress is installed by looking for wp-config-sample.php
if [ ! -f "$WP_PATH/wp-config-sample.php" ]; then
    echo "Downloading WordPress core files..."
    wp core download --path=$WP_PATH --allow-root
fi

# Generate wp-config.php if it does not exist
if [ ! -f "$WP_PATH/wp-config.php" ]; then
    echo "Generating wp-config.php..."
    wp config create --dbname=$DB_NAME --dbuser=wordpress --dbpass=wordpress --dbhost=$DB_Host --path=$WP_PATH --force --allow-root
fi

# Verify that wp-config.php exists
if [ -f "$WP_PATH/wp-config.php" ]; then
    echo "wp-config.php generated successfully."
else
    echo "Failed to generate wp-config.php."
    exit 1
fi