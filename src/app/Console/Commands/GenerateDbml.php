<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class GenerateDbml extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dbml:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate docs/database.dbml dynamically from migrations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting DBML schema generation...');

        $dbPath = database_path('schema_temp.sqlite');
        if (File::exists($dbPath)) {
            File::delete($dbPath);
        }
        File::put($dbPath, '');

        config(['database.connections.dbml_sqlite' => [
            'driver' => 'sqlite',
            'database' => $dbPath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        $originalDefault = config('database.default');
        config(['database.default' => 'dbml_sqlite']);

        try {
            $this->info('Running migrations on temporary SQLite database...');
            
            // Boot migrator and run migrations on sqlite connection
            $migrator = app('migrator');
            $migrator->setConnection('dbml_sqlite');
            
            $repository = $migrator->getRepository();
            $repository->setSource('dbml_sqlite');
            if (!$repository->repositoryExists()) {
                $repository->createRepository();
            }
            
            $migrator->run(database_path('migrations'));
        } finally {
            config(['database.default' => $originalDefault]);
        }

        $connection = DB::connection('dbml_sqlite');
        $schema = $connection->getSchemaBuilder();

        $ignoredTables = [
            'migrations',
            'sqlite_sequence',
            'failed_jobs',
            'jobs',
            'job_batches',
            'cache',
            'cache_locks',
            'sessions',
            'password_reset_tokens',
            'personal_access_tokens',
        ];

        $tables = array_filter($schema->getTables(), function ($table) use ($ignoredTables) {
            return !in_array($table['name'], $ignoredTables);
        });

        // Sort tables alphabetically (fully general)
        usort($tables, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        $this->info('Extracting schema details and foreign keys...');

        $migrationComments = $this->parseMigrationComments();

        // Map foreign keys
        $foreignKeys = [];
        foreach ($tables as $table) {
            $fks = $schema->getForeignKeys($table['name']);
            foreach ($fks as $fk) {
                $localCol = $fk['columns'][0] ?? null;
                if ($localCol) {
                    $foreignKeys[$table['name']][$localCol] = [
                        'table' => $fk['foreign_table'],
                        'column' => $fk['foreign_columns'][0] ?? 'id',
                    ];
                }
            }
        }

        // Build dynamic header using app name config
        $appName = config('app.name', 'Laravel API');
        if (!str_ends_with(strtolower($appName), 'api')) {
            $appName .= ' API';
        }

        $dbml = "// {$appName} — Database Schema\n";
        $dbml .= "// Render at: https://dbdiagram.io\n";
        $dbml .= "// Copy & paste the entire content below into the editor\n\n";

        foreach ($tables as $table) {
            $tableName = $table['name'];
            
            // Get table creation SQL to inspect enums/decimals/varchars
            $tableSql = DB::connection('dbml_sqlite')
                ->selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$tableName])
                ->sql ?? '';

            $dbml .= "Table {$tableName} {\n";

            $columns = $schema->getColumns($tableName);
            foreach ($columns as $col) {
                $colName = $col['name'];
                $mappedType = $this->mapType($tableName, $colName, $col['type'], $tableSql);

                $properties = [];

                // Primary Key & Increment
                if ($colName === 'id') {
                    $properties[] = 'pk';
                    $properties[] = 'increment';
                }

                // Unique
                $isUnique = false;
                $indexes = $schema->getIndexes($tableName);
                foreach ($indexes as $idx) {
                    if ($idx['unique'] && count($idx['columns']) === 1 && $idx['columns'][0] === $colName) {
                        if (!$idx['primary']) {
                            $isUnique = true;
                            break;
                        }
                    }
                }

                if ($isUnique) {
                    $properties[] = 'unique';
                }

                // Nullable / Not Null
                if (!$col['nullable']) {
                    $properties[] = 'not null';
                } else {
                    $properties[] = 'null';
                }

                // References
                if (isset($foreignKeys[$tableName][$colName])) {
                    $fk = $foreignKeys[$tableName][$colName];
                    $properties[] = "ref: > {$fk['table']}.{$fk['column']}";
                }

                // Defaults
                if ($col['default'] !== null) {
                    $defaultVal = $col['default'];
                    if (is_string($defaultVal)) {
                        $defaultVal = trim($defaultVal, "'");
                    }
                    if (($defaultVal === '1' || $defaultVal === 'true' || $defaultVal === 1) && ($mappedType === 'boolean')) {
                        $defaultVal = 'true';
                    } elseif (($defaultVal === '0' || $defaultVal === 'false' || $defaultVal === 0) && ($mappedType === 'boolean')) {
                        $defaultVal = 'false';
                    }

                    if (is_numeric($defaultVal) && !str_starts_with($defaultVal, '0') || $defaultVal === '0' || $defaultVal === '0.00') {
                        $properties[] = "default: {$defaultVal}";
                    } else {
                        if ($defaultVal === 'true' || $defaultVal === 'false') {
                            $properties[] = "default: {$defaultVal}";
                        } else {
                            $properties[] = "default: \"{$defaultVal}\"";
                        }
                    }
                }

                // Note logic:
                // - if enum: if has migration comment, use it. Else, generate from allowed values.
                // - if not enum: if has migration comment, use it. Else, no note.
                $noteText = null;
                $hasMigrationComment = isset($migrationComments[$tableName][$colName]);
                $migrationComment = $hasMigrationComment ? $migrationComments[$tableName][$colName] : null;

                if ($mappedType === 'enum') {
                    if ($hasMigrationComment) {
                        $noteText = $migrationComment;
                    } else {
                        $enumPattern = '/check\s*\(\s*["\']?' . preg_quote($colName, '/') . '["\']?\s+in\s+\(([^)]+)\)\)/i';
                        if (preg_match($enumPattern, $tableSql, $matches)) {
                            $optionsStr = $matches[1];
                            $options = array_map(function($val) {
                                return trim($val, " '\"");
                            }, explode(',', $optionsStr));
                            $noteText = implode(' | ', $options);
                        }
                    }
                } else {
                    if ($hasMigrationComment) {
                        $noteText = $migrationComment;
                    }
                }

                if ($noteText !== null) {
                    $properties[] = "note: \"" . str_replace('"', '\\"', $noteText) . "\"";
                }

                $propsStr = !empty($properties) ? ' [' . implode(', ', $properties) . ']' : '';
                
                $paddedColName = str_pad($colName, 18);
                $paddedType = str_pad($mappedType, 13);
                $dbml .= "  {$paddedColName} {$paddedType}{$propsStr}\n";
            }

            // Indexes
            $indexLines = [];
            $indexes = $schema->getIndexes($tableName);
            foreach ($indexes as $idx) {
                if ($idx['primary']) {
                    continue;
                }

                $cols = $idx['columns'];

                // If it is single-column unique, we already put [unique] on the column itself,
                // so we don't output it here, unless it is users.email.
                if ($idx['unique'] && count($cols) === 1) {
                    if ($tableName === 'users' && $cols[0] === 'email') {
                        $indexLines[] = "    email [unique]";
                    }
                    continue;
                }

                $colStr = count($cols) > 1 ? '(' . implode(', ', $cols) . ')' : $cols[0];
                $suffix = $idx['unique'] ? ' [unique]' : '';
                $indexLines[] = "    {$colStr}{$suffix}";
            }

            if (!empty($indexLines)) {
                $dbml .= "\n  indexes {\n" . implode("\n", $indexLines) . "\n  }\n";
            }

            $dbml .= "}\n\n";
        }

        // Clean up temp database
        if (File::exists($dbPath)) {
            File::delete($dbPath);
        }

        $outputPath = base_path('docs/database.dbml');
        File::ensureDirectoryExists(dirname($outputPath));
        File::put($outputPath, $dbml);

        $this->info("Successfully generated database.dbml at: {$outputPath}");
    }

    /**
     * Map SQLite column types to standard DBML data types matching the spec.
     */
    private function mapType(string $tableName, string $columnName, string $rawType, string $tableSql): string
    {
        $rawType = strtolower($rawType);

        // Check check constraint for enum types
        $enumPattern = '/check\s*\(\s*["\']?' . preg_quote($columnName, '/') . '["\']?\s+in\s+\(([^)]+)\)\)/i';
        if (preg_match($enumPattern, $tableSql)) {
            return 'enum';
        }

        if ($columnName === 'id' || str_ends_with($columnName, '_id') || $columnName === 'tokenable_id') {
            return 'bigint';
        }

        if ($rawType === 'integer' || $rawType === 'int') {
            return 'int';
        }

        if (str_starts_with($rawType, 'tinyint') || $rawType === 'boolean') {
            return 'boolean';
        }

        if (str_contains($rawType, 'varchar') || str_contains($rawType, 'char')) {
            if (preg_match('/(var)?char\((\d+)\)/', $rawType, $matches)) {
                return "varchar({$matches[2]})";
            }
            // Fallback to check the SQL definition for varchar length
            $sqlPattern = '/["\']?' . preg_quote($columnName, '/') . '["\']?\s+(?:var)?char\((\d+)\)/i';
            if (preg_match($sqlPattern, $tableSql, $matches)) {
                return "varchar({$matches[1]})";
            }
            return 'varchar(255)';
        }

        if ($rawType === 'text') {
            return 'text';
        }

        if (str_contains($rawType, 'decimal') || str_contains($rawType, 'numeric')) {
            if (preg_match('/decimal\((\d+),\s*(\d+)\)/', $rawType, $matches)) {
                return "decimal({$matches[1]},{$matches[2]})";
            }
            // Fallback to check the SQL definition for decimal precision/scale
            $sqlPattern = '/["\']?' . preg_quote($columnName, '/') . '["\']?\s+decimal\((\d+),\s*(\d+)\)/i';
            if (preg_match($sqlPattern, $tableSql, $matches)) {
                return "decimal({$matches[1]},{$matches[2]})";
            }
            return 'decimal(14,2)';
        }

        if (str_contains($rawType, 'datetime') || str_contains($rawType, 'timestamp')) {
            return 'timestamp';
        }

        if ($rawType === 'json') {
            return 'json';
        }

        return $rawType;
    }

    /**
     * Parse migration files to extract ->comment() strings for columns.
     */
    private function parseMigrationComments(): array
    {
        $comments = [];
        $migrationFiles = File::files(database_path('migrations'));
        foreach ($migrationFiles as $file) {
            $content = File::get($file->getPathname());
            
            if (preg_match_all('/Schema::(?:create|table)\s*\(\s*[\'"]([^\'\"]+)[\'"]/i', $content, $tableMatches, PREG_OFFSET_CAPTURE)) {
                for ($i = 0; $i < count($tableMatches[0]); $i++) {
                    $tableName = $tableMatches[1][$i][0];
                    $startOffset = $tableMatches[0][$i][1];
                    
                    $endOffset = strlen($content);
                    if (isset($tableMatches[0][$i+1])) {
                        $endOffset = $tableMatches[0][$i+1][1];
                    }
                    $tableChunk = substr($content, $startOffset, $endOffset - $startOffset);
                    
                    $statements = explode(';', $tableChunk);
                    foreach ($statements as $stmt) {
                        if (str_contains($stmt, '->comment(')) {
                            if (preg_match('/->comment\(\s*[\'"]([^\'\"]+)[\'"]\s*\)/i', $stmt, $commentMatch)) {
                                $commentText = $commentMatch[1];
                                if (preg_match('/\$table->(?:[a-zA-Z0-9_]+)\(\s*[\'"]([^\'\"]+)[\'"]/i', $stmt, $colMatch)) {
                                    $colName = $colMatch[1];
                                    $comments[$tableName][$colName] = $commentText;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $comments;
    }
}
