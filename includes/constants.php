<?php
// Prevent direct access to this file for security
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'IDR_PLUGIN_FILE', __FILE__ );
//define( 'IDR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
//define( 'IDR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IDR_VERSION', '0.2.0' );
//define( 'IDR_CAPABILITY', 'manage_options' );
define( 'IDR_UPLOAD_OPTION_PREFIX', 'idr_upload_' );
define( 'IDR_UPLOAD_TTL', HOUR_IN_SECONDS );

// Define plugin version
define( 'IDR_PLUGIN_VERSION', '1.0.0' );

// Define plugin directory path (absolute path on server)
//define( 'IDR_PLUGIN_DIR', plugin_dir_path( dirname( __FILE__ ) ) );

// Define plugin URL (used for assets like JS/CSS/images)
define( 'IDR_PLUGIN_URL', plugin_dir_url( dirname( __FILE__ ) ) );

// Define capability required to access plugin features
define( 'IDR_CAPABILITY', 'manage_options' );

// Define REST API namespace
define( 'IDR_REST_NAMESPACE', 'idr/v1' );

// Define nonce action for security
define( 'IDR_NONCE_ACTION', 'idr_nonce_action' );