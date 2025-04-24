<?php

// Example of using advanced Elasticsearch features with Laravel Elastoquent

use Fereydooni\LaravelElastoquent\Attributes\ElasticField;
use Fereydooni\LaravelElastoquent\Attributes\ElasticModel;
use Fereydooni\LaravelElastoquent\Models\Model;
use Fereydooni\LaravelElastoquent\Enums\ElasticsearchFieldType;

// 1. Advanced model with sparse vectors for ELSER
#[ElasticModel(
    index: 'documents',
    settings: [
        'number_of_shards' => 1,
        'number_of_replicas' => 0,
        'analysis' => [
            'analyzer' => [
                'content_analyzer' => [
                    'type' => 'custom',
                    'tokenizer' => 'standard',
                    'filter' => ['lowercase', 'stop', 'snowball']
                ]
            ]
        ]
    ]
)]
class Document extends Model
{
    // Dense vector field for standard vector search
    #[ElasticField(type: ElasticsearchFieldType::DENSE_VECTOR, options: ['dims' => 768])]
    public array $embedding;
    
    // Sparse vector field for ELSER
    #[ElasticField(type: ElasticsearchFieldType::SPARSE_VECTOR)]
    public array $content_embedding;
    
    // Text field for normal search
    #[ElasticField(type: ElasticsearchFieldType::TEXT, options: ['analyzer' => 'content_analyzer'])]
    public string $content;
    
    // Field for sorting/filtering
    #[ElasticField(type: ElasticsearchFieldType::KEYWORD)]
    public string $category;
    
    // Automatically use timestamps
    public bool $timestamps = true;
}

// 2. Creating a document with vectors
$document = new Document();
$document->content = "This is a sample document about artificial intelligence and machine learning.";
$document->category = "technology";

// Add embedding vectors (usually generated via API)
$document->embedding = [ /* 768-dimensional vector */ ];
$document->content_embedding = [ /* sparse vector tokens */ ];
$document->save();

// 3. Using advanced search features

// Basic full-text search
$results = Document::search("artificial intelligence")
    ->where('category', 'technology')
    ->get();

// Vector search with KNN
$results = Document::query()
    ->vectorSearch('embedding', $queryVector, 10)
    ->get();

// Sparse vector search with ELSER
$results = Document::query()
    ->sparseVectorSearch('content_embedding', ['token1' => 0.5, 'token2' => 0.3])
    ->get();

// Hybrid search with both text and vectors
$results = Document::query()
    ->hybridSearch(
        "machine learning applications",
        ['content'],
        ['embedding' => $queryVector]
    )
    ->get();

// Semantic search using embedding service
$results = Document::query()
    ->semanticSearch(
        "What are neural networks?",
        'openai',
        ['embedding' => $queryVector]
    )
    ->get();

// Using ES|QL for advanced queries
$results = Document::query()
    ->esql("FROM documents WHERE match(content, 'artificial intelligence') AND category = 'technology' | LIMIT 10")
    ->get();

// 4. Performance optimizations
$results = Document::query()
    ->select(['content', 'category']) // Only return specific fields
    ->exclude(['embedding']) // Exclude large vector fields
    ->trackTotalHits(false) // Don't count total hits for better performance
    ->search("machine learning")
    ->get();

// 5. Using search with fields
$results = Document::query()
    ->search(
        "How does machine learning work?",
        ['content']
    )
    ->get(); 