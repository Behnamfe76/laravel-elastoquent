<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ElasticModel
{
    /**
     * Create a new ElasticModel attribute instance.
     *
     * @param string $index The Elasticsearch index name
     * @param array $settings The index settings (e.g. number_of_shards, number_of_replicas)
     * @param array $mappings The index mappings for fields not defined with ElasticField attributes
     */
    public function __construct(
        public string $index,
        public array $settings = [],
        public array $mappings = [],
    ) {
    }
} 