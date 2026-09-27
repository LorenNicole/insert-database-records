<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function idr_quote_identifier( string $identifier ): string {
	if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $identifier ) ) {
		throw new InvalidArgumentException( 'Unsafe SQL identifier detected.' );
	}

	return '`' . $identifier . '`';
}

function idr_generate_product_id(): string {
	return bin2hex( random_bytes( 16 ) ); // 32 chars
}

function idr_build_storage_path( string $head, string $body, string $tail, string $productId, string $filename ): string {
	$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

	$base_path = "{$head}/{$body}/{$tail}/{$productId}";

	if ( 'jpg' === $extension || 'jpeg' === $extension ) {
		return "{$base_path}/display/original/{$filename}";
	}

	if ( 'png' === $extension ) {
		return "{$base_path}/print/original/{$filename}";
	}

	// fallback (optional)
	return "{$base_path}/original/{$filename}";
}
