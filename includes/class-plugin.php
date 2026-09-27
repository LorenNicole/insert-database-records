<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IDR_Plugin {
	private IDR_Admin_Page $admin_page;
	private IDR_REST_Controller $rest_controller;

	public function __construct() {
		$db            = new IDR_DB();
		$logs          = new IDR_Log_Repository( $db );
		$detector      = new IDR_Custom_Table_Detector( $db );
		$tables        = new IDR_Table_Registry( $detector );
		$schema_reader = new IDR_Schema_Reader( $db );
		$csv_parser    = new IDR_CSV_Parser();
		$uploader      = new IDR_Upload_Service();
		$validator     = new IDR_Validator( $tables, $schema_reader, $csv_parser, $db );
		$importer      = new IDR_Import_Service( $db, $logs, $schema_reader, $csv_parser, $validator, $uploader );
		$deleter       = new IDR_Delete_Service( $db, $logs, $schema_reader, $tables );

		$this->admin_page = new IDR_Admin_Page( $tables, $logs );

		$this->rest_controller = new IDR_REST_Controller(
			$tables,
			$validator,
			$importer,
			$deleter,
			$logs,
			$uploader
		);
	}

	public function run(): void {
		add_action( 'admin_menu', [ $this->admin_page, 'register_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this->admin_page, 'enqueue_assets' ] );
		add_action( 'rest_api_init', [ $this->rest_controller, 'register_routes' ] );
	}

	public static function activate(): void {
		$repo = new IDR_Log_Repository( new IDR_DB() );
		$repo->create_tables();
	}
}
