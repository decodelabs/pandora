# Pandora

[![PHP from Packagist](https://img.shields.io/packagist/php-v/decodelabs/pandora?style=flat)](https://packagist.org/packages/decodelabs/pandora)
[![Latest Version](https://img.shields.io/packagist/v/decodelabs/pandora.svg?style=flat)](https://packagist.org/packages/decodelabs/pandora)
[![Total Downloads](https://img.shields.io/packagist/dt/decodelabs/pandora.svg?style=flat)](https://packagist.org/packages/decodelabs/pandora)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/decodelabs/pandora/integrate.yml?branch=develop)](https://github.com/decodelabs/pandora/actions/workflows/integrate.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-44CC11.svg?longCache=true&style=flat)](https://github.com/phpstan/phpstan)
[![License](https://img.shields.io/packagist/l/decodelabs/pandora?style=flat)](https://packagist.org/packages/decodelabs/pandora)

### PSR-11 dependency injection container

Pandora offers a simple, powerful and flexible dependency injection and instantiation system to used as the core of your application.

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

// Will only bind if CoolInterface has not been bound before
$container->tryBind(CoolInterface::class, new OtherImplementation()); // Will not bind
$imp = $container->get(CoolInterface::class);

// Bind a factory
$container->bind(CoolInterface::class, fn() => new CoolImplementation());
```


### Retrieval

Parameters can be passed to constructors of implementation classes:

```php
$imp = $container->getWith(CoolInterface::class, ['list', 'of', 'params']);

// Or inject parameters for later:
$container->inject(CoolInterface::class, 'paramName', 'paramValue');
$imp = $container->get(CoolInterface::class);
```

Access the binding controllers:

```php
$binding = $container->getBinding(CoolInterface::class);
```

## Licensing
Pandora is licensed under the MIT License. See [LICENSE](./LICENSE) for the full license text.
