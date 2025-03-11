###############################################################################
# Container for running Codeception tests on a WooGraphQL Docker instance. #
###############################################################################

FROM php:8.3-apache

# Set default values for ARGs if not already set
ENV DOCKERIZE_VERSION=v0.9.2
LABEL author="Joshua Head"
LABEL author_uri="https://github.com/jhead12"

# Install system packages
RUN apt-get update && \
    apt-get -y install git ssh tar gzip wget mariadb-client libpcre3 libpcre3-dev && \
    rm -rf /var/lib/apt/lists/*

# Install Dockerize
RUN wget -O - https://github.com/jwilder/dockerize/releases/download/${DOCKERIZE_VERSION}/dockerize-linux-amd64-${DOCKERIZE_VERSION}.tar.gz | tar xzf - -C /usr/local/bin && \
    chmod +x /usr/local/bin/dockerize

# Verify Dockerize installation
RUN /usr/local/bin/dockerize --version || { echo "Dockerize installation failed"; exit 1; }

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    chmod +x /usr/local/bin/composer && \
    composer --version || { echo "Composer installation failed"; exit 1; }

# Install WP-CLI using Composer
RUN composer global require wp-cli/wp-cli-bundle

# Install pecl and PHP extensions
RUN pecl install pcov uopz && \
    docker-php-ext-enable pcov uopz

# Set up working directory for the WordPress plugin
WORKDIR /var/www/

# Copy composer.json to the working directory
COPY docker/composer.json /var/www/composer.json

# Copy Conception codeception.dist.yml
COPY docker/codeception.yml /var/www/codeception.dist.yml

# Create the tests directory and copy its contents
COPY tests /var/www/tests

# Install Codeception dependencies and verify installation
RUN composer install --prefer-source --no-interaction && \
    if [ ! -f /var/www/vendor/bin/codecept ]; then \
        echo "Codeception installation failed"; \
        exit 1; \
    fi

# Remove exec statement from base entrypoint script if it exists
RUN if [ -f "app-entrypoint.sh" ]; then \
        sed -i '$d' app-entrypoint.sh; \
        echo ${PWD} \
    else \
        echo "Warning: app-entrypoint.sh not found. Skipping modification."; \
    fi

# Copy testing entrypoint script and make it executable
COPY docker/testing.entrypoint.sh /usr/local/bin/testing-entrypoint.sh
RUN chmod 755 /usr/local/bin/testing-entrypoint.sh

# Set up Apache
ENTRYPOINT ["testing-entrypoint.sh"]
CMD ["apache2-foreground"]
