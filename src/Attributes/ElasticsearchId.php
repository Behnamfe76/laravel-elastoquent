<?php

namespace Fereydooni\LaravelElastoquent\Attributes;

use Attribute;
use Fereydooni\LaravelElastoquent\Enums\ElasticsearchFieldType;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ElasticsearchId
{
    public function __construct(
        public ElasticsearchFieldType $type = ElasticsearchFieldType::KEYWORD,
        public array $options = []
    ) {
    }
} 