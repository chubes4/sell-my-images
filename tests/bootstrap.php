<?php
/**
 * PHPUnit Bootstrap File
 *
 * Sets up Brain Monkey for WordPress function mocking
 *
 * @package SellMyImages\Tests
 */

// Composer autoloader
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Brain Monkey setup
use Brain\Monkey;
use Brain\Monkey\Functions;

// Define WordPress constants if not already defined
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'SMI_PLUGIN_DIR' ) ) {
    define( 'SMI_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'SMI_VERSION' ) ) {
    define( 'SMI_VERSION', '1.2.1' );
}

/**
 * Simple WP_Error implementation for testing
 */
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        protected $errors = array();
        protected $error_data = array();

        public function __construct( $code = '', $message = '', $data = '' ) {
            if ( ! empty( $code ) ) {
                $this->errors[ $code ][] = $message;
                if ( ! empty( $data ) ) {
                    $this->error_data[ $code ] = $data;
                }
            }
        }

        public function get_error_code() {
            $codes = array_keys( $this->errors );
            return reset( $codes ) ?: '';
        }

        public function get_error_message( $code = '' ) {
            if ( empty( $code ) ) {
                $code = $this->get_error_code();
            }
            return isset( $this->errors[ $code ] ) ? $this->errors[ $code ][0] : '';
        }

        public function get_error_data( $code = '' ) {
            if ( empty( $code ) ) {
                $code = $this->get_error_code();
            }
            return $this->error_data[ $code ] ?? null;
        }

        public function has_errors() {
            return ! empty( $this->errors );
        }

        public function add( $code, $message, $data = '' ) {
            $this->errors[ $code ][] = $message;
            if ( ! empty( $data ) ) {
                $this->error_data[ $code ] = $data;
            }
        }
    }
}

/**
 * Helper function for stripslashes_deep if not defined
 */
if ( ! function_exists( 'stripslashes_deep' ) ) {
    function stripslashes_deep( $value ) {
        return is_array( $value )
            ? array_map( 'stripslashes_deep', $value )
            : stripslashes( $value );
    }
}

/**
 * Mock WebhookManager for testing - must be loaded before PaymentService
 */
if ( ! class_exists( 'SellMyImages\Managers\WebhookManager' ) ) {
    // Create the namespace and class
    eval( '
        namespace SellMyImages\Managers;
        class WebhookManager {
            public static function register_webhook( $name, $callback ) {
                return true;
            }
        }
    ' );
}

/**
 * Base Test Case for all plugin tests
 */
abstract class SMI_TestCase extends \PHPUnit\Framework\TestCase {

    use \Yoast\PHPUnitPolyfills\Polyfills\AssertIsType;

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        $this->setup_common_wordpress_functions();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Setup commonly used WordPress functions with sensible defaults
     */
    protected function setup_common_wordpress_functions(): void {
        // Escaping functions - passthrough
        Functions\stubs(
            array(
                'esc_html'            => function ( $text ) {
                    return $text;
                },
                'esc_attr'            => function ( $text ) {
                    return $text;
                },
                'esc_url'             => function ( $url ) {
                    return $url;
                },
                'esc_url_raw'         => function ( $url ) {
                    return $url;
                },
                'sanitize_text_field' => function ( $str ) {
                    return $str;
                },
                'sanitize_email'      => function ( $email ) {
                    return $email;
                },
                'sanitize_key'        => function ( $key ) {
                    return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', $key ) );
                },
                'wp_unslash'          => function ( $value ) {
                    return stripslashes_deep( $value );
                },
            )
        );

        // Translation functions - passthrough
        Functions\stubs(
            array(
                '__'         => function ( $text, $domain = 'default' ) {
                    return $text;
                },
                '_e'         => function ( $text, $domain = 'default' ) {
                    echo $text;
                },
                'esc_html__' => function ( $text, $domain = 'default' ) {
                    return $text;
                },
                'esc_attr__' => function ( $text, $domain = 'default' ) {
                    return $text;
                },
            )
        );

        // Common WordPress functions
        Functions\stubs(
            array(
                'is_wp_error'   => function ( $thing ) {
                    return $thing instanceof \WP_Error;
                },
                'wp_parse_args' => function ( $args, $defaults = array() ) {
                    return array_merge( $defaults, $args );
                },
            )
        );
    }

    /**
     * Create a mock WP_Error
     */
    protected function create_wp_error( string $code, string $message, $data = '' ): \WP_Error {
        return new \WP_Error( $code, $message, $data );
    }
}
