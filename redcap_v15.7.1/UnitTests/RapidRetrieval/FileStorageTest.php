<?php
namespace Vanderbilt\REDCap\Tests\Cache\StorageSystems;

use Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Vanderbilt\REDCap\Classes\Cache\StorageSystems\FileStorage;
use Vanderbilt\REDCap\Classes\Cache\Helpers\MemorySafeSerializer;
use Vanderbilt\REDCap\Classes\Cache\Exceptions\SerializationMemoryException;
use Vanderbilt\REDCap\Classes\Utility\FileCache\FileCache;

class FileStorageTest extends TestCase
{
    /** @var MockObject&FileCache */
    private $mockFileCache;
    
    /** @var MockObject&MemorySafeSerializer */
    private $mockSerializer;
    
    /** @var FileStorage */
    private $fileStorage;
    
    private $projectId = 123;

    protected function setUp(): void
    {
        $this->mockFileCache = $this->createMock(FileCache::class);
        $this->mockSerializer = $this->createMock(MemorySafeSerializer::class);
        
        // Mock the getList operation that happens in destructor
        $this->mockFileCache->method('get')
            ->with(FileStorage::METADATA_KEY)
            ->willReturn(serialize([]));
        
        // Allow saveList in destructor
        $this->mockFileCache->method('set')
            ->with(FileStorage::METADATA_KEY, $this->anything(), $this->anything());
        
        $this->fileStorage = new FileStorage(
            $this->projectId, 
            $this->mockFileCache, 
            $this->mockSerializer
        );
    }

    public function testAddWithMemoryRelatedSerializationException()
    {
        $cacheKey = 'test_key';
        $testData = ['large' => 'data_array'];
        
        // Create memory-related exception
        $memoryException = new SerializationMemoryException(
            'Memory limit approaching',
            SerializationMemoryException::REASON_MEMORY_LIMIT,
            'array',
            1000000
        );
        
        // Create a fresh mock for this specific test
        $this->mockSerializer = $this->createMock(MemorySafeSerializer::class);
        $this->mockFileCache = $this->createMock(FileCache::class);
        
        // Mock serializer throwing memory exception
        $this->mockSerializer->expects($this->once())
            ->method('serialize')
            ->with($testData)
            ->willThrowException($memoryException);
        
        // Only allow the metadata operations (for destructor)
        $this->mockFileCache->method('get')
            ->with(FileStorage::METADATA_KEY)
            ->willReturn(serialize([]));
        $this->mockFileCache->method('set')
            ->with(FileStorage::METADATA_KEY, $this->anything(), $this->anything());
        
        // Create file storage with specific mocks
        $fileStorage = new FileStorage($this->projectId, $this->mockFileCache, $this->mockSerializer);
        
        // Expect the FileStorage to throw an Exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Memory limit approaching/');
        
        $fileStorage->add($cacheKey, $testData);
    }

    public function testAddWithDataTooLargeSerializationException()
    {
        $cacheKey = 'test_key';
        $testData = ['huge' => 'data_structure'];
        
        // Create data-too-large exception
        $dataException = new SerializationMemoryException(
            'Data too large to serialize',
            SerializationMemoryException::REASON_DATA_TOO_LARGE,
            'array',
            2000000
        );
        
        // Create fresh mocks
        $this->mockSerializer = $this->createMock(MemorySafeSerializer::class);
        $this->mockFileCache = $this->createMock(FileCache::class);
        
        // Mock serializer throwing data-too-large exception
        $this->mockSerializer->expects($this->once())
            ->method('serialize')
            ->with($testData)
            ->willThrowException($dataException);
        
        // Allow metadata operations
        $this->mockFileCache->method('get')
            ->with(FileStorage::METADATA_KEY)
            ->willReturn(serialize([]));
        $this->mockFileCache->method('set')
            ->with(FileStorage::METADATA_KEY, $this->anything(), $this->anything());
        
        $fileStorage = new FileStorage($this->projectId, $this->mockFileCache, $this->mockSerializer);
        
        // Expect the FileStorage to throw an Exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Data too large to serialize/');
        
        $fileStorage->add($cacheKey, $testData);
    }

    public function testAddWithNonMemorySerializationException()
    {
        $cacheKey = 'test_key';
        $testData = ['test' => 'data'];
        
        // Create non-memory-related exception
        $serializationException = new SerializationMemoryException(
            'Object cannot be serialized',
            SerializationMemoryException::REASON_SERIALIZATION_ERROR,
            'object',
            500000
        );
        
        // Create fresh mocks
        $this->mockSerializer = $this->createMock(MemorySafeSerializer::class);
        $this->mockFileCache = $this->createMock(FileCache::class);
        
        // Mock serializer throwing serialization exception
        $this->mockSerializer->expects($this->once())
            ->method('serialize')
            ->with($testData)
            ->willThrowException($serializationException);
        
        // Allow metadata operations
        $this->mockFileCache->method('get')
            ->with(FileStorage::METADATA_KEY)
            ->willReturn(serialize([]));
        $this->mockFileCache->method('set')
            ->with(FileStorage::METADATA_KEY, $this->anything(), $this->anything());
        
        $fileStorage = new FileStorage($this->projectId, $this->mockFileCache, $this->mockSerializer);
        
        // Expect the FileStorage to throw an Exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Object cannot be serialized/');
        
        $fileStorage->add($cacheKey, $testData);
    }

    public function testAddSuccessfulCaching()
    {
        $cacheKey = 'test_key';
        $testData = ['test' => 'data'];
        $ttl = 3600;
        
        // Create fresh mocks
        $this->mockSerializer = $this->createMock(MemorySafeSerializer::class);
        $this->mockFileCache = $this->createMock(FileCache::class);
        
        // Mock successful serialization
        $this->mockSerializer->expects($this->once())
            ->method('serialize')
            ->with($testData)
            ->willReturn('serialized_test_data');
        
        // Mock list operations first (for getList in updateList)
        $this->mockFileCache->expects($this->once())
            ->method('get')
            ->with(FileStorage::METADATA_KEY)
            ->willReturn(serialize([]));
        
        // Use a callback to handle multiple set calls
        $setCallCount = 0;
        $this->mockFileCache->expects($this->atLeast(1))
            ->method('set')
            ->willReturnCallback(function($key, $data, $cacheTtl) use ($cacheKey, $ttl, &$setCallCount) {
                $setCallCount++;
                if ($setCallCount === 1) {
                    // First call should be for the actual cache data
                    $this->assertEquals($cacheKey, $key);
                    $this->assertEquals($ttl, $cacheTtl);
                } else {
                    // Subsequent calls are for metadata (destructor)
                    $this->assertEquals(FileStorage::METADATA_KEY, $key);
                }
                return true;
            });
        
        $fileStorage = new FileStorage($this->projectId, $this->mockFileCache, $this->mockSerializer);
        
        // This should succeed without throwing exceptions
        $result = $fileStorage->add($cacheKey, $testData, $ttl);
        
        $this->assertNotNull($result);
    }

    protected function tearDown(): void
    {
        $this->mockFileCache = null;
        $this->mockSerializer = null;
        $this->fileStorage = null;
    }
}