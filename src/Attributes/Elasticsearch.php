<?php

namespace Fereydooni\LaravelElastoquent\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Elasticsearch
{
    public function __construct()
    {
    }
} 