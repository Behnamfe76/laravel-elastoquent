<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Models\Relations;

use Fereydooni\LaravelElastoquent\Models\Model;
use Fereydooni\LaravelElastoquent\Query\Builder;
use Illuminate\Support\Collection;

class HasOne extends Relation
{
    /**
     * Indicates if the default constraints should be applied.
     */
    protected static bool $constraints = true;

    /**
     * The foreign key of the parent model.
     */
    protected string $foreignKey;

    /**
     * The local key of the parent model.
     */
    protected string $localKey;

    /**
     * Create a new has one relation instance.
     */
    public function __construct(Builder $query, Model $parent, string $foreignKey, string $localKey)
    {
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;

        parent::__construct($query, $parent);
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->query->where($this->foreignKey, '=', $this->parent->{$this->localKey});
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        $this->query->whereIn(
            $this->foreignKey,
            $this->getKeys($models, $this->localKey)
        );
    }

    /**
     * Initialize the relation on a set of models.
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     */
    public function match(array $models, Collection $results, string $relation): array
    {
        return $this->matchOne($models, $results, $relation);
    }

    /**
     * Match the eagerly loaded results to their parents.
     */
    protected function matchOne(array $models, Collection $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            if (isset($dictionary[$key = $model->{$this->localKey}])) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }

    /**
     * Get the results of the relationship.
     */
    public function getResults()
    {
        return $this->query->first();
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     */
    protected function buildDictionary(Collection $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->{$this->foreignKey}] = $result;
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