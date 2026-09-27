<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IDR_Log_Repository {
	private IDR_DB $db;
	private string $batches_table;
	private string $rows_table;

	public function __construct( IDR_DB $db ) {
		global $wpdb;
		$this->db            = $db;
		$this->batches_table = $wpdb->prefix . 'idr_import_batches';
		$this->rows_table    = $wpdb->prefix . 'idr_import_batch_rows';
	}

	public function create_tables(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql1 = "CREATE TABLE {$this->batches_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			table_name VARCHAR(191) NOT NULL,
			csv_filename VARCHAR(255) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(50) NOT NULL,
			inserted_count INT UNSIGNED NOT NULL DEFAULT 0,
			error_message LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			deleted_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY table_status (table_name, status)
		) $charset_collate;";

		$sql2 = "CREATE TABLE {$this->rows_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			batch_id BIGINT UNSIGNED NOT NULL,
			target_primary_key VARCHAR(191) NULL,
			row_hash CHAR(64) NULL,
			row_data_json LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY batch_id (batch_id)
		) $charset_collate;";

		dbDelta( $sql1 );
		dbDelta( $sql2 );
	}

	public function create_batch( string $table_name, string $file_name, int $user_id ): int {
		global $wpdb;

		$wpdb->insert( $this->batches_table, [
			'table_name'   => $table_name,
			'csv_filename' => $file_name,
			'user_id'      => $user_id,
			'status'       => 'validated',
			'created_at'   => current_time( 'mysql' ),
		] );

		return (int) $wpdb->insert_id;
	}

	public function record_inserted_row( int $batch_id, ?string $pk_value, ?string $row_hash, ?string $row_data_json ): void {
		global $wpdb;

		$wpdb->insert( $this->rows_table, [
			'batch_id'           => $batch_id,
			'target_primary_key' => $pk_value,
			'row_hash'           => $row_hash,
			'row_data_json'      => $row_data_json,
			'created_at'         => current_time( 'mysql' ),
		] );
	}

	public function mark_batch_inserted( int $batch_id, int $count ): void {
		global $wpdb;
		$wpdb->update( $this->batches_table, [
			'status'         => 'inserted',
			'inserted_count' => $count,
		], [ 'id' => $batch_id ] );
	}

	public function mark_batch_failed( int $batch_id, string $message ): void {
		global $wpdb;
		$wpdb->update( $this->batches_table, [
			'status'        => 'failed',
			'error_message' => $message,
		], [ 'id' => $batch_id ] );
	}

	public function mark_batch_deleted( int $batch_id ): void {
		global $wpdb;
		$wpdb->update( $this->batches_table, [
			'status'     => 'deleted',
			'deleted_at' => current_time( 'mysql' ),
		], [ 'id' => $batch_id ] );
	}

	public function get_last_successful_batch( string $table_name ): ?array {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->batches_table}
			 WHERE table_name = %s
			   AND status = 'inserted'
			 ORDER BY id DESC
			 LIMIT 1",
			$table_name
		);

		$row = $wpdb->get_row( $sql, ARRAY_A );
		return $row ?: null;
	}

	public function get_batch_rows( int $batch_id ): array {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->rows_table}
			 WHERE batch_id = %d
			 ORDER BY id ASC",
			$batch_id
		);

		return $wpdb->get_results( $sql, ARRAY_A ) ?: [];
	}
}
