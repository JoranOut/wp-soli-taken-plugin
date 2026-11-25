<?php

namespace Soli\Taken;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The soli-taken/taken-index block: a searchable A–Z list of all published
 * taken. Pages are built from whole letter groups, so a letter never spans
 * two pages. Search and page travel as plain GET parameters, so it works
 * without JavaScript.
 */
class Taken_Index {

	const SEARCH_PARAM = 'taken_zoek';
	const PAGE_PARAM   = 'taken_pagina';
	const ANCHOR       = 'taken-index';

	public function init() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	public function register_block() {
		$block = register_block_type( SOLI_TAKEN__PLUGIN_DIR_PATH . 'build/taken-index' );

		// Core only looks in wp-content/languages for block script translations.
		foreach ( $block ? $block->editor_script_handles : array() as $handle ) {
			wp_set_script_translations( $handle, 'soli-taken', SOLI_TAKEN__PLUGIN_DIR_PATH . 'languages' );
		}
	}

	/**
	 * @param array $attributes Block attributes.
	 * @return string Escaped HTML.
	 */
	public static function render( $attributes ) {
		$wrapper = get_block_wrapper_attributes( array( 'id' => self::ANCHOR ) );

		if ( ! is_user_logged_in() ) {
			return sprintf(
				'<div %s><p class="taken-index__none">%s <a href="%s">%s</a></p></div>',
				$wrapper,
				esc_html__( 'Taken are only visible to members.', 'soli-taken' ),
				esc_url( wp_login_url( get_permalink() ) ),
				esc_html__( 'Log in', 'soli-taken' )
			);
		}

		$per_page = max( 1, (int) ( $attributes['perPage'] ?? 10 ) );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only GET filter.
		$query = isset( $_GET[ self::SEARCH_PARAM ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::SEARCH_PARAM ] ) ) : '';
		$page  = isset( $_GET[ self::PAGE_PARAM ] ) ? absint( $_GET[ self::PAGE_PARAM ] ) : 1;
		// phpcs:enable

		$terms   = self::terms( $query );
		$entries = self::filter( self::entries(), $terms );
		$pages   = self::paginate( self::group( $entries ), $per_page );
		$page    = min( max( 1, $page ), max( 1, count( $pages ) ) );
		$current = $pages[ $page - 1 ] ?? array();

		$html  = '<div ' . $wrapper . '>';
		$html .= self::render_head( $query, count( $entries ) );
		if ( $entries ) {
			$html .= self::render_letters( $pages, $page, $query );
			$html .= '<div class="taken-index__cols">';
			foreach ( $current as $letter => $items ) {
				$html .= self::render_letter( $letter, $items, $terms );
			}
			$html .= '</div>';
			$html .= self::render_pager( $pages, $page, $query );
		} else {
			$html .= self::render_none( $query );
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * All published taken as plain arrays, with a lowercase, accent-free
	 * haystack for matching.
	 */
	private static function entries() {
		$posts = get_posts(
			array(
				'post_type'      => Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$entries = array();
		foreach ( $posts as $post ) {
			$details = Pattern::details( $post->post_content );
			$entry   = array(
				'title'   => get_the_title( $post ),
				'url'     => get_permalink( $post ),
				// Built from the content minus the details, which get their own line.
				'excerpt' => has_excerpt( $post )
					? wp_strip_all_tags( get_the_excerpt( $post ) )
					: wp_trim_words( $details['rest'], 30 ),
				'when'    => $details['when'],
				'contact' => $details['contact'],
			);

			$entry['haystack'] = self::normalize(
				implode( ' ', array( $entry['title'], $entry['excerpt'], wp_strip_all_tags( $post->post_content ) ) )
			);
			$entry['sort']     = self::normalize( $entry['title'] );
			$entries[]         = $entry;
		}

		usort( $entries, fn( $a, $b ) => strnatcmp( $a['sort'], $b['sort'] ) );

		return $entries;
	}

	private static function normalize( $text ) {
		return mb_strtolower( remove_accents( html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
	}

	private static function terms( $query ) {
		return array_values( array_filter( preg_split( '/\s+/u', self::normalize( $query ) ) ) );
	}

	/**
	 * Every term must occur somewhere in the entry; no terms keeps everything.
	 */
	private static function filter( $entries, $terms ) {
		if ( ! $terms ) {
			return $entries;
		}

		return array_values(
			array_filter(
				$entries,
				function ( $entry ) use ( $terms ) {
					foreach ( $terms as $term ) {
						if ( false === strpos( $entry['haystack'], $term ) ) {
							return false;
						}
					}
					return true;
				}
			)
		);
	}

	/**
	 * Titles that do not start with A–Z (digits, quotes) go under '#'.
	 */
	private static function letter_of( $entry ) {
		$first = strtoupper( substr( $entry['sort'], 0, 1 ) );
		return preg_match( '/^[A-Z]$/', $first ) ? $first : '#';
	}

	private static function group( $entries ) {
		$groups = array();
		foreach ( $entries as $entry ) {
			$groups[ self::letter_of( $entry ) ][] = $entry;
		}

		if ( isset( $groups['#'] ) ) {
			$hash = $groups['#'];
			unset( $groups['#'] );
			$groups = array( '#' => $hash ) + $groups;
		}

		return $groups;
	}

	/**
	 * Fills each page with whole letter groups up to $per_page entries. A letter
	 * larger than $per_page gets a page of its own rather than being split.
	 *
	 * @return array[] List of pages, each a map of letter => entries.
	 */
	private static function paginate( $groups, $per_page ) {
		$pages = array();
		$page  = array();
		$count = 0;

		foreach ( $groups as $letter => $items ) {
			if ( $count && $count + count( $items ) > $per_page ) {
				$pages[] = $page;
				$page    = array();
				$count   = 0;
			}
			$page[ $letter ] = $items;
			$count          += count( $items );
		}

		if ( $page ) {
			$pages[] = $page;
		}

		return $pages;
	}

	private static function url( $query, $page = 1 ) {
		$args = array(
			self::SEARCH_PARAM => '' !== $query ? $query : false,
			self::PAGE_PARAM   => $page > 1 ? $page : false,
		);

		return add_query_arg( $args, get_permalink() ) . '#' . self::ANCHOR;
	}

	/**
	 * Wraps every term occurrence in <mark>. Matching is case-insensitive but
	 * not accent-insensitive, so "cafe" finds "café" without marking it.
	 */
	private static function highlight( $text, $terms ) {
		if ( ! $terms ) {
			return esc_html( $text );
		}

		$pattern = '/(' . implode( '|', array_map( fn( $t ) => preg_quote( $t, '/' ), $terms ) ) . ')/iu';
		$parts   = preg_split( $pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		$html    = '';
		foreach ( $parts as $i => $part ) {
			$html .= $i % 2 ? '<mark>' . esc_html( $part ) . '</mark>' : esc_html( $part );
		}

		return $html;
	}

	private static function render_head( $query, $total ) {
		// A GET form drops the action's own query string, so plain-permalink
		// pages (?page_id=12) carry those arguments as hidden fields.
		$action = get_permalink();
		$hidden = '';
		wp_parse_str( (string) wp_parse_url( $action, PHP_URL_QUERY ), $action_args );
		foreach ( $action_args as $name => $value ) {
			$hidden .= sprintf( '<input type="hidden" name="%s" value="%s">', esc_attr( $name ), esc_attr( $value ) );
		}

		if ( '' === $query ) {
			/* translators: %d: number of taken */
			$summary = esc_html( sprintf( _n( '%d taak', 'All %d taken, alphabetical', $total, 'soli-taken' ), $total ) );
		} else {
			/* translators: 1: number of results, 2: search term */
			$summary  = esc_html( sprintf( _n( '%1$d taak for “%2$s”', '%1$d taken for “%2$s”', $total, 'soli-taken' ), $total, $query ) );
			$summary .= sprintf( '<a href="%s">%s</a>', esc_url( self::url( '' ) ), esc_html__( 'Show all', 'soli-taken' ) );
		}

		return sprintf(
			'<div class="taken-index__head">
				<form class="taken-index__search" role="search" method="get" action="%1$s#%2$s">%3$s
					<input type="search" name="%4$s" value="%5$s" placeholder="%6$s" aria-label="%7$s">
					<button type="submit" aria-label="%8$s"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></button>
				</form>
				<p class="taken-index__summary">%9$s</p>
			</div>',
			esc_url( $action ),
			esc_attr( self::ANCHOR ),
			$hidden,
			esc_attr( self::SEARCH_PARAM ),
			esc_attr( $query ),
			esc_attr__( 'What are you looking for?', 'soli-taken' ),
			esc_attr__( 'Search taken', 'soli-taken' ),
			esc_attr__( 'Search', 'soli-taken' ),
			$summary
		);
	}

	private static function render_letters( $pages, $current, $query ) {
		$page_of = array();
		foreach ( $pages as $i => $page ) {
			foreach ( array_keys( $page ) as $letter ) {
				$page_of[ $letter ] = $i + 1;
			}
		}

		$letters = array_merge( isset( $page_of['#'] ) ? array( '#' ) : array(), range( 'A', 'Z' ) );
		$html    = '<ul class="taken-index__az" aria-label="' . esc_attr__( 'Jump to letter', 'soli-taken' ) . '">';
		foreach ( $letters as $letter ) {
			if ( ! isset( $page_of[ $letter ] ) ) {
				$html .= '<li><span aria-disabled="true">' . esc_html( $letter ) . '</span></li>';
				continue;
			}
			$is_current = $page_of[ $letter ] === $current;
			$html      .= sprintf(
				'<li><a href="%s"%s>%s</a></li>',
				esc_url( self::url( $query, $page_of[ $letter ] ) ),
				$is_current ? ' class="is-current" aria-current="page"' : '',
				esc_html( $letter )
			);
		}

		return $html . '</ul>';
	}

	private static function render_letter( $letter, $items, $terms ) {
		$html = '<section class="taken-index__letter"><h2>' . esc_html( $letter ) . '</h2>';

		foreach ( $items as $item ) {
			$html .= '<article class="taken-index__item">';
			$html .= sprintf( '<h3><a href="%s">%s</a></h3>', esc_url( $item['url'] ), self::highlight( $item['title'], $terms ) );
			if ( '' !== $item['excerpt'] ) {
				$html .= '<p>' . self::highlight( $item['excerpt'], $terms ) . '</p>';
			}

			$meta = array_filter(
				array(
					__( 'When', 'soli-taken' )    => $item['when'],
					__( 'Contact', 'soli-taken' ) => $item['contact'],
				),
				fn( $value ) => '' !== $value
			);
			if ( $meta ) {
				$html .= '<dl class="taken-index__meta">';
				foreach ( $meta as $label => $value ) {
					$html .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . self::highlight( $value, $terms ) . '</dd></div>';
				}
				$html .= '</dl>';
			}
			$html .= '</article>';
		}

		return $html . '</section>';
	}

	private static function render_pager( $pages, $page, $query ) {
		$total = count( $pages );
		if ( $total < 2 ) {
			return '';
		}

		$letters = array_keys( $pages[ $page - 1 ] );
		$range   = reset( $letters ) === end( $letters ) ? reset( $letters ) : reset( $letters ) . '–' . end( $letters );

		$prev = $page > 1
			? sprintf( '<a href="%s" rel="prev">%s</a>', esc_url( self::url( $query, $page - 1 ) ), esc_html__( '← Previous', 'soli-taken' ) )
			: '<span class="is-disabled">' . esc_html__( '← Previous', 'soli-taken' ) . '</span>';
		$next = $page < $total
			? sprintf( '<a href="%s" rel="next">%s</a>', esc_url( self::url( $query, $page + 1 ) ), esc_html__( 'Next →', 'soli-taken' ) )
			: '<span class="is-disabled">' . esc_html__( 'Next →', 'soli-taken' ) . '</span>';

		return sprintf(
			'<nav class="taken-index__pager" aria-label="%s">%s<span class="taken-index__position">%s</span>%s</nav>',
			esc_attr__( 'Pages', 'soli-taken' ),
			$prev,
			/* translators: 1: letter range such as A–K, 2: current page, 3: total pages */
			esc_html( sprintf( __( '%1$s · page %2$d of %3$d', 'soli-taken' ), $range, $page, $total ) ),
			$next
		);
	}

	private static function render_none( $query ) {
		return sprintf(
			'<div class="taken-index__none"><strong>%s</strong>%s<br><a href="%s">%s</a></div>',
			/* translators: %s: search term */
			esc_html( sprintf( __( 'No taken found for “%s”', 'soli-taken' ), $query ) ),
			esc_html__( 'Try another word, or look through all taken.', 'soli-taken' ),
			esc_url( self::url( '' ) ),
			esc_html__( 'Show all taken', 'soli-taken' )
		);
	}
}
