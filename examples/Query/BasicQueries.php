<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Examples\Query;

use Fereydooni\LaravelElastoquent\Models\User;

class BasicQueries
{
    public function examples()
    {
        // Basic where clause
        $users = User::where('name', 'John')->get();
        
        // Where with operator
        $users = User::where('age', '>', 18)->get();
        
        // Multiple where clauses
        $users = User::where('name', 'John')
                    ->where('age', '>', 18)
                    ->get();
        
        // Or where clause
        $users = User::where('name', 'John')
                    ->orWhere('name', 'Jane')
                    ->get();
        
        // Where in clause
        $users = User::whereIn('id', [1, 2, 3])->get();
        
        // Where null clause
        $users = User::whereNull('deleted_at')->get();
        
        // Where not null clause
        $users = User::whereNotNull('email')->get();
        
        // Order by clause
        $users = User::orderBy('created_at', 'desc')->get();
        
        // Limit and offset
        $users = User::limit(10)->offset(20)->get();
        
        // Select specific fields
        $users = User::select(['id', 'name', 'email'])->get();
        
        // Distinct results
        $users = User::distinct()->get();
        
        // Combining multiple clauses
        $users = User::where('status', 'active')
                    ->whereIn('role', ['admin', 'editor'])
                    ->whereNotNull('email')
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();
    }
} 