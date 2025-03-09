<?php

namespace WPGatsby\Preview;

use UserError;
use GraphQL\Deferred;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Schema;
use GraphQL\Error\FormattedError;
use WordPress\WP_Query;
use function wp_remote_get;
use function get_post_meta;
use function json_decode;
use function is_user_logged_in;
use function current_user_can;

class GatsbyGraphQL {

	const PREVIEW_FRONTEND_URL = 'http://localhost:8000';

	public static function register_graphql_fields() {
		self::register_gatsby_preview_status_field();
		self::register_is_preview_frontend_online_field();
	}

	private static function register_gatsby_preview_status_field() {
		// ... (rest of the method remains unchanged)
	}

	private static function register_is_preview_frontend_online_field() {
		// ... (rest of the method remains unchanged)
	}

	private static function get_gatsby_preview_instance_url( $server_side = false ) {
		if ( $server_side ) {
			return self::PREVIEW_FRONTEND_URL;
		} else {
			return self::PREVIEW_FRONTEND_URL . '/__graphql';
		}
	}

	private static function was_request_successful( $request ) {
		$status_code = wp_remote_retrieve_response_code( $request );

		if ( $status_code === 200 ) {
			return true;
		} else {
			return false;
		}
	}

}