<?php

namespace Fereydooni\LaravelElastoquent\Attributes;

use Attribute;
use Fereydooni\LaravelElastoquent\Enums\ElasticsearchFieldType;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ElasticsearchField
{
    public function __construct(
        public ElasticsearchFieldType $type,
        public ?array $options = null
    ) {
    }

    public function toMapping(): array
    {
        $mapping = ['type' => $this->type->value];

        if ($this->options !== null) {
            $mapping = array_merge($mapping, $this->options);
        }

        return $mapping;
    }
} 