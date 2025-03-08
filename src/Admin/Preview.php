<?php

namespace WPGatsby;

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
		register_graphql_object_type(
			'WPGatsbyPreviewStatus',
			[
				'description' => __( 'Check compatibility with a given version of gatsby-source-wordpress and the WordPress source site.' ),
				'fields'      => [
					'pageNode'       => [
						'type' => 'WPGatsbyPageNode',
					],
					'statusType'     => [
						'type' => 'WPGatsbyWPPreviewedNodeStatus',
					],
					'remoteStatus'   => [
						'type' => 'WPGatsbyRemotePreviewStatusEnum',
					],
					'modifiedLocal'  => [
						'type' => 'String',
					],
					'modifiedRemote' => [
						'type' => 'String',
					],
					'statusContext'  => [
						'type' => 'String',
					],
				],
			]
		);

		register_graphql_field(
			'WPGatsby',
			'gatsbyPreviewStatus',
			[
				'description' => __( 'The current status of a Gatsby Preview.', 'wp-gatsby' ),
				'type'        => 'WPGatsbyPreviewStatus',
				'args'        => [
					'nodeId' => [
						'type'        => [ 'non_null' => 'Number' ],
						'description' => __( 'The post id for the previewed node.', 'wp-gatsby' ),
					],
				],
				'resolve'     => function( $root, $args, $context, $info ) {
					$post_id = $args['nodeId'] ?? null;

					// make sure post_id is a valid post
					$post = get_post( $post_id );

					$post_type_object = $post
						? get_post_type_object( $post->post_type )
						: null;

					$user_can_edit_this_post = $post
						? current_user_can(
							$post_type_object->cap->edit_posts,
							$post_id
						)
						: null;

					if ( ! $post || ! $user_can_edit_this_post ) {
						throw new UserError(
							sprintf(
								__(
									'Sorry, you are not allowed to access the Preview status of post %1$s',
									'wp-gatsby'
								),
								$post_id
							)
						);
					}

					if ( ! $post ) {
						return [
							'statusType' => 'NO_NODE_FOUND',
						];
					}

					$found_preview_path_post_meta = get_post_meta(
						$post_id,
						'_wpgatsby_page_path',
						true
					);

					$revision = Preview::getPreviewablePostObjectByPostId( $post_id );

					$revision_modified = $revision->post_modified ?? null;

					$modified = $revision_modified ?? $post->post_modified;

					$gatsby_node_modified = get_post_meta(
						$post_id,
						'_wpgatsby_node_modified',
						true
					);

					$remote_status = get_post_meta(
						$post_id,
						'_wpgatsby_node_remote_preview_status',
						true
					);

					$node_modified_was_updated =
						strtotime( $gatsby_node_modified ) >= strtotime( $modified );

					if (
						$node_modified_was_updated
						&& (
						'NO_PAGE_CREATED_FOR_PREVIEWED_NODE' === $remote_status
						|| 'RECEIVED_PREVIEW_DATA_FROM_WRONG_URL' === $remote_status
						)
					) {
						return [
							'statusType'    => null,
							'statusContext' => null,
							'remoteStatus'  => $remote_status,
						];
					}

					$node_was_updated = false;

					if ( $node_modified_was_updated && $found_preview_path_post_meta ) {
						$server_side = true;

						$gatbsy_preview_frontend_url =
							self::get_gatsby_preview_instance_url(
								$server_side
							);

						$page_data_path = $found_preview_path_post_meta === "/"
							? "/index/"
							: $found_preview_path_post_meta;

						$page_data_path_trimmed = trim( $page_data_path, "/" );

						$modified_deployed_url =
							$gatbsy_preview_frontend_url .
							"page-data/$page_data_path_trimmed/page-data.json";

						// check if node page was deployed
						$request  = wp_remote_get( $modified_deployed_url );
						$response = wp_remote_retrieve_body( $request );

						$page_data = json_decode( $response );

						$modified_response =
							$page_data->result->pageContext->__wpGatsbyNodeModified
							?? null;

						$preview_was_deployed =
							$modified_response &&
							strtotime( $modified_response ) >= strtotime( $modified );

						if ( ! $preview_was_deployed ) {
							return [
								'statusType'    =>
									'PREVIEW_PAGE_UPDATED_BUT_NOT_YET_DEPLOYED',
								'statusContext' => null,
								'remoteStatus'  => null,
							];
						} else {
							$node_was_updated = true;
						}
					}

					// if the node wasn't updated, then any status we have is stale.
					$remote_status_type = $remote_status && $node_was_updated
						? $remote_status
						: null;

					if ( 'GATSBY_PREVIEW_PROCESS_ERROR' === $remote_status ) {
						$remote_status_type = $remote_status;
					}

					$status_type = 'PREVIEW_READY';

					if ( ! $node_was_updated ) {
						$status_type = 'REMOTE_NODE_NOT_YET_UPDATED';
					}

					if ( ! $found_preview_path_post_meta ) {
						$status_type = 'NO_PREVIEW_PATH_FOUND';
					}

					$status_context = get_post_meta(
						$post_id,
						'_wpgatsby_node_remote_preview_status_context',
						true
					);

					if ( $status_context === '' ) {
						$status_context = null;
					}

					$normalized_preview_page_path =
						$found_preview_path_post_meta !== ''
							? $found_preview_path_post_meta
							: null;

					return [
						'statusType'     => $status_type,
						'statusContext'  => $status_context,
						'remoteStatus'   => $remote_status_type,
						'pageNode'       => [
							'path' => $normalized_preview_page_path,
						],
						'modifiedLocal'  => $modified,
						'modifiedRemote' => $gatsby_node_modified,
					];
				},
			]
		);
	}

	private static function register_is_preview_frontend_online_field() {
		register_graphql_field(
			'WPGatsby',
			'isPreviewFrontendOnline',
			[
				'description' => __( 'Wether or not the Preview frontend URL is online.', 'wp-gatsby' ),
				'type'        => 'Boolean',
				'resolve'     => function( $root, $args, $context, $info ) {
					if ( ! is_user_logged_in() ) {
						return false;
					}

					$preview_url = self::get_gatsby_preview_instance_url();

					$request = wp_remote_get( $preview_url );

					$request_was_successful =
						self::was_request_successful( $request );

					return $request_was_successful;
				},
			]
		);
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
