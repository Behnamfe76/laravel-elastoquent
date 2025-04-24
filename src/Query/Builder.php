<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Query;

use Fereydooni\LaravelElastoquent\Managers\ElasticManager;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class Builder
{
    /**
     * The Elasticsearch manager instance.
     */
    protected ElasticManager $elasticManager;

    /**
     * The model being queried.
     */
    protected string $model;

    /**
     * The query parts.
     */
    protected array $query = [
        'query' => [
            'bool' => [
                'must' => [],
                'must_not' => [],
                'should' => [],
                'filter' => [],
            ],
        ],
    ];

    /**
     * The "from" value of the query.
     */
    protected ?int $from = null;

    /**
     * The "size" value of the query.
     */
    protected ?int $size = null;

    /**
     * The sort order for the query.
     */
    protected array $sort = [];

    /**
     * The aggregations for the query.
     */
    protected array $aggregations = [];

    /**
     * Indicates if soft deleted models should be included.
     */
    protected bool $withTrashed = false;

    /**
     * Indicates if only soft deleted models should be included.
     */
    protected bool $onlyTrashed = false;

    /**
     * Create a new Elasticsearch query builder instance.
     */
    public function __construct(ElasticManager $elasticManager)
    {
        $this->elasticManager = $elasticManager;
    }

    /**
     * Set the model for the query.
     */
    public function setModel(string $model): self
    {
        $this->model = $model;

        // Add filter for soft deletes by default
        if ($this->elasticManager->getConfig('soft_deletes', true) && !$this->withTrashed && !$this->onlyTrashed) {
            $this->whereNull('_deleted_at');
        }

        return $this;
    }

    /**
     * Add a basic where clause to the query.
     */
    public function where(string $field, $operator = null, $value = null): self
    {
        // Handle different parameter patterns
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        switch ($operator) {
            case '=':
                $this->addTermQuery('must', $field, $value);
                break;
            case '!=':
            case '<>':
                $this->addTermQuery('must_not', $field, $value);
                break;
            case '>':
                $this->addRangeQuery('must', $field, ['gt' => $value]);
                break;
            case '>=':
                $this->addRangeQuery('must', $field, ['gte' => $value]);
                break;
            case '<':
                $this->addRangeQuery('must', $field, ['lt' => $value]);
                break;
            case '<=':
                $this->addRangeQuery('must', $field, ['lte' => $value]);
                break;
            case 'like':
            case 'contains':
                $this->addWildcardQuery('must', $field, "*{$value}*");
                break;
            case 'starts_with':
                $this->addWildcardQuery('must', $field, "{$value}*");
                break;
            case 'ends_with':
                $this->addWildcardQuery('must', $field, "*{$value}");
                break;
            case 'in':
                $this->addTermsQuery('must', $field, (array) $value);
                break;
            case 'not_in':
                $this->addTermsQuery('must_not', $field, (array) $value);
                break;
            case 'between':
                $this->addRangeQuery('must', $field, ['gte' => $value[0], 'lte' => $value[1]]);
                break;
            case 'not_between':
                $this->addRangeQuery('must_not', $field, ['gte' => $value[0], 'lte' => $value[1]]);
                break;
            default:
                throw new \InvalidArgumentException("Operator [{$operator}] is not supported");
        }

        return $this;
    }

    /**
     * Add an "or where" clause to the query.
     */
    public function orWhere(string $field, $operator = null, $value = null): self
    {
        // Handle different parameter patterns
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        switch ($operator) {
            case '=':
                $this->addTermQuery('should', $field, $value);
                break;
            case '!=':
            case '<>':
                // For "or not equal", we need to add a must_not inside a should
                $this->query['query']['bool']['should'][] = [
                    'bool' => [
                        'must_not' => [
                            ['term' => [$field => $value]],
                        ],
                    ],
                ];
                break;
            case '>':
                $this->addRangeQuery('should', $field, ['gt' => $value]);
                break;
            case '>=':
                $this->addRangeQuery('should', $field, ['gte' => $value]);
                break;
            case '<':
                $this->addRangeQuery('should', $field, ['lt' => $value]);
                break;
            case '<=':
                $this->addRangeQuery('should', $field, ['lte' => $value]);
                break;
            case 'like':
            case 'contains':
                $this->addWildcardQuery('should', $field, "*{$value}*");
                break;
            case 'starts_with':
                $this->addWildcardQuery('should', $field, "{$value}*");
                break;
            case 'ends_with':
                $this->addWildcardQuery('should', $field, "*{$value}");
                break;
            case 'in':
                $this->addTermsQuery('should', $field, (array) $value);
                break;
            case 'not_in':
                // For "or not in", we need to add a must_not inside a should
                $this->query['query']['bool']['should'][] = [
                    'bool' => [
                        'must_not' => [
                            ['terms' => [$field => (array) $value]],
                        ],
                    ],
                ];
                break;
            case 'between':
                $this->addRangeQuery('should', $field, ['gte' => $value[0], 'lte' => $value[1]]);
                break;
            case 'not_between':
                // For "or not between", we need to add a must_not inside a should
                $this->query['query']['bool']['should'][] = [
                    'bool' => [
                        'must_not' => [
                            ['range' => [$field => ['gte' => $value[0], 'lte' => $value[1]]]],
                        ],
                    ],
                ];
                break;
            default:
                throw new \InvalidArgumentException("Operator [{$operator}] is not supported");
        }

        return $this;
    }

    /**
     * Add a "where null" clause to the query.
     */
    public function whereNull(string $field): self
    {
        $this->query['query']['bool']['must_not'][] = [
            'exists' => ['field' => $field],
        ];

        return $this;
    }

    /**
     * Add a "where not null" clause to the query.
     */
    public function whereNotNull(string $field): self
    {
        $this->query['query']['bool']['must'][] = [
            'exists' => ['field' => $field],
        ];

        return $this;
    }

    /**
     * Add a "or where null" clause to the query.
     */
    public function orWhereNull(string $field): self
    {
        $this->query['query']['bool']['should'][] = [
            'bool' => [
                'must_not' => [
                    ['exists' => ['field' => $field]],
                ],
            ],
        ];

        return $this;
    }

    /**
     * Add a "or where not null" clause to the query.
     */
    public function orWhereNotNull(string $field): self
    {
        $this->query['query']['bool']['should'][] = [
            'exists' => ['field' => $field],
        ];

        return $this;
    }

    /**
     * Include soft deleted models in the results.
     */
    public function withTrashed(): self
    {
        $this->withTrashed = true;
        $this->onlyTrashed = false;

        // Remove the _deleted_at filter if it exists
        $this->removeExistenceQuery('must_not', '_deleted_at');

        return $this;
    }

    /**
     * Include only soft deleted models in the results.
     */
    public function onlyTrashed(): self
    {
        $this->onlyTrashed = true;
        $this->withTrashed = false;

        // Remove the _deleted_at filter if it exists
        $this->removeExistenceQuery('must_not', '_deleted_at');

        // Add filter for only trashed
        $this->whereNotNull('_deleted_at');

        return $this;
    }

    /**
     * Add a full-text search query.
     */
    public function search(string $term, array $fields = ['*'], float $fuzziness = 'auto'): self
    {
        if ($fields === ['*']) {
            // Search in all fields
            $this->query['query']['bool']['must'][] = [
                'multi_match' => [
                    'query' => $term,
                    'fuzziness' => $fuzziness,
                ],
            ];
        } else {
            // Search in specific fields
            $this->query['query']['bool']['must'][] = [
                'multi_match' => [
                    'query' => $term,
                    'fields' => $fields,
                    'fuzziness' => $fuzziness,
                ],
            ];
        }

        return $this;
    }

    /**
     * Add an "order by" clause to the query.
     */
    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $this->sort[] = [$field => strtolower($direction)];

        return $this;
    }

    /**
     * Add a "limit" clause to the query.
     */
    public function limit(int $value): self
    {
        $this->size = $value;

        return $this;
    }

    /**
     * Add an "offset" clause to the query.
     */
    public function offset(int $value): self
    {
        $this->from = $value;

        return $this;
    }

    /**
     * Set the "limit" and "offset" for a given page.
     */
    public function forPage(int $page, int $perPage = 15): self
    {
        $this->from = ($page - 1) * $perPage;
        $this->size = $perPage;

        return $this;
    }

    /**
     * Paginate the given query.
     */
    public function paginate(int $perPage = 15, int $page = null): LengthAwarePaginator
    {
        $page = $page ?: $this->resolvePage();
        $this->forPage($page, $perPage);

        $results = $this->get();
        $total = $this->count();

        return new LengthAwarePaginator(
            $results,
            $total,
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
    }

    /**
     * Get the current page from the request.
     */
    protected function resolvePage(): int
    {
        return (int) request()->input('page', 1);
    }

    /**
     * Add an aggregation to the query.
     */
    public function aggregate(string $name, array $aggregation): self
    {
        $this->aggregations[$name] = $aggregation;

        return $this;
    }

    /**
     * Execute the query and get the first result.
     */
    public function first()
    {
        return $this->limit(1)->get()->first();
    }

    /**
     * Execute the query and get the results.
     */
    public function get(): Collection
    {
        $params = $this->buildParams();
        $result = $this->elasticManager->search(
            $this->model::getIndexName(),
            $params,
            [
                'from' => $this->from,
                'size' => $this->size,
                'sort' => $this->sort,
            ]
        );

        $collection = new Collection();

        foreach ($result['hits'] as $hit) {
            $data = $hit['_source'] ?? [];
            $data['_id'] = $hit['_id'];
            $data['_score'] = $hit['_score'] ?? null;

            $model = $this->model::newFromElasticData($data);
            $collection->push($model);
        }

        return $collection;
    }

    /**
     * Get the count of documents.
     */
    public function count(): int
    {
        $params = $this->buildParams();
        $params['track_total_hits'] = true;

        $result = $this->elasticManager->search(
            $this->model::getIndexName(),
            $params,
            [
                'from' => 0,
                'size' => 0,
            ]
        );

        return $result['total'];
    }

    /**
     * Get a collection of specified fields.
     */
    public function pluck(string $field): Collection
    {
        $results = $this->get();

        return $results->pluck($field);
    }

    /**
     * Build the query parameters.
     */
    protected function buildParams(): array
    {
        $params = $this->query;

        // Clean up empty bool clauses
        foreach (['must', 'must_not', 'should', 'filter'] as $clause) {
            if (empty($params['query']['bool'][$clause])) {
                unset($params['query']['bool'][$clause]);
            }
        }

        // Add aggregations if any
        if (!empty($this->aggregations)) {
            $params['aggs'] = $this->aggregations;
        }

        return $params;
    }

    /**
     * Add a term query to the bool query.
     */
    protected function addTermQuery(string $type, string $field, $value): void
    {
        $this->query['query']['bool'][$type][] = [
            'term' => [$field => $value],
        ];
    }

    /**
     * Add a terms query to the bool query.
     */
    protected function addTermsQuery(string $type, string $field, array $values): void
    {
        $this->query['query']['bool'][$type][] = [
            'terms' => [$field => $values],
        ];
    }

    /**
     * Add a range query to the bool query.
     */
    protected function addRangeQuery(string $type, string $field, array $range): void
    {
        $this->query['query']['bool'][$type][] = [
            'range' => [$field => $range],
        ];
    }

    /**
     * Add a wildcard query to the bool query.
     */
    protected function addWildcardQuery(string $type, string $field, string $value): void
    {
        $this->query['query']['bool'][$type][] = [
            'wildcard' => [$field => $value],
        ];
    }

    /**
     * Remove an existence query from the bool query.
     */
    protected function removeExistenceQuery(string $type, string $field): void
    {
        if (!isset($this->query['query']['bool'][$type])) {
            return;
        }

        foreach ($this->query['query']['bool'][$type] as $key => $query) {
            if (isset($query['exists']) && $query['exists']['field'] === $field) {
                unset($this->query['query']['bool'][$type][$key]);
                $this->query['query']['bool'][$type] = array_values($this->query['query']['bool'][$type]);
                break;
            }
        }
    }
} 