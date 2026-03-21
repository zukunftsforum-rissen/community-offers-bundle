<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'community-offers:emulator:create-device',
    description: 'Creates or updates the emulator device and prints a fresh plaintext token.',
)]
final class CreateEmulatorDeviceCommand extends Command
{
    public function __construct(
        private readonly Connection $db,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('deviceId', InputArgument::OPTIONAL, 'Device ID', 'pi-emulator')
            ->addArgument('name', InputArgument::OPTIONAL, 'Device name', 'PI Emulator')
            ->addOption('areas', null, InputOption::VALUE_REQUIRED, 'Comma-separated list of areas', 'workshop,sharing,depot,swap-house')
            ->addOption('print-env-snippet', null, InputOption::VALUE_NONE, 'Print a .env.local snippet with the generated token')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $deviceId = (string) $input->getArgument('deviceId');
        $name = (string) $input->getArgument('name');

        $areasOption = (string) $input->getOption('areas');

        $areas = array_values(array_filter(array_map(
            static fn (string $v): string => trim($v),
            explode(',', $areasOption),
        )));

        if ([] === $areas) {
            $io->error('At least one area is required.');

            return Command::FAILURE;
        }

        $plainToken = bin2hex(random_bytes(24));
        $tokenHash = hash('sha256', $plainToken);

        $now = time();
        $serializedAreas = serialize($areas);

        $existing = $this->db->fetchAssociative(
            'SELECT id FROM tl_co_device WHERE deviceId = :deviceId LIMIT 1',
            ['deviceId' => $deviceId],
        );

        if ($existing) {
            $this->db->update(
                'tl_co_device',
                [
                    'tstamp' => $now,
                    'name' => $name,
                    'enabled' => '1',
                    'isEmulator' => '1',
                    'areas' => $serializedAreas,
                    'apiTokenHash' => $tokenHash,
                ],
                ['id' => (int) $existing['id']],
            );

            $action = 'updated';
            $id = (int) $existing['id'];
        } else {
            $this->db->insert('tl_co_device', [
                'tstamp' => $now,
                'name' => $name,
                'deviceId' => $deviceId,
                'enabled' => '1',
                'isEmulator' => '1',
                'areas' => $serializedAreas,
                'apiTokenHash' => $tokenHash,
                'lastSeen' => 0,
                'ipLast' => '',
            ]);

            $action = 'created';
            $id = (int) $this->db->lastInsertId();
        }

        $io->success(\sprintf('Emulator device %s.', $action));

        $io->table(
            ['Field', 'Value'],
            [
                ['id', (string) $id],
                ['deviceId', $deviceId],
                ['name', $name],
                ['isEmulator', '1'],
                ['enabled', '1'],
                ['areas', implode(', ', $areas)],
            ],
        );

        $io->warning('The plaintext token is shown only once. Store it securely now.');

        $io->writeln('');
        $io->writeln('<info>CO_EMULATOR_TOKEN='.$plainToken.'</info>');
        $io->writeln('');

        if ((bool) $input->getOption('print-env-snippet')) {
            $io->section('.env.local snippet');

            $io->writeln('CO_EMULATOR_BASE_URL=https://your-domain');
            $io->writeln('CO_EMULATOR_DEVICE_ID='.$deviceId);
            $io->writeln('CO_EMULATOR_TOKEN='.$plainToken);
            $io->writeln('CO_EMULATOR_POLL_INTERVAL_SECONDS=2');
        }

        return Command::SUCCESS;
    }
}
