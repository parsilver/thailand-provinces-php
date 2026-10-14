<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Tests\Laravel;

use Farzai\ThailandAddress\Laravel\ThailandAddressServiceProvider;
use Farzai\ThailandAddress\Thailand;
use Orchestra\Testbench\TestCase as Testbench;

abstract class TestCase extends Testbench
{
    protected function getPackageProviders($app): array
    {
        return [ThailandAddressServiceProvider::class];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Thailand::resolveUsing(null);
    }
}
