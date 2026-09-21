<?php
namespace Vanderbilt\REDCap\Tests\Cache\Helpers;

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Cache\Helpers\MemorySafeSerializer;
use Vanderbilt\REDCap\Classes\Cache\Exceptions\SerializationMemoryException;

class MemorySafeSerializerTest extends TestCase
{
    private $serializer;

    protected function setUp(): void
    {
        $this->serializer = new MemorySafeSerializer();
    }

    public function testSerializeScalarValues()
    {
        // Test integers
        $result = $this->serializer->serialize(42);
        $this->assertEquals(serialize(42), $result);

        // Test strings
        $result = $this->serializer->serialize("hello");
        $this->assertEquals(serialize("hello"), $result);

        // Test floats
        $result = $this->serializer->serialize(3.14);
        $this->assertEquals(serialize(3.14), $result);

        // Test booleans
        $result = $this->serializer->serialize(true);
        $this->assertEquals(serialize(true), $result);

        // Test null
        $result = $this->serializer->serialize(null);
        $this->assertEquals(serialize(null), $result);
    }

    public function testSerializeSmallArray()
    {
        $data = ['key1' => 'value1', 'key2' => 'value2'];
        $result = $this->serializer->serialize($data);
        $this->assertEquals(serialize($data), $result);
    }

    public function testSerializeSimpleObject()
    {
        $obj = new \stdClass();
        $obj->property = 'value';
        
        $result = $this->serializer->serialize($obj);
        $this->assertEquals(serialize($obj), $result);
    }

    public function testSerializeSmallString()
    {
        $smallString = str_repeat('a', 100); // Much smaller than CHUNK_SIZE
        $result = $this->serializer->serialize($smallString);
        $this->assertEquals(serialize($smallString), $result);
    }

    public function testSerializeLargeString()
    {
        $largeString = str_repeat('a', MemorySafeSerializer::CHUNK_SIZE * 2); // Larger than CHUNK_SIZE
        $result = $this->serializer->serialize($largeString);
        $this->assertEquals(serialize($largeString), $result);
    }

    public function testSerializeLargeArray()
    {
        $largeArray = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeArray["key_{$i}"] = "value_{$i}";
        }
        
        $result = $this->serializer->serialize($largeArray);
        $this->assertEquals(serialize($largeArray), $result);
    }

    public function testUnserializeValidData()
    {
        $originalData = ['test' => 'data', 'numbers' => [1, 2, 3]];
        $serialized = serialize($originalData);
        
        $result = $this->serializer->unserialize($serialized);
        $this->assertEquals($originalData, $result);
    }

    public function testUnserializeWithAllowedClasses()
    {
        $obj = new \stdClass();
        $obj->property = 'value';
        $serialized = serialize($obj);
        
        $result = $this->serializer->unserialize($serialized, [\stdClass::class]);
        $this->assertEquals($obj, $result);
    }

    public function testUnserializeInvalidDataThrowsException()
    {
        $this->expectException(SerializationMemoryException::class);
        $this->expectExceptionMessage('Invalid serialized data format');
        
        $this->serializer->unserialize('invalid_serialized_data');
    }

    public function testUnserializeSerializedFalse()
    {
        // Test that serialized false is handled correctly (edge case)
        $serializedFalse = serialize(false);
        $result = $this->serializer->unserialize($serializedFalse);
        $this->assertFalse($result);
    }

    public function testUnserializeEmptyString()
    {
        $this->expectException(SerializationMemoryException::class);
        $this->expectExceptionMessage('Invalid serialized data format');
        
        $this->serializer->unserialize('');
    }

    public function testSerializeEmptyArray()
    {
        $emptyArray = [];
        $result = $this->serializer->serialize($emptyArray);
        $this->assertEquals(serialize($emptyArray), $result);
    }

    public function testSerializeNestedArray()
    {
        $nestedArray = [
            'level1' => [
                'level2' => [
                    'level3' => 'deep_value'
                ]
            ],
            'other_key' => 'other_value'
        ];
        
        $result = $this->serializer->serialize($nestedArray);
        $this->assertEquals(serialize($nestedArray), $result);
    }

    public function testRoundTripSerialization()
    {
        $originalData = [
            'string' => 'test string',
            'number' => 42,
            'array' => [1, 2, 3, 4, 5],
            'nested' => [
                'inner' => 'value'
            ]
        ];
        
        $serialized = $this->serializer->serialize($originalData);
        $unserialized = $this->serializer->unserialize($serialized);
        
        $this->assertEquals($originalData, $unserialized);
    }

    public function testMemoryConstraintsConstants()
    {
        $this->assertEquals(0.8, MemorySafeSerializer::MEMORY_SAFETY_THRESHOLD);
        $this->assertEquals(8192, MemorySafeSerializer::CHUNK_SIZE);
    }

    /**
     * Test with extremely large array that might trigger memory constraints
     * Note: This test should be run with appropriate memory limits to actually test the constraints
     */
    public function testMemoryConstraintsWithVeryLargeData()
    {
        // Skip this test if memory limit is unlimited to avoid hanging
        if (ini_get('memory_limit') == -1) {
            $this->markTestSkipped('Memory limit is unlimited, skipping memory constraint test');
        }

        // Create a very large array
        $veryLargeArray = [];
        for ($i = 0; $i < 100000; $i++) {
            $veryLargeArray["key_{$i}"] = str_repeat('x', 1000);
        }
        
        // This might throw a SerializationMemoryException depending on memory settings
        try {
            $result = $this->serializer->serialize($veryLargeArray);
            // If it succeeds, verify the result
            $this->assertIsString($result);
        } catch (SerializationMemoryException $e) {
            // If it fails due to memory, that's also a valid outcome
            $this->assertTrue($e->isMemoryRelated());
        }
    }

    public function testDataTypeDetection()
    {
        // Create an object that cannot be serialized (contains closure)
        $obj = new \stdClass();
        $obj->closure = function() { return 'test'; };
        
        try {
            $this->serializer->serialize($obj);
            $this->fail('Expected SerializationMemoryException was not thrown');
        } catch (SerializationMemoryException $e) {
            $this->assertEquals('object', $e->getDataType());
            $this->assertEquals(SerializationMemoryException::REASON_SERIALIZATION_ERROR, $e->getReason());
            $this->assertStringContainsString('serialize', $e->getMessage());
        }
    }


    protected function tearDown(): void
    {
        $this->serializer = null;
    }
}