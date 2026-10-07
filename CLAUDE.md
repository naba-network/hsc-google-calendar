# HSC Google Calendar – project rules

WordPress plugin for the HSC Hohenems club website (free ice time slots from Google Calendar, bookable online).

If you add or update ANY file in this directory, you MUST use these skills if a matching file is touched:

- `vue-best-practices`
- `frontend-best-practices`
- `php-best-practices`
- `php-engineer`
- `php-testing`

Alwayse use these skills for all tasks:

- `caveman`


## This repository is PUBLIC

Everything committed or pushed is world-readable and stays in git history. **Never commit or push anything private or sensitive**, including:

- API keys, tokens, passwords, OAuth client secrets, service-account JSON files, private keys
- `.env` files, `wp-config.php` snippets with credentials, local config overrides
- Real calendar IDs/URLs of private calendars, personal data (names, emails, phone numbers) of members, trainers or people who booked, booking records
- Internal URLs, server paths, hostnames or infrastructure details

Rules:

- Keep secrets out of the code: read them from WordPress options, `wp-config.php` constants or environment variables, never hardcode them.
- Use obviously fake values in tests, docs and examples (e.g. `example@example.com`, `calendar-id@example.test`).
- Before every commit, check `git status` / `git diff --staged` for anything that looks like a secret or personal data. If unsure, ask instead of committing.
- Add new secret-bearing file patterns to `.gitignore` (`.env*`, `*.pem`, `*credentials*.json`, `*service-account*.json`, `wp-config.php`).
- If a secret was pushed by accident: tell the user immediately. Rewriting history is not enough – the secret must be revoked/rotated.
- Do not commit or push without the user asking for it.

## Conventions

- Commit messages follow Conventional Commits (`feat:`, `fix:`, `docs:` …); the release workflow derives version bumps and changelog from them.
- `main`: CI (lint + tests). Push to `production`: release workflow bumps version, readme, changelog, tags and publishes the zip. Do not edit version numbers by hand.
- Checks: `composer lint`, `composer test`. Plugin code supports PHP 8.0+ (CI matrix 8.1/8.3) and follows WordPress Coding Standards.
