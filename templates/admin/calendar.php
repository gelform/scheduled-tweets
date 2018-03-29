<div class="wrap">
	<h2>Calendar</h2>

	<style>
		table.calendar {
			border-spacing: 4px;
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
			cursor: pointer;
			max-width: 0;
			height: 6em;
			width: <?php echo 100/7 ?>%;
			padding: 3px;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
			vertical-align: top;
		}

		table.calendar .past {
			opacity: .95;
			cursor: default;
		}

		table.calendar .past .day-number {
			opacity: .618;
		}


		table.calendar .today {
			background: #ffFb4b;
		}

		table.calendar .calendar-day-np {
			background: #DDD;
		}

		.tweet {
			background: #777;
			color: white;
			display: block;
			margin: 0 -3px 1px;
			padding-left: 3px;
			opacity: .618;
			overflow: hidden;
			width: 100%;
			text-overflow: ellipsis;
			text-decoration: none;
		}

		.tweet:hover,
		.tweet:focus,
		.tweet:active {
			color: inherit;
			opacity: 1;
		}

		.tweet.failed {
			background: tomato;
			color: white;
		}

		.tweet.tweeted {
			background: limegreen;
			color: white;
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
				<h3>
					<?php if ( $dt->format( 'Y-m' ) != $today_dt->format( 'Y-m' ) ) : ?>
						<a href="<?php echo add_query_arg( array(
							'month' => $today_dt->format( 'm' ),
							'year'  => $today_dt->format( 'Y' )
						) ) ?>">
							<?php echo $dt->format( 'M, Y' ) ?>
						</a>
					<?php else : ?>
						<?php echo $dt->format( 'M, Y' ) ?>
					<?php endif ?>
				</h3>
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
				<?php $list_date = sprintf(
					'%s-%s-01 - %d days',
					$year,
					$month,
					( $running_day - $x )
				);

				$list_dt = new DateTime( $list_date );

				$diff = $today_dt->diff( $list_dt );

				?>
				<td class="calendar-day-np <?php echo $diff->format( '%R%a' ) == 0 ? 'today' : '' ?> <?php echo $diff->format( '%R%a' ) < 0 ? 'past' : '' ?>"
				    data-url="<?php echo esc_attr( add_query_arg(
					    array(
						    'post_type' => self::$post_type,
						    'date'      => $list_dt->format( 'd' ),
						    'month'     => $list_dt->format( 'm' ),
						    'year'      => $list_dt->format( 'Y' ),
					    ),
					    admin_url( 'post-new.php' )
				    ) ); ?>">

					<?php echo $list_dt->format( 'j' ) ?>
				</td>
				<?php $days_in_this_week ++; ?>
			<?php endfor; ?>

			<?php for ( $list_day = 1;
			$list_day <= $days_in_month;
			$list_day ++ ) : ?>
			<?php $list_date = sprintf(
				'%s-%s-%s',
				$year,
				str_pad( $month, 2, '0', STR_PAD_LEFT ),
				str_pad( $list_day, 2, '0', STR_PAD_LEFT )
			);

			$list_dt = new DateTime( $list_date );

			$diff = $today_dt->diff( $list_dt );

			?>
			<td class="calendar-day <?php echo $diff->format( '%R%a' ) == 0 ? 'today' : '' ?> <?php echo $diff->format( '%R%a' ) < 0 ? 'past' : '' ?>"
			    data-url="<?php echo esc_attr( add_query_arg(
				    array(
					    'post_type' => self::$post_type,
					    'date'      => str_pad( $list_day, 2, '0', STR_PAD_LEFT ),
					    'month'     => str_pad( $month, 2, '0', STR_PAD_LEFT ),
					    'year'      => $year
				    ),
				    admin_url( 'post-new.php' )
			    ) ); ?>">

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

			<?php if ( $days_in_this_week < 8 && $days_in_this_week > 1 ):
				for ( $x = 1; $x <= ( 8 - $days_in_this_week ); $x ++ ): ?>
					<?php $list_date = sprintf(
						'%s-%s-01 + %d days',
						$dt_next->format( 'Y' ),
						$dt_next->format( 'm' ),
						$x-1
					);

					$list_dt = new DateTime( $list_date );

					$diff = $today_dt->diff( $list_dt );

					?>
					<td class="calendar-day-np <?php echo $diff->format( '%R%a' ) == 0 ? 'today' : '' ?> <?php echo $diff->format( '%R%a' ) < 0 ? 'past' : '' ?>"
					    data-url="<?php echo esc_attr( add_query_arg(
						    array(
							    'post_type' => self::$post_type,
							    'date'      => $list_dt->format( 'd' ),
							    'month'     => $list_dt->format( 'm' ),
							    'year'      => $list_dt->format( 'Y' ),
						    ),
						    admin_url( 'post-new.php' )
					    ) ); ?>">

						<?php echo $list_dt->format( 'j' ) ?>
					</td>
				<?php endfor; endif; ?>
		</tr>

		</tbody>

	</table>

	<p style="text-align: center;">
		<small>
			Click a day to add a new tweet.
		</small>
	</p>

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

					var $td = $(e.target);

					if ($td.hasClass('past')) return false;

					var url = $td.attr('data-url');

					// Otherwise, let's create a new one.
					window.location = url;

					return false;
				}
			)
		});
	</script>

</div><!--wrap-->