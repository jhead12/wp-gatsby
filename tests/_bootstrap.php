<?php

// Load any necessary environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Load WordPress functions
require '/var/www/html/wordpress/wp-content/wp-load.php';
require '/var/www/vendor/autoload.php';
require '/var/www/html/wordpress/wp-load.php';


// Define any additional configurations or constants needed for testing
define('WP_TESTS_DOMAIN', 'localhost');
define('WP_TESTS_EMAIL', 'test@example.com');

// Optionally, you can include other initialization scripts or load additional classes here