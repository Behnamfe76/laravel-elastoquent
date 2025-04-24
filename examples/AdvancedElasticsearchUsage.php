<?php

// Example of using advanced Elasticsearch features with Laravel Elastoquent

use Fereydooni\LaravelElastoquent\Attributes\ElasticField;
use Fereydooni\LaravelElastoquent\Attributes\ElasticModel;
use Fereydooni\LaravelElastoquent\Models\Model;

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
    #[ElasticField(type: 'dense_vector', options: ['dims' => 768])]
    public array $embedding;
    
    // Sparse vector field for ELSER
    #[ElasticField(type: 'sparse_vector')]
    public array $content_embedding;
    
    // Text field for normal search
    #[ElasticField(type: 'text', options: ['analyzer' => 'content_analyzer'])]
    public string $content;
    
    // Field for sorting/filtering
    #[ElasticField(type: 'keyword')]
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
    ->vectorSearch('embedding', $queryVector, k: 10)
    ->get();

// Sparse vector search with ELSER
$results = Document::query()
    ->sparseVectorSearch("How does AI work?", 'content_embedding')
    ->get();

// Hybrid search with both text and vectors
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

// Semantic search using embedding service
$results = Document::query()
    ->semanticSearch(
        "What are neural networks?", 
        'embedding', 
        'openai'
    )
    ->get();

// Semantic reranking
$results = Document::query()
    ->search("machine learning")
    ->semanticRerank("machine learning applications")
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

// 5. Using retrievers
$results = Document::query()
    ->retriever(
        "How does machine learning work?",
        ['content'],
        'hybrid'
    )
    ->get(); 