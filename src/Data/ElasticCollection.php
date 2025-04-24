<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Data;

use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\DataCollection;

class ElasticCollection extends Collection
{
    /**
     * The total number of results available.
     */
    protected int $total;

    /**
     * The aggregations returned from the search.
     */
    protected array $aggregations;

    /**
     * The time taken for the search query.
     */
    protected ?float $executionTime = null;

    /**
     * Create a new ElasticCollection instance.
     *
     * @param mixed $items The collection items
     * @param int $total The total count of results
     * @param array $aggregations The search aggregations
     * @param float|null $executionTime The execution time in ms
     */
    public function __construct($items = [], int $total = 0, array $aggregations = [], ?float $executionTime = null)
    {
        parent::__construct($items);
        $this->total = $total;
        $this->aggregations = $aggregations;
        $this->executionTime = $executionTime;
    }

    /**
     * Create a new collection from Elasticsearch search results.
     *
     * @param array $searchResult The Elasticsearch search result
     * @param string $dataClass The data class to use for items (defaults to ElasticDocument)
     * @return static
     */
    public static function fromSearchResult(array $searchResult, string $dataClass = ElasticDocument::class): static
    {
        $items = [];
        $total = $searchResult['hits']['total']['value'] ?? 0;
        $aggregations = $searchResult['aggregations'] ?? [];
        $executionTime = $searchResult['took'] ?? null;

        if (isset($searchResult['hits']['hits'])) {
            foreach ($searchResult['hits']['hits'] as $hit) {
                if ($dataClass === ElasticDocument::class) {
                    $items[] = ElasticDocument::fromElasticsearchHit($hit);
                } else {
                    // If using a custom data class that doesn't extend ElasticDocument
                    $data = $hit['_source'] ?? [];
                    $data['_id'] = $hit['_id'] ?? null;
                    $data['_score'] = $hit['_score'] ?? null;
                    $items[] = new $dataClass($data);
                }
            }
        }

        return new static(
            $items,
            $total,
            $aggregations,
            $executionTime ? $executionTime / 1000 : null
        );
    }

    /**
     * Get the total number of results.
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Get the aggregations.
     */
    public function aggregations(): array
    {
        return $this->aggregations;
    }

    /**
     * Get the execution time in seconds.
     */
    public function executionTime(): ?float
    {
        return $this->executionTime;
    }

    /**
     * Create a new length-aware paginator instance.
     */
    public function paginate(int $perPage, int $page, array $options = []): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            $this->items,
            $this->total,
            $perPage,
            $page,
            $options
        );
    }

    /**
     * Convert to a Spatie DataCollection if needed.
     *
     * @return DataCollection
     */
    public function toDataCollection(): DataCollection
    {
        return new DataCollection(ElasticDocument::class, $this->items);
    }

    /**
     * Convert the collection to a standard array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $array = [
            'total' => $this->total,
            'hits' => parent::toArray(),
        ];
        
        if (!empty($this->aggregations)) {
            $array['aggregations'] = $this->aggregations;
        }
        
        if ($this->executionTime !== null) {
            $array['execution_time_ms'] = $this->executionTime;
        }
        
        return $array;
    }
} 