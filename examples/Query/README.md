# Laravel Elastoquent Query Examples

This directory contains examples of how to use various query methods in Laravel Elastoquent. Each file demonstrates different aspects of querying Elasticsearch using an Eloquent-like interface.

## Example Files

### 1. BasicQueries.php
Demonstrates basic query methods:
- `where()`
- `orWhere()`
- `whereIn()`
- `whereNull()`
- `whereNotNull()`
- `orderBy()`
- `limit()`
- `offset()`
- `select()`
- `distinct()`

### 2. RelationshipQueries.php
Shows how to work with relationships:
- `with()` (eager loading)
- `has()`
- `orHas()`
- `doesntHave()`
- `whereHas()`
- `whereDoesntHave()`

### 3. PaginationQueries.php
Demonstrates pagination and chunking:
- `paginate()`
- `simplePaginate()`
- `cursorPaginate()`
- `chunk()`
- `cursor()`

### 4. CrudOperations.php
Shows CRUD operations:
- `create()`
- `make()`
- `forceCreate()`
- `firstOrCreate()`
- `firstOrNew()`
- `updateOrCreate()`
- `update()`
- `delete()`
- `forceDelete()`
- `destroy()`
- `truncate()`
- `save()`
- `fill()`
- `push()`
- `touch()`

## Usage

Each example file contains a class with an `examples()` method that demonstrates various query scenarios. You can use these examples as a reference for implementing similar functionality in your application.

## Notes

1. All examples use the `User` model as a reference, but you can apply these methods to any model that extends the base `Model` class.
2. The examples assume you have properly configured your Elasticsearch connection and have the necessary indices and mappings set up.
3. Some examples may require additional setup, such as defining relationships or configuring timestamps.
4. The examples are organized by functionality to make it easier to find specific query patterns.

## Best Practices

1. Always use proper indexing and mapping for your Elasticsearch fields
2. Consider using bulk operations for large datasets
3. Use appropriate pagination methods based on your use case
4. Implement proper error handling for Elasticsearch operations
5. Consider using caching for frequently accessed data
6. Monitor query performance and optimize as needed 