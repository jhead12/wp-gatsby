<?php

namespace WPGatsby\GraphQL;

use \Firebase\JWT\JWT;
use \WPGatsby\Admin\Settings;

class ParseAuthToken {
    const AUTH_HEADER = 'HTTP_WPGATSBYPREVIEW';
    const USER_ID_HEADER = 'HTTP_WPGATSBYPREVIEWUSER';

	function __construct() {
		add_action( 'init_graphql_request', [ $this, 'set_current_user' ] );
	}

    /**
     * Sets the current user based on the JWT token.
     */
	function set_current_user() {
        // Retrieve the JWT token from the HTTP header.
        $jwt_token = $_SERVER[self::AUTH_HEADER] ?? null;

        if ( $jwt_token ) {
            // Decode the JWT token using the secret key.
            $secret_key  = Settings::get_setting( 'preview_jwt_secret' );
            $decoded_jwt = JWT::decode( $jwt_token, $secret_key, [ 'HS256' ] );

            // Extract the user ID from the decoded JWT payload.
            $user_id_from_token = $decoded_jwt->data->user_id ?? null;

            // Check if the decoded JWT and user ID are valid.
            if ( ! $decoded_jwt || ! $user_id_from_token ) {
				return;
			}

            // Check if the user ID corresponds to an existing author in WordPress.
            $existing_author = get_user_by( 'id', $user_id_from_token );

            // Retrieve the user ID from the HTTP header or use the user ID from the token.
            $final_user_id = $_SERVER[self::USER_ID_HEADER] ??
                $user_id_from_token ?? null;

            // Set the current WordPress user if both conditions are met.
            if ( $final_user_id && $decoded_jwt && $existing_author ) {
                wp_set_current_user( $final_user_id );
            }
        }
    }

}
