<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Serializer\Normalizer;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webf\FlysystemFailoverBundle\Message\DeleteDirectory;
use Webf\FlysystemFailoverBundle\Message\DeleteFile;
use Webf\FlysystemFailoverBundle\Message\ReplicateFile;
use Webf\FlysystemFailoverBundle\MessageRepository\FindResults;
use Webf\FlysystemFailoverBundle\MessageRepository\MessageWithMetadata;

final class FindResultsNormalizer implements NormalizerInterface
{
    public const SUPPORTED_FORMATS = ['csv', 'json', 'xml'];

    #[\Override]
    public function normalize($data, $format = null, array $context = []): array
    {
        if (!$data instanceof FindResults) {
            throw new InvalidArgumentException(sprintf('The object must be an instance of "%s".', FindResults::class));
        }

        return match ($format) {
            'csv' => $this->formatItems($data->getItems()),
            'json', 'xml' => [
                'limit' => $data->getLimit(),
                'total' => $data->getTotal(),
                'page' => $data->getPage(),
                'items' => $this->formatItems($data->getItems()),
            ],
            default => throw new InvalidArgumentException(sprintf('The format must be one of "%s".', join('", "', self::SUPPORTED_FORMATS))),
        };
    }

    #[\Override]
    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return $data instanceof FindResults && in_array($format, self::SUPPORTED_FORMATS);
    }

    #[\Override]
    public function getSupportedTypes(?string $format): array
    {
        return [FindResults::class => true];
    }

    /**
     * @param iterable<MessageWithMetadata> $items
     *
     * @return array<array>
     */
    private function formatItems(iterable $items): array
    {
        $rows = [];
        foreach ($items as $item) {
            $message = $item->getMessage();

            $rows[] = [
                'adapter' => $message->getFailoverAdapter(),
                'action' => match (get_class($message)) {
                    DeleteDirectory::class => 'delete_directory',
                    DeleteFile::class => 'delete_file',
                    ReplicateFile::class => 'replicate_file',
                    default => throw new InvalidArgumentException('Unsupported message'),
                },
                'path' => $message->getPath(),
                'source' => $message->getInnerSourceAdapter(),
                'destination' => $message->getInnerDestinationAdapter(),
                'retry_count' => $message->getRetryCount(),
                'created_at' => $item->getCreatedAt()->format('c'),
                'available_at' => $item->getAvailableAt()->format('c'),
            ];
        }

        return $rows;
    }
}
