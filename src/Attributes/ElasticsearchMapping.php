<?php

namespace Fereydooni\LaravelElastoquent\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ElasticsearchMapping
{
    public function __construct(
        public array $properties = []
    ) {
    }
} 