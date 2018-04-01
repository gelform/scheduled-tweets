<p>
	Status:
	<?php if ( $post_meta['is_tweet_failed'] == 1 ) : ?>
		<b style="background: tomato; color: white; padding: .5em;">Failed!</b>
	<?php else : // is_tweet_failed ?>
		<?php if ( $post_meta['is_tweeted'] == 1 ) : ?>
			<b style="background: limegreen; color: white; padding: .5em;">Tweeted!</b>
		<?php else : // $is_tweeted ?>
			Not yet
		<?php endif // $is_tweeted ?>
	<?php endif // $is_tweet_failed ?>
</p>


<p>
	<?php if ( $post_meta['is_tweeted'] == 1 ) : ?>

		Tweeted: <?php echo self::ago( $dt ); ?>

	<?php elseif ( $post->post_status != 'auto-draft' && $post->post_status != 'draft' ) : // $is_tweeted ?>
		Scheduled: <?php echo self::ago( $dt ); ?>
	<?php endif // $is_tweeted ?>
</p>

<?php if ( $post_meta['is_tweet_failed'] == 1 ) : ?>
	<p>
		Failed: <?php echo self::ago( $dt ); ?>
	</p>
	<p>
		Reason: <?php echo $post_meta['tweet_failed_reason'] ?>
	</p>
<?php endif // $is_tweeted ?>

