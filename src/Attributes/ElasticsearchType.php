<?php

namespace Fereydooni\LaravelElastoquent\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ElasticsearchType
{
    public function __construct(
        public string $name
    ) {
    }
} 