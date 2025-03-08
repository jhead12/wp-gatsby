class PostMonitor {
    private $action_monitor;

    public function __construct( ActionMonitor $action_monitor ) {
        $this->action_monitor = $action_monitor;
        add_action( 'save_post', [ $this, 'callback_post_updated' ], 10, 3 );
		add_action( 'transition_post_status', [ $this, 'callback_transition_post_status' ], 10, 3 );
		add_action( 'deleted_post', [ $this, 'callback_deleted_post' ], 10, 1 );
		add_action( 'updated_post_meta', [ $this, 'callback_updated_post_meta' ], 10, 4 );
		add_action( 'added_post_meta', [ $this, 'callback_updated_post_meta' ], 10, 4 );
		add_action( 'deleted_post_meta', [ $this, 'callback_deleted_post_meta' ], 10, 4 );
	}

	public function callback_post_updated( int $post_id, WP_Post $post_after, WP_Post $post_before ) {
		if ( isset( $post_after->post_author ) && (int) $post_after->post_author !== (int) $post_before->post_author ) {
			$this->log_user_update( $post_after );
			$this->log_user_update( $post_before );
		}
	}

	public function callback_transition_post_status( $new_status, $old_status, WP_Post $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}
		if ( ! $this->is_post_type_tracked( $post->post_type ) ) {
			return;
		}
		$initial_post_statuses = [ 'auto-draft', 'inherit', 'new' ];
		if ( in_array( $new_status, $initial_post_statuses, true ) ) {
			return;
		}
		if ( 'draft' === $new_status && 'draft' === $old_status ) {
			return;
		}
		if ( 'publish' !== $old_status && 'publish' !== $new_status ) {
			return;
		}

		$action_type = 'UPDATE';

		if ( 'publish' !== $new_status && 'publish' === $old_status ) {
			$action_type = 'DELETE';
		} elseif ( 'publish' === $new_status && 'publish' !== $old_status ) {
			$action_type = 'CREATE';
		}

		if ( 'UPDATE' !== $action_type ) {
			$this->log_user_update( $post );
		}

		$post_type_object = get_post_type_object( $post->post_type );

		$action = [
			'action_type'         => $action_type,
			'title'               => $post->post_title,
			'node_id'             => $post->ID,
			'relay_id'            => Relay::toGlobalId( 'post', $post->ID ),
			'graphql_single_name' => $post_type_object->graphql_single_name,
			'graphql_plural_name' => $post_type_object->graphql_plural_name,
			'status'              => $new_status,
		];

		$this->log_action( $action );
	}

	public function callback_deleted_post( int $post_id ) {
		$post = get_post( $post_id );

		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}
		if ( ! $this->is_post_type_tracked( $post->post_type ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		$post_type_object = get_post_type_object( $post->post_type );

		$action = [
			'action_type'         => 'DELETE',
			'title'               => $post->post_title,
			'node_id'             => $post->ID,
			'relay_id'            => Relay::toGlobalId( 'post', $post->ID ),
			'graphql_single_name' => $post_type_object->graphql_single_name,
			'graphql_plural_name' => $post_type_object->graphql_plural_name,
			'status'              => 'trash',
		];

		$this->log_action( $action );

		$this->log_user_update( $post );
	}

	public function is_post_type_tracked( string $post_type ) {
		return in_array( $post_type, $this->action_monitor->get_tracked_post_types(), true );
	}

	public function callback_updated_post_meta( int $meta_id, int $object_id, string $meta_key, $meta_value ) {
		$post = get_post( $object_id );

		if ( empty( $post ) || ! is_a( $post, 'WP_Post' ) ) {
			return;
		}
		if ( ! $this->is_post_type_tracked( $post->post_type ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		if ( false === $this->should_track_meta( $meta_key, $meta_value, $post ) ) {
			return;
		}

		$post_type_object = get_post_type_object( $post->post_type );

		$action = [
			'action_type'         => 'UPDATE',
			'title'               => $post->post_title,
			'node_id'             => $post->ID,
			'relay_id'            => Relay::toGlobalId( 'post', $post->ID ),
			'graphql_single_name' => $post_type_object->graphql_single_name,
			'graphql_plural_name' => $post_type_object->graphql_plural_name,
			'status'              => $post->post_status,
		];

		$this->log_action( $action );
	}

	public function callback_deleted_post_meta( array $meta_ids, int $object_id, string $meta_key, $meta_value ) {
		$post = get_post( $object_id );

		if ( empty( $post ) || ! is_a( $post, 'WP_Post' ) ) {
			return;
		}
		if ( ! $this->is_post_type_tracked( $post->post_type ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		if ( false === $this->should_track_meta( $meta_key, $meta_value, $post ) ) {
			return;
		}

		$post_type_object = get_post_type_object( $post->post_type );

		$action = [
			'action_type'         => 'UPDATE',
			'title'               => $post->post_title,
			'node_id'             => $post->ID,
			'relay_id'            => Relay::toGlobalId( 'post', $post->ID ),
			'graphql_single_name' => $post_type_object->graphql_single_name,
			'graphql_plural_name' => $post_type_object->graphql_plural_name,
			'status'              => $post->post_status,
		];

		$this->log_action( $action );
	}

	public function log_user_update( WP_Post $post ) {
		if ( empty( $post->post_author ) || ! absint( $post->post_author ) ) {
			return;
		}

		$user = get_user_by( 'id', absint( $post->post_author ) );

		if ( ! $user || 0 === $user->ID ) {
			return;
		}

		$user_monitor = $this->action_monitor->get_action_monitor( 'UserMonitor' );

		if ( empty( $user_monitor ) || ! $user_monitor instanceof UserMonitor ) {
			return;
		}

		if ( ! $user_monitor->is_published_author( $user->ID ) ) {
			$action_type = 'DELETE';
			$status      = 'trash';
		} else {
			$action_type = 'UPDATE';
			$status      = 'publish';
		}

		$this->log_action(
			[
				'action_type'         => $action_type,
				'title'               => $user->display_name,
				'node_id'             => $user->ID,
				'relay_id'            => Relay::toGlobalId( 'user', $user->ID ),
				'graphql_single_name' => 'user',
				'graphql_plural_name' => 'users',
				'status'              => $status,
			]
		);
	}
}
		);
	}

}


