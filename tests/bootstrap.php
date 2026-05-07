<?php
/**
 * PHPUnit bootstrap file for FundRaiseHub WordPress plugins.
 *
 * This file sets up the minimum environment required to run unit tests
 * without a full WordPress install. Integration tests that need the WP
 * test library should extend this to load wp-tests-config.php.
 */

declare( strict_types=1 );

// Load WordPress function / class stubs before the Composer autoloader so
// that the ABSPATH guard in every plugin class file passes when PHPUnit first
// autoloads a class during a test.
require_once __DIR__ . '/stubs.php';

// Autoload classes via Composer.
$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! file_exists( $autoload ) ) {
	echo "Composer autoloader not found. Run `composer install` first.\n";
	exit( 1 );
}

require_once $autoload;
