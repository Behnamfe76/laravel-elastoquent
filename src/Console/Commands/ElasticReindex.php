<?php

declare(strict_types=1);

namespace Fereydooni\LaravelElastoquent\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class ElasticReindex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:reindex 
                            {model? : The model class to reindex}
                            {--all : Reindex all models}
                            {--chunk=1000 : Chunk size for processing records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reindex all data for Elasticsearch models';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->reindexAll();
        }

        $model = $this->argument('model');

        if (empty($model)) {
            $this->error('Please provide a model class or use the --all option.');
            return 1;
        }

        return $this->reindex($model);
    }

    /**
     * Reindex a specific model.
     */
    protected function reindex(string $model): int
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
            $this->info("Reindexing {$model} into {$indexName}...");

            // First ensure the index exists and mapping is updated
            $model::createIndex();
            $model::updateMapping();

            // Get the Eloquent model if it exists
            $eloquentModel = $this->getEloquentModel($model);

            if (!$eloquentModel) {
                $this->error("Could not find corresponding Eloquent model for {$model}.");
                return 1;
            }

            $chunkSize = (int) $this->option('chunk');
            $bar = $this->output->createProgressBar();
            $bar->start();

            $eloquentModel::query()->chunk($chunkSize, function ($records) use ($model, $bar) {
                $items = [];

                foreach ($records as $record) {
                    $items[] = $record->toArray();
                    $bar->advance();
                }

                if (!empty($items)) {
                    $result = $model::bulkIndex($items);
                    if (!empty($result['errors'])) {
                        $this->newLine();
                        $this->warn("Some records failed to index. First error: " . json_encode($result['errors'][0] ?? 'Unknown error'));
                    }
                }
            });

            $bar->finish();
            $this->newLine();
            $this->info("Successfully reindexed {$model} into {$indexName}.");

            return 0;
        } catch (\Exception $e) {
            $this->error("Error reindexing {$model}: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Reindex all Elastic models.
     */
    protected function reindexAll(): int
    {
        $models = $this->findElasticModels();
        $success = true;

        if (empty($models)) {
            $this->info('No Elastic models found.');
            return 0;
        }

        $this->info('Reindexing ' . count($models) . ' models...');

        foreach ($models as $model) {
            $this->newLine();
            $this->line("Processing model: {$model}");
            $result = $this->reindex($model);
            if ($result !== 0) {
                $success = false;
            }
        }

        $this->newLine();
        if ($success) {
            $this->info('All models reindexed successfully.');
            return 0;
        }

        $this->error('Some models failed to reindex. See above for details.');
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

    /**
     * Get the corresponding Eloquent model for an ElasticModel.
     */
    protected function getEloquentModel(string $elasticModel): ?string
    {
        // Try to derive Eloquent model from the ElasticModel class name
        $modelClass = str_replace(['Fereydooni\\LaravelElastoquent\\', 'Elastic'], '', $elasticModel);
        
        // Try to find the class in App\Models namespace
        $eloquentClass = 'App\\Models\\' . class_basename($modelClass);
        
        if (class_exists($eloquentClass)) {
            return $eloquentClass;
        }
        
        // Try without namespace
        if (class_exists($modelClass)) {
            return $modelClass;
        }
        
        // Try to derive from the index name
        try {
            $instance = new $elasticModel();
            $indexName = $instance::getIndexName();
            $modelName = Str::studly(Str::singular($indexName));
            
            $candidates = [
                'App\\Models\\' . $modelName,
                'App\\' . $modelName,
                $modelName,
            ];
            
            foreach ($candidates as $candidate) {
                if (class_exists($candidate)) {
                    return $candidate;
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        return null;
    }
} 