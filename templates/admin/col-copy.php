<div class="<?php echo self::$post_type ?>-copy-wrapper">
	<a href="#" class="button button-default <?php echo self::$post_type ?>-copy-form-toggle"
	   data-date="<?php echo esc_attr($dt->format('Y-m-d')) ?>"
	   data-time="<?php echo esc_attr($dt->format('H:i')) ?>">
		Copy
	</a>

	<div class="<?php echo self::$post_type ?>-copy-form">
		<div class="datepicker"></div>

		<p style="margin-top: 10px; text-align: center;">
			At: <input type="text" class="timepicker">
		</p>

		<p style="margin-top: 10px; text-align: center;">
			<button type="button"
			        data-post_id="<?php echo esc_attr($post->ID) ?>"
		        class="button button-primary <?php echo self::$post_type ?>-copy-form-submit">Copy</button>
			<a href="#" class="<?php echo self::$post_type ?>-copy-cancel">Cancel</a>
		</p>
	</div>
</div>