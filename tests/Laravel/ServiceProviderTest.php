<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Tests\Laravel;

use Farzai\ThailandAddress\AddressRepository;
use Farzai\ThailandAddress\Laravel\Facades\ThailandAddress;
use Farzai\ThailandAddress\Thailand;
use Illuminate\Support\Facades\Validator;

final class ServiceProviderTest extends TestCase
{
    public function test_binds_one_repository_shared_by_both_facades(): void
    {
        $repository = $this->app->make(AddressRepository::class);
        $this->assertSame($repository, $this->app->make(AddressRepository::class));
        $this->assertSame($repository, Thailand::repository());
        $this->assertSame($repository, ThailandAddress::getFacadeRoot());
        $this->assertSame('Bangkok', ThailandAddress::province('10')?->nameEn);
        $this->assertSame(Thailand::province('10'), ThailandAddress::province('10'));
    }

    public function test_the_package_is_discovered_from_composer_json(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);
        $this->assertSame(
            ['providers' => [\Farzai\ThailandAddress\Laravel\ThailandAddressServiceProvider::class], 'aliases' => ['ThailandAddress' => ThailandAddress::class]],
            $composer['extra']['laravel'],
        );
    }

    public function test_one_fake_in_the_container_reaches_facades_rules_and_casts(): void
    {
        $fake = AddressRepository::fromArrays(['99' => ['name_th' => 'ทดสอบ', 'name_en' => 'Test', 'iso_code' => 'TH-99']]);
        ThailandAddress::swap($fake);

        $this->assertSame('Test', ThailandAddress::province('99')?->nameEn);
        $this->assertSame('Test', Thailand::province('99')?->nameEn);
        $this->assertNull(Thailand::province('10'));
        $this->assertTrue(Validator::make(['p' => '99'], ['p' => 'thai_province'])->passes());
        $this->assertTrue(Validator::make(['p' => '10'], ['p' => 'thai_province'])->fails());
    }
}
