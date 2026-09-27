<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IDR_Admin_Page {
	private IDR_Table_Registry $tables;
	private IDR_Log_Repository $logs;
	private string $hook_suffix = '';

	public function __construct( IDR_Table_Registry $tables, IDR_Log_Repository $logs ) {
		$this->tables = $tables;
		$this->logs   = $logs;
	}

	public function register_page(): void {
		$this->hook_suffix = add_management_page(
			__( 'Insert Database Records', 'insert-database-records' ),
			__( 'Insert Database Records', 'insert-database-records' ),
			IDR_CAPABILITY,
			'insert-database-records',
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'idr-admin',
			IDR_PLUGIN_URL . 'assets/admin.css',
			[],
			IDR_VERSION
		);

		wp_enqueue_script(
			'idr-admin',
			IDR_PLUGIN_URL . 'assets/admin.js',
			[],
			IDR_VERSION,
			true
		);

		wp_localize_script(
			'idr-admin',
			'IDR_CONFIG',
			[
				'root'  => esc_url_raw( rest_url( 'idr/v1/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			]
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( IDR_CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'insert-database-records' ) );
		}

		$tables = $this->tables->get_allowed_tables();
		require IDR_PLUGIN_DIR . 'templates/admin-page.php';
	}
}
