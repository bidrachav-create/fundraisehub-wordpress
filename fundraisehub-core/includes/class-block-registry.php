<?php
/**
 * Block Registry – registers all Gutenberg blocks provided by FundRaiseHub Core.
 *
 * @package FundRaiseHub\Core
 */

declare( strict_types=1 );

namespace FundRaiseHub\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BlockRegistry
 *
 * Discovers and registers every block whose metadata lives in a
 * `blocks/{block-name}/block.json` file inside the plugin.
 */
class BlockRegistry {

	/**
	 * Hook the registration callback into WordPress.
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_blocks' ] );
	}

	/**
	 * Register all blocks found in the blocks/ directory.
	 *
	 * Each block must have a `block.json` metadata file as required by
	 * the block API v2 / v3 specification.
	 */
	public function register_blocks(): void {
		$blocks_dir = FUNDRAISEHUB_CORE_DIR . 'blocks';

		if ( ! is_dir( $blocks_dir ) ) {
			return;
		}

		$entries = glob( trailingslashit( $blocks_dir ) . '*', GLOB_ONLYDIR );

		if ( ! $entries ) {
			return;
		}

		foreach ( $entries as $block_dir ) {
			if ( file_exists( $block_dir . '/block.json' ) ) {
				register_block_type( $block_dir );
			}
		}
	}
}
