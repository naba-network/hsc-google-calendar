<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/bin/Bumper.php';
require_once dirname(__DIR__) . '/includes/class-credentials.php';
require_once dirname(__DIR__) . '/includes/class-connection-result.php';
require_once dirname(__DIR__) . '/includes/class-connection-tester.php';
require_once dirname(__DIR__) . '/includes/class-event.php';
require_once dirname(__DIR__) . '/includes/class-event-filter.php';
require_once dirname(__DIR__) . '/includes/class-calendar-client.php';
require_once dirname(__DIR__) . '/includes/class-booking.php';
require_once dirname(__DIR__) . '/includes/class-booking-summary.php';
require_once dirname(__DIR__) . '/includes/class-booking-request.php';
require_once dirname(__DIR__) . '/includes/class-date-range.php';
require_once dirname(__DIR__) . '/includes/class-captcha.php';
require_once dirname(__DIR__) . '/includes/class-mail-message.php';
require_once dirname(__DIR__) . '/includes/class-mail-layout.php';
require_once dirname(__DIR__) . '/includes/class-booking-mailer.php';

// Pure classes never load WordPress; translation is the identity in tests.
if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}
