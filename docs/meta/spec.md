# Pandora — Package Specification

> **Cluster:** `runtime`
> **Language:** `php`
> **Milestone:** `m2`
> **Repo:** `https://github.com/decodelabs/pandora`
> **Role:** PSR container

## Overview

### Purpose

Pandora provides a simple, powerful, and flexible PSR-11 dependency injection container. It enables:

- Binding interfaces to implementations
- Binding instances directly
- Binding factories (closures)
- Conditional binding (only if not already bound)
- Parameter injection for constructor arguments
- Lazy proxy instantiation for deferred loading
- Service provision integration (PureService, Service, EagreService)
- Archetype-based class resolution
- Container self-registration
- Preparator callbacks for instance customization
- Slingshot integration for automatic dependency resolution

Pandora serves as the core dependency injection system for Decode Labs applications, managing object instantiation and lifecycle.

### Non-Goals

- Pandora does not provide application lifecycle management (delegates to Kingdom)
- It does not provide configuration management (delegates to Dovetail)
- It does not handle service discovery or auto-wiring of entire directories
- It does not provide caching or persistence
- It does not handle circular dependency detection beyond PHP's native mechanisms
- It does not provide scoped containers (request, session)

## Role in the Ecosystem

### Cluster & Positioning

Pandora belongs to the **runtime** cluster, providing core dependency injection capabilities. It implements the PSR-11 container interface and the Kingdom `ContainerAdapter` interface, serving as the standard container implementation across the ecosystem.

### Usage Contexts

Pandora is used for:

- Application dependency injection
- Service management and instantiation
- Interface-to-implementation binding
- Constructor parameter injection
- Lazy object loading
- Container-aware service provision
- Instance preparation and customization

## Public Surface

### Key Types

- **`Container`** — Main container class implementing PSR-11 `ContainerInterface` and Kingdom `ContainerAdapter`. Manages bindings and provides dependency resolution.

- **`Binding`** — Generic class representing a binding between a type and its implementation. Manages factory, target, instance, parameters, and preparators.

### Main Entry Points

- **`Container::__construct(?Archetype $archetype = null)`** — Creates container instance. Binds Archetype to container. Creates new Archetype if not provided.

- **`Container::bind(string $type, string|object|null $target = null): Binding`** — Creates a binding. Target can be a class name, closure, or instance. Returns Binding for further configuration.

- **`Container::tryBind(string $type, string|object|null $target = null): ?Binding`** — Creates binding only if type is not already bound. Returns null if already bound.

- **`Container::set(string $type, object $instance): void`** — Binds an instance directly. Alias for `bind($type, $instance)`.

- **`Container::setFactory(string $type, Closure $factory): void`** — Binds a factory closure. Alias for `bind($type, $factory)`.

- **`Container::setType(string $type, string $instanceType, array $parameters = [], mixed ...$parameterList): void`** — Binds a type to an implementation class with constructor parameters.

- **`Container::get(string $type): object`** — Gets an instance. Creates if not exists. Throws Runtime exception if not bound and cannot be resolved.

- **`Container::tryGet(string $type): ?object`** — Gets an instance or null if not bound.

- **`Container::getOrCreate(string $type): object`** — Gets an instance or creates it using Slingshot if not bound.

- **`Container::getWith(string $type, array $params = []): object`** — Gets an instance with additional constructor parameters.

- **`Container::tryGetWith(string $type, array $params = []): ?object`** — Gets an instance with parameters or null if not bound.

- **`Container::has(string $type): bool`** — Checks if a type is bound.

- **`Container::remove(string $type): void`** — Removes a binding.

- **`Container::getBinding(string $type): Binding`** — Gets a binding. Creates binding via Archetype resolution if not found. Throws NotFound exception if cannot resolve.

- **`Container::prepare(string $type, Closure $callback): void`** — Registers a preparator callback for a type. Called after instance creation.

- **`Container::inject(string $type, string $name, mixed $value): void`** — Injects a named parameter for a type's constructor.

- **`Container::addParams(string $type, array $params): void`** — Adds multiple constructor parameters for a type.

- **`Container::clearParams(string $type): void`** — Clears all constructor parameters for a type.

- **`Container::clearAllParams(): void`** — Clears all constructor parameters for all types.

- **`Container::clear(): void`** — Clears all bindings.

- **`Container::getPsrContainer(): ContainerInterface`** — Returns self (PSR-11 container).

- **`Container::getBindings(): array`** — Returns all bindings.

- **`Binding::prepareWith(Closure $callback): void`** — Registers a preparator callback. Called after instance creation with instance and container as arguments.

- **`Binding::inject(string $name, mixed $value): void`** — Injects a named constructor parameter.

- **`Binding::addParams(array $params): void`** — Adds multiple constructor parameters.

- **`Binding::clearParams(): void`** — Clears all constructor parameters.

- **`Binding::hasInstance(): bool`** — Checks if instance has been created. Uses reflection to avoid triggering lazy instantiation.

- **`Binding::getGroupInstances(): array`** — Returns array containing instance if created, empty array otherwise.

- **`Binding::describeInstance(): string`** — Returns human-readable description of binding state (instance, factory, type, service).

## Dependencies

### Decode Labs

- **`archetype`** — Used for class resolution when types are not explicitly bound.

- **`exceptional`** — Used for exception handling throughout the package.

- **`kingdom`** — Used for ContainerAdapter interface and Service interfaces (PureService, Service, EagreService).

- **`slingshot`** — Used for automatic dependency resolution during instantiation.

### External

- **`psr/container`** — PSR-11 container interface implementation.

## Behaviour & Contracts

### Invariants

- Each type can only have one binding at a time
- Bindings are created lazily (instances are not created until accessed)
- Instances are singletons within the container
- Factory closures are invoked with container and params if they accept parameters
- PureService types are instantiated via providePureService()
- Service types are instantiated via provideService(container)
- EagreService types are initialized immediately (not lazy)
- Non-service types are created as lazy proxies
- Preparators are called after instance creation
- ContainerInterface requests resolve to the container itself
- Archetype resolution is attempted for unbound types
- Parameters are merged (later values override earlier values)
- Setting instance clears factory and target

### Input & Output Contracts

- **`Container::bind(string $type, string|object|null $target): Binding`** — Returns Binding instance. Target is null: binds to type itself. Target is instance: stores instance and clears factory/target. Target is closure: stores as factory and clears target. Target is class name: stores as target. Throws InvalidArgument if target is invalid.

- **`Container::tryBind(string $type, string|object|null $target): ?Binding`** — Returns null if type already bound, otherwise returns Binding from bind().

- **`Container::get(string $type): object`** — Returns instance. Creates via binding if exists. Throws Runtime exception if not bound and cannot be resolved.

- **`Container::tryGet(string $type): ?object`** — Returns instance or null. Never throws.

- **`Container::getOrCreate(string $type): object`** — Returns instance. Creates via Slingshot with params if not bound. Updates binding factory to use Slingshot.

- **`Container::getWith(string $type, array $params): object`** — Returns instance. Adds params to binding before instantiation.

- **`Container::getBinding(string $type): Binding`** — Returns binding. Creates new binding if not exists. Attempts Archetype resolution. Self-binds container types. Throws NotFound if cannot resolve.

- **`Container::prepare(string $type, Closure $callback): void`** — Adds preparator to binding. Callback receives instance and container.

- **`Container::inject(string $type, string $name, mixed $value): void`** — Adds named parameter to binding params.

- **`Binding::instance: object`** — Get: Returns instance. Creates via factory if set. Resolves target binding if different from type. Creates PureService via providePureService(). Creates Service via provideService(container). Creates others as lazy proxy via Slingshot. Initializes EagreService immediately. Calls preparators. Clears factory and target after creation. Set: Stores instance, clears factory and target, calls preparators.

- **`Binding::target: ?string`** — Set: Handles null, closure (stores as factory), instance (stores as instance), or class name (stores as target). Throws InvalidArgument if target is invalid.

- **`Binding::hasInstance(): bool`** — Returns true if instance property has been set. Uses reflection to avoid lazy initialization.

- **`Binding::describeInstance(): string`** — Returns description. Format: "service : ClassName", "instance : ClassName", "factory @ path:line", "type : ClassName", or "null".

## Error Handling

Pandora uses the Exceptional pattern for error handling. Key exception types:

- **`Runtime`** — Thrown when `get()` is called for a type that is not bound and cannot be resolved.

- **`NotFound`** — Thrown when `getBinding()` is called for a type that cannot be resolved. Implements PSR-11 `NotFoundExceptionInterface`.

- **`InvalidArgument`** — Thrown when binding type is not a valid interface or class, or when target cannot be converted to a factory.

Exceptions preserve context and implement PSR-11 exception interfaces where appropriate.

## Configuration & Extensibility

### Extension Points

- **Factories** — Bind closure factories to customize instantiation logic.

- **Preparators** — Register callbacks via `prepare()` or `Binding::prepareWith()` to modify instances after creation.

- **Parameter Injection** — Inject constructor parameters via `inject()` or `addParams()`.

- **Custom Archetype** — Provide custom Archetype instance to customize class resolution.

- **Service Providers** — Implement Kingdom Service interfaces (PureService, Service, EagreService) for specialized instantiation.

### Configuration

- **Binding Setup** — Bindings are typically configured during application bootstrap.

- **Archetype Integration** — Archetype is used for automatic class resolution when types are not explicitly bound.

- **Slingshot Integration** — Slingshot is used for automatic constructor dependency resolution.

- **Lazy Loading** — Non-eager services are created as lazy proxies, deferring instantiation until first use.

- **Eager Loading** — EagreService types are initialized immediately upon first binding access.

- **Parameter Management** — Parameters can be injected before instantiation and cleared after use.

## Interactions with Other Packages

- **Kingdom** — Pandora implements `ContainerAdapter` and is used as the default container implementation.

- **Slingshot** — Uses Slingshot for automatic dependency resolution during instantiation.

- **Archetype** — Uses Archetype for class resolution when types are not explicitly bound.

- **Monarch** — Detected at runtime if installed, used for path prettification in binding descriptions.

- **Clip** — Uses Pandora for CLI service management.

- **Fabric** — Uses Pandora as the application container.

## Usage Examples

### Basic Binding and Retrieval

```php
use DecodeLabs\Pandora\Container;

$container = new Container();

// Bind instance
$container->bind(CoolInterface::class, new CoolImplementation());
$imp = $container->get(CoolInterface::class);

// Bind factory
$container->bind(CoolInterface::class, fn() => new CoolImplementation());
$imp = $container->get(CoolInterface::class);

// Bind type
$container->bind(CoolInterface::class, CoolImplementation::class);
$imp = $container->get(CoolInterface::class);
```

### Conditional Binding

```php
// Only bind if not already bound
$container->tryBind(CoolInterface::class, new CoolImplementation());
$imp = $container->get(CoolInterface::class);

// Try again - will not bind
$container->tryBind(CoolInterface::class, new OtherImplementation());
$imp = $container->get(CoolInterface::class); // Still CoolImplementation
```

### Parameter Injection

```php
// Inject parameters for constructor
$container->inject(CoolInterface::class, 'paramName', 'paramValue');
$imp = $container->get(CoolInterface::class);

// Or pass parameters directly
$imp = $container->getWith(CoolInterface::class, [
    'paramName' => 'paramValue',
    'otherParam' => 123
]);

// Add multiple parameters
$container->addParams(CoolInterface::class, [
    'param1' => 'value1',
    'param2' => 'value2'
]);
```

### Instance Preparation

```php
// Register preparator callback
$container->prepare(CoolInterface::class, function($instance, $container) {
    // Customize instance after creation
    $instance->configure($container->get(ConfigInterface::class));
    return $instance;
});

$imp = $container->get(CoolInterface::class);
// Instance is configured before being returned
```

### Service Integration

```php
use DecodeLabs\Kingdom\Service;
use DecodeLabs\Kingdom\PureService;

// PureService - instantiated without container
class SimpleThing implements PureService
{
    public static function providePureService(): self
    {
        return new self();
    }
}

// Service - instantiated with container
class ComplexThing implements Service
{
    public static function provideService($container): self
    {
        return new self($container->get(DependencyInterface::class));
    }
}

// Bind and get
$container->bind(SimpleThing::class);
$simple = $container->get(SimpleThing::class);

$container->bind(ComplexThing::class);
$complex = $container->get(ComplexThing::class);
```

### Binding Management

```php
// Check if bound
if ($container->has(CoolInterface::class)) {
    // Type is bound
}

// Get binding controller
$binding = $container->getBinding(CoolInterface::class);

// Remove binding
$container->remove(CoolInterface::class);

// Clear all bindings
$container->clear();
```

### Parameter Management

```php
// Clear params for specific type
$container->clearParams(CoolInterface::class);

// Clear params for all types
$container->clearAllParams();
```

### Helper Methods

```php
// Set instance directly
$container->set(CoolInterface::class, new CoolImplementation());

// Set factory
$container->setFactory(CoolInterface::class, fn() => new CoolImplementation());

// Set type with parameters
$container->setType(
    CoolInterface::class,
    CoolImplementation::class,
    ['param1' => 'value1'],
    param2: 'value2'
);
```

### Binding Information

```php
$binding = $container->getBinding(CoolInterface::class);

// Check if instance exists
if ($binding->hasInstance()) {
    // Instance has been created
}

// Get instance description
$description = $binding->describeInstance();
// Returns: "service : ClassName", "instance : ClassName", "factory @ path:line", or "type : ClassName"
```

### Archetype Integration

```php
use DecodeLabs\Archetype;

// Create container with custom Archetype
$archetype = new Archetype();
$container = new Container($archetype);

// Container will use Archetype for class resolution
$imp = $container->get(UnboundInterface::class);
// Archetype attempts to resolve UnboundInterface
```

## Implementation Notes (for Contributors)

### Architecture

- **Binding-Based Design** — Each type-to-implementation mapping is represented by a Binding instance.

- **Lazy Instantiation** — Instances are created as lazy proxies using PHP's `newLazyProxy()`, deferring work until first use.

- **Eager Services** — EagreService types are initialized immediately using `initializeLazyObject()`.

- **Parameter Injection** — Parameters are stored in bindings and passed to Slingshot for constructor resolution.

- **Preparator Chain** — Multiple preparators can be registered per binding, called in registration order.

- **Archetype Fallback** — Unbound types are resolved via Archetype before throwing exceptions.

- **Container Self-Binding** — ContainerInterface and subclass requests automatically resolve to the container instance.

- **Service Integration** — PureService, Service, and EagreService interfaces receive special handling during instantiation.

- **Slingshot Integration** — All non-service, non-factory instantiation uses Slingshot for automatic dependency resolution.

### Performance Considerations

- Lazy proxies defer instantiation until first use, reducing upfront cost
- Instance creation is cached (singletons)
- Binding lookup is simple array access
- Preparators are stored by object ID for efficient deduplication
- Archetype resolution only occurs for unbound types

### Design Decisions

- **Lazy Proxies** — Using lazy proxies allows defining complex dependency graphs without immediate instantiation.

- **Eager Services** — EagreService opt-in provides control over initialization timing for critical services.

- **Binding Fluency** — Returning Binding from bind() enables fluent configuration.

- **tryBind Pattern** — Conditional binding prevents accidental override of existing bindings.

- **Parameter Injection** — Named parameters provide flexibility for complex constructor signatures.

- **Preparator Pattern** — Post-creation callbacks enable instance customization without subclassing.

- **Archetype Integration** — Automatic class resolution reduces boilerplate for common bindings.

- **PSR-11 Compliance** — Implementing PSR-11 ensures compatibility with ecosystem tools.

- **Kingdom Integration** — Implementing ContainerAdapter enables use as Kingdom's container.

## Testing & Quality

**Code Quality:** 4/5 — Good, mature codebase with comprehensive functionality and solid architecture.

**README Quality:** 3/5 — Good documentation with clear usage examples covering main use cases.

**Documentation:** 0/5 — No formal documentation beyond README.

**Tests:** 0/5 — No test suite currently.

See `composer.json` for supported PHP versions.

## Roadmap & Future Ideas

- Enhanced documentation and API reference
- Test suite implementation
- Scoped container support (request, session)
- Circular dependency detection
- Auto-wiring capabilities
- Tagged services
- Container compilation/caching
- Performance profiling
- Binding validation
- Enhanced debugging tools

## References

- [Decode Labs Chorus](https://github.com/decodelabs/chorus)
- [Pandora Repository](https://github.com/decodelabs/pandora)
- [Kingdom Repository](https://github.com/decodelabs/kingdom)
- [Slingshot Repository](https://github.com/decodelabs/slingshot)
- [PSR-11: Container Interface](https://www.php-fig.org/psr/psr-11/)

