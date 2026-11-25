<?php

namespace Soli\Taken;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The "Taak" block pattern: an intro paragraph and a details group with a
 * "when" and a "contact" paragraph. New taken start from it, and the taken
 * index reads the two detail lines back by their class names.
 */
class Pattern {

	const SLUG          = 'soli-taken/taak';
	const DETAILS_CLASS = 'soli-taak-details';
	const WHEN_CLASS    = 'soli-taak-details__when';
	const CONTACT_CLASS = 'soli-taak-details__contact';

	public function init() {
		add_action( 'init', array( $this, 'register_pattern' ) );
		add_action( 'init', array( $this, 'register_style' ) );
	}

	public function register_pattern() {
		register_block_pattern_category( 'soli-taken', array( 'label' => __( 'Taken', 'soli-taken' ) ) );

		register_block_pattern(
			self::SLUG,
			array(
				'title'       => __( 'Taak', 'soli-taken' ),
				'description' => __( 'Description of a taak with when and whom to contact.', 'soli-taken' ),
				'categories'  => array( 'soli-taken' ),
				'postTypes'   => array( Post_Type::POST_TYPE ),
				'content'     => self::content(),
			)
		);
	}

	public function register_style() {
		wp_enqueue_block_style(
			'core/group',
			array(
				'handle' => 'soli-taken-taak-details',
				'src'    => SOLI_TAKEN__PLUGIN_DIR_URL . 'assets/css/taak-details.css',
				'path'   => SOLI_TAKEN__PLUGIN_DIR_PATH . 'assets/css/taak-details.css',
				'ver'    => SOLI_TAKEN__PLUGIN_VERSION,
			)
		);
	}

	private static function content() {
		$paragraph = fn( $html, $class = '' ) => $class
			? sprintf( "<!-- wp:paragraph {\"className\":\"%1\$s\"} -->\n<p class=\"%1\$s\">%2\$s</p>\n<!-- /wp:paragraph -->", $class, $html )
			: sprintf( "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->", $html );

		return implode(
			"\n\n",
			array(
				$paragraph( esc_html__( 'What the taak is about and who already helps.', 'soli-taken' ) ),
				sprintf( '<!-- wp:group {"className":"%1$s","layout":{"type":"flex","flexWrap":"wrap"}} -->', self::DETAILS_CLASS ),
				sprintf( '<div class="wp-block-group %s">', self::DETAILS_CLASS ),
				$paragraph( '<strong>' . esc_html__( 'When:', 'soli-taken' ) . '</strong> ' . esc_html__( 'Every Monday', 'soli-taken' ), self::WHEN_CLASS ),
				$paragraph( '<strong>' . esc_html__( 'Contact:', 'soli-taken' ) . '</strong> ' . esc_html__( 'Name', 'soli-taken' ), self::CONTACT_CLASS ),
				'</div>',
				'<!-- /wp:group -->',
			)
		);
	}

	/**
	 * Reads the pattern's detail lines from a taak's content, without their
	 * bold label. Missing lines come back as ''.
	 *
	 * @return array{ when: string, contact: string, rest: string } `rest` is
	 *         the text of every other block, for the summary.
	 */
	public static function details( $content ) {
		$found = array(
			'when'    => '',
			'contact' => '',
			'rest'    => '',
		);
		self::walk( parse_blocks( $content ), $found );
		$found['rest'] = trim( preg_replace( '/\s+/u', ' ', $found['rest'] ) );

		return $found;
	}

	private static function walk( $blocks, &$found ) {
		foreach ( $blocks as $block ) {
			$classes = explode( ' ', $block['attrs']['className'] ?? '' );

			if ( in_array( self::WHEN_CLASS, $classes, true ) ) {
				$found['when'] = self::without_label( $block['innerHTML'] );
			} elseif ( in_array( self::CONTACT_CLASS, $classes, true ) ) {
				$found['contact'] = self::without_label( $block['innerHTML'] );
			} elseif ( $block['innerBlocks'] ) {
				self::walk( $block['innerBlocks'], $found );
			} else {
				$found['rest'] .= ' ' . wp_strip_all_tags( $block['innerHTML'] );
			}
		}
	}

	/**
	 * Drops a leading <strong>…</strong> label, so "When: Every Monday" reads
	 * "Every Monday" whatever the label says.
	 */
	private static function without_label( $html ) {
		$html = preg_replace( '#^\s*<p[^>]*>\s*<strong>.*?</strong>#su', '', $html );
		return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
	}
}
