<?php

/**
 * Plugin Name: Scheduled Tweets
 * Description: Schedule tweets to tweet to your twitter account
 * Version: 0.0.1
 *
 * @todo :
 * - Bulk upload (CSV?)
 * - Make preview live
 * - Test empty message, other errors
 * - Add drag and drop to calendar
 *
 * @link https://developer.twitter.com/en/docs/tweets/post-and-engage/overview
 */


class Scheduled_Tweets {

	private static $post_type = 'scheduled_tweets';

	private static $exchange = null;

	static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedule' ) );
		add_action( 'init', array( __CLASS__, 'schedule_cron' ), 0 );

		add_action( 'init', array( __CLASS__, 'register_post_type' ), 0 );
		add_filter( 'wp_editor_settings', array( __CLASS__, 'remove_tinymce' ), 10, 2 );

		add_action( self::$post_type . '_check', array( __CLASS__, 'check_for_posts' ) );

		add_filter( 'wp_insert_post_data', array( __CLASS__, 'set_title' ), '99', 2 );

		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'dbx_post_sidebar', array( __CLASS__, 'editor_char_count' ) );

		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'save_settings' ) );

		add_filter( 'manage_' . self::$post_type . '_posts_columns', array( __CLASS__, 'manage_posts_columns' ) );
		add_filter( 'manage_' . self::$post_type . '_posts_custom_column', array(
			__CLASS__,
			'manage_posts_custom_column'
		), 10, 2 );
//		add_action( 'pre_get_posts', array( __CLASS__, 'admin_list_order'), 9 );
	}

	static function schedule_cron() {

		if ( ! wp_next_scheduled( self::$post_type . '_check' ) ) {
			wp_schedule_event( time(), '5min', self::$post_type . '_check' );
		}
	}

	static function add_cron_schedule( $schedules ) {
		if ( ! isset( $schedules["5min"] ) ) {
			$schedules["5min"] = array(
				'interval' => 5 * 60,
				'display'  => __( 'Once every 5 minutes' )
			);
		}

		return $schedules;
	}

	static function editor_char_count() {
		?>

		<style>
			#content {
				max-height: 10em;
			}
		</style>

		<script type="text/javascript">
			(function ($) {
				wpCharCount = function (txt) {
					var len = '' + txt.length;

					var html = len;
					if (len > 140) {
						var html = '<b style="background: tomato; color: white; padding: .5em;">' + len + '</b>';
					}

					$('.char-count').html(html);
					return len;
				};
				$(document).ready(function () {
					$('#wp-word-count').append('<br />Char count: <span class="char-count">0</span>');

					$('#content').bind('change keydown paste', function () {
						var val = $('#content').val();
						var count = wpCharCount(val);
						if ( count > 300 ) {
							$('#content').val(
								val.substring(0, 300)
							);

							wpCharCount(val);
							return false;
						}
					});

					$('#content').trigger('keydown');
				})
				.bind('wpcountwords', function (e, txt) {
					wpCharCount(txt);
				});


			}(jQuery));
		</script>
		<?php
	}

	static function check_for_posts() {

		$now_dt = new Datetime( 'now' );

		$tweets = get_posts( array(
			'post_type'   => self::$post_type,
			'post_status' => array( 'publish', 'future' ),
			'numberposts' => - 1,
			'date_query'  => array(
				'column' => 'post_date',
				'before' => 'now'
			),
			'meta_query'  => array(
				'relation' => 'OR',
				array(
					'key'     => 'is_tweeted',
					'value'   => '1',
					'compare' => '!=',
				),
				array(
					'key'     => 'is_tweeted',
					'compare' => 'NOT EXISTS',
				)
			)
		) );

		if ( count( $tweets ) == 0 ) {
			return;
		}

		self::connect();

		foreach ( $tweets as $tweet ) {

			$media_id = null;

			if ( has_post_thumbnail( $tweet->ID ) ) {
				$post_thumbnail_id = get_post_thumbnail_id( $tweet->ID );

				$attached_file = get_attached_file( $post_thumbnail_id );

				if ( is_file( $attached_file ) ) {
					$file   = file_get_contents( $attached_file );
					$data   = base64_encode( $file );
					$url    = 'https://upload.twitter.com/1.1/media/upload.json';
					$method = 'POST';
					$params = array(
						'media_data' => $data
					);

					try {
						$data = self::$exchange->request( $url, $method, $params );

						update_post_meta( $tweet->ID, 'featured_image_tweet_data', $data );

						$data     = @json_decode( $data, true );
						$media_id = $data['media_id'];

					} catch ( Exception $e ) {
						update_post_meta( $tweet->ID, 'featured_image_is_tweet_failed', true );
						update_post_meta( $tweet->ID, 'featured_image_tweet_failed_reason', $e->getMessage() );
					}
				}
			}

			$url    = 'https://api.twitter.com/1.1/statuses/update.json';
			$method = 'POST';
			$params = array(
				'status'             => self::build_tweet_content( $tweet ),
				'possibly_sensitive' => false
			);

			if ( ! is_null( $media_id ) ) {
				$params['media_ids'] = $media_id;
			}

			try {
				$data = self::$exchange->request( $url, $method, $params );

				update_post_meta( $tweet->ID, 'tweet_data', $data );

			} catch ( Exception $e ) {
				update_post_meta( $tweet->ID, 'is_tweet_failed', true );
				update_post_meta( $tweet->ID, 'tweet_failed_reason', $e->getMessage() );
			}

			// Publish post, just in case.
			$post = array(
				'ID'          => $tweet->ID,
				'post_status' => 'publish'
			);

			wp_update_post( $post );

			update_post_meta( $tweet->ID, 'is_tweeted', true );
			update_post_meta( $tweet->ID, 'tweeted_dt', $now_dt->format( 'Y-m-d H:i:s' ) );
		}
	}

	static function connect() {
		require_once( __DIR__ . '/TwitterAPIExchange.php' );

		$keys = get_option( 'scheduled_tweets_settings' );

		self::$exchange = new TwitterAPIExchange( array(
			'consumer_key'              => $keys['consumer_key'],
			'consumer_secret'           => $keys['consumer_secret'],
			'oauth_access_token'        => $keys['access_token'],
			'oauth_access_token_secret' => $keys['access_secret']
		) );
	}

	static function build_tweet_content( $post ) {
		$terms = wp_get_post_terms( $post->ID, self::$post_type . '_tags' );

		$term_names = array();
		foreach ( $terms as $term ) {
			$term_names[] = '#' . $term->name;
		}

		$content = sprintf(
			'%s %s',
			$post->post_content,
			implode( ' ', $term_names )
		);

		return $content;
	}

	static function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=' . self::$post_type,
			'Calendar',
			'Calendar',
			'manage_options',
			self::$post_type . '_calendar',
			array( __CLASS__, 'render_admin_menu_calendar' )
		);

		add_submenu_page(
			'edit.php?post_type=' . self::$post_type,
			'Settings',
			'Settings',
			'manage_options',
			self::$post_type . '_settings',
			array( __CLASS__, 'render_admin_menu_settings' )
		);
	}

	static function render_admin_menu_calendar() {

		$headings = array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' );

		$month = date( 'm' );
		if ( isset( $_GET['month'] ) ) {
			$month = intval( $_GET['month'] );
		}

		$year = date( 'Y' );
		if ( isset( $_GET['year'] ) ) {
			$year = intval( $_GET['year'] );
		}

		if ( ! is_numeric( $month ) || $month < 1 || $month > 12 ) {
			$month = date( 'm' );
		}

		if ( ! is_numeric( $year ) || strlen( $year ) != 4 ) {
			$year = date( 'Y' );
		}

		$dt      = new DateTime( sprintf( '1-%d-%d', $month, $year ) );
		$dt_prev = clone $dt;
		$dt_prev->modify( '-1 month' );

		$dt_next = clone $dt;
		$dt_next->modify( '+1 month' );

		$running_day       = $dt->format( 'w' );
		$days_in_month     = $dt->format( 't' );
		$days_in_this_week = 1;
		$day_counter       = 0;
		$dates_array       = array();


		$tweets = get_posts( array(
			'post_type'   => self::$post_type,
			'post_status' => array( 'publish', 'future' ),
			'numberposts' => - 1,
			'orderby' => 'post_date_gmt',
			'date_query'  => array(
				'column' => 'post_date',
				'after'  => $dt_prev->format( 'Y-m-t' ),
				'before' => $dt_next->format( 'Y-m-d' )
			)
		) );

		$tweets_by_date = array();
		foreach ( $tweets as $tweet ) {
			$date = substr( $tweet->post_date, 0, 10 );

			if ( ! isset( $tweets_by_date[ $date ] ) ) {
				$tweets_by_date[ $date ] = array();
			}

			$tweets_by_date[ $date ][] = $tweet;
		}


		?>
		<div class="wrap">
			<h2>Calendar</h2>

			<style>
				table.calendar {
					border-spacing: 2px;
					border-collapse: separate;
					width: 100%;
				}

				table.calendar th {
					text-align: center;
					padding-bottom: 5px;
					width: <?php echo 100/7 ?>%;
				}

				table.calendar td {
					background: white;
					max-width: 0;
					height: 6em;
					width: <?php echo 100/7 ?>%;
					padding: 3px;
					overflow: hidden;
					text-overflow: ellipsis;
					white-space: nowrap;
					vertical-align: top;
				}

				table.calendar .calendar-day-np {
					background: #EEE;
				}

				.tweet {
					color: #999;
					display: block;
					overflow: hidden;
					width: 100%;
					text-overflow: ellipsis;
					text-decoration: none;
				}

				.tweet.failed {
					color: tomato;
				}

				.tweet.tweeted {
					color: limegreen;
				}

			</style>


			<table cellpadding="0" cellspacing="0" class="calendar">

				<thead>
				<tr>
					<th colspan="2">
						<a href="<?php echo add_query_arg( array(
							'month' => $dt_prev->format( 'm' ),
							'year'  => $dt_prev->format( 'Y' )
						) ) ?>">
							&lt;
							<?php echo $dt_prev->format( 'M, Y' ) ?>
						</a>
					</th>
					<th colspan="3">
						<h3><?php echo $dt->format( 'M, Y' ) ?></h3>
					</th>
					<th colspan="2">
						<a href="<?php echo add_query_arg( array(
							'month' => $dt_next->format( 'm' ),
							'year'  => $dt_next->format( 'Y' )
						) ) ?>">
							<?php echo $dt_next->format( 'M, Y' ) ?>
							&gt;
						</a>
					</th>
				</tr>
				</thead>

				<tbody>

				<tr class="calendar-row">
					<?php foreach ( $headings as $heading ) : ?>
						<th class="calendar-day-head">
							<?php echo $heading ?>
						</th>
					<?php endforeach; // headings ?>
				</tr>

				<tr class="calendar-row">

					<?php for ( $x = 0; $x < $running_day; $x ++ ): ?>
						<td class="calendar-day-np">&nbsp;</td>
						<?php $days_in_this_week ++; ?>
					<?php endfor; ?>

					<?php for ( $list_day = 1;
					$list_day <= $days_in_month;
					$list_day ++ ): ?>
					<?php $list_date = sprintf(
						'%s-%s-%s',
						$year,
						str_pad( $month, 2, '0', STR_PAD_LEFT ),
						str_pad( $list_day, 2, '0', STR_PAD_LEFT )
					); ?>
					<td class="calendar-day" data-date="<?php echo $list_date ?>">
						<div class="day-number"><?php echo $list_day ?></div>

						<?php if ( isset( $tweets_by_date[ $list_date ] ) ) : $tweets_by_date[ $list_date ] = array_reverse($tweets_by_date[ $list_date ]); ?>
							<?php foreach ( $tweets_by_date[ $list_date ] as $tweet ) : ?>
								<?php

								$tweet_dt = new DateTime( $tweet->post_date );

								$post_meta = self::get_post_meta( $tweet->ID );

								$class = '';
								if ( $post_meta['is_tweet_failed'] == 1 ) {
									$class = 'failed';
								} else {
									if ( $post_meta['is_tweeted'] == 1 ) {
										$class = 'tweeted';
									}
								}

								?>
								<a href="<?php echo admin_url( 'post.php?action=edit&post=' . $tweet->ID ) ?>"
								   class="tweet <?php echo $class ?>">
									<small>
									<?php echo $tweet_dt->format('G:i') ?>
									</small>
									<?php echo $tweet->post_content ?>
								</a>
							<?php endforeach; ?>
						<?php endif; ?>
					</td>

					<?php if ( $running_day == 6 ): ?>
				</tr>
				<?php if ( ( $day_counter + 1 ) != $days_in_month ): ?>
				<tr class="calendar-row">
					<?php endif; ?>
					<?php $running_day = - 1;
					$days_in_this_week = 0; ?>
					<?php endif; ?>
					<?php $days_in_this_week ++;
					$running_day ++;
					$day_counter ++; ?>
					<?php endfor; ?>

					<?php if ( $days_in_this_week < 8 ):
						for ( $x = 1; $x <= ( 8 - $days_in_this_week ); $x ++ ): ?>
							<td class="calendar-day-np">&nbsp;</td>
						<?php endfor; endif; ?>


				</tr>

				</tbody>
			</table>

			<script>
				jQuery(function($) {
					$('table.calendar').on(
						'click',
						'td',
						function (e) {
							// Let links go through.
							if ( e.target.tagName == 'A' ) {
								return true;
							}

							var date = $(e.target).attr('data-date');

							// Otherwise, let's creat a new one.
							window.location = '<?php echo admin_url('/post-new.php?post_type=' . self::$post_type . '&date=') ?>' + date;

							return false;
						}
					)
				});
			</script>

		</div><!--wrap-->
		<?php
	}

	static function get_post_meta( $post_id = null ) {

		if ( is_null( $post_id ) ) {
			global $post;
		} else {
			$post = get_post( $post_id );
		}

		$return = array();

		$return['is_tweeted'] = get_post_meta( $post->ID, 'is_tweeted', true );
		$return['tweeted_dt'] = get_post_meta( $post->ID, 'tweeted_dt', true );

		$return['is_tweet_failed']     = get_post_meta( $post->ID, 'is_tweet_failed', true );
		$return['tweet_failed_reason'] = get_post_meta( $post->ID, 'tweet_failed_reason', true );

		$return['tweet_data'] = get_post_meta( $post->ID, 'tweet_data', true );

		return $return;
	}

	static function render_admin_menu_settings() {

		$keys = get_option( 'scheduled_tweets_settings' );

		?>
		<div class="wrap">
			<h2>Settings</h2>

			<?php if ( isset( $_GET['message'] ) ) : ?>
				<div class="notice notice-success">
					<p><?php echo $_GET['message'] ?></p>
				</div>
			<?php endif ?>

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

			<hr>

			<ol>
				<li>
					Visit <a href="https://apps.twitter.com/" target="_blank">https://apps.twitter.com/</a>
				</li>
				<li>
					<p>
						Click "create new app".
					</p>
					<p>
						<img src="<?php echo plugin_dir_url( __FILE__ ) ?>/img/create-new-app.png">
					</p>
				</li>
				<li>
					<p>
						Fill out the required fields.
						Recommended: use your domain as a name.
					</p>
					<p>
						<img src="<?php echo plugin_dir_url( __FILE__ ) ?>/img/create-app-form.png">
					</p>
				</li>
				<li>
					<p>
						You should see a confirmation notice that your app is created.
						Copy your consumer key from the data shown into the form above.
					</p>
					<p>
						<img src="<?php echo plugin_dir_url( __FILE__ ) ?>/img/app-created.png">
					</p>
				</li>
				<li>
					<p>
						Click on the "Keys and Access Tokens" tab.
					</p>
					<p>
						<img src="<?php echo plugin_dir_url( __FILE__ ) ?>/img/keys-tab.png">
					</p>
				</li>
				<li>
					<p>
						Copy your consumer key and consumer key into the form above.
					</p>
					<p>
						<img src="<?php echo plugin_dir_url( __FILE__ ) ?>/img/consumer-keys.png">
					</p>
				</li>
				<li>
					<p>
						Scroll down to the "Your Access Token" section.
						Click the "Create my access token" button.
					</p>
					<p>
						<img src="<?php echo plugin_dir_url( __FILE__ ) ?>/img/create-access-token-button.png">
					</p>
				</li>
				<li>
					<p>
						Copy the Access Token and Access Token Secret into the form above.
					</p>
					<p>
						<img src="<?php echo plugin_dir_url( __FILE__ ) ?>/img/access-tokens.png">
					</p>
				</li>
			</ol>
		</div><!--wrap-->
		<?php
	}

	static function save_settings() {
		if ( $_SERVER['REQUEST_METHOD'] != 'POST' ) {
			return false;
		}

		if ( ! isset( $_POST[ self::$post_type ] ) || ! wp_verify_nonce( $_POST[ self::$post_type ], 'save_settings' ) ) {
			return false;
		}

		$keys = array(
			'consumer_key'    => sanitize_text_field( $_POST['consumer_key'] ),
			'consumer_secret' => sanitize_text_field( $_POST['consumer_secret'] ),
			'access_token'    => sanitize_text_field( $_POST['access_token'] ),
			'access_secret'   => sanitize_text_field( $_POST['access_secret'] )
		);

		update_option(
			'scheduled_tweets_settings',
			$keys,
			false
		);

		wp_redirect(
			add_query_arg(
				array(
					'message' => urlencode( 'Settings saved!' )
				)
			)
		);
		exit;
	}

//	static function admin_list_order ( $query ) {
//		if ( !is_admin() || ! $query->is_main_query() || self::$post_type != $query->get( 'post_type' )  ) return;
//
//		$query->set( 'orderby',  'post_date_gmt' );
//	}

	static function manage_posts_columns( $columns ) {
		unset( $columns['date'] );

		$columns['is_tweeted'] = 'Status';
		$columns['tweeted_dt'] = 'When';

		return $columns;
	}

	static function manage_posts_custom_column( $column, $post_id ) {
		global $post;

		$post_meta = self::get_post_meta( $post_id );

		if ( $post_meta['is_tweeted'] == 1 ) {
			$dt = $post_meta['tweeted_dt'];
		} else {
			$dt = $post->post_date_gmt;
		}

		switch ( $column ) {
			case 'is_tweeted' :
				?>
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
				<?php
				break;

			case 'tweeted_dt' :
				?>
				<?php if ( $post_meta['is_tweet_failed'] == 1 ) : ?>
				<?php echo self::ago( $dt ); ?>
			<?php else : // is_tweet_failed ?>

				<?php if ( $post_meta['is_tweeted'] == 1 ) : ?>
					<?php echo self::ago( $dt ); ?>
				<?php elseif ( $post->post_status != 'auto-draft' && $post->post_status != 'draft' ) : // $is_tweeted ?>
					<?php echo self::ago( $dt ); ?>
				<?php endif // $is_tweeted ?>
			<?php endif // $is_tweet_failed
				?>
				<?php
				break;

		}
	}

	static function ago( $dt ) {


		if ( ! ( $dt instanceof Datetime ) ) {

			$dt = new Datetime( $dt );
		}

		$now_dt = new Datetime();

		$str = human_time_diff( $dt->format( 'U' ) );

		if ( $now_dt > $dt ) {
			return sprintf( '%s ago', $str );
		} else {
			return sprintf( 'In %s', $str );
		}

	}

	static function add_meta_boxes() {
		global $post;
		if ( $post->post_type != self::$post_type ) {
			return false;
		}

		add_meta_box(
			'tweet_status',
			'Tweet',
			array( __CLASS__, 'render_meta_box_status' ),
			self::$post_type,
			'side',
			'high'
		);

		add_meta_box(
			'tweet_preview',
			'Preview',
			array( __CLASS__, 'render_meta_box_preview' ),
			self::$post_type,
			'normal',
			'low'
		);
	}

	static function render_meta_box_preview() {
		global $post;

		$content = self::build_tweet_content( $post );

		$strlen = strlen( $content );

		?>

		<blockquote>
		<p>
			<?php echo $content ?>
		</p>
		</blockquote>

		<p style="background: #DDD; padding: 5px;">
			<?php if ( $strlen > 140 ) : ?>
				<b style="background: tomato; color: white; padding: .5em;">
					<?php echo $strlen ?> characters
				</b>
			<?php else : // $strlen ?>
				<?php echo $strlen ?> characters
			<?php endif // $strlen ?>
		</p>

		<?php
	}

	static function render_meta_box_status() {
		global $post;

		$post_meta = self::get_post_meta();

		if ( $post_meta['is_tweeted'] == 1 ) {
			$dt = $post_meta['tweeted_dt'];
		} else {
			$dt = $post->post_date_gmt;
		}

		?>
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
				Reason: <?php echo $post_meta['weet_failed_reason'] ?>
			</p>
		<?php endif // $is_tweeted ?>


		<?php
	}

	static function set_title( $data, $postarr ) {
		// Set slug
		$data['post_name'] = substr(
			sanitize_title( $data['post_content'] ),
			0, 140
		);

		// Shorten content and set title
		$data['post_title'] = substr(
			$data['post_content'],
			0,
			140
		);

		return $data;
	}

	static function remove_tinymce( $settings, $editor_id ) {
		if ( $editor_id === 'content' && get_current_screen()->post_type === self::$post_type ) {
			$settings['tinymce']       = false;
			$settings['quicktags']     = false;
			$settings['media_buttons'] = false;
		}

		return $settings;
	}

	// Register Custom Post Type
	static function register_post_type() {

		$labels = array(
			'name'                  => 'Tweets',
			'singular_name'         => 'Tweet',
			'menu_name'             => 'Tweets',
			'name_admin_bar'        => 'Tweets',
			'archives'              => 'Tweet Archives',
			'attributes'            => 'Tweet Attributes',
			'parent_item_colon'     => 'Parent Tweet:',
			'all_items'             => 'All Tweets',
			'add_new_item'          => 'Add New Tweet',
			'add_new'               => 'Add New',
			'new_item'              => 'New Tweet',
			'edit_item'             => 'Edit Tweet',
			'update_item'           => 'Update Tweet',
			'view_item'             => 'View Tweet',
			'view_items'            => 'View Tweets',
			'search_items'          => 'Search Tweet',
			'not_found'             => 'Not found',
			'not_found_in_trash'    => 'Not found in Trash',
			'featured_image'        => 'Featured Image',
			'set_featured_image'    => 'Set featured image',
			'remove_featured_image' => 'Remove featured image',
			'use_featured_image'    => 'Use as featured image',
			'insert_into_item'      => 'Insert into Tweet',
			'uploaded_to_this_item' => 'Uploaded to this Tweet',
			'items_list'            => 'Tweets list',
			'items_list_navigation' => 'Tweets list navigation',
			'filter_items_list'     => 'Filter Tweets list',
		);
		$args   = array(
			'label'               => 'Tweet',
			'description'         => 'Scheduled Tweets',
			'labels'              => $labels,
			'supports'            => array( 'editor', 'thumbnail' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 5,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'can_export'          => false,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'page',
		);
		register_post_type( self::$post_type, $args );


		$labels = array(
			'name'                       => 'Hashtags',
			'singular_name'              => 'Hashtag',
			'menu_name'                  => 'Hashtags',
			'all_items'                  => 'All Hashtags',
			'parent_item'                => 'Parent Hashtag',
			'parent_item_colon'          => 'Parent Hashtag:',
			'new_item_name'              => 'New Hashtag',
			'add_new_item'               => 'Add New Hashtag',
			'edit_item'                  => 'Edit Hashtag',
			'update_item'                => 'Update Hashtag',
			'view_item'                  => 'View Hashtag',
			'separate_items_with_commas' => 'Separate Hashtags with commas',
			'add_or_remove_items'        => 'Add or remove Hashtags',
			'choose_from_most_used'      => 'Choose from the most used',
			'popular_items'              => 'Popular Hashtags',
			'search_items'               => 'Search Hashtags',
			'not_found'                  => 'Not Found',
			'no_terms'                   => 'No Hashtags',
			'items_list'                 => 'Hashtags list',
			'items_list_navigation'      => 'Hashtags list navigation',
		);
		$args   = array(
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			'rewrite'           => false,
		);
		register_taxonomy( self::$post_type . '_tags', array( self::$post_type ), $args );


	}


}

function Scheduled_Tweets() {
	Scheduled_Tweets::init();
}

Scheduled_Tweets();


