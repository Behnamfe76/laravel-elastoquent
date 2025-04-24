<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Managers;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

class ElasticManager
{
    /**
     * The Elasticsearch client instance.
     */
    protected Client $client;

    /**
     * The ORM configuration.
     */
    protected array $config;

    /**
     * The logger instance.
     */
    protected ?LoggerInterface $logger = null;

    /**
     * Create a new ElasticManager instance.
     */
    public function __construct(Client $client, array $config)
    {
        $this->client = $client;
        $this->config = $config;

        // Set up logging if enabled
        if (Arr::get($config, 'logging.enabled', false)) {
            $this->logger = Log::channel(Arr::get($config, 'logging.channel', 'stack'));
        }
    }

    /**
     * Get the Elasticsearch client instance.
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Get a configuration value.
     */
    public function getConfig(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return Arr::get($this->config, $key, $default);
    }

    /**
     * Check if an index exists.
     */
    public function indexExists(string $indexName): bool
    {
        try {
            $response = $this->client->indices()->exists([
                'index' => $this->prefixIndex($indexName),
            ]);

            return $response->asBool();
        } catch (\Exception $e) {
            $this->logError('Error checking if index exists: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new index.
     */
    public function createIndex(string $indexName, array $settings = [], array $mappings = []): bool
    {
        $indexName = $this->prefixIndex($indexName);

        // Don't recreate an existing index
        if ($this->indexExists($indexName)) {
            $this->logInfo("Index {$indexName} already exists");
            return true;
        }

        // Apply default settings if not provided
        if (empty($settings)) {
            $settings = [
                'number_of_shards' => $this->getConfig('default_settings.number_of_shards', 1),
                'number_of_replicas' => $this->getConfig('default_settings.number_of_replicas', 0),
            ];
        }

        try {
            $params = [
                'index' => $indexName,
                'body' => [
                    'settings' => $settings,
                ],
            ];

            // Add mappings if provided
            if (!empty($mappings)) {
                $params['body']['mappings'] = $mappings;
            }

            $response = $this->client->indices()->create($params);
            $acknowledged = $response['acknowledged'] ?? false;

            if ($acknowledged) {
                $this->logInfo("Successfully created index {$indexName}");
            } else {
                $this->logWarning("Index creation not acknowledged for {$indexName}");
            }

            return $acknowledged;
        } catch (ClientResponseException|ServerResponseException $e) {
            $this->logError("Failed to create index {$indexName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete an index.
     */
    public function deleteIndex(string $indexName): bool
    {
        $indexName = $this->prefixIndex($indexName);

        if (!$this->indexExists($indexName)) {
            $this->logInfo("Index {$indexName} does not exist, skipping delete");
            return true;
        }

        try {
            $response = $this->client->indices()->delete(['index' => $indexName]);
            $acknowledged = $response['acknowledged'] ?? false;

            if ($acknowledged) {
                $this->logInfo("Successfully deleted index {$indexName}");
            } else {
                $this->logWarning("Index deletion not acknowledged for {$indexName}");
            }

            return $acknowledged;
        } catch (ClientResponseException|ServerResponseException $e) {
            $this->logError("Failed to delete index {$indexName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update mapping for an index.
     */
    public function updateMapping(string $indexName, array $mappings): bool
    {
        $indexName = $this->prefixIndex($indexName);

        if (!$this->indexExists($indexName)) {
            $this->logError("Cannot update mapping: Index {$indexName} does not exist");
            return false;
        }

        try {
            $response = $this->client->indices()->putMapping([
                'index' => $indexName,
                'body' => $mappings,
            ]);

            $acknowledged = $response['acknowledged'] ?? false;

            if ($acknowledged) {
                $this->logInfo("Successfully updated mapping for {$indexName}");
            } else {
                $this->logWarning("Mapping update not acknowledged for {$indexName}");
            }

            return $acknowledged;
        } catch (ClientResponseException|ServerResponseException $e) {
            $this->logError("Failed to update mapping for {$indexName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add prefix to index name based on configuration.
     */
    public function prefixIndex(string $indexName): string
    {
        $prefix = $this->getConfig('index_prefix', '');
        
        // If the index already has the prefix, don't add it again
        if (!empty($prefix) && strpos($indexName, $prefix) !== 0) {
            return $prefix . $indexName;
        }

        return $indexName;
    }

    /**
     * Index a document.
     */
    public function index(string $indexName, string $id, array $document, array $options = []): bool
    {
        $indexName = $this->prefixIndex($indexName);
        $refresh = $options['refresh'] ?? $this->getConfig('refresh_policy', 'wait_for');

        try {
            $params = [
                'index' => $indexName,
                'id' => $id,
                'body' => $document,
            ];

            if ($refresh !== false) {
                $params['refresh'] = $refresh;
            }

            $response = $this->client->index($params);
            $result = $response['result'] ?? null;

            if (in_array($result, ['created', 'updated'])) {
                $this->logInfo("Successfully indexed document {$id} in {$indexName}");
                return true;
            }

            $this->logWarning("Unexpected result when indexing document {$id} in {$indexName}: {$result}");
            return false;
        } catch (ClientResponseException|ServerResponseException $e) {
            $this->logError("Failed to index document {$id} in {$indexName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Bulk index documents.
     */
    public function bulkIndex(string $indexName, array $documents, array $options = []): array
    {
        $indexName = $this->prefixIndex($indexName);
        $refresh = $options['refresh'] ?? $this->getConfig('refresh_policy', 'wait_for');
        $batchSize = $options['batch_size'] ?? $this->getConfig('bulk_size', 1000);

        $stats = [
            'total' => count($documents),
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        if (empty($documents)) {
            return $stats;
        }

        // Process in batches
        foreach (array_chunk($documents, $batchSize) as $batch) {
            $bulkParams = ['body' => []];

            foreach ($batch as $document) {
                $id = $document['id'] ?? null;
                unset($document['id']); // Remove ID from document

                // Add metadata
                $bulkParams['body'][] = [
                    'index' => [
                        '_index' => $indexName,
                        '_id' => $id,
                    ],
                ];

                // Add document body
                $bulkParams['body'][] = $document;
            }

            if ($refresh !== false) {
                $bulkParams['refresh'] = $refresh;
            }

            try {
                $response = $this->client->bulk($bulkParams);
                
                // Process results
                if (isset($response['errors']) && $response['errors']) {
                    foreach ($response['items'] as $item) {
                        $index = $item['index'] ?? null;
                        
                        if ($index) {
                            if (isset($index['error'])) {
                                $stats['failed']++;
                                $stats['errors'][] = [
                                    'id' => $index['_id'] ?? 'unknown',
                                    'error' => $index['error']['reason'] ?? 'Unknown error',
                                ];
                            } else {
                                $stats['success']++;
                            }
                        }
                    }
                } else {
                    // All successful
                    $stats['success'] += count($batch);
                }

                $this->logInfo("Bulk indexed {$stats['success']} documents in {$indexName}");
            } catch (ClientResponseException|ServerResponseException $e) {
                $stats['failed'] += count($batch);
                $stats['errors'][] = [
                    'batch' => true,
                    'error' => $e->getMessage(),
                ];
                $this->logError("Bulk indexing error in {$indexName}: " . $e->getMessage());
            }
        }

        return $stats;
    }

    /**
     * Get a document by ID.
     */
    public function get(string $indexName, string $id): ?array
    {
        $indexName = $this->prefixIndex($indexName);

        try {
            $response = $this->client->get([
                'index' => $indexName,
                'id' => $id,
            ]);

            $source = $response['_source'] ?? null;
            
            if ($source) {
                // Add the _id field to the source document
                $source['_id'] = $response['_id'];
                return $source;
            }

            return null;
        } catch (ClientResponseException $e) {
            // 404 not found is a normal condition, don't log as error
            if ($e->getCode() === 404) {
                $this->logInfo("Document {$id} not found in {$indexName}");
                return null;
            }
            
            $this->logError("Error retrieving document {$id} from {$indexName}: " . $e->getMessage());
            return null;
        } catch (ServerResponseException $e) {
            $this->logError("Server error retrieving document {$id} from {$indexName}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete a document by ID.
     */
    public function delete(string $indexName, string $id, array $options = []): bool
    {
        $indexName = $this->prefixIndex($indexName);
        $refresh = $options['refresh'] ?? $this->getConfig('refresh_policy', 'wait_for');

        try {
            $params = [
                'index' => $indexName,
                'id' => $id,
            ];

            if ($refresh !== false) {
                $params['refresh'] = $refresh;
            }

            $response = $this->client->delete($params);
            $result = $response['result'] ?? null;

            if ($result === 'deleted') {
                $this->logInfo("Successfully deleted document {$id} from {$indexName}");
                return true;
            }

            $this->logWarning("Unexpected result when deleting document {$id} from {$indexName}: {$result}");
            return false;
        } catch (ClientResponseException $e) {
            // 404 not found means it's already deleted
            if ($e->getCode() === 404) {
                $this->logInfo("Document {$id} already deleted from {$indexName}");
                return true;
            }
            
            $this->logError("Error deleting document {$id} from {$indexName}: " . $e->getMessage());
            return false;
        } catch (ServerResponseException $e) {
            $this->logError("Server error deleting document {$id} from {$indexName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Search for documents.
     */
    public function search(string $indexName, array $query, array $options = []): array
    {
        $indexName = $this->prefixIndex($indexName);
        
        $params = [
            'index' => $indexName,
            'body' => $query,
        ];

        // Add pagination if provided
        if (isset($options['from'])) {
            $params['from'] = $options['from'];
        }

        if (isset($options['size'])) {
            $params['size'] = $options['size'];
        }

        // Add sort if provided
        if (isset($options['sort'])) {
            $params['sort'] = $options['sort'];
        }

        try {
            $startTime = microtime(true);
            $response = $this->client->search($params);
            $endTime = microtime(true);
            
            $executionTime = ($endTime - $startTime) * 1000; // in milliseconds
            $slowQueryThreshold = $this->getConfig('logging.slow_query_threshold', 1000);
            
            if ($executionTime > $slowQueryThreshold) {
                $this->logWarning("Slow query ({$executionTime}ms) on {$indexName}: " . json_encode($query));
            } else {
                $this->logInfo("Search executed in {$executionTime}ms on {$indexName}");
            }

            return [
                'hits' => $response['hits']['hits'] ?? [],
                'total' => $response['hits']['total']['value'] ?? 0,
                'aggregations' => $response['aggregations'] ?? [],
                'execution_time_ms' => $executionTime,
            ];
        } catch (ClientResponseException|ServerResponseException $e) {
            $this->logError("Search error on {$indexName}: " . $e->getMessage());
            
            return [
                'hits' => [],
                'total' => 0,
                'aggregations' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute an ES|QL query.
     *
     * @param string $query The ES|QL query
     * @param array $options Additional query options
     * @return array The query result
     */
    public function esql(string $query, array $options = []): array
    {
        try {
            // Prepare the request body
            $body = [
                'query' => $query
            ];

            // Add options
            if (isset($options['from'])) {
                $body['from'] = $options['from'];
            }

            if (isset($options['size'])) {
                $body['size'] = $options['size'];
            }

            // Use a raw HTTP request since ES|QL might not be available in the client
            $response = $this->client->searchTemplate([
                'body' => [
                    'source' => '{"esql": {"query": "{{query}}"}}',
                    'params' => [
                        'query' => $query
                    ]
                ]
            ]);
            
            $result = $response->asArray();
            
            // Transform result to match search() output format for consistency
            return [
                'total' => $result['hits']['total']['value'] ?? 0,
                'hits' => $result['hits']['hits'] ?? [],
                'aggregations' => $result['aggregations'] ?? [],
            ];
        } catch (ClientResponseException|ServerResponseException $e) {
            $this->logError("ES|QL query error: " . $e->getMessage());
            return [
                'total' => 0,
                'hits' => [],
                'aggregations' => [],
            ];
        } catch (\Exception $e) {
            $this->logError("ES|QL query error: " . $e->getMessage());
            return [
                'total' => 0,
                'hits' => [],
                'aggregations' => [],
            ];
        }
    }

    /**
     * Log an info message if logging is enabled.
     */
    protected function logInfo(string $message): void
    {
        if ($this->logger && $this->getConfig('logging.enabled', false)) {
            $this->logger->info("[ElasticORM] {$message}");
        }
    }

    /**
     * Log a warning message if logging is enabled.
     */
    protected function logWarning(string $message): void
    {
        if ($this->logger && $this->getConfig('logging.enabled', false)) {
            $this->logger->warning("[ElasticORM] {$message}");
        }
    }

    /**
     * Log an error message if logging is enabled.
     */
    protected function logError(string $message): void
    {
        if ($this->logger && $this->getConfig('logging.enabled', false)) {
            $this->logger->error("[ElasticORM] {$message}");
        }
    }
} 