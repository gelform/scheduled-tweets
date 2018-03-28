<div class="wrap">
	<h2>Add many</h2>
</div>


<form action="" method="post">

	<table class="form-table">
		<tbody>
		<?php
		$x = 0;
		$y = 1;
		?>

		<?php for($i=0;$i<=5;$i++) : $z = $x + $y;  ?>
		<tr>
			<th>
				<label>On day:<br>
				<input type="number" step="1" min="1" value="<?php echo $z ?>" class="regular-text">
				</label>
			</th>
			<td>
				<label>Tweet:<br>
				<textarea class="large-text" rows="4"></textarea>
				</label>
			</td>
		</tr>
		<?php $x=$y;
			$y=$z;
			endfor ?>
		</tbody>
	</table>

	<?php
	wp_nonce_field( 'save_settings', self::$post_type );
	submit_button( 'Submit' );
	?>

</form>


<style>
	.wp-editor-container textarea.wp-editor-area {
		/*max-height: 10em;*/
	}
</style>