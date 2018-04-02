<style>
	.<?php echo self::$post_type ?>-copy-wrapper {
		position: relative;
	}

	.<?php echo self::$post_type ?>-copy-button {

	}

	.<?php echo self::$post_type ?>-copy-form {
		background: white;
		border: 1px solid #999;
		display: none;
		right: 0;
		min-height: 200px;
		min-width: 200px;
		padding: 10px;
		position: absolute;
		top: 0;
		z-index: 1;

		-webkit-box-shadow: 0px 0px 10px 0px rgba(0,0,0,.382);
		-moz-box-shadow: 0px 0px 10px 0px rgba(0,0,0,.382);
		box-shadow: 0px 0px 10px 0px rgba(0,0,0,.382);
	}
</style>

<script>
	jQuery(function ($) {

		$('.<?php echo self::$post_type ?>-copy-form-toggle').on(
			'click',
			function () {
				var $btn = $(this);
				var $wrapper = $btn.closest('.<?php echo self::$post_type ?>-copy-wrapper');
				var $url = $('.<?php echo self::$post_type ?>-copy-url', $wrapper);

				$('.<?php echo self::$post_type ?>-copy-form', $wrapper).show();

				var date = $btn.attr('data-date');
				var time = $btn.attr('data-time');


				$('.datepicker', $wrapper)
					.datepicker({
						firstDay: 0,
						minDate: new Date(),
						dayNames: [ "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday" ],
						dayNamesMin: [ "Su", "Mo", "Tu", "We", "Th", "Fr", "Sa" ],
						dateFormat: "yy-mm-dd",
						defaultDate: date
					});


				$('.timepicker', $wrapper).timepicker({
					timeFormat: 'H:i'
				})
				.val(time);
			}
		);

	$('.<?php echo self::$post_type ?>-copy-form-submit').on(
		'click',
		function () {
			var $btn = $(this);
			var $wrapper = $btn.closest('.<?php echo self::$post_type ?>-copy-wrapper');

			var date = $('.datepicker', $wrapper).datepicker( "getDate" );
			var time = $('.timepicker', $wrapper).timepicker('getTime');
			var post_id = $btn.attr('data-post_id');

			if ( !(date instanceof Date) || !(time instanceof Date) || '' == post_id || isNaN(post_id) ) {
				return false;
			}

			var params = {
				date: date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + date.getDate() + ' ' + time.getHours() + ':' + time.getMinutes() + ':00',
				post_id: post_id
			};

			var queryString = $.param(params);

			var url = '<?php echo add_query_arg(array(
					'post_type' => self::$post_type,
					self::$post_type => 'copy'

				)); ?>&' + queryString;

			window.location = url;
		});

		$('.<?php echo self::$post_type ?>-copy-cancel').on(
			'click',
			function () {
				var $wrapper = $(this).closest('.<?php echo self::$post_type ?>-copy-wrapper');

				$('.<?php echo self::$post_type ?>-copy-form', $wrapper).hide();

				return false;
			}
		);
	});
</script>