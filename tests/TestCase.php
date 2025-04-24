<?php

namespace Fereydooni\LaravelElastoquent\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            'Fereydooni\LaravelElastoquent\ElasticORMServiceProvider',
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Set up Elasticsearch configuration
        $app['config']->set('elastic-orm.connection.hosts', ['localhost:9200']);
        $app['config']->set('elastic-orm.index_prefix', 'test_');
        $app['config']->set('elastic-orm.soft_deletes', true);
        $app['config']->set('elastic-orm.bulk_size', 100);
    }
} 