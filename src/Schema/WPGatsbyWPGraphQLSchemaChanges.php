<?php

namespace WPGatsby\Schema;

/**
 * Modifies WPGraphQL built-in types
 */
class WPGatsbyWPGraphQLSchemaChanges {
	function __construct() {
		add_action(
			'graphql_register_types',
			function() {
				$this->register();
			}
		);
	}

	function register() {
        // Register the input union type
        register_graphql_input_type('MyInputUnion', [
            'description' => __('An example input union.', 'wp-gatsby'),
            'types' => ['TypeA', 'TypeB'],
        ]);

        // Register the object type that can implement another interface
        register_graphql_object_type('MyObject', [
            'fields' => [
                'field1' => ['type' => 'String'],
            ],
            'interfaces' => [['resolveType' => function() { /* resolve logic */ }]],
        ]);

        // Add fields to ContentType and Taxonomy types
		register_graphql_field(
			'ContentType',
			'archivePath',
			[
				'type'        => 'String',
				'description' => __( 'The url path of the first page of the archive page for this content type.', 'wp-gatsby' ),
				'resolve'     => function( $source, $args, $context, $info ) {
					$archive_link = get_post_type_archive_link( $source->name );

					if ( empty( $archive_link ) ) {
						return null;
					}

					$site_url = get_site_url();

					$archive_path = str_replace( $site_url, '', $archive_link );

					if ( $archive_link === $site_url && $archive_path === '' ) {
						return '/';
					}

					return $archive_path ?? null;
				},
			]
		);

		register_graphql_field(
			'Taxonomy',
			'archivePath',
			[
				'type'        => 'String',
				'description' => __( 'The url path of the first page of the archive page for this content type.', 'wp-gatsby' ),
				'resolve'     => function( $source, $args, $context, $info ) {
					$tax = get_taxonomy( $source->name );

					if ( ! $tax->rewrite['slug'] ?? false ) {
						return null;
					}

					return '/' . $tax->rewrite['slug'] . '/';
				},
			]
		);
	}
}

// Example of adding a custom field to an existing type
add_action('graphql_register_types', function() {
    register_graphql_field('Post', 'customField', [
        'type' => 'String',
        'description' => __('A custom field on posts.', 'wp-gatsby'),
        'resolve' => function($post) {
            return get_post_meta($post->ID, 'custom_field_key', true);
        },
    ]);
});

// Example of adding a custom security check
add_filter('graphql_is_query_allowed', function($allowed, $schema) {
    // Custom logic to determine if the query is allowed
    // For example, you could allow only queries with a certain API key in the request headers
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
    return $api_key === 'your_api_key_here';
}, 10, 2);
