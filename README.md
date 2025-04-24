# Laravel Elastoquent

A robust Elasticsearch ORM for Laravel, mirroring Eloquent functionality. This package provides a familiar Eloquent-like API for indexing, querying, and managing data in Elasticsearch.

## Requirements

- PHP 8.1+
- Laravel 10.x+
- Elasticsearch 8.x

## Installation

```bash
composer require fereydooni/laravel-elastoquent
```

### Publish Configuration

```bash
php artisan vendor:publish --provider="Fereydooni\LaravelElastoquent\ElasticORMServiceProvider"
```

### Run Migrations

```bash
php artisan migrate
```

## Configuration

The package configuration is located at `config/elastic-orm.php`. Key options include:

```php
return [
    // Elasticsearch connection settings
    'connection' => [
        'hosts' => ['localhost:9200'],
        'username' => env('ELASTIC_USERNAME', null),
        'password' => env('ELASTIC_PASSWORD', null),
    ],
    
    // Default index prefix for all indices
    'index_prefix' => env('ELASTIC_INDEX_PREFIX', 'app_'),
    
    // Enable/disable soft deletes
    'soft_deletes' => true,
    
    // Bulk indexing batch size
    'bulk_size' => 1000,
];
```

## Basic Usage

### Define a Model

```php
use Fereydooni\LaravelElastoquent\Attributes\ElasticModel;
use Fereydooni\LaravelElastoquent\Attributes\ElasticField;
use Fereydooni\LaravelElastoquent\Models\Model;

#[ElasticModel(index: 'users', settings: ['number_of_shards' => 1])]
class User extends Model
{
    #[ElasticField(type: 'text', index: true)]
    public string $name;

    #[ElasticField(type: 'keyword')]
    public string $email;

    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}
```

### CRUD Operations

```php
// Create a user
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

// Find a user
$user = User::find('user_id');

// Update a user
$user->name = 'Jane Doe';
$user->save();

// Delete a user
$user->delete();
```

### Query Builder

```php
// Basic where clause
$users = User::where('name', 'John')->get();

// Advanced query
$users = User::where('name', 'like', 'J*')
    ->orWhere('email', 'contains', 'example.com')
    ->orderBy('name', 'asc')
    ->limit(10)
    ->get();

// Pagination
$users = User::where('active', true)
    ->paginate(15);
```

### Full-Text Search

```php
// Simple search
$results = User::search('John Doe')->get();

// Advanced search with filters
$results = User::search('John')
    ->filter('active', true)
    ->get();
```

### Relationships

```php
// Define relationships
class User extends Model
{
    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}

class Post extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

// Using relationships
$user = User::find('user_id');
$posts = $user->posts()->get();

// Eager loading
$users = User::with('posts')->get();
```

### Bulk Operations

```php
// Bulk create
User::bulkIndex([
    ['name' => 'Jane Doe', 'email' => 'jane@example.com'],
    ['name' => 'Bob Smith', 'email' => 'bob@example.com'],
]);
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE). 