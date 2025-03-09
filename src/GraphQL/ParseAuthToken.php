<?php

namespace WPGatsby\GraphQL;

use Firebase\JWT\JWT;
use WPGatsby\Admin\Settings;

/**
 * Parses authentication token and sets the current user accordingly.
 *
 * @category WPGatsby\GraphQL
 * @package  WPGatsby\GraphQL
 * @author   Joshua Head <me@joshuahead.com>
 * @license  MIT License
 * @link     https://joshuahead.com
 */
class ParseAuthToken
{
    const AUTH_HEADER = 'HTTP_WPGATSBYPREVIEW';
    const USER_ID_HEADER = 'HTTP_WPGATSBYPREVIEWUSER';

    /**
     * Constructs the class.
     */
    public function __construct()
    {
        add_action('init_graphql_request', [$this, 'set_current_user']);
    }

    /**
     * Sets the current user based on the JWT token.
     *
     * @return void
     */
    public function set_currentUser(): void
    {
        $jwt_token = $_SERVER[self::AUTH_HEADER] ?? null;

        if ($jwt_token) {
            $secret_key  = Settings::get_setting('preview_jwt_secret');
            $decoded_jwt = JWT::decode($jwt_token, $secret_key, ['HS256']);

            $user_id_from_token = $decoded_jwt->data->user_id ?? null;

            if (!$decoded_jwt || !$user_id_from_token) {
                return;
            }

            $existing_author = get_user_by('id', $user_id_from_token);

            $final_user_id = $_SERVER[self::USER_ID_HEADER] ??
                $user_id_from_token ?? null;

            if ($final_user_id && $decoded_jwt && $existing_author) {
                wp_set_current_user($final_user_id);
            }
        }
    }
}