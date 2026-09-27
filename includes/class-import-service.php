<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IDR_Import_Service {
	private IDR_DB $db;
	private IDR_Log_Repository $logs;
	private IDR_Schema_Reader $schema_reader;
	private IDR_CSV_Parser $csv_parser;
	private IDR_Validator $validator;
	private IDR_Upload_Service $uploader;

	public function __construct(
		IDR_DB $db,
		IDR_Log_Repository $logs,
		IDR_Schema_Reader $schema_reader,
		IDR_CSV_Parser $csv_parser,
		IDR_Validator $validator,
		IDR_Upload_Service $uploader
	) {
		$this->db            = $db;
		$this->logs          = $logs;
		$this->schema_reader = $schema_reader;
		$this->csv_parser    = $csv_parser;
		$this->validator     = $validator;
		$this->uploader      = $uploader;
	}

	public function handle_rest_import( WP_REST_Request $request ) {
		global $wpdb;

		$table_name   = sanitize_text_field( (string) $request->get_param( 'table_name' ) );
		$upload_token = sanitize_text_field( (string) $request->get_param( 'upload_token' ) );

		$file = $this->uploader->resolve_token( $upload_token );
		if ( ! $file ) {
			return new WP_REST_Response(
				[ 'success' => false, 'message' => 'Uploaded file token is invalid or expired.' ],
				422
			);
		}

		$validation = $this->validator->validate( $table_name, $file['path'], $file['file_name'] );
		if ( ! $validation['valid'] ) {
			return new WP_REST_Response( $validation, 422 );
		}

		try {
			$parsed     = $this->csv_parser->parse( $file['path'] );
			$assoc_rows = $this->csv_parser->to_assoc_rows( $parsed );
		} catch ( Throwable $e ) {
			return new WP_REST_Response(
				[ 'success' => false, 'message' => $e->getMessage() ],
				422
			);
		}

		$schema      = $this->schema_reader->get_columns( $table_name );
		$primary_key = $this->schema_reader->get_primary_key_column( $table_name );
		$batch_id    = $this->logs->create_batch( $table_name, $file['file_name'], get_current_user_id() );

		$transaction_started = ( false !== $wpdb->query( 'START TRANSACTION' ) );

		try {
			$inserted_count = 0;

			foreach ( $assoc_rows as $row ) {
				$prepared = $this->prepare_row_for_insert( $row['data'], $schema );

				$max_attempts = 5;
				$attempt = 0;

				do {
					if ( isset( $prepared['data']['ProductId'] ) ) {
						$prepared['data']['ProductId'] = idr_generate_product_id();
					}

					$insert_result = $wpdb->insert( $table_name, $prepared['data'], $prepared['formats'] );

					if ( $insert_result !== false ) {
						break;
					}

					// Check for duplicate key error
					if ( strpos( strtolower( $wpdb->last_error ), 'duplicate' ) === false ) {
						break;
					}

					$attempt++;

				} while ( $attempt < $max_attempts );

				if ( false === $insert_result ) {
					throw new RuntimeException(
						sprintf(
							'Insert failed on row %d. %s',
							(int) $row['row_number'],
							$wpdb->last_error ?: 'Database error.'
						)
					);
				}

				if ( false === $insert_result ) {
					throw new RuntimeException(
						sprintf( 'Insert failed on row %d. %s', (int) $row['row_number'], $wpdb->last_error ?: 'Database error.' )
					);
				}

				$pk_value = null;

				if ( $primary_key && ! empty( $schema[ $primary_key ]['auto_increment'] ) ) {
					$pk_value = (string) $wpdb->insert_id;
				} elseif ( $primary_key && array_key_exists( $primary_key, $prepared['data'] ) ) {
					$pk_value = (string) $prepared['data'][ $primary_key ];
				}

				$row_hash = hash( 'sha256', wp_json_encode( $prepared['data'] ) );

				$this->logs->record_inserted_row(
					$batch_id,
					$pk_value,
					$row_hash,
					wp_json_encode( $prepared['data'] )
				);

				$inserted_count++;
			}

			if ( $transaction_started ) {
				$wpdb->query( 'COMMIT' );
			}

			$this->logs->mark_batch_inserted( $batch_id, $inserted_count );
			$this->uploader->delete_token_file( $upload_token );

			return new WP_REST_Response([
				'success'   => true,
				'batch_id'  => $batch_id,
				'row_count' => $inserted_count,
				'message'   => sprintf( '%d records were inserted into %s successfully.', $inserted_count, $table_name ),
			], 200 );
		} catch ( Throwable $e ) {
			if ( $transaction_started ) {
				$wpdb->query( 'ROLLBACK' );
			}

			$this->logs->mark_batch_failed( $batch_id, $e->getMessage() );

			return new WP_REST_Response([
				'success' => false,
				'message' => $e->getMessage(),
			], 500 );
		}
	}

	private function prepare_row_for_insert( array $row, array $schema ): array {
		$row = $this->normalize_row_data( $row );

		// Auto-generate ProductId if it exists in schema but not in CSV
		if ( isset( $schema['ProductId'] ) && ! array_key_exists( 'ProductId', $row ) ) {
			$row['ProductId'] = idr_generate_product_id();
		}

		if ( isset( $schema['UploadedDate'] ) && ! array_key_exists( 'UploadedDate', $row ) ) {
			$row['UploadedDate'] = current_time( 'mysql' );
		}

		$row['Archived'] = ! empty( $row['Archived'] ) ? 1 : 0;

		$row = $this->prepare_field_storage_path( $row );

		$data = [];
		$formats = [];

		foreach ( $row as $column_name => $value ) {
			if ( ! isset( $schema[ $column_name ] ) ) {
				continue;
			}

			$column = $schema[ $column_name ];
			$trimmed = trim( (string) $value );
			$type = strtolower( (string) $column['type'] );

			if ( $column['generated'] ) {
				continue;
			}

			if ( $column['auto_increment'] && ( '' === $trimmed || '0' === $trimmed ) ) {
				continue;
			}

			if ( '' === $trimmed ) {
				if ( $column['nullable'] ) {
					$data[ $column_name ] = null;
					$formats[] = null;
					continue;
				}

				if ( null !== $column['default'] ) {
					continue;
				}
			}

			if ( preg_match( '/^(tinyint|smallint|mediumint|int|bigint)/', $type ) ) {
				$data[ $column_name ] = (int) $trimmed;
				$formats[] = '%d';
			} elseif ( preg_match( '/^(decimal|float|double)/', $type ) ) {
				$data[ $column_name ] = (float) $trimmed;
				$formats[] = '%f';
			} else {
				$data[ $column_name ] = $trimmed;
				$formats[] = '%s';
			}
		}

		return [
			'data'    => $data,
			'formats' => $formats,
		];
	}

	private function prepare_field_storage_path( array $row ): array {
		if (
			empty( $row['StoragePathDisplay'] ) &&
			isset( $row['ImageHead'], $row['ImageBody'], $row['ImageButt'], $row['FilenameDisplay'],  $row['FilenamePrint'] )
		) {
			$row['StoragePathDisplay'] = idr_build_storage_path(
				$row['ImageHead'],
				$row['ImageBody'],
				$row['ImageButt'],
				$row['ProductId'],
				$row['FilenameDisplay']
			);
			$row['StoragePathPrint'] = idr_build_storage_path(
				$row['ImageHead'],
				$row['ImageBody'],
				$row['ImageButt'],
				$row['ProductId'],
				$row['FilenamePrint']
			);
		}

		return $row;
	}

	private function normalize_row_data(array $row): array {
		if ( isset( $row['ImageHead'] ) ) {
			$row['ImageHead'] = strtolower( trim( $row['ImageHead'] ) );
		}
		if ( isset( $row['ImageBody'] ) ) {
			$row['ImageBody'] = strtolower( trim( $row['ImageBody'] ) );
		}
		if ( isset( $row['ImageButt'] ) ) {
			$row['ImageButt'] = strtolower( trim( $row['ImageButt'] ) );
		}
		if ( isset( $row['FilenameDisplay'] ) ) {
			$row['FilenameDisplay'] = strtolower( trim( $row['FilenameDisplay'] ) );
		}
		if ( isset( $row['FilenamePrint'] ) ) {
			$row['FilenamePrint'] = strtolower( trim( $row['FilenamePrint'] ) );
		}

		return $row;
	}
}
