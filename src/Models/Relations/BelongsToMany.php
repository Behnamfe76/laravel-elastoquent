<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Models\Relations;

use Fereydooni\LaravelElastoquent\Models\Model;
use Fereydooni\LaravelElastoquent\Query\Builder;
use Illuminate\Support\Collection;

class BelongsToMany extends Relation
{
    /**
     * Indicates if the default constraints should be applied.
     */
    protected static bool $constraints = true;

    /**
     * The intermediate table for the relation.
     */
    protected string $table;

    /**
     * The foreign key of the parent model.
     */
    protected string $foreignPivotKey;

    /**
     * The associated key of the relation.
     */
    protected string $relatedPivotKey;

    /**
     * The key name of the parent model.
     */
    protected string $parentKey;

    /**
     * The key name of the related model.
     */
    protected string $relatedKey;

    /**
     * Create a new belongs to many relation instance.
     */
    public function __construct(Builder $query, Model $parent, string $table, string $foreignPivotKey, string $relatedPivotKey, string $parentKey, string $relatedKey)
    {
        $this->table = $table;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;

        parent::__construct($query, $parent);
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->query->where($this->relatedPivotKey, '=', $this->parent->{$this->parentKey});
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        $this->query->whereIn(
            $this->relatedPivotKey,
            $this->getKeys($models, $this->parentKey)
        );
    }

    /**
     * Initialize the relation on a set of models.
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     */
    public function match(array $models, Collection $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            if (isset($dictionary[$key = $model->{$this->parentKey}])) {
                $model->setRelation($relation, $this->related->newCollection($dictionary[$key]));
            }
        }

        return $models;
    }

    /**
     * Get the results of the relationship.
     */
    public function getResults()
    {
        return $this->query->get();
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     */
    protected function buildDictionary(Collection $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->{$this->relatedPivotKey}][] = $result;
        }

        return $dictionary;
    }

    /**
     * Get the key value of the parent's local key.
     */
    protected function getKeys(array $models, string $key): array
    {
        return array_map(function ($value) use ($key) {
            return $value->{$key};
        }, $models);
    }
} 