<div align="center" style="margin-bottom: 20px;">
  <img src="https://raw.githubusercontent.com/gatsbyjs/gatsby/master/packages/gatsby-source-wordpress/docs/assets/gatsby-wapuus.png" alt="Wapuu hugging a ball with the Gatsby logo on it" />
  <img src="https://raw.githubusercontent.com/gatsbyjs/wp-gatsby/master/docs/assets/gatsby-wapuus.png" alt="Wapuu hugging a ball with the Gatsby logo on it" />
</div>

<p align="center">
  <a href="https://github.com/gatsbyjs/wp-gatsby/blob/master/license.txt">
    <img src="https://img.shields.io/badge/license-GPLv3-blue.svg" alt="Gatsby and gatsby-source-wordpress are released under the MIT license." />
    <img src="https://img.shields.io/badge/license-GPLv3-blue.svg" alt="WPGatsby is released under the GPLv3 license." />
  </a>
  <a href="https://twitter.com/intent/follow?screen_name=gatsbyjs">
    <img src="https://img.shields.io/twitter/follow/gatsbyjs.svg?label=Follow%20@gatsbyjs" alt="Follow @gatsbyjs" />
  </a>
</p>

# WPGatsby

WPGatsby is a free open-source WordPress plugin that optimizes your WordPress site to work as a data source for [Gatsby](https://www.gatsbyjs.com/docs/how-to/sourcing-data/sourcing-from-wordpress).

This plugin must be used in combination with the npm package [`gatsby-source-wordpress@^4.0.0`](https://www.npmjs.com/package/gatsby-source-wordpress).

## Install and Activation

WPGatsby is available on the WordPress.org repository and can be installed from your WordPress dashboard, or by using any other plugin installation method you prefer, such as installing with Composer from wpackagist.org.

## Plugin Overview

This plugin has 2 primary responsibilities:

- [Monitor Activity in WordPress to keep Gatsby in sync with WP](https://github.com/gatsbyjs/wp-gatsby/blob/master/docs/action-monitor.md)
- [Configure WordPress Previews to work with Gatsby](https://github.com/gatsbyjs/gatsby/blob/master/packages/gatsby-source-wordpress/docs/tutorials/configuring-wp-gatsby.md#setting-up-preview)

Additionally, WPGatsby has a settings page to connect your WordPress site with your Gatsby site:

## Testing Process

To ensure the plugin is working correctly, it should be tested thoroughly before deployment. The following steps can be taken:

1. Install and activate the plugin on a development environment.
2. Configure the plugin to connect with the Gatsby site.
3. Test all functionalities of the plugin, such as monitoring activity and configuring WordPress previews.
4. Check for any errors or issues that may arise during testing.

## Docker Setup

To build and run the Docker containers for your WordPress and Gatsby environment, use the following commands:

1. Build the WordPress image:

    ```bash
    docker-compose build
    ```

2. Start the Docker containers:

    ```bash
    docker-compose up -d
    ```

3. Log into the Terminal of the App Container:

    ```bash
    ssh root@localhost -p 2022
    ```

4. Access the WordPress container shell:

    ```bash
    docker exec -it wp-gatsby-app-1 /bin/bash
    ```

5. Check running Docker containers:

    ```bash
    docker ps
    ```

## Environmental Variables

Ensure you have the following environmental variables set up in your environment or `.env` file:

- `WP_URL` - The URL of your WordPress site.
- `WP_DOMAIN` - The domain of your WordPress site.
- `DB_HOST` - The database host (usually the service name of the MySQL container).
- `DB_NAME` - The name of your WordPress database.
- `DB_USER` - The WordPress database username.
- `DB_PASSWORD` - The WordPress database password.
- `ADMIN_EMAIL` - The admin email address for your WordPress site.
- `ADMIN_USERNAME` - The admin username for your WordPress site.
- `ADMIN_PASSWORD` - The admin password for your WordPress site.
- `INCLUDE_WPGRAPHIQL` - Flag to include the WPGraphiQL plugin.
- `IMPORT_WC_PRODUCTS` - Flag to import WooCommerce products.
- `STRIPE_GATEWAY` - Flag to include Stripe payment gateway support.
- `WPGRAPHQL_VERSION` - The version of the WPGraphQL plugin to install.

Make sure to adjust these variables to suit your setup.

## Licensing

WPGatsby is released under the GPLv3 license. For more information, see the [license file](https://github.com/gatsbyjs/wp-gatsby/blob/master/license.txt).

Follow [@gatsbyjs](https://twitter.com/intent/follow?screen_name=gatsbyjs) on Twitter for updates.

