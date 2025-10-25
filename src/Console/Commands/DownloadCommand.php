<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Console\Commands;

use Farzai\ThailandAddress\Data\Province;
use Farzai\ThailandAddress\Service\DownloaderService;
use Farzai\ThailandAddress\Service\ProvinceScraperService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'download',
    description: 'Download JSON data for all Thai provinces',
)]
final class DownloadCommand extends Command
{
    private ProvinceScraperService $scraperService;

    private DownloaderService $downloaderService;

    public function __construct()
    {
        parent::__construct();
        $this->scraperService = new ProvinceScraperService;

        // Get the resources/json directory path
        $resourcesDir = dirname(__DIR__, 3).'/resources/json';
        $this->downloaderService = new DownloaderService($resourcesDir);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Thai Province Data Downloader');

        // Step 1: Scrape provinces
        $io->section('Step 1: Fetching province list');
        $io->text('Scraping province data from government portal...');

        try {
            $provinces = $this->scraperService->scrapeProvinces();

            if (count($provinces) === 0) {
                $io->warning('No provinces found!');

                return Command::FAILURE;
            }

            $io->success(sprintf('Found %d provinces', count($provinces)));
        } catch (\RuntimeException $e) {
            $io->error('Failed to fetch province data: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Step 2: Download JSON files
        $io->section('Step 2: Downloading JSON files');

        $progressBar = new ProgressBar($output, count($provinces));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %message%');
        $progressBar->setMessage('Starting downloads...');
        $progressBar->start();

        $results = $this->downloaderService->downloadProvinces(
            $provinces,
            function (Province $province) use ($progressBar): void {
                $progressBar->setMessage(sprintf('Downloaded: %s', $province->name));
                $progressBar->advance();
            }
        );

        $progressBar->setMessage('Download complete!');
        $progressBar->finish();
        $io->newLine(2);

        // Step 3: Display results
        $io->section('Download Results');

        $successCount = count($results['success']);
        $failedCount = count($results['failed']);
        $total = $successCount + $failedCount;

        if ($successCount > 0) {
            $io->success(sprintf('Successfully downloaded %d/%d files', $successCount, $total));
        }

        if ($failedCount > 0) {
            $io->warning(sprintf('Failed to download %d/%d files', $failedCount, $total));

            $io->section('Failed Downloads');
            foreach ($results['failed'] as $failure) {
                $io->text('<error>✗</error> '.$failure);
            }
        }

        return $failedCount === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
