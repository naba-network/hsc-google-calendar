<?php
/**
 * Bumps the plugin version from conventional commits since the last tag and
 * updates plugin header, constant, readme.txt and CHANGELOG.md.
 *
 * Usage: php bin/bump-version.php   (prints the new version to stdout)
 */

declare(strict_types=1);

require __DIR__ . '/Bumper.php';

use Hsc\Release\Bumper;

$root = dirname(__DIR__);
$plugin = "{$root}/hsc-google-calendar.php";

$lastTag = trim((string) shell_exec('git describe --tags --abbrev=0 --match "v*" 2>/dev/null'));
$range = $lastTag !== '' ? "{$lastTag}..HEAD" : 'HEAD';
$subjects = array_values(array_filter(
    array_map('trim', explode("\n", (string) shell_exec('git log --no-merges --pretty=format:%s ' . escapeshellarg($range)))),
    static fn (string $s): bool => $s !== '' && !str_starts_with($s, 'chore(release)')
));
if ($subjects === []) {
    fwrite(STDERR, "No commits since {$lastTag}; nothing to release.\n");
    exit(2);
}

$source = (string) file_get_contents($plugin);
if (preg_match('/^ \* Version:\s*(\S+)/m', $source, $m) !== 1) {
    fwrite(STDERR, "Version header not found.\n");
    exit(1);
}
$current = $m[1];
$new = Bumper::nextVersion($current, Bumper::determineBump($subjects));

$source = preg_replace('/^( \* Version:\s*)\S+/m', '${1}' . $new, $source);
$source = preg_replace("/(define\( 'HSC_GCAL_VERSION', ')[^']+(' \);)/", '${1}' . $new . '${2}', (string) $source);
file_put_contents($plugin, $source);

$readme = (string) file_get_contents("{$root}/readme.txt");
$readme = preg_replace('/^(Stable tag:\s*)\S+/m', '${1}' . $new, $readme);
$entry = "= {$new} =\n" . implode("\n", array_map(static fn (string $s): string => '* ' . preg_replace('/^\w+(\([^)]*\))?!?:\s*/', '', $s), $subjects)) . "\n\n";
$readme = preg_replace('/^(== Changelog ==\n\n)/m', '${1}' . addcslashes($entry, '\\$'), (string) $readme);
file_put_contents("{$root}/readme.txt", $readme);

$changelog = (string) file_get_contents("{$root}/CHANGELOG.md");
$section = Bumper::changelogSection($new, gmdate('Y-m-d'), $subjects);
$pos = strpos($changelog, "\n## [");
$changelog = $pos === false
    ? rtrim($changelog) . "\n\n{$section}"
    : substr($changelog, 0, $pos + 1) . "{$section}\n" . substr($changelog, $pos + 1);
file_put_contents("{$root}/CHANGELOG.md", $changelog);

echo $new, "\n";
