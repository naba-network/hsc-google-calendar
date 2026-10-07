<?php
/**
 * Bookable events grouped by month, plus the (hidden) booking form template.
 *
 * @package HscGoogleCalendar
 *
 * @var array<string, mixed> $view Template variables.
 */

declare(strict_types=1);

use Hsc\GoogleCalendar\Booking_Request;

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
<div class="hsc-booking" data-hsc-booking data-config="<?php echo esc_attr( (string) wp_json_encode( $view['config'] ) ); ?>">
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

	<template data-hsc-form>
		<form class="hsc-sheet" novalidate>
			<header class="hsc-sheet__head">
				<div>
					<h3 class="hsc-sheet__title" tabindex="-1" data-hsc-title></h3>
					<p data-hsc-time></p>
				</div>
				<button type="button" class="hsc-x" data-hsc-close aria-label="<?php esc_attr_e( 'Schließen', 'hsc-google-calendar' ); ?>">
					<svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 4l12 12M16 4L4 16"/></svg>
				</button>
			</header>
			<div class="hsc-sheet__body">
				<input type="hidden" name="<?php echo esc_attr( Booking_Request::FIELD_EVENT ); ?>">
				<input type="hidden" name="<?php echo esc_attr( Booking_Request::FIELD_TOKEN ); ?>">

				<div class="hsc-field" data-field="<?php echo esc_attr( Booking_Request::FIELD_NAME ); ?>">
					<label for="hsc-name"><?php esc_html_e( 'Name', 'hsc-google-calendar' ); ?></label>
					<input id="hsc-name" name="<?php echo esc_attr( Booking_Request::FIELD_NAME ); ?>" autocomplete="name" maxlength="<?php echo esc_attr( (string) Booking_Request::MAX_NAME ); ?>">
					<p class="hsc-error" data-error aria-live="polite" hidden></p>
				</div>
				<div class="hsc-field" data-field="<?php echo esc_attr( Booking_Request::FIELD_EMAIL ); ?>">
					<label for="hsc-email"><?php esc_html_e( 'E-Mail', 'hsc-google-calendar' ); ?></label>
					<p class="hsc-hint"><?php esc_html_e( 'Hierhin senden wir die Bestätigung.', 'hsc-google-calendar' ); ?></p>
					<input id="hsc-email" name="<?php echo esc_attr( Booking_Request::FIELD_EMAIL ); ?>" type="email" autocomplete="email" inputmode="email">
					<p class="hsc-error" data-error aria-live="polite" hidden></p>
				</div>
				<div class="hsc-field" data-field="<?php echo esc_attr( Booking_Request::FIELD_PHONE ); ?>">
					<label for="hsc-phone"><?php esc_html_e( 'Telefon', 'hsc-google-calendar' ); ?></label>
					<input id="hsc-phone" name="<?php echo esc_attr( Booking_Request::FIELD_PHONE ); ?>" type="tel" autocomplete="tel" inputmode="tel">
					<p class="hsc-error" data-error aria-live="polite" hidden></p>
				</div>
				<div class="hsc-two">
					<div class="hsc-field" data-field="<?php echo esc_attr( Booking_Request::FIELD_PARTICIPANTS ); ?>">
						<label for="hsc-participants"><?php esc_html_e( 'Teilnehmer', 'hsc-google-calendar' ); ?></label>
						<div class="hsc-step">
							<button type="button" data-step="-1" tabindex="-1" aria-label="<?php esc_attr_e( 'Weniger', 'hsc-google-calendar' ); ?>">−</button>
							<input id="hsc-participants" name="<?php echo esc_attr( Booking_Request::FIELD_PARTICIPANTS ); ?>" type="number" inputmode="numeric" min="1" max="<?php echo esc_attr( (string) Booking_Request::MAX_PARTICIPANTS ); ?>" value="1">
							<button type="button" data-step="1" tabindex="-1" aria-label="<?php esc_attr_e( 'Mehr', 'hsc-google-calendar' ); ?>">+</button>
						</div>
						<p class="hsc-error" data-error aria-live="polite" hidden></p>
					</div>
					<div class="hsc-field" data-field="<?php echo esc_attr( Booking_Request::FIELD_RENTAL ); ?>">
						<label for="hsc-rental"><?php esc_html_e( 'Leihausrüstung', 'hsc-google-calendar' ); ?></label>
						<div class="hsc-step">
							<button type="button" data-step="-1" tabindex="-1" aria-label="<?php esc_attr_e( 'Weniger', 'hsc-google-calendar' ); ?>">−</button>
							<input id="hsc-rental" name="<?php echo esc_attr( Booking_Request::FIELD_RENTAL ); ?>" type="number" inputmode="numeric" min="0" max="<?php echo esc_attr( (string) Booking_Request::MAX_PARTICIPANTS ); ?>" value="0">
							<button type="button" data-step="1" tabindex="-1" aria-label="<?php esc_attr_e( 'Mehr', 'hsc-google-calendar' ); ?>">+</button>
						</div>
						<p class="hsc-error" data-error aria-live="polite" hidden></p>
					</div>
				</div>
				<div class="hsc-field" data-field="<?php echo esc_attr( Booking_Request::FIELD_MESSAGE ); ?>">
					<label for="hsc-message"><?php esc_html_e( 'Fragen oder Anmerkungen', 'hsc-google-calendar' ); ?> <span class="hsc-optional"><?php esc_html_e( '(optional)', 'hsc-google-calendar' ); ?></span></label>
					<textarea id="hsc-message" name="<?php echo esc_attr( Booking_Request::FIELD_MESSAGE ); ?>" rows="3" maxlength="<?php echo esc_attr( (string) Booking_Request::MAX_MESSAGE ); ?>"></textarea>
				</div>
				<div class="hsc-field hsc-field--captcha" data-field="<?php echo esc_attr( Booking_Request::FIELD_CAPTCHA ); ?>">
					<label for="hsc-captcha"><?php esc_html_e( 'Sicherheitsfrage: Was ergibt', 'hsc-google-calendar' ); ?> <span data-hsc-question>…</span>?</label>
					<input id="hsc-captcha" name="<?php echo esc_attr( Booking_Request::FIELD_CAPTCHA ); ?>" inputmode="numeric" autocomplete="off">
					<p class="hsc-error" data-error aria-live="polite" hidden></p>
				</div>
				<div class="hsc-hp" aria-hidden="true">
					<label><?php esc_html_e( 'Bitte leer lassen', 'hsc-google-calendar' ); ?>
						<input name="<?php echo esc_attr( Booking_Request::FIELD_HONEYPOT ); ?>" tabindex="-1" autocomplete="off">
					</label>
				</div>
				<p class="hsc-error hsc-error--general" data-error-general role="alert" hidden></p>
			</div>
			<div class="hsc-sheet__foot">
				<button type="submit" class="hsc-btn hsc-btn--block"><?php esc_html_e( 'Buchung absenden', 'hsc-google-calendar' ); ?></button>
			</div>
		</form>
	</template>

	<template data-hsc-done>
		<div class="hsc-sheet" role="status">
			<header class="hsc-sheet__head">
				<div>
					<h3 class="hsc-sheet__title" tabindex="-1" data-hsc-title></h3>
					<p data-hsc-time></p>
				</div>
				<button type="button" class="hsc-x" data-hsc-close aria-label="<?php esc_attr_e( 'Schließen', 'hsc-google-calendar' ); ?>">
					<svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 4l12 12M16 4L4 16"/></svg>
				</button>
			</header>
			<div class="hsc-sheet__body hsc-done">
				<div class="hsc-tick"><svg width="30" height="30" viewBox="0 0 30 30" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 16l6 6 11-13"/></svg></div>
				<h4><?php esc_html_e( 'Anfrage gesendet', 'hsc-google-calendar' ); ?></h4>
				<p><?php esc_html_e( 'Danke! Wir melden uns per E-Mail bei dir.', 'hsc-google-calendar' ); ?></p>
			</div>
			<div class="hsc-sheet__foot">
				<button type="button" class="hsc-btn hsc-btn--block" data-hsc-close><?php esc_html_e( 'Schließen', 'hsc-google-calendar' ); ?></button>
			</div>
		</div>
	</template>
</div>
