<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Tests\Unit;

use Fereydooni\LaravelElastoquent\Attributes\ElasticField;
use Fereydooni\LaravelElastoquent\Attributes\ElasticModel;
use Fereydooni\LaravelElastoquent\Models\Model;
use Fereydooni\LaravelElastoquent\Managers\ElasticManager;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\TestCase;

#[ElasticModel('users')]
class TestUser extends Model
{
    #[ElasticField]
    public string $name;

    #[ElasticField]
    public string $email;

    public function posts()
    {
        return $this->hasMany(TestPost::class);
    }
}

#[ElasticModel('posts')]
class TestPost extends Model
{
    #[ElasticField]
    public string $title;

    #[ElasticField]
    public string $content;

    #[ElasticField]
    public string $user_id;

    public function user()
    {
        return $this->belongsTo(TestUser::class);
    }
}

class RelationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $mock = Mockery::mock(ElasticManager::class);
        
        // Setup expected method calls
        $mock->shouldReceive('index')->andReturn(true);
        $mock->shouldReceive('get')->andReturn([
            '_id' => '123',
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $mock->shouldReceive('search')->andReturn([
            'hits' => [
                [
                    '_id' => '456',
                    '_source' => [
                        'title' => 'Test Post',
                        'content' => 'Test Content',
                        'user_id' => '123',
                    ],
                ],
            ],
            'total' => 1,
        ]);
        $mock->shouldReceive('delete')->andReturn(true);
        $mock->shouldReceive('getConfig')->andReturn(true);

        // Set the mock
        TestUser::setElasticManager($mock);
        TestPost::setElasticManager($mock);
    }

    /** @test */
    public function it_can_define_has_many_relation()
    {
        $user = TestUser::find('123');
        $posts = $user->posts;

        $this->assertInstanceOf(Collection::class, $posts);
        $this->assertCount(1, $posts);
        $this->assertInstanceOf(TestPost::class, $posts->first());
        $this->assertEquals('Test Post', $posts->first()->title);
        $this->assertEquals('Test Content', $posts->first()->content);
    }

    /** @test */
    public function it_can_define_belongs_to_relation()
    {
        $post = TestPost::find('456');
        $user = $post->user;

        $this->assertInstanceOf(TestUser::class, $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
} 