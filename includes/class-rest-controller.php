<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IDR_REST_Controller {
	private IDR_Table_Registry $tables;
	private IDR_Validator $validator;
	private IDR_Import_Service $importer;
	private IDR_Delete_Service $deleter;
	private IDR_Log_Repository $logs;
	private IDR_Upload_Service $uploader;

	public function __construct(
		IDR_Table_Registry $tables,
		IDR_Validator $validator,
		IDR_Import_Service $importer,
		IDR_Delete_Service $deleter,
		IDR_Log_Repository $logs,
		IDR_Upload_Service $uploader
	) {
		$this->tables    = $tables;
		$this->validator = $validator;
		$this->importer  = $importer;
		$this->deleter   = $deleter;
		$this->logs      = $logs;
		$this->uploader  = $uploader;
	}

	public function register_routes(): void {
		register_rest_route( 'idr/v1', '/upload', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'upload_csv' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'idr/v1', '/tables', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_tables' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'idr/v1', '/validate', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'validate_csv' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'idr/v1', '/import', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'import_csv' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'idr/v1', '/delete-last-batch', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'delete_last_batch' ],
			'permission_callback' => [ $this, 'permissions_check' ],
		] );

		register_rest_route( 'idr/v1', '/last-batch-summary', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'last_batch_summary' ],
			'permission_callback' => [ $this, 'permissions_check' ],
			'args'                => [
				'table_name' => [
					'required' => true,
					'type'     => 'string',
				],
			],
		] );
	}

	public function permissions_check(): bool {
		return current_user_can( IDR_CAPABILITY );
	}

	public function upload_csv( WP_REST_Request $request ): WP_REST_Response {
		if ( empty( $_FILES['csv_file'] ) ) {
			return new WP_REST_Response(
				[ 'success' => false, 'message' => 'CSV file is required.' ],
				422
			);
		}

		$result = $this->uploader->handle_upload_from_files( $_FILES['csv_file'] );
		return new WP_REST_Response( $result, $result['success'] ? 200 : 422 );
	}

	public function get_tables( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response([
			'tables' => $this->tables->get_allowed_tables(),
		]);
	}

	public function validate_csv( WP_REST_Request $request ) {
		return $this->validator->handle_rest_validation( $request, $this->uploader );
	}

	public function import_csv( WP_REST_Request $request ) {
		return $this->importer->handle_rest_import( $request );
	}

	public function delete_last_batch( WP_REST_Request $request ) {
		return $this->deleter->handle_rest_delete( $request );
	}

	public function last_batch_summary( WP_REST_Request $request ) {
		$table_name = sanitize_text_field( (string) $request->get_param( 'table_name' ) );
		return new WP_REST_Response([
			'batch' => $this->logs->get_last_successful_batch( $table_name ),
		]);
	}
}
