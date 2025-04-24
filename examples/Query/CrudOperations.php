<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Examples\Query;

use Fereydooni\LaravelElastoquent\Models\User;

class CrudOperations
{
    public function examples()
    {
        // Create a new model
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30
        ]);
        
        // Create a model instance without saving
        $user = User::make([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com'
        ]);
        
        // Force create a model (bypass validation)
        $user = User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@example.com'
        ]);
        
        // First or create
        $user = User::firstOrCreate(
            ['email' => 'john@example.com'],
            ['name' => 'John Doe', 'age' => 30]
        );
        
        // First or new
        $user = User::firstOrNew(
            ['email' => 'jane@example.com'],
            ['name' => 'Jane Doe']
        );
        
        // Update or create
        $user = User::updateOrCreate(
            ['email' => 'john@example.com'],
            ['name' => 'John Doe Updated']
        );
        
        // Update a model
        $user = User::find(1);
        $user->update(['name' => 'John Doe Updated']);
        
        // Update multiple models
        User::where('status', 'inactive')
            ->update(['status' => 'active']);
        
        // Delete a model
        $user = User::find(1);
        $user->delete();
        
        // Force delete a model
        $user = User::find(1);
        $user->forceDelete();
        
        // Delete multiple models
        User::where('status', 'inactive')->delete();
        
        // Destroy models by ID
        User::destroy([1, 2, 3]);
        
        // Truncate the table
        User::truncate();
        
        // Save a model
        $user = new User();
        $user->name = 'New User';
        $user->email = 'new@example.com';
        $user->save();
        
        // Fill and save
        $user = new User();
        $user->fill([
            'name' => 'Filled User',
            'email' => 'filled@example.com'
        ]);
        $user->save();
        
        // Push changes
        $user = User::find(1);
        $user->name = 'Updated Name';
        $user->push();
        
        // Touch timestamp
        $user = User::find(1);
        $user->touch();
    }
} 