<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IDR_Delete_Service {
	private IDR_DB $db;
	private IDR_Log_Repository $logs;
	private IDR_Schema_Reader $schema_reader;
	private IDR_Table_Registry $tables;

	public function __construct(
		IDR_DB $db,
		IDR_Log_Repository $logs,
		IDR_Schema_Reader $schema_reader,
		IDR_Table_Registry $tables
	) {
		$this->db            = $db;
		$this->logs          = $logs;
		$this->schema_reader = $schema_reader;
		$this->tables        = $tables;
	}

	public function handle_rest_delete( WP_REST_Request $request ) {
		global $wpdb;

		$table_name = sanitize_text_field( (string) $request->get_param( 'table_name' ) );

		if ( ! $this->tables->is_allowed_table( $table_name ) ) {
			return new WP_REST_Response([
				'success' => false,
				'message' => 'Selected table is not allowed.',
			], 422 );
		}

		$batch = $this->logs->get_last_successful_batch( $table_name );

		if ( empty( $batch ) ) {
			return new WP_REST_Response([
				'success' => false,
				'message' => 'No successful batch found to delete.',
			], 404 );
		}

		$primary_key = $this->schema_reader->get_primary_key_column( $table_name );
		$row_logs    = $this->logs->get_batch_rows( (int) $batch['id'] );

		if ( empty( $row_logs ) ) {
			return new WP_REST_Response([
				'success' => false,
				'message' => 'No logged rows found for the batch.',
			], 404 );
		}

		$transaction_started = ( false !== $wpdb->query( 'START TRANSACTION' ) );

		try {
			$deleted_total = 0;

			if ( $primary_key ) {
				$ids = array_filter( array_map(
					static fn( $row ) => $row['target_primary_key'],
					$row_logs
				) );

				if ( ! empty( $ids ) ) {
					$placeholders = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
					$sql = $wpdb->prepare(
						"DELETE FROM " . idr_quote_identifier( $table_name ) .
						" WHERE " . idr_quote_identifier( $primary_key ) . " IN ($placeholders)",
						$ids
					);

					$result = $wpdb->query( $sql );
					if ( false === $result ) {
						throw new RuntimeException( $wpdb->last_error ?: 'Delete failed.' );
					}

					$deleted_total += (int) $result;
				}
			} else {
				foreach ( $row_logs as $row_log ) {
					$snapshot = json_decode( (string) $row_log['row_data_json'], true );

					if ( ! is_array( $snapshot ) || empty( $snapshot ) ) {
						continue;
					}

					$where = [];
					$params = [];

					foreach ( $snapshot as $column => $value ) {
						if ( null === $value ) {
							$where[] = idr_quote_identifier( $column ) . ' IS NULL';
						} else {
							$where[] = idr_quote_identifier( $column ) . ' = %s';
							$params[] = (string) $value;
						}
					}

					if ( empty( $where ) ) {
						continue;
					}

					$sql = "DELETE FROM " . idr_quote_identifier( $table_name ) .
						" WHERE " . implode( ' AND ', $where ) .
						" LIMIT 1";

					$prepared = empty( $params ) ? $sql : $wpdb->prepare( $sql, $params );
					$result = $wpdb->query( $prepared );

					if ( false === $result ) {
						throw new RuntimeException( $wpdb->last_error ?: 'Delete failed.' );
					}

					$deleted_total += (int) $result;
				}
			}

			if ( $transaction_started ) {
				$wpdb->query( 'COMMIT' );
			}

			$this->logs->mark_batch_deleted( (int) $batch['id'] );

			return new WP_REST_Response([
				'success' => true,
				'message' => sprintf(
					'The %d records from the last import batch were deleted successfully.',
					$deleted_total
				),
			], 200 );
		} catch ( Throwable $e ) {
			if ( $transaction_started ) {
				$wpdb->query( 'ROLLBACK' );
			}

			return new WP_REST_Response([
				'success' => false,
				'message' => 'Delete failed. ' . $e->getMessage(),
			], 500 );
		}
	}
}
