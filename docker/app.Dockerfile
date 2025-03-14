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
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
RUN chmod +x wp-cli.phar
RUN mv wp-cli.phar /usr/local/bin/wp

WORKDIR /var/www/

RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
RUN chmod +x wp-cli.phar
RUN mv wp-cli.phar /usr/local/bin/wp



# Set up Apache
RUN echo 'WPGraphQL localhost' >> /etc/apache2/apache2.conf

# Set up entrypoint
ENTRYPOINT ["app-entrypoint.sh"]
CMD ["apache2-foreground"]