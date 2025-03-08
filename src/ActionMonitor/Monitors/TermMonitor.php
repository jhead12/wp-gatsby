class TermTracking {

    private $action_monitor;
    private $terms_before_delete = [];

    public function __construct( ActionMonitor $action_monitor ) {
        $this->action_monitor = $action_monitor;

        add_action( 'created_term', [ $this, 'handle_created_term' ], 10, 3 );
        add_action( 'delete_term', [ $this, 'handle_deleted_term' ], 10, 4 );
        add_action( 'edited_term', [ $this, 'handle_updated_term' ], 10, 3 );
        add_action( 'pre_delete_term', [ $this, 'prepare_deleted_term' ], 10, 2 );

        add_action( 'updated_term_meta', [ $this, 'handle_updated_term_meta' ], 10, 4 );
        add_action( 'deleted_term_meta', [ $this, 'handle_deleted_term_meta' ], 10, 4 );
    }

    private function is_taxonomy_tracked( string $taxonomy ): bool {
		return in_array( $taxonomy, $this->action_monitor->get_tracked_taxonomies(), true );
	}

    public function handle_created_term( int $term_id, int $tt_id, string $taxonomy ) {
		$tax_object = get_taxonomy( $taxonomy );

		if ( false === $tax_object || ! $this->is_taxonomy_tracked( $taxonomy ) ) {
			return;
		}

		$term = get_term( $term_id, $taxonomy );

		if ( ! is_a( $term, 'WP_Term' ) ) {
			return;
		}

        $action_data = [
				'action_type'         => 'CREATE',
				'title'               => $term->name,
				'node_id'             => $term->term_id,
				'relay_id'            => Relay::toGlobalId( 'term', $term->term_id ),
				'graphql_single_name' => $tax_object->graphql_single_name,
				'graphql_plural_name' => $tax_object->graphql_plural_name,
				'status'              => 'publish',
		];

        $this->log_action( $action_data );
        if ( true === $tax_object->hierarchical ) {
			$this->update_hierarchical_relatives( $term, $tax_object );
		}
	}

    public function handle_deleted_term( int $term_id, int $tt_id, string $taxonomy, $deleted_term ) {
        $tax_object = get_taxonomy( $taxonomy );

        if ( false === $tax_object || ! $this->is_taxonomy_tracked( $taxonomy ) ) {
			return;
		}

        $action_data = [
            'action_type'         => 'DELETE',
            'title'               => $deleted_term->name,
            'node_id'             => $deleted_term->term_id,
            'relay_id'            => Relay::toGlobalId( 'term', $deleted_term->term_id ),
			'graphql_single_name' => $tax_object->graphql_single_name,
			'graphql_plural_name' => $tax_object->graphql_plural_name,
            'status'              => 'trash',
		];

        $this->log_action( $action_data );

        if ( true === $tax_object->hierarchical ) {
            $this->update_hierarchical_relatives( $deleted_term, $tax_object );
	}
    }

    public function handle_updated_term( int $term_id, int $tt_id, string $taxonomy ) {
        $tax_object = get_taxonomy( $taxonomy );

        if ( false === $tax_object || ! $this->is_taxonomy_tracked( $taxonomy ) ) {
			return;
		}

        $term = get_term( $term_id, $taxonomy );

        $action_data = [
			'action_type'         => 'UPDATE',
			'title'               => $term->name,
			'node_id'             => $term->term_id,
			'relay_id'            => Relay::toGlobalId( 'term', $term->term_id ),
			'graphql_single_name' => $tax_object->graphql_single_name,
			'graphql_plural_name' => $tax_object->graphql_plural_name,
			'status'              => 'publish',
		];

        $this->log_action( $action_data );

        if ( true === $tax_object->hierarchical ) {
            $this->update_hierarchical_relatives( $term, $tax_object );
	}
}

    public function prepare_deleted_term( int $term_id, string $taxonomy ) {
        $term = get_term_by( 'id', $term_id, $taxonomy );

        if ( ! $term instanceof WP_Term ) {
            return;
        }

        $before_delete = [
            'term' => $term,
        ];

        if ( true === get_taxonomy( $taxonomy )->hierarchical ) {
            $term_children = get_term_children( $term->term_id, $taxonomy );
            if ( ! empty( $term_children ) ) {
                $before_delete['children'] = $term_children;
            }
        }

        $this->terms_before_delete[ $term->term_id ] = $before_delete;
    }

    private function update_hierarchical_relatives( WP_Term $term, WP_Taxonomy $tax_object ): void {
        $taxonomy = $tax_object->name;

        if ( true === $tax_object->hierarchical ) {
            if ( ! empty( $term->parent ) ) {
                $parent = get_term_by( 'id', absint( $term->parent ), $taxonomy );

                if ( is_a( $parent, 'WP_Term' ) ) {
                    $this->log_action(
                        [
                            'action_type'         => 'UPDATE',
                            'title'               => $parent->name . ' Parent',
                            'node_id'             => $parent->term_id,
                            'relay_id'            => Relay::toGlobalId( 'term', $parent->term_id ),
                            'graphql_single_name' => $tax_object->graphql_single_name,
                            'graphql_plural_name' => $tax_object->graphql_plural_name,
                            'status'              => 'publish',
                        ]
                    );
                }
            }

            if ( isset( $this->terms_before_delete[ $term->term_id ]['children'] ) ) {
                $child_ids = $this->terms_before_delete[ $term->term_id ]['children'];
            } else {
                $child_ids = get_term_children( $term->term_id, $taxonomy );
            }

            if ( ! empty( $child_ids ) && is_array( $child_ids ) ) {
                foreach ( $child_ids as $child_term_id ) {
                    $child_term = get_term_by( 'id', $child_term_id, $taxonomy );

                    if ( ! empty( $child_term ) ) {
                        $this->log_action(
                            [
                                'action_type'         => 'UPDATE',
                                'title'               => $child_term->name . ' Parent',
                                'node_id'             => $child_term->term_id,
                                'relay_id'            => Relay::toGlobalId( 'term', $child_term->term_id ),
                                'graphql_single_name' => $tax_object->graphql_single_name,
                                'graphql_plural_name' => $tax_object->graphql_plural_name,
                                'status'              => 'publish',
                            ]
                        );
                    }
                }
            }
        }
    }

    public function handle_updated_term_meta( int $meta_id, int $object_id, string $meta_key, $meta_value ) {
        if ( empty( $term = get_term( $object_id ) ) || ! is_a( $term, 'WP_Term' ) ) {
            return;
        }

        $tax_object = get_taxonomy( $term->taxonomy );

        // If the updated term is of a post type that isn't being tracked, do nothing
        if ( false === $tax_object || ! $this->is_taxonomy_tracked( $term->taxonomy ) ) {
            return;
        }

        if ( false === $this->should_track_meta( $meta_key, $meta_value, $term ) ) {
            return;
        }

        $action_data = [
            'action_type'         => 'UPDATE',
            'title'               => $term->name,
            'node_id'             => $term->term_id,
            'relay_id'            => Relay::toGlobalId( 'term', $term->term_id ),
            'graphql_single_name' => $tax_object->graphql_single_name,
            'graphql_plural_name' => $tax_object->graphql_plural_name,
            'status'              => 'publish',
        ];

        // Log the action
        $this->log_action( $action_data );
    }

    public function handle_deleted_term_meta( array $meta_ids, int $object_id, string $meta_key, $meta_value ) {
        if ( empty( $term = get_term( $object_id ) ) || ! is_a( $term, 'WP_Term' ) ) {
            return;
        }

        $tax_object = get_taxonomy( $term->taxonomy );

        // If the updated term is of a post type that isn't being tracked, do nothing
        if ( false === $tax_object || ! $this->is_taxonomy_tracked( $term->taxonomy ) ) {
            return;
        }

        if ( false === $this->should_track_meta( $meta_key, $meta_value, $term ) ) {
            return;
        }

        $action_data = [
            'action_type'         => 'UPDATE',
            'title'               => $term->name,
            'node_id'             => $term->term_id,
            'relay_id'            => Relay::toGlobalId( 'term', $term->term_id ),
            'graphql_single_name' => $tax_object->graphql_single_name,
            'graphql_plural_name' => $tax_object->graphql_plural_name,
            'status'              => 'publish',
        ];

        // Log the action
        $this->log_action( $action_data );
    }

    private function log_action( array $action_data ): void {
        // Implement logging logic here
    }

    private function should_track_meta( string $meta_key, $meta_value, WP_Term $term ): bool {
        // Implement meta tracking logic here
    }
}
