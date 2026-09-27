<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IDR_Table_Registry {
	private IDR_Custom_Table_Detector $detector;

	public function __construct( IDR_Custom_Table_Detector $detector ) {
		$this->detector = $detector;
	}

	public function get_allowed_tables(): array {
		return $this->detector->detect();
	}

	public function is_allowed_table( string $table_name ): bool {
		return in_array( $table_name, $this->get_allowed_tables(), true );
	}
}
