<?php

declare(strict_types=1);

/*
 * Pest bootstrap. Tests under tests/Core run on a plain test case: the core has
 * no Laravel dependency beyond illuminate/collections. Tests under
 * tests/Laravel boot a Testbench application with the service provider.
 *
 * The suite runs on Pest 3, 4 and 5 (CI resolves the newest one each PHP
 * version allows), so it sticks to the API the three share: test(), uses(),
 * beforeEach(), afterEach(), dataset() and ->with().
 */

uses(Farzai\ThailandAddress\Tests\Laravel\TestCase::class)->in('Laravel');
