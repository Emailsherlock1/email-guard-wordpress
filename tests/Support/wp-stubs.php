<?php
/**
 * Minimal WordPress test doubles: an in-memory options store, a working
 * hook registry (so adapter tests can fire the same filters WordPress
 * would), WP_Error, and the escaping/i18n helpers the plugin calls.
 *
 * Shared by every test. Plugin-specific fakes (a WPForms process stub, a
 * CF7 validation result) belong in the test file that needs them, not here.
 */

$GLOBALS['wp_stub_options'] = array();
$GLOBALS['wp_stub_filters'] = array();

/**
 * Reset all stub state between tests.
 */
function wp_stubs_reset() {
	$GLOBALS['wp_stub_options'] = array();
	$GLOBALS['wp_stub_filters'] = array();
}

function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['wp_stub_filters'][ $tag ][] = array(
		'callback' => $callback,
		'priority' => $priority,
		'args'     => $accepted_args,
	);
	usort(
		$GLOBALS['wp_stub_filters'][ $tag ],
		static function ( $a, $b ) {
			return $a['priority'] <=> $b['priority'];
		}
	);

	return true;
}

function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_filter( $tag, $callback, $priority, $accepted_args );
}

function has_filter( $tag ) {
	return ! empty( $GLOBALS['wp_stub_filters'][ $tag ] );
}

function apply_filters( $tag, $value, ...$args ) {
	foreach ( $GLOBALS['wp_stub_filters'][ $tag ] ?? array() as $hook ) {
		$all   = array_merge( array( $value ), $args );
		$value = call_user_func_array( $hook['callback'], array_slice( $all, 0, max( 1, $hook['args'] ) ) );
	}

	return $value;
}

function do_action( $tag, ...$args ) {
	foreach ( $GLOBALS['wp_stub_filters'][ $tag ] ?? array() as $hook ) {
		call_user_func_array( $hook['callback'], array_slice( $args, 0, max( 0, $hook['args'] ) ) );
	}
}

function get_option( $name, $default_value = false ) {
	return array_key_exists( $name, $GLOBALS['wp_stub_options'] ) ? $GLOBALS['wp_stub_options'][ $name ] : $default_value;
}

function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['wp_stub_options'][ $name ] = $value;

	return true;
}

function delete_option( $name ) {
	unset( $GLOBALS['wp_stub_options'][ $name ] );

	return true;
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function esc_html__( $text, $domain = 'default' ) {
	return $text;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_url( $url ) {
	return (string) $url;
}

function sanitize_text_field( $str ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $str ) ) );
}

function sanitize_email( $email ) {
	return (string) $email;
}

function wp_unslash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_unslash', $value );
	}

	return is_string( $value ) ? stripslashes( $value ) : $value;
}

function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options, $depth );
}

function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

/**
 * Minimal WP_Error: enough for the adapters and their tests.
 */
class WP_Error {

	/** @var array<string, string[]> */
	public $errors = array();

	/** @var array<string, mixed> */
	public $error_data = array();

	public function __construct( $code = '', $message = '', $data = '' ) {
		if ( '' !== $code ) {
			$this->add( $code, $message, $data );
		}
	}

	public function add( $code, $message, $data = '' ) {
		$this->errors[ $code ][] = $message;
		if ( '' !== $data ) {
			$this->error_data[ $code ] = $data;
		}
	}

	public function get_error_code() {
		$codes = array_keys( $this->errors );

		return $codes ? $codes[0] : '';
	}

	public function get_error_message( $code = '' ) {
		if ( '' === $code ) {
			$code = $this->get_error_code();
		}

		return isset( $this->errors[ $code ][0] ) ? $this->errors[ $code ][0] : '';
	}

	public function get_error_data( $code = '' ) {
		if ( '' === $code ) {
			$code = $this->get_error_code();
		}

		return isset( $this->error_data[ $code ] ) ? $this->error_data[ $code ] : null;
	}
}

function is_admin() {
	return false;
}
