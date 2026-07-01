<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('postman:generate')]
#[Description('Automatically generates a Postman collection based on docs/api/openapi.json')]
class GeneratePostmanCollection extends Command
{
    private $schemas = [];
    private $faker;

    public function __construct()
    {
        parent::__construct();
        $this->faker = \Faker\Factory::create();
    }

    public function handle()
    {
        $openapiPath = base_path('docs/api/openapi.json');
        if (!file_exists($openapiPath)) {
            $this->error("OpenAPI spec file not found at: {$openapiPath}");
            return 1;
        }

        $openapi = json_decode(file_get_contents($openapiPath), true);
        if (!$openapi) {
            $this->error("Failed to parse OpenAPI JSON.");
            return 1;
        }

        $this->schemas = $openapi['components']['schemas'] ?? [];

        $collection = [
            'info' => [
                'name' => ($openapi['info']['title'] ?? 'Marketplace API') . ' (Auto-Generated)',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
            ],
            'item' => [],
            'variable' => [
                [
                    'key' => 'base_url',
                    'value' => 'http://localhost/api',
                    'type' => 'string'
                ],
                [
                    'key' => 'token',
                    'value' => '',
                    'type' => 'string'
                ]
            ]
        ];

        $paths = $openapi['paths'] ?? [];
        $folders = [];

        foreach ($paths as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if (in_array(strtolower($method), ['parameters', 'summary', 'description'])) {
                    continue;
                }

                $methodUpper = strtoupper($method);
                $summary = $operation['summary'] ?? '';
                if (strpos($summary, "\n") !== false) {
                    $summary = explode("\n", $summary)[0];
                }
                if (strpos($summary, "\r") !== false) {
                    $summary = explode("\r", $summary)[0];
                }
                $summary = trim($summary);

                $description = $operation['description'] ?? '';
                $tags = $operation['tags'] ?? ['Other'];
                $firstTag = $tags[0];

                // Parse folders from tag, e.g. "Admin/Users" -> parent "Admin", child "Users"
                $tagParts = explode('/', $firstTag);
                $parentName = $tagParts[0];
                $childName = $tagParts[1] ?? null;

                // Resolve parameters
                $pathParams = [];
                $queryParams = [];
                $params = array_merge($paths[$path]['parameters'] ?? [], $operation['parameters'] ?? []);
                foreach ($params as $param) {
                    if (isset($param['$ref'])) {
                        // resolve parameter ref if any
                    } else {
                        if ($param['in'] === 'path') {
                            $pathParams[$param['name']] = $param;
                        } elseif ($param['in'] === 'query') {
                            $queryParams[] = $param;
                        }
                    }
                }

                // Construct test URI for Postman (replacing {param} with :param)
                $postmanPath = str_replace(['{', '}'], [':', ''], $path);
                if (strpos($postmanPath, '/') === 0) {
                    $postmanPath = substr($postmanPath, 1);
                }

                // Parse Request Body Example
                $bodyData = null;
                $reqBody = $operation['requestBody'] ?? null;
                if ($reqBody) {
                    $contentSchema = $reqBody['content']['application/json']['schema'] ?? null;
                    if ($contentSchema) {
                        $bodyData = $this->resolveSchemaExample($contentSchema);
                    }
                }

                // Parse Response Body Examples
                $responsesMarkdown = "";
                $responses = $operation['responses'] ?? [];
                foreach ($responses as $statusCode => $res) {
                    $resSchema = $res['content']['application/json']['schema'] ?? null;
                    $resDescription = $res['description'] ?? '';
                    $responsesMarkdown .= "#### Response: `{$statusCode}`\n{$resDescription}\n\n";
                    if ($resSchema) {
                        $example = $this->resolveSchemaExample($resSchema);
                        $responsesMarkdown .= "```json\n" . json_encode($example, JSON_PRETTY_PRINT) . "\n```\n\n";
                    }
                }

                // Build Markdown description for Postman Request
                $markdownDesc = "### {$summary}\n\n";
                if ($description) {
                    $markdownDesc .= "{$description}\n\n";
                }
                
                if (!empty($pathParams)) {
                    $markdownDesc .= "#### Path Variables\n\n";
                    $markdownDesc .= "| Parameter | Type | Required | Description | Constraints |\n";
                    $markdownDesc .= "|---|---|---|---|---|\n";
                    foreach ($pathParams as $name => $pp) {
                        $pType = $pp['schema']['type'] ?? 'string';
                        if (is_array($pType)) {
                            $pType = implode(' / ', $pType);
                        }
                        $pDesc = str_replace('|', ' / ', $pp['description'] ?? '');
                        $markdownDesc .= "| `{$name}` | `{$pType}` | Yes | {$pDesc} | - |\n";
                    }
                    $markdownDesc .= "\n";
                }

                if (!empty($queryParams)) {
                    $markdownDesc .= "#### Query Parameters\n\n";
                    $markdownDesc .= "| Parameter | Type | Required | Description | Constraints |\n";
                    $markdownDesc .= "|---|---|---|---|---|\n";
                    foreach ($queryParams as $qp) {
                        $req = ($qp['required'] ?? false) ? 'Yes' : 'No';
                        $pType = $qp['schema']['type'] ?? 'string';
                        if (is_array($pType)) {
                            $pType = implode(' / ', $pType);
                        }
                        $qSchema = $qp['schema'] ?? [];
                        $constraints = [];
                        if (isset($qSchema['enum']) && is_array($qSchema['enum'])) {
                            $enumOpts = array_map(fn($opt) => "`$opt`", $qSchema['enum']);
                            $constraints[] = "Enum: " . implode(', ', $enumOpts);
                        }
                        $constraintsStr = empty($constraints) ? '-' : implode('<br>', $constraints);
                        $qpDesc = str_replace('|', ' / ', $qp['description'] ?? '');
                        $markdownDesc .= "| `{$qp['name']}` | `{$pType}` | {$req} | {$qpDesc} | {$constraintsStr} |\n";
                    }
                    $markdownDesc .= "\n";
                }

                // Parse and build Request Body parameters table
                $reqBody = $operation['requestBody'] ?? null;
                if ($reqBody) {
                    $contentSchema = $reqBody['content']['application/json']['schema'] ?? null;
                    if ($contentSchema) {
                        // Resolve schema first in case it's a ref
                        $resolvedSchema = $contentSchema;
                        if (isset($contentSchema['$ref'])) {
                            $ref = $contentSchema['$ref'];
                            $parts = explode('/', $ref);
                            $schemaName = end($parts);
                            if (isset($this->schemas[$schemaName])) {
                                $resolvedSchema = $this->schemas[$schemaName];
                            }
                        }

                        $properties = $resolvedSchema['properties'] ?? [];
                        if (!empty($properties)) {
                            $requiredProps = $resolvedSchema['required'] ?? [];
                            $markdownDesc .= "#### Request Body Parameters\n\n";
                            $markdownDesc .= "| Parameter | Type | Required | Description | Constraints |\n";
                            $markdownDesc .= "|---|---|---|---|---|\n";
                            foreach ($properties as $propName => $propSchema) {
                                // Resolve properties recursively if ref
                                if (isset($propSchema['$ref'])) {
                                    $ref = $propSchema['$ref'];
                                    $parts = explode('/', $ref);
                                    $schemaName = end($parts);
                                    if (isset($this->schemas[$schemaName])) {
                                        $propSchema = $this->schemas[$schemaName];
                                    }
                                }

                                $propType = $propSchema['type'] ?? 'string';
                                if (is_array($propType)) {
                                    $propType = implode(' / ', $propType);
                                }
                                $isReq = in_array($propName, $requiredProps) ? 'Yes' : 'No';
                                $propDesc = str_replace('|', ' / ', $propSchema['description'] ?? '');

                                // Build constraints
                                $constraints = [];
                                if (isset($propSchema['enum']) && is_array($propSchema['enum'])) {
                                    $enumOpts = array_map(fn($opt) => "`$opt`", $propSchema['enum']);
                                    $constraints[] = "Enum: " . implode(', ', $enumOpts);
                                }
                                if (isset($propSchema['minLength'])) {
                                    $constraints[] = "Min Length: `{$propSchema['minLength']}`";
                                }
                                if (isset($propSchema['maxLength'])) {
                                    $constraints[] = "Max Length: `{$propSchema['maxLength']}`";
                                }
                                if (isset($propSchema['minimum'])) {
                                    $constraints[] = "Min: `{$propSchema['minimum']}`";
                                }
                                if (isset($propSchema['maximum'])) {
                                    $constraints[] = "Max: `{$propSchema['maximum']}`";
                                }
                                
                                $constraintsStr = empty($constraints) ? '-' : implode('<br>', $constraints);
                                $markdownDesc .= "| `{$propName}` | `{$propType}` | {$isReq} | {$propDesc} | {$constraintsStr} |\n";
                            }
                            $markdownDesc .= "\n";
                        }
                    }
                }

                if ($responsesMarkdown) {
                    $markdownDesc .= "### Responses\n\n{$responsesMarkdown}";
                }

                // Check if authenticated (security requirement present or role middleware tag)
                $security = $operation['security'] ?? $openapi['security'] ?? [];
                $needsAuth = !empty($security);

                $request = [
                    'method' => $methodUpper,
                    'header' => [
                        [
                            'key' => 'Accept',
                            'value' => 'application/json',
                            'type' => 'text'
                        ]
                    ],
                    'url' => [
                        'raw' => '{{base_url}}/' . $postmanPath,
                        'host' => ['{{base_url}}'],
                        'path' => explode('/', $postmanPath)
                    ],
                    'description' => $markdownDesc
                ];

                // Query params in URL path
                if (!empty($queryParams)) {
                    $request['url']['query'] = [];
                    foreach ($queryParams as $qp) {
                        $request['url']['query'][] = [
                            'key' => $qp['name'],
                            'value' => $qp['example'] ?? ($qp['schema']['example'] ?? ''),
                            'description' => $qp['description'] ?? '',
                            'disabled' => !($qp['required'] ?? false)
                        ];
                    }
                }

                // Path variables
                if (!empty($pathParams)) {
                    $request['url']['variable'] = [];
                    foreach ($pathParams as $name => $pp) {
                        $request['url']['variable'][] = [
                            'key' => $name,
                            'value' => '1',
                            'description' => $pp['description'] ?? ''
                        ];
                    }
                }

                if ($needsAuth) {
                    $request['auth'] = [
                        'type' => 'bearer',
                        'bearer' => [
                            [
                                'key' => 'token',
                                'value' => '{{token}}',
                                'type' => 'string'
                            ]
                        ]
                    ];
                }

                if ($bodyData !== null && in_array($methodUpper, ['POST', 'PUT', 'PATCH'])) {
                    $request['body'] = [
                        'mode' => 'raw',
                        'raw' => json_encode($bodyData, JSON_PRETTY_PRINT),
                        'options' => [
                            'raw' => [
                                'language' => 'json'
                            ]
                        ]
                    ];
                }

                $item = [
                    'name' => $summary ?: "[$methodUpper] /$postmanPath",
                    'request' => $request,
                    'response' => []
                ];

                // Organize into parent/child folders
                if ($childName) {
                    if (!isset($folders[$parentName])) {
                        $folders[$parentName] = [
                            'name' => $parentName,
                            'item' => []
                        ];
                    }
                    if (!isset($folders[$parentName]['item'][$childName])) {
                        $folders[$parentName]['item'][$childName] = [
                            'name' => $childName,
                            'item' => []
                        ];
                    }
                    $folders[$parentName]['item'][$childName]['item'][] = $item;
                } else {
                    if (!isset($folders[$parentName])) {
                        $folders[$parentName] = [
                            'name' => $parentName,
                            'item' => []
                        ];
                    }
                    $folders[$parentName]['item'][] = $item;
                }
            }
        }

        // Convert folders associative arrays into sequential arrays for Postman JSON
        $formattedItems = [];
        foreach ($folders as $parentName => $parentFolder) {
            $parentItem = [
                'name' => $parentName,
                'item' => []
            ];

            foreach ($parentFolder['item'] as $key => $val) {
                if (isset($val['item'])) {
                    // Convert child associative folder to sequential
                    $childFolder = $val;
                    $childFolder['item'] = array_values($val['item']);
                    $parentItem['item'][] = $childFolder;
                } else {
                    // It's a direct request item
                    $parentItem['item'][] = $val;
                }
            }
            $formattedItems[] = $parentItem;
        }

        $collection['item'] = $formattedItems;

        // Save generated collection directly to docs folder
        $docsPath = base_path('docs/api/postman_collection.json');
        file_put_contents($docsPath, json_encode($collection, JSON_PRETTY_PRINT));
        $this->info("Postman collection successfully generated at: {$docsPath}");
    }

    public function resolveSchemaExample($schema, $propName = null)
    {
        if (isset($schema['$ref'])) {
            $ref = $schema['$ref'];
            $parts = explode('/', $ref);
            $schemaName = end($parts);
            if (isset($this->schemas[$schemaName])) {
                return $this->resolveSchemaExample($this->schemas[$schemaName], $propName);
            }
            return null;
        }

        if (isset($schema['example'])) {
            return $schema['example'];
        }

        $type = $schema['type'] ?? 'string';
        if (is_array($type)) {
            $type = $type[0] ?? 'string';
        }

        if ($type === 'object') {
            $properties = $schema['properties'] ?? [];
            $example = [];
            foreach ($properties as $prop => $propSchema) {
                $example[$prop] = $this->resolveSchemaExample($propSchema, $prop);
            }
            return $example;
        }

        if ($type === 'array') {
            $itemsSchema = $schema['items'] ?? [];
            return [$this->resolveSchemaExample($itemsSchema, $propName)];
        }

        return $this->getMockValueByType($schema, $propName);
    }

    public function getMockValueByType($schema, $propName = null)
    {
        // 1. Resolve Enums (allowed values from schema)
        if (isset($schema['enum']) && is_array($schema['enum']) && !empty($schema['enum'])) {
            return $schema['enum'][0];
        }

        $type = $schema['type'] ?? 'string';
        if (is_array($type)) {
            $type = $type[0] ?? 'string';
        }
        $type = strtolower((string)$type);
        $format = $schema['format'] ?? null;

        // 2. Resolve Min/Max Length Rules
        $minLength = $schema['minLength'] ?? null;
        $maxLength = $schema['maxLength'] ?? null;

        switch ($type) {
            case 'integer':
            case 'int':
            case 'int32':
            case 'int64':
                $min = $schema['minimum'] ?? 1;
                $max = $schema['maximum'] ?? 100;
                return $this->faker->numberBetween($min, $max);

            case 'number':
            case 'float':
            case 'double':
            case 'decimal':
                $min = $schema['minimum'] ?? 1.0;
                $max = $schema['maximum'] ?? 100.0;
                return $this->faker->randomFloat(2, $min, $max);

            case 'boolean':
            case 'bool':
                return true;

            case 'string':
                // Check format-specific rules first
                if ($format === 'date') {
                    return $this->faker->date('Y-m-d');
                }
                if ($format === 'date-time' || $format === 'datetime') {
                    return $this->faker->dateTime()->format('c');
                }
                if ($format === 'password') {
                    $minL = $minLength ?? 8;
                    $maxL = $maxLength ?? 16;
                    return $this->faker->password($minL, $maxL, true, true, true);
                }
                if ($format === 'email') {
                    return $this->faker->unique()->safeEmail;
                }

                // Property name based pattern heuristics
                if ($propName !== null) {
                    $propLower = strtolower($propName);
                    if (strpos($propLower, 'email') !== false) {
                        return $this->faker->unique()->safeEmail;
                    }
                    if (strpos($propLower, 'password') !== false) {
                        $minL = $minLength ?? 8;
                        $maxL = $maxLength ?? 16;
                        return $this->faker->password($minL, $maxL, true, true, true);
                    }
                    if (strpos($propLower, 'phone') !== false || strpos($propLower, 'mobile') !== false) {
                        return $this->faker->phoneNumber;
                    }
                    if (strpos($propLower, 'description') !== false || strpos($propLower, 'summary') !== false) {
                        return $this->faker->sentence();
                    }
                    if (strpos($propLower, 'price') !== false || strpos($propLower, 'balance') !== false || strpos($propLower, 'amount') !== false || $propLower === 'subtotal') {
                        return $this->faker->randomFloat(2, 10, 1000);
                    }
                    if ($propLower === 'country') {
                        return $this->faker->country;
                    }
                    if (strpos($propLower, 'zip') !== false || strpos($propLower, 'postal') !== false) {
                        return $this->faker->postcode;
                    }
                    if (strpos($propLower, 'city') !== false) {
                        return $this->faker->city;
                    }
                    if (strpos($propLower, 'state') !== false || strpos($propLower, 'province') !== false) {
                        return $this->faker->state;
                    }
                    if (strpos($propLower, 'address') !== false) {
                        return $this->faker->address;
                    }
                    if ($propLower === 'name' || strpos($propLower, 'title') !== false) {
                        return $this->faker->words(3, true);
                    }
                }

                // Use minLength and maxLength rules if set
                if ($minLength !== null || $maxLength !== null) {
                    $minL = $minLength ?? 1;
                    $maxL = $maxLength ?? 100;
                    return substr($this->faker->text($maxL), 0, $maxL);
                }

                return $this->faker->word;

            case 'date':
                return $this->faker->date('Y-m-d');

            case 'date-time':
            case 'datetime':
                return $this->faker->dateTime()->format('c');

            case 'array':
                return [];

            case 'object':
                return new \stdClass();

            case 'null':
                return null;

            default:
                return '';
        }
    }
}
