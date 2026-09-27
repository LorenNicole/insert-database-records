<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IDR_Validator {
	private IDR_Table_Registry $tables;
	private IDR_Schema_Reader $schema_reader;
	private IDR_CSV_Parser $csv_parser;
	private IDR_DB $db;

	public function __construct(
		IDR_Table_Registry $tables,
		IDR_Schema_Reader $schema_reader,
		IDR_CSV_Parser $csv_parser,
		IDR_DB $db
	) {
		$this->tables        = $tables;
		$this->schema_reader = $schema_reader;
		$this->csv_parser    = $csv_parser;
		$this->db            = $db;
	}

	public function handle_rest_validation( WP_REST_Request $request, IDR_Upload_Service $uploader ) {
		$table_name   = sanitize_text_field( (string) $request->get_param( 'table_name' ) );
		$upload_token = sanitize_text_field( (string) $request->get_param( 'upload_token' ) );

		$file = $uploader->resolve_token( $upload_token );

		if ( ! $file ) {
			return new WP_REST_Response(
				[ 'valid' => false, 'message' => 'Uploaded file token is invalid or expired.' ],
				422
			);
		}

		$result = $this->validate( $table_name, $file['path'], $file['original_name'] ?? $file['file_name']);
		return new WP_REST_Response( $result, $result['valid'] ? 200 : 422 );
	}

	public function validate( string $table_name, string $file_path, string $file_name ): array {
		if ( ! $this->tables->is_allowed_table( $table_name ) ) {
			return [ 'valid' => false, 'message' => 'Selected table is not allowed.' ];
		}

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return [ 'valid' => false, 'message' => 'Uploaded CSV file is missing or unreadable.' ];
		}

		$schema = $this->schema_reader->get_columns( $table_name );

		if ( empty( $schema ) ) {
			return [ 'valid' => false, 'message' => 'Unable to read destination table schema.' ];
		}

		try {
			$parsed     = $this->csv_parser->parse( $file_path );
			$assoc_rows = $this->csv_parser->to_assoc_rows( $parsed );
		} catch ( Throwable $e ) {
			return [ 'valid' => false, 'message' => $e->getMessage() ];
		}

		if ( empty( $assoc_rows ) ) {
			return [ 'valid' => false, 'message' => 'CSV contains no data rows.' ];
		}

		$header         = $parsed['header'];
		$schema_columns = array_keys( $schema );

		// 🚨 Block ProductId in CSV (system-generated field)
		$normalized_header = array_map('strtolower', $header);

		if ( in_array( 'productid', $normalized_header, true ) ) {
			return [
				'valid' => false,
				'message' => 'ProductId should not be included in the CSV. It is generated automatically.',
			];
		}

		if ( in_array( 'StoragePathDisplay', $header, true ) ) {
			return [
				'valid' => false,
				'message' => 'StoragePathDisplay should not be included in the CSV. It is generated automatically.',
			];
		}

		if ( in_array( 'StoragePathPrint', $header, true ) ) {
			return [
				'valid' => false,
				'message' => 'StoragePathPrint should not be included in the CSV. It is generated automatically.',
			];
		}

		if ( in_array( 'UploadedDate', $header, true ) ) {
			return [
				'valid' => false,
				'message' => 'UploadedDate should not be included in the CSV. It is generated automatically.',
			];
		}

		$unknown = array_diff( $header, $schema_columns );
		if ( ! empty( $unknown ) ) {
			return [ 'valid' => false, 'message' => 'Unknown columns: ' . implode( ', ', $unknown ) ];
		}

		$generated = [];
		foreach ( $header as $column_name ) {
			if ( ! empty( $schema[ $column_name ]['generated'] ) ) {
				$generated[] = $column_name;
			}
		}
		if ( ! empty( $generated ) ) {
			return [ 'valid' => false, 'message' => 'Generated columns cannot be imported: ' . implode( ', ', $generated ) ];
		}

		$system_generated_columns = [ 'ProductId', 'StoragePathDisplay', 'StoragePathPrint', 'UploadedDate' ];

		$required = [];
		foreach ( $schema as $column ) {
			if ( in_array( $column['name'], $system_generated_columns, true ) ) {
				continue;
			}

			if (
				! $column['nullable']
				&& null === $column['default']
				&& ! $column['auto_increment']
				&& ! $column['generated']
			) {
				$required[] = $column['name'];
			}
		}

		$missing_required = array_diff( $required, $header );
		if ( ! empty( $missing_required ) ) {
			return [ 'valid' => false, 'message' => 'Missing required columns: ' . implode( ', ', $missing_required ) ];
		}

		foreach ( $assoc_rows as $row ) {
			foreach ( $row['data'] as $column_name => $value ) {
				$column = $schema[ $column_name ];
				$type_result = $this->validate_value_against_type( $value, $column );

				if ( ! $type_result['valid'] ) {
					return [
						'valid' => false,
						'message' => sprintf(
							'Row %d column %s is invalid: %s',
							(int) $row['row_number'],
							$column_name,
							$type_result['message']
						),
					];
				}
			}
		}

		$unique_result = $this->validate_unique_indexes( $table_name, $assoc_rows );
		if ( ! $unique_result['valid'] ) {
			return $unique_result;
		}

		return [
			'valid'     => true,
			'message'   => sprintf( 'Validation passed. %d row(s) ready for import - %s.', count( $assoc_rows ), $file_name ),
			'row_count' => count( $assoc_rows ),
			'header'    => $header,
		];
	}

	private function validate_value_against_type( string $value, array $column ): array {
		$type = strtolower( (string) $column['type'] );
		$trimmed = trim( $value );

		if ( '' === $trimmed ) {
			if ( $column['nullable'] || null !== $column['default'] || $column['auto_increment'] ) {
				return [ 'valid' => true, 'message' => '' ];
			}
			return [ 'valid' => false, 'message' => 'value is required' ];
		}

		if ( preg_match( '/^(tinyint|smallint|mediumint|int|bigint)/', $type ) ) {
			if ( ! preg_match( '/^-?\d+$/', $trimmed ) ) {
				return [ 'valid' => false, 'message' => 'expected integer' ];
			}
		} elseif ( preg_match( '/^(decimal|float|double)/', $type ) ) {
			if ( ! is_numeric( $trimmed ) ) {
				return [ 'valid' => false, 'message' => 'expected numeric value' ];
			}
		} elseif ( preg_match( '/^(date)$/', $type ) ) {
			$dt = DateTime::createFromFormat( 'Y-m-d', $trimmed );
			if ( ! $dt || $dt->format( 'Y-m-d' ) !== $trimmed ) {
				return [ 'valid' => false, 'message' => 'expected YYYY-MM-DD date' ];
			}
		} elseif ( preg_match( '/^(datetime|timestamp)/', $type ) ) {
			$ok = strtotime( $trimmed ) !== false;
			if ( ! $ok ) {
				return [ 'valid' => false, 'message' => 'expected datetime/timestamp' ];
			}
		}

		return [ 'valid' => true, 'message' => '' ];
	}

	private function validate_unique_indexes( string $table_name, array $assoc_rows ): array {
		global $wpdb;

		$indexes = $this->schema_reader->get_unique_indexes( $table_name );
		if ( empty( $indexes ) ) {
			return [ 'valid' => true ];
		}

		foreach ( $indexes as $index_name => $columns ) {
			$csv_seen = [];

			foreach ( $assoc_rows as $row ) {
				$parts = [];

			foreach ( $columns as $col ) {
				if ( ! array_key_exists( $col, $row['data'] ) ) {
					continue 2;
				}

				$value = trim( (string) $row['data'][ $col ] );
				if ( '' === $value ) {
					continue 2;
				}

				$parts[] = (string) $row['data'][ $col ];
			}

			$key = implode( "\x1F", $parts );

			if ( isset( $csv_seen[ $key ] ) ) {
					return [
						'valid' => false,
						'message' => sprintf(
							'Duplicate values detected in CSV for unique index %s (row %d).',
							$index_name,
							(int) $row['row_number']
						),
					];
				}

				$csv_seen[ $key ] = true;

				$where = [];
				$params = [];

				foreach ( $columns as $col ) {
					if ( ! array_key_exists( $col, $row['data'] ) ) {
						continue 2;
					}

					$value = trim( (string) $row['data'][ $col ] );
					if ( '' === $value ) {
						continue 2;
					}

					$where[] = idr_quote_identifier( $col ) . ' = %s';
					$params[] = $value;
				}

				if ( empty( $where ) ) {
					continue;
				}

				$sql = "SELECT COUNT(*) FROM " . idr_quote_identifier( $table_name ) . " WHERE " . implode( ' AND ', $where );
				$count = (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );

				if ( $count > 0 ) {
					return [
						'valid' => false,
						'message' => sprintf(
							'Row %d would violate unique index %s in the destination table.',
							(int) $row['row_number'],
							$index_name
						),
					];
				}
			}
		}

		return [ 'valid' => true ];
	}
}
