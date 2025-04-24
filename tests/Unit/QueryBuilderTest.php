<?php

namespace Fereydooni\LaravelElastoquent\Tests\Unit;

use Fereydooni\LaravelElastoquent\Query\Builder;
use Fereydooni\LaravelElastoquent\Data\ElasticDocument;
use Fereydooni\LaravelElastoquent\Data\ElasticCollection;
use Fereydooni\LaravelElastoquent\Managers\ElasticManager;
use Fereydooni\LaravelElastoquent\Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Collection;
use Mockery;

class TestModel {
    public static function getIndexName(): string {
        return 'test_index';
    }

    public static function newFromElasticData(array $data) {
        return new self();
    }
}

it('can build basic where queries', function () {
    $mockManager = Mockery::mock(ElasticManager::class);
    $mockManager->shouldReceive('getConfig')
        ->with('soft_deletes', true)
        ->andReturn(false);
    
    $mockManager->shouldReceive('search')
        ->once()
        ->withArgs(function ($index, $params) {
            expect($params['query']['bool']['must'])->toHaveCount(1);
            expect($params['query']['bool']['must'][0]['term'])->toHaveKey('name', 'John');
            return true;
        })
        ->andReturn([
            'hits' => [
                'hits' => [
                    ['_id' => '1', '_score' => 1.0, '_source' => []]
                ],
                'total' => ['value' => 1]
            ]
        ]);
    
    $builder = new Builder($mockManager);
    $builder->setModel(TestModel::class)->where('name', 'John')->get();
});

it('can build complex boolean queries', function () {
    $mockManager = Mockery::mock(ElasticManager::class);
    $mockManager->shouldReceive('getConfig')
        ->with('soft_deletes', true)
        ->andReturn(false);
    
    $mockManager->shouldReceive('search')
        ->once()
        ->withArgs(function ($index, $params) {
            expect($params['query']['bool']['must'])->toHaveCount(2);
            expect($params['query']['bool']['should'])->toHaveCount(1);
            return true;
        })
        ->andReturn([
            'hits' => [
                'hits' => [
                    ['_id' => '1', '_score' => 1.0, '_source' => []]
                ],
                'total' => ['value' => 1]
            ]
        ]);
    
    $builder = new Builder($mockManager);
    $builder->setModel(TestModel::class)
            ->where('name', 'John')
            ->orWhere('email', 'john@example.com')
            ->where('age', '>', 25)
            ->get();
});

it('can handle pagination', function () {
    $mockManager = Mockery::mock(ElasticManager::class);
    $mockManager->shouldReceive('getConfig')
        ->with('soft_deletes', true)
        ->andReturn(false);
    
    $mockManager->shouldReceive('search')
        ->once()
        ->withArgs(function ($index, $params, $options) {
            expect($options['from'])->toBe(10);
            expect($options['size'])->toBe(10);
            return true;
        })
        ->andReturn([
            'hits' => [
                'hits' => [
                    ['_id' => '1', '_score' => 1.0, '_source' => []]
                ],
                'total' => ['value' => 1]
            ]
        ]);
    
    $builder = new Builder($mockManager);
    $builder->setModel(TestModel::class)->forPage(2, 10)->get();
});

it('can return results as DTOs', function () {
    $mockManager = Mockery::mock(ElasticManager::class);
    $mockManager->shouldReceive('getConfig')
        ->with('soft_deletes', true)
        ->andReturn(false);
    
    $mockManager->shouldReceive('search')
        ->andReturn([
            'hits' => [
                'hits' => [
                    ['_id' => '1', '_score' => 1.0, '_source' => ['name' => 'John']],
                    ['_id' => '2', '_score' => 0.8, '_source' => ['name' => 'Jane']],
                ],
                'total' => ['value' => 2]
            ]
        ]);
    
    $builder = new Builder($mockManager);
    $results = $builder->setModel(TestModel::class)->get();
    
    expect($results)->toBeInstanceOf(Collection::class);
    expect($results->count())->toBe(2);
});

it('can handle vector search queries', function () {
    $mockManager = Mockery::mock(ElasticManager::class);
    $mockManager->shouldReceive('getConfig')
        ->with('soft_deletes', true)
        ->andReturn(false);
    
    $mockManager->shouldReceive('search')
        ->once()
        ->withArgs(function ($index, $params) {
            expect($params['knn'])->toHaveKey('field', 'embedding');
            expect($params['knn'])->toHaveKey('query_vector', [0.1, 0.2, 0.3]);
            expect($params['knn'])->toHaveKey('k', 10);
            return true;
        })
        ->andReturn([
            'hits' => [
                'hits' => [
                    ['_id' => '1', '_score' => 1.0, '_source' => []]
                ],
                'total' => ['value' => 1]
            ]
        ]);
    
    $builder = new Builder($mockManager);
    $vector = [0.1, 0.2, 0.3];
    $builder->setModel(TestModel::class)->vectorSearch('embedding', $vector, k: 10)->get();
});

it('can handle semantic search queries', function () {
    // Mock the HTTP client with a successful response
    $mock = new MockHandler([
        new Response(200, [], json_encode([
            'embedding' => [0.1, 0.2, 0.3]
        ]))
    ]);
    $handlerStack = HandlerStack::create($mock);
    $client = new Client(['handler' => $handlerStack]);

    // Mock the ElasticManager
    $mockManager = Mockery::mock(ElasticManager::class);
    $mockManager->shouldReceive('getConfig')
        ->with('soft_deletes', true)
        ->andReturn(false);
    
    $mockManager->shouldReceive('getConfig')
        ->with('embedding_services.default')
        ->andReturn([
            'provider' => 'local',
            'url' => 'http://localhost:8080/embed',
            'model' => 'default'
        ]);

    $mockManager->shouldReceive('search')
        ->once()
        ->withArgs(function ($index, $params) {
            expect($params['knn'])->toHaveKey('field', 'embedding');
            expect($params['knn'])->toHaveKey('query_vector');
            expect($params['knn'])->toHaveKey('k', 10);
            return true;
        })
        ->andReturn([
            'hits' => [
                'hits' => [
                    ['_id' => '1', '_score' => 1.0, '_source' => []]
                ],
                'total' => ['value' => 1]
            ]
        ]);
    
    $builder = new Builder($mockManager);
    $builder->setModel(TestModel::class)
        ->setHttpClient($client)
        ->semanticSearch('test query', 'embedding')
        ->get();
});

it('can handle hybrid search queries', function () {
    $mockManager = Mockery::mock(ElasticManager::class);
    $mockManager->shouldReceive('getConfig')
        ->with('soft_deletes', true)
        ->andReturn(false);
    
    $mockManager->shouldReceive('search')
        ->once()
        ->withArgs(function ($index, $params) {
            expect($params['query']['bool']['must'])->toHaveCount(2);
            expect($params['query']['bool']['must'][0]['multi_match'])->toHaveKey('boost', 3.0);
            expect($params['query']['bool']['must'][1]['script_score'])->toHaveKey('boost', 7.0);
            return true;
        })
        ->andReturn([
            'hits' => [
                'hits' => [
                    ['_id' => '1', '_score' => 1.0, '_source' => []]
                ],
                'total' => ['value' => 1]
            ]
        ]);
    
    $builder = new Builder($mockManager);
    $vector = [0.1, 0.2, 0.3];
    $builder->setModel(TestModel::class)
            ->hybridSearch(
                'machine learning',
                ['content'],
                'embedding',
                $vector,
                textWeight: 0.3,
                vectorWeight: 0.7
            )->get();
}); 