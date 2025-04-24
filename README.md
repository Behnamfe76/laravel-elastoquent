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

### Advanced Elasticsearch Features

#### Vector Search

```php
// Define a model with vector fields
#[ElasticModel(index: 'documents')]
class Document extends Model
{
    #[ElasticField(type: 'dense_vector', options: ['dims' => 768])]
    public array $embedding;
    
    #[ElasticField(type: 'text')]
    public string $content;
}

// Perform KNN vector search
$results = Document::query()
    ->vectorSearch('embedding', $queryVector, k: 10)
    ->get();
```

#### Semantic Search

```php
// Using embedding services (OpenAI, HuggingFace, etc.)
$results = Document::query()
    ->semanticSearch(
        "What is machine learning?", 
        'embedding', 
        'openai'
    )
    ->get();

// Sparse vector search with ELSER
$results = Document::query()
    ->sparseVectorSearch("How does AI work?", 'content_embedding')
    ->get();
```

#### Hybrid Search & Semantic Reranking

```php
// Hybrid search combining text and vector scores
$results = Document::query()
    ->hybridSearch(
        "machine learning applications", 
        ['content'], 
        'embedding',
        $queryVector, 
        textWeight: 0.3, 
        vectorWeight: 0.7
    )
    ->get();
    
// Semantic reranking to improve relevance
$results = Document::query()
    ->search("artificial intelligence")
    ->semanticRerank("How do neural networks work?")
    ->get();
```

#### ES|QL Support

```php
// Using Elasticsearch's SQL-like query language
$results = Document::query()
    ->esql("FROM documents WHERE match(content, 'artificial intelligence') | LIMIT 10")
    ->get();
```

#### Performance Optimizations

```php
// Field selection and exclusion
$results = Document::query()
    ->select(['content', 'title'])    // Only return these fields
    ->exclude(['embedding'])          // Exclude large vector fields
    ->trackTotalHits(false)           // Disable exact hit counting for better performance
    ->search("machine learning")
    ->get();
```

### Data Transfer Objects & Pagination

Laravel Elastoquent integrates with [spatie/laravel-data](https://github.com/spatie/laravel-data) to provide structured DTOs for your Elasticsearch results:

```php
// Using the built-in ElasticDocument DTO
$results = Document::query()
    ->where('category', 'technology')
    ->asDocument()  // Return results as ElasticDocument DTOs
    ->get();

// Using a custom DTO that extends ElasticDocument
class ProductDocument extends ElasticDocument 
{
    public function getFormattedPrice(): string
    {
        return '$' . number_format($this->field('price', 0), 2);
    }
}

$results = Product::query()
    ->asData(ProductDocument::class)  // Use custom DTOs
    ->get();

// Enhanced pagination with DTOs
$paginatedResults = Product::query()
    ->where('active', true)
    ->asDocument()
    ->paginate(15);

// Accessing pagination metadata
echo "Showing {$paginatedResults->count()} of {$paginatedResults->total()} results";
echo "Page {$paginatedResults->currentPage()} of {$paginatedResults->lastPage()}";

// Converting to Spatie Data collection
use Spatie\LaravelData\Data;

class ProductData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public float $price,
    ) {}
    
    // Transform from ElasticDocument to Spatie Data
    public static function fromElasticDocument(ElasticDocument $doc): self
    {
        return new self(
            id: $doc->id,
            name: $doc->field('name'),
            price: (float)$doc->field('price', 0)
        );
    }
}

// Map the results to Spatie Data objects
$dataCollection = $results->map(fn($doc) => ProductData::fromElasticDocument($doc));
```

### Retriever API Support

```php
// Using retrievers for advanced semantic search
$results = Document::query()
    ->retriever(
        "How does machine learning work?",
        ['content'],
        'hybrid'  // Use hybrid retrieval (text + semantic)
    )
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

## User Model Example

Here's an example of how to use the User model with Elasticsearch and Spatie Data:

```php
use Fereydooni\LaravelElastoquent\Examples\Models\User;
use Fereydooni\LaravelElastoquent\Examples\Models\UserData;

// Create a new user
$user = new User([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'hashed_password',
    'age' => 30,
    'is_active' => true,
    'roles' => ['user', 'admin'],
    'profile' => [
        'bio' => 'Software Developer',
        'location' => 'New York',
        'website' => 'https://example.com',
        'avatar' => 'https://example.com/avatar.jpg'
    ]
]);

// Save the user to Elasticsearch
$user->save();

// Find a user by ID
$user = User::find('user_id');

// Update user attributes
$user->setName('Jane Doe');
$user->setAge(31);
$user->save();

// Delete a user
$user->delete();

// Query users
$activeUsers = User::where('is_active', true)
    ->where('age', '>', 25)
    ->get();

// Convert to Spatie Data DTO
$userData = $user->toData();

// Use DTO in API responses
return response()->json($userData->toArray());

// Or use Spatie Data's collection
$usersData = $activeUsers->map(fn($user) => $user->toData());
return response()->json($usersData->toArray());
```

### Available Query Methods

The User model supports all standard Eloquent-like query methods:

```php
// Basic queries
User::all();
User::find('id');
User::first();
User::where('age', '>', 25)->get();

// Pagination
User::paginate(15);
User::simplePaginate(15);
User::cursorPaginate(15);

// Aggregations
User::where('is_active', true)->count();
User::where('age', '>', 25)->exists();

// Nested queries
User::where('profile.location', 'New York')->get();

// Sorting
User::orderBy('created_at', 'desc')->get();

// Limiting
User::limit(10)->get();
User::offset(5)->limit(10)->get();
```

### Elasticsearch Features

The User model includes several Elasticsearch-specific features:

1. **Nested Fields**: The `profile` field is defined as a nested type, allowing for complex queries on nested objects.

2. **Field Types**:
   - `text` for full-text search (name, profile.bio)
   - `keyword` for exact matches (email, roles)
   - `integer` for numeric values (age)
   - `boolean` for true/false values (is_active)
   - `date` for timestamps (created_at, updated_at)

3. **Index Settings**:
   - Single shard for consistent ordering
   - One replica for high availability

4. **Mapping**:
   - Custom field mappings for optimal search performance
   - Nested object support for complex data structures

5. **Spatie Data Integration**:
   - Automatic snake_case to camelCase conversion
   - Type-safe data transfer objects
   - Built-in validation and transformation
   - Easy serialization to JSON

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE). 