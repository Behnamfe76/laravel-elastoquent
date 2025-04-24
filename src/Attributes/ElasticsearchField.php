<?php

namespace Fereydooni\LaravelElastoquent\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ElasticsearchField
{
    public function __construct(
        public string $type,
        public array $options = []
    ) {
    }
} 