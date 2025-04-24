<?php

namespace Fereydooni\LaravelElastoquent\Tests\Unit;

use Fereydooni\LaravelElastoquent\Data\ElasticDocument;
use Fereydooni\LaravelElastoquent\Tests\TestCase;

it('can create document from elasticsearch hit', function () {
    $hit = [
        '_id' => '1',
        '_score' => 0.8,
        '_source' => [
            'name' => 'John',
            'age' => 30,
            'email' => 'john@example.com'
        ]
    ];
    
    $document = ElasticDocument::fromElasticsearchHit($hit);
    
    expect($document->id)->toBe('1');
    expect($document->score)->toBe(0.8);
    expect($document->field('name'))->toBe('John');
    expect($document->field('age'))->toBe(30);
    expect($document->field('email'))->toBe('john@example.com');
});

it('can access properties using array syntax', function () {
    $hit = [
        '_id' => '1',
        '_score' => 0.8,
        '_source' => ['name' => 'John']
    ];
    
    $document = ElasticDocument::fromElasticsearchHit($hit);
    
    expect($document->field('name'))->toBe('John');
    expect($document->id)->toBe('1');
    expect($document->score)->toBe(0.8);
});

it('can check if property exists', function () {
    $hit = [
        '_id' => '1',
        '_score' => 0.8,
        '_source' => ['name' => 'John']
    ];
    
    $document = ElasticDocument::fromElasticsearchHit($hit);
    
    expect($document->field('name'))->toBe('John');
    expect($document->field('non_existent'))->toBeNull();
});

it('can convert to array', function () {
    $hit = [
        '_id' => '1',
        '_score' => 0.8,
        '_source' => [
            'name' => 'John',
            'age' => 30
        ]
    ];
    
    $document = ElasticDocument::fromElasticsearchHit($hit);
    $array = $document->toFlatArray();
    
    expect($array)->toHaveKey('_id', '1');
    expect($array)->toHaveKey('_score', 0.8);
    expect($array)->toHaveKey('name', 'John');
    expect($array)->toHaveKey('age', 30);
});

it('can handle nested arrays', function () {
    $hit = [
        '_id' => '1',
        '_score' => 0.8,
        '_source' => [
            'name' => 'John',
            'address' => [
                'street' => '123 Main St',
                'city' => 'New York'
            ]
        ]
    ];
    
    $document = ElasticDocument::fromElasticsearchHit($hit);
    
    expect($document->field('address'))->toBe([
        'street' => '123 Main St',
        'city' => 'New York'
    ]);
});

it('can handle null values', function () {
    $hit = [
        '_id' => '1',
        '_score' => 0.8,
        '_source' => [
            'name' => 'John',
            'middle_name' => null
        ]
    ];
    
    $document = ElasticDocument::fromElasticsearchHit($hit);
    
    expect($document->field('middle_name'))->toBeNull();
}); 