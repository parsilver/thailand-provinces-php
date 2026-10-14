<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Laravel;

use Farzai\ThailandAddress\AddressRepository;
use Farzai\ThailandAddress\Laravel\Facades\ThailandAddress;
use Farzai\ThailandAddress\Laravel\Rules\AreaRule;
use Farzai\ThailandAddress\Laravel\Rules\ThaiDistrict;
use Farzai\ThailandAddress\Laravel\Rules\ThaiPostalCode;
use Farzai\ThailandAddress\Laravel\Rules\ThaiProvince;
use Farzai\ThailandAddress\Laravel\Rules\ThaiSubdistrict;
use Farzai\ThailandAddress\Thailand;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ThailandAddressServiceProvider extends ServiceProvider
{
    /** String rule name => rule class. */
    private const RULES = [
        'thai_province' => ThaiProvince::class,
        'thai_district' => ThaiDistrict::class,
        'thai_subdistrict' => ThaiSubdistrict::class,
        'thai_postal_code' => ThaiPostalCode::class,
    ];

    public function register(): void
    {
        $this->app->singleton(AddressRepository::class);
    }

    public function boot(): void
    {
        // Thailand, the facade, the rules and the casts all use the container's
        // repository, so one fake bound there reaches every one of them, and
        // Thailand::swap() rebinds it there rather than beside it.
        $app = $this->app;
        Thailand::resolveUsing(
            static fn (): AddressRepository => $app->make(AddressRepository::class),
            static function (?AddressRepository $repository) use ($app): void {
                if ($repository === null) {
                    $app->forgetInstance(AddressRepository::class);
                } else {
                    $app->instance(AddressRepository::class, $repository);
                }
                ThailandAddress::clearResolvedInstance(AddressRepository::class);
            },
        );

        $lang = dirname(__DIR__, 2) . '/resources/lang';
        $this->loadTranslationsFrom($lang, 'thailand-address');
        $this->publishes([$lang => $this->app->langPath('vendor/thailand-address')], 'thailand-address-lang');

        Rule::macro('thaiProvince', fn (): ThaiProvince => ThaiProvince::make());
        Rule::macro('thaiDistrict', fn (): ThaiDistrict => ThaiDistrict::make());
        Rule::macro('thaiSubdistrict', fn (): ThaiSubdistrict => ThaiSubdistrict::make());
        Rule::macro('thaiPostalCode', fn (): ThaiPostalCode => ThaiPostalCode::make());

        $this->callAfterResolving('validator', function (ValidationFactory $factory): void {
            foreach (self::RULES as $name => $class) {
                $factory->extend($name, fn (string $attribute, mixed $value, array $parameters, Validator $validator): bool => self::stringRule($class, $parameters)->failure($value, $validator->getData(), $attribute) === null);
                $factory->replacer($name, function (string $message, string $attribute, string $rule, array $parameters, Validator $validator) use ($name, $class): string {
                    // :other names this attribute's own parent, so a wildcard
                    // parameter resolves per row (addresses.1.province).
                    // The attribute arrives unescaped here while the data is
                    // escaped, so both are read through the rule's own key walk,
                    // which has the validator and so its placeholder (#20):
                    // getValue() would miss a row key with a dot in it.
                    $rule = self::stringRule($class, $parameters)->setValidator($validator)->setData($validator->getData());
                    $other = $rule->otherName($attribute);
                    // Laravel falls back to the key itself when the app defines no
                    // message for the rule; then use the package's message.
                    if ($message !== "validation.{$name}") {
                        return str_replace(':other', $other, $message);
                    }
                    $key = $rule->failure($rule->valueAt($validator->getData(), $attribute), $validator->getData(), $attribute) ?? 'province';

                    return (string) trans('thailand-address::validation.' . $key, [
                        'attribute' => $validator->getDisplayableAttribute($attribute),
                        'other' => $other,
                    ]);
                });
            }
        });
    }

    /**
     * @param class-string<AreaRule> $class
     * @param list<string> $parameters
     */
    private static function stringRule(string $class, array $parameters): AreaRule
    {
        $rule = $class::make();
        if (isset($parameters[0])) {
            match (true) {
                $rule instanceof ThaiDistrict => $rule->inProvince($parameters[0]),
                $rule instanceof ThaiSubdistrict => $rule->inDistrict($parameters[0]),
                $rule instanceof ThaiPostalCode => $rule->forSubdistrict($parameters[0]),
                default => throw new \InvalidArgumentException('thai_province takes no parameter'),
            };
        }

        return $rule;
    }
}
