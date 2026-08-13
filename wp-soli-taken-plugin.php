<?php

namespace Soli\Taken;

/*
  Plugin Name: Soli Taken Plugin
  Version: 0.1.0
  Author: Joran Out
  Description: Members-only tasks (taken) post type for Soli sites, usable in the Query Loop block
  Requires PHP: 8.2
  Text Domain: soli-taken
  Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SOLI_TAKEN__PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'SOLI_TAKEN__PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SOLI_TAKEN__PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'SOLI_TAKEN__PLUGIN_VERSION', '0.1.0' );

require_once SOLI_TAKEN__PLUGIN_DIR_PATH . 'includes/class-soli-taken-post-type.php';
require_once SOLI_TAKEN__PLUGIN_DIR_PATH . 'includes/class-soli-taken-visibility.php';

add_action( 'init', function () {
	load_plugin_textdomain( 'soli-taken', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	include_once 'updater.php';

	if ( ! defined( 'WP_GITHUB_FORCE_UPDATE' ) ) {
		define( 'WP_GITHUB_FORCE_UPDATE', true );
	}

	if ( is_admin() ) {
		$config = array(
			'slug'               => plugin_basename( __FILE__ ),
			'proper_folder_name' => dirname( plugin_basename( __FILE__ ) ),
			'api_url'            => 'https://api.github.com/repos/JoranOut/wp-soli-taken-plugin',
			'raw_url'            => 'https://raw.githubusercontent.com/JoranOut/wp-soli-taken-plugin/main',
			'github_url'         => 'https://github.com/JoranOut/wp-soli-taken-plugin',
			// Fallback only. The updater resolves the real download from the GitHub
			// releases API and overrides this with the release's zip asset.
			'zip_url'            => 'https://github.com/JoranOut/wp-soli-taken-plugin/releases/latest/download/wp-soli-taken-plugin.zip',
			'sslverify'          => true,
			'requires'           => '6.0.0',
			'tested'             => '6.7.0',
			'readme'             => 'readme.md',
		);

		new WP_GitHub_Updater( $config );
	}
} );

// Register the taken post type and its Query Loop variation script
$soli_taken_post_type = new Post_Type();
$soli_taken_post_type->init();

// Keep taken invisible to not-logged-in visitors
$soli_taken_visibility = new Visibility();
$soli_taken_visibility->init();
