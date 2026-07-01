<?php

namespace App\Docs;

use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use App\Exceptions\ServerErrorException;
use Illuminate\Support\Str;

class ServerErrorExceptionExtension extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(ServerErrorException::class);
    }

    public function toResponse(Type $type)
    {
        $className = ltrim($type->name, '\\');
        $baseName = class_basename($className);
        
        $statusCode = 500;
        $errorCode = 'INTERNAL_ERROR';
        $message = 'This action cannot be completed.';
        $description = 'A generic fallback for unhandled domain errors.';
        
        try {
            $reflection = new \ReflectionClass($className);
            if ($reflection->isInstantiable()) {
                $instance = $reflection->newInstanceWithoutConstructor();
                if ($instance instanceof ServerErrorException) {
                    $statusCode = $instance->getStatusCode();
                    $errorCode = $instance->getErrorCode();
                }
                
                $docComment = $reflection->getDocComment() ?: '';
                
                // Extract description lines (ignoring lines starting with @)
                $docLines = explode("\n", $docComment);
                $descLines = [];
                foreach ($docLines as $line) {
                    $cleaned = trim($line, "/* \t\r\n");
                    if ($cleaned && strpos($cleaned, '@') !== 0) {
                        $descLines[] = $cleaned;
                    }
                }
                if (!empty($descLines)) {
                    $description = implode(' ', $descLines);
                }

                if (preg_match('/^\s*\*\s*@message\s+(.+)/m', $docComment, $matches)) {
                    $message = trim($matches[1]);
                }
            }
        } catch (\Throwable $e) {
            // fallback
        }

        $responseBodyType = (new OpenApiTypes\ObjectType)
            ->addProperty(
                'error_code',
                (new OpenApiTypes\StringType)
                    ->setDescription('The domain-specific error code.')
                    ->example($errorCode)
            )
            ->addProperty(
                'exception_type',
                (new OpenApiTypes\StringType)
                    ->setDescription('The exception class name.')
                    ->example($baseName)
            )
            ->addProperty(
                'message',
                (new OpenApiTypes\StringType)
                    ->setDescription('A human-readable error message.')
                    ->example($message)
            )
            ->setRequired(['error_code', 'exception_type', 'message']);

        return Response::make($statusCode)
            ->setDescription($description)
            ->setContent(
                'application/json',
                Schema::fromType($responseBodyType),
            );
    }
}
