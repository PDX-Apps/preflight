<?php

declare(strict_types=1);

use PdxApps\Preflight\Preflight;
use PdxApps\Preflight\Steps\ComposerNormalize;
use PdxApps\Preflight\Steps\Deptrac;
use PdxApps\Preflight\Steps\Tests;

/*
|--------------------------------------------------------------------------
| Preflight runs on Preflight
|--------------------------------------------------------------------------
|
| This is Preflight's own config — it dogfoods every built-in step, including
| the two opt-in ones that aren't in the default set:
|
|   ComposerNormalize  keeps composer.json tidy        (ergebnis/composer-normalize plugin)
|   Deptrac            enforces the layer boundaries    (see deptrac.yaml)
|
| addSteps() keeps the eight auto-detected defaults and appends the opt-in pair,
| so we don't restate the whole pipeline. tune() reconfigures the Tests step to
| also emit coverage reports (CI uploads them as an artifact). Each step still
| runs only if its tool is installed; coverage needs a driver, and without one
| the tests still run with a non-failing warning.
*/

return Preflight::configure()
    ->addSteps([
        ComposerNormalize::class, // composer.json hygiene  (fixable, opt-in)
        Deptrac::class,           // architecture layers    (opt-in)
    ])
    ->tune(
        Tests::make()
            ->coverage([
                'clover' => 'build/coverage.xml', // for Codecov/Coveralls + the gate
                'html' => 'build/coverage',       // browsable report, uploaded as an artifact
            ])
            ->minCoverage(97)      // ~97.5% line coverage; floor leaves a little headroom
            ->minPatchCoverage(90), // changed lines on a --since/--dirty run must be 90% covered
    );
