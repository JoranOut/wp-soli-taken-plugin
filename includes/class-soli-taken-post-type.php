<?php

namespace Soli\Taken;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the soli_taak post type and the editor script that adds
 * the "Taken" Query Loop variation.
 */
class Post_Type {

	const POST_TYPE = 'soli_taak';
	const REST_BASE = 'taken';

	public function init() {
		add_action( 'init', array( $this, 'register_post_type' ), 0 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	public function register_post_type() {
		$labels = array(
			'name'               => __( 'Taken', 'soli-taken' ),
			'singular_name'      => __( 'Taak', 'soli-taken' ),
			'add_new'            => __( 'Add Taak', 'soli-taken' ),
			'add_new_item'       => __( 'Add New Taak', 'soli-taken' ),
			'view_item'          => __( 'View Taak', 'soli-taken' ),
			'edit_item'          => __( 'Edit Taak', 'soli-taken' ),
			'insert_into_item'   => __( 'Insert into Taak', 'soli-taken' ),
			'search_items'       => __( 'Search Taken', 'soli-taken' ),
			'not_found'          => __( 'No Taken Found', 'soli-taken' ),
		);

		$supports = array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' );

		$args = array(
			'labels'              => $labels,
			'description'         => __( 'Members-only tasks', 'soli-taken' ),
			'supports'            => $supports,
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'menu_position'       => 6,
			'menu_icon'           => 'dashicons-clipboard',
			'can_export'          => true,
			'has_archive'         => false,
			// Not-logged-in visitors may never find taken through site
			// search; the flag is evaluated per request so members still can.
			'exclude_from_search' => ! is_user_logged_in(),
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
			'rewrite'             => array( 'slug' => 'taken' ),
			'show_in_rest'        => true,
			'rest_base'           => self::REST_BASE,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	public function enqueue_editor_assets() {
		$asset_file = SOLI_TAKEN__PLUGIN_DIR_PATH . 'build/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			'soli-taken-editor',
			SOLI_TAKEN__PLUGIN_DIR_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations(
			'soli-taken-editor',
			'soli-taken',
			SOLI_TAKEN__PLUGIN_DIR_PATH . 'languages'
		);
	}
}
