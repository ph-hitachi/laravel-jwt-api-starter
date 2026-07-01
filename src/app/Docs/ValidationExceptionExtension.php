<?php

namespace App\Docs;

use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Validation\ValidationException;

class ValidationExceptionExtension extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type)
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(ValidationException::class);
    }

    public function toResponse(Type $type)
    {
        $validationResponseBodyType = (new OpenApiTypes\ObjectType)
            ->addProperty(
                'error_code',
                (new OpenApiTypes\StringType)
                    ->setDescription('The specific error code identifying a validation failure.')
                    ->example('VALIDATION_ERROR')
            )
            ->addProperty(
                'message',
                (new OpenApiTypes\StringType)
                    ->setDescription('Errors overview message.')
                    ->example('The given data was invalid.')
            )
            ->addProperty(
                'errors',
                (new OpenApiTypes\ObjectType)
                    ->setDescription('A detailed description of each field that failed validation.')
                    ->additionalProperties((new OpenApiTypes\ArrayType)->setItems((new OpenApiTypes\StringType)->example('The field is required.')))
            )
            ->setRequired(['error_code', 'message', 'errors']);

        return Response::make(422)
            ->setDescription('Validation error')
            ->setContent(
                'application/json',
                Schema::fromType($validationResponseBodyType),
            );
    }
}
