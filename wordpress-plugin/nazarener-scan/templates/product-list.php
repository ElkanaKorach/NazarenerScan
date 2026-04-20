<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="ns-list-wrap">

	<!-- Filter bar -->
	<form class="ns-list-filter" method="get">
		<?php foreach ( $_GET as $key => $val ):
			if ( in_array( $key, [ 'ns_search','ns_status','ns_page' ], true ) ) continue;
		?>
			<input type="hidden" name="<?= esc_attr( $key ) ?>" value="<?= esc_attr( $val ) ?>" />
		<?php endforeach; ?>

		<div class="ns-list-filter-row">
			<input type="text" name="ns_search" class="ns-input" placeholder="Suche nach Name, Marke, Barcode…"
			       value="<?= esc_attr( $search ) ?>" />

			<select name="ns_status" class="ns-select">
				<option value="" <?= ! $status ? 'selected' : '' ?>>Alle Status</option>
				<option value="green" <?= $status === 'green' ? 'selected' : '' ?>>🟢 Erlaubt</option>
				<option value="red"   <?= $status === 'red'   ? 'selected' : '' ?>>🔴 Nicht erlaubt</option>
			</select>

			<button type="submit" class="ns-btn ns-btn--primary">Suchen</button>
			<?php if ( $search || $status ): ?>
				<a href="?" class="ns-btn">× Zurücksetzen</a>
			<?php endif; ?>
		</div>
	</form>

	<!-- Count -->
	<div class="ns-list-count">
		<?php if ( $total === 0 ): ?>
			Keine Produkte gefunden.
		<?php else: ?>
			<?= esc_html( $total ) ?> Produkt<?= $total !== 1 ? 'e' : '' ?> gefunden
			<?php if ( $search ): ?> für „<?= esc_html( $search ) ?>"<?php endif; ?>
		<?php endif; ?>
	</div>

	<!-- Product grid -->
	<?php if ( $products ): ?>
	<div class="ns-product-grid">
		<?php foreach ( $products as $p ):
			$img_url = $p->product_image_id
				? wp_get_attachment_image_url( (int) $p->product_image_id, 'thumbnail' )
				: '';
		?>
		<div class="ns-product-tile ns-product-tile--<?= esc_attr( $p->status ) ?>">
			<?php if ( $img_url ): ?>
				<img class="ns-product-tile__img" src="<?= esc_url( $img_url ) ?>" alt="<?= esc_attr( $p->name ) ?>" loading="lazy" />
			<?php else: ?>
				<div class="ns-product-tile__img-placeholder">
					<?= $p->status === 'green' ? '🟢' : '🔴' ?>
				</div>
			<?php endif; ?>

			<div class="ns-product-tile__body">
				<div class="ns-product-tile__status">
					<?php if ( $p->status === 'green' ): ?>
						<span class="ns-badge ns-badge--green">🟢 Erlaubt</span>
					<?php else: ?>
						<span class="ns-badge ns-badge--red">🔴 Nicht erlaubt</span>
					<?php endif; ?>
				</div>
				<div class="ns-product-tile__name"><?= esc_html( $p->name ) ?></div>
				<?php if ( $p->brand ): ?>
					<div class="ns-product-tile__brand"><?= esc_html( $p->brand ) ?></div>
				<?php endif; ?>

				<?php if ( $p->status === 'red' && $p->forbidden_ingredients ): ?>
					<div class="ns-product-tile__forbidden">
						<strong>Verboten:</strong> <?= esc_html( $p->forbidden_ingredients ) ?>
					</div>
				<?php endif; ?>

				<?php if ( $p->biblical_reference ): ?>
					<div class="ns-product-tile__ref">📖 <?= esc_html( $p->biblical_reference ) ?></div>
				<?php endif; ?>

				<div class="ns-product-tile__barcode"><?= esc_html( $p->barcode ) ?></div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- Pagination -->
	<?php if ( $pages > 1 ):
		$base_url = remove_query_arg( 'ns_page' );
	?>
	<nav class="ns-pagination">
		<?php for ( $i = 1; $i <= $pages; $i++ ): ?>
			<?php if ( abs( $i - $paged ) <= 2 || $i === 1 || $i === $pages ): ?>
				<a href="<?= esc_url( add_query_arg( 'ns_page', $i, $base_url ) ) ?>"
				   class="ns-page-btn <?= $i === $paged ? 'ns-page-btn--current' : '' ?>">
					<?= esc_html( $i ) ?>
				</a>
			<?php elseif ( abs( $i - $paged ) === 3 ): ?>
				<span class="ns-page-dots">…</span>
			<?php endif; ?>
		<?php endfor; ?>
	</nav>
	<?php endif; ?>

	<?php endif; ?>
</div>
