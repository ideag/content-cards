<div class="content_cards_card">
	<a href="<?php echo esc_url( get_cc_data( 'url' ) ); ?>"<?php the_cc_target(); ?>>
<?php if ( get_cc_data( 'image' ) ) : ?>
		<img src="<?php echo esc_url( get_cc_data( 'image' ) ); ?>" alt="<?php echo esc_attr( get_cc_data( 'title' ) ); ?>"/>
<?php endif; ?>
		<h2><?php the_cc_data( 'title' ); ?></h2>
		<div class="content_cards_description"><?php the_cc_data( 'description' ); ?></div>
	</a>
	<p class="content_cards_site">via <?php the_cc_data( 'site_name' ); ?></p>
</div>