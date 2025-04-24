<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Query;

use Exception;
use Fereydooni\LaravelElastoquent\Managers\ElasticManager;
use Fereydooni\LaravelElastoquent\Model;
use GuzzleHttp\Client;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Request;

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
     * Track total hits flag for the query.
     */
    protected ?bool $trackTotalHits = null;

    /**
     * Source fields inclusion/exclusion settings.
     */
    protected array $sourceFields = [];

    /**
     * ES|QL query string.
     */
    protected ?string $esqlQuery = null;

    /**
     * Indicates if soft deleted models should be included.
     */
    protected bool $withTrashed = false;

    /**
     * Indicates if only soft deleted models should be included.
     */
    protected bool $onlyTrashed = false;

    /**
     * The HTTP client instance.
     */
    protected ?Client $httpClient = null;

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
     * Search documents based on a search query string (full-text search).
     *
     * @param  string  $query  The search query string.
     * @param  string[]  $fields  The fields to search in.
     * @param  int|string|null  $fuzziness  The fuzziness level ('AUTO', 0, 1, 2, etc.)
     * @param  string  $operator  The operator ('and' or 'or') to use for the query.
     * @return $this
     */
    public function search(string $query, array $fields = ['*'], $fuzziness = 'AUTO', string $operator = 'or'): static
    {
        if ($fields === ['*']) {
            // Search in all fields
            $this->query['query']['bool']['must'][] = [
                'multi_match' => [
                    'query' => $query,
                    'fuzziness' => $fuzziness,
                ],
            ];
        } else {
            // Search in specific fields
            $this->query['query']['bool']['must'][] = [
                'multi_match' => [
                    'query' => $query,
                    'fields' => $fields,
                    'fuzziness' => $fuzziness,
                ],
            ];
        }

        return $this;
    }

    /**
     * Add a vector search query using the kNN functionality.
     * 
     * @param string $field The vector field to search in
     * @param array $vector The query vector
     * @param int $k The number of nearest neighbors to return
     * @param float|null $similarity The minimum similarity threshold
     * @return $this
     */
    public function vectorSearch(string $field, array $vector, int $k = 10, ?float $similarity = null): self
    {
        $knnQuery = [
            'field' => $field,
            'query_vector' => $vector,
            'k' => $k,
            'num_candidates' => $k * 2
        ];

        if ($similarity !== null) {
            $knnQuery['similarity'] = $similarity;
        }

        // Replace the query with a kNN query
        $this->query['knn'] = $knnQuery;

        return $this;
    }

    /**
     * Add a hybrid search combining text and vector search.
     */
    public function hybridSearch(string $textQuery, array $vectorQuery, array $options = []): self
    {
        $this->query['query'] = [
            'bool' => [
                'should' => [
                    [
                        'match' => [
                            'text' => $textQuery
                        ]
                    ],
                    [
                        'knn' => $vectorQuery
                    ]
                ]
            ]
        ];

        return $this;
    }

    /**
     * Add a semantic search query.
     */
    public function semanticSearch(string $query, string $modelId, array $options = []): self
    {
        $this->query['query'] = [
            'neural' => array_merge([
                'query' => $query,
                'model_id' => $modelId
            ], $options)
        ];

        return $this;
    }

    /**
     * Add a sparse vector search.
     */
    public function sparseVectorSearch(string $field, array $tokens, array $options = []): self
    {
        $this->query['query'] = [
            'match_sparse' => array_merge([
                $field => [
                    'tokens' => $tokens
                ]
            ], $options)
        ];

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
        return (int) Request::input('page', 1);
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
        $result = [];
        
        // Use ES|QL endpoint if esqlQuery is set
        if ($this->esqlQuery !== null) {
            $result = $this->elasticManager->esql(
                $params['esql'],
                [
                    'from' => $this->from,
                    'size' => $this->size,
                ]
            );
        } else {
            $result = $this->elasticManager->search(
                $this->model::getIndexName(),
                $params,
                [
                    'from' => $this->from,
                    'size' => $this->size,
                    'sort' => $this->sort,
                ]
            );
        }

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
        // If using ES|QL, return the appropriate format
        if ($this->esqlQuery !== null) {
            return [
                'esql' => $this->esqlQuery
            ];
        }
        
        $params = $this->query;

        // Clean up empty bool clauses
        if (isset($params['query']['bool'])) {
            foreach (['must', 'must_not', 'should', 'filter'] as $clause) {
                if (empty($params['query']['bool'][$clause])) {
                    unset($params['query']['bool'][$clause]);
                }
            }
        }

        // Add aggregations if any
        if (!empty($this->aggregations)) {
            $params['aggs'] = $this->aggregations;
        }
        
        // Add source field filtering if specified
        if (!empty($this->sourceFields)) {
            $params['_source'] = $this->sourceFields;
        }
        
        // Set track_total_hits if specified
        if ($this->trackTotalHits !== null) {
            $params['track_total_hits'] = $this->trackTotalHits;
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

    /**
     * Set whether to track total hits or not.
     */
    public function trackTotalHits(?bool $track = true): self
    {
        $this->trackTotalHits = $track;
        
        return $this;
    }

    /**
     * Include only specified source fields in the results.
     */
    public function select(array $fields): self
    {
        $this->sourceFields['includes'] = $fields;
        
        return $this;
    }

    /**
     * Exclude specified fields from the source.
     */
    public function exclude(array $fields): self
    {
        $this->sourceFields['excludes'] = $fields;
        
        return $this;
    }

    /**
     * Use ES|QL query instead of DSL.
     * 
     * @param string $query ES|QL query string
     * @return $this
     */
    public function esql(string $query): self
    {
        $this->esqlQuery = $query;
        
        return $this;
    }

    /**
     * Add a match phrase query to the bool query.
     */
    public function matchPhrase(string $field, string $value, array $options = []): self
    {
        $this->query['query']['bool']['must'][] = [
            'match_phrase' => array_merge(
                [$field => $value],
                $options
            )
        ];

        return $this;
    }

    /**
     * Add a fuzzy query to the bool query.
     */
    public function fuzzy(string $field, string $value, array $options = []): self
    {
        $this->query['query']['bool']['must'][] = [
            'fuzzy' => array_merge(
                [$field => $value],
                $options
            )
        ];

        return $this;
    }

    /**
     * Add a regexp query to the bool query.
     */
    public function regexp(string $field, string $pattern, array $options = []): self
    {
        $this->query['query']['bool']['must'][] = [
            'regexp' => array_merge(
                [$field => $pattern],
                $options
            )
        ];

        return $this;
    }

    /**
     * Add a prefix query to the bool query.
     */
    public function prefix(string $field, string $value, array $options = []): self
    {
        $this->query['query']['bool']['must'][] = [
            'prefix' => array_merge(
                [$field => $value],
                $options
            )
        ];

        return $this;
    }

    /**
     * Add highlighting to the query.
     */
    public function highlight(array $fields, array $options = []): self
    {
        $this->query['highlight'] = array_merge([
            'fields' => array_reduce($fields, function ($carry, $field) {
                $carry[$field] = new \stdClass();
                return $carry;
            }, [])
        ], $options);

        return $this;
    }

    /**
     * Add suggestions to the query.
     */
    public function suggest(string $name, array $suggestion): self
    {
        $this->query['suggest'][$name] = $suggestion;
        return $this;
    }

    /**
     * Add a function score query to modify the score of matching documents.
     */
    public function functionScore(array $functions, array $options = []): self
    {
        $this->query['query'] = [
            'function_score' => array_merge([
                'query' => $this->query['query'],
                'functions' => $functions
            ], $options)
        ];

        return $this;
    }

    /**
     * Add a nested query to search nested objects.
     */
    public function nested(string $path, callable $callback, string $scoreMode = 'avg'): self
    {
        $builder = new self($this->elasticManager);
        $callback($builder);

        $this->query['query']['bool']['must'][] = [
            'nested' => [
                'path' => $path,
                'query' => $builder->query['query'],
                'score_mode' => $scoreMode
            ]
        ];

        return $this;
    }

    /**
     * Add a parent query to search parent documents.
     */
    public function hasParent(string $parentType, callable $callback): self
    {
        $builder = new self($this->elasticManager);
        $callback($builder);

        $this->query['query']['bool']['must'][] = [
            'has_parent' => [
                'parent_type' => $parentType,
                'query' => $builder->query['query']
            ]
        ];

        return $this;
    }

    /**
     * Add a child query to search child documents.
     */
    public function hasChild(string $childType, callable $callback, string $scoreMode = 'none'): self
    {
        $builder = new self($this->elasticManager);
        $callback($builder);

        $this->query['query']['bool']['must'][] = [
            'has_child' => [
                'type' => $childType,
                'query' => $builder->query['query'],
                'score_mode' => $scoreMode
            ]
        ];

        return $this;
    }

    /**
     * Add a geo distance query.
     */
    public function geoDistance(string $field, float $lat, float $lon, string $distance): self
    {
        $this->query['query']['bool']['filter'][] = [
            'geo_distance' => [
                'distance' => $distance,
                $field => [
                    'lat' => $lat,
                    'lon' => $lon
                ]
            ]
        ];

        return $this;
    }

    /**
     * Add a geo bounding box query.
     */
    public function geoBoundingBox(string $field, array $topLeft, array $bottomRight): self
    {
        $this->query['query']['bool']['filter'][] = [
            'geo_bounding_box' => [
                $field => [
                    'top_left' => $topLeft,
                    'bottom_right' => $bottomRight
                ]
            ]
        ];

        return $this;
    }

    /**
     * Add a geo polygon query.
     */
    public function geoPolygon(string $field, array $points): self
    {
        $this->query['query']['bool']['filter'][] = [
            'geo_polygon' => [
                $field => [
                    'points' => $points
                ]
            ]
        ];

        return $this;
    }

    /**
     * Add a geo shape query.
     */
    public function geoShape(string $field, array $shape, string $relation = 'intersects'): self
    {
        $this->query['query']['bool']['filter'][] = [
            'geo_shape' => [
                $field => [
                    'shape' => $shape,
                    'relation' => $relation
                ]
            ]
        ];

        return $this;
    }

    /**
     * Add a script query.
     */
    public function script(string $script, array $params = [], string $lang = 'painless'): self
    {
        $this->query['query']['bool']['must'][] = [
            'script' => [
                'script' => [
                    'lang' => $lang,
                    'source' => $script,
                    'params' => $params
                ]
            ]
        ];

        return $this;
    }

    /**
     * Add a more like this (MLT) query.
     */
    public function moreLikeThis(array $fields, array $like, array $options = []): self
    {
        $this->query['query']['bool']['must'][] = array_merge([
            'more_like_this' => [
                'fields' => $fields,
                'like' => $like,
                'min_term_freq' => 1,
                'max_query_terms' => 12,
                'min_doc_freq' => 1
            ]
        ], $options);

        return $this;
    }

    /**
     * Add a percolate query to match stored queries.
     */
    public function percolate(string $field, array $document): self
    {
        $this->query['query']['bool']['must'][] = [
            'percolate' => [
                'field' => $field,
                'document' => $document
            ]
        ];

        return $this;
    }

    /**
     * Add a rank feature query.
     */
    public function rankFeature(string $field, array $options = []): self
    {
        $this->query['query']['bool']['must'][] = [
            'rank_feature' => array_merge([
                'field' => $field
            ], $options)
        ];

        return $this;
    }

    /**
     * Add a shape query for arbitrary shapes.
     */
    public function shape(string $field, array $shape, string $relation = 'intersects'): self
    {
        $this->query['query']['bool']['filter'][] = [
            'shape' => [
                $field => [
                    'shape' => $shape,
                    'relation' => $relation
                ]
            ]
        ];

        return $this;
    }

    /**
     * Add a distance feature query.
     */
    public function distanceFeature(string $field, $origin, string $pivot): self
    {
        $this->query['query']['bool']['must'][] = [
            'distance_feature' => [
                'field' => $field,
                'origin' => $origin,
                'pivot' => $pivot
            ]
        ];

        return $this;
    }

    /**
     * Add a terms set query.
     */
    public function termsSet(string $field, array $terms, array $options = []): self
    {
        $this->query['query']['bool']['must'][] = [
            'terms_set' => [
                $field => array_merge([
                    'terms' => $terms
                ], $options)
            ]
        ];

        return $this;
    }

    /**
     * Add a combined fields query.
     */
    public function combinedFields(array $fields, string $query, array $options = []): self
    {
        $this->query['query']['bool']['must'][] = [
            'combined_fields' => array_merge([
                'query' => $query,
                'fields' => $fields
            ], $options)
        ];

        return $this;
    }

    /**
     * Add a pinned query to boost specific documents.
     */
    public function pinned(array $ids, array $organic): self
    {
        $this->query['query'] = [
            'pinned' => [
                'ids' => $ids,
                'organic' => $organic
            ]
        ];

        return $this;
    }

    /**
     * Add advanced aggregations.
     */
    public function advancedAggregation(string $name, array $aggregation): self
    {
        $this->aggregations[$name] = $aggregation;
        return $this;
    }

    /**
     * Add a collapse field for results grouping.
     */
    public function collapse(string $field, array $options = []): self
    {
        $this->query['collapse'] = array_merge([
            'field' => $field
        ], $options);

        return $this;
    }

    /**
     * Add search after for deep pagination.
     */
    public function searchAfter(array $searchAfter): self
    {
        $this->query['search_after'] = $searchAfter;
        return $this;
    }

    /**
     * Enable explain to get detailed scoring information.
     */
    public function explain(bool $explain = true): self
    {
        $this->query['explain'] = $explain;
        return $this;
    }

    /**
     * Add runtime fields to the query.
     */
    public function runtimeMappings(array $fields): self
    {
        $this->query['runtime_mappings'] = $fields;
        return $this;
    }

    /**
     * Add a minimum_should_match parameter to the bool query.
     */
    public function minimumShouldMatch(string|int $minimum): self
    {
        $this->query['query']['bool']['minimum_should_match'] = $minimum;
        return $this;
    }

    /**
     * Add a metrics aggregation.
     */
    public function metricsAggregation(string $name, string $type, string $field, array $options = []): self
    {
        $this->aggregations[$name] = array_merge([
            $type => [
                'field' => $field
            ]
        ], $options);

        return $this;
    }

    /**
     * Add a bucket aggregation.
     */
    public function bucketAggregation(string $name, string $type, array $options = []): self
    {
        $this->aggregations[$name] = array_merge([
            $type => $options
        ], $options);

        return $this;
    }

    /**
     * Add a pipeline aggregation.
     */
    public function pipelineAggregation(string $name, string $type, array $options = []): self
    {
        $this->aggregations[$name] = array_merge([
            $type => $options
        ], $options);

        return $this;
    }

    /**
     * Add a matrix aggregation.
     */
    public function matrixAggregation(string $name, array $fields, array $options = []): self
    {
        $this->aggregations[$name] = array_merge([
            'matrix_stats' => [
                'fields' => $fields
            ]
        ], $options);

        return $this;
    }

    /**
     * Enable cross-cluster search.
     */
    public function crossClusterSearch(array $clusters): self
    {
        $this->query['ccs_minimize_roundtrips'] = true;
        $this->query['indices'] = array_map(function($cluster) {
            return $cluster . ':*';
        }, $clusters);

        return $this;
    }

    /**
     * Enable async search.
     */
    public function asyncSearch(array $options = []): self
    {
        $this->query['async'] = array_merge([
            'wait_for_completion_timeout' => '1s',
            'keep_alive' => '5m'
        ], $options);

        return $this;
    }

    /**
     * Add field capabilities.
     */
    public function fieldCapabilities(array $fields = [], array $options = []): self
    {
        $this->query['field_caps'] = array_merge([
            'fields' => $fields ?: ['*']
        ], $options);

        return $this;
    }

    /**
     * Enable point in time search.
     */
    public function pointInTime(string $id, array $options = []): self
    {
        $this->query['pit'] = array_merge([
            'id' => $id
        ], $options);

        return $this;
    }

    /**
     * Add a knn query for vector search.
     */
    public function knn(string $field, array $vector, int $k = 10, array $options = []): self
    {
        $this->query['knn'] = array_merge([
            'field' => $field,
            'query_vector' => $vector,
            'k' => $k,
            'num_candidates' => $k * 2
        ], $options);

        return $this;
    }

    /**
     * Add a rank features query.
     */
    public function rankFeatures(array $features, array $options = []): self
    {
        $this->query['query'] = [
            'rank_features' => array_merge([
                'features' => $features
            ], $options)
        ];

        return $this;
    }

    /**
     * Add a learning to rank query.
     */
    public function learningToRank(string $modelId, array $features, array $options = []): self
    {
        $this->query['query'] = [
            'learning_to_rank' => array_merge([
                'model_id' => $modelId,
                'features' => $features
            ], $options)
        ];

        return $this;
    }

    /**
     * Add a query rescorer.
     */
    public function rescore(array $windowSize, array $query, array $options = []): self
    {
        $this->query['rescore'] = array_merge([
            'window_size' => $windowSize,
            'query' => $query
        ], $options);

        return $this;
    }

    /**
     * Add a search template.
     */
    public function searchTemplate(string $id, array $params = []): self
    {
        $this->query['template'] = [
            'id' => $id,
            'params' => $params
        ];

        return $this;
    }

    /**
     * Add a multi search template.
     */
    public function multiSearchTemplate(array $templates): self
    {
        $this->query['multi_search_template'] = [
            'templates' => $templates
        ];

        return $this;
    }

    /**
     * Add a field collapsing with inner hits.
     */
    public function collapseWithInnerHits(string $field, array $options = []): self
    {
        $this->query['collapse'] = array_merge([
            'field' => $field,
            'inner_hits' => [
                'name' => 'collapsed',
                'size' => 5
            ]
        ], $options);

        return $this;
    }

    /**
     * Add a search after with sort.
     */
    public function searchAfterWithSort(array $sort, array $searchAfter): self
    {
        $this->query['sort'] = $sort;
        $this->query['search_after'] = $searchAfter;

        return $this;
    }

    /**
     * Add a runtime field.
     */
    public function runtimeField(string $name, string $type, string $script, array $options = []): self
    {
        $this->query['runtime_mappings'][$name] = array_merge([
            'type' => $type,
            'script' => $script
        ], $options);

        return $this;
    }

    /**
     * Add a field collapsing with max concurrent group requests.
     */
    public function collapseWithMaxConcurrentGroupRequests(string $field, int $maxConcurrentGroupRequests): self
    {
        $this->query['collapse'] = [
            'field' => $field,
            'max_concurrent_group_searches' => $maxConcurrentGroupRequests
        ];

        return $this;
    }

    /**
     * Add a field collapsing with inner hits and max concurrent group requests.
     */
    public function collapseWithInnerHitsAndMaxConcurrentGroupRequests(
        string $field,
        int $maxConcurrentGroupRequests,
        array $innerHitsOptions = []
    ): self {
        $this->query['collapse'] = [
            'field' => $field,
            'max_concurrent_group_searches' => $maxConcurrentGroupRequests,
            'inner_hits' => array_merge([
                'name' => 'collapsed',
                'size' => 5
            ], $innerHitsOptions)
        ];

        return $this;
    }
} 