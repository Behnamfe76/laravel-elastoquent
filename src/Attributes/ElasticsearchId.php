<?php

namespace Fereydooni\LaravelElastoquent\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ElasticsearchId
{
    public function __construct()
    {
    }
} 