<?php

declare(strict_types=1);

/*
 * The token scoping of update-data.yml (security review on #22): the job that
 * installs dependencies and runs the suite has a read-only token that is never
 * written to disk; the job that can push runs no Composer and no PHP, and
 * commits only the data files; the job that starts tests.yml on the pull
 * request's branch can do nothing else.
 */
function updateDataJobs(): array
{
    $yaml = (string) file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/update-data.yml');
    $jobs = substr($yaml, (int) strpos($yaml, "\njobs:\n") + 7);
    preg_match_all('/^  ([a-z][a-z0-9_-]*):\n(.*?)(?=^  [a-z][a-z0-9_-]*:\n|\z)/ms', $jobs, $m, PREG_SET_ORDER);

    return ['yaml' => $yaml, 'jobs' => array_column(array_map(fn (array $x) => [$x[1], $x[2]], $m), 1, 0)];
}

test('three jobs: a read-only import, a minimal pull request job, and one that starts the tests', function () {
    ['yaml' => $yaml, 'jobs' => $jobs] = updateDataJobs();
    $this->assertSame(['import', 'open-pr', 'run-tests'], array_keys($jobs));
    preg_match('/^permissions:\n((?:  .*\n)+)/m', $yaml, $m);
    $this->assertSame("  contents: read\n", $m[1], 'the workflow default is read-only');
});

test('the import job cannot write and leaves no token on disk', function () {
    $import = updateDataJobs()['jobs']['import'];
    $this->assertMatchesRegularExpression('/permissions:\n\s+contents: read\n\s+pull-requests: read\n/', $import);
    $this->assertStringNotContainsString(': write', $import);
    $this->assertStringContainsString('persist-credentials: false', $import);
    $this->assertStringContainsString('--no-scripts', $import);
    $this->assertStringContainsString('continue-on-error: true', $import, 'a failing suite still reaches the pull request');
});

test('the write job runs no Composer and no PHP, and commits only the data files', function () {
    $pr = updateDataJobs()['jobs']['open-pr'];
    $this->assertStringContainsString('contents: write', $pr);
    $this->assertStringContainsString('persist-credentials: false', $pr);
    foreach (['composer', 'setup-php', 'vendor/bin'] as $tool) {
        $this->assertStringNotContainsString($tool, $pr, $tool);
    }
    $this->assertDoesNotMatchRegularExpression('/(^|[\s;&|(])php\s/m', $pr, 'no php command');
    $this->assertStringContainsString('git add -- data/provinces.php data/districts.php data/subdistricts.php data/sources.json', $pr);
    $this->assertStringContainsString('--draft', $pr, 'a failing suite opens a draft');
    $this->assertStringContainsString('needs-tests', $pr);
    $this->assertStringContainsString('--force', $pr, 'a branch left by a closed pull request is updated, not a dead end');
});

test('the skip looks for an open pull request, not for a leftover branch', function () {
    $import = updateDataJobs()['jobs']['import'];
    $this->assertStringContainsString('gh pr list --head "data/$target" --state open', $import);
    $this->assertStringNotContainsString('git ls-remote', $import);
});

test('the data files are handed on before any third-party code runs, and tests.log apart', function () {
    $import = updateDataJobs()['jobs']['import'];
    $data = strpos($import, 'name: import-data-${{ github.run_id }}');
    $this->assertNotFalse($data, 'a data-only artifact');
    $this->assertLessThan(strpos($import, 'composer update'), $data, 'uploaded before Composer runs');
    $this->assertLessThan(strpos($import, 'vendor/bin/pest'), $data);
    $this->assertStringContainsString('name: import-tests-${{ github.run_id }}', $import);
});

test('the write job takes the data only from the data artifact and checks its grammar first', function () {
    $pr = updateDataJobs()['jobs']['open-pr'];
    $this->assertStringContainsString('name: import-data-${{ github.run_id }}', $pr);
    $this->assertStringContainsString('tools/check-data-grammar.sh', $pr);
    $this->assertLessThan(strpos($pr, 'git add --'), strpos($pr, 'tools/check-data-grammar.sh'));
});

/*
 * A pull request opened with GITHUB_TOKEN starts no other workflow, so the
 * PHP x Laravel matrix would never run on a data change. A workflow_dispatch
 * is the documented exception: run-tests dispatches tests.yml on the data
 * branch, whose head is the pull request's head, so the matrix shows on it.
 */
test('the matrix runs on the data pull request: tests.yml is dispatched on its branch', function () {
    $run = updateDataJobs()['jobs']['run-tests'];
    $this->assertStringContainsString('needs: [import, open-pr]', $run);
    $this->assertStringContainsString('gh workflow run tests.yml --ref "data/$TARGET"', $run);

    $tests = (string) file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/tests.yml');
    $this->assertMatchesRegularExpression('/^on:\n(?:  .*\n|    .*\n)*  workflow_dispatch:/m', $tests);
    preg_match('/^permissions:\n((?:  .*\n)+)/m', $tests, $m);
    $this->assertSame("  contents: read\n", $m[1], 'tests.yml stays read-only');
});

test('only the dispatching job can start workflows, and it can do nothing else', function () {
    ['jobs' => $jobs] = updateDataJobs();
    preg_match('/permissions:\n((?:      .*\n)+)/', $jobs['run-tests'], $m);
    $this->assertSame("      actions: write\n", $m[1], 'actions: write and nothing more');
    foreach (['checkout', 'composer', 'setup-php', 'vendor/bin', 'git push'] as $step) {
        $this->assertStringNotContainsString($step, $jobs['run-tests'], $step);
    }
    $this->assertStringNotContainsString('actions: write', $jobs['import']);
    $this->assertStringNotContainsString('actions: write', $jobs['open-pr']);
});
