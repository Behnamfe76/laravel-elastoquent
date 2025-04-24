<?php

namespace Fereydooni\LaravelElastoquent\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ElasticsearchSettings
{
    public function __construct(
        public array $settings = []
    ) {
    }
} 