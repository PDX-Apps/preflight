<?php

declare(strict_types=1);

namespace PdxApps\Preflight\Report;

/**
 * The additive sections a {@see RunReport} can carry, selected via `--report-include`.
 * Metadata (version, timestamp, mode, success, summary) is always present; these are the
 * optional parts layered on top.
 */
enum ReportInclude: string
{
    /** The normalized findings list. */
    case Findings = 'findings';

    /** The per-step results (name, status, duration). */
    case Steps = 'steps';

    /** Include passed/skipped steps in the steps list, not just failed ones. */
    case Passing = 'passing';

    /** The raw tool output captured per step (verbose). */
    case Output = 'output';

    /**
     * Parse a comma-separated `--report-include` value. `all` expands to every section.
     *
     * @return list<self>
     */
    public static function parse(string $value): array
    {
        $names = array_values(array_filter(array_map(trim(...), explode(',', $value))));

        if (in_array('all', $names, true)) {
            return self::cases();
        }

        $includes = [];
        foreach ($names as $name) {
            $case = self::tryFrom($name);
            if ($case instanceof self && ! in_array($case, $includes, true)) {
                $includes[] = $case;
            }
        }

        return $includes;
    }
}
