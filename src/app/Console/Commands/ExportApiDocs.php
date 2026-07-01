<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

class ExportApiDocs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:export';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export Scramble OpenAPI docs, sort endpoints/groups, and group schemas by type';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Exporting raw OpenAPI JSON via Scramble...');
        $exitCode = Artisan::call('scramble:export', [
            '--path' => 'docs/api/openapi.json'
        ]);

        if ($exitCode !== 0) {
            $this->error('Failed to export OpenAPI JSON.');
            return $exitCode;
        }

        $path = base_path('docs/api/openapi.json');
        if (!file_exists($path)) {
            $this->error('openapi.json not found at ' . $path);
            return 1;
        }

        $this->info('Reading exported OpenAPI JSON...');
        $json = file_get_contents($path);
        $data = json_decode($json, true);

        // --- SORT TAGS ---
        if (isset($data['tags'])) {
            $this->info('Sorting tags based on controller group weights...');
            $tagWeights = [];
            foreach ($data['tags'] as $tagInfo) {
                $tagName = $tagInfo['name'] ?? '';
                $tagWeights[$tagName] = $this->getTagWeightByName($tagName);
            }
            usort($data['tags'], function ($a, $b) use ($tagWeights) {
                $tagNameA = $a['name'] ?? '';
                $tagNameB = $b['name'] ?? '';
                $wa = $tagWeights[$tagNameA] ?? 999;
                $wb = $tagWeights[$tagNameB] ?? 999;
                if ($wa === $wb) {
                    return strcmp($tagNameA, $tagNameB);
                }
                return $wa <=> $wb;
            });
        }

        // --- SORT PATHS ---
        if (isset($data['paths'])) {
            $this->info('Sorting paths based on controller group weights...');
            $paths = $data['paths'];
            $pathWeights = [];

            foreach ($paths as $pathKey => $methods) {
                $minWeight = 999999;
                foreach ($methods as $method => $operation) {
                    $route = $this->findRoute($pathKey, $method);
                    if ($route) {
                        $action = $route->getAction('controller');
                        if (is_string($action) && str_contains($action, '@')) {
                            [$controllerClass, $methodName] = explode('@', $action);
                            $groupWeight = $this->getGroupWeight($controllerClass);
                            if ($groupWeight < $minWeight) {
                                $minWeight = $groupWeight;
                            }
                        }
                    }
                }
                $pathWeights[$pathKey] = $minWeight;
            }

            uksort($paths, function ($a, $b) use ($pathWeights) {
                $wa = $pathWeights[$a] ?? 999999;
                $wb = $pathWeights[$b] ?? 999999;
                if ($wa === $wb) {
                    // Alphabetical sorting within the same weight
                    return strcmp($a, $b);
                }
                return $wa <=> $wb;
            });

            // Cleanly sort operations (GET, POST, etc.) inside each path
            foreach ($paths as $pathKey => &$methods) {
                $methodOrder = ['get' => 0, 'post' => 1, 'put' => 2, 'patch' => 3, 'delete' => 4];
                uksort($methods, function ($a, $b) use ($methodOrder) {
                    $wa = $methodOrder[strtolower($a)] ?? 99;
                    $wb = $methodOrder[strtolower($b)] ?? 99;
                    return $wa <=> $wb;
                });
            }
            unset($methods);

            $data['paths'] = $paths;
        }

        // --- GROUP SCHEMAS ---
        if (isset($data['components']['schemas'])) {
            $this->info('Grouping schemas by type...');
            $schemas = $data['components']['schemas'];
            $newSchemas = [];
            $replacements = [];

            foreach ($schemas as $key => $schema) {
                if (str_ends_with($key, 'Request')) {
                    $newKey = 'Requests.' . $key;
                } elseif (str_ends_with($key, 'Resource') || str_ends_with($key, 'Collection')) {
                    $newKey = 'Resources.' . $key;
                } else {
                    $newKey = 'Models.' . $key;
                }
                
                $newSchemas[$newKey] = $schema;
                $replacements['"#/components/schemas/'.$key.'"'] = '"#/components/schemas/'.$newKey.'"';
            }

            ksort($newSchemas);
            $data['components']['schemas'] = $newSchemas;

            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $json = strtr($json, $replacements);
        } else {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        file_put_contents($path, $json);
        $this->info('Successfully sorted tags, paths, and grouped schemas at ' . $path);
        
        return 0;
    }

    private function findRoute(string $openapiPath, string $method): ?\Illuminate\Routing\Route
    {
        $cleanOpenapiPath = trim($openapiPath, '/');
        $method = strtoupper($method);

        foreach (Route::getRoutes() as $route) {
            if (!in_array($method, $route->methods())) {
                continue;
            }

            $routeUri = trim($route->uri(), '/');
            if (str_starts_with($routeUri, 'api/')) {
                $routeUri = substr($routeUri, 4);
            }

            if ($routeUri === $cleanOpenapiPath) {
                return $route;
            }
        }

        return null;
    }

    private function getGroupWeight(string $controllerClass): int
    {
        try {
            $reflection = new \ReflectionClass($controllerClass);
            $attributes = $reflection->getAttributes(\Dedoc\Scramble\Attributes\Group::class);
            foreach ($attributes as $attribute) {
                $arguments = $attribute->getArguments();
                if (isset($arguments['weight'])) {
                    return (int) $arguments['weight'];
                }
                if (isset($arguments[1])) {
                    return (int) $arguments[1];
                }
            }
        } catch (\Throwable $e) {
        }
        return 999;
    }

    private function getTagWeightByName(string $tagName): int
    {
        foreach (Route::getRoutes() as $route) {
            $action = $route->getAction('controller');
            if (is_string($action) && str_contains($action, '@')) {
                [$controllerClass, $methodName] = explode('@', $action);
                try {
                    $reflection = new \ReflectionClass($controllerClass);
                    $attributes = $reflection->getAttributes(\Dedoc\Scramble\Attributes\Group::class);
                    foreach ($attributes as $attribute) {
                        $arguments = $attribute->getArguments();
                        $name = $arguments[0] ?? $arguments['name'] ?? null;
                        if ($name === $tagName) {
                            if (isset($arguments['weight'])) {
                                return (int) $arguments['weight'];
                            }
                            if (isset($arguments[1])) {
                                return (int) $arguments[1];
                            }
                        }
                    }
                } catch (\Throwable $e) {
                }
            }
        }
        return 999;
    }
}
