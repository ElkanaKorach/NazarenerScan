<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$table = new NazarenerScan_Activity_Log_Table();
$table->prepare_items();
?>
<div class="wrap ns-wrap">
	<h1><span class="dashicons dashicons-list-view"></span> Aktivitätslog</h1>
	<p class="description">Alle Änderungen an Produkten und Einreichungen – wer hat was wann gemacht.</p>
	<hr class="wp-header-end">
	<?php $table->display(); ?>
</div>
