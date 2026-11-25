// Builds the blocks in src/*/block.json plus the standalone Query Loop variation
// script (src/index.js), which wp-scripts would skip once block.json files exist.
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: () => ( {
		...( typeof defaultConfig.entry === 'function'
			? defaultConfig.entry()
			: defaultConfig.entry ),
		index: './src/index.js',
	} ),
};
