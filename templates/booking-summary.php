<?php
/**
 * Booked events: totals per month and one card per booking.
 *
 * @package HscGoogleCalendar
 *
 * @var array<string, mixed> $view Template variables.
 */

declare(strict_types=1);

use Hsc\GoogleCalendar\Booking;
use Hsc\GoogleCalendar\Booking_Summary;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bookings of the booked events.
 *
 * @var Booking_Summary $summary
 */
$summary = $view['summary'];
/**
 * Turns a month key into a label.
 *
 * @var callable $labeller
 */
$labeller = $view['labeller'];
$months   = $summary->by_month();
$rows     = $summary->rows();
$total    = $summary->totals();
$now      = (int) ( $view['now'] ?? time() );
$next     = $summary->next( $now );

/**
 * Prints one booking card. The highlighted one is the scroll target of the "next" button.
 *
 * @param Booking $booking   Booking.
 * @param bool    $highlight Whether this is the next booking.
 */
$render_card = static function ( Booking $booking, bool $highlight ): void {
	?>
		<article class="hsc-card<?php echo $highlight ? ' hsc-card--next' : ''; ?>"<?php echo $highlight ? ' id="hsc-next"' : ''; ?>>
			<h4><?php echo esc_html( $booking->event->when_label() ); ?></h4>
			<dl>
				<div><dt><?php esc_html_e( 'Name', 'hsc-google-calendar' ); ?></dt><dd><?php echo esc_html( $booking->name ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Teilnehmer', 'hsc-google-calendar' ); ?></dt><dd><?php echo esc_html( (string) $booking->participants ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Leihausrüstung', 'hsc-google-calendar' ); ?></dt><dd><?php echo esc_html( (string) $booking->rental ); ?></dd></div>
				<?php if ( '' !== $booking->email ) : ?>
					<div><dt><?php esc_html_e( 'E-Mail', 'hsc-google-calendar' ); ?></dt><dd><?php echo esc_html( $booking->email ); ?></dd></div>
				<?php endif; ?>
				<?php if ( '' !== $booking->phone ) : ?>
					<div><dt><?php esc_html_e( 'Telefon', 'hsc-google-calendar' ); ?></dt><dd><?php echo esc_html( $booking->phone ); ?></dd></div>
				<?php endif; ?>
				<?php if ( '' !== $booking->note ) : ?>
					<div class="hsc-card__full"><dt><?php esc_html_e( 'Anmerkung', 'hsc-google-calendar' ); ?></dt><dd><?php echo esc_html( $booking->note ); ?></dd></div>
				<?php endif; ?>
			</dl>
		</article>
	<?php
};
?>
<div class="hsc-booking hsc-booked">
	<h2 class="hsc-booked__title"><?php esc_html_e( 'Gebuchte Termine', 'hsc-google-calendar' ); ?></h2>
	<?php if ( $summary->is_empty() ) : ?>
		<p class="hsc-booking__notice"><?php esc_html_e( 'Aktuell gibt es keine gebuchten Termine.', 'hsc-google-calendar' ); ?></p>
	<?php else : ?>
		<table class="hsc-sum">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Monat', 'hsc-google-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Slots', 'hsc-google-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Gebucht', 'hsc-google-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Teil­nehmer', 'hsc-google-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Leih­ausrüstung', 'hsc-google-calendar' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $key => $sums ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $labeller( (string) $key ) ); ?></th>
						<td><?php echo esc_html( (string) $sums['slots'] ); ?></td>
						<td><?php echo esc_html( (string) $sums['bookings'] ); ?></td>
						<td><?php echo esc_html( (string) $sums['participants'] ); ?></td>
						<td><?php echo esc_html( (string) $sums['rental'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th scope="row"><?php esc_html_e( 'Gesamt', 'hsc-google-calendar' ); ?></th>
					<td><?php echo esc_html( (string) $total['slots'] ); ?></td>
					<td><?php echo esc_html( (string) $total['bookings'] ); ?></td>
					<td><?php echo esc_html( (string) $total['participants'] ); ?></td>
					<td><?php echo esc_html( (string) $total['rental'] ); ?></td>
				</tr>
			</tfoot>
		</table>

		<?php if ( null !== $next ) : ?>
			<aside class="hsc-next" aria-label="<?php esc_attr_e( 'Nächster Termin', 'hsc-google-calendar' ); ?>">
				<div>
					<p class="hsc-next__label"><?php echo esc_html( 0 === $next->event->days_from( $now ) ? __( 'Heute', 'hsc-google-calendar' ) : __( 'Nächster Termin', 'hsc-google-calendar' ) ); ?></p>
					<p class="hsc-next__when"><?php echo esc_html( $next->event->when_label() ); ?> &middot; <?php echo esc_html( $next->name ); ?></p>
				</div>
				<a class="hsc-btn" href="#hsc-next"><?php echo esc_html( 0 === $next->event->days_from( $now ) ? __( 'Zum heutigen Termin', 'hsc-google-calendar' ) : __( 'Zum nächsten Termin', 'hsc-google-calendar' ) ); ?></a>
			</aside>
		<?php endif; ?>

		<div class="hsc-months">
		<?php foreach ( $months as $key => $bookings ) : ?>
			<section class="hsc-month">
				<h3 class="hsc-month__head">
					<span><?php echo esc_html( $labeller( (string) $key ) ); ?></span>
					<small>
						<?php
						/* translators: %d: number of booked appointments in the month */
						echo esc_html( sprintf( _n( '%d Termin', '%d Termine', count( $bookings ), 'hsc-google-calendar' ), count( $bookings ) ) );
						?>
					</small>
				</h3>
				<div class="hsc-cards">
					<?php foreach ( $bookings as $booking ) : ?>
						<?php $render_card( $booking, $booking === $next ); ?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
