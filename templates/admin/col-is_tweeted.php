<?php if ( $post->post_status == 'draft' ) : ?>
	<span style="opacity: .382">Draft</span>
	<?php return; endif ?>

<?php if ( $post_meta['is_tweet_failed'] == 1 ) : ?>
	<b style="color: tomato; color: white; padding: .5em;">Failed</b>
<?php else : // is_tweet_failed ?>

	<?php if ( $post_meta['is_tweeted'] == 1 ) : ?>
		<b style="color: limegreen;">Tweeted</b>
	<?php else : // $is_tweeted ?>
		Scheduled
	<?php endif // $is_tweeted ?>
<?php endif // $is_tweet_failed
?>