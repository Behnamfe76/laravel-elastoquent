<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class ElasticDropIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:drop-index 
                            {model? : The model class to drop an index for}
                            {--all : Drop indices for all models}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Drop Elasticsearch indices for models';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('all')) {
            if (!$this->option('force') && !$this->confirm('Are you sure you want to drop ALL indices? This action cannot be undone.')) {
                $this->info('Operation cancelled.');
                return 0;
            }

            return $this->dropAllIndices();
        }

        $model = $this->argument('model');

        if (empty($model)) {
            $this->error('Please provide a model class or use the --all option.');
            return 1;
        }

        if (!$this->option('force') && !$this->confirm("Are you sure you want to drop the index for {$model}? This action cannot be undone.")) {
            $this->info('Operation cancelled.');
            return 0;
        }

        return $this->dropIndex($model);
    }

    /**
     * Drop an index for a specific model.
     */
    protected function dropIndex(string $model): int
    {
        // Add namespace if not provided
        if (!Str::startsWith($model, 'App\\')) {
            $model = 'App\\Models\\' . $model;
        }

        if (!class_exists($model)) {
            $this->error("Model {$model} not found.");
            return 1;
        }

        try {
            $reflection = new ReflectionClass($model);

            if (!$reflection->isSubclassOf('Fereydooni\\LaravelElastoquent\\Models\\Model')) {
                $this->error("Class {$model} is not an ElasticModel.");
                return 1;
            }

            $indexName = $model::getIndexName();
            $result = $model::dropIndex();

            if ($result) {
                $this->info("Successfully dropped index for {$model} ({$indexName}).");
                return 0;
            }

            $this->error("Failed to drop index for {$model}.");
            return 1;
        } catch (\Exception $e) {
            $this->error("Error dropping index for {$model}: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Drop indices for all Elastic models.
     */
    protected function dropAllIndices(): int
    {
        $models = $this->findElasticModels();
        $success = true;

        if (empty($models)) {
            $this->info('No Elastic models found.');
            return 0;
        }

        $this->info('Dropping indices for ' . count($models) . ' models...');

        foreach ($models as $model) {
            $result = $this->dropIndex($model);
            if ($result !== 0) {
                $success = false;
            }
        }

        if ($success) {
            $this->info('All indices dropped successfully.');
            return 0;
        }

        $this->error('Some indices failed to drop. See above for details.');
        return 1;
    }

    /**
     * Find all Elastic models in the application.
     */
    protected function findElasticModels(): array
    {
        $models = [];
        $modelsPath = app_path('Models');

        if (!is_dir($modelsPath)) {
            return $models;
        }

        $finder = new Finder();
        $finder->files()->in($modelsPath)->name('*.php');

        foreach ($finder as $file) {
            $className = 'App\\Models\\' . $file->getBasename('.php');

            if (class_exists($className)) {
                try {
                    $reflection = new ReflectionClass($className);

                    if (!$reflection->isAbstract() && $reflection->isSubclassOf('Fereydooni\\LaravelElastoquent\\Models\\Model')) {
                        $models[] = $className;
                    }
                } catch (\Exception $e) {
                    // Skip if any errors
                    continue;
                }
            }
        }

        return $models;
    }
} 