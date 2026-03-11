<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'community-offers:cleanup-door-data',
    description: 'Cleanup old door logs and finished door jobs.',
)]
final class CleanupDoorDataCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('log-days', null, InputOption::VALUE_REQUIRED, 'Keep door logs for this many days', 90)
            ->addOption('job-days', null, InputOption::VALUE_REQUIRED, 'Keep finished jobs for this many days', 30)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show counts without deleting')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logDays = (int) $input->getOption('log-days');
        $jobDays = (int) $input->getOption('job-days');
        $dryRun = (bool) $input->getOption('dry-run');

        $logCutoff = time() - ($logDays * 86400);
        $jobCutoff = time() - ($jobDays * 86400);

        $doorLogCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM tl_co_door_log WHERE tstamp < ?',
            [$logCutoff],
        );

        $jobCount = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM tl_co_door_job
             WHERE createdAt < ?
             AND status IN ('executed','failed','expired')",
            [$jobCutoff],
        );

        $output->writeln("Old door logs: $doorLogCount");
        $output->writeln("Old finished jobs: $jobCount");

        if ($dryRun) {
            $output->writeln('Dry run only.');

            return Command::SUCCESS;
        }

        $deletedLogs = $this->connection->executeStatement(
            'DELETE FROM tl_co_door_log WHERE tstamp < ?',
            [$logCutoff],
        );

        $deletedJobs = $this->connection->executeStatement(
            "DELETE FROM tl_co_door_job
             WHERE createdAt < ?
             AND status IN ('executed','failed','expired')",
            [$jobCutoff],
        );

        $output->writeln("Deleted door logs: $deletedLogs");
        $output->writeln("Deleted jobs: $deletedJobs");

        return Command::SUCCESS;
    }
}
