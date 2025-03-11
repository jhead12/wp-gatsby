<?php

namespace WPGatsby\Admin;

use WPGraphQL_Settings_API;

class Settings
{

    const PLUGIN_NAME = 'WPGatsby';
    const OPTION_KEY = 'wpgatsby_settings';

    private $settings_api;
    private $settings_sections;
    private $settings_fields;

public function __construct()
{
    $this->settings_api = new WPGraphQL_Settings_API();
    add_action('init', [$this, 'set_default_jwt_key']);
    add_action('admin_init', [$this, 'admin_init']);
    add_action('admin_menu', [$this, 'register_settings_page']);
    add_filter('graphql_setting_field_config', [$this, 'filter_graphql_introspection_setting_field'], 10, 3);
    add_filter('graphql_get_setting_section_field_value', [$this, 'filter_graphql_introspection_setting_value'], 10, 5);

    $this->settings_sections = $this->get_settings_sections();
    $this->settings_fields = $this->get_settings_fields();
}

public function set_default_jwt_key()
{
    $secret = self::get_setting('preview_jwt_secret');
    if (empty($secret)) {
        $options = get_option(self::OPTION_KEY, []);
        $options['preview_jwt_secret'] = self::generate_secret();
        update_option(self::OPTION_KEY, $options);
    }
}

public function filter_graphql_introspection_setting_field($field_config, $field_name, $section)
{
    if ('graphql_general_settings' === $section && 'public_introspection_enabled' === $field_name) {
        $field_config['value'] = 'on';
        $field_config['disabled'] = true;
        $field_config['desc'] .= sprintf(__('Force enabled by %s. Gatsby requires WPGraphQL introspection to communicate with WordPress.', self::PLUGIN_NAME), self::PLUGIN_NAME);
    }
    return $field_config;
}

public function filter_graphql_introspection_setting_value($value, $default, $field_name, $section_fields, $section_name)
{
    if ('graphql_general_settings' === $section_name && 'public_introspection_enabled' === $field_name) {
        return 'on';
    }
    return $value;
}

public function admin_init()
    {
        $this->settings_api->set_sections($this->settings_sections);
        $this->settings_api->set_fields($this->settings_fields);
        $this
