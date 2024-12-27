<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webf\FlysystemFailoverBundle\MessageHandler\MessageHandlerLocator;
use Webf\FlysystemFailoverBundle\MessageRepository\MessageRepositoryInterface;

class ProcessMessagesCommand extends Command
{
    protected static string $defaultName = 'webf:flysystem-failover:process-messages';

    public function __construct(
        private MessageHandlerLocator $messageHandlerLocator,
        private MessageRepositoryInterface $messageRepository,
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        /** @var string|null $limit */
        $limit = $input->getOption('limit');

        $io = new SymfonyStyle($input, $output);
        $io->comment(sprintf(
            'Ready to process %s message%s.',
            null === $limit ? 'an unlimited number of' : (int) $limit,
            null === $limit || ((int) $limit) > 1 ? 's' : ''
        ));

        if (null === $limit) {
            while (true) {
                $this->handleMessage($io);
            }
        }

        $limit = (int) $limit;
        while ($limit-- > 0) {
            $this->handleMessage($io);
        }

        return 0;
    }

    private function handleMessage(SymfonyStyle $io): void
    {
        $message = $this->messageRepository->pop();

        while (null === $message) {
            usleep(1_000_000);
            $message = $this->messageRepository->pop();
        }

        $handler = $this->messageHandlerLocator->getHandlerForMessage($message);

        $io->text($message->__toString());
        $handler($message);
        $io->success('Message successfully processed');
    }

    protected function configure(): void
    {
        $this->addOption(
            'limit',
            'l',
            InputOption::VALUE_REQUIRED,
            'Limit the number of messages to process',
        );

        $this->setDescription('Process messages in the repository');
    }
}
