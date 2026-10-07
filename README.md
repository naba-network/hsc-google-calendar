# HSC Google Calendar

WordPress plugin for the club website: free ice time slots from Google Calendar become bookable.

## Development

```bash
composer install
composer lint   # php -l + PHPCS (WordPress standards)
composer test   # PHPUnit
bin/build-zip.sh  # -> dist/hsc-google-calendar.zip
```

## Shortcodes

| Shortcode | Shows |
|---|---|
| `[HSC-Event-Booking-List]` | Upcoming events whose title matches **Free events (regex)**, grouped by month, each with a "Jetzt buchen" button that opens the booking dialog. |
| `[HSC-Event-Booking-Summary]` | Events whose title matches **Booked events (regex)**: totals per month and one card per booking. Protect the page with a WordPress page password, it shows names and contact data. |

Both shortcodes only look at events inside the **Date range** from the settings (inclusive, empty start = beginning of the current month, empty end = no limit).
Google Calendar is the single source of truth, the plugin never changes events and stores no bookings.
A booking request only sends two e-mails (notification to the club, confirmation to the visitor). The club then books
manually by renaming the event so it no longer matches the event filter (and matches the booked regex) and writes the
details into the event description, one `Key: value` line each: `Name`, `E-Mail`, `Telefon`, `Datum`, `Personen`,
`Ausrüstungen` (older spellings like `Teilnehmer`, `Leihausrüstung`, `Anmerkung` are read too). The notification e-mail
(subject `Verleih Anfrage - {name} - Sa, 17.01.2026 von 17:00 - 19:45 Uhr`) starts with exactly this `-- Eintrag für Kalender --` block to copy.

E-mails go through `wp_mail()` (PHP `mail()` unless an SMTP plugin is active). Sender address, sender name and recipient are
set under **HSC Calendar**. For reliable delivery the sending domain needs SPF/DKIM for the server that sends the mail.

## Release flow

- `main` and every pull request: lint + tests (`.github/workflows/ci.yml`).
- Push to `production` (e.g. merge `main` into it): `.github/workflows/release.yml`
  derives the next version from [Conventional Commits](https://www.conventionalcommits.org)
  since the last tag (`feat` → minor, `fix`/other → patch, `!`/`BREAKING CHANGE` → major),
  updates plugin header, `readme.txt` and `CHANGELOG.md`, commits to `production`,
  tags `vX.Y.Z` and publishes a GitHub release with `hsc-google-calendar.zip`.
- Installed sites check the latest GitHub release via [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) (same as hd-plugin-wordpress) and show the update in WP admin; the HSC Calendar page has a "Check for updates" button. The zip ships production `vendor/`.
  The repository must be public.
