<?php

namespace Fereydooni\LaravelElastoquent\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ElasticsearchProperty
{
    public function __construct(
        public string $type,
        public array $options = []
    ) {
    }
} 