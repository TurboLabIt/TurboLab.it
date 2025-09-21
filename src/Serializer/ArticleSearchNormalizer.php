<?php
namespace App\Serializer;

use App\Entity\Cms\Article as ArticleEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;


class ArticleSearchNormalizer implements NormalizerInterface
{
    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        protected readonly NormalizerInterface $normalizer
    ) {}


    public function getSupportedTypes(?string $format): array
    {
        return [ArticleEntity::class => true];
    }


    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ArticleEntity && in_array('searchable', $context['groups'] ?? [], true);
    }


    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $normalizedData = $this->normalizer->normalize($data, $format, $context);

        $normalizedData["title"]    = static::normalizeForIndexing($normalizedData["title"]);
        $normalizedData["body"]     = static::normalizeForIndexing($normalizedData["body"]);

        return $normalizedData;
    }


    public static function normalizeForIndexing(?string $text) : string
    {
        if( empty($text) ) {
            return '';
        }

        $normalized = strip_tags($text);

        // 👇🏻 the most aggressive version I can think of!
        $normalized = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($normalized);
    }
}
