<?php
/**
 * Seeds the taken for the A–Z index tests and a page holding the block with
 * four taken per page. Run via `wp eval-file`. Idempotent. Prints JSON with
 * the page url.
 */

$taken = array(
	array( 'Activiteitencommissie', 'Organiseert de nieuwjaarsborrel en de BBQ.', 'Paar keer per jaar', 'Esther' ),
	array( 'Barcommissie', 'Regelt inkoop en rooster voor de bar.', 'Wekelijks rooster', 'Anouk' ),
	array( 'Éénmalige klus: piano verhuizen', 'De vleugel moet naar de Dorpskerk.', 'Op afroep', 'Gert' ),
	array( 'Kampleiding jeugd', 'Gaat mee op het muziekkamp.', 'Eén weekend in juni', 'Fleur' ),
	array( 'Kascommissie', 'Controleert de boeken van de penningmeester.', 'Eens per jaar', 'Wim' ),
	array( 'Kerstconcert organisatie', 'Regelt kerk en kaartverkoop voor het kerstconcert.', 'Okt t/m dec', 'Mirjam' ),
	array( 'Klusploeg', 'Onze timmermannen. Repareren wat stuk gaat.', 'Zaterdagochtend', 'Gert' ),
	array( 'Opbouwploeg', 'Stoelen en podium neerzetten voor een concert.', 'Per concert', 'Bas' ),
	array( 'Schoonmaakploeg Muziekcentrum', 'Zorgt dat het muziekcentrum er netjes uitziet.', 'Elke maandag', 'Klaas' ),
	array( 'Tuinploeg', 'Houdt de tuin rond het muziekcentrum bij.', 'Eens per maand', 'Frans' ),
);

foreach ( $taken as list( $title, $intro, $wanneer, $contact ) ) {
	$existing = get_posts(
		array(
			'post_type'   => 'soli_taak',
			'post_status' => 'publish',
			'title'       => $title,
			'numberposts' => 1,
		)
	);
	if ( $existing ) {
		continue;
	}

	wp_insert_post(
		array(
			'post_type'    => 'soli_taak',
			'post_status'  => 'publish',
			'post_title'   => $title,
			// Older than the base fixture, so the newest-first Query Loop tests still see it.
			'post_date'    => '2020-01-01 12:00:00',
			// The markup of the "Taak" pattern, as an editor would fill it in.
			'post_content' => implode(
				"\n\n",
				array(
					"<!-- wp:paragraph -->\n<p>{$intro}</p>\n<!-- /wp:paragraph -->",
					'<!-- wp:group {"className":"soli-taak-details","layout":{"type":"flex","flexWrap":"wrap"}} -->',
					'<div class="wp-block-group soli-taak-details">',
					"<!-- wp:paragraph {\"className\":\"soli-taak-details__when\"} -->\n<p class=\"soli-taak-details__when\"><strong>Wanneer:</strong> {$wanneer}</p>\n<!-- /wp:paragraph -->",
					"<!-- wp:paragraph {\"className\":\"soli-taak-details__contact\"} -->\n<p class=\"soli-taak-details__contact\"><strong>Contact:</strong> {$contact}</p>\n<!-- /wp:paragraph -->",
					'</div>',
					'<!-- /wp:group -->',
				)
			),
		)
	);
}

$page_title    = 'Taken A-Z';
$existing_page = get_posts(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'title'       => $page_title,
		'numberposts' => 1,
	)
);

$page_id = $existing_page ? $existing_page[0]->ID : wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $page_title,
		'post_content' => '<!-- wp:soli-taken/taken-index {"perPage":4} /-->',
	)
);

echo wp_json_encode( array( 'pageUrl' => get_permalink( $page_id ) ) );
