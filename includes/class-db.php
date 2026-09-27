<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IDR_DB {
	public function get_results( string $sql, string $output = OBJECT ) {
		global $wpdb;
		return $wpdb->get_results( $sql, $output );
	}

	public function get_col( string $sql ): array {
		global $wpdb;
		return $wpdb->get_col( $sql );
	}
}
