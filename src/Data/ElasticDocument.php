<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Illuminate\Support\Collection;

class ElasticDocument extends Data
{
    /**
     * Create a new ElasticDocument instance.
     *
     * @param string $id The document ID
     * @param float|null $score The document score
     * @param array $source The document source
     * @param array $metadata Any additional metadata
     */
    public function __construct(
        public string $id,
        public float|null $score,
        public array $source,
        public array $metadata = [],
    ) {
    }

    /**
     * Create a new ElasticDocument from Elasticsearch hit data.
     *
     * @param array $hit The Elasticsearch hit data
     * @return static
     */
    public static function fromElasticsearchHit(array $hit): static
    {
        return new static(
            id: $hit['_id'] ?? '',
            score: $hit['_score'] ?? null,
            source: $hit['_source'] ?? [],
            metadata: array_diff_key($hit, array_flip(['_id', '_score', '_source'])),
        );
    }

    /**
     * Get a specific field from the source.
     *
     * @param string $field The field name
     * @param mixed $default The default value if field doesn't exist
     * @return mixed The field value
     */
    public function field(string $field, mixed $default = null): mixed
    {
        return $this->source[$field] ?? $default;
    }

    /**
     * Convert to array with a flattened structure.
     *
     * @return array
     */
    public function toFlatArray(): array
    {
        return array_merge(
            ['_id' => $this->id, '_score' => $this->score],
            $this->source,
            $this->metadata
        );
    }
} 