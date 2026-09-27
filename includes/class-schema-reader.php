<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IDR_Schema_Reader {
	private IDR_DB $db;

	public function __construct( IDR_DB $db ) {
		$this->db = $db;
	}

	public function get_columns( string $table_name ): array {
		$safe_table = idr_quote_identifier( $table_name );
		$sql = "SHOW COLUMNS FROM {$safe_table}";
		$rows = $this->db->get_results( $sql, ARRAY_A );

		$columns = [];
		foreach ( $rows as $row ) {
			$extra = strtolower( (string) $row['Extra'] );

			$columns[ $row['Field'] ] = [
				'name'           => $row['Field'],
				'type'           => $row['Type'],
				'nullable'       => $row['Null'] === 'YES',
				'key'            => $row['Key'],
				'default'        => $row['Default'],
				'auto_increment' => strpos( $extra, 'auto_increment' ) !== false,
				'generated'      => strpos( $extra, 'generated' ) !== false,
				'extra'          => $row['Extra'],
			];
		}

		return $columns;
	}

	public function get_primary_key_column( string $table_name ): ?string {
		$columns = $this->get_columns( $table_name );

		foreach ( $columns as $column ) {
			if ( $column['key'] === 'PRI' ) {
				return $column['name'];
			}
		}

		return null;
	}

	public function get_unique_indexes( string $table_name ): array {
		$safe_table = idr_quote_identifier( $table_name );
		$rows = $this->db->get_results( "SHOW INDEX FROM {$safe_table}", ARRAY_A );

		$indexes = [];

		foreach ( $rows as $row ) {
			if ( (int) $row['Non_unique'] !== 0 ) {
				continue;
			}

			$key = $row['Key_name'];

			if ( ! isset( $indexes[ $key ] ) ) {
				$indexes[ $key ] = [];
			}

			$indexes[ $key ][ (int) $row['Seq_in_index'] ] = $row['Column_name'];
		}

		foreach ( $indexes as $name => $cols ) {
			ksort( $cols );
			$indexes[ $name ] = array_values( $cols );
		}

		return $indexes;
	}
}
