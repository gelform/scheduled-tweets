<?php

/**
Plugin Name:  Scheduled Tweets
Description:  Schedule tweets to tweet to your Twitter account. Add tweets to a calendar. Plan a Twitter campaign. Host your own Buffer app.
Version:      0.0.4
Release Date: April 19, 2018
Plugin Name:  WordPress.org Plugin
Plugin URI:   https://developer.wordpress.org/plugins/scheduled-tweets/
Author:       Gelform
Author URI:   https://gelform.com
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html

 */


class Scheduled_Tweets {

	private static $post_type = 'scheduled_tweets';

	private static $exchange = null;

	static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedule' ) );
		add_action( 'init', array( __CLASS__, 'schedule_cron' ), 0 );
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 0 );

		// Admin.
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'check_settings_show_notice' ) );

		add_action( 'admin_init', array( __CLASS__, 'save_settings' ) );

		// Tweet list screen.
//		add_filter('post_row_actions', array(__CLASS__, 'add_copy_action'),10,2);
		add_filter( 'manage_' . self::$post_type . '_posts_columns', array( __CLASS__, 'manage_posts_columns' ) );
		add_filter( 'manage_' . self::$post_type . '_posts_custom_column', array(__CLASS__, 'manage_posts_custom_column' ), 10, 2 );
		add_action( 'admin_footer', array( __CLASS__, 'admin_footer_list' ) );

		// Tweet edit screen.
		add_filter( 'wp_editor_settings', array( __CLASS__, 'remove_tinymce' ), 10, 2 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
		add_action( 'admin_footer', array( __CLASS__, 'admin_footer_single' ) );

		// After tweet is saved.
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'set_title' ), '99', 2 );
		add_action('save_post', array( __CLASS__, 'save_campaign'));

		// Copy tweet.
		add_action('admin_init', array( __CLASS__, 'copy_post' ) );

		// Send Tweets.
		add_action( self::$post_type . '_check', array( __CLASS__, 'check_for_posts' ) );

		// For testing.
		if ( isset($_GET[self::$post_type]) && $_GET[self::$post_type] == 'check_for_posts' ) {
			add_action( 'admin_init', array( __CLASS__, 'check_for_posts' ) );
		}
	}

//	static function add_copy_action ($actions, $post) {
//		if ( $post->post_type != self::$post_type ) return $actions;
//
//		$actions['clone'] = '<a href="#" title="'
//		                    . esc_attr__("Copy and schedule")
//		                    . '">' .  esc_html__('Copy') . '</a>';
//
//		return $actions;
//	}

	static function copy_post () {
		if ( !isset($_GET[self::$post_type]) || $_GET[self::$post_type] != 'copy' || !isset($_GET['post_id']) ) return false;

		$url_redirect = add_query_arg(
			array(
				'post_type' => self::$post_type
			),
			admin_url('edit.php')
		);

		$_GET['post_id'] = absint($_GET['post_id']);
		$post = get_post($_GET['post_id']);

		if ( empty($post) ) {
			wp_redirect( $url_redirect );
		}

		unset($post->ID);

		if ( isset($_GET['date']) ) {

			if (DateTime::createFromFormat('Y-m-d H:i:s', $_GET['date']) !== FALSE) {
				$post_date_dt = DateTime::createFromFormat('Y-m-d H:i:s', $_GET['date']) ;

				$post->post_date_gmt = $post_date_dt->format('Y-m-d H:i:s');

				$gmt_offset = get_option('gmt_offset');
				if ( !empty($gmt_offset) ) {
					$post_date_dt->modify($gmt_offset . ' hours');
				}

				$post->post_date = $post_date_dt->format('Y-m-d H:i:s');
			}
		}

		// Copy post
		$new_post_id = wp_insert_post( (array) $post );

		// Copy terms;
		$post_terms = wp_get_object_terms(
			$_GET['post_id'],
			self::$post_type . '_tags',
			array('fields' => 'slugs')
		);

		wp_set_object_terms($new_post_id, $post_terms, self::$post_type . '_tags', false);

		wp_redirect($url_redirect);
	}

	static function show_admin_notice_settings() {
		?>
		<div class="notice notice-warning is-dismissible">
			<p><?php echo sprintf(
					_( 'Your settings are incomplete! Tweets will not be sent. Fill out your settings <a href="%s">here</a>.' ),
					add_query_arg(
						array(
							'post_type' => self::$post_type,
							'page' => self::$post_type . '_settings'
						),
						admin_url('edit.php')
					)
				); ?></p>
		</div>
		<?php
	}

	static function check_settings_show_notice () {

		if ( !isset($_GET['post_type']) || $_GET['post_type'] != self::$post_type ) return false;

		$keys = get_option( 'scheduled_tweets_settings' );

		if ( count($keys) != 4 || empty($keys['consumer_key']) || empty($keys['consumer_secret']) || empty($keys['access_token']) || empty($keys['access_secret']) ) {
			add_action( 'admin_notices', array(__CLASS__, 'show_admin_notice_settings') );
		}
	}

	static function admin_enqueue_scripts () {
		global $post;

		if ( $post->post_type != self::$post_type ) return false;

		wp_enqueue_script('jquery-ui-datepicker');

		wp_register_style('jquery-ui', '//ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css');
		wp_enqueue_style('jquery-ui');

		wp_enqueue_script(
			'timepicker',
			plugin_dir_url(__FILE__) . '/js/jquery.timepicker.min.js',
			array('jquery', 'jquery-ui-core', 'jquery-ui-datepicker')
		);

		wp_enqueue_style(
			'timepicker',
			plugin_dir_url(__FILE__) . '/css/jquery.timepicker.min.css'
		);
	}

	static function schedule_cron() {

		if ( ! wp_next_scheduled( self::$post_type . '_check' ) ) {
			wp_schedule_event( time(), '5min', self::$post_type . '_check' );
		}
	}

	static function add_cron_schedule( $schedules ) {
		if ( ! isset( $schedules["5min"] ) ) {
			$schedules["5min"] = array(
				'interval' => 560,
				'display'  => __( 'Once every 5 minutes' )
			);
		}

		return $schedules;
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

		$is_connected = self::connect();

		if ( !$is_connected ) {
			return false;
		}

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

			$content = self::build_tweet_content( $tweet );

			// Don't send if empty.
			if ( empty( $content ) && is_null( $media_id ) ) {
				continue;
			}

			$url    = 'https://api.twitter.com/1.1/statuses/update.json';
			$method = 'POST';
			$params = array(
				'status'             => $content,
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
		require_once( __DIR__ . '/lib/TwitterAPIExchange.php' );

		$keys = get_option( 'scheduled_tweets_settings' );

		try {
			self::$exchange = new TwitterAPIExchange( array(
				'consumer_key'              => $keys['consumer_key'],
				'consumer_secret'           => $keys['consumer_secret'],
				'oauth_access_token'        => $keys['access_token'],
				'oauth_access_token_secret' => $keys['access_secret']
			) );

			return true;
		} catch (Exception $e) {
			return false;
		}
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
//		add_submenu_page(
//			'edit.php?post_type=' . self::$post_type,
//			'Add Many',
//			'Add Many',
//			'manage_options',
//			self::$post_type . '_add_many',
//			array( __CLASS__, 'render_admin_menu_add_many' )
//		);

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

	static function render_admin_menu_add_many() {
		include plugin_dir_path( __FILE__ ) . '/templates/admin/add-many.php';
	}

	static function render_admin_menu_calendar() {

		$today_dt = new DateTime( current_time( 'mysql', 1 ) );
		$today_dt->setTime( 0, 0 );

		$today = $today_dt->format( 'Y-m-d' );

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
			'orderby'     => 'post_date_gmt',
			'date_query'  => array(
				'column' => 'post_date',
				'after'  => $dt_prev->format( 'Y-m-t' ),
				'before' => $dt_next->format( 'Y-m-d' )
			)
		) );

		$tweets_by_date = array();
		foreach ( $tweets as $tweet ) {
			$date = substr( $tweet->post_date_gmt, 0, 10 );

			if ( ! isset( $tweets_by_date[ $date ] ) ) {
				$tweets_by_date[ $date ] = array();
			}

			$tweets_by_date[ $date ][] = $tweet;
		}


		include plugin_dir_path( __FILE__ ) . '/templates/admin/calendar.php';
	}

	static function render_admin_menu_settings() {

		$keys = get_option( 'scheduled_tweets_settings' );

		$plugin_dir_url = plugin_dir_url( __FILE__ );

		include plugin_dir_path( __FILE__ ) . '/templates/admin/settings.php';
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

	static function manage_posts_columns( $columns ) {
		unset( $columns['date'] );

		$columns['is_tweeted'] = 'Status';
		$columns['tweeted_dt'] = 'When';
		$columns['copy'] = 'Copy';

		return $columns;
	}

	static function manage_posts_custom_column( $column, $post_id ) {
		global $post;

		$post_meta = self::get_post_meta( $post_id );

		$post_date = ( $post_meta['is_tweeted'] == 1 ) ? $post_meta['tweeted_dt'] : $post->post_date_gmt;

		$dt = new DateTime($post_date);

		switch ( $column ) {
			case 'is_tweeted' :
				include plugin_dir_path( __FILE__ ) . '/templates/admin/col-is_tweeted.php';
				break;

			case 'tweeted_dt' :
				include plugin_dir_path( __FILE__ ) . '/templates/admin/col-tweeted_dt.php';
				break;

			case 'copy':
				$now_gmt_dt = new DateTime(current_time('mysql', 1));

				if ( $dt < $now_gmt_dt ) {
					$dt = $now_gmt_dt;
				}

				include plugin_dir_path( __FILE__ ) . '/templates/admin/col-copy.php';
				break;
		}
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

	static function admin_footer_list () {

		$screen = get_current_screen();

		if ( $screen->parent_base != 'edit' || $screen->base != 'edit' || ! isset($_GET['post_type']) || $_GET['post_type'] != self::$post_type ) {
			return false;
		}

		include plugin_dir_path( __FILE__ ) . '/templates/admin/footer-list.php';
	}

	static function admin_footer_single() {
//		global $post;
//		(isset($_GET['post_type']) && $_GET['post_type'] != self::$post_type)

		$screen = get_current_screen();

		if ( $screen->parent_base != 'edit' || $screen->base != 'post' || $screen->id != self::$post_type ) {
			return false;
		}

		// Simple validation.
		if ( isset( $_GET['date'] ) ) {
			$_GET['date'] = str_pad( intval( $_GET['date'] ), 2, '0', STR_PAD_LEFT );
		}
		if ( isset( $_GET['month'] ) ) {
			$_GET['month'] = str_pad( intval( $_GET['month'] ), 2, '0', STR_PAD_LEFT );
		}
		if ( isset( $_GET['year'] ) ) {
			$_GET['year'] = intval( $_GET['year'] );
		}

		if ( isset( $_GET['hour'] ) ) {
			$_GET['hour'] = str_pad( intval( $_GET['hour'] ), 2, '0', STR_PAD_LEFT );
		}
		if ( isset( $_GET['minute'] ) ) {
			$_GET['minute'] = str_pad( intval( $_GET['minute'] ), 2, '0', STR_PAD_LEFT );
		}

		?>
		<style>
			#content {
				max-height: 10em;
			}

			#mceu_25,
			.mce-toolbar-grp,
			.mce-flow-layout {
				display: none;
			}

			#<?php echo self::$post_type ?>-preview,
			#<?php echo self::$post_type ?>-preview p {
				font-size: 20px;
			}

			#visibility {
				display: none;
			}
		</style>

		<script>
			jQuery(function ($) {

				<?php if ( isset( $_GET['date'] ) ) : ?>
				$('#jj').val('<?php echo $_GET['date']; ?>');
				<?php endif ?>

				<?php if ( isset( $_GET['month'] ) ) : ?>
				$('#mm').val('<?php echo $_GET['month']; ?>');
				<?php endif ?>

				<?php if ( isset( $_GET['year'] ) ) : ?>
				$('#aa').val('<?php echo $_GET['year']; ?>');
				<?php endif ?>

				<?php if ( isset( $_GET['hour'] ) ) : ?>
				$('#hh').val('<?php echo $_GET['hour']; ?>');
				<?php endif ?>

				<?php if ( isset( $_GET['minute'] ) ) : ?>
				$('#mn').val('<?php echo $_GET['minute']; ?>');
				<?php endif ?>

				setTimeout(function () {
					$('#timestampdiv .save-timestamp').trigger('click');
				}, 1000);

				var default_date = $('#aa').val() + '-' + $('#mm').val() + '-' + $('#jj').val();

				$('<div id="<?php echo self::$post_type ?>-datepicker"></div>')
				.insertBefore('.misc-pub-curtime')
				.datepicker({
					firstDay: 0,
					minDate: new Date(),
					dayNames: [ "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday" ],
					dayNamesMin: [ "Su", "Mo", "Tu", "We", "Th", "Fr", "Sa" ],
					dateFormat: "yy-mm-dd",
					defaultDate: default_date,
					onSelect: function (dateText) {
						var date_arr = dateText.split('-');
						var year = date_arr[0];
						var month = date_arr[1];
						var date = date_arr[2];

						$('#aa').val(year);
						$('#mm').val(month);
						$('#jj').val(date);
						$('#timestampdiv .save-timestamp').trigger('click');
					}
				});

				$('#aa, #mm, #jj').on('change', function() {
					$( "#<?php echo self::$post_type ?>-datepicker" ).datepicker( "setDate", $('#aa').val() + '-' + $('#mm').val() + '-' + $('#jj').val() );
				});

				$('<p style="text-align: center;">At: <input type="text" id="<?php echo self::$post_type ?>-timepicker"></p>')
				.insertAfter('#<?php echo self::$post_type ?>-datepicker');

				var hour = $('#hh').val();
				var min = $('#mn').val();

				$('#<?php echo self::$post_type ?>-timepicker').timepicker({
					timeFormat: 'H:i'
				})
				.val(hour + ':' + min)
				.on('changeTime', function () {
					var time = $(this).val();
					var time_arr = time.split(':');

					$('#hh').val(time_arr[0]);
					$('#mn').val(time_arr[1]);
					$('#timestampdiv .save-timestamp').trigger('click');
				});

				$('#hh, #mn').on('change', function() {
					var hour = $('#hh').val();
					var min = $('#mn').val();

					$('#<?php echo self::$post_type ?>-timepicker').val(hour + ':' + min).trigger('changeTime');
				});

				wpCharCount = function () {

					var html = tinymce.activeEditor.getContent();

					var tags = [];
					$('#<?php echo self::$post_type ?>_tagschecklist [type="checkbox"]:checked').each(function () {
						var tag = $(this).closest('label').text();

						if ( '' != tag ) {

							tags.push('#' + $.trim(tag));
						}
					});

					html += ' ' + tags.join(' ');

					$('#<?php echo self::$post_type ?>-preview').html(html);

					var txt = $('<div />').html(html).text();

					var len = '' + txt.length;

					var count = len;
					if (len > 280) {
						var count = '<b style="background: tomato; color: white; padding: .5em;">' + len + '</b>';
					}
					$('.char-count').html(count);

					$('#<?php echo self::$post_type ?>-preview-count').html(count);

				};

				$(document).ready(function () {
					$('#wp-word-count').append('<br />Char count: <span class="char-count">0</span>');
				})
				.bind('wpcountwords', function (e, txt) {
					wpCharCount(txt);
				});

				$('#content').bind('keyup', function() {
					wpCharCount();
				});

				setTimeout(function () {
					$('#content').trigger('keyup');

					setInterval(function () {
						$('#content').trigger('keyup');
					}, 1000);
				}, 500);

			});
		</script>
		<?php
	}

	static function add_meta_boxes() {
		global $post;
		if ( $post->post_type != self::$post_type ) {
			return false;
		}

		remove_meta_box('tagsdiv-' . self::$post_type . '_campaigns',self::$post_type,'side');

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

		add_meta_box(
			self::$post_type . '_campaigns',
			'Campaign',
			array( __CLASS__, 'render_meta_box_campaign' ),
			self::$post_type,
			'side',
			'low'
		);
	}

	static function render_meta_box_campaign ($post) {
		$campaigns = get_terms(self::$post_type . '_campaigns', 'hide_empty=0');

		$names = wp_get_object_terms($post->ID, self::$post_type . '_campaigns');
pre
		?>
		<p>
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
		</p>

		<p>
			<input type="text" id="<?php self::$post_type ?>-campaign-new">
			<button type="button"
			        id="<?php self::$post_type ?>-campaign-add"
			class="button">
				Add a new campaign
			</button>
		</p>

		<script>
			jQuery(function ($) {
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

						return false;
					}
				);
			});
		</script>
		<?php

	}

	static function render_meta_box_preview() {
		global $post;

		$content = self::build_tweet_content( $post );

		$strlen = strlen( $content );

		include plugin_dir_path( __FILE__ ) . '/templates/admin/meta_box-preview.php';
	}

	static function render_meta_box_status() {
		global $post;

		$post_meta = self::get_post_meta();

		if ( $post_meta['is_tweeted'] == 1 ) {
			$dt = $post_meta['tweeted_dt'];
		} else {
			$dt = $post->post_date_gmt;
		}

		include plugin_dir_path( __FILE__ ) . '/templates/admin/meta_box-status.php';
	}

	static function set_title( $data, $postarr ) {
		if ( !isset($data['post_type']) || $data['post_type'] != self::$post_type ) {
			return $data;
		}


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

	static function save_campaign ($post_id) {

		// verify if this is an auto save routine. If it is our form has not been submitted, so we dont want to do anything
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
			return false;
		}

		if ( $_POST['post_type'] != self::$post_type || !current_user_can( 'edit_page', $post_id ) )
		{
			return false;
		}

		$campaign = sanitize_text_field($_POST[self::$post_type . '_campaigns']);

		wp_set_object_terms( $post_id, $campaign, self::$post_type . '_campaigns' );
	}

	static function remove_tinymce( $settings, $editor_id ) {
		if ( $editor_id === 'content' && get_current_screen()->post_type === self::$post_type ) {
//			$settings['tinymce']       = false;
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
			'add_new'               => 'Add a Tweet',
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


		$labels = array(
			'name'                       => _x( 'Campaign', 'Campaign General Name', 'text_domain' ),
			'singular_name'              => _x( 'Campaign', 'Campaign Singular Name', 'text_domain' ),
			'menu_name'                  => __( 'Campaigns', 'text_domain' ),
			'all_items'                  => __( 'All Items', 'text_domain' ),
			'parent_item'                => __( 'Parent Item', 'text_domain' ),
			'parent_item_colon'          => __( 'Parent Item:', 'text_domain' ),
			'new_item_name'              => __( 'New Item Name', 'text_domain' ),
			'add_new_item'               => __( 'Add New Item', 'text_domain' ),
			'edit_item'                  => __( 'Edit Item', 'text_domain' ),
			'update_item'                => __( 'Update Item', 'text_domain' ),
			'view_item'                  => __( 'View Item', 'text_domain' ),
			'separate_items_with_commas' => __( 'Separate items with commas', 'text_domain' ),
			'add_or_remove_items'        => __( 'Add or remove items', 'text_domain' ),
			'choose_from_most_used'      => __( 'Choose from the most used', 'text_domain' ),
			'popular_items'              => __( 'Popular Items', 'text_domain' ),
			'search_items'               => __( 'Search Items', 'text_domain' ),
			'not_found'                  => __( 'Not Found', 'text_domain' ),
			'no_terms'                   => __( 'No items', 'text_domain' ),
			'items_list'                 => __( 'Items list', 'text_domain' ),
			'items_list_navigation'      => __( 'Items list navigation', 'text_domain' ),
		);
		$args = array(
			'labels'                     => $labels,
			'hierarchical'               => false,
			'public'                     => true,
			'show_ui'                    => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => true,
			'show_tagcloud'              => true,
		);
		register_taxonomy( self::$post_type . '_campaigns', array( self::$post_type ), $args );
	}


}

function Scheduled_Tweets() {
	Scheduled_Tweets::init();
}

Scheduled_Tweets();


