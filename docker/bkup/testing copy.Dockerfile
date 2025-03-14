# Dockerfile

FROM wordpress:beta-6.8-php8.3-apache

LABEL author="Joshua Head"
LABEL author_uri="https://github.com/jhead12"

# Set default values for ARGs if not already set
ENV DOCKERIZE_VERSION=v0.6.1

# Set up the working directory
WORKDIR /var/www

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

# Install Composer temporarily
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    composer --version

# Check for dependency conflicts
RUN apt-get check || { echo "Dependency conflicts detected"; exit 1; }

# Install Dockerize
RUN wget -O - https://github.com/jwilder/dockerize/releases/download/${DOCKERIZE_VERSION}/dockerize-linux-amd64-${DOCKERIZE_VERSION}.tar.gz | tar xzf - -C /usr/local/bin && \
    chmod +x /usr/local/bin/dockerize && \
    /usr/local/bin/dockerize --version || { echo "Dockerize installation failed"; exit 1; }

# Install WP-CLI directly
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
    chmod +x wp-cli.phar && \
    mv wp-cli.phar /usr/local/bin/wp

# Verify WP-CLI installation
RUN wp --info

# Ensure WordPress is properly configured
RUN cd /var/www/

COPY docker/configure-wordpress.sh /var/www/configure-wordpress.sh
RUN chmod +x /var/www/configure-wordpress.sh

# Add GitHub SSH key to known hosts
RUN mkdir -p ~/.ssh && \
    ssh-keyscan github.com >> ~/.ssh/known_hosts

# Copy composer.json and install dependencies
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-source && \
    if [ ! -f vendor/bin/codecept ]; then \
        echo "Codeception installation failed"; \
        exit 1; \
    fi


# Create .env file with necessary environment variables
# RUN echo 'DB_HOST=localhost\nDB_PORT=3306\nDB_USER=root\nDB_PASSWORD=wordpress\nDB_NAME=wordpress_tests\nWP_ROOT_FOLDER=/var/www' > /var/www/tests/.env

# Set ServerName to avoid warnings
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copy testing entrypoint script and make it executable
COPY docker/testing.entrypoint.sh /usr/local/bin/testing-entrypoint.sh
RUN chmod +x /usr/local/bin/testing-entrypoint.sh

# Remove exec statement from base entrypoint script if it exists
RUN if [ -f "app-entrypoint.sh" ]; then \
        sed -i '$d' app-entrypoint.sh; \
        echo ${PWD} \
    else \
        echo "Warning: app-entrypoint.sh not found. Skipping modification."; \
    fi

# Expose the web server port
EXPOSE 80

# Set up Apache
ENTRYPOINT ["/usr/local/bin/testing-entrypoint.sh"]
CMD ["apache2-foreground"]