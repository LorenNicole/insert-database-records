<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IDR_CSV_Parser {
	public function parse( string $file_path ): array {
		$handle = fopen( $file_path, 'rb' );

		if ( ! $handle ) {
			throw new RuntimeException( 'Unable to open CSV file.' );
		}

		$header = fgetcsv( $handle );

		if ( ! is_array( $header ) || empty( $header ) ) {
			fclose( $handle );
			throw new RuntimeException( 'CSV header row is missing or invalid.' );
		}

		$header = array_map( [ $this, 'normalize_header' ], $header );

		$duplicates = array_keys(
			array_filter(
				array_count_values( $header ),
				static fn( $count ) => $count > 1
			)
		);
		
		if ( ! empty( $duplicates ) ) {
			fclose( $handle );
			throw new RuntimeException(
				'CSV header contains duplicate column names: ' . implode( ', ', $duplicates )
			);
		}

		if ( count( $header ) !== count( array_unique( $header ) ) ) {
			fclose( $handle );
			throw new RuntimeException( 'CSV header contains duplicate column names.' );
		}

		$rows = [];
		$row_number = 1;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$row_number++;

			if ( $this->row_is_empty( $row ) ) {
				continue;
			}

			$rows[] = [
				'row_number' => $row_number,
				'values'     => array_map( [ $this, 'normalize_cell' ], $row ),
			];
		}

		fclose( $handle );

		return [
			'header' => $header,
			'rows'   => $rows,
		];
	}

	public function to_assoc_rows( array $parsed ): array {
		$assoc = [];

		foreach ( $parsed['rows'] as $row ) {
			if ( count( $row['values'] ) !== count( $parsed['header'] ) ) {
				throw new RuntimeException(
					sprintf( 'Row %d does not match the header column count.', (int) $row['row_number'] )
				);
			}

			$assoc[] = [
				'row_number' => (int) $row['row_number'],
				'data'       => array_combine( $parsed['header'], $row['values'] ),
			];
		}

		return $assoc;
	}

	private function normalize_header( string $value ): string {
		$value = preg_replace( '/^\xEF\xBB\xBF/', '', $value );
		$value = trim( $value );
		return $value;
	}

	private function normalize_cell( $value ): string {
		$value = is_string( $value ) ? $value : (string) $value;
		return $this->ensure_utf8( $value );
	}

	private function ensure_utf8( string $value ): string {
		if ( mb_check_encoding( $value, 'UTF-8' ) ) {
			return $value;
		}

		$converted = mb_convert_encoding( $value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252' );
		return is_string( $converted ) ? $converted : $value;
	}

	private function row_is_empty( array $row ): bool {
		foreach ( $row as $value ) {
			if ( trim( (string) $value ) !== '' ) {
				return false;
			}
		}
		return true;
	}
}
