<?php
/**
 * Minimal WordPress stubs for unit tests.
 *
 * Defines constants, the WP_Error class, and all WordPress functions referenced
 * by FundRaiseHub Core classes so that tests run without a live WordPress install.
 *
 * All stubs read from / write to simple globals so tests can inspect and control
 * their behaviour via WPTestState helper methods.
 */

declare( strict_types=1 );

// ---------------------------------------------------------------------------
// Constants required by plugin files.
// ---------------------------------------------------------------------------
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wp/' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

// ---------------------------------------------------------------------------
// WP_Error class stub.
// ---------------------------------------------------------------------------
if ( ! class_exists( 'WP_Error', false ) ) {
	/**
	 * Minimal WP_Error stub.
	 */
	class WP_Error {
		/** @var string */
		private string $code;
		/** @var string */
		private string $message;
		/** @var mixed */
		private mixed $data;

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Human-readable message.
		 * @param mixed  $data    Optional associated data.
		 */
		public function __construct( string $code = '', string $message = '', mixed $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		/** @return string */
		public function get_error_code(): string {
			return $this->code;
		}

		/** @return string */
		public function get_error_message( string $code = '' ): string {
			return $this->message;
		}

		/** @return mixed */
		public function get_error_data( string $code = '' ): mixed {
			return $this->data;
		}
	}
}

// ---------------------------------------------------------------------------
// Global state store used by all stubs.
// ---------------------------------------------------------------------------

/**
 * Helper that tests use to set up and inspect stub state.
 */
class WPTestState {
	/** @var array<string,mixed> */
	public static array $options = array();
	/** @var array<string,mixed> */
	public static array $transients = array();
	/** @var array<string,mixed> */
	public static array $post_meta = array();
	/** @var array<int,mixed> */
	public static array $posts = array();
	/** @var int Counter for the next inserted post ID. */
	public static int $next_post_id = 1;
	/**
	 * Queue of HTTP responses popped by wp_remote_get / wp_remote_post.
	 * Each entry is an array|WP_Error.
	 *
	 * @var list<array<string,mixed>|WP_Error>
	 */
	public static array $http_response_queue = array();
	/** @var int Total times wp_remote_get was called. */
	public static int $http_get_call_count = 0;
	/** @var int Total times wp_remote_post was called. */
	public static int $http_post_call_count = 0;
	/** @var list<string> URLs requested via wp_remote_get. */
	public static array $http_get_urls = array();
	/** @var list<string> URLs requested via wp_remote_post. */
	public static array $http_post_urls = array();
	/** @var list<array<string,mixed>> Full args passed to wp_remote_post. */
	public static array $http_post_args = array();
	/** @var list<array<string,mixed>> Registered settings errors added by add_settings_error(). */
	public static array $settings_errors = array();
	/** @var string|null Last URL passed to wp_safe_redirect(). */
	public static ?string $last_redirect = null;
	/** @var bool Whether flush_rewrite_rules() was called. */
	public static bool $flushed_rewrite_rules = false;
	/** @var bool Return value for current_user_can(). */
	public static bool $current_user_can = false;
	/** @var bool Return value for is_user_logged_in(). */
	public static bool $is_user_logged_in = false;
	/** @var bool Return value for wp_using_ext_object_cache(). */
	public static bool $using_ext_object_cache = false;

	/** Build a default successful HTTP response with a JSON body. */
	public static function http_ok( mixed $data ): array {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => (string) json_encode( $data ),
		);
	}

	/** Build an HTTP error response. */
	public static function http_error( int $code = 500 ): array {
		return array(
			'response' => array( 'code' => $code ),
			'body'     => '',
		);
	}

	/** Reset all state between tests. */
	public static function reset(): void {
		self::$options                = array();
		self::$transients             = array();
		self::$post_meta              = array();
		self::$posts                  = array();
		self::$next_post_id           = 1;
		self::$http_response_queue    = array();
		self::$http_get_call_count    = 0;
		self::$http_post_call_count   = 0;
		self::$http_get_urls          = array();
		self::$http_post_urls         = array();
		self::$http_post_args         = array();
		self::$settings_errors        = array();
		self::$last_redirect          = null;
		self::$flushed_rewrite_rules  = false;
		self::$current_user_can       = false;
		self::$is_user_logged_in      = false;
		self::$using_ext_object_cache = false;
	}

	/** Dequeue the next HTTP response, or return a default 200 OK with {}. */
	public static function dequeue_http_response(): WP_Error|array {
		if ( ! empty( self::$http_response_queue ) ) {
			return array_shift( self::$http_response_queue );
		}
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => '{}',
		);
	}
}

// ---------------------------------------------------------------------------
// Database stub ($wpdb) – minimal shim for direct-query paths in ApiClient.
// ---------------------------------------------------------------------------

// Assign to the global variable that plugin code accesses via `global $wpdb`.
// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
$GLOBALS['wpdb'] = new class() {
	/** @var string */
	public string $options = 'wp_options';

	/**
	 * Escapes SQL LIKE special characters (%, _, \) so they can be used
	 * safely inside a LIKE pattern in a prepared statement.
	 *
	 * @param string $text Raw string to escape.
	 * @return string Escaped string.
	 */
	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}

	/**
	 * @param string $query
	 * @param mixed  ...$args
	 */
	public function prepare( string $query, mixed ...$args ): string {
		// Very minimal: replace %s placeholders with single-quoted values.
		return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
	}

	public function query( string $query ): int|false {
		return 0; // No-op in tests.
	}
};

// ---------------------------------------------------------------------------
// WordPress function stubs – options.
// ---------------------------------------------------------------------------

function get_option( string $key, mixed $default = false ): mixed {
	return WPTestState::$options[ $key ] ?? $default;
}

function update_option( string $key, mixed $value, bool|string $autoload = true ): bool {
	WPTestState::$options[ $key ] = $value;
	return true;
}

function delete_option( string $key ): bool {
	unset( WPTestState::$options[ $key ] );
	return true;
}

// ---------------------------------------------------------------------------
// Transients.
// ---------------------------------------------------------------------------

function get_transient( string $key ): mixed {
	return WPTestState::$transients[ $key ] ?? false;
}

function set_transient( string $key, mixed $value, int $expiration = 0 ): bool {
	WPTestState::$transients[ $key ] = $value;
	return true;
}

function delete_transient( string $key ): bool {
	unset( WPTestState::$transients[ $key ] );
	return true;
}

// ---------------------------------------------------------------------------
// HTTP API.
// ---------------------------------------------------------------------------

function wp_remote_get( string $url, array $args = array() ): WP_Error|array {
	WPTestState::$http_get_call_count++;
	WPTestState::$http_get_urls[] = $url;
	return WPTestState::dequeue_http_response();
}

function wp_remote_post( string $url, array $args = array() ): WP_Error|array {
	WPTestState::$http_post_call_count++;
	WPTestState::$http_post_urls[] = $url;
	WPTestState::$http_post_args[] = $args;
	return WPTestState::dequeue_http_response();
}

function wp_remote_retrieve_response_code( array $response ): int|string {
	return $response['response']['code'] ?? 0;
}

function wp_remote_retrieve_body( array $response ): string {
	return (string) ( $response['body'] ?? '' );
}

// ---------------------------------------------------------------------------
// Error helpers.
// ---------------------------------------------------------------------------

function is_wp_error( mixed $thing ): bool {
	return $thing instanceof WP_Error;
}

// ---------------------------------------------------------------------------
// URL helpers.
// ---------------------------------------------------------------------------

function add_query_arg( array|string $key_or_params, string $value_or_url = '', string $url = '' ): string {
	if ( is_array( $key_or_params ) ) {
		$params   = $key_or_params;
		$base_url = $value_or_url;
	} else {
		$params   = array( $key_or_params => $value_or_url );
		$base_url = $url;
	}
	if ( empty( $params ) ) {
		return $base_url;
	}
	$query = http_build_query( $params );
	$sep   = ( str_contains( $base_url, '?' ) ) ? '&' : '?';
	return $base_url . $sep . $query;
}

function home_url( string $path = '' ): string {
	return 'https://example.org' . $path;
}

function admin_url( string $path = '' ): string {
	return 'https://example.org/wp-admin/' . ltrim( $path, '/' );
}

function get_site_url( int $blog_id = 0, string $path = '', string $scheme = '' ): string {
	return 'https://example.org' . $path;
}

function esc_url_raw( string $url, array $protocols = array() ): string {
	return $url;
}

function wp_safe_redirect( string $location, int $status = 302, string $x_redirect_by = 'WordPress' ): bool {
	WPTestState::$last_redirect = $location;
	throw new WPTestRedirectException( $location );
}

function wp_parse_url( string $url, int $component = -1 ): mixed {
	return parse_url( $url, $component );
}

// ---------------------------------------------------------------------------
// JSON.
// ---------------------------------------------------------------------------

function wp_json_encode( mixed $data, int $flags = 0, int $depth = 512 ): string|false {
	return json_encode( $data, $flags, $depth );
}

// ---------------------------------------------------------------------------
// Sanitisation.
// ---------------------------------------------------------------------------

function sanitize_text_field( string $str ): string {
	return strip_tags( trim( $str ) );
}

function sanitize_key( string $key ): string {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
}

function sanitize_title( string $title, string $fallback_title = '', string $context = 'save' ): string {
	$title = strtolower( $title );
	$title = preg_replace( '/[^a-z0-9\-_]/', '-', $title );
	return trim( $title, '-' );
}

function wp_unslash( string|array $value ): string|array {
	if ( is_array( $value ) ) {
		// Recursively strip slashes from every element without relying on
		// WordPress's stripslashes_deep() helper which is not stubbed here.
		return array_map(
			function ( mixed $item ): mixed {
				if ( is_array( $item ) ) {
					return wp_unslash( $item );
				}
				return is_string( $item ) ? stripslashes( $item ) : $item;
			},
			$value
		);
	}
	return stripslashes( $value );
}

// ---------------------------------------------------------------------------
// Output escaping.
// ---------------------------------------------------------------------------

function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function esc_attr( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function esc_url( string $url, array $protocols = array(), string $context = 'display' ): string {
	return $url;
}

function esc_js( string $text ): string {
	return addslashes( $text );
}

function wp_kses_post( string $data ): string {
	return strip_tags( $data, '<p><br><strong><em><a><ul><ol><li><h1><h2><h3><h4><h5><h6><img><span><div>' );
}

function wp_kses( string $data, array $allowed_html, array $allowed_protocols = array() ): string {
	return $data;
}

// ---------------------------------------------------------------------------
// i18n stubs – just return the string as-is.
// ---------------------------------------------------------------------------

function __( string $text, string $domain = 'default' ): string {
	return $text;
}

function _x( string $text, string $context, string $domain = 'default' ): string {
	return $text;
}

function _n( string $single, string $plural, int $number, string $domain = 'default' ): string {
	return 1 === $number ? $single : $plural;
}

function esc_html__( string $text, string $domain = 'default' ): string {
	return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function esc_attr__( string $text, string $domain = 'default' ): string {
	return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function esc_html_e( string $text, string $domain = 'default' ): void {
	echo htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function esc_attr_e( string $text, string $domain = 'default' ): void {
	echo htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

// ---------------------------------------------------------------------------
// Post / meta stubs.
// ---------------------------------------------------------------------------

function wp_parse_args( array|string $args, array $defaults = array() ): array {
	if ( is_string( $args ) ) {
		parse_str( $args, $parsed );
		$args = $parsed;
	}
	return array_merge( $defaults, $args );
}

function get_posts( array $args = array() ): array {
	// Return mock posts set by the test, keyed by 'meta_query[0][value]' if present.
	$meta_value = $args['meta_query'][0]['value'] ?? null;
	$return_ids = ( $args['fields'] ?? '' ) === 'ids';

	if ( null !== $meta_value && isset( WPTestState::$posts[ $meta_value ] ) ) {
		$post = WPTestState::$posts[ $meta_value ];
		// When 'fields' => 'ids', return integer IDs just as WordPress does.
		if ( $return_ids ) {
			return array( is_object( $post ) ? (int) $post->ID : (int) $post );
		}
		return array( $post );
	}
	return array();
}

function wp_insert_post( array $post_data, bool $wp_error = false ): int|WP_Error {
	$post_id = WPTestState::$next_post_id++;
	WPTestState::$posts[ $post_id ] = (object) array_merge( $post_data, array( 'ID' => $post_id ) );
	return $post_id;
}

function wp_update_post( array $post_data, bool $wp_error = false, bool $fire_after_hooks = true ): int|WP_Error {
	$post_id = (int) ( $post_data['ID'] ?? 0 );
	if ( $post_id > 0 ) {
		WPTestState::$posts[ $post_id ] = (object) array_merge( (array) ( WPTestState::$posts[ $post_id ] ?? array() ), $post_data );
	}
	return $post_id;
}

function get_post_meta( int $post_id, string $key = '', bool $single = false ): mixed {
	return WPTestState::$post_meta[ $post_id ][ $key ] ?? ( $single ? '' : array() );
}

function update_post_meta( int $post_id, string $meta_key, mixed $meta_value, mixed $prev_value = '' ): int|bool {
	WPTestState::$post_meta[ $post_id ][ $meta_key ] = $meta_value;
	return true;
}

// ---------------------------------------------------------------------------
// Capabilities.
// ---------------------------------------------------------------------------

function current_user_can( string $capability, mixed ...$args ): bool {
	return WPTestState::$current_user_can;
}

function is_user_logged_in(): bool {
	return WPTestState::$is_user_logged_in;
}

// ---------------------------------------------------------------------------
// REST API helpers.
// ---------------------------------------------------------------------------

function rest_authorization_required_code(): int {
	return 401;
}

// ---------------------------------------------------------------------------
// Object cache.
// ---------------------------------------------------------------------------

function wp_using_ext_object_cache(): bool {
	return WPTestState::$using_ext_object_cache;
}

// ---------------------------------------------------------------------------
// Settings API stubs.
// ---------------------------------------------------------------------------

function add_settings_error( string $setting, string $code, string $message, string $type = 'error' ): void {
	WPTestState::$settings_errors[] = array(
		'setting' => $setting,
		'code'    => $code,
		'message' => $message,
		'type'    => $type,
	);
}

function register_setting( string $option_group, string $option_name, array $args = array() ): void {}

function add_settings_section( string $id, string $title, callable $callback, string $page ): void {}

function add_settings_field( string $id, string $title, callable $callback, string $page, string $section = 'default', array $args = array() ): void {}

function settings_fields( string $option_group ): void {}

function do_settings_sections( string $page ): void {}

function submit_button( string $text = '', string $type = 'primary', string $name = 'submit', bool $wrap = true, array $other_attributes = array() ): void {}

function get_admin_page_title(): string {
	return 'FundRaiseHub Settings';
}

// ---------------------------------------------------------------------------
// Admin hooks stubs.
// ---------------------------------------------------------------------------

function add_action( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {}

function add_filter( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {}

function add_options_page( string $page_title, string $menu_title, string $capability, string $menu_slug, callable|string $callback = '', int $position = 0 ): string|false {
	return 'fundraisehub-settings';
}

function add_submenu_page( ?string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, callable|string $callback = '', int $position = 0 ): string|false {
	return 'fundraisehub-setup';
}

function check_admin_referer( string $action = '-1', string $query_arg = '_wpnonce' ): int|false {
	return 1;
}

function wp_nonce_field( string $action = '-1', string $name = '_wpnonce', bool $referer = true, bool $display = true ): string {
	return '<input type="hidden" name="' . htmlspecialchars( $name ) . '" value="stub_nonce">';
}

function wp_die( string|WP_Error $message = '', string|int $title = '', array|int $args = array() ): never {
	throw new WPTestDieException( is_string( $message ) ? $message : $message->get_error_message() );
}

function flush_rewrite_rules( bool $hard = true ): void {
	WPTestState::$flushed_rewrite_rules = true;
}

// ---------------------------------------------------------------------------
// Cron stubs.
// ---------------------------------------------------------------------------

function wp_get_schedule( string $hook, array $args = array() ): string|false {
	return false;
}

function wp_clear_scheduled_hook( string $hook, array $args = array() ): int|false {
	return 0;
}

function wp_schedule_event( int $timestamp, string $recurrence, string $hook, array $args = array(), bool $wp_error = false ): bool|WP_Error {
	return true;
}

// ---------------------------------------------------------------------------
// oEmbed stub (for render_video).
// ---------------------------------------------------------------------------

function wp_oembed_get( string $url, array $args = array() ): string|false {
	return false;
}

// ---------------------------------------------------------------------------
// Block wrapper attributes stub.
// ---------------------------------------------------------------------------

function get_block_wrapper_attributes( array $extra_attributes = array() ): string {
	$class = $extra_attributes['class'] ?? '';
	return $class ? 'class="' . htmlspecialchars( $class, ENT_QUOTES ) . '"' : '';
}

// ---------------------------------------------------------------------------
// WordPress REST API class stubs – used as type hints in CampaignCPT.
// ---------------------------------------------------------------------------

if ( ! class_exists( 'WP_REST_Server', false ) ) {
	/** Minimal WP_REST_Server stub. */
	class WP_REST_Server {}
}

if ( ! class_exists( 'WP_REST_Request', false ) ) {
	/** Minimal WP_REST_Request stub. */
	class WP_REST_Request {
		/** @var string */
		private string $route = '/';

		/**
		 * @param string $route The request route.
		 */
		public function set_route( string $route ): void {
			$this->route = $route;
		}

		/** @return string */
		public function get_route(): string {
			return $this->route;
		}
	}
}

// ---------------------------------------------------------------------------
// Custom test exceptions thrown instead of redirect/die.
// ---------------------------------------------------------------------------

/**
 * Thrown by the wp_safe_redirect() stub to allow test assertions
 * on redirect targets without reaching the real exit() call.
 */
class WPTestRedirectException extends \RuntimeException {}

/**
 * Thrown by the wp_die() stub to allow test assertions on die messages
 * without reaching the real exit() call.
 */
class WPTestDieException extends \RuntimeException {}
