###############################################################################
# Container for running Codeception tests on a WooGraphQL Docker instance. #
###############################################################################

# FROM php:8.3-apache
FROM wordpress:beta-6.8-php8.3-apache


LABEL author="Joshua Head"
LABEL author_uri="https://github.com/jhead12"

# Set default values for ARGs if not already set
ENV DOCKERIZE_VERSION=v0.6.1

# Install system packages and development libraries
RUN apt-get update && \
    apt-get -y install software-properties-common python3-venv python3-pip wget git ssh tar gzip mariadb-client libpcre3 libpcre3-dev unzip \
    libcurl4-openssl-dev libxml2-dev libzip-dev && \
    rm -rf /var/lib/apt/lists/*

# Add Ondrej's PHP PPA directly
RUN echo "deb http://ppa.launchpad.net/ondrej/php/ubuntu focal main" > /etc/apt/sources.list.d/ondrej-php.list && \
    apt-key adv --keyserver keyserver.ubuntu.com --recv-keys E5267A6C && \
    apt-get update

# Create a virtual environment for Python packages
RUN python3 -m venv /opt/venv && \
    . /opt/venv/bin/activate && \
    pip install --upgrade pip setuptools wheel

# Install PHP extensions using docker-php-ext-install
RUN docker-php-ext-install mysqli pdo_mysql curl dom simplexml zip && \
    docker-php-ext-enable mysqli pdo_mysql curl dom simplexml zip

# Check for dependency conflicts
RUN apt-get check || { echo "Dependency conflicts detected"; exit 1; }

# Install Dockerize
RUN wget -O - https://github.com/jwilder/dockerize/releases/download/${DOCKERIZE_VERSION}/dockerize-linux-amd64-${DOCKERIZE_VERSION}.tar.gz | tar xzf - -C /usr/local/bin && \
    chmod +x /usr/local/bin/dockerize && \
    /usr/local/bin/dockerize --version || { echo "Dockerize installation failed"; exit 1; }

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install WP-CLI using Composer
RUN composer global require wp-cli/wp-cli-bundle

# Install additional PHP extensions
RUN pecl install pcov uopz && \
    docker-php-ext-enable pcov uopz

# Add GitHub SSH key to known hosts
RUN mkdir -p ~/.ssh && \
    ssh-keyscan github.com >> ~/.ssh/known_hosts

# Set up the working directory
WORKDIR /var/www/html

# Copy composer.json and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-source && \
    if [ ! -f vendor/bin/codecept ]; then \
        echo "Codeception installation failed"; \
        exit 1; \
    fi

# Copy additional configuration and test files
COPY docker/codeception.yml /var/www/codeception.dist.yml
COPY tests /var/www/tests

# Remove exec statement from base entrypoint script if it exists
RUN if [ -f "app-entrypoint.sh" ]; then \
        sed -i '$d' app-entrypoint.sh; \
        echo ${PWD} \
    else \
        echo "Warning: app-entrypoint.sh not found. Skipping modification."; \
    fi

# Copy testing entrypoint script and make it executable
# COPY docker/testing.entrypoint.sh /usr/local/bin/testing-entrypoint.sh
RUN chmod 755 /usr/local/bin/testing-entrypoint.sh

# Expose the web server port
EXPOSE 80

# Set up Apache
ENTRYPOINT ["testing.entrypoint.sh"]
CMD ["apache2-foreground"]
