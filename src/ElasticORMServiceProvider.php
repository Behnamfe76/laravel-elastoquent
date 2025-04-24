<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Fereydooni\LaravelElastoquent\Console\Commands\ElasticCreateIndex;
use Fereydooni\LaravelElastoquent\Console\Commands\ElasticDropIndex;
use Fereydooni\LaravelElastoquent\Console\Commands\ElasticMappingUpdate;
use Fereydooni\LaravelElastoquent\Console\Commands\ElasticReindex;
use Fereydooni\LaravelElastoquent\Managers\ElasticManager;
use Illuminate\Support\ServiceProvider;

class ElasticORMServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/elastic-orm.php',
            'elastic-orm'
        );

        // Register the Elasticsearch client
        $this->app->singleton(Client::class, function ($app) {
            $config = $app['config']->get('elastic-orm.connection', []);
            $clientBuilder = ClientBuilder::create();

            // Configure client
            if (isset($config['hosts']) && !empty($config['hosts'])) {
                $clientBuilder->setHosts($config['hosts']);
            }

            if (isset($config['cloud_id']) && !empty($config['cloud_id'])) {
                $clientBuilder->setElasticCloudId($config['cloud_id']);
            }

            // API key has priority over username/password
            if (isset($config['api_key']) && !empty($config['api_key'])) {
                $clientBuilder->setApiKey($config['api_key']);
            } elseif (isset($config['username']) && isset($config['password'])) {
                $clientBuilder->setBasicAuthentication($config['username'], $config['password']);
            }

            // SSL verification
            if (isset($config['ssl_verification'])) {
                $clientBuilder->setSSLVerification($config['ssl_verification']);
            }

            // Timeouts
            $httpClientOptions = [];
            
            if (isset($config['connection_timeout'])) {
                $httpClientOptions['connect_timeout'] = $config['connection_timeout'];
            }

            if (isset($config['request_timeout'])) {
                $httpClientOptions['timeout'] = $config['request_timeout'];
            }
            
            if (!empty($httpClientOptions)) {
                $clientBuilder->setHttpClient(
                    new \GuzzleHttp\Client($httpClientOptions)
                );
            }

            // Retries
            if (isset($config['retry_on_failure']) && $config['retry_on_failure']) {
                $clientBuilder->setRetries(
                    $config['max_retries'] ?? 3
                );
            }

            return $clientBuilder->build();
        });

        // Register ElasticManager
        $this->app->singleton(ElasticManager::class, function ($app) {
            return new ElasticManager(
                $app->make(Client::class),
                $app['config']->get('elastic-orm')
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../config/elastic-orm.php' => config_path('elastic-orm.php'),
        ], 'config');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ElasticCreateIndex::class,
                ElasticDropIndex::class,
                ElasticMappingUpdate::class,
                ElasticReindex::class,
            ]);
        }
    }
} 