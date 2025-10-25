<?php

declare(strict_types=1);

namespace Farzai\ThailandAddress\Console\Commands;

use Farzai\ThailandAddress\Service\ProvinceScraperService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'list',
    description: 'List all Thai provinces with their resource IDs',
)]
final class ListCommand extends Command
{
    private ProvinceScraperService $scraperService;

    public function __construct()
    {
        parent::__construct();
        $this->scraperService = new ProvinceScraperService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Thai Provinces List');
        $io->text('Fetching province data from government portal...');

        try {
            $provinces = $this->scraperService->scrapeProvinces();

            if (count($provinces) === 0) {
                $io->warning('No provinces found!');

                return Command::FAILURE;
            }

            // Create table
            $table = new Table($output);
            $table->setHeaders(['#', 'Province Name', 'Thai Name', 'Resource ID']);

            foreach ($provinces as $index => $province) {
                assert(is_int($index));
                $table->addRow([
                    $index + 1,
                    $province->name,
                    $province->thaiName,
                    $province->resourceId,
                ]);
            }

            $table->render();

            $io->success(sprintf('Found %d provinces', count($provinces)));

            return Command::SUCCESS;
        } catch (\RuntimeException $e) {
            $io->error('Failed to fetch province data: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
