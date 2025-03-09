<?php

namespace WPGatsby\ActionMonitor\Monitors;

use GraphQLRelay\Relay;
use WPGatsby\Admin\Preview;
use WPGatsby\Admin\Settings;

class PreviewMonitor extends Monitor
{
    function init()
    {
        $enable_gatsby_preview = Settings::get_setting('enable_gatsby_preview') === 'on';

        if ($enable_gatsby_preview ) {
            add_filter('template_include', [ $this, 'setup_preview_template' ], 1, 99);

            add_filter(
                'preview_post_link', function ( $link, $post ) {
                    if (defined('GRAPHQL_REQUEST') && GRAPHQL_REQUEST ) {
                          return $link;
                    }

                    return \add_query_arg('gatsby_preview', 'true', $link);
                }, 10, 2 
            );
        }
    }

    public static function is_gatsby_content_sync_preview()
    {
        if (defined('GRAPHQL_REQUEST') && GRAPHQL_REQUEST ) {
            return false;
        }

        $enable_gatsby_preview = Settings::get_setting('enable_gatsby_preview') === 'on';

        $is_gatsby_content_sync_preview
        = \is_preview()
        || isset($_GET['preview_nonce'])
        || isset($_GET['gatsby_preview']);

        return $is_gatsby_content_sync_preview && $enable_gatsby_preview;
    }

    public function setup_preview_template( $template )
    {
        global $post;

        if (empty($post) && isset($_GET['preview_id']) ) {
            $post = get_post($_GET['preview_id']);
        }

        if (self::is_gatsby_content_sync_preview() && $post ) {
            $post_type_object = $post->post_type ? get_post_type_object($post->post_type) : null;

            if ($post_type_object && ! $post_type_object->show_in_graphql ?? true ) {
                return plugin_dir_path(__FILE__) . '../../Admin/includes/post-type-not-shown-in-graphql.php';
            }

            do_action('save_post', $post->ID, $post, true);

            $this->post_to_preview_instance($post->ID, $post);

            return trailingslashit(dirname(__FILE__)) . '../../Admin/includes/preview-template.php';
        }

        return $template;
    }

    public function post_to_preview_instance( $post_ID, $post )
    {
        if (defined('DOING_AUTOSAVE')
            && DOING_AUTOSAVE
            && wp_revisions_enabled($post)
            && $post->post_type !== 'action_monitor'
            && $post->post_status !== 'auto-draft'
            && (      $post->post_status === 'draft'
            || $post->post_date_gmt !== '0000-00-00 00:00:00'      )
            && $post->post_type !== 'revision'
            && self::is_gatsby_content_sync_preview()
        ) {
            $token = \WPGatsby\GraphQL\Auth::get_token();

            if (! $token ) {
                error_log('Please set a JWT token in WPGatsby to enable Preview support.');
                return;
            }

            $preview_webhook = $this::get_gatsby_preview_webhook();

            $original_post = get_post($post->post_parent);

            if ($original_post
                && $original_post->post_modified === $post->post_modified
                && ! self::is_gatsby_content_sync_preview()
            ) {
                return;
            }

            $post_type_object = $original_post
            ? \get_post_type_object($original_post->post_type)
            : \get_post_type_object($post->post_type);

            if (! $post_type_object->show_in_graphql ?? true ) {
                return;
            }

            $global_relay_id = Relay::toGlobalId(
                'post',
                absint($original_post->ID ?? $post_ID)
            );

            $graphql_endpoint = apply_filters('graphql_endpoint', 'graphql');
            $graphql_url = get_site_url() . '/' . ltrim($graphql_endpoint, '/');

            $preview_data = [
            'previewDatabaseId' => $post_ID,
            'id'                => $global_relay_id,
                'singleName'        => lcfirst(
                    $post_type_object->graphql_single_name ?? null
                ),
                'isDraft'           => $post->post_status === 'draft',
            'remoteUrl'         => $graphql_url,
            'modified'          => $post->post_modified,
            'parentDatabaseId'  => $post->post_parent,
            'userDatabaseId'    => get_current_user_id(),
            ];

            $this->log_action(
                [
                'action_type'         => 'UPDATE',
                'title'               => $post->post_title,
                'node_id'             => $original_post->ID ?? $post_ID,
                'relay_id'            => $global_relay_id,
                'graphql_single_name' => lcfirst(
                    $post_type_object->graphql_single_name ?? null
                ),
                'graphql_plural_name' => lcfirst(
                    $post_type_object->graphql_plural_name ?? null
                ),
                'status'              => 'publish',
                'stream_type'         => 'PREVIEW',
                'preview_data'        => wp_json_encode($preview_data),
                ] 
            );

            $response = wp_remote_post(
                $preview_webhook,
                [
                    'body'    => wp_json_encode(
                        array_merge(
                            $preview_data,
                            [ 'token' => $token ]
                        ) 
                    ),
                    'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                    ],
                    'method'  => 'POST',
                ]
            );

            if (\is_wp_error($response) ) {
                    error_log(
                        "WPGatsby couldn\'t POST to the Preview webhook set in plugin options.\nWebhook returned error: {$response->get_error_message()}"
                    );
            }
        }
    }

    static function get_gatsby_preview_webhook()
    {
        $preview_webhook = Settings::get_setting('preview_api_webhook');

        if (! $preview_webhook || ! filter_var($preview_webhook, FILTER_VALIDATE_URL) ) {
            return false;
        }

        if (substr($preview_webhook, -1) !== '/' ) {
            $preview_webhook .= '/';
        }

        return $preview_webhook;
    }
}