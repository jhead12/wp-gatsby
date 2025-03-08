<?php

// Define constants
define('TOKEN_EXPIRY_SECONDS', 3600); // Token expiry time in seconds (1 hour)

class JWTGenerator {

    /**
     * Generates a JWT token for the given user.
     *
     * @return string The generated JWT token.
     * @throws Exception If any required values are missing or if an error occurs during token generation.
     */
    public function get_token() {
        // Get site URL, secret, and current user ID
        $site_url = $this->get_site_url();
        $secret   = $this->get_jwt_secret();
        $user_id  = $this->get_current_user_id();

        // Validate required values
        if (empty($site_url) || empty($secret) || empty($user_id)) {
            throw new Exception('Missing required values for token generation.');
        }

        // Get current time and token expiry time
        $current_time     = time();
        $token_expiry_time = $this->get_token_expiry_time();

        // Create payload with user information and token expiry time
        $payload = [
            'iss' => $site_url,
            'sub' => $user_id,
            'iat' => $current_time,
            'exp' => $token_expiry_time,
        ];

        // Encode payload using JWT library or custom implementation
        try {
            $jwt_token = JWT::encode($payload, $secret);
            return $jwt_token;
        } catch (Exception $e) {
            throw new Exception('Error generating token: ' . $e->getMessage());
        }
    }

    /**
     * Retrieves the site URL.
     *
     * @return string The site URL.
     */
    private function get_site_url() {
        return home_url();
    }

    /**
     * Retrieves the JWT secret key.
     *
     * @return string The JWT secret key.
     */
    private function get_jwt_secret() {
        // Replace with your own method to retrieve the JWT secret key
        // For example, you could use a constant or retrieve it from an option in the database
        return 'your_jwt_secret_key';
    }

    /**
     * Retrieves the current user ID.
     *
     * @return int The current user ID.
     */
    private function get_current_user_id() {
        $current_user = wp_get_current_user();
        if ($current_user->exists()) {
            return $current_user->ID;
        }
        return 0; // Return 0 or another default value if no user is logged in
    }

    /**
     * Retrieves the token expiry time.
     *
     * @return int The token expiry time (in seconds since epoch).
     */
    private function get_token_expiry_time() {
        $current_time = time();
        return $current_time + TOKEN_EXPIRY_SECONDS;
    }
}
