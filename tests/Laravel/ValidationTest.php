<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Tests\Laravel;

use Farzai\ThailandAddress\Laravel\Rules\ThaiDistrict;
use Farzai\ThailandAddress\Laravel\Rules\ThaiPostalCode;
use Farzai\ThailandAddress\Laravel\Rules\ThaiProvince;
use Farzai\ThailandAddress\Laravel\Rules\ThaiSubdistrict;
use Farzai\ThailandAddress\Thailand;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PHPUnit\Framework\Attributes\DataProvider;

final class ValidationTest extends TestCase
{
    /** A Bangkok address that is right at every level. */
    private const VALID = ['province' => '10', 'district' => '1001', 'subdistrict' => '100101', 'postal_code' => '10200'];

    /** @return array<string, list<mixed>> */
    private static function objectRules(): array
    {
        return [
            'province' => ['required', Rule::thaiProvince()],
            'district' => ['required', Rule::thaiDistrict()->inProvince('province')],
            'subdistrict' => ['required', Rule::thaiSubdistrict()->inDistrict('district')],
            'postal_code' => ['required', Rule::thaiPostalCode()->forSubdistrict('subdistrict')],
        ];
    }

    /** @return array<string, string> */
    private static function stringRules(): array
    {
        return [
            'province' => 'required|thai_province',
            'district' => 'required|thai_district:province',
            'subdistrict' => 'required|thai_subdistrict:district',
            'postal_code' => 'required|thai_postal_code:subdistrict',
        ];
    }

    /** @return iterable<string, array{string}> */
    public static function styles(): iterable
    {
        yield 'rule objects' => ['object'];
        yield 'string rules' => ['string'];
    }

    /** @param array<string, mixed> $data @return array<string, list<string>> */
    private function errors(string $style, array $data): array
    {
        return Validator::make($data, $style === 'object' ? self::objectRules() : self::stringRules())->errors()->toArray();
    }

    #[DataProvider('styles')]
    public function test_a_consistent_address_passes(string $style): void
    {
        $this->assertSame([], $this->errors($style, self::VALID));
        $this->assertSame([], $this->errors($style, ['province' => 38, 'district' => 3801, 'subdistrict' => 380101, 'postal_code' => '38000']));
    }

    #[DataProvider('styles')]
    public function test_unknown_codes_fail_each_with_its_own_message(string $style): void
    {
        $errors = $this->errors($style, ['province' => '99', 'district' => '9999', 'subdistrict' => 'x', 'postal_code' => '1020']);
        $this->assertSame([
            'province' => ['The province must be a valid Thai province code.'],
            'district' => ['The district must be a valid Thai district code.'],
            'subdistrict' => ['The subdistrict must be a valid Thai subdistrict code.'],
            'postal_code' => ['The postal code must be a valid Thai postal code.'],
        ], $errors);
    }

    #[DataProvider('styles')]
    public function test_each_level_must_belong_to_its_parent(string $style): void
    {
        $errors = $this->errors($style, ['province' => '38', 'district' => '1001', 'subdistrict' => '380101', 'postal_code' => '50000']);
        $this->assertSame([
            'district' => ['The district is not in the selected province.'],
            'subdistrict' => ['The subdistrict is not in the selected district.'],
            'postal_code' => ['The postal code does not serve the selected subdistrict.'],
        ], $errors);
    }

    #[DataProvider('styles')]
    public function test_an_invalid_parent_is_reported_once_on_the_parent(string $style): void
    {
        $errors = $this->errors($style, ['province' => '99', 'district' => '1001', 'subdistrict' => '100101', 'postal_code' => '10200']);
        $this->assertSame(['province'], array_keys($errors));
    }

    #[DataProvider('styles')]
    public function test_messages_are_translated_to_thai(string $style): void
    {
        $this->app->setLocale('th');
        $errors = $this->errors($style, ['province' => '38', 'district' => '1001', 'subdistrict' => '999999', 'postal_code' => '10200']);
        $this->assertSame([
            'district' => ['district ไม่อยู่ในprovinceที่เลือก'],
            'subdistrict' => ['subdistrict ไม่ใช่รหัสตำบลหรือแขวงที่ถูกต้อง'],
        ], $errors);
    }

    public function test_custom_attribute_names_reach_both_placeholders(): void
    {
        $validator = Validator::make(['province' => '38', 'district' => '1001'], [
            'district' => [Rule::thaiDistrict()->inProvince('province')],
        ], [], ['province' => 'จังหวัด', 'district' => 'อำเภอ']);
        $this->app->setLocale('th');
        $this->assertSame(['อำเภอ ไม่อยู่ในจังหวัดที่เลือก'], $validator->errors()->get('district'));
    }

    public function test_an_app_message_for_a_string_rule_wins(): void
    {
        $validator = Validator::make(['district' => '9999'], ['district' => 'thai_district'], ['district.thai_district' => 'Pick a real :attribute.']);
        $this->assertSame(['Pick a real district.'], $validator->errors()->get('district'));
    }

    public function test_wildcard_parents_pair_by_index(): void
    {
        $data = ['addresses' => [
            ['province' => '10', 'district' => '1001'],
            ['province' => '38', 'district' => '1001'],
        ]];
        $errors = Validator::make($data, ['addresses.*.district' => [Rule::thaiDistrict()->inProvince('addresses.*.province')]])->errors()->toArray();
        $this->assertSame(['addresses.1.district'], array_keys($errors));

        $errors = Validator::make($data, ['addresses.*.district' => 'thai_district:addresses.*.province'])->errors()->toArray();
        $this->assertSame(['addresses.1.district'], array_keys($errors));
    }

    public function test_rule_classes_work_without_the_macros(): void
    {
        $validator = Validator::make(self::VALID, [
            'province' => [new ThaiProvince()],
            'district' => [ThaiDistrict::make()->inProvince('province')],
            'subdistrict' => [ThaiSubdistrict::make()->inDistrict('district')],
            'postal_code' => [ThaiPostalCode::make()->forSubdistrict('subdistrict')],
        ]);
        $this->assertTrue($validator->passes());
    }

    public function test_a_subdistrict_without_known_postal_codes_accepts_any_valid_one(): void
    {
        $none = Thailand::subdistricts()->first(fn ($s) => $s->postalCodes === []);
        $this->assertNotNull($none);
        $validator = Validator::make(['subdistrict' => $none->code, 'postal_code' => '10200'], ['postal_code' => 'thai_postal_code:subdistrict']);
        $this->assertTrue($validator->passes());
    }

    public function test_non_scalar_values_fail_cleanly(): void
    {
        $validator = Validator::make(['province' => ['10']], ['province' => 'thai_province']);
        $this->assertSame(['The province must be a valid Thai province code.'], $validator->errors()->get('province'));
    }
}
