<?php

namespace Fereydooni\LaravelElastoquent\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ElasticsearchIndex
{
    public function __construct(
        public string $name,
        public array $settings = [],
        public array $mappings = []
    ) {
    }
} 