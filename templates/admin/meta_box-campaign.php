<p class="<?php echo self::$post_type ?>_campaigns-p">
	<label>
	Choose an existing campaign:<br>
	<select name="<?php echo self::$post_type . '_campaigns' ?>"
	        id="<?php echo self::$post_type . '_campaigns' ?>-select"
	        style="width: 100%;">
		<option value=''
			<?php if (!count($names)) echo "selected";?>>-- Choose a campaign --</option>
		<?php foreach ($campaigns as $campaign) :  ?>
			<option value="<?php echo $campaign->slug ?>"
				<?php echo !is_wp_error($names) && !empty($names) && !strcmp($campaign->slug, $names[0]->slug) ? 'selected' : '' ?>>
				<?php echo $campaign->name ?>
			</option>
		<?php endforeach ?>
	</select>
	</label>

	<br>
	<br>

	Or <button type="button"
	        class="button <?php self::$post_type ?>-campaign-add-toggle">
		Add a new campaign
	</button>
</p>

<p class="<?php echo self::$post_type ?>_campaigns-p" style="display: none;">
	<label>
		Add your new campaign:<br>
	<input type="text"
	       placeholder="New campaign name"
	       style="width: 100%;"
	       id="<?php self::$post_type ?>-campaign-new">
	</label>
	<br>
	<br>
	<button type="button"
	        id="<?php self::$post_type ?>-campaign-add"
	        class="button">
		Add
	</button>
	<a href="#" class="<?php self::$post_type ?>-campaign-add-toggle">Cancel</a>
</p>

<br style="clear: both; height: 0;">

<script>
	jQuery(function ($) {
		$('.<?php self::$post_type ?>-campaign-add-toggle').on(
			'click',
			function () {
				$('.<?php echo self::$post_type ?>_campaigns-p').toggle();

				return false;
			}
		);

		$('#<?php self::$post_type ?>-campaign-add').on(
			'click',
			function () {
				var $input = $('#<?php self::$post_type ?>-campaign-new');
				var val = $input.val();

				if ( '' == val ) {
					return false;
				}

				var $select = $('#<?php echo self::$post_type . '_campaigns' ?>-select');

				$('<option value="' + val + '">' + val + '</option>').prependTo($select);
				$select.val($("option:first", $select).val());

				$input.val('');
				$('.<?php echo self::$post_type ?>_campaigns-p').toggle();

				return false;
			}
		);
	});
</script>