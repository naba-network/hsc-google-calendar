<?php
/**
 * HTML frame shared by all e-mails.
 *
 * @package HscGoogleCalendar
 */

declare(strict_types=1);

namespace Hsc\GoogleCalendar;

/**
 * Grey page, white card with the club name on top and a link to the website below.
 * Table based with inline styles only, because mail clients ignore most modern CSS.
 */
final class Mail_Layout {

	public const BRAND    = 'SC Samina Hohenems - Verleih';
	public const SITE_URL = 'https://sc-hohenems.at';

	private const FONT  = "-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif";
	private const TEXT  = '#181818';
	private const MUTED = '#6B7280';

	/**
	 * Escapes text for HTML output. Pure PHP, WordPress is not loaded in unit tests.
	 *
	 * @param string $text Raw text.
	 */
	public static function escape( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	/**
	 * Paragraph of already escaped HTML.
	 *
	 * @param string $html Escaped HTML.
	 */
	public static function paragraph( string $html ): string {
		return '<p style="margin:0 0 16px;font-size:16px;line-height:24px;color:' . self::TEXT . ';">' . $html . '</p>';
	}

	/**
	 * Wraps already escaped content into the full HTML document.
	 *
	 * @param string $title   Document title (plain text).
	 * @param string $content Escaped HTML for the card body.
	 */
	public static function wrap( string $title, string $content ): string {
		$font   = 'font-family:' . self::FONT . ';font-size:16px;line-height:24px;color:' . self::TEXT . ';';
		$brand  = self::escape( self::BRAND );
		$link   = self::escape( self::SITE_URL );
		$host   = self::escape( (string) preg_replace( '~^https?://~', '', self::SITE_URL ) );
		$footer = 'font-size:13px;line-height:20px;color:' . self::MUTED . ';';

		return '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<title>' . self::escape( $title ) . '</title></head>'
			. '<body style="margin:0;padding:0;background-color:#F4F4F4;' . $font . '">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#F4F4F4" style="border-collapse:collapse;width:100%;background-color:#F4F4F4;"><tr>'
			. '<td align="center" style="' . $font . 'padding:48px 16px;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#FFFFFF" style="border-collapse:separate;width:100%;max-width:640px;background-color:#FFFFFF;border-radius:12px;border:1px solid #E5E7EB;">'
			. '<tr><td style="' . $font . 'padding:24px 24px 0;"><h2 style="margin:0;font-size:20px;line-height:24px;font-weight:700;color:' . self::TEXT . ';">' . $brand . '</h2></td></tr>'
			. '<tr><td style="' . $font . 'padding:24px;text-align:left;">' . $content . '</td></tr>'
			. '</table>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:640px;"><tr>'
			. '<td style="' . $font . 'padding:24px;' . $footer . '"><a href="' . $link . '" style="color:#000000;text-decoration:underline;">' . $host . '</a></td>'
			. '</tr></table>'
			. '</td></tr></table></body></html>';
	}
}
