<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Connection Settings
    |--------------------------------------------------------------------------
    |
    | Configure your Elasticsearch connection settings here.
    |
    */
    'connection' => [
        'hosts' => [env('ELASTIC_HOST', 'localhost:9200')],
        'username' => env('ELASTIC_USERNAME', null),
        'password' => env('ELASTIC_PASSWORD', null),
        'api_key' => env('ELASTIC_API_KEY', null),
        'cloud_id' => env('ELASTIC_CLOUD_ID', null),
        'ssl_verification' => env('ELASTIC_SSL_VERIFICATION', true),
        'connection_timeout' => env('ELASTIC_CONNECTION_TIMEOUT', 60),
        'request_timeout' => env('ELASTIC_REQUEST_TIMEOUT', 30),
        'retry_on_failure' => env('ELASTIC_RETRY_ON_FAILURE', true),
        'max_retries' => env('ELASTIC_MAX_RETRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Index Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be applied to all indices created by the ORM.
    | For example, with a prefix of 'app_', the 'users' index will become 'app_users'.
    |
    */
    'index_prefix' => env('ELASTIC_INDEX_PREFIX', 'app_'),

    /*
    |--------------------------------------------------------------------------
    | Soft Deletes
    |--------------------------------------------------------------------------
    |
    | When enabled, deleted records will not be removed from the index, but will
    | be marked with a '_deleted_at' field. This provides the ability to revert
    | a deletion or query deleted records.
    |
    */
    'soft_deletes' => env('ELASTIC_SOFT_DELETES', true),

    /*
    |--------------------------------------------------------------------------
    | Bulk Indexing
    |--------------------------------------------------------------------------
    |
    | Configure the batch size for bulk indexing operations.
    |
    */
    'bulk_size' => env('ELASTIC_BULK_SIZE', 1000),

    /*
    |--------------------------------------------------------------------------
    | Default Shard Settings
    |--------------------------------------------------------------------------
    |
    | Configure the default number of shards and replicas for new indices.
    |
    */
    'default_settings' => [
        'number_of_shards' => env('ELASTIC_NUMBER_OF_SHARDS', 1),
        'number_of_replicas' => env('ELASTIC_NUMBER_OF_REPLICAS', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Refresh Policy
    |--------------------------------------------------------------------------
    |
    | Configure when changes made by the ORM should be visible to search.
    | Options: 'immediate', 'wait_for', or false (async).
    |
    */
    'refresh_policy' => env('ELASTIC_REFRESH_POLICY', 'wait_for'),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, the ORM will log queries and operations to the Laravel log.
    |
    */
    'logging' => [
        'enabled' => env('ELASTIC_LOGGING_ENABLED', false),
        'level' => env('ELASTIC_LOGGING_LEVEL', 'debug'),
        'slow_query_threshold' => env('ELASTIC_SLOW_QUERY_THRESHOLD', 1000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    |
    | Enable or disable relationship support.
    |
    */
    'relationships' => [
        'enabled' => env('ELASTIC_RELATIONSHIPS_ENABLED', true),
        'max_depth' => env('ELASTIC_RELATIONSHIPS_MAX_DEPTH', 3),
    ],
]; 