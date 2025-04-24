<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Models\Concerns;

use Fereydooni\LaravelElastoquent\Models\Model;
use Illuminate\Support\Collection;

trait HasRelations
{
    /**
     * The loaded relationships for the model.
     */
    protected array $relations = [];

    /**
     * The relationships that should be eager loaded.
     */
    protected array $with = [];

    /**
     * Define a one-to-one relationship.
     */
    protected function hasOne(string $related, string $foreignKey = null, string $localKey = null): HasOne
    {
        $instance = new $related;
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->getKeyName();

        return new HasOne($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * Define a one-to-many relationship.
     */
    protected function hasMany(string $related, string $foreignKey = null, string $localKey = null): HasMany
    {
        $instance = new $related;
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->getKeyName();

        return new HasMany($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * Define a belongs-to relationship.
     */
    protected function belongsTo(string $related, string $foreignKey = null, string $ownerKey = null): BelongsTo
    {
        $instance = new $related;
        $foreignKey = $foreignKey ?: $instance->getForeignKey();
        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return new BelongsTo($instance->newQuery(), $this, $foreignKey, $ownerKey);
    }

    /**
     * Define a many-to-many relationship.
     */
    protected function belongsToMany(string $related, string $table = null, string $foreignPivotKey = null, string $relatedPivotKey = null, string $parentKey = null, string $relatedKey = null): BelongsToMany
    {
        $instance = new $related;
        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();
        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();
        $parentKey = $parentKey ?: $this->getKeyName();
        $relatedKey = $relatedKey ?: $instance->getKeyName();

        return new BelongsToMany($instance->newQuery(), $this, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey);
    }

    /**
     * Get a relationship value from a method.
     */
    protected function getRelationValue(string $key)
    {
        $relation = $this->$key();

        if (!$relation instanceof Relation) {
            throw new \LogicException(sprintf('Relationship method must return an instance of Relation, got %s', get_class($relation)));
        }

        return $relation->getResults();
    }

    /**
     * Get all the loaded relations for the instance.
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Get a specified relationship.
     */
    public function getRelation(string $relation)
    {
        return $this->relations[$relation] ?? null;
    }

    /**
     * Set the specific relationship in the model.
     */
    public function setRelation(string $relation, $value): self
    {
        $this->relations[$relation] = $value;
        return $this;
    }

    /**
     * Set the entire relations array on the model.
     */
    public function setRelations(array $relations): self
    {
        $this->relations = $relations;
        return $this;
    }

    /**
     * Get the relationships that should be eager loaded.
     */
    public function getWith(): array
    {
        return $this->with;
    }

    /**
     * Set the relationships that should be eager loaded.
     */
    public function setWith(array $with): self
    {
        $this->with = $with;
        return $this;
    }

    /**
     * Load a set of relationships onto the model.
     */
    public function load(array $relations): self
    {
        $query = $this->newQuery()->with($relations);
        $query->eagerLoadRelations([$this]);
        return $this;
    }

    /**
     * Load a set of relationships onto the model if they are not already eager loaded.
     */
    public function loadMissing(array $relations): self
    {
        $relations = array_filter($relations, function ($relation) {
            return !$this->relationLoaded($relation);
        });

        if (!empty($relations)) {
            $this->load($relations);
        }

        return $this;
    }

    /**
     * Determine if the given relation is loaded.
     */
    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }

    /**
     * Get the default foreign key name for the model.
     */
    public function getForeignKey(): string
    {
        return Str::snake(class_basename($this)) . '_id';
    }

    /**
     * Get the primary key for the model.
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get the value of the model's primary key.
     */
    public function getKey(): ?string
    {
        return $this->getAttribute($this->getKeyName());
    }
} 