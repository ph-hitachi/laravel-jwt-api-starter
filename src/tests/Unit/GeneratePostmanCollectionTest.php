<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Console\Commands\GeneratePostmanCollection;

class GeneratePostmanCollectionTest extends TestCase
{
    /**
     * Test the resolveSchemaExample method directly with mock schemas.
     */
    public function test_resolve_schema_example_handles_basic_types(): void
    {
        $command = new GeneratePostmanCollection();

        // Object with integer type
        $schemaInt = ['type' => 'integer', 'minimum' => 5, 'maximum' => 10];
        $valInt = $command->resolveSchemaExample($schemaInt);
        $this->assertIsInt($valInt);
        $this->assertGreaterThanOrEqual(5, $valInt);
        $this->assertLessThanOrEqual(10, $valInt);

        // String with email format
        $schemaEmail = ['type' => 'string', 'format' => 'email'];
        $valEmail = $command->resolveSchemaExample($schemaEmail);
        $this->assertIsString($valEmail);
        $this->assertStringContainsString('@', $valEmail);

        // Schema enum allowed values logic
        $schemaEnum = ['type' => 'string', 'enum' => ['wallet', 'cod']];
        $valEnum = $command->resolveSchemaExample($schemaEnum);
        $this->assertEquals('wallet', $valEnum);
    }

    /**
     * Test getMockValueByType method constraints and lengths.
     */
    public function test_get_mock_value_by_type_resolves_correct_formats(): void
    {
        $command = new GeneratePostmanCollection();

        // Test string length bounds (minLength, maxLength)
        $schemaText = ['type' => 'string', 'minLength' => 5, 'maxLength' => 10];
        $valText = $command->getMockValueByType($schemaText);
        $this->assertIsString($valText);
        $this->assertLessThanOrEqual(10, strlen($valText));

        // Test float number range bounds
        $schemaNumber = ['type' => 'number', 'minimum' => 10.5, 'maximum' => 15.5];
        $valNumber = $command->getMockValueByType($schemaNumber);
        $this->assertIsFloat($valNumber);
        $this->assertGreaterThanOrEqual(10.5, $valNumber);
        $this->assertLessThanOrEqual(15.5, $valNumber);

        // Test password formatting
        $schemaPassword = ['type' => 'string', 'format' => 'password', 'minLength' => 8, 'maxLength' => 12];
        $valPassword = $command->getMockValueByType($schemaPassword);
        $this->assertIsString($valPassword);
        $this->assertGreaterThanOrEqual(8, strlen($valPassword));
        $this->assertLessThanOrEqual(12, strlen($valPassword));
    }
}
