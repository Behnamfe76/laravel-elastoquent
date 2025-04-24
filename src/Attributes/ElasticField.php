<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ElasticField
{
    /**
     * Create a new ElasticField attribute instance.
     *
     * @param string $type The Elasticsearch field type (e.g. text, keyword, integer, etc.)
     * @param bool $index Whether the field should be indexed
     * @param array $options Additional field options
     */
    public function __construct(
        public string $type = 'text',
        public bool $index = true,
        public array $options = [],
    ) {
    }

    /**
     * Get the field mapping configuration.
     *
     * @return array
     */
    public function toMapping(): array
    {
        $mapping = [
            'type' => $this->type,
            'index' => $this->index,
        ];

        return array_merge($mapping, $this->options);
    }
} 