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

				<?php if ( isset( $tweets_by_date[ $list_date ] ) ) : $tweets_by_date[ $list_date ] = array_reverse( $tweets_by_date[ $list_date ] ); ?>
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
								<?php echo $tweet_dt->format( 'G:i' ) ?>
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
		jQuery(function ($) {
			$('table.calendar').on(
				'click',
				'td',
				function (e) {
					// Let links go through.
					if (e.target.tagName == 'A') {
						return true;
					}

					var date = $(e.target).attr('data-date');

					// Otherwise, let's creat a new one.
					window.location = '<?php echo admin_url( '/post-new.php?post_type=' . self::$post_type . '&date=' ) ?>' + date;

					return false;
				}
			)
		});
	</script>

</div><!--wrap-->