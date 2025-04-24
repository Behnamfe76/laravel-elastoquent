<?php

// Example demonstrating the use of Spatie Data DTOs with Laravel Elastoquent

use Fereydooni\LaravelElastoquent\Attributes\ElasticField;
use Fereydooni\LaravelElastoquent\Attributes\ElasticModel;
use Fereydooni\LaravelElastoquent\Data\ElasticDocument;
use Fereydooni\LaravelElastoquent\Models\Model;
use Spatie\LaravelData\Data;
use Fereydooni\LaravelElastoquent\Enums\ElasticsearchFieldType;

// 1. Define your Elasticsearch model as before
#[ElasticModel(index: 'products')]
class Product extends Model
{
    #[ElasticField(type: ElasticsearchFieldType::TEXT)]
    public string $name;

    #[ElasticField(type: ElasticsearchFieldType::KEYWORD)]
    public string $sku;

    #[ElasticField(type: ElasticsearchFieldType::DOUBLE)]
    public float $price;

    #[ElasticField(type: ElasticsearchFieldType::TEXT)]
    public string $description;

    #[ElasticField(type: ElasticsearchFieldType::KEYWORD)]
    public string $category;
}

// 2. Define a Spatie Data class for your model
class ProductData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $sku,
        public float $price,
        public string $description,
        public string $category,
        public ?float $score = null,
    ) {
    }

    // You can add transformation methods here
    public static function fromElasticDocument(ElasticDocument $document): self
    {
        return new self(
            id: $document->id,
            name: $document->field('name'),
            sku: $document->field('sku'),
            price: (float)$document->field('price', 0),
            description: $document->field('description', ''),
            category: $document->field('category', ''),
            score: $document->score,
        );
    }
}

// 3. Define a custom document class that extends ElasticDocument
class ProductDocument extends ElasticDocument
{
    // Add custom methods specific to products
    public function getFormattedPrice(): string
    {
        return '$' . number_format($this->field('price', 0), 2);
    }

    public function getPriceWithTax(float $taxRate = 0.1): float
    {
        $price = (float)$this->field('price', 0);
        return $price * (1 + $taxRate);
    }
}

// 4. Usage examples

// Basic search with standard ElasticDocument DTOs
$results = Product::query()
    ->where('category', 'electronics')
    ->asDocument()  // Use ElasticDocument DTOs
    ->get();

// Using a custom document class
$results = Product::query()
    ->where('price', '>=', 100)
    ->asData(ProductDocument::class)  // Use custom document DTOs
    ->get();

// Access document properties
foreach ($results as $product) {
    echo "Product: {$product->field('name')} - Price: {$product->getFormattedPrice()}\n";
    echo "Price with tax: {$product->getPriceWithTax()}\n";
}

// Convert to Spatie Data objects
$productDataCollection = $results->map(function (ProductDocument $doc) {
    return ProductData::fromElasticDocument($doc);
});

// Pagination with DTOs
$paginatedResults = Product::query()
    ->asDocument()
    ->paginate(20);  // 20 items per page

// Get the current page items as DTOs
$items = $paginatedResults->items();

// Total results
echo "Showing page {$paginatedResults->currentPage()} of {$paginatedResults->lastPage()} pages\n";
echo "Total results: {$paginatedResults->total()}\n";

// Advanced filtering with DTOs
$filteredResults = Product::query()
    ->where('category', 'electronics')
    ->where('price', '>=', 100)
    ->where('price', '<=', 500)
    ->orderBy('price', 'asc')
    ->asData(ProductDocument::class)
    ->get();

// Aggregate results
$stats = Product::query()
    ->aggregate('avg_price', ['avg' => ['field' => 'price']])
    ->aggregate('categories', ['terms' => ['field' => 'category.keyword']])
    ->asDocument()
    ->get();

// Access aggregations
$avgPrice = $stats->getAggregations()['avg_price']['value'] ?? 0;
$categories = $stats->getAggregations()['categories']['buckets'] ?? [];

echo "Average price: $avgPrice\n";
echo "Categories:\n";
foreach ($categories as $category) {
    echo "- {$category['key']}: {$category['doc_count']} products\n";
} 