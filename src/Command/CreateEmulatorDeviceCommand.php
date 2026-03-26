<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'community-offers:emulator:create-device',
    description: 'Creates or updates an emulator device and generates a token.',
)]
final class CreateEmulatorDeviceCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework $framework,
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
            ->addOption('write-token-file', null, InputOption::VALUE_REQUIRED, 'Write the plaintext token to the given file')
            ->addOption('write-default-token-file', null, InputOption::VALUE_NONE, 'Write the plaintext token to var/device-tokens/<deviceId>.token')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->framework->initialize();
        $io = new SymfonyStyle($input, $output);

        $deviceId = trim((string) $input->getArgument('deviceId'));
        $name = trim((string) $input->getArgument('name'));
        $areasOption = (string) $input->getOption('areas');

        if ('' === $deviceId) {
            $io->error('Device ID must not be empty.');

            return Command::FAILURE;
        }

        if ('' === $name) {
            $io->error('Device name must not be empty.');

            return Command::FAILURE;
        }

        $allowedAreas = ['workshop', 'sharing', 'depot', 'swap-house'];
        $areas = array_values(array_filter(array_map(
            static fn (string $area): string => trim($area),
            explode(',', $areasOption),
        )));

        foreach ($areas as $area) {
            if (!\in_array($area, $allowedAreas, true)) {
                $io->error(\sprintf(
                    'Invalid area "%s". Allowed areas: %s',
                    $area,
                    implode(', ', $allowedAreas),
                ));

                return Command::FAILURE;
            }
        }

        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        $db = Database::getInstance();

        $existing = $db
            ->prepare('SELECT id FROM tl_co_device WHERE deviceId=? LIMIT 1')
            ->execute($deviceId)
        ;

        $serializedAreas = serialize($areas);
        $now = time();

        if ($existing->numRows > 0) {
            /** @var array<string, mixed> $row */
            $row = $existing->row();

            $devicePk = (int) ($row['id'] ?? 0);

            if ($devicePk <= 0) {
                $io->error(\sprintf(
                    'Could not resolve primary key for device "%s".',
                    $deviceId,
                ));

                return Command::FAILURE;
            }

            $db
                ->prepare('
                    UPDATE tl_co_device
                    SET tstamp=?,
                        name=?,
                        enabled=?,
                        isEmulator=?,
                        apiTokenHash=?,
                        areas=?
                    WHERE id=?
                ')
                ->execute(
                    $now,
                    $name,
                    '1',
                    '1',
                    $tokenHash,
                    $serializedAreas,
                    $devicePk,
                )
            ;

            $io->success(\sprintf('Updated emulator device "%s".', $deviceId));
        } else {
            $db
                ->prepare('
                    INSERT INTO tl_co_device
                    (tstamp, deviceId, name, enabled, isEmulator, apiTokenHash, areas)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ')
                ->execute(
                    $now,
                    $deviceId,
                    $name,
                    '1',
                    '1',
                    $tokenHash,
                    $serializedAreas,
                )
            ;

            $io->success(\sprintf('Created emulator device "%s".', $deviceId));
        }

        $io->section('Generated token');
        $io->writeln($plainToken);

        if ((bool) $input->getOption('print-env-snippet')) {
            $io->section('.env.local snippet');
            $io->writeln(\sprintf('PI_EMULATOR_DEVICE_ID=%s', $deviceId));
            $io->writeln(\sprintf('PI_EMULATOR_DEVICE_TOKEN=%s', $plainToken));
        }

        $tokenFile = null;

        if ((bool) $input->getOption('write-default-token-file')) {
            $tokenFile = \sprintf('var/device-tokens/%s.token', $deviceId);
        }

        $explicitTokenFile = $input->getOption('write-token-file');
        if (\is_string($explicitTokenFile) && '' !== trim($explicitTokenFile)) {
            $tokenFile = trim($explicitTokenFile);
        }

        if (null !== $tokenFile) {
            $dir = \dirname($tokenFile);

            if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
                $io->error(\sprintf('Could not create directory "%s".', $dir));

                return Command::FAILURE;
            }

            if (false === file_put_contents($tokenFile, $plainToken."\n")) {
                $io->error(\sprintf('Could not write token file "%s".', $tokenFile));

                return Command::FAILURE;
            }

            @chmod($tokenFile, 0640);

            $io->success(\sprintf('Token file written to %s', $tokenFile));
        }

        return Command::SUCCESS;
    }
}
