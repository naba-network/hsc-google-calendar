<?php
/**
 * Bookable events grouped by month.
 *
 * @package HscGoogleCalendar
 *
 * @var array<string, mixed> $view Template variables.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Events per month key.
 *
 * @var array<string, \Hsc\GoogleCalendar\Event[]> $months
 */
$months = $view['months'];
/**
 * Turns a month key into a label.
 *
 * @var callable $labeller
 */
$labeller = $view['labeller'];
?>
<?php if ( array() === $months ) : ?>
	<p class="hsc-booking__notice"><?php esc_html_e( 'Aktuell gibt es keine freien Termine.', 'hsc-google-calendar' ); ?></p>
<?php endif; ?>
<?php foreach ( $months as $key => $events ) : ?>
	<section class="hsc-month">
		<h3 class="hsc-month__head">
			<span><?php echo esc_html( $labeller( (string) $key ) ); ?></span>
			<small>
				<?php
				/* translators: %d: number of free appointments in the month */
				echo esc_html( sprintf( _n( '%d Termin', '%d Termine', count( $events ), 'hsc-google-calendar' ), count( $events ) ) );
				?>
			</small>
		</h3>
		<ul class="hsc-list">
			<?php foreach ( $events as $event ) : ?>
				<li class="hsc-row">
					<div class="hsc-row__info">
						<div class="hsc-row__date"><?php echo esc_html( $event->day_label() ); ?></div>
						<div class="hsc-row__time"><?php echo esc_html( $event->time_label() ); ?></div>
					</div>
					<button type="button" class="hsc-btn" data-hsc-book
						data-event="<?php echo esc_attr( $event->id ); ?>"
						data-date="<?php echo esc_attr( $event->date_label() ); ?>"
						data-time="<?php echo esc_attr( $event->time_label() ); ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %s: date and time of the appointment */ __( 'Jetzt buchen: %s', 'hsc-google-calendar' ), $event->date_label() . ', ' . $event->time_label() ) ); ?>">
						<?php esc_html_e( 'Jetzt buchen', 'hsc-google-calendar' ); ?>
					</button>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endforeach; ?>
