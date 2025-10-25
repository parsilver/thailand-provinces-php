<?php

declare(strict_types=1);

use Farzai\ThailandAddress\Data\Province;

test('Province can be instantiated with correct properties', function (): void {
    $province = new Province(
        name: 'Bangkok',
        thaiName: 'กรุงเทพมหานคร',
        resourceId: '12345-abcde'
    );

    expect($province->name)->toBe('Bangkok');
    expect($province->thaiName)->toBe('กรุงเทพมหานคร');
    expect($province->resourceId)->toBe('12345-abcde');
});

test('Province returns correct JSON filename', function (): void {
    $province = new Province(
        name: 'Bangkok',
        thaiName: 'กรุงเทพมหานคร',
        resourceId: '12345-abcde'
    );

    expect($province->getJsonFilename())->toBe('Bangkok.json');
});

test('Province returns correct download URL', function (): void {
    $province = new Province(
        name: 'Bangkok',
        thaiName: 'กรุงเทพมหานคร',
        resourceId: '12345-abcde'
    );

    $expectedUrl = 'https://catalog.dopa.go.th/dataset/705402d4-2715-4ece-9166-22b54c6c3476/resource/12345-abcde/download/.json';

    expect($province->getDownloadUrl())->toBe($expectedUrl);
});
