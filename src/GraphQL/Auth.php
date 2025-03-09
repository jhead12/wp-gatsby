<?php

namespace WPGatsby\GraphQL;

use Firebase\JWT\JWT;
use WPGatsby\Admin\Settings;

/**
 * Class for authentication.
 *
 * @category WPGatsby
 * @package  WPGatsby\GraphQL
 * @author   Your Name <your.email@example.com>
 * @license  MIT License <https://opensource.org/licenses/MIT>
 * @link     https://github.com/yourusername/wpgatsby-graphql
 */
class Auth
{
    const TOKEN_EXPIRY_SECONDS = 3600; // Token expires in 1 hour
    /**
     * Generate a JSON Web Token (JWT) for authentication.
     *
     * @return string JWT token
     */
    public static function getToken()
    {
        $siteUrl = self::getSiteUrl();
        $secret   = self::getJwtSecret();
        $userId  = self::getCurrentUserId();
        $siteUrl = self::_getSiteUrl();
        $secret   = self::_getJwtSecret();
        $userId  = self::_getCurrentUserId();

        // Validate required values
        if (empty($siteUrl) || empty($secret) || empty($userId)) {
            throw new \Exception('Invalid configuration for JWT generation.');
        }

        $payload = [
            'iss'  => $siteUrl,
            'aud'  => $siteUrl,
            'iat'  => time(),
            'nbf'  => time(),
            'exp'  => self::getTokenExpiryTime(),
            'exp'  => self::_getTokenExpiryTime(),
            'data' => [
                'user_id' => $userId,
            ],
        ];

        return JWT::encode($payload, $secret);
    }

    /**
     * Get the site URL.
     *
     * @return string Site URL
     */
    private static function getSiteUrl()
    private static function _getSiteUrl()
    {
        return get_bloginfo('url');
    }

    /**
     * Get the JWT secret from settings.
     *
     * @return string JWT secret
     */
    private static function getJwtSecret()
    private static function _getJwtSecret()
    {
        return Settings::get_setting('preview_jwt_secret');
    }

    /**
     * Get the current user ID.
     *
     * @return int User ID
     */
    private static function getCurrentUserId()
    private static function _getCurrentUserId()
    {
        return get_current_user_id();
    }

    /**
     * Calculate the token expiry time.
     *
     * @return int Expiry time in seconds since epoch
     */
    private static function getTokenExpiryTime()
    private static function _getTokenExpiryTime()
    {
        return time() + self::TOKEN_EXPIRY_SECONDS;
    }
}
}
