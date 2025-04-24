<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ElasticField
{
    /**
     * The field type.
     */
    public string $type;

    /**
     * Additional mapping options.
     */
    public array $options;

    /**
     * Create a new ElasticField instance.
     */
    public function __construct(string $type = 'text', array $options = [])
    {
        $this->type = $type;
        $this->options = $options;
    }

    /**
     * Create a text field.
     */
    public static function text(array $options = []): self
    {
        return new self('text', $options);
    }

    /**
     * Create a keyword field.
     */
    public static function keyword(array $options = []): self
    {
        return new self('keyword', $options);
    }

    /**
     * Create a date field.
     */
    public static function date(array $options = []): self
    {
        return new self('date', $options);
    }

    /**
     * Create a boolean field.
     */
    public static function boolean(array $options = []): self
    {
        return new self('boolean', $options);
    }

    /**
     * Create a long field.
     */
    public static function long(array $options = []): self
    {
        return new self('long', $options);
    }

    /**
     * Create a double field.
     */
    public static function double(array $options = []): self
    {
        return new self('double', $options);
    }

    /**
     * Create an object field.
     */
    public static function object(array $options = []): self
    {
        return new self('object', $options);
    }

    /**
     * Create a nested field.
     */
    public static function nested(array $options = []): self
    {
        return new self('nested', $options);
    }

    /**
     * Create a geo_point field.
     */
    public static function geoPoint(array $options = []): self
    {
        return new self('geo_point', $options);
    }

    /**
     * Create a dense_vector field.
     */
    public static function denseVector(array $options = []): self
    {
        return new self('dense_vector', $options);
    }

    /**
     * Create a sparse_vector field.
     */
    public static function sparseVector(array $options = []): self
    {
        return new self('sparse_vector', $options);
    }

    /**
     * Convert the field to a mapping array.
     */
    public function toMapping(): array
    {
        $mapping = ['type' => $this->type];

        return array_merge($mapping, $this->options);
    }
} 