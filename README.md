# HSC Google Calendar

WordPress plugin for the club website: free ice time slots from Google Calendar become bookable.

## Development

```bash
composer install
composer lint   # php -l + PHPCS (WordPress standards)
composer test   # PHPUnit
bin/build-zip.sh  # -> dist/hsc-google-calendar.zip
```

## Release flow

- `main` and every pull request: lint + tests (`.github/workflows/ci.yml`).
- Push to `production` (e.g. merge `main` into it): `.github/workflows/release.yml`
  derives the next version from [Conventional Commits](https://www.conventionalcommits.org)
  since the last tag (`feat` → minor, `fix`/other → patch, `!`/`BREAKING CHANGE` → major),
  updates plugin header, `readme.txt` and `CHANGELOG.md`, commits to `production`,
  tags `vX.Y.Z` and publishes a GitHub release with `hsc-google-calendar.zip`.
- Installed sites check the latest GitHub release and show the update in WP admin.
  The repository must be public.
