<?php
namespace WPGatsby\ActionMonitor\Monitors;

use GraphQLRelay\Relay;
use WPGraphQL\GatsbyMonitor\ActionMonitorPreview as Preview;
use WPGraphQL\GatsbyMonitor\ActionMonitorAction as Action;
abstract class Monitor {
    /**

    /**
     * The ActionMonitor instance.
     *
     * @var ActionMonitor
     */
    public $action_monitor = null;

    /**
     * IDs to ignore when logging actions
     *
     * @var array
     */
    protected $ignored_ids = [];

    public function __construct() {
        // Nothing to do here for now
    }

    /**
     * Inserts an action that triggers Gatsby Source WordPress to diff the Schemas.
     *
     * This can be used for plugins such as Custom Post Type UI, Advanced Custom Fields, etc that
     * alter the Schema in some way.
     *
     * @param array $args Optional args to add to the action
     */
    public function trigger_schema_diff( $args = [] ) {
        $default = [
        'title'               => __('Diff schemas', 'WPGatsby'),
        'node_id'             => 'none',
        'relay_id'            => 'none',
        'graphql_single_name' => 'none',
        'graphql_plural_name' => 'none',
        'status'              => 'none',
        ];
        $args = array_merge($default, $args);
        $args['action_type'] = 'DIFF_SCHEMAS';
        $this->log_action($args);
    }

    /**
     * Insert new action
     *
     * $args = [$action_type, $title, $status, $node_id, $relay_id, $graphql_single_name,
     * $graphql_plural_name]
     *
     * @param array $args Array of arguments to configure the action to be inserted
     */
    public function log_action( array $args ) {
        if (
            !isset($args['action_type']) ||
            !isset($args['title']) ||
            !isset($args['node_id']) ||
            !isset($args['relay_id']) ||
            !isset($args['graphql_single_name']) ||
            !isset($args['graphql_plural_name']) ||
            !isset($args['status'])
        ) {
            // Log error or throw exception for better debugging
            return;
        }

        /**
         * Filter to allow skipping a logged action. If set to false, the action will not be logged.
         *
         * @param null|bool $enable    Whether the action should be logged
         * @param array     $arguments The args to log
         * @param Monitor   $monitor   Instance of the Monitor
         */
        $pre_log_action = apply_filters('gatsby_pre_log_action_monitor_action', null, $args, $this);

        if (null !== $pre_log_action) {
            if (false === $pre_log_action) {
                return;
            }
        }

        // If the node_id is set to be ignored, don't create a log
        if (in_array($args['node_id'], $this->ignored_ids, true)) {
            return;
        }

        $should_dispatch = !isset($args['skip_webhook']) || !$args['skip_webhook'];
        $time = time();
        $node_type = 'unknown';
        if (isset($args['graphql_single_name'])) {
            $node_type = $args['graphql_single_name'];
        } elseif (isset($args['relay_id'])) {
            $id_parts = Relay::fromGlobalId($args['relay_id']);
            if (!isset($id_parts['type'])) {
                $node_type = $id_parts['type'];
            }
        }

        $stream_type = ($args['stream_type'] ?? null) === 'PREVIEW' ? 'PREVIEW' : 'CONTENT';
        $is_preview_stream = $stream_type === 'PREVIEW';

        // Check to see if an action already exists for this node type/database id
        $existing = new \WP_Query([
            'post_type'      => 'action_monitor',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
            'fields'         => 'ids',
            'tax_query'      => [
            'relation' => 'AND',
            [
                    'taxonomy' => 'gatsby_action_ref_node_dbid',
                    'field'    => 'name',
                    'terms'    => sanitize_text_field($args['node_id']),
            ],
            [
            'taxonomy' => 'gatsby_action_ref_node_type',
            'field'    => 'name',
            'terms'    => $node_type,
            ],
            [
            'taxonomy' => 'gatsby_action_stream_type',
            'field'    => 'name',
            'terms'    => $stream_type,
            ]
            ]
        ]);

        if (isset($existing->posts) && !empty($existing->posts)) {
            $existing_id = $existing->posts[0];
            return $this->update_existing_action($existing_id, $args);
        }

        return $this->insert_new_action($args);
    }

    private function update_existing_action(int $existing_id, array $args): void {
        wp_update_post([
            'ID'           => absint($existing_id),
            'post_title'   => $args['title'],
            'post_content' => wp_json_encode($args),
        ]);
    }

    private function insert_new_action(array $args): void {
        $action_monitor_post_id = \wp_insert_post([
            'post_title'   => $args['title'],
            'post_type'    => 'action_monitor',
            'post_status'  => 'private',
            'author'       => -1,
            'post_name'    => sanitize_title("{$args['title']}-{$time}"),
            'post_content' => wp_json_encode($args),
        ]);
        wp_set_object_terms($action_monitor_post_id, sanitize_text_field($args['node_id']), 'gatsby_action_ref_node_dbid');
        wp_set_object_terms($action_monitor_post_id, sanitize_text_field($node_type), 'gatsby_action_ref_node_type');

        $this->set_action_metadata($action_monitor_post_id, $args);
        $this->schedule_dispatch_if_needed($args);
    }

    private function set_action_metadata(int $action_monitor_post_id, array $args): void {
        wp_set_object_terms($action_monitor_post_id, sanitize_text_field($args['relay_id']), 'gatsby_action_ref_node_id');
        wp_set_object_terms($action_monitor_post_id, $args['action_type'], 'gatsby_action_type');
        wp_set_object_terms($action_monitor_post_id, $stream_type, 'gatsby_action_stream_type');

        if (isset($args['preview_data'])) {
            $existing_preview_data = \get_post_meta(
                $action_monitor_post_id,
                '_gatsby_preview_data',
                true
            );

            $manifest_id = Preview::get_preview_manifest_id_for_post(
                get_post($args['node_id'])
            );

            $manifest_ids = [$manifest_id];

            if ($existing_preview_data && $existing_preview_data !== "") {
                   $existing_preview_data = json_decode($existing_preview_data);

                if ($existing_preview_data->manifestIds ?? false) {
                    $manifest_ids = array_unique(
                        array_merge(
                            $existing_preview_data->manifestIds,
                            $manifest_ids
                        )
                    );
                }
            }

            $preview_data = json_decode($args['preview_data']);
            $preview_data->manifestIds = $manifest_ids;
            $preview_data = json_encode($preview_data);

            \update_post_meta(
                $action_monitor_post_id,
                '_gatsby_preview_data',
                $preview_data
            );
        }

        \update_post_meta(
            $action_monitor_post_id,
            'referenced_node_status',
            $args['status']
        );
        \update_post_meta(
            $action_monitor_post_id,
            'referenced_node_single_name',
            graphql_format_field_name($args['graphql_single_name'])
        );
        \update_post_meta(
            $action_monitor_post_id,
            'referenced_node_plural_name',
            graphql_format_field_name($args['graphql_plural_name'])
        );
    }

    private function schedule_dispatch_if_needed(array $args): void {
        if ($should_dispatch && !$is_preview_stream) {
            $this->action_monitor->schedule_dispatch();
        }
    }

    private function garbage_collect_actions(): void {
        $this->action_monitor->garbage_collect_actions();
    }

    /**
     * Initialize the Monitor
     *
     * @return mixed
     */
    abstract public function init();
}