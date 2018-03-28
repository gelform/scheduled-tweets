<div class="wrap">
	<h2>Settings</h2>

	<?php if ( isset( $_GET['message'] ) ) : ?>
		<div class="notice notice-success">
			<p><?php echo $_GET['message'] ?></p>
		</div>
	<?php endif ?>

	<p>
		Instructions: Enter your Twitter app credentials below.
		If you need to create a new Twitter app, follow <a href="#section-instructions">these instructions</a>.
	</p>

	<form action="" method="post">

		<table class="form-table">
			<tbody>
			<tr>
				<th>
					<label for="consumer_key">
						Consumer key
					</label>
				</th>
				<td>
					<input name="consumer_key" type="text" id="consumer_key"
					       value="<?php echo esc_attr( $keys['consumer_key'] ) ?>" class="regular-text"
					       autocomplete="off">
				</td>
			</tr>
			<tr>
				<th>
					<label for="consumer_secret">
						Consumer Secret
					</label>
				</th>
				<td>
					<input name="consumer_secret" type="text" id="consumer_secret"
					       value="<?php echo esc_attr( $keys['consumer_secret'] ) ?>"
					       class="regular-text" autocomplete="off">
				</td>
			</tr>
			<tr>
				<th>
					<label for="access_token">
						Access Token
					</label>
				</th>
				<td>
					<input name="access_token" type="text" id="access_token"
					       value="<?php echo esc_attr( $keys['access_token'] ) ?>" class="regular-text"
					       autocomplete="off">
				</td>
			</tr>
			<tr>
				<th>
					<label for="access_secret">
						Access Secret
					</label>
				</th>
				<td>
					<input name="access_secret" type="text" id="access_secret"
					       value="<?php echo esc_attr( $keys['access_secret'] ) ?>" class="regular-text"
					       autocomplete="off">
				</td>
			</tr>
			</tbody>
		</table>

		<?php
		wp_nonce_field( 'save_settings', self::$post_type );
		submit_button( 'Submit' );
		?>

	</form>

	<hr id="section-instructions">

	<h3>How to get your credentials</h3>

	<ol>
		<li>
			Visit <a href="https://apps.twitter.com/" target="_blank">https://apps.twitter.com/</a>
		</li>
		<li>
			<p>
				Click "create new app".
			</p>
			<p>
				<img src="<?php echo $plugin_dir_url ?>/img/create-new-app.png">
			</p>
		</li>
		<li>
			<p>
				Fill out the required fields.
				Recommended: use your domain as a name.
			</p>
			<p>
				<img src="<?php echo $plugin_dir_url ?>/img/create-app-form.png">
			</p>
		</li>
		<li>
			<p>
				You should see a confirmation notice that your app is created.
				Copy your consumer key from the data shown into the form above.
			</p>
			<p>
				<img src="<?php echo $plugin_dir_url ?>/img/app-created.png">
			</p>
		</li>
		<li>
			<p>
				Click on the "Keys and Access Tokens" tab.
			</p>
			<p>
				<img src="<?php echo $plugin_dir_url ?>/img/keys-tab.png">
			</p>
		</li>
		<li>
			<p>
				Copy your consumer key and consumer key into the form above.
			</p>
			<p>
				<img src="<?php echo $plugin_dir_url ?>/img/consumer-keys.png">
			</p>
		</li>
		<li>
			<p>
				Scroll down to the "Your Access Token" section.
				Click the "Create my access token" button.
			</p>
			<p>
				<img src="<?php echo $plugin_dir_url ?>/img/create-access-token-button.png">
			</p>
		</li>
		<li>
			<p>
				Copy the Access Token and Access Token Secret into the form above.
			</p>
			<p>
				<img src="<?php echo $plugin_dir_url ?>/img/access-tokens.png">
			</p>
		</li>
	</ol>
</div><!--wrap-->