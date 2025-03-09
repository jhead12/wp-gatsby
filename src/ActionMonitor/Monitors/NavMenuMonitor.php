<?php

namespace WPGatsby\ActionMonitor\Monitors;

class NavMenuActionLogger
{

    // ... (other methods and properties)
    /**
     * Log an action when a menu is updated.
     *
     * @param int   $menu_id   The ID of the menu being updated.
     * @param array $menu_data The data associated with the menu.
     */
    public function callback_update_nav_menu( int $menu_id, array $menu_data = [] )
    {
        if (! $this->is_menu_public($menu_id) ) {
            return;
        }
        $menu = get_term_by('id', absint($menu_id), 'nav_menu');
        if (! $menu || is_wp_error($menu) ) {
            return;
        }
        $this->log_action_for_menu(
            $menu,
            [
                'action_type' => 'UPDATE',
                'status'      => 'publish',
            ]
        );
    }

    /**
     * Log actions when menu locations are updated.
     *
     * @param array $old_locations Old locations with a menu assigned.
     * @param array $new_locations New locations with a menu assigned.
     */
    public function log_diffed_menus( array $old_locations, array $new_locations )
    {
        if ($old_locations === $new_locations ) {
            return;
        }
        $added_locations = array_diff($new_locations, $old_locations);
        foreach ( $added_locations as $location => $menu_id ) {
            $this->log_menu_action_for_location(
                $menu_id,
                'CREATE',
                [
                    'status' => 'publish',
                ]
            );
        }
        $removed_locations = array_diff($old_locations, $new_locations);
        foreach ( $removed_locations as $location => $menu_id ) {
            $this->log_menu_action_for_location(
                $menu_id,
                'DELETE',
                [
                    'status' => 'trash',
                ]
            );
        }
    }

    /**
     * Log an action when a menu is deleted.
     *
     * @param int $term_id ID of the deleted menu.
     */
    public function callback_delete_nav_menu( $term_id )
    {
        $this->log_action_for_menu(
            (object) [ 'term_id' => $term_id, 'name' => '#' . $term_id ],
            [
                'action_type' => 'DELETE',
                'status'      => 'trash',
            ]
        );
    }

    /**
     * Log an action when a menu item is added.
     *
     * @param int   $menu_id         ID of the updated menu.
     * @param int   $menu_item_db_id ID of the updated menu item.
     * @param array $args            An array of arguments used to update a menu item.
     */
    public function callback_add_nav_menu_item( int $menu_id, int $menu_item_db_id, array $args )
    {
        if (! $this->is_menu_public($menu_id) ) {
            return;
        }
        $menu = get_term_by('id', $menu_id, 'nav_menu');
        if (! $menu || is_wp_error($menu) ) {
            return;
        }
        $this->log_action_for_menu(
            $menu,
            [
                'action_type' => 'UPDATE',
                'status'      => 'publish',
            ]
        );
        $menu_item = get_post($menu_item_db_id);
        if (! $menu_item || is_wp_error($menu_item) ) {
            return;
        }
        // ... (rest of the code)
    }
}
