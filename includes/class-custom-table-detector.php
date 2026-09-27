<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IDR_Custom_Table_Detector {
	private IDR_DB $db;

	public function __construct( IDR_DB $db ) {
		$this->db = $db;
	}

	public function detect(): array {
		global $wpdb;

		$all_tables = $this->db->get_col( 'SHOW TABLES' );

		$core_tables = array_filter( [
			$wpdb->posts,
			$wpdb->postmeta,
			$wpdb->users,
			$wpdb->usermeta,
			$wpdb->options,
			$wpdb->terms,
			$wpdb->termmeta,
			$wpdb->term_taxonomy,
			$wpdb->term_relationships,
			$wpdb->comments,
			$wpdb->commentmeta,
			$wpdb->links,
			$wpdb->blogs ?? null,
			$wpdb->blogmeta ?? null,
			$wpdb->site ?? null,
			$wpdb->sitemeta ?? null,
			$wpdb->signups ?? null,
			$wpdb->registration_log ?? null,
		] );

		$plugin_tables = [
			$wpdb->prefix . 'idr_import_batches',
			$wpdb->prefix . 'idr_import_batch_rows',
		];

		$disallowed = array_unique( array_merge( $core_tables, $plugin_tables ) );

		$custom = array_filter( $all_tables, static function ( $table ) use ( $disallowed ) {
			return ! in_array( $table, $disallowed, true );
		} );

		sort( $custom );

		return array_values( $custom );
	}
}
