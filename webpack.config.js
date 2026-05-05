// webpack.config.js
//
// Extends the default @wordpress/scripts webpack configuration.
// Add block entry points here as you create new blocks or assets.

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		// Core plugin admin settings page JS (placeholder).
		// 'fundraisehub-core-admin': path.resolve( __dirname, 'fundraisehub-core/assets/js/admin.js' ),

		// Add block entry points when blocks are created, e.g.:
		// 'campaign-card/index': path.resolve( __dirname, 'fundraisehub-core/blocks/campaign-card/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'fundraisehub-core/assets' ),
	},
};
