<?php

namespace Fereydooni\LaravelElastoquent\Tests;

use Fereydooni\LaravelElastoquent\Attributes\ElasticField;
use Fereydooni\LaravelElastoquent\Attributes\ElasticModel;
use Fereydooni\LaravelElastoquent\Managers\ElasticManager;
use Fereydooni\LaravelElastoquent\Models\Model;
use Mockery;
use Orchestra\Testbench\TestCase;

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

class ElasticModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock the Elastic Manager to avoid real Elasticsearch requests
        $this->mockElasticManager();
    }

    protected function getPackageProviders($app)
    {
        return [
            'Fereydooni\LaravelElastoquent\ElasticORMServiceProvider',
        ];
    }

    protected function mockElasticManager()
    {
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
    }

    /** @test */
    public function it_can_create_model_instance()
    {
        $user = new TestUser([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals(30, $user->age);
    }

    /** @test */
    public function it_can_save_model()
    {
        $user = new TestUser([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        $result = $user->save();

        $this->assertTrue($result);
        $this->assertTrue($user->exists);
        $this->assertNotNull($user->getId());
    }

    /** @test */
    public function it_can_find_model_by_id()
    {
        $user = TestUser::find('123');

        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertEquals('123', $user->getId());
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals(30, $user->age);
    }

    /** @test */
    public function it_can_update_model()
    {
        $user = TestUser::find('123');
        $user->name = 'Jane Doe';
        $result = $user->save();

        $this->assertTrue($result);
        $this->assertEquals('Jane Doe', $user->name);
    }

    /** @test */
    public function it_can_delete_model()
    {
        $user = TestUser::find('123');
        $result = $user->delete();

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_bulk_index_models()
    {
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

        $this->assertEquals(2, $result['success']);
        $this->assertEquals(0, $result['failed']);
    }

    /** @test */
    public function it_can_query_models()
    {
        $users = TestUser::where('name', 'John Doe')->get();

        $this->assertCount(1, $users);
        $this->assertInstanceOf(TestUser::class, $users->first());
        $this->assertEquals('John Doe', $users->first()->name);
    }

    /** @test */
    public function it_can_get_mappings_from_attributes()
    {
        $mappings = TestUser::getMappings();

        $this->assertArrayHasKey('properties', $mappings);
        $this->assertArrayHasKey('name', $mappings['properties']);
        $this->assertArrayHasKey('email', $mappings['properties']);
        $this->assertArrayHasKey('age', $mappings['properties']);
        
        $this->assertEquals('text', $mappings['properties']['name']['type']);
        $this->assertEquals('keyword', $mappings['properties']['email']['type']);
        $this->assertEquals('integer', $mappings['properties']['age']['type']);
    }

    /** @test */
    public function it_can_get_index_name()
    {
        $indexName = TestUser::getIndexName();
        $this->assertEquals('test_users', $indexName);
    }

    /** @test */
    public function it_can_convert_to_array()
    {
        $user = new TestUser([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        $array = $user->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals('John Doe', $array['name']);
        $this->assertEquals('john@example.com', $array['email']);
        $this->assertEquals(30, $array['age']);
    }

    /** @test */
    public function it_can_create_and_drop_index()
    {
        $createResult = TestUser::createIndex();
        $this->assertTrue($createResult);

        $dropResult = TestUser::dropIndex();
        $this->assertTrue($dropResult);
    }
} 