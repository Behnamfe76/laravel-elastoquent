<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Models\Relations;

use Fereydooni\LaravelElastoquent\Models\Model;
use Fereydooni\LaravelElastoquent\Query\Builder;
use Illuminate\Support\Collection;

class BelongsTo extends Relation
{
    /**
     * Indicates if the default constraints should be applied.
     */
    protected static bool $constraints = true;

    /**
     * The foreign key of the child model.
     */
    protected string $foreignKey;

    /**
     * The owner key of the parent model.
     */
    protected string $ownerKey;

    /**
     * Create a new belongs to relation instance.
     */
    public function __construct(Builder $query, Model $child, string $foreignKey, string $ownerKey)
    {
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;

        parent::__construct($query, $child);
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->query->where($this->ownerKey, '=', $this->parent->{$this->foreignKey});
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        $this->query->whereIn(
            $this->ownerKey,
            $this->getKeys($models, $this->foreignKey)
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
        $foreign = $this->foreignKey;

        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->{$this->ownerKey}] = $result;
        }

        foreach ($models as $model) {
            if (isset($dictionary[$model->{$foreign}])) {
                $model->setRelation($relation, $dictionary[$model->{$foreign}]);
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
     * Get the key value of the parent's local key.
     */
    protected function getKeys(array $models, string $key): array
    {
        return array_map(function ($value) use ($key) {
            return $value->{$key};
        }, $models);
    }
} 