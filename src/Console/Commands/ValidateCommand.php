<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Console\Commands;

use Farzai\ThailandAddress\Service\ProvinceScraperService;
use Farzai\ThailandAddress\Service\ValidatorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'validate',
    description: 'Validate downloaded JSON files',
)]
final class ValidateCommand extends Command
{
    private ProvinceScraperService $scraperService;

    private ValidatorService $validatorService;

    public function __construct()
    {
        parent::__construct();
        $this->scraperService = new ProvinceScraperService;

        // Get the resources/json directory path
        $resourcesDir = dirname(__DIR__, 3).'/resources/json';
        $this->validatorService = new ValidatorService($resourcesDir);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Thai Province Data Validator');

        // Step 1: Get province list
        $io->section('Step 1: Fetching province list');

        try {
            $provinces = $this->scraperService->scrapeProvinces();

            if (count($provinces) === 0) {
                $io->warning('No provinces found!');

                return Command::FAILURE;
            }

            $io->success(sprintf('Found %d provinces to validate', count($provinces)));
        } catch (\RuntimeException $e) {
            $io->error('Failed to fetch province data: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Step 2: Validate files
        $io->section('Step 2: Validating JSON files');

        $results = $this->validatorService->validateProvinces($provinces);
        $summary = $this->validatorService->getSummary($results);

        // Step 3: Display results
        $io->section('Validation Results');

        // Create summary table
        $summaryTable = new Table($output);
        $summaryTable->setHeaders(['Metric', 'Value']);
        $summaryTable->addRows([
            ['Total Files', $summary['total']],
            ['Valid Files', sprintf('<info>%d</info>', $summary['valid'])],
            ['Invalid Files', $summary['invalid'] > 0 ? sprintf('<error>%d</error>', $summary['invalid']) : '0'],
            ['Total Size', $this->formatBytes($summary['totalSize'])],
        ]);
        $summaryTable->render();

        $io->newLine();

        // Show invalid files if any
        if ($summary['invalid'] > 0) {
            $io->section('Invalid Files Details');

            $detailsTable = new Table($output);
            $detailsTable->setHeaders(['Province', 'Status', 'Message', 'Size']);

            foreach ($results['details'] as $detail) {
                if (! $detail['valid']) {
                    $detailsTable->addRow([
                        $detail['province'],
                        '<error>✗ Invalid</error>',
                        $detail['message'],
                        $detail['fileSize'] !== null ? $this->formatBytes($detail['fileSize']) : 'N/A',
                    ]);
                }
            }

            $detailsTable->render();
        }

        // Final status message
        if ($summary['invalid'] === 0) {
            $io->success('All files are valid!');

            return Command::SUCCESS;
        }

        $io->warning(sprintf('%d file(s) failed validation', $summary['invalid']));

        return Command::FAILURE;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(log($bytes, 1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1024 ** $pow);

        return round($bytes, 2).' '.$units[(int) $pow];
    }
}
