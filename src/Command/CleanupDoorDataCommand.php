<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService;

#[AsCommand(
    name: 'community-offers:cleanup-door-data',
    description: 'Expires stale active jobs and deletes old door logs and old finished jobs.',
)]
final class CleanupDoorDataCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggingService $logging,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('log-days', null, InputOption::VALUE_REQUIRED, 'Keep door logs for this many days', '90')
            ->addOption('job-days', null, InputOption::VALUE_REQUIRED, 'Keep executed and expired jobs for this many days', '30')
            ->addOption('failed-job-days', null, InputOption::VALUE_REQUIRED, 'Keep failed jobs for this many days', '180')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be changed without deleting anything')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logDays = max(1, (int) $input->getOption('log-days'));
        $jobDays = max(1, (int) $input->getOption('job-days'));
        $failedJobDays = max(1, (int) $input->getOption('failed-job-days'));
        $dryRun = (bool) $input->getOption('dry-run');
        $now = time();

        $logCutoff = $now - ($logDays * 86400);
        $jobCutoff = $now - ($jobDays * 86400);
        $failedJobCutoff = $now - ($failedJobDays * 86400);

        $stalePendingCount = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM tl_co_door_job
             WHERE expiresAt > 0
               AND expiresAt < ?
               AND status = 'pending'",
            [$now],
        );

        $staleDispatchedCount = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM tl_co_door_job
             WHERE expiresAt > 0
               AND expiresAt < ?
               AND status = 'dispatched'",
            [$now],
        );

        $doorLogCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM tl_co_door_log WHERE tstamp < ?',
            [$logCutoff],
        );

        $oldExecutedExpiredCount = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM tl_co_door_job
             WHERE createdAt > 0
               AND createdAt < ?
               AND status IN ('executed', 'expired')",
            [$jobCutoff],
        );

        $oldFailedCount = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM tl_co_door_job
             WHERE createdAt > 0
               AND createdAt < ?
               AND status = 'failed'",
            [$failedJobCutoff],
        );

        $output->writeln(\sprintf('Stale pending jobs to expire: %d', $stalePendingCount));
        $output->writeln(\sprintf('Stale dispatched jobs to expire: %d', $staleDispatchedCount));
        $output->writeln(\sprintf('Door logs older than %d days: %d', $logDays, $doorLogCount));
        $output->writeln(\sprintf('Executed/expired jobs older than %d days: %d', $jobDays, $oldExecutedExpiredCount));
        $output->writeln(\sprintf('Failed jobs older than %d days: %d', $failedJobDays, $oldFailedCount));

        if ($dryRun) {
            $output->writeln('Dry run only, nothing changed.');

            return Command::SUCCESS;
        }

        $expiredPending = $this->connection->executeStatement(
            "UPDATE tl_co_door_job
             SET status = 'expired'
             WHERE expiresAt > 0
               AND expiresAt < ?
               AND status = 'pending'",
            [$now],
        );

        $expiredDispatched = $this->connection->executeStatement(
            "UPDATE tl_co_door_job
             SET status = 'expired'
             WHERE expiresAt > 0
               AND expiresAt < ?
               AND status = 'dispatched'",
            [$now],
        );

        $deletedDoorLogs = $this->connection->executeStatement(
            'DELETE FROM tl_co_door_log WHERE tstamp < ?',
            [$logCutoff],
        );

        $deletedExecutedExpiredJobs = $this->connection->executeStatement(
            "DELETE FROM tl_co_door_job
             WHERE createdAt > 0
               AND createdAt < ?
               AND status IN ('executed', 'expired')",
            [$jobCutoff],
        );

        $deletedFailedJobs = $this->connection->executeStatement(
            "DELETE FROM tl_co_door_job
             WHERE createdAt > 0
               AND createdAt < ?
               AND status = 'failed'",
            [$failedJobCutoff],
        );

        $this->logging->initiateLogging('door', 'community-offers');
        $this->logging->info('door_cleanup.completed', [
            'expiredPending' => $expiredPending,
            'expiredDispatched' => $expiredDispatched,
            'deletedDoorLogs' => $deletedDoorLogs,
            'deletedExecutedExpiredJobs' => $deletedExecutedExpiredJobs,
            'deletedFailedJobs' => $deletedFailedJobs,
            'logDays' => $logDays,
            'jobDays' => $jobDays,
            'failedJobDays' => $failedJobDays,
        ]);

        $output->writeln(\sprintf('Expired pending jobs: %d', $expiredPending));
        $output->writeln(\sprintf('Expired dispatched jobs: %d', $expiredDispatched));
        $output->writeln(\sprintf('Deleted door logs: %d', $deletedDoorLogs));
        $output->writeln(\sprintf('Deleted executed/expired jobs: %d', $deletedExecutedExpiredJobs));
        $output->writeln(\sprintf('Deleted failed jobs: %d', $deletedFailedJobs));

        return Command::SUCCESS;
    }
}
