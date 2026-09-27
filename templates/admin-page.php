<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap idr-wrap">
	<h1><?php echo esc_html__( 'Insert Database Records', 'insert-database-records' ); ?></h1>
	<p><?php echo esc_html__( 'Upload a CSV and insert rows into a selected custom table.', 'insert-database-records' ); ?></p>

	<div id="idr-notices"></div>

	<form id="idr-import-form">
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="idr_table"><?php esc_html_e( 'Destination Table', 'insert-database-records' ); ?></label>
					</th>
					<td>
						<select id="idr_table" name="table_name" required>
							<option value=""><?php esc_html_e( 'Select a custom table', 'insert-database-records' ); ?></option>
							<?php foreach ( $tables as $table_name ) : ?>
								<option value="<?php echo esc_attr( $table_name ); ?>">
									<?php echo esc_html( $table_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Only allowed custom tables are shown.', 'insert-database-records' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="idr_csv"><?php esc_html_e( 'CSV File', 'insert-database-records' ); ?></label>
					</th>
					<td>
						<input type="file" id="idr_csv" name="csv_file" accept=".csv,text/csv" required />
						<p class="description"><?php esc_html_e( 'First row must be a header row matching destination columns.', 'insert-database-records' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="button" class="button button-secondary" id="idr-validate-btn">
				<?php esc_html_e( 'Validate CSV', 'insert-database-records' ); ?>
			</button>
			<button type="button" class="button button-primary" id="idr-insert-btn">
				<?php esc_html_e( 'Insert Records', 'insert-database-records' ); ?>
			</button>
			<button type="button" class="button" id="idr-delete-btn">
				<?php esc_html_e( 'Delete Last Inserted Records', 'insert-database-records' ); ?>
			</button>
		</p>
	</form>

	<div id="idr-modal" class="idr-modal" hidden>
		<div class="idr-modal__content">
			<h2 id="idr-modal-title"><?php esc_html_e( 'Confirm Action', 'insert-database-records' ); ?></h2>
			<p id="idr-modal-message"></p>
			<div class="idr-modal__actions">
				<button type="button" class="button button-primary" id="idr-modal-confirm"><?php esc_html_e( 'Confirm', 'insert-database-records' ); ?></button>
				<button type="button" class="button" id="idr-modal-cancel"><?php esc_html_e( 'Cancel', 'insert-database-records' ); ?></button>
			</div>
		</div>
	</div>
</div>
