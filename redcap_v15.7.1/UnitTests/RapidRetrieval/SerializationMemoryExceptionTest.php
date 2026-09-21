<?php
namespace Vanderbilt\REDCap\Tests\Cache\Exceptions;

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Cache\Exceptions\SerializationMemoryException;

class SerializationMemoryExceptionTest extends TestCase
{
    public function testConstructorWithDefaults()
    {
        $exception = new SerializationMemoryException();
        
        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(SerializationMemoryException::REASON_SERIALIZATION_ERROR, $exception->getReason());
        $this->assertEquals('unknown', $exception->getDataType());
        $this->assertNull($exception->getMemoryUsage());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithAllParameters()
    {
        $message = 'Test error message';
        $reason = SerializationMemoryException::REASON_MEMORY_LIMIT;
        $dataType = 'array';
        $memoryUsage = 1024000;
        $previous = new \Exception('Previous exception');
        
        $exception = new SerializationMemoryException($message, $reason, $dataType, $memoryUsage, $previous);
        
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($reason, $exception->getReason());
        $this->assertEquals($dataType, $exception->getDataType());
        $this->assertEquals($memoryUsage, $exception->getMemoryUsage());
        $this->assertEquals($previous, $exception->getPrevious());
    }

    public function testReasonConstants()
    {
        $this->assertEquals('memory_limit', SerializationMemoryException::REASON_MEMORY_LIMIT);
        $this->assertEquals('serialization_error', SerializationMemoryException::REASON_SERIALIZATION_ERROR);
        $this->assertEquals('data_too_large', SerializationMemoryException::REASON_DATA_TOO_LARGE);
    }

    public function testIsMemoryRelatedWithMemoryLimit()
    {
        $exception = new SerializationMemoryException(
            'Memory limit reached',
            SerializationMemoryException::REASON_MEMORY_LIMIT,
            'array',
            1024000
        );
        
        $this->assertTrue($exception->isMemoryRelated());
    }

    public function testIsMemoryRelatedWithDataTooLarge()
    {
        $exception = new SerializationMemoryException(
            'Data too large',
            SerializationMemoryException::REASON_DATA_TOO_LARGE,
            'string',
            2048000
        );
        
        $this->assertTrue($exception->isMemoryRelated());
    }

    public function testIsMemoryRelatedWithSerializationError()
    {
        $exception = new SerializationMemoryException(
            'Serialization failed',
            SerializationMemoryException::REASON_SERIALIZATION_ERROR,
            'object',
            512000
        );
        
        $this->assertFalse($exception->isMemoryRelated());
    }

    public function testGetReason()
    {
        $exception = new SerializationMemoryException(
            'Test message',
            SerializationMemoryException::REASON_DATA_TOO_LARGE
        );
        
        $this->assertEquals(SerializationMemoryException::REASON_DATA_TOO_LARGE, $exception->getReason());
    }

    public function testGetDataType()
    {
        $exception = new SerializationMemoryException(
            'Test message',
            SerializationMemoryException::REASON_SERIALIZATION_ERROR,
            'string'
        );
        
        $this->assertEquals('string', $exception->getDataType());
    }

    public function testGetMemoryUsage()
    {
        $memoryUsage = 1024000;
        $exception = new SerializationMemoryException(
            'Test message',
            SerializationMemoryException::REASON_MEMORY_LIMIT,
            'array',
            $memoryUsage
        );
        
        $this->assertEquals($memoryUsage, $exception->getMemoryUsage());
    }

    public function testExceptionInheritance()
    {
        $exception = new SerializationMemoryException('Test message');
        
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertEquals(0, $exception->getCode()); // Default code should be 0
    }

    public function testWithPreviousException()
    {
        $previousException = new \RuntimeException('Original error');
        $exception = new SerializationMemoryException(
            'Serialization failed',
            SerializationMemoryException::REASON_SERIALIZATION_ERROR,
            'object',
            null,
            $previousException
        );
        
        $this->assertEquals($previousException, $exception->getPrevious());
    }

    public function testMemoryRelatedReasons()
    {
        $memoryReasons = [
            SerializationMemoryException::REASON_MEMORY_LIMIT,
            SerializationMemoryException::REASON_DATA_TOO_LARGE
        ];
        
        foreach ($memoryReasons as $reason) {
            $exception = new SerializationMemoryException('Test', $reason);
            $this->assertTrue($exception->isMemoryRelated(), "Reason '{$reason}' should be memory-related");
        }
        
        $nonMemoryReasons = [
            SerializationMemoryException::REASON_SERIALIZATION_ERROR,
            'some_other_reason'
        ];
        
        foreach ($nonMemoryReasons as $reason) {
            $exception = new SerializationMemoryException('Test', $reason);
            $this->assertFalse($exception->isMemoryRelated(), "Reason '{$reason}' should not be memory-related");
        }
    }
}