<?php

// Define constants
define('JWT_TOKEN_EXPIRY_SECONDS', 3600); // Token expiry time in seconds (1 hour)

class JWTGenerator {

    /**
     * Generates a JWT token for the given user.
     *
     * @return string The generated JWT token.
     * @throws Exception If any required values are missing or if an error occurs during token generation.
     */
    public function generateToken() {
        // Retrieve site URL, secret key, and current user ID
        $siteUrl = $this->getSiteUrl();
        $secretKey = $this->getJwtSecret();
        $userId = $this->getCurrentUserId();

        // Validate required values
        if (empty($siteUrl) || empty($secretKey) || empty($userId)) {
            throw new Exception('Missing required values for token generation.');
        }

        // Create payload with user information and token expiry time
        $payload = [
            'iss' => $siteUrl,
            'sub' => $userId,
            'iat' => time(),
            'exp' => $this->getTokenExpiryTime(),
        ];

        // Encode payload using JWT library or custom implementation
        try {
            $jwtToken = JWT::encode($payload, $secretKey);
            return $jwtToken;
        } catch (Exception $e) {
            throw new Exception('Error generating token: ' . $e->getMessage());
        }
    }

    /**
     * Retrieves the site URL.
     *
     * @return string The site URL.
     */
    private function getSiteUrl() {
        return home_url();
    }

    /**
     * Retrieves the JWT secret key.
     *
     * @return string The JWT secret key.
     */
    private function getJwtSecret() {
        // Replace with your own method to retrieve the JWT secret key
        // For example, you could use a constant or retrieve it from an option in the database
        return 'your_jwt_secret_key';
    }

    /**
     * Retrieves the current user ID.
     *
     * @return int The current user ID.
     */
    private function getCurrentUserId() {
        $currentUser = wp_get_current_user();
        if ($currentUser->exists()) {
            return $currentUser->ID;
        }
        return 0; // Return 0 or another default value if no user is logged in
    }

    /**
     * Retrieves the token expiry time.
     *
     * @return int The token expiry time (in seconds since epoch).
     */
    private function getTokenExpiryTime() {
        return time() + JWT_TOKEN_EXPIRY_SECONDS;
    }
}