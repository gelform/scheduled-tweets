<?php if ( $post->post_status == 'draft' ) : ?>
	<span style="opacity: .382">Draft</span>
<?php else : ?>

<?php echo self::ago( $dt ); ?>
<?php endif ?>
