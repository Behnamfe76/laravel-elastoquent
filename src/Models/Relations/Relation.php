<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Models\Relations;

use Fereydooni\LaravelElastoquent\Models\Model;
use Fereydooni\LaravelElastoquent\Query\Builder;
use Illuminate\Support\Collection;

abstract class Relation
{
    /**
     * The Eloquent query builder instance.
     */
    protected Builder $query;

    /**
     * The parent model instance.
     */
    protected Model $parent;

    /**
     * The related model instance.
     */
    protected Model $related;

    /**
     * Create a new relation instance.
     */
    public function __construct(Builder $query, Model $parent)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $query->getModel();

        $this->addConstraints();
    }

    /**
     * Set the base constraints on the relation query.
     */
    abstract public function addConstraints(): void;

    /**
     * Set the constraints for an eager load of the relation.
     */
    abstract public function addEagerConstraints(array $models): void;

    /**
     * Initialize the relation on a set of models.
     */
    abstract public function initRelation(array $models, string $relation): array;

    /**
     * Match the eagerly loaded results to their parents.
     */
    abstract public function match(array $models, Collection $results, string $relation): array;

    /**
     * Get the results of the relationship.
     */
    abstract public function getResults();

    /**
     * Get the underlying query for the relation.
     */
    public function getQuery(): Builder
    {
        return $this->query;
    }

    /**
     * Get the parent model of the relation.
     */
    public function getParent(): Model
    {
        return $this->parent;
    }

    /**
     * Get the related model of the relation.
     */
    public function getRelated(): Model
    {
        return $this->related;
    }

    /**
     * Get the name of the "created at" column.
     */
    public function createdAt(): string
    {
        return 'created_at';
    }

    /**
     * Get the name of the "updated at" column.
     */
    public function updatedAt(): string
    {
        return 'updated_at';
    }

    /**
     * Get the name of the related model's "deleted at" column.
     */
    public function deletedAt(): string
    {
        return 'deleted_at';
    }
} 