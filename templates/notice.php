<?php
/**
 * Message shown instead of a list.
 *
 * @package HscGoogleCalendar
 *
 * @var array<string, mixed> $view Template variables.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p class="hsc-booking__notice"><?php echo esc_html( (string) $view['message'] ); ?></p>
