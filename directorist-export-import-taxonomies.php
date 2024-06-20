<?php

/**
 * @package  Directorist - Export Import Taxonomies
 */


/**
 * Plugin Name:       Directorist - Export Import Taxonomies
 * Plugin URI:        https://directorist.com/
 * Description:       This is an extension for Directorist plugin. It helps to you to export and import the taxonomies.
 * Version:           1.1.0
 * Requires at least: 5.2
 * Author:            wpWax
 * Author URI:        https://wpwax.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       directorist-export-import-taxonomies
 * Domain Path:       /languages
 */


/**
 * If this file is called directly, abrot!!!
 */
if (!defined('ABSPATH')) {
    exit;                      // Exit if accessed
}


if (!class_exists('ATDEximTaxonomies')) {
    /**
     * Main plugin class
     */
    final class ATDEximTaxonomies
    {
        /**
         * Created plugin Singleton instance
         *
         * @var string
         */
        private static $instance;


        /**
         * Class constructor
         * 
         * @return object ATDEximTaxonomies 
         */
        public static function instance()
        {
            // Authenticate, is instance create or not
            if (!isset(self::$instance) && !(self::$instance instanceof ATDEximTaxonomies)) {
                self::$instance = new ATDEximTaxonomies();
                self::$instance->initial_functions();
            }

            return self::$instance;
        }

        public function initial_functions()
        {
            include_once('Inc/functions.php');
            add_action('admin_menu', array(self::$instance, 'dir_taxonomies_exim_submenu'));
        }

        public function dir_taxonomies_exim_submenu()
        {
            $this->page_id = add_submenu_page(
                'edit.php?post_type=at_biz_dir',
                __('Taxonomy Export/Import', 'directorist-export-import-taxonomies'),
                __('Taxonomy Export/Import', 'directorist-export-import-taxonomies'),
                'manage_options',
                'taxonomy-export-import',
                array($this, 'dir_taxonomies_exim_layout')
            );
        }

        public function dir_taxonomies_exim_layout()
        {
            include_once('Inc/option_page.php');
        }

        /**
         * Get version from file content
         *
         * @return string
         */
        public static function get_version_from_file_content($file_path = '')
        {
            $version = '';

            if (!file_exists($file_path)) {
                return $version;
            }

            $content = file_get_contents($file_path);
            $version = self::get_version_from_content($content);

            return $version;
        }

        /**
         * Get version from content
         *
         * @return string
         */
        public static function get_version_from_content($content = '')
        {
            $version = '';

            if (preg_match('/\*[\s\t]+?version:[\s\t]+?([0-9.]+)/i', $content, $v)) {
                $version = $v[1];
            }

            return $version;
        }
    } // End class


    /**
     * Initializes the main plugin
     *
     * @return \ATDEximTaxonomies class
     */
    function ATDEximTaxonomies()
    {
        return ATDEximTaxonomies::instance();
    }


    /**
     * Handle plugin activation
     *
     * @return void
     */
    function atd_exim_taxonomies_plugin_activate()
    {
        // Get the Directorist plugin is active or deactive
        if (in_array('directorist/directorist-base.php', (array)get_option('active_plugins'))) {

            // Get Directorist - Compare Listings plugin activation time
            $installed = get_option('atd_exim_taxonomies_installed');
            if (!$installed) {
                update_option('atd_exim_taxonomies_installed', time());
            }
            // Store current version of this plugin.
            update_option('atd_exim_taxonomies_version', '1.0');
        }
    }
    register_activation_hook(__FILE__, 'atd_exim_taxonomies_plugin_activate');



    /**
     * Handle plugin deactivation
     *
     * @return void
     */
    function atd_exim_taxonomies_plugin_deactivate()
    {
        //flush_rewrite_rules();
    }
    register_deactivation_hook(__FILE__, 'atd_exim_taxonomies_plugin_deactivate');

    if (!function_exists('directorist_is_plugin_active')) {
        function directorist_is_plugin_active($plugin)
        {
            return in_array($plugin, (array) get_option('active_plugins', array()), true) || directorist_is_plugin_active_for_network($plugin);
        }
    }

    if (!function_exists('directorist_is_plugin_active_for_network')) {
        function directorist_is_plugin_active_for_network($plugin)
        {
            if (!is_multisite()) {
                return false;
            }

            $plugins = get_site_option('active_sitewide_plugins');
            if (isset($plugins[$plugin])) {
                return true;
            }

            return false;
        }
    }


    /**
     * If the Directorist plugin is activate then Directorist - Compare Listings plugin is run otherwise don't run.
     */
    if (directorist_is_plugin_active('directorist/directorist-base.php')) {
        /**
         * If called this method then Directorist - Compare Listings plugin is run otherwise don't run
         */
        ATDEximTaxonomies(); // get the plugin running
    }
}
