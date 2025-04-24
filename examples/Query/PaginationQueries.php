<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Examples\Query;

use Fereydooni\LaravelElastoquent\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\CursorPaginator;

class PaginationQueries
{
    public function examples()
    {
        // Basic pagination
        $users = User::paginate(15);
        
        // Pagination with page number
        $users = User::paginate(15, ['*'], 'page', 2);
        
        // Simple pagination (without total count)
        $users = User::simplePaginate(15);
        
        // Cursor pagination
        $users = User::cursorPaginate(15);
        
        // Cursor pagination with cursor
        $users = User::cursorPaginate(15, ['*'], 'cursor', 'eyJpZCI6IjEwIn0');
        
        // Chunking results
        User::chunk(200, function ($users) {
            foreach ($users as $user) {
                // Process each user
            }
        });
        
        // Cursor-based chunking
        foreach (User::cursor() as $user) {
            // Process each user
        }
        
        // Combining pagination with other clauses
        $users = User::where('status', 'active')
                    ->orderBy('created_at', 'desc')
                    ->paginate(15);
        
        // Pagination with eager loading
        $users = User::with('posts')
                    ->paginate(15);
        
        // Custom pagination
        $users = User::where('status', 'active')
                    ->orderBy('created_at', 'desc')
                    ->paginate(15, ['id', 'name', 'email']);
    }
} 