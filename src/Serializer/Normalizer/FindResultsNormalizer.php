<?php

declare(strict_types=1);

namespace Webf\FlysystemFailoverBundle\Serializer\Normalizer;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webf\FlysystemFailoverBundle\Message\DeleteDirectory;
use Webf\FlysystemFailoverBundle\Message\DeleteFile;
use Webf\FlysystemFailoverBundle\Message\ReplicateFile;
use Webf\FlysystemFailoverBundle\MessageRepository\FindResults;
use Webf\FlysystemFailoverBundle\MessageRepository\MessageWithMetadata;

class FindResultsNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    public const SUPPORTED_FORMATS = ['csv', 'json', 'xml'];

    public function normalize($object, string $format = null, array $context = []): array
    {
        if (!$object instanceof FindResults) {
            throw new InvalidArgumentException(sprintf('The object must be an instance of "%s".', FindResults::class));
        }

        return match ($format) {
            'csv' => $this->formatItems($object->getItems()),
            'json', 'xml' => [
                'limit' => $object->getLimit(),
                'total' => $object->getTotal(),
                'page' => $object->getPage(),
                'items' => $this->formatItems($object->getItems()),
            ],
            default => throw new InvalidArgumentException(sprintf('The format must be one of "%s".', join('", "', self::SUPPORTED_FORMATS))),
        };
    }

    public function supportsNormalization($data, string $format = null): bool
    {
        return $data instanceof FindResults && in_array($format, self::SUPPORTED_FORMATS);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
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
                    default => throw new InvalidArgumentException('Unsupported message')
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
