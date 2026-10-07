<?php

declare(strict_types=1);

namespace Hsc\Release;

/**
 * Pure helpers for semantic version bumps based on conventional commits.
 */
final class Bumper
{
    /**
     * @param list<string> $subjects Commit subjects (first line) since the last release.
     * @return 'major'|'minor'|'patch'
     */
    public static function determineBump(array $subjects): string
    {
        $bump = 'patch';
        foreach ($subjects as $subject) {
            if (preg_match('/^\w+(\([^)]*\))?!:/', $subject) === 1 || str_contains($subject, 'BREAKING CHANGE')) {
                return 'major';
            }
            if (preg_match('/^feat(\([^)]*\))?:/', $subject) === 1) {
                $bump = 'minor';
            }
        }

        return $bump;
    }

    public static function nextVersion(string $current, string $bump): string
    {
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $current, $m) !== 1) {
            throw new \InvalidArgumentException("Invalid version: {$current}");
        }
        [, $major, $minor, $patch] = array_map('intval', $m);

        return match ($bump) {
            'major' => ($major + 1) . '.0.0',
            'minor' => "{$major}." . ($minor + 1) . '.0',
            'patch' => "{$major}.{$minor}." . ($patch + 1),
            default => throw new \InvalidArgumentException("Invalid bump: {$bump}"),
        };
    }

    /**
     * Groups commit subjects into a Keep-a-Changelog style section.
     *
     * @param list<string> $subjects
     */
    public static function changelogSection(string $version, string $date, array $subjects): string
    {
        $groups = ['Added' => [], 'Fixed' => [], 'Changed' => []];
        foreach ($subjects as $subject) {
            $text = preg_replace('/^\w+(\([^)]*\))?!?:\s*/', '', $subject) ?? $subject;
            if (preg_match('/^feat/', $subject) === 1) {
                $groups['Added'][] = $text;
            } elseif (preg_match('/^fix/', $subject) === 1) {
                $groups['Fixed'][] = $text;
            } else {
                $groups['Changed'][] = $text;
            }
        }

        $out = "## [{$version}] - {$date}\n";
        foreach ($groups as $title => $items) {
            if ($items === []) {
                continue;
            }
            $out .= "\n### {$title}\n\n";
            foreach ($items as $item) {
                $out .= "- {$item}\n";
            }
        }

        return $out;
    }
}
