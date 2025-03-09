<?php

namespace WPGatsby\GraphQL;

use Firebase\JWT\JWT;
use WPGatsby\Admin\Settings;

/**
 * Class Auth
 *
 * Handles authentication.
 *
 * @category WPGatsby
 * @package  WPGatsby\GraphQL\Auth
 * @author   Your Name
 * @license  MIT License <https://opensource.org/licenses/MIT>
 * @link     https://github.com/yourusername/wpgatsby-graphql
 */
class Auth
{
    private const TOKEN_EXPIRY_SECONDS = 3600; // Token expires in 1 hour

    /**
     * Generate a JSON Web Token (JWT) for authentication.
     *
     * @return string JWT token
     * @throws \Exception
     */
    public static function getToken(): string
    {
        $siteUrl = self::getSiteUrl();
        $secret = self::getJwtSecret();
        $userId = self::getCurrentUserId();

        // Validate required values
        if (empty($siteUrl) || empty($secret) || empty($userId)) {
            throw new \Exception('Invalid configuration for JWT generation.');
        }

        $payload = [
            'iss' => $siteUrl,
            'aud' => $siteUrl,
            'iat' => time(),
            'nbf' => time(),
            'exp' => self::getTokenExpiryTime(),
            'data' => [
                'user_id' => $userId,
            ],
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    /**
     * Get the site URL.
     *
     * @return string Site URL
     */
    private static function getSiteUrl(): string
    {
        return get_bloginfo('url');
    }

    /**
     * Get the JWT secret from settings.
     *
     * @return string JWT secret
     */
    private static function getJwtSecret(): string
    {
        return Settings::getSetting('preview_jwt_secret');
    }

    /**
     * Get the current user ID.
     *
     * @return int User ID
     */
    private static function getCurrentUserId(): int
    {
        return get_current_user_id();
    }

    /**
     * Calculate the token expiry time.
     *
     * @return int Expiry time in seconds since epoch
     */
    private static function getTokenExpiryTime(): int
    {
        return time() + self::TOKEN_EXPIRY_SECONDS;
    }
}
