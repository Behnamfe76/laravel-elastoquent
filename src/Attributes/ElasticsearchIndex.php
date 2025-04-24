<?php

namespace Fereydooni\LaravelElastoquent\Attributes;

use Attribute;
use Fereydooni\LaravelElastoquent\Enums\ElasticsearchFieldType;

#[Attribute(Attribute::TARGET_CLASS)]
class ElasticsearchIndex
{
    public function __construct(
        public string $name,
        public ElasticsearchFieldType $idType = ElasticsearchFieldType::KEYWORD,
        public array $settings = [],
        public array $mappings = []
    ) {
    }
} 