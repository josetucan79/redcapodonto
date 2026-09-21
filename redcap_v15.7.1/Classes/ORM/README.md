# EntityManagerBuilder

A fluent builder pattern implementation for configuring and creating Doctrine EntityManager instances in REDCap.

## Overview

The `EntityManagerBuilder` provides a flexible and intuitive way to configure Doctrine EntityManager instances with various settings. It follows the builder pattern to allow step-by-step construction with a fluent interface.

## Basic Usage

```php
// Create an EntityManager with default settings
$entityManager = EntityManagerBuilder::create()->build();
```

## Advanced Configuration

### Development Mode

```php
// Set development mode explicitly
$entityManager = EntityManagerBuilder::create()
    ->setDevMode(true)
    ->build();
```

### Custom Entity Paths

```php
// Specify custom entity paths
$entityManager = EntityManagerBuilder::create()
    ->setEntityPaths([
        __DIR__ . '/CustomEntities',
        __DIR__ . '/AnotherEntityPath'
    ])
    ->build();
```

### Proxy Configuration

```php
// Configure proxy settings
$entityManager = EntityManagerBuilder::create()
    ->setProxyDir(APP_PATH_TEMP . 'custom-doctrine/proxies/')
    ->setProxyNamespace('MyApp\DoctrineProxies')
    ->setAutoGenerateProxyClasses(true)
    ->build();
```

### Proxy Generation Control

```php
// Force regeneration of proxy classes
$entityManager = EntityManagerBuilder::create()
    ->setForceProxyRegeneration(true)
    ->build();

// Disable version checking for proxies
$entityManager = EntityManagerBuilder::create()
    ->setCheckProxyVersions(false)
    ->build();
```

### Event Manager

```php
// Add a custom event manager
$eventManager = new EventManager();
$eventManager->addEventSubscriber(new MyCustomSubscriber());

$entityManager = EntityManagerBuilder::create()
    ->setEventManager($eventManager)
    ->build();
```

### Database Connection

```php
// Create EntityManager with independent connection (not using REDCap's connection)
$entityManager = EntityManagerBuilder::create()
    ->useExistingConnection(false)
    ->build();
```