<?php
/**
 * Plugin Name: Insert Database Records
 * Description: Admin-only CSV importer for custom MySQL tables with validation, batch logging, and delete-last-import support.
 * Version: 0.2.0
 * Author: OpenAI
 * Text Domain: insert-database-records
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
define( 'IDR_PLUGIN_FILE', __FILE__ );
define( 'IDR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IDR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IDR_VERSION', '0.2.0' );
define( 'IDR_CAPABILITY', 'manage_options' );
define( 'IDR_UPLOAD_OPTION_PREFIX', 'idr_upload_' );
define( 'IDR_UPLOAD_TTL', HOUR_IN_SECONDS );
*/

// Define plugin directory path (absolute path on server)
define( 'IDR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );


require_once IDR_PLUGIN_DIR . 'includes/constants.php';
require_once IDR_PLUGIN_DIR . 'includes/helpers.php';
require_once IDR_PLUGIN_DIR . 'includes/class-db.php';
require_once IDR_PLUGIN_DIR . 'includes/class-log-repository.php';
require_once IDR_PLUGIN_DIR . 'includes/class-custom-table-detector.php';
require_once IDR_PLUGIN_DIR . 'includes/class-table-registry.php';
require_once IDR_PLUGIN_DIR . 'includes/class-schema-reader.php';
require_once IDR_PLUGIN_DIR . 'includes/class-csv-parser.php';
require_once IDR_PLUGIN_DIR . 'includes/class-validator.php';
require_once IDR_PLUGIN_DIR . 'includes/class-upload-service.php';
require_once IDR_PLUGIN_DIR . 'includes/class-import-service.php';
require_once IDR_PLUGIN_DIR . 'includes/class-delete-service.php';
require_once IDR_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once IDR_PLUGIN_DIR . 'includes/class-rest-controller.php';
require_once IDR_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, [ 'IDR_Plugin', 'activate' ] );

add_action( 'plugins_loaded', function () {
	$plugin = new IDR_Plugin();
	$plugin->run();
} );
