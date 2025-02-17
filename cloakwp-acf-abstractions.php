<?php

use CloakWP\Core\Enqueue\Script;
use CloakWP\Core\Enqueue\Stylesheet;

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://https://github.com/cloak-labs
 * @package           CloakWP/ACFAbstractions
 *
 * @wordpress-plugin
 * Plugin Name:       CloakWP - ACF Abstractions
 * Plugin URI:        https://https://github.com/cloak-labs/cloakwp-acf-abstractions
 * Description:       A set of OOP abstractions around ACF to improve developer experience.
 * Version:           0.0.1
 * Author:            Cloak Labs
 * Author URI:        https://https://github.com/cloak-labs
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       cloakwp
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
  die;
}

Stylesheet::make("cloakwp_acf_block_styles")
  ->hook("enqueue_block_editor_assets")
  ->src(plugin_dir_url(__FILE__) . '/css/acf-block.css')
  ->version(\WP_ENV === "development" ? filemtime(plugin_dir_path(__FILE__) . '/css/acf-block.css') : '0.0.1')
  ->enqueue();

Stylesheet::make("cloakwp_acf_tooltip_styles")
  ->hook("enqueue_block_editor_assets")
  ->src(plugin_dir_url(__FILE__) . '/css/acf-tooltip.css')
  ->version(\WP_ENV === "development" ? filemtime(plugin_dir_path(__FILE__) . '/css/acf-tooltip.css') : '0.0.1')
  ->enqueue();

Script::make("cloakwp_acf_tooltip_script")
  ->hook("enqueue_block_editor_assets")
  ->src(plugin_dir_url(__FILE__) . '/js/acf-tooltip.js')
  ->deps(["jquery"])
  ->version(\WP_ENV === "development" ? filemtime(plugin_dir_path(__FILE__) . '/js/acf-tooltip.js') : '0.0.1')
  ->enqueue();
