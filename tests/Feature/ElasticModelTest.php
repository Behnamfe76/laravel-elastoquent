<?php

use Fereydooni\LaravelElastoquent\Attributes\ElasticField;
use Fereydooni\LaravelElastoquent\Attributes\ElasticModel;
use Fereydooni\LaravelElastoquent\Managers\ElasticManager;
use Fereydooni\LaravelElastoquent\Models\Model;
use Mockery;

#[ElasticModel(index: 'test_users', settings: ['number_of_shards' => 1])]
class TestUser extends Model
{
    #[ElasticField(type: 'text')]
    public string $name;

    #[ElasticField(type: 'keyword')]
    public string $email;

    #[ElasticField(type: 'integer')]
    public int $age;
}

beforeEach(function () {
    // Mock the Elastic Manager to avoid real Elasticsearch requests
    $mock = Mockery::mock(ElasticManager::class);
    
    // Setup expected method calls
    $mock->shouldReceive('index')->andReturn(true);
    $mock->shouldReceive('get')->andReturn([
        '_id' => '123',
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30,
    ]);
    $mock->shouldReceive('search')->andReturn([
        'hits' => [
            [
                '_id' => '123',
                '_source' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'age' => 30,
                ],
            ],
        ],
        'total' => 1,
    ]);
    $mock->shouldReceive('delete')->andReturn(true);
    $mock->shouldReceive('bulkIndex')->andReturn([
        'success' => 2,
        'failed' => 0,
        'total' => 2,
        'errors' => [],
    ]);
    $mock->shouldReceive('getConfig')->andReturn(true);
    $mock->shouldReceive('createIndex')->andReturn(true);
    $mock->shouldReceive('updateMapping')->andReturn(true);
    $mock->shouldReceive('deleteIndex')->andReturn(true);

    // Set the mock
    TestUser::setElasticManager($mock);
});

it('can create model instance', function () {
    $user = new TestUser([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30,
    ]);

    expect($user->name)->toBe('John Doe');
    expect($user->email)->toBe('john@example.com');
    expect($user->age)->toBe(30);
});

it('can save model', function () {
    $user = new TestUser([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30,
    ]);

    $result = $user->save();

    expect($result)->toBeTrue();
    expect($user->exists)->toBeTrue();
    expect($user->getId())->not->toBeNull();
});

it('can find model by id', function () {
    $user = TestUser::find('123');

    expect($user)->toBeInstanceOf(TestUser::class);
    expect($user->getId())->toBe('123');
    expect($user->name)->toBe('John Doe');
    expect($user->email)->toBe('john@example.com');
    expect($user->age)->toBe(30);
});

it('can update model', function () {
    $user = TestUser::find('123');
    $user->name = 'Jane Doe';
    $result = $user->save();

    expect($result)->toBeTrue();
    expect($user->name)->toBe('Jane Doe');
});

it('can delete model', function () {
    $user = TestUser::find('123');
    $result = $user->delete();

    expect($result)->toBeTrue();
});

it('can bulk index models', function () {
    $result = TestUser::bulkIndex([
        [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ],
        [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'age' => 28,
        ],
    ]);

    expect($result['success'])->toBe(2);
    expect($result['failed'])->toBe(0);
});

it('can query models', function () {
    $users = TestUser::where('name', 'John Doe')->get();

    expect($users)->toHaveCount(1);
    expect($users->first())->toBeInstanceOf(TestUser::class);
    expect($users->first()->name)->toBe('John Doe');
});

it('can get mappings from attributes', function () {
    $mappings = TestUser::getMappings();

    expect($mappings)->toHaveKey('properties');
    expect($mappings['properties'])->toHaveKey('name');
    expect($mappings['properties'])->toHaveKey('email');
    expect($mappings['properties'])->toHaveKey('age');
    
    expect($mappings['properties']['name']['type'])->toBe('text');
    expect($mappings['properties']['email']['type'])->toBe('keyword');
    expect($mappings['properties']['age']['type'])->toBe('integer');
});

it('can get index name', function () {
    $indexName = TestUser::getIndexName();
    expect($indexName)->toBe('test_users');
});

it('can convert to array', function () {
    $user = new TestUser([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30,
    ]);

    $array = $user->toArray();
    
    expect($array)->toBeArray();
    expect($array['name'])->toBe('John Doe');
    expect($array['email'])->toBe('john@example.com');
    expect($array['age'])->toBe(30);
});

it('can create and drop index', function () {
    $createResult = TestUser::createIndex();
    expect($createResult)->toBeTrue();

    $dropResult = TestUser::dropIndex();
    expect($dropResult)->toBeTrue();
}); 