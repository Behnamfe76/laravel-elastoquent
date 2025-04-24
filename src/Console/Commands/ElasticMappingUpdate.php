<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class ElasticMappingUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:mapping-update 
                            {model? : The model class to update mapping for}
                            {--all : Update mapping for all models}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Elasticsearch mappings for models';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->updateAllMappings();
        }

        $model = $this->argument('model');

        if (empty($model)) {
            $this->error('Please provide a model class or use the --all option.');
            return 1;
        }

        return $this->updateMapping($model);
    }

    /**
     * Update mapping for a specific model.
     */
    protected function updateMapping(string $model): int
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
            $result = $model::updateMapping();

            if ($result) {
                $this->info("Successfully updated mapping for {$model} ({$indexName}).");
                return 0;
            }

            $this->error("Failed to update mapping for {$model}.");
            return 1;
        } catch (\Exception $e) {
            $this->error("Error updating mapping for {$model}: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Update mappings for all Elastic models.
     */
    protected function updateAllMappings(): int
    {
        $models = $this->findElasticModels();
        $success = true;

        if (empty($models)) {
            $this->info('No Elastic models found.');
            return 0;
        }

        $this->info('Updating mappings for ' . count($models) . ' models...');

        foreach ($models as $model) {
            $result = $this->updateMapping($model);
            if ($result !== 0) {
                $success = false;
            }
        }

        if ($success) {
            $this->info('All mappings updated successfully.');
            return 0;
        }

        $this->error('Some mappings failed to update. See above for details.');
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