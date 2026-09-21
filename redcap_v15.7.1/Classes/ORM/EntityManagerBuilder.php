<?php
namespace Vanderbilt\REDCap\Classes\ORM;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Vanderbilt\REDCap\Classes\ORM\Driver\RedcapConnectionDriver;

class EntityManagerBuilder
{
    private static $version = '1.0.0';
    
    private ?bool $isDevMode = null;
    private ?array $entityPaths = null;
    private ?Configuration $configuration = null;
    private ?Connection $connection = null;
    private ?EventManager $eventManager = null;
    private ?string $proxyDir = null;
    private ?string $proxyNamespace = null;
    private bool $autoGenerateProxies = true;
    private bool $useExistingConnection = true;
    
    /**
     * Create a new EntityManagerBuilder
     */
    public function __construct()
    {
        $this->isDevMode = isDev();
        $this->entityPaths = $this->getDefaultEntityPaths();
        $this->proxyDir = APP_PATH_TEMP . '';
        $this->proxyNamespace = 'DoctrineProxies';
    }
    
    /**
     * Create a new instance of the builder
     */
    public static function create(): self
    {
        return new self();
    }
    
    /**
     * Get the default entity paths for Doctrine configuration
     */
    private function getDefaultEntityPaths(): array
    {
        $configFile = __DIR__ . '/config/entity_paths.php';
        
        if (file_exists($configFile)) {
            return require $configFile;
        }
        
        // Fallback to default path if config doesn't exist
        return [__DIR__ . '/Entities'];
    }
    
    /**
     * Set development mode
     */
    public function setDevMode(bool $isDevMode): self
    {
        $this->isDevMode = $isDevMode;
        return $this;
    }
    
    /**
     * Set entity paths
     */
    public function setEntityPaths(array $paths): self
    {
        $this->entityPaths = $paths;
        return $this;
    }
    
    /**
     * Set proxy directory
     */
    public function setProxyDir(string $proxyDir): self
    {
        $this->proxyDir = $proxyDir;
        return $this;
    }
    
    /**
     * Set proxy namespace
     */
    public function setProxyNamespace(string $namespace): self
    {
        $this->proxyNamespace = $namespace;
        return $this;
    }
    
    /**
     * Set whether to auto-generate proxy classes
     */
    public function setAutoGenerateProxyClasses(bool $autoGenerate): self
    {
        $this->autoGenerateProxies = $autoGenerate;
        return $this;
    }
    
    /**
     * Set event manager
     */
    public function setEventManager(EventManager $eventManager): self
    {
        $this->eventManager = $eventManager;
        return $this;
    }
    
    /**
     * Set custom configuration
     */
    public function setConfiguration(Configuration $configuration): self
    {
        $this->configuration = $configuration;
        return $this;
    }
    
    /**
     * Set custom connection
     */
    public function setConnection(Connection $connection): self
    {
        $this->connection = $connection;
        $this->useExistingConnection = false;
        return $this;
    }
    
    /**
     * Use existing REDCap connection (default)
     */
    public function useExistingConnection(bool $useExisting = true): self
    {
        $this->useExistingConnection = $useExisting;
        return $this;
    }
    
    /**
     * Build the Doctrine configuration
     */
    public function buildConfiguration(): Configuration
    {
        if ($this->configuration !== null) {
            return $this->configuration;
        }

        // Create Symfony Filesystem cache adapter
        $metadataCache = new FilesystemAdapter(
            namespace: 'doctrine_metadata',
            defaultLifetime: 0,
            directory: APP_PATH_TEMP . ''
        );
        
        $config = ORMSetup::createAttributeMetadataConfiguration(
            $this->entityPaths,
            $this->isDevMode,
            $this->proxyDir,
            $metadataCache
        );
        
        // Set up proxy configuration
        $config->setProxyNamespace($this->proxyNamespace);
        $config->setAutoGenerateProxyClasses($this->autoGenerateProxies);
        
        if ($this->isDevMode) {
            $metadataCache->clear();
        }
        
        return $config;
    }
    
    /**
     * Build the database connection
     */
    public function buildConnection(): Connection
    {
        if ($this->connection !== null) {
            return $this->connection;
        }
        
        $config = $this->buildConfiguration();
        
        if ($this->useExistingConnection) {
            // Ensure REDCap connection is established
            global $rc_connection;
            if (!$rc_connection) {
                db_connect();
            }
            
            // Use the custom REDCap driver
            $connectionParams = [
                'driver' => 'pdo_mysql', // Will be overridden
                'driverClass' => RedcapConnectionDriver::class,
                // No need for connection details as we'll use the existing connection
            ];
        } else {
            // Create a new connection
            $dbConfig = $this->getDBConfig();
            $connectionParams = [
                'dbname'   => $dbConfig['db'],
                'user'     => $dbConfig['username'],
                'password' => $dbConfig['password'],
                'host'     => $dbConfig['hostname'],
                'driver'   => 'pdo_mysql',
            ];
        }
        
        return DriverManager::getConnection($connectionParams, $config);
    }
    
    /**
     * Get database configuration
     */
    private function getDBConfig(): array
    {
        global $hostname, $db, $username, $password;
        return [
            'hostname'  => $hostname,
            'db'        => $db,
            'username'  => $username,
            'password'  => $password,
        ];
    }
    
    /**
     * Internal flag to check if proxy directory is verified
     */
    private bool $proxyDirVerified = false;
    
    /**
     * Verify proxy directory exists and is writable
     */
    private function verifyProxyDirectory(): void
    {
        if ($this->proxyDirVerified) {
            return;
        }

        if (!is_dir($this->proxyDir)) {
            if (!mkdir($this->proxyDir, 0777, true) && !is_dir($this->proxyDir)) {
                throw new \RuntimeException("Failed to create proxy directory: {$this->proxyDir}");
            }
            chmod($this->proxyDir, 0777);
        } else {
            chmod($this->proxyDir, 0777); // Explicitly reset permissions in case they're off
        }

        clearstatcache(true, $this->proxyDir);

        if (!is_writable($this->proxyDir)) {
            throw new \RuntimeException("Doctrine proxy directory is not writable: {$this->proxyDir}");
        }

        $this->proxyDirVerified = true;
    }

    
    /**
     * Configure check for proxy versions
     */
    private bool $checkProxyVersions = true;
    private bool $forceProxyRegeneration = false;
    
    /**
     * Enable or disable proxy version checking
     */
    public function setCheckProxyVersions(bool $check): self
    {
        $this->checkProxyVersions = $check;
        return $this;
    }
    
    /**
     * Force proxy regeneration regardless of version
     */
    public function setForceProxyRegeneration(bool $force): self
    {
        $this->forceProxyRegeneration = $force;
        return $this;
    }
    
    /**
     * Build and return the EntityManager
     */
    public function build(): EntityManager
    {
        // Verify proxy directory
        $this->verifyProxyDirectory();
        
        // Build components
        $config = $this->buildConfiguration();
        $connection = $this->buildConnection();
        
        // Create EntityManager
        $entityManager = new EntityManager($connection, $config, $this->eventManager);
        
        // Handle proxy generation if needed
        if ($this->forceProxyRegeneration || ($this->checkProxyVersions && $this->shouldRegenerateProxies())) {
            $this->generateProxies($entityManager);
        }
        
        return $entityManager;
    }
    
    /**
     * Check if proxies should be regenerated based on version
     */
    private function shouldRegenerateProxies(): bool
    {
        $doctrineDir = dirname($this->proxyDir);
        $proxyFiles = glob($this->proxyDir . '/__CG__*.php');
        $versionFile = $doctrineDir . '/.version';
        $currentVersion = REDCAP_VERSION . '-' . self::$version;
        
        // No proxy files exist
        if (empty($proxyFiles)) {
            return true;
        }
        
        // Check version match
        if (file_exists($versionFile)) {
            $savedVersion = trim(file_get_contents($versionFile));
            return $savedVersion !== $currentVersion;
        }
        
        return true; // No version file, regenerate
    }
    
    /**
     * Generate proxy classes for EntityManager
     */
   private function generateProxies(EntityManager $entityManager): void
    {
        $doctrineDir = dirname($this->proxyDir);
        $currentVersion = REDCAP_VERSION . '-' . self::$version;

        // Clear proxy files
        $proxyFiles = glob($this->proxyDir . '/__CG__*.php');
        if (is_array($proxyFiles)) {
            foreach ($proxyFiles as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        // Clear metadata cache
        $metadataCache = new FilesystemAdapter(
            namespace: 'doctrine_metadata',
            defaultLifetime: 0,
            directory: $doctrineDir
        );
        $metadataCache->clear();

        // Generate proxies
        $proxyFactory = $entityManager->getProxyFactory();
        $metadataFactory = $entityManager->getMetadataFactory();
        $allMetadata = $metadataFactory->getAllMetadata();

        if (empty($allMetadata)) {
            throw new \RuntimeException('No metadata found. Are your entities configured correctly?');
        }

        foreach ($allMetadata as $metadata) {
            $proxyFactory->generateProxyClasses([$metadata]);
        }

        // Save the new version
        @file_put_contents($doctrineDir . '/.version', $currentVersion);
    }

}