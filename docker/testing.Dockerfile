############################################################################
# Container for running Codeception tests on a WooGraphQL Docker instance. #
############################################################################

FROM wordpress:beta-6.8-php8.3-apache

LABEL author=jasonbahl
LABEL author_uri=https://github.com/jasonbahl

SHELL [ "/bin/bash", "-c" ]

# Redeclare ARGs and set as environmental variables.
ARG USE_XDEBUG=0
ENV USING_XDEBUG=${USE_XDEBUG}

# Install PHP extensions, Composer, and configure PHP
RUN docker-php-ext-install pdo_mysql \
    && curl -sS https://getcomposer.org/installer -o composer-setup.php \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php \
    && echo "date.timezone = UTC" > /usr/local/etc/php/conf.d/timezone.ini

# Add Composer global binaries to PATH
ENV PATH="/root/.composer/vendor/bin:$PATH"



# Remove exec statement from base entrypoint script if it exists.
RUN if [ -f "app-entrypoint.sh" ]; then \
        sed -i '$d' app-entrypoint.sh; \
        echo ${PWD}\
    else \
        echo "Warning: app-entrypoint.sh not found. Skipping modification."; \
    fi


# Set up working directory and testing entrypoint
WORKDIR /var/www/html/wp-content/plugins/wp-gatsby
COPY docker/testing.entrypoint.sh /usr/local/bin/testing-entrypoint.sh
RUN chmod 755 /usr/local/bin/testing-entrypoint.sh

# Set up Apache
ENTRYPOINT ["testing-entrypoint.sh"]
CMD ["apache2-foreground"]
