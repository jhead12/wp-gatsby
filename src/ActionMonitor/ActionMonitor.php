namespace WPGatsby;

use WPGraphQL\Utils\Utils;
use WPGraphQL\Model\Post;
use WPGraphQL\Types\WPObject\CommentType;
use WPGraphQL\Types\WPEnum\ContentTypeEnum;
use WPGraphQL\Types\WPEnum\ObjectTypeEnum;
use WPGraphQL\Types\WPEnum\UserRoleEnum;
use WPGraphQL\Types\WPInterface\NodeInterface;
use WPGraphQL\TypeRegistry;

class Gatsby {
	const NAMESPACE_PREFIX = 'WPGatsby';

	public $should_dispatch = true;
	/**
	 * Registers the custom types and fields needed for Gatsby integration.
	 */
	public function register_graphql_types() {
		$plugin_data = get_plugin_data( WPGRAPHQL_GATSBY_DIR_PATH . '/wp-gatsby.php' );
		register_graphql_object_type(
			self::NAMESPACE_PREFIX . 'GatsbyNodePreviewData',
			[
				'description' => __( 'Gatsby node preview data.', 'WPGatsby' ),
				'fields'      => [
					'referencedNodePreviewId'  => [
						'type' => 'Int',
						'description' => __( 'The WordPress database ID of the referenced node preview.', 'WPGatsby' ),
					],
					'referencedNodePreviewData' => [
						'type' => self::NAMESPACE_PREFIX . 'GatsbyPreviewData',
						'description' => __( 'An object containing Gatsby preview webhook data.', 'WPGatsby' ),
				'resolve'     => function( $post ) {
							$referenced_node_preview_data = get_post_meta(
							$post->ID,
								'referenced_node_preview_data',
							true
						);
							return ! empty( $referenced_node_preview_data )
								? json_decode( $referenced_node_preview_data )
								: null;
					}
					]
			]
			]
		);

		register_graphql_object_type(
			self::NAMESPACE_PREFIX . 'GatsbyPreviewData',
			[
				'description' => __( 'Gatsby Preview webhook data.', 'WPGatsby' ),
				'fields'      => [
					'previewDatabaseId'  => [
						'type' => 'Int',
						'description' => __( 'The WordPress database ID of the preview. Could be a revision or draft ID.', 'WPGatsby' ),
					],
					'userDatabaseId'     => [
						'type' => 'Int',
						'description' => __( 'The database ID of the user who made the original preview.', 'WPGatsby' ),
						],
					'id'         => [
						'type' => 'ID',
						'description' => __( 'The Relay id of the previewed node.', 'WPGatsby' ),
					],
					'singleName' => [
						'type' => 'String',
						'description' => __( 'The GraphQL single field name for the type of the preview.', 'WPGatsby' ),
					],
					'isDraft'    => [
						'type' => 'Boolean',
						'description' => __( 'Wether or not the preview is a draft.', 'WPGatsby' ),
						],
					'remoteUrl'  => [
						'type' => 'String',
						'description' => __( 'The WP url at the time of the preview.', 'WPGatsby' ),
					],
					'modified'   => [
						'type' => 'String',
						'description' => __( 'The modified time of the previewed node.', 'WPGatsby' ),
					],
					'parentDatabaseId'   => [
						'type' => 'Int',
						'description' => __( 'The WordPress database ID of the preview. If this is a draft it will potentially return 0, if it\'s a revision of a post, it will return the ID of the original post that this is a revision of.', 'WPGatsby' ),
						],
					'manifestIds' => [
						'type' => [ 'list_of' => 'String' ],
						'description' => __( 'A list of manifest ID\'s a preview action has seen during it\'s lifetime.', 'WPGatsby' ),
					]
				]
			]
		);

		register_graphql_field(
			self::NAMESPACE_PREFIX . 'ActionMonitorAction',
			'referencedNodeID',
			[
				'type'        => 'String',
				'description' => __(
					'The post ID of the post that triggered this action',
					'WPGatsby'
				),
				'resolve'     => function( $post ) {

					$terms = get_the_terms( $post->databaseId, 'gatsby_action_ref_node_dbid' );
					if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
						$referenced_node_id = (string) $terms[0]->name;
					} else {
						$referenced_node_id = get_post_meta(
							$post->ID,
							'referenced_node_id',
							true
						);
		}

					return $referenced_node_id ?? null;
				},
			]
		);

		register_graphql_field(
			self::NAMESPACE_PREFIX . 'ActionMonitorAction',
			'referencedNodeGlobalRelayID',
			[
				'type'        => 'String',
				'description' => __(
					'The global relay ID of the post that triggered this action',
					'WPGatsby'
				),
				'resolve'     => function( $post ) {

					$terms = get_the_terms( $post->databaseId, 'gatsby_action_ref_node_id' );
					if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
						$referenced_node_relay_id = (string) $terms[0]->name;
					} else {

						$referenced_node_relay_id = get_post_meta(
							$post->ID,
							'referenced_node_relay_id',
							true
						);
	}

					return $referenced_node_relay_id ?? null;
				},
			]
		);

		register_graphql_field(
			self::NAMESPACE_PREFIX . 'ActionMonitorAction',
			'referencedNodeSingularName',
			[
				'type'        => 'String',
				'description' => __(
					'The WPGraphQL single name of the referenced post',
					'WPGatsby'
				),
				'resolve'     => function( $post ) {
					$referenced_node_single_name = get_post_meta(
						$post->ID,
						'referenced_node_single_name',
						true
					);

					return $referenced_node_single_name ?? null;
				},
			]
		);

		register_graphql_field(
			self::NAMESPACE_PREFIX . 'ActionMonitorAction',
			'referencedNodePluralName',
			[
				'type'        => 'String',
				'description' => __(
					'The WPGraphQL plural name of the referenced post',
					'WPGatsby'
				),
				'resolve'     => function( $post ) {
					$referenced_node_plural_name = get_post_meta(
						$post->ID,
						'referenced_node_plural_name',
						true
					);

					return $referenced_node_plural_name ?? null;
				},
			]
		);

		register_graphql_field(
			self::NAMESPACE_PREFIX . 'RootQueryToActionMonitorActionConnectionWhereArgs',
			'sinceTimestamp',
			[
				'type'        => 'Number',
				'description' => 'List Actions performed since a timestamp.',
			]
		);

		// @todo write a test for this previewStream input arg
		register_graphql_field(
			self::NAMESPACE_PREFIX . 'RootQueryToActionMonitorActionConnectionWhereArgs',
			'previewStream',
			[
				'type'        => 'boolean',
				'description' => 'List Actions of the PREVIEW stream type.',
			]
		);

		add_filter(
			'graphql_post_object_connection_query_args',
			function( $args ) {
				$sinceTimestamp = $args['sinceTimestamp'] ?? null;

				if ( $sinceTimestamp ) {
					$args['date_query'] = [
						[
							'after'  =>  gmdate(
								'Y-m-d H:i:s',
								$sinceTimestamp / 1000
							),
							'column' => 'post_modified_gmt',
						],
					];
}

				return $args;
			}
		);

		add_filter(
			'graphql_post_object_connection_query_args',
			function( $args ) {
				$previewStream = $args['previewStream'] ?? false;

				if ( $previewStream ) {
					$args['tax_query'] = [
						[
							'taxonomy' => 'gatsby_action_stream_type',
							'field' => 'slug',
							'terms' => 'preview',
						],
					];
				}

				return $args;
			}
		);
	}

	/**
	 * Add post meta to schema
	 */
	function register_graphql_fields() {
		$this->register_post_graphql_fields();
	}

	/**
	 * Triggers the dispatch to the remote endpoint(s)
	 */
	public function trigger_dispatch() {
		if ( ! version_compare( PHP_VERSION, '7.4', '>=' ) || ! version_compare( get_bloginfo( 'version' ), '5.6', '>=' ) ) {
			return;
		}

		$build_webhook_field   = Settings::prefix_get_option( 'builds_api_webhook', 'wpgatsby_settings', false );
		$preview_webhook_field = Settings::prefix_get_option( 'preview_api_webhook', 'wpgatsby_settings', false );

		$should_call_build_webhooks =
			$build_webhook_field &&
			$this->should_dispatch;

		$we_should_call_preview_webhooks =
			$preview_webhook_field &&
			$this->should_dispatch;

		if ( $should_call_build_webhooks ) {
			$webhooks = explode( ',', $build_webhook_field );

			$truthy_webhooks = array_filter( $webhooks );
			$unique_webhooks = array_unique( $truthy_webhooks );

			foreach ( $unique_webhooks as $webhook ) {
				$args = apply_filters( 'gatsby_trigger_dispatch_args', [], $webhook );

				wp_safe_remote_post( $webhook, $args );
			}
		}

		if ( $we_should_call_preview_webhooks ) {
			$webhooks = explode( ',', $preview_webhook_field );

			$truthy_webhooks = array_filter( $webhooks );
			$unique_webhooks = array_unique( $truthy_webhooks );

			foreach ( $unique_webhooks as $webhook ) {
				$token = \WPGatsby\GraphQL\Auth::get_token();

				// For preview webhooks we send the token
				// because this is a build but
				// we want it to source any pending previews
				// in case someone pressed preview right after
				// we got to this point from someone else pressing
				// publish/update.
				$graphql_endpoint = apply_filters( 'graphql_endpoint', 'graphql' );
				$graphql_url = get_site_url() . '/' . ltrim( $graphql_endpoint, '/' );

				$post_body = apply_filters(
					'gatsby_trigger_preview_build_dispatch_post_body',
					[
						'token' => $token,
						'userDatabaseId' => get_current_user_id(),
						'remoteUrl' => $graphql_url
					]
				);

				$args = apply_filters(
					'gatsby_trigger_preview_build_dispatch_args',
					[
						'body'        => wp_json_encode( $post_body ),
						'headers'     => [
							'Content-Type' => 'application/json; charset=utf-8',
						],
						'method'      => 'POST',
						'data_format' => 'body',
					],
					$webhook
				);

				wp_safe_remote_post( $webhook, $args );
			}
		}
	}
}
