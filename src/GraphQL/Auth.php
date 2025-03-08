<?php

namespace WPGatsby\GraphQL;

use Firebase\JWT\JWT;
use WPGatsby\Admin\Settings;

class Auth {
    const TOKEN_EXPIRY_SECONDS = 3600; // Token expires in 1 hour

    /**
     * Generate a JSON Web Token (JWT) for authentication.
     *
     * @return string JWT token
     */
    public static function get_token() {
        $site_url = self::get_site_url();
        $secret   = self::get_jwt_secret();
        $user_id  = self::get_current_user_id();

        // Validate required values
        if (empty($site_url) || empty($secret) || empty($user_id)) {
            throw new \Exception('Invalid configuration for JWT generation.');
        }

        $payload = [
            'iss'  => $site_url,
            'aud'  => $site_url,
            'iat'  => time(),
            'nbf'  => time(),
            'exp'  => self::get_token_expiry_time(),
            'data' => [
                'user_id' => $user_id,
            ],
        ];

        return JWT::encode($payload, $secret);
    }

    /**
     * Get the site URL.
     *
     * @return string Site URL
     */
    private static function get_site_url() {
        return get_bloginfo('url');
    }

    /**
     * Get the JWT secret from settings.
     *
     * @return string JWT secret
     */
    private static function get_jwt_secret() {
        return Settings::get_setting('preview_jwt_secret');
    }

    /**
     * Get the current user ID.
     *
     * @return int User ID
     */
    private static function get_current_user_id() {
        return get_current_user_id();
    }

    /**
     * Calculate the token expiry time.
     *
     * @return int Expiry time in seconds since epoch
     */
    private static function get_token_expiry_time() {
        return time() + self::TOKEN_EXPIRY_SECONDS;
    }
}