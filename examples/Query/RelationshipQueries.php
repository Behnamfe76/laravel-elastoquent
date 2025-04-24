<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Examples\Query;

use Fereydooni\LaravelElastoquent\Models\User;
use Fereydooni\LaravelElastoquent\Models\Post;

class RelationshipQueries
{
    public function examples()
    {
        // Eager loading relationships
        $users = User::with('posts')->get();
        
        // Eager loading multiple relationships
        $users = User::with(['posts', 'comments'])->get();
        
        // Nested eager loading
        $users = User::with(['posts.comments'])->get();
        
        // Has relationship condition
        $users = User::has('posts')->get();
        
        // Has relationship with count condition
        $users = User::has('posts', '>=', 5)->get();
        
        // Or has relationship condition
        $users = User::has('posts')
                    ->orHas('comments')
                    ->get();
        
        // Doesn't have relationship condition
        $users = User::doesntHave('posts')->get();
        
        // Where has relationship condition
        $users = User::whereHas('posts', function ($query) {
            $query->where('status', 'published');
        })->get();
        
        // Where doesn't have relationship condition
        $users = User::whereDoesntHave('posts', function ($query) {
            $query->where('status', 'draft');
        })->get();
        
        // Combining relationship conditions
        $users = User::has('posts')
                    ->whereHas('posts', function ($query) {
                        $query->where('status', 'published')
                              ->where('created_at', '>', now()->subDays(7));
                    })
                    ->with(['posts' => function ($query) {
                        $query->orderBy('created_at', 'desc');
                    }])
                    ->get();
    }
} 