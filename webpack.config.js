// webpack.config.js
//
// Extends the default @wordpress/scripts webpack configuration.
// Add block entry points here as you create new blocks or assets.

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

const blocksDir = path.resolve( __dirname, 'fundraisehub-core/blocks' );

const blockEntry = ( slug, hasView = true ) => {
	const entries = {
		[ `blocks/${ slug }/index` ]: path.resolve(
			blocksDir,
			slug,
			'index.js'
		),
	};
	if ( hasView ) {
		entries[ `blocks/${ slug }/view` ] = path.resolve(
			blocksDir,
			slug,
			'view.js'
		);
	}
	return entries;
};

module.exports = {
	...defaultConfig,
	entry: {
		// campaign-wrapper has no separate front-end view script.
		...blockEntry( 'campaign-wrapper', false ),

		// Blocks with stub-only view.js – no front-end bundle needed.
		...blockEntry( 'campaign-banner', false ),
		...blockEntry( 'campaign-stats-bar', false ),
		...blockEntry( 'campaign-thermometer', false ),
		...blockEntry( 'campaign-description', false ),
		...blockEntry( 'campaign-teams', false ),
		...blockEntry( 'campaign-video', false ),
		...blockEntry( 'campaign-photo-gallery', false ),
		...blockEntry( 'campaign-comments', false ),

		// Blocks with real front-end JavaScript.
		...blockEntry( 'campaign-donate-button' ),
		...blockEntry( 'campaign-donation-tiles' ),
		...blockEntry( 'campaign-honor-scroll' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'fundraisehub-core/assets' ),
	},
};
