<div class="content_cards_card">
	<?php if ( get_cc_data( 'image' ) ) : ?>
		<div class="content_cards_image">
				<a class="content_cards_image_link" href="<?php the_cc_data( 'url', 'esc_url' ); ?>"<?php the_cc_target(); ?>>
						<img src="<?php the_cc_data( 'image', 'esc_url' ); ?>" alt="<?php the_cc_data( 'title', 'esc_attr' ); ?>" />
				</a>
		</div>
	<?php endif; ?>

	<div class="content_cards_title">
		<a class="content_cards_title_link" href="<?php the_cc_data( 'url', 'esc_url' ); ?>"<?php the_cc_target(); ?>>
			<?php the_cc_data( 'title' ); ?>
		</a>
	</div>
	<div class="content_cards_description">
		<a class="content_cards_description_link" href="<?php the_cc_data( 'url', 'esc_url' ); ?>"<?php the_cc_target(); ?>>
			<?php the_cc_data( 'description' ); ?>
		</a>
	</div>
	<div class="content_cards_site_name">
		<?php the_cc_data( 'site_name' ); ?>
	</div>
</div>