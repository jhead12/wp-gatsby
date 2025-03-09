<?php

namespace WPGatsby\Admin;

class Settings
{

    const PLUGIN_NAME = 'WPGatsby';
    const OPTION_KEY = 'wpgatsby_settings';

    private $settings_api;
    private $settings_sections;
    private $settings_fields;

    function __construct()
    {
        $this->settings_api = new \WPGraphQL_Settings_API();
        add_action('init', [ $this, 'set_default_jwt_key' ]);
        add_action('admin_init', [ $this, 'admin_init' ]);
        add_action('admin_menu', [ $this, 'register_settings_page' ]);
        add_filter('graphql_setting_field_config', [ $this, 'filter_graphql_introspection_setting_field' ], 10, 3);
        add_filter('graphql_get_setting_section_field_value', [ $this, 'filter_graphql_introspection_setting_value' ], 10, 5);

        $this->settings_sections = $this->get_settings_sections();
        $this->settings_fields = $this->get_settings_fields();
    }

    public function set_default_jwt_key()
    {
        $secret = self::get_setting('preview_jwt_secret');
        if (empty($secret) ) {
            $options = get_option(self::OPTION_KEY, []);
            $options['preview_jwt_secret'] = self::generate_secret();
            update_option(self::OPTION_KEY, $options);
        }
    }

    public function filter_graphql_introspection_setting_field( $field_config, $field_name, $section )
    {
        if ('graphql_general_settings' === $section && 'public_introspection_enabled' === $field_name ) {
            $field_config['value'] = 'on';
            $field_config['disabled'] = true;
            $field_config['desc'] .= sprintf(__('Force enabled by %s. Gatsby requires WPGraphQL introspection to communicate with WordPress.', self::PLUGIN_NAME), self::PLUGIN_NAME);
        }
        return $field_config;
    }

    public function filter_graphql_introspection_setting_value( $value, $default, $field_name, $section_fields, $section_name )
    {
        if ('graphql_general_settings' === $section_name && 'public_introspection_enabled' === $field_name ) {
            return 'on';
        }
        return $value;
    }

    function admin_init()
    {
        $this->settings_api->set_sections($this->settings_sections);
        $this->settings_api->set_fields($this->settings_fields);
        $this->settings_api->admin_init();
    }

    function register_settings_page()
    {
        add_options_page(
            self::PLUGIN_NAME,
            self::PLUGIN_NAME,
            'manage_options',
            self::OPTION_KEY,
            [
            $this,
            'plugin_page',
            ]
        );
    }

    private function get_settings_sections()
    {
        return [
        [
        'id' => self::OPTION_KEY,
        'title' => __('Settings', self::PLUGIN_NAME),
        ],
        ];
    }

    public static function prefix_get_option( $option, $section, $default = '' )
    {
        $options = get_option($section);
        return isset($options[ $option ]) ? $options[ $option ] : $default;
    }

    private static function generate_secret()
    {
        $factory = new \RandomLib\Factory();
        $generator = $factory->getMediumStrengthGenerator();
        return $generator->generateString(50);
    }

    private static function get_default_secret()
    {
        $secret = self::get_setting('preview_jwt_secret');
        return $secret ?: self::generate_secret();
    }

    public static function sanitize_url_field( $input )
    {
        $urls = array_map('trim', explode(',', $input));
        if (count($urls) > 1 ) {
            $validated_urls = array_filter(
                $urls,
                function ( $url ) {
                    return filter_var($url, FILTER_VALIDATE_URL);
                }
            );
            return implode(',', $validated_urls);
        }
        return filter_var($input, FILTER_VALIDATE_URL);
    }

    public static function get_setting( $key )
    {
        $wpgatsby_settings = get_option(self::OPTION_KEY);
        return $wpgatsby_settings[ $key ] ?? null;
    }

    private function get_settings_fields()
    {
        return [
        self::OPTION_KEY => [
        [
        'name' => 'enable_gatsby_preview',
        'label' => __('Enable Gatsby Preview?', self::PLUGIN_NAME),
        'desc' => __('Yes, allow Gatsby to take over WordPress previews.', self::PLUGIN_NAME),
        'type' => 'checkbox',
        ],
        [
                    'name' => 'preview_api_webhook',
                    'label' => __('Preview Webhook URL', self::PLUGIN_NAME),
                    'desc' => __('Use a comma-separated list to configure multiple webhooks.', self::PLUGIN_NAME),
                    'placeholder' => __('https://', self::PLUGIN_NAME),
                    'type' => 'text',
                    'sanitize_callback' => function ( $input ) {
                        return self::sanitize_url_field($input);
                    },
        ],
        [
                    'name' => 'builds_api_webhook',
                    'label' => __('Builds Webhook URL', self::PLUGIN_NAME),
                    'desc' => __('Use a comma-separated list to configure multiple webhooks.', self::PLUGIN_NAME),
                    'placeholder' => __('https://', self::PLUGIN_NAME),
                    'type' => 'text',
                    'sanitize_callback' => function ( $input ) {
                        return self::sanitize_url_field($input);
                    },
        ],
        [
                    'name' => 'gatsby_content_sync_url',
                    'label' => __('Gatsby Content Sync URL', self::PLUGIN_NAME),
                    'desc' => __('Find this URL in your Gatsbyjs.com dashboard settings.', self::PLUGIN_NAME),
                    'placeholder' => __('https://', self::PLUGIN_NAME),
                    'type' => 'text',
                    'sanitize_callback' => function ( $input ) {
                        return self::sanitize_url_field($input);
                    },
        ],
        [
                    'name' => 'preview_jwt_secret',
                    'label' => __('Preview JWT Secret', self::PLUGIN_NAME),
                    'desc' => __('This secret is used in the encoding and decoding of the JWT token. If the Secret were ever changed on the server, ALL tokens that were generated with the previous Secret would become invalid. So, if you wanted to invalidate all user tokens, you can change the Secret on the server and all previously issued tokens would become invalid and require users to re-authenticate.', self::PLUGIN_NAME),
                    'type' => 'password',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => self::get_default_secret(),
        ],
        [
                    'name' => 'enable_gatsby_locations',
                    'label' => __('Enable Gatsby Menu Locations?', self::PLUGIN_NAME),
                    'desc' => __('Yes', self::PLUGIN_NAME),
                    'type' => 'checkbox',
                    'default' => 'on',
        ],
        ],
        ];
    }

    function plugin_page()
    {
        ?>
<div class="wrap">
    <div class="notice notice-info">
        <p>
            <a target="_blank" href="<?php echo esc_url('https://github.com/gatsbyjs/gatsby/blob/master/packages/gatsby-source-wordpress/docs/tutorials/configuring-wp-gatsby.md'); ?>">
        <?php printf(__('Learn how to configure %s here.', self::PLUGIN_NAME), self::PLUGIN_NAME); ?>
            </a>
        </p>
    </div>
    <h2><?php echo esc_html__('Settings', self::PLUGIN_NAME); ?></h2>
    <form method="post" action="options.php">
        <?php
        settings_fields(self::OPTION_KEY);
        do_settings_sections(self::OPTION_KEY);
        submit_button();
        ?>
    </form>
</div>
        <?php
    }
}
