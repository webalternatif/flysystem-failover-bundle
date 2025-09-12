<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Webf\FlysystemFailoverBundle\Event\SyncService\DeleteFileMessageDispatched;
use Webf\FlysystemFailoverBundle\Event\SyncService\DeleteFileMessagePreDispatch;
use Webf\FlysystemFailoverBundle\Event\SyncService\ListingContentFailed;
use Webf\FlysystemFailoverBundle\Event\SyncService\ListingContentStarted;
use Webf\FlysystemFailoverBundle\Event\SyncService\ListingContentSucceeded;
use Webf\FlysystemFailoverBundle\Event\SyncService\ReplicateFileMessageDispatched;
use Webf\FlysystemFailoverBundle\Event\SyncService\ReplicateFileMessagePreDispatch;
use Webf\FlysystemFailoverBundle\Event\SyncService\SearchingFilesToReplicateStarted;
use Webf\FlysystemFailoverBundle\Flysystem\FailoverAdaptersLocatorInterface;
use Webf\FlysystemFailoverBundle\Service\SyncService;

final class SyncCommand extends Command
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private FailoverAdaptersLocatorInterface $failoverAdaptersLocator,
        private SyncService $syncService,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        /** @var string $adapterName */
        $adapterName = $input->hasArgument('adapter')
            ? $input->getArgument('adapter')
            : array_keys(iterator_to_array($this->failoverAdaptersLocator))[0];

        /** @var string $extraFilesStrategy */
        $extraFilesStrategy = $input->getOption('extra-files');

        $ignoreModificationDates = (bool) $input->getOption('ignore-modification-dates');

        $io = new SymfonyStyle($input, $output);

        $stats = new class() {
            /** @var array<int, int> */
            private array $nbReplicatedMap = [];

            /** @var array<int, int> */
            private array $nbDeletedMap = [];

            public function incrReplicated(int $adapter): void
            {
                if (!key_exists($adapter, $this->nbReplicatedMap)) {
                    $this->nbReplicatedMap[$adapter] = 0;
                }

                ++$this->nbReplicatedMap[$adapter];
            }

            public function incrDeleted(int $adapter): void
            {
                if (!key_exists($adapter, $this->nbDeletedMap)) {
                    $this->nbDeletedMap[$adapter] = 0;
                }

                ++$this->nbDeletedMap[$adapter];
            }

            public function totalReplicated(): int
            {
                return array_sum($this->nbReplicatedMap);
            }

            public function totalDeleted(): int
            {
                return array_sum($this->nbDeletedMap);
            }

            public function getTableLines(bool $showDeleted): array
            {
                return array_filter(
                    array_map(
                        fn (int $key) => array_merge(
                            [$key, $this->nbReplicatedMap[$key] ?? 0],
                            $showDeleted
                                ? [$this->nbDeletedMap[$key] ?? 0]
                                : [],
                        ),
                        array_keys($this->nbReplicatedMap + $this->nbDeletedMap),
                    ),
                    fn ($line) => $line[1] + ($line[2] ?? 0) > 0
                );
            }
        };

        $this->eventDispatcher->addListener(
            ListingContentStarted::class,
            function (ListingContentStarted $event) use ($io) {
                $io->write(sprintf(
                    'Listing content of storage %s...',
                    $event->getInnerAdapter()
                ));
            }
        );

        $this->eventDispatcher->addListener(
            ListingContentSucceeded::class,
            function (ListingContentSucceeded $event) use ($io) {
                $io->writeln(sprintf(
                    ' <info>done (%s item%s fetched)</info>',
                    $event->getNbItems(),
                    $event->getNbItems() > 1 ? 's' : ''
                ));
            }
        );

        $this->eventDispatcher->addListener(
            ListingContentFailed::class,
            function () use ($io) {
                $io->writeln(' <error>failed</error>');
            }
        );

        $this->eventDispatcher->addListener(
            SearchingFilesToReplicateStarted::class,
            function () use ($io) {
                $io->newLine();
                $io->writeln('Searching files to replicate...');
                $io->newLine();
            }
        );

        $this->eventDispatcher->addListener(
            ReplicateFileMessagePreDispatch::class,
            function (ReplicateFileMessagePreDispatch $event) use ($io, $stats) {
                $message = $event->getMessage();

                $io->write(sprintf(
                    'Dispatching message to replicate file ' .
                    '<comment>%s</comment> from storage %s to %s...',
                    $message->getPath(),
                    $message->getInnerSourceAdapter(),
                    $message->getInnerDestinationAdapter(),
                ));

                $stats->incrReplicated($message->getInnerDestinationAdapter());
            }
        );

        $this->eventDispatcher->addListener(
            ReplicateFileMessageDispatched::class,
            function () use ($io) {
                $io->writeln(' <info>done</info>');
            }
        );

        $this->eventDispatcher->addListener(
            DeleteFileMessagePreDispatch::class,
            function (DeleteFileMessagePreDispatch $event) use ($io, $stats) {
                $io->write(sprintf(
                    'Dispatching message to delete file ' .
                    '<comment>%s</comment> from storage %s...',
                    $event->getMessage()->getPath(),
                    $event->getMessage()->getInnerDestinationAdapter(),
                ));

                $stats->incrDeleted($event->getMessage()->getInnerDestinationAdapter());
            }
        );

        $this->eventDispatcher->addListener(
            DeleteFileMessageDispatched::class,
            function () use ($io) {
                $io->writeln(' <info>done</info>');
            }
        );

        $this->syncService->sync(
            $adapterName,
            $extraFilesStrategy,
            $ignoreModificationDates
        );

        $nbReplicated = $stats->totalReplicated();
        $nbDeleted = $stats->totalDeleted();

        $io->success(sprintf(
            'Storages are synced, %s file%s has been replicated%s.',
            0 === $nbReplicated ? 'no' : $nbReplicated,
            $nbReplicated > 1 ? 's' : '',
            SyncService::EXTRA_FILES_DELETE === $extraFilesStrategy ? sprintf(
                ' and %s file%s has been deleted',
                0 === $nbDeleted ? 'no' : $nbDeleted,
                $nbDeleted > 1 ? 's' : '',
            ) : ''
        ));

        if ($nbReplicated > 0 || $nbDeleted > 0) {
            $io->table(
                array_merge(
                    ['Storage', 'Replicated files'],
                    SyncService::EXTRA_FILES_DELETE === $extraFilesStrategy
                        ? ['Deleted files']
                        : [],
                ),
                $stats->getTableLines(
                    SyncService::EXTRA_FILES_DELETE === $extraFilesStrategy
                )
            );
        }

        return 0;
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('webf:flysystem-failover:sync')
            ->setDescription(
                'Synchronize storages to replicate all files present in the ' .
                'first one to the others'
            )
        ;

        $adapters = array_keys(
            iterator_to_array($this->failoverAdaptersLocator)
        );

        if (count($adapters) > 1) {
            $this->addArgument(
                'adapter',
                InputArgument::REQUIRED,
                'Name of the failover adapter for which to scan the ' .
                'underlaying storages' .
                sprintf(
                    ' (one of <comment>"%s"</comment>)',
                    join('"</comment>, <comment>"', $adapters)
                )
            );
        }

        $this->addOption(
            'extra-files',
            mode: InputOption::VALUE_OPTIONAL,
            description: sprintf(
                'How to handle extra files in secondary storages. One of ' .
                '<comment>"%s"</comment>.',
                join('"</comment>, <comment>"', [
                    SyncService::EXTRA_FILES_IGNORE,
                    SyncService::EXTRA_FILES_DELETE,
                    SyncService::EXTRA_FILES_COPY,
                ])
            ),
            default: SyncService::EXTRA_FILES_IGNORE
        );

        $this->addOption(
            'ignore-modification-dates',
            mode: InputOption::VALUE_NONE,
            description: 'Do not replicate files already present in ' .
                'secondary storages, even if the modification date is older.'
        );
    }
}
