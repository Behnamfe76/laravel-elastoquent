<?php

namespace Fereydooni\LaravelElastoquent\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ElasticsearchRelation
{
    public function __construct(
        public string $type,
        public string $related,
        public ?string $foreignKey = null,
        public ?string $localKey = null
    ) {
    }
} 