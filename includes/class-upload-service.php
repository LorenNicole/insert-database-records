<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IDR_Upload_Service {
	public function handle_upload_from_files( array $file ): array {
		if ( empty( $file['tmp_name'] ) || empty( $file['name'] ) ) {
			return [
				'success' => false,
				'message' => 'No file was uploaded.',
			];
		}

		$ext = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
		if ( 'csv' !== $ext ) {
			return [
				'success' => false,
				'message' => 'Only .csv files are allowed.',
			];
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$overrides = [
			'test_form' => false,
			'mimes'     => [
				'csv' => 'text/csv',
			],
		];

		$uploaded = wp_handle_upload( $file, $overrides );

		if ( isset( $uploaded['error'] ) ) {
			return [
				'success' => false,
				'message' => $uploaded['error'],
			];
		}

		$path = (string) $uploaded['file'];
		$url  = (string) $uploaded['url'];

		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return [
				'success' => false,
				'message' => 'Uploaded file could not be read.',
			];
		}

		$token = wp_generate_password( 32, false, false );
		$key   = IDR_UPLOAD_OPTION_PREFIX . $token;

		$payload = [
			'path'          => $path,
			'url'           => $url,
			'file_name'     => sanitize_file_name( wp_basename( $path ) ),
			'original_name' => sanitize_file_name( $file['name'] ),
			'created_at'    => time(),
			'user_id'       => get_current_user_id(),
		];

		update_option( $key, $payload, false );

		return [
			'success'   => true,
			'token'     => $token,
			'file_name' => $payload['file_name'],
		];
	}

	public function resolve_token( string $token ): ?array {
		$key = IDR_UPLOAD_OPTION_PREFIX . $token;
		$data = get_option( $key );

		if ( ! is_array( $data ) ) {
			return null;
		}

		if ( empty( $data['created_at'] ) || ( time() - (int) $data['created_at'] ) > IDR_UPLOAD_TTL ) {
			$this->delete_token_file( $token );
			return null;
		}

		if ( (int) $data['user_id'] !== get_current_user_id() ) {
			return null;
		}

		if ( empty( $data['path'] ) || ! file_exists( $data['path'] ) || ! is_readable( $data['path'] ) ) {
			return null;
		}

		return $data;
	}

	public function delete_token_file( string $token ): void {
		$key = IDR_UPLOAD_OPTION_PREFIX . $token;
		$data = get_option( $key );

		if ( is_array( $data ) && ! empty( $data['path'] ) && file_exists( $data['path'] ) ) {
			wp_delete_file( $data['path'] );
		}

		delete_option( $key );
	}
}
