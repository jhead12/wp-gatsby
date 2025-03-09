<?php

namespace WPGatsby\ActionMonitor\Monitors;

/**
 * Class TaxonomyMonitor
 *
 * @category  ActionMonitor
 * @package   WPGatsby
 * @author    Your Name <your.email@example.com>
 * @license   MIT License
 * @link      https://github.com/yourusername/WPGatsby
 *
 * This class monitors changes in registered taxonomies and triggers a Schema diff
 * if detected.
 */
class TaxonomyMonitor extends Monitor
{
    /**
     * List of registered taxonomies that Gatsby tracks
     *
     * @var array
     */
    public $current_taxonomies;
    /**
     * List of taxonomies that were previously registered
     *
     * @var array
     */
    public $prev_taxonomies;

    /**
     * Option name used to cache tracked taxonomies
     *
     * @var string
     */
    public $option_name;

    /**
     * Initializes the TaxonomyMonitor
     *
     * Sets the option name for caching taxonomies and hooks into 'gatsby_init_action_monitors'
     * to check for taxonomy changes.
     *
     * @return void
     */
    public function init()
    {
        // Set the option name that's used to cache taxonomies
        $this->option_name = '_gatsby_tracked_taxonomies';

        // Check to see if the taxonomies are different
        add_action('gatsby_init_action_monitors', [ $this, '_checkTaxonomies' ], 999);
    }

    /**
     * Checks for changes in registered taxonomies and triggers a Schema diff if detected.
     *
     * Compares the current list of tracked taxonomies with the previously cached list
     * and updates the cache if there are changes. Triggers a Schema diff if new or removed taxonomies
     * are detected.
     *
     * @return void
     */
    private function _checkTaxonomies()
    {
        $this->current_taxonomies = array_keys($this->action_monitor->get_tracked_taxonomies());
        $this->prev_taxonomies    = get_option($this->option_name, []);

        if (empty($this->prev_taxonomies) ) {
            update_option($this->option_name, $this->current_taxonomies);
            return;
        }

        // Check for changes in taxonomies
        if ($this->areTaxonomiesDifferent() ) {
            update_option($this->option_name, $this->current_taxonomies);

            $added   = array_diff($this->current_taxonomies, $this->prev_taxonomies);
            $removed = array_diff($this->prev_taxonomies, $this->current_taxonomies);

            if (! empty($added) ) {
                $this->trigger_schema_diff(
                    [
                        'title' => __('Taxonomy added', 'WPGatsby'),
                    ]
                );
            }

            if (! empty($removed) ) {
                $this->trigger_schema_diff(
                    [
                        'title' => __('Taxonomy removed', 'WPGatsby'),
                    ]
                );
            }
        }
    }

    /**
     * Determines if the current taxonomies are different from the previous ones.
     *
     * @return bool True if the current taxonomies differ from the previous ones, false otherwise.
     */
    private function _areTaxonomiesDifferent()
    {
        return $this->current_taxonomies !== $this->prev_taxonomies;
    }
}
