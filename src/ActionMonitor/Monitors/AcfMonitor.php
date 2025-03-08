<?php

namespace WPGatsby\ActionMonitor\Monitors;

use WPGatsby\Utils\Utils;

class AcfMonitor extends Monitor {

	public function init() {
		// ACF Actions
		add_action( 'acf/update_field_group', [ $this, 'handle_acf_field_group_change' ] );
		add_action( 'acf/delete_field_group', [ $this, 'handle_acf_field_group_change' ] );

        add_action( 'acf/save_post', [ $this, 'after_acf_save_post' ], 20 );
	}

    /**
     * Handles changes to ACF field groups.
     *
     * @param string $action The action type ('update' or 'delete').
     */
    private function handle_acf_field_group_change( string $action ) {
		$title = __( 'Update ACF Field Group', 'WPGatsby' );
		if ( 'delete' === $action ) {
			$title = __( 'Delete ACF Field Group', 'WPGatsby' );
		}

		$this->trigger_schema_diff(
			[
				'title' => $title,
			]
		);
    }

    /**
     * Handles content updates of ACF option pages.
     */
    public function after_acf_save_post() {
		if ( ! function_exists( 'acf_get_options_pages' ) ) {
			return;
		}

        $option_pages = acf_get_options_pages();

        if ( ! is_array( $option_pages ) ) {
			return;
        }

        $option_pages_slugs = array_keys( $option_pages );

        /**
         * Filters the $option_pages_slugs array.
         *
         * @since 2.1.2
         *
         * @param	array $option_pages_slugs Array with slugs of all registered ACF option pages.
         */
        $option_pages_slugs = apply_filters(
			'gatsby_action_monitor_tracked_acf_options_pages',
			$option_pages_slugs
		);

        $screen = get_current_screen();

        if(
			! empty( $option_pages_slugs ) 
			&& is_array( $option_pages_slugs )
			&& Utils::str_in_substr_array( $screen->id, $option_pages_slugs )
		) {
            $this->trigger_non_node_root_field_update();
        }
    }
}