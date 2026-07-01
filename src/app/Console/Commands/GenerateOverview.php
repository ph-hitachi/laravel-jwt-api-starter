<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

class GenerateOverview extends Command
{
    protected $signature = 'overview:generate {--no-api-export : Skip regenerating the OpenAPI schema}';
    protected $description = 'Generate the docs/api/overview.md file dynamically';

    public function handle()
    {
        $this->info('Starting dynamic overview generation...');

        $overviewPath = base_path('docs/api/overview.md');

        // Resolve Scramble's Generator dynamically in memory (prevents circular file dependency)
        $this->info('Resolving Scramble OpenAPI generator in memory...');
        $generator = app(\Dedoc\Scramble\Generator::class);
        $openapi = $generator();

        $appName = config('app.name', 'Marketplace API');
        if (!str_ends_with(strtolower($appName), 'api')) {
            $appName .= ' API';
        }

        $content = "# API Overview\n\nWelcome to the {$appName} documentation.\n\n";

        // Section 1: Endpoints Summary (Project-Agnostic, grouped by OpenAPI Tags & Sub-tags)
        $content .= "## 1. Endpoints Summary\n\n";
        $content .= $this->getEndpointsSummaryTable($openapi);
        $content .= "---\n\n";

        // Section 2: Standard HTTP Response Codes
        $content .= "## 2. Standard HTTP Response Codes\n\n";
        $content .= "While domain-specific errors (`ServerErrorException`) have their own dedicated error codes, the API also relies heavily on standard HTTP status codes.\n\n";
        $content .= "### Success Responses\n\n";
        $content .= "| HTTP Status | Description |\n";
        $content .= "|---|---|\n";
        $content .= "| `200 OK` | The request succeeded. Used for `GET` (fetching records), `PUT`/`PATCH` (updating records), and standard actions. |\n";
        $content .= "| `201 Created` | The request succeeded and a new resource was created. Used exclusively for `POST` requests that result in database creation. |\n";
        $content .= "| `204 No Content` | The request succeeded, but there is no body to return. Used primarily for `DELETE` requests where the resource is successfully removed. |\n";
        $content .= "| `304 Not Modified` | The resource has not been modified since the last request (matching ETag). The response body is empty, instructing the client to use its cached copy. |\n\n";
        $content .= "---\n\n";

        // Section 3: Global Error Responses & Domain Errors
        $content .= "## 3. Global Error Responses\n\n";
        $content .= "The API uses a standardized error format for all exceptions. The `app.php` bootstrap configuration enforces the following global error codes:\n\n";
        $content .= $this->getGlobalErrorsTable();
        $content .= "\n\n### Global Error JSON Format\n\n";
        $content .= "When a global error occurs, the API returns a structured JSON response matching the domain exception format:\n\n";
        $content .= "```json\n{\n    \"error_code\": \"UNAUTHENTICATED\",\n    \"exception_type\": \"AuthenticationException\",\n    \"message\": \"Unauthenticated.\"\n}\n```\n\n";
        $content .= "### Example Error Response\n\n";
        $content .= "```json\n{\n    \"error_code\": \"VALIDATION_ERROR\",\n    \"message\": \"The given data was invalid.\",\n    \"errors\": {\n        \"payment_method\": [\n            \"The selected payment method is invalid.\"\n        ]\n    }\n}\n```\n\n";
        $content .= "### Domain-Specific Error Codes\n\n";
        $content .= "In addition to the standard HTTP errors, the API throws custom business logic exceptions. These return a consistent JSON payload containing the specific `error_code` and a human-readable `message`.\n\n";
        $content .= $this->getDomainExceptionsTable();
        $content .= "\n### Domain Error JSON Format\n\n";
        $content .= "When a domain exception is thrown, the API returns a structured JSON response:\n\n";
        $content .= "```json\n{\n    \"error_code\": \"INSUFFICIENT_BALANCE\",\n    \"exception_type\": \"InsufficientBalanceException\",\n    \"message\": \"Your wallet does not have enough balance to complete this transaction.\"\n}\n```\n\n";
        $content .= "---\n\n";

        // Section 4: Rate Limiting & Security Policies
        $content .= "## 4. Rate Limiting & Security Policies\n\n";
        $content .= "The {$appName} is built with high security standards. Every response includes strict security headers and global rate limits to protect both customer data and system integrity.\n\n";

        // Dynamic Authentication & Rate Limiting details from OpenAPI Spec
        $content .= "### API Rate Limiting & Authentication\n\n";
        $content .= $this->getDynamicSecurityPolicies($openapi);

        $content .= "### HTTP Security Headers\n\n";
        $content .= $this->getSecurityHeadersTable();

        $content .= "\n### CORS Configuration\n\n";
        $content .= $this->getCorsConfigurationDetails();

        $content .= "\n### JWT Security Mechanisms\n\n";
        $content .= "JSON Web Tokens (JWTs) are a secure way to authenticate users in stateless environments. Here's how the security is maintained:\n\n";
        $content .= "- **Token Expiration**: JWT tokens have an expiration time (expiry). After a token expires, it's no longer valid for authentication. This ensures that if a token is intercepted, it can only be used for a limited time.\n\n";
        $content .= "- **Token Refresh**: When an access token expires, the user can use the refresh token to obtain a new access token without having to re-enter their credentials. The refresh token is typically long-lived and is used to generate new access tokens.\n\n";
        $content .= "- **Token Blacklisting**: While expired tokens can't be used for authentication, they can be blacklisted to ensure that even if an attacker gets hold of a valid token, it won't work after being blacklisted. Laravel has a built-in blacklist mechanism for this purpose.\n\n";
        $content .= "- **Statelessness**: JWTs are self-contained and do not require server-side storage. This makes JWT-based authentication suitable for scalable and distributed systems.\n\n";
        $content .= "### Handling Expired Tokens and Refreshing\n\n";
        $content .= "When an access token expires, the user can use the refresh token to request a new access token. This is done by making a request to the `/api/auth/refresh` endpoint, providing the expired token in the authorization header. The API then responds with a new access token, extending the user's session.\n\n";
        $content .= "### Blacklisting Tokens\n\n";
        $content .= "If a token is compromised or a user logs out, their tokens can be blacklisted. Blacklisting means that even if an expired token is used for refresh, the new access token won't be generated. Laravel's built-in mechanism takes care of blacklisting tokens to enhance security.\n\n";

        File::ensureDirectoryExists(dirname($overviewPath));
        File::put($overviewPath, $content);
        $this->info("overview.md successfully generated at: {$overviewPath}");

        // Update in-memory configuration to ensure the subsequent export command uses the fresh description
        config(['scramble.info.description' => $content]);

        // Sync changes back to Scramble files
        if (!$this->option('no-api-export')) {
            $this->info('Re-exporting OpenAPI spec to include new overview description...');
            $this->call('api:export');
            $this->info('Re-generating Postman Collection...');
            $this->call('postman:generate');
        }
    }

    private function generateTree($dir, $prefix = '', $exclude = [])
    {
        $result = '';
        $files = array_diff(scandir($dir), ['.', '..']);

        $files = array_filter($files, function ($file) use ($exclude) {
            return !in_array($file, $exclude) && substr($file, 0, 1) !== '.' && $file !== 'artisan' && $file !== 'phpunit.xml' && $file !== 'composer.json' && $file !== 'composer.lock' && $file !== 'package.json' && $file !== 'vite.config.js' && $file !== 'package-lock.json';
        });

        if ($prefix === '') {
            $files = array_filter($files, function ($file) {
                return in_array($file, ['app', 'database', 'docs', 'routes', 'tests']);
            });
        }

        $files = array_values($files);
        $count = count($files);

        foreach ($files as $index => $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            $isLast = ($index === $count - 1);
            $connector = $isLast ? '└── ' : '├── ';

            if (is_dir($path)) {
                $result .= $prefix . $connector . $file . "/\n";
                $newPrefix = $prefix . ($isLast ? '    ' : '│   ');
                $result .= $this->generateTree($path, $newPrefix, []);
            } else {
                $result .= $prefix . $connector . $file . "\n";
            }
        }
        return $result;
    }

    private function getEndpointsSummaryTable(array $openapi)
    {
        $markdown = "";
        $grouped = [];

        if (empty($openapi['paths'])) {
            return "| Method | Endpoint | Success Status | Description |\n|---|---|---|---|\n";
        }

        foreach ($openapi['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                $methodUpper = strtoupper($method);
                $description = $operation['description'] ?? ($operation['summary'] ?? 'No description provided.');

                $successStatus = '200 OK';
                if (!empty($operation['responses'])) {
                    foreach ($operation['responses'] as $code => $res) {
                        if (str_starts_with($code, '2')) {
                            $statusText = match ((int) $code) {
                                201 => 'Created',
                                204 => 'No Content',
                                default => 'OK',
                            };
                            $successStatus = "{$code} {$statusText}";
                            break;
                        }
                    }
                }

                $tags = $operation['tags'] ?? [];
                $category = !empty($tags[0]) ? $tags[0] : 'General';
                $subcategory = !empty($tags[1]) ? $tags[1] : 'General';

                $formattedPath = $path;
                if (!str_starts_with($formattedPath, '/api/')) {
                    $formattedPath = '/api/' . ltrim($formattedPath, '/');
                }
                $formattedPath = preg_replace('#/{2,}#', '/', $formattedPath);

                $grouped[$category][$subcategory][] = [
                    'path' => $formattedPath,
                    'method' => $methodUpper,
                    'row' => "| {$methodUpper} | `{$formattedPath}` | `{$successStatus}` | {$description} |",
                ];
            }
        }

        // Sort categories by group weight dynamically
        uksort($grouped, function($a, $b) {
            $wa = $this->getTagWeightByName($a);
            $wb = $this->getTagWeightByName($b);
            if ($wa === $wb) {
                return strcmp($a, $b);
            }
            return $wa <=> $wb;
        });

        foreach ($grouped as $category => $subcategories) {
            $markdown .= "### {$category}\n\n";
            
            // Sort subcategories alphabetically
            uksort($subcategories, function($a, $b) {
                return strcmp($a, $b);
            });

            foreach ($subcategories as $subcategory => $items) {
                if ($subcategory !== 'General') {
                    $markdown .= "#### {$subcategory}\n\n";
                }

                // Sort endpoints alphabetically by path, then by method weight order
                usort($items, function($a, $b) {
                    $cmp = strcmp($a['path'], $b['path']);
                    if ($cmp === 0) {
                        $methodOrder = ['GET' => 0, 'POST' => 1, 'PUT' => 2, 'PATCH' => 3, 'DELETE' => 4];
                        $wa = $methodOrder[strtoupper($a['method'])] ?? 99;
                        $wb = $methodOrder[strtoupper($b['method'])] ?? 99;
                        return $wa <=> $wb;
                    }
                    return $cmp;
                });

                $rows = array_column($items, 'row');

                $markdown .= "| Method | Endpoint | Success Status | Description |\n";
                $markdown .= "|---|---|---|---|\n";
                $markdown .= implode("\n", $rows) . "\n\n";
            }
        }

        return $markdown;
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

    private function getGlobalErrorsTable()
    {
        $filePath = app_path('Providers/ApiExceptionServiceProvider.php');
        if (!File::exists($filePath)) {
            return '';
        }

        $content = File::get($filePath);
        $markdown = "| Error Code | HTTP Status | Exception Type | Description |\n";
        $markdown .= "|---|---|---|---|\n";

        $statusMap = [
            'Response::HTTP_UNAUTHORIZED' => '401 Unauthorized',
            'Response::HTTP_FORBIDDEN' => '403 Forbidden',
            'Response::HTTP_NOT_FOUND' => '404 Not Found',
            'Response::HTTP_METHOD_NOT_ALLOWED' => '405 Method Not Allowed',
            'Response::HTTP_BAD_REQUEST' => '400 Bad Request',
            'Response::HTTP_UNPROCESSABLE_ENTITY' => '422 Unprocessable Entity',
            'Response::HTTP_TOO_MANY_REQUESTS' => '429 Too Many Requests',
            'Response::HTTP_INTERNAL_SERVER_ERROR' => '500 Internal Server Error',
        ];

        $exceptions = [];

        // Match all renderable exception closures
        preg_match_all('/\$handler->renderable\(function\s*\(\s*([a-zA-Z0-9_\\\\]+)\s+\$e.*?\}\);/s', $content, $renderableMatches);

        foreach ($renderableMatches[0] as $index => $blockText) {
            $outerClass = $renderableMatches[1][$index];
            if ($outerClass === 'ServerErrorException') {
                continue;
            }

            // Check if this block has nested try-catch blocks with json returns
            if (preg_match_all('/catch\s*\(\s*([a-zA-Z0-9_]+)\s+\$e\s*\)\s*\{([^}]+)\}/s', $blockText, $catchMatches)) {
                foreach ($catchMatches[0] as $cIndex => $catchBlockText) {
                    $catchClass = $catchMatches[1][$cIndex];
                    if (preg_match('/response\(\)->json\(\[\s*\'error_code\'\s*=>\s*([^,]+),\s*(?:[^\]]*\s*\'message\'\s*=>\s*([^,\]\n]+))?.*?\],\s*([^)]+)\)/s', $catchBlockText, $jsonMatch)) {
                        $errorCode = trim($jsonMatch[1], "'\" ");
                        $message = isset($jsonMatch[2]) ? trim($jsonMatch[2], "'\" ") : '';
                        $status = trim($jsonMatch[3]);

                        $exceptions[$errorCode . '_' . $catchClass] = [
                            'code' => $errorCode,
                            'type' => $catchClass,
                            'status' => $status,
                            'desc' => $message
                        ];
                    }
                }
            }

            // Parse the main return statement of the renderable block (outside catches)
            $mainBlockText = preg_replace('/catch\s*\(\s*[a-zA-Z0-9_]+\s+\$e\s*\)\s*\{([^}]+)\}/s', '', $blockText);

            if (preg_match('/response\(\)->json\(\[\s*\'error_code\'\s*=>\s*([^,]+),\s*(?:[^\]]*\s*\'message\'\s*=>\s*([^,\]\n]+))?.*?\],\s*([^)]+)\)/s', $mainBlockText, $jsonMatch)) {
                $errorCode = trim($jsonMatch[1], "'\" ");
                $message = isset($jsonMatch[2]) ? trim($jsonMatch[2], "'\" ") : '';
                $status = trim($jsonMatch[3]);

                // Resolve dynamic fields for Throwable fallback
                if (str_contains($mainBlockText, '$unexpected')) {
                    $errorCode = 'INTERNAL_ERROR';
                    $message = 'Sorry, something went wrong on the server. Please try again later.';
                    $status = '500';
                }

                $exceptions[$errorCode . '_' . $outerClass] = [
                    'code' => $errorCode,
                    'type' => $outerClass,
                    'status' => $status,
                    'desc' => $message
                ];
            }
        }

        // Add ModelNotFoundException and AccessDeniedHttpException manually as they are handled implicitly or dynamically
        if (str_contains($content, 'ModelNotFoundException')) {
            $exceptions['NOT_FOUND_ModelNotFoundException'] = [
                'code' => 'NOT_FOUND',
                'status' => '404',
                'type' => 'ModelNotFoundException',
                'desc' => 'The database record does not exist.'
            ];
        }
        if (str_contains($content, 'AccessDeniedHttpException')) {
            $exceptions['FORBIDDEN_AccessDeniedHttpException'] = [
                'code' => 'FORBIDDEN',
                'status' => '403',
                'type' => 'AccessDeniedHttpException',
                'desc' => 'Attempting to perform an action without required permissions.'
            ];
        }

        // Format status codes
        foreach ($exceptions as &$ex) {
            $statusRaw = $ex['status'];
            if (isset($statusMap[$statusRaw])) {
                $ex['status'] = $statusMap[$statusRaw];
            } else {
                $statusNum = (int)$statusRaw;
                $statusText = match ($statusNum) {
                    400 => 'Bad Request',
                    401 => 'Unauthorized',
                    403 => 'Forbidden',
                    404 => 'Not Found',
                    405 => 'Method Not Allowed',
                    422 => 'Unprocessable Entity',
                    429 => 'Too Many Requests',
                    500 => 'Internal Server Error',
                    default => 'Error',
                };
                $ex['status'] = "{$statusNum} {$statusText}";
            }

            if (str_contains($ex['desc'], '$e->getMessage()')) {
                $ex['desc'] = 'The request could not be understood or was malformed.';
            }
            if ($ex['desc'] === '$message') {
                $ex['desc'] = 'Requesting an endpoint or database record that does not exist.';
            }
        }
        unset($ex);

        // Sort exceptions by HTTP status code
        usort($exceptions, function($a, $b) {
            return strcmp($a['status'], $b['status']);
        });

        foreach ($exceptions as $ex) {
            $markdown .= "| `{$ex['code']}` | `{$ex['status']}` | `{$ex['type']}` | {$ex['desc']} |\n";
        }

        return $markdown;
    }

    private function getDomainExceptionsTable()
    {
        $markdown = "| Exception Class | HTTP Status | Error Code (`error_code`) | Typical Cause |\n";
        $markdown .= "|---|---|---|---|\n";

        $files = glob(app_path('Exceptions/*.php'));
        foreach ($files as $file) {
            $className = 'App\\Exceptions\\' . pathinfo($file, PATHINFO_FILENAME);
            if ($className === 'App\\Exceptions\\ServerErrorException')
                continue;

            $reflection = new \ReflectionClass($className);
            if ($reflection->isInstantiable()) {
                $instance = $reflection->newInstanceWithoutConstructor();

                $status = 500;
                if (method_exists($instance, 'getStatusCode')) {
                    try {
                        $status = $instance->getStatusCode();
                    } catch (\Throwable $e) {
                        $status = str_contains($className, 'OAuth') ? 401 : 500;
                    }
                }

                $errorCode = 'INTERNAL_ERROR';
                if (method_exists($instance, 'getErrorCode')) {
                    try {
                        $errorCode = $instance->getErrorCode();
                    } catch (\Throwable $e) {
                        $errorCode = str_contains($className, 'OAuth') ? 'TOKEN_COULD_NOT_VERIFIED' : 'INTERNAL_ERROR';
                    }
                }

                $docComment = $reflection->getDocComment() ?: '';
                $description = 'Custom domain logic exception.';
                if ($docComment) {
                    $docLines = explode("\n", $docComment);
                    $descLines = [];
                    foreach ($docLines as $line) {
                        $cleaned = trim($line, "/* \t\r\n");
                        if ($cleaned && !str_starts_with($cleaned, '@')) {
                            $descLines[] = $cleaned;
                        }
                    }
                    if (!empty($descLines)) {
                        $description = implode(' ', $descLines);
                    }
                }

                $markdown .= "| `{$reflection->getShortName()}` | `{$status}` | `{$errorCode}` | {$description} |\n";
            }
        }
        return $markdown;
    }

    private function getDynamicSecurityPolicies(array $openapi)
    {
        $maxAttempts = 0;
        $decayMinutes = 1;
        $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('api');
        if ($limiter instanceof \Closure) {
            $limit = $limiter(new \Illuminate\Http\Request());
            if ($limit instanceof \Illuminate\Cache\RateLimiting\Limit) {
                $maxAttempts = $limit->maxAttempts;
                $decaySeconds = $limit->decaySeconds ?? 60;
                $decayMinutes = (int) ($decaySeconds / 60);
                if ($decayMinutes < 1) {
                    $decayMinutes = 1;
                }
            }
        }

        if ($maxAttempts > 0) {
            $markdown = "The API applies a strict rate limit of **{$maxAttempts} requests per " . ($decayMinutes == 1 ? 'minute' : "{$decayMinutes} minutes") . "** per IP Address or Authenticated User ID.\n";
            $markdown .= "When the limit is reached, the API returns a `429 Too Many Requests` status code (`TOO_MANY_REQUESTS` global error code).\n\n";
            $markdown .= "Response headers included on every request to track your limit:\n";
            $markdown .= "- `X-RateLimit-Limit`: Maximum requests allowed per " . ($decayMinutes == 1 ? 'minute' : "{$decayMinutes} minutes") . " ({$maxAttempts})\n";
            $markdown .= "- `X-RateLimit-Remaining`: Number of requests remaining in the current period\n";
            $markdown .= "- `Retry-After`: (On a 429 response) Seconds to wait before making another request\n\n";
        } else {
            $markdown = "Rate limiting is currently not configured or disabled for the API endpoints.\n\n";
        }

        $securitySchemes = $openapi['components']['securitySchemes'] ?? [];
        if (!empty($securitySchemes)) {
            $markdown .= "#### Security & Authentication Schemes\n\n";
            foreach ($securitySchemes as $name => $scheme) {
                $type = $scheme['type'] ?? 'unknown';
                $description = $scheme['description'] ?? 'Protected endpoints require this authentication scheme.';
                $markdown .= "- **{$name}** (Type: `{$type}`): {$description}\n";
            }
            $markdown .= "\n";
        }

        return $markdown;
    }

    private function getSecurityHeadersTable()
    {
        $filePath = app_path('Http/Middleware/SecurityHeaders.php');
        if (!File::exists($filePath))
            return '';

        $content = File::get($filePath);
        $markdown = "| Header | Value | Purpose |\n";
        $markdown .= "|---|---|---|\n";

        $lines = explode("\n", $content);
        $lastComment = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (str_starts_with($trimmed, '//')) {
                $lastComment = trim($trimmed, '/ ');
            }

            if (preg_match('/\$response->headers->set\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*(?:[\']([^\']+)[\']|["]([^"]+)["])\s*\);/', $trimmed, $matches)) {
                $header = $matches[1];
                $value = !empty($matches[3]) ? $matches[3] : $matches[2];
                $purpose = $lastComment ?: 'API response protection header.';

                $markdown .= "| `{$header}` | `{$value}` | {$purpose} |\n";
                $lastComment = '';
            }
        }

        return $markdown;
    }

    private function getCorsConfigurationDetails()
    {
        $cors = config('cors', []);

        $paths = implode(', ', (array) ($cors['paths'] ?? []));
        $origins = implode(', ', (array) ($cors['allowed_origins'] ?? []));
        $methods = implode(', ', (array) ($cors['allowed_methods'] ?? []));
        $headers = implode(', ', (array) ($cors['allowed_headers'] ?? []));
        $credentials = ($cors['supports_credentials'] ?? false) ? 'true' : 'false';
        $maxAge = $cors['max_age'] ?? 0;

        $appName = config('app.name', 'Marketplace API');
        if (!str_ends_with(strtolower($appName), 'api')) {
            $appName .= ' API';
        }

        $markdown = "The {$appName} implements Cross-Origin Resource Sharing (CORS) policies to control which external domains are allowed to access resources.\n\n";
        $markdown .= "The current CORS configuration is dynamically parsed below:\n\n";
        $markdown .= "| CORS Directive | Configured Value | Description |\n";
        $markdown .= "|---|---|---|\n";
        $markdown .= "| `Allowed Paths` | `{$paths}` | Paths for which cross-origin requests are enabled. |\n";
        $markdown .= "| `Allowed Origins` | `{$origins}` | Allowed origins (domains) that can access the API. |\n";
        $markdown .= "| `Allowed Methods` | `{$methods}` | HTTP methods permitted when accessing the resource. |\n";
        $markdown .= "| `Allowed Headers` | `{$headers}` | HTTP headers that can be used during the actual request. |\n";
        $markdown .= "| `Supports Credentials` | `{$credentials}` | Indicates whether the request can be made using credentials (cookies, HTTP auth). |\n";
        $markdown .= "| `Max Age` | `{$maxAge}` | Seconds the results of a preflight request can be cached. |\n";

        return $markdown;
    }
}
