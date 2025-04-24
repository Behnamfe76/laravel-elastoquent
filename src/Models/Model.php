<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Models;

use Fereydooni\LaravelElastoquent\Attributes\ElasticField;
use Fereydooni\LaravelElastoquent\Attributes\ElasticModel;
use Fereydooni\LaravelElastoquent\Managers\ElasticManager;
use Fereydooni\LaravelElastoquent\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;

abstract class Model
{
    /**
     * The model's attributes.
     */
    protected array $attributes = [];

    /**
     * The model's original attributes.
     */
    protected array $original = [];

    /**
     * The primary key for the model.
     */
    protected string $primaryKey = '_id';

    /**
     * The model's ID.
     */
    protected ?string $id = null;

    /**
     * Indicates if the model exists.
     */
    protected bool $exists = false;

    /**
     * The relations to eager load on every query.
     */
    protected array $with = [];

    /**
     * The elastic manager instance.
     */
    protected static ?ElasticManager $elasticManager = null;

    /**
     * The ElasticModel attribute.
     */
    protected static ?ElasticModel $elasticModel = null;

    /**
     * The mappings for the model.
     */
    protected static array $mappings = [];

    /**
     * Create a new model instance.
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Fill the model with an array of attributes.
     */
    public function fill(array $attributes): self
    {
        // Set the ID if provided
        if (isset($attributes[$this->primaryKey])) {
            $this->setId($attributes[$this->primaryKey]);
            unset($attributes[$this->primaryKey]);
        }
        
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        $this->syncOriginal();

        return $this;
    }

    /**
     * Get the value of the model's primary key.
     */
    public function getKey(): ?string
    {
        return $this->getId();
    }

    /**
     * Get the model's ID.
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set the model's ID.
     */
    public function setId(string $id): self
    {
        $this->id = $id;
        $this->exists = true;

        return $this;
    }

    /**
     * Get all of the model's attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get an attribute from the model.
     */
    public function getAttribute(string $key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        // Check for dynamic property
        if (property_exists($this, $key)) {
            return $this->$key;
        }

        return null;
    }

    /**
     * Set an attribute on the model.
     */
    public function setAttribute(string $key, $value): self
    {
        $this->attributes[$key] = $value;

        // Set property if it exists
        if (property_exists($this, $key)) {
            $this->$key = $value;
        }

        return $this;
    }

    /**
     * Sync the original attributes with the current.
     */
    public function syncOriginal(): self
    {
        $this->original = $this->attributes;

        return $this;
    }

    /**
     * Determine if the model or given attributes have been modified.
     */
    public function isDirty(array $attributes = null): bool
    {
        if ($attributes === null) {
            return $this->attributes !== $this->original;
        }

        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $this->attributes) && 
                (!array_key_exists($attribute, $this->original) || 
                 $this->attributes[$attribute] !== $this->original[$attribute])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the elastic manager instance.
     */
    public static function getElasticManager(): ElasticManager
    {
        if (static::$elasticManager === null) {
            static::$elasticManager = app(ElasticManager::class);
        }

        return static::$elasticManager;
    }

    /**
     * Set the elastic manager instance.
     */
    public static function setElasticManager(ElasticManager $manager): void
    {
        static::$elasticManager = $manager;
    }

    /**
     * Get the index name for the model.
     */
    public static function getIndexName(): string
    {
        $modelAttribute = static::getElasticModelAttribute();
        
        return $modelAttribute->index;
    }

    /**
     * Get the ElasticModel attribute for the model.
     */
    public static function getElasticModelAttribute(): ElasticModel
    {
        if (static::$elasticModel === null) {
            $reflection = new ReflectionClass(static::class);
            $attributes = $reflection->getAttributes(ElasticModel::class, ReflectionAttribute::IS_INSTANCEOF);

            if (empty($attributes)) {
                throw new \RuntimeException(sprintf('The model [%s] must have an ElasticModel attribute', static::class));
            }

            static::$elasticModel = $attributes[0]->newInstance();
        }

        return static::$elasticModel;
    }

    /**
     * Get the mapping for the model.
     */
    public static function getMappings(): array
    {
        if (empty(static::$mappings)) {
            $reflection = new ReflectionClass(static::class);
            $modelAttribute = static::getElasticModelAttribute();
            
            // Start with any mappings defined in the ElasticModel attribute
            $mappings = $modelAttribute->mappings;
            
            // Add properties from ElasticField attributes
            $properties = [];
            
            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                $attributes = $property->getAttributes(ElasticField::class, ReflectionAttribute::IS_INSTANCEOF);
                
                if (!empty($attributes)) {
                    $elasticField = $attributes[0]->newInstance();
                    $properties[$property->getName()] = $elasticField->toMapping();
                }
            }
            
            if (!empty($properties)) {
                $mappings['properties'] = $properties;
            }
            
            static::$mappings = $mappings;
        }
        
        return static::$mappings;
    }

    /**
     * Create a new model instance from Elasticsearch data.
     */
    public static function newFromElasticData(array $data): self
    {
        $model = new static();
        
        // Set ID if present
        if (isset($data['_id'])) {
            $model->setId($data['_id']);
            unset($data['_id']);
        }
        
        $model->fill($data);
        $model->exists = true;
        $model->syncOriginal();
        
        return $model;
    }

    /**
     * Save the model to Elasticsearch.
     */
    public function save(): bool
    {
        $id = $this->getId();
        
        if ($id === null) {
            $id = (string) Str::uuid();
            $this->setId($id);
        }
        
        $data = $this->attributes;
        
        // Add timestamps if necessary
        if (method_exists($this, 'addTimestamps')) {
            $this->addTimestamps();
        }
        
        $result = static::getElasticManager()->index(
            static::getIndexName(),
            $id,
            $data
        );
        
        if ($result) {
            $this->exists = true;
            $this->syncOriginal();
        }
        
        return $result;
    }

    /**
     * Delete the model from Elasticsearch.
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return true;
        }
        
        $id = $this->getId();
        
        if ($id === null) {
            return false;
        }
        
        $softDelete = static::getElasticManager()->getConfig('soft_deletes', true);
        
        if ($softDelete) {
            // Soft delete: set _deleted_at field
            $this->setAttribute('_deleted_at', now()->toIso8601String());
            return $this->save();
        }
        
        // Hard delete
        $result = static::getElasticManager()->delete(
            static::getIndexName(),
            $id
        );
        
        if ($result) {
            $this->exists = false;
        }
        
        return $result;
    }

    /**
     * Get a new query builder for the model.
     */
    public static function query(): Builder
    {
        return (new Builder(static::getElasticManager()))->setModel(static::class);
    }

    /**
     * Begin querying the model.
     */
    public static function where(string $field, $operator = null, $value = null): Builder
    {
        return static::query()->where($field, $operator, $value);
    }

    /**
     * Find a model by its primary key.
     */
    public static function find(string $id): ?self
    {
        $data = static::getElasticManager()->get(
            static::getIndexName(),
            $id
        );
        
        if ($data === null) {
            return null;
        }
        
        return static::newFromElasticData($data);
    }

    /**
     * Create a new model and save it to Elasticsearch.
     */
    public static function create(array $attributes = []): self
    {
        $model = new static($attributes);
        $model->save();
        
        return $model;
    }

    /**
     * Execute a search query and get all results.
     */
    public static function all(): Collection
    {
        return static::query()->get();
    }

    /**
     * Start a search query for all models with the given search term.
     */
    public static function search(string $term): Builder
    {
        return static::query()->search($term);
    }

    /**
     * Bulk index multiple models.
     */
    public static function bulkIndex(array $items): array
    {
        return static::getElasticManager()->bulkIndex(
            static::getIndexName(),
            $items
        );
    }

    /**
     * Create or update the Elasticsearch index for this model.
     */
    public static function createIndex(): bool
    {
        $modelAttribute = static::getElasticModelAttribute();
        
        return static::getElasticManager()->createIndex(
            static::getIndexName(),
            $modelAttribute->settings,
            static::getMappings()
        );
    }

    /**
     * Update the mapping for this model's index.
     */
    public static function updateMapping(): bool
    {
        return static::getElasticManager()->updateMapping(
            static::getIndexName(),
            static::getMappings()
        );
    }

    /**
     * Drop the index for this model.
     */
    public static function dropIndex(): bool
    {
        return static::getElasticManager()->deleteIndex(
            static::getIndexName()
        );
    }

    /**
     * Magic method for retrieving a property value.
     */
    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Magic method for setting a property value.
     */
    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Magic method to check if a property is set.
     */
    public function __isset(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Convert the model to an array.
     */
    public function toArray(): array
    {
        $array = $this->attributes;
        
        if ($this->id !== null) {
            $array[$this->primaryKey] = $this->id;
        }
        
        return $array;
    }

    /**
     * Determine if two models are the same.
     */
    public function is(self $model): bool
    {
        return $this->getId() === $model->getId() && 
               static::class === get_class($model) &&
               static::getIndexName() === $model::getIndexName();
    }
} 