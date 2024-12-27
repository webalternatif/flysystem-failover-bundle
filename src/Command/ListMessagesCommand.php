<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use Webf\FlysystemFailoverBundle\Flysystem\FailoverAdaptersLocatorInterface;
use Webf\FlysystemFailoverBundle\Message\DeleteDirectory;
use Webf\FlysystemFailoverBundle\Message\DeleteFile;
use Webf\FlysystemFailoverBundle\Message\ReplicateFile;
use Webf\FlysystemFailoverBundle\MessageRepository\FindByCriteria;
use Webf\FlysystemFailoverBundle\MessageRepository\MessageRepositoryInterface;
use Webf\FlysystemFailoverBundle\Serializer\Normalizer\FindResultsNormalizer;

class ListMessagesCommand extends Command
{
    public function __construct(
        private FailoverAdaptersLocatorInterface $failoverAdaptersLocator,
        private MessageRepositoryInterface $messageRepository,
        private ?SerializerInterface $serializer,
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $criteria = new FindByCriteria();

        if ($input->hasOption('adapter')) {
            $criteria->setFailoverAdapter(
                $this->getStringOption($input, 'adapter')
            );
        }

        $criteria
            ->setLimit($this->getIntOption($input, 'limit'))
            ->setPage($this->getIntOption($input, 'page'))
        ;

        $results = $this->messageRepository->findBy($criteria);

        if (null !== $this->serializer) {
            if (null !== ($format = $this->getStringOption($input, 'format'))) {
                $context = $input->getOption('pretty') ? [
                    JsonEncode::OPTIONS => JSON_PRETTY_PRINT,
                    XmlEncoder::FORMAT_OUTPUT => true,
                ] : [];

                $output->writeln($this->serializer->serialize($results, $format, $context));

                return 0;
            }
        }

        $io = new SymfonyStyle($input, $output);
        $showFailoverAdapterColumn = $input->hasOption('adapter')
            && null !== $criteria->getFailoverAdapter();

        $headers = [
            'Adapter',
            'Action',
            'Path',
            'Source',
            'Destination',
            'Retry count',
            'Creation date',
            'Availability date',
        ];

        if (!$showFailoverAdapterColumn) {
            unset($headers[0]);
        }

        $rows = [];
        foreach ($results->getItems() as $item) {
            $message = $item->getMessage();

            $row = [
                $message->getFailoverAdapter(),
                match (get_class($message)) {
                    DeleteDirectory::class => 'Delete directory',
                    DeleteFile::class => 'Delete file',
                    ReplicateFile::class => 'Replicate file',
                    default => throw new InvalidArgumentException('Unsupported message'),
                },
                $message->getPath(),
                $message->getInnerSourceAdapter(),
                $message->getInnerDestinationAdapter(),
                $message->getRetryCount(),
                $item->getCreatedAt()->format('c'),
                $item->getAvailableAt()->format('c'),
            ];

            if (!$showFailoverAdapterColumn) {
                unset($row[0]);
            }

            $rows[] = $row;
        }

        $nbItems = count($rows);

        $io->table($headers, $rows);

        if ($nbItems === $results->getTotal()) {
            $io->text(sprintf(
                'Displayed all of %s items.',
                $results->getTotal()
            ));
        } else {
            $firstItemNb = $results->getFirstItemNb();
            $lastItemNb = $firstItemNb + $nbItems - 1;

            $io->text(sprintf(
                'Displayed %s of %s items (page %s/%s).',
                $firstItemNb <= $lastItemNb
                    ? sprintf('%s-%s', $firstItemNb, $lastItemNb)
                    : 'none',
                $results->getTotal(),
                $results->getPage(),
                $results->getTotalPages(),
            ));
        }

        return 0;
    }

    protected function configure(): void
    {
        $this
            ->setName('webf:flysystem-failover:list-messages')
            ->setDescription('List messages of the repository')
        ;

        $adapters = array_keys(
            iterator_to_array($this->failoverAdaptersLocator)
        );

        if (count($adapters) > 1) {
            $this->addOption(
                'adapter',
                'a',
                InputOption::VALUE_REQUIRED,
                'Name of the failover adapter for which to list messages ' .
                sprintf(
                    ' (one of <comment>"%s"</comment>)',
                    join('"</comment>, <comment>"', $adapters)
                )
            );
        }

        $this->addOption(
            'limit',
            'l',
            InputOption::VALUE_REQUIRED,
            'Maximum number of items to display',
            FindByCriteria::DEFAULT_LIMIT
        );

        $this->addOption(
            'page',
            'p',
            InputOption::VALUE_REQUIRED,
            'Page of items to display',
            FindByCriteria::DEFAULT_PAGE
        );

        if (null !== $this->serializer) {
            $this->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                sprintf(
                    'Output format (one of <comment>"%s"</comment>)',
                    join('"</comment>, <comment>"', FindResultsNormalizer::SUPPORTED_FORMATS)
                )
            );

            $this->addOption(
                'pretty',
                null,
                InputOption::VALUE_NONE,
                'Pretty print json and xml format outputs'
            );
        }
    }

    private function getStringOption(InputInterface $input, string $option): ?string
    {
        if (!is_string($value = $input->getOption($option))) {
            return null;
        }

        return $value;
    }

    private function getIntOption(InputInterface $input, string $option): ?int
    {
        if (null === ($value = $this->getStringOption($input, $option))) {
            return null;
        }

        return (int) $value;
    }
}
