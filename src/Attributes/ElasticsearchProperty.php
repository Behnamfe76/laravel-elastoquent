<?php

namespace Fereydooni\LaravelElastoquent\Attributes;

use Attribute;
use Fereydooni\LaravelElastoquent\Enums\ElasticsearchFieldType;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ElasticsearchProperty
{
    public function __construct(
        public ElasticsearchFieldType $type,
        public array $options = []
    ) {
    }
} 