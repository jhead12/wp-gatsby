# docker/app.Dockerfile

###############################################################################
# Pre-configured WordPress Installation w/ WPGraphQL, WPGatsby #
# For testing only, use in production not recommended. #
###############################################################################
# Set default values for ARGs if not already set
ARG WP_VERSION=6.7.2
ARG PHP_VERSION=8.3

# Use a specific base image with the correct tag
# FROM wordpress:${WP_VERSION}-php${PHP_VERSION}-alpine
FROM wordpress:beta-6.8-php8.3-apache

# FROM alpine:latest

LABEL author=joshuahead
LABEL author_uri=https://github.com/jhead12

SHELL [ "/bin/bash", "-c" ]

# Install system packages
RUN apt-get update && \
    apt-get -y install \
    git \
    ssh \
    tar \
    gzip \
    wget \
    mariadb-client \
    && rm -rf /var/lib/apt/lists/*

# Install Dockerize
ENV DOCKERIZE_VERSION=v0.6.1
RUN curl -L "https://github.com/jwilder/dockerize/releases/download/${DOCKERIZE_VERSION}/dockerize-linux-amd64" -o /usr/local/bin/dockerize \
    && chmod +x /usr/local/bin/dockerize

# Install WP-CLI
RUN curl -s -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp


# Set project environmental variables
# ENV WP_ROOT_FOLDER="/var/www/html"
# ENV WORDPRESS_DB_HOST="${WORDPRESS_DB_HOST}"
# ENV WORDPRESS_DB_PASSWORD="${WORDPRESS_DB_PASSWORD}"
# ENV WORDPRESS_DB_NAME="${WORDPRESS_DB_NAME}"
# ENV PLUGINS_DIR="${WP_ROOT_FOLDER}/wp-content/plugins"
# ENV PROJECT_DIR="${PLUGINS_DIR}/wp-gatsby"

# Remove exec statement from base entrypoint script.
WORKDIR /var/www/html
COPY docker/app.entrypoint.sh /usr/local/bin/app-entrypoint.sh
RUN chmod 755 /usr/local/bin/app-entrypoint.sh

# Set up Apache
RUN echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# Set up entrypoint
ENTRYPOINT ["app-entrypoint.sh"]
CMD ["apache2-foreground"]