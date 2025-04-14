# Pandora

[![PHP from Packagist](https://img.shields.io/packagist/php-v/decodelabs/pandora?style=flat)](https://packagist.org/packages/decodelabs/pandora)
[![Latest Version](https://img.shields.io/packagist/v/decodelabs/pandora.svg?style=flat)](https://packagist.org/packages/decodelabs/pandora)
[![Total Downloads](https://img.shields.io/packagist/dt/decodelabs/pandora.svg?style=flat)](https://packagist.org/packages/decodelabs/pandora)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/decodelabs/pandora/integrate.yml?branch=develop)](https://github.com/decodelabs/pandora/actions/workflows/integrate.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-44CC11.svg?longCache=true&style=flat)](https://github.com/phpstan/phpstan)
[![License](https://img.shields.io/packagist/l/decodelabs/pandora?style=flat)](https://packagist.org/packages/decodelabs/pandora)

### Potent PSR-11 dependency injection container for PHP

Pandora offers all of the usual benefits of a solid DI container structure with some extra majic juju sprinkled in.

---


## Installation

```bash
composer require decodelabs/pandora
```

## Usage

Instantiate a new `Container` to keep your important objects organised:

```php
use DecodeLabs\Pandora\Container;

$container = new Container();
```

Bind instances or classes to interfaces and retrieve them when you need them:

```php
use My\Library\CoolInterface;
use My\Library\CoolImplementation; // Implements CoolInterface

// Instance
$container->bind(CoolInterface::class, new CoolImplementation());
$imp = $container->get(CoolInterface::class);

// Reused class
$container->bind(CoolInterface::class, CoolImplementation::class);
// Creates a new instace every call
$imp = $container->get(CoolInterface::class);

// Shared class
$container->bindShared(CoolInterface::class, CoolImplementation::class);
// Stores created instance and returns that each call
$imp = $container->get(CoolInterface::class);

// Locked instance - will only bind if CoolInterface has not been bound before
$container->bindLocked(CoolInterface::class, new CoolImplementation());
$imp = $container->get(CoolInterface::class);

// Bind a factory
$container->bind(CoolInterface::class, function($container) {
    return new CoolImplementation();
})
```

Groups allow for multiple instances to be bound to one interface:

```php
// Group multiple bindings
$container->bindToGroup(CoolInterface::class, new CoolImplementation(1));
$container->bindToGroup(CoolInterface::class, new CoolImplementation(2));
$container->bindToGroup(CoolInterface::class, new CoolImplementation(3));
$group = $container->getGroup(CoolInterface::class); // Contains 3 Implementations

$container->each(CoolInterface::class, function($instance, $container) {
    // Do something with each instance
});
```

Aliases can be useful when retrieving objects without repeating the interface:

```php
// Aliased instance
$container->bind(CoolInterface::class, new CoolImplementation())
    ->alias('cool.thing');
$imp = $container->get('cool.thing');

// Or
$container->registerAlias(CoolInterface::class, 'cool.thing');
$container->bind(CoolInterface::class, CoolImplementation::class);
$imp = $container->get('cool.thing');
```


### Retrieval

Parameters can be passed to constructors of implementation classes:

```php
$imp = $container->getWith(CoolInterface::class, ['list', 'of', 'params']);

// Or inject parameters for later:
$container->inject(CoolInterface::class, 'paramName', 'paramValue');
$imp = $container->get(CoolInterface::class);
```

Containers also have ArrayAccess aliased to get / bind / has / remove:

```php
$imp = $container[CoolInterface::class];

if(isset($container[CoolInterface::class])) {
    unset($container[CoolInterface::class]);
}
```

Access the binding controllers with member names:

```php
$binding = $container->{CoolInterface::class};

// Or
$binding = $container->getBinding(CoolInterface::class);
```

### Events

React to events on the container:

```php
$container->afterResolving(CoolInterface::class, function($instance, $container) {
    // Prepare instance
});

$container->afterRebinding(CoolInterface::class, function($instance, $container) {
    // Prepare instance again
});

// Triggers resolve callback once
$imp = $container->get(CoolInterface::class);

// Triggers rebinding callback
$container->bind(CoolInterface::class, new AnotherImplementation());
```

## Service providers

Implement the `Provider` interface and register it for lazy loaded mass-bindings with virtually no overhead:

```php
use DecodeLabs\Pandora\Provider;
use DecodeLabs\Pandora\Container;

class CoolService implements Provider {

    public static function getProvidedServices(): array {
        return [
            CoolInterface::class,
            OtherInterface::class
        ];
    }

    public function registerServices(Container $container): void {
        $container->bindShared(CoolInterface::class, CoolImplementation::class)
            ->alias('cool.thing');

        // Create factory closure
        $container->bind(OtherInterface::class, function($container) {
            return new class implements OtherInterface {

                public function hello(): string {
                    return 'world';
                }
            };
        });
    }
}


$conainer->registerProvider(CoolService::class);
```

## Licensing
Pandora is licensed under the MIT License. See [LICENSE](./LICENSE) for the full license text.
