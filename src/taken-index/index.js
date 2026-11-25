import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';
import './style.scss';

registerBlockType( metadata.name, {
	edit: ( { attributes, setAttributes } ) => (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'soli-taken' ) }>
					<RangeControl
						label={ __( 'Taken per page', 'soli-taken' ) }
						help={ __(
							'A letter is never split over two pages, so a page can hold more when one letter has many taken.',
							'soli-taken'
						) }
						value={ attributes.perPage }
						onChange={ ( perPage ) => setAttributes( { perPage } ) }
						min={ 3 }
						max={ 50 }
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender block={ metadata.name } attributes={ attributes } />
		</div>
	),
} );
