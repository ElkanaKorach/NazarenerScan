<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$table = new NazarenerScan_Submissions_Table();
$table->prepare_items();
?>
<div class="wrap ns-wrap">
	<h1>
		<span class="dashicons dashicons-list-view"></span>
		Einreichungen
	</h1>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['reviewed'] ) )  : ?><div class="notice notice-success is-dismissible"><p>✅ Einreichung bewertet.</p></div><?php endif; ?>
	<?php if ( isset( $_GET['deleted'] ) )   : ?><div class="notice notice-success is-dismissible"><p>🗑️ Einreichung gelöscht.</p></div><?php endif; ?>

	<form method="get">
		<input type="hidden" name="page" value="nazarener-submissions" />
		<?php if ( ! empty( $_GET['status_filter'] ) ): ?>
			<input type="hidden" name="status_filter" value="<?= esc_attr( $_GET['status_filter'] ) ?>" />
		<?php endif; ?>
		<?php $table->views(); ?>
		<?php $table->search_box( 'Suchen', 'submission_search' ); ?>
		<?php $table->display(); ?>
	</form>
</div>
