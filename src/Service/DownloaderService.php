<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Service;

use Farzai\ThailandAddress\Data\Province;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class DownloaderService
{
    private const int MAX_RETRIES = 3;

    private const array BACKOFF_DELAYS = [1, 2, 4]; // seconds

    private HttpClientInterface $httpClient;

    private string $outputDirectory;

    public function __construct(
        string $outputDirectory,
        ?HttpClientInterface $httpClient = null
    ) {
        $this->outputDirectory = rtrim($outputDirectory, '/');
        $this->httpClient = $httpClient ?? HttpClient::create([
            'timeout' => 60,
            'max_redirects' => 5,
        ]);
    }

    /**
     * Download JSON data for a single province.
     *
     * @throws \RuntimeException if download fails after all retries
     */
    public function downloadProvince(Province $province): string
    {
        $content = $this->downloadWithRetry($province->getDownloadUrl(), $province->name);
        $outputPath = $this->getOutputPath($province);

        $this->ensureDirectoryExists();

        if (file_put_contents($outputPath, $content) === false) {
            throw new \RuntimeException("Failed to write file: {$outputPath}");
        }

        return $outputPath;
    }

    /**
     * Download JSON data for multiple provinces.
     *
     * @param  array<Province>  $provinces
     * @param  (callable(Province): void)|null  $progressCallback  Called with province after each download
     * @return array{success: array<string>, failed: array<string>}
     */
    public function downloadProvinces(array $provinces, ?callable $progressCallback = null): array
    {
        $results = [
            'success' => [],
            'failed' => [],
        ];

        foreach ($provinces as $province) {
            try {
                $path = $this->downloadProvince($province);
                $results['success'][] = $path;

                if ($progressCallback !== null) {
                    $progressCallback($province);
                }
            } catch (\RuntimeException $e) {
                $results['failed'][] = sprintf('%s: %s', $province->name, $e->getMessage());

                if ($progressCallback !== null) {
                    $progressCallback($province);
                }
            }
        }

        return $results;
    }

    /**
     * Download content with retry logic and exponential backoff.
     *
     * @throws \RuntimeException if all retries fail
     */
    private function downloadWithRetry(string $url, string $provinceName): string
    {
        $lastException = null;

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            try {
                $response = $this->httpClient->request('GET', $url);
                $content = $response->getContent();

                // Validate that we got JSON
                json_decode($content, true, 512, JSON_THROW_ON_ERROR);

                return $content;
            } catch (TransportExceptionInterface|\JsonException $e) {
                $lastException = $e;

                if ($attempt < self::MAX_RETRIES - 1) {
                    $delay = self::BACKOFF_DELAYS[$attempt];
                    sleep($delay);
                }
            }
        }

        throw new \RuntimeException(
            sprintf(
                'Failed to download data for %s after %d attempts: %s',
                $provinceName,
                self::MAX_RETRIES,
                $lastException?->getMessage() ?? 'Unknown error'
            ),
            0,
            $lastException
        );
    }

    private function getOutputPath(Province $province): string
    {
        return $this->outputDirectory.'/'.$province->getJsonFilename();
    }

    private function ensureDirectoryExists(): void
    {
        if (! is_dir($this->outputDirectory) && ! mkdir($this->outputDirectory, 0755, true) && ! is_dir($this->outputDirectory)) {
            throw new \RuntimeException("Failed to create directory: {$this->outputDirectory}");
        }
    }
}
