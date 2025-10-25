<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Service;

use Farzai\ThailandAddress\Data\Province;

final class ValidatorService
{
    private string $dataDirectory;

    public function __construct(string $dataDirectory)
    {
        $this->dataDirectory = rtrim($dataDirectory, '/');
    }

    /**
     * Validate a single province JSON file.
     *
     * @return array{valid: bool, message: string, fileSize: int|null}
     */
    public function validateProvince(Province $province): array
    {
        $filePath = $this->dataDirectory.'/'.$province->getJsonFilename();

        // Check if file exists
        if (! file_exists($filePath)) {
            return [
                'valid' => false,
                'message' => 'File does not exist',
                'fileSize' => null,
            ];
        }

        // Check if file is readable
        if (! is_readable($filePath)) {
            return [
                'valid' => false,
                'message' => 'File is not readable',
                'fileSize' => null,
            ];
        }

        $fileSize = filesize($filePath);
        if ($fileSize === false) {
            return [
                'valid' => false,
                'message' => 'Could not determine file size',
                'fileSize' => null,
            ];
        }

        // Check if file is empty
        if ($fileSize === 0) {
            return [
                'valid' => false,
                'message' => 'File is empty',
                'fileSize' => 0,
            ];
        }

        // Try to read and parse JSON
        $content = file_get_contents($filePath);
        if ($content === false) {
            return [
                'valid' => false,
                'message' => 'Could not read file contents',
                'fileSize' => $fileSize,
            ];
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            // Basic validation - check if it's an array
            if (! is_array($data)) {
                return [
                    'valid' => false,
                    'message' => 'JSON content is not an array',
                    'fileSize' => $fileSize,
                ];
            }

            // Check if array is empty
            if (count($data) === 0) {
                return [
                    'valid' => false,
                    'message' => 'JSON array is empty',
                    'fileSize' => $fileSize,
                ];
            }

            return [
                'valid' => true,
                'message' => sprintf('Valid JSON with %d records', count($data)),
                'fileSize' => $fileSize,
            ];
        } catch (\JsonException $e) {
            return [
                'valid' => false,
                'message' => 'Invalid JSON: '.$e->getMessage(),
                'fileSize' => $fileSize,
            ];
        }
    }

    /**
     * Validate all province JSON files.
     *
     * @param  array<Province>  $provinces
     * @return array{valid: array<string>, invalid: array<string>, details: array<array{province: string, valid: bool, message: string, fileSize: int|null}>}
     */
    public function validateProvinces(array $provinces): array
    {
        $results = [
            'valid' => [],
            'invalid' => [],
            'details' => [],
        ];

        foreach ($provinces as $province) {
            $validation = $this->validateProvince($province);

            $results['details'][] = [
                'province' => $province->name,
                'valid' => $validation['valid'],
                'message' => $validation['message'],
                'fileSize' => $validation['fileSize'],
            ];

            if ($validation['valid']) {
                $results['valid'][] = $province->name;
            } else {
                $results['invalid'][] = $province->name;
            }
        }

        return $results;
    }

    /**
     * Get summary statistics for validation results.
     *
     * @param  array{valid: array<string>, invalid: array<string>, details: array<array{province: string, valid: bool, message: string, fileSize: int|null}>}  $validationResults
     * @return array{total: int, valid: int, invalid: int, totalSize: int}
     */
    public function getSummary(array $validationResults): array
    {
        $totalSize = 0;

        foreach ($validationResults['details'] as $detail) {
            if ($detail['fileSize'] !== null) {
                $totalSize += $detail['fileSize'];
            }
        }

        return [
            'total' => count($validationResults['details']),
            'valid' => count($validationResults['valid']),
            'invalid' => count($validationResults['invalid']),
            'totalSize' => $totalSize,
        ];
    }
}
