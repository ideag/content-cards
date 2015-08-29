<div class="content_cards_card">
	<div class="content_cards_image">
		<?php if ( get_cc_data( 'image' ) ) : ?>
			<a class="content_cards_image_link" href="<?php echo esc_url( get_cc_data( 'url' ) ); ?>"<?php the_cc_target(); ?>>
					<img src="<?php echo esc_url( get_cc_data( 'image' ) ); ?>" alt="<?php echo esc_attr( get_cc_data( 'title' ) ); ?>"/>
			</a>
		<?php endif; ?>
	</div>

	<div class="content_cards_title">
		<?php the_cc_data( 'title' ); ?>
	</div>
	<div class="content_cards_description">
		<?php the_cc_data( 'description' ); ?>
	</div>
	<div class="content_cards_site_name">
		<?php the_cc_data( 'site_name' ); ?>
	</div>
</div>