<?php

/**
 * @package Pandora
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Pandora;

use ArrayAccess;
use Closure;

use DecodeLabs\Exceptional;
//use DecodeLabs\Reactor\Dispatcher as EventDispatcher;
use DecodeLabs\Pandora\Events as EventDispatcher;

use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface as NotFoundException;

use ReflectionClass;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionParameter;

/**
 * @implements ArrayAccess<string, mixed>
 */
class Container implements
    ContainerInterface,
    ArrayAccess
{
    /**
     * @var array<string, Binding>
     */
    protected array $bindings = [];

    /**
     * @phpstan-var array<class-string, Provider>
     */
    protected array $providers = [];

    /**
     * @var array<string, string>
     */
    protected array $aliases = [];

    /**
     * @var array<string, callable>
     */
    protected array $autoAliasers = [];

    protected EventDispatcher $events;

    /**
     * Setup with new event dispatcher
     */
    public function __construct()
    {
        $this->events = new EventDispatcher();
    }

    /**
     * Take a list of provider types and register
     *
     * @phpstan-param class-string<Provider> ...$providers
     */
    public function registerProviders(string ...$providers): void
    {
        foreach ($providers as $provider) {
            $this->registerProvider($provider);
        }
    }

    /**
     * Instantiate provider and register
     *
     * @phpstan-param class-string<Provider> $provider
     */
    public function registerProvider(string $provider): void
    {
        if (!class_exists($provider)) {
            throw Exceptional::{'Implementation,NotFound'}(
                'Service provider ' . $provider . ' could not be found'
            );
        }

        $provider = $this->newInstanceOf($provider);

        if (!$provider instanceof Provider) {
            throw Exceptional::{'Implementation'}(
                'Service provider ' . $provider . ' does not implement DecodeLabs\\Pandora\\Provider'
            );
        }

        $this->registerProviderInstance($provider);
    }

    /**
     * Register provider instance
     */
    public function registerProviderInstance(Provider $provider): void
    {
        $types = $provider::getProvidedServices();

        foreach ($types as $type) {
            if (isset($this->bindings[$type])) {
                continue;
            }

            $this->providers[$type] = $provider;

            if (null !== ($alias = $this->autoAlias($type))) {
                $this->registerAlias($type, $alias);
            }
        }
    }

    /**
     * Get list of registered providers
     *
     * @return array<string, Provider>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Generate an automated alias for a type
     *
     * @phpstan-param class-string $type
     */
    public function autoAlias(string $type): ?string
    {
        foreach ($this->autoAliasers as $aliaser) {
            $alias = $aliaser($type);

            if (
                is_string($alias) &&
                !empty($alias)
            ) {
                return $alias;
            }
        }

        return null;
    }

    /**
     * Register auto aliaser
     */
    public function registerAutoAliaser(
        string $name,
        callable $aliaser
    ): void {
        $this->autoAliasers[$name] = $aliaser;
    }

    /**
     * Unregister auto aliaser
     */
    public function unregisterAutoAliaser(string $name): void
    {
        unset($this->autoAliasers[$name]);
    }

    /**
     * Bind a concrete type or instance to interface
     */
    public function bind(
        string $type,
        string|object|null $target = null
    ): Binding {
        // Create binding
        $binding = new Binding($this, $type, $target);
        $type = $binding->getType();

        // Remove old binding
        if ($oldBinding = ($this->bindings[$type] ?? null)) {
            $this->remove($type);
        }

        // Add new binding
        $this->bindings[$type] = $binding;

        // Remove provider reference
        unset($this->providers[$type]);

        if ($oldBinding) {
            // Trigger rebinding event
            $this->triggerAfterRebinding($binding);
        }

        return $binding;
    }

    /**
     * Only bind if it's not bound already
     */
    public function bindLocked(
        string $type,
        string|object|null $target = null,
        callable $callback = null
    ): Binding {
        // Return old binding if exists
        if (isset($this->bindings[$type])) {
            return new Binding($this, $type, $target);
        }

        // Create binding
        $binding = $this->bind($type, $target);

        // Trigger setup callback
        if ($callback) {
            $callback($binding, $this);
        }

        return $binding;
    }

    /**
     * Add binding as part of a group
     */
    public function bindToGroup(
        string $type,
        string|object|null ...$targets
    ): Binding {
        if (isset($this->bindings[$type])) {
            // Get current binding
            $group = $this->bindings[$type];

            if (!$group instanceof Group) {
                // Merge old binding to group
                $oldBinding = $group;
                $group = new Group($this, $type);
                $group->addBinding($oldBinding);
                $this->remove($type);
            }
        } else {
            // Setup new group
            $group = new Group($this, $type);
        }


        // Add targets to group
        foreach ($targets as $target) {
            $binding = new Binding($this, $type, $target);
            $group->addBinding($binding);
        }

        // Add group binding
        $this->bindings[$type] = $group;

        // Remove provider reference
        unset($this->providers[$type]);

        return $group;
    }

    /**
     * Bind a single instance concrete type
     */
    public function bindShared(
        string $type,
        string|object|null $target = null
    ): Binding {
        return $this->bind($type, $target)->setShared(true);
    }

    /**
     * Bind single instance only if it's not bound already
     */
    public function bindSharedLocked(
        string $type,
        string|object|null $target = null,
        callable $callback = null
    ): Binding {
        // Return current binding if exists
        if (isset($this->bindings[$type])) {
            return $this->bindings[$type];
        }

        // Create binding
        $binding = $this->bindShared($type, $target);

        // Trigger setup callback
        if ($callback) {
            $callback($binding, $this);
        }

        return $binding;
    }

    /**
     * Add singleton binding as group
     */
    public function bindSharedToGroup(
        string $type,
        string|object|null $target = null
    ): Binding {
        return $this->bindToGroup($type, $target)->setShared(true);
    }

    /**
     * Set an alias for an existing binding
     *
     * @return $this
     */
    public function alias(
        string $type,
        string $alias
    ): static {
        $this->getBinding($type)->alias($alias);
        return $this;
    }

    /**
     * Retrieve the alias from binding if it exists
     */
    public function getAlias(string $type): ?string
    {
        // Return existing binding type alias
        if (isset($this->bindings[$type])) {
            return $this->bindings[$type]->getAlias();
        }

        // Lookup alias reference
        if (false !== ($key = array_search($type, $this->aliases))) {
            return (string)$key;
        }

        return null;
    }

    /**
     * Has an alias been used?
     */
    public function hasAlias(string $alias): bool
    {
        return isset($this->aliases[$alias]);
    }

    /**
     * Has this bound type been aliased?
     */
    public function isAliased(string $type): bool
    {
        return in_array($type, $this->aliases);
    }

    /**
     * Lookup alias
     */
    public function getAliasedType(string $alias): ?string
    {
        return $this->aliases[$alias] ?? null;
    }

    /**
     * Quietly add $alias to the reference list
     */
    public function registerAlias(
        string $type,
        string $alias
    ): void {
        $this->aliases[$alias] = $type;
    }

    /**
     * Quietly remove $alias from the reference list
     */
    public function unregisterAlias(string $alias): void
    {
        unset($this->aliases[$alias]);
    }

    /**
     * Build or retrieve an instance
     */
    public function get(string $type): object
    {
        return $this->getBinding($type)
            ->getInstance();
    }

    /**
     * Build or retrieve an instance using params
     *
     * @param array<string, mixed> $params
     */
    public function getWith(
        string $type,
        array $params = []
    ): object {
        return $this->getBinding($type)
            ->addParams($params)
            ->getInstance();
    }

    /**
     * Return array of bound instances
     *
     * @return array<object>
     */
    public function getGroup(string $type): array
    {
        return $this->getBinding($type)
            ->getGroupInstances();
    }

    /**
     * Loop through all group instances and call callback
     *
     * @return $this
     */
    public function each(
        string $type,
        callable $callback
    ): static {
        foreach ($this->getGroup($type) as $instance) {
            $callback($instance, $this);
        }

        return $this;
    }

    /**
     * Is this type or alias bound?
     */
    public function has(string $type): bool
    {
        return
            isset($this->bindings[$type]) ||
            isset($this->aliases[$type]) ||
            isset($this->providers[$type]);
    }

    /**
     * Remove a current binding
     *
     * @return $this
     */
    public function remove(string $type): static
    {
        // Remove provider reference
        unset($this->providers[$type]);

        // Skip if no binding
        if (!isset($this->bindings[$type])) {
            return $this;
        }


        $binding = $this->bindings[$type];

        // Remove alias
        if (null !== ($alias = $binding->getAlias())) {
            unset($this->aliases[$alias]);
        }

        // Remove binding
        unset($this->bindings[$type]);
        return $this;
    }

    /**
     * Look up existing binding
     */
    public function getBinding(string $type): Binding
    {
        if ($binding = $this->lookupBinding($type)) {
            return $binding;
        }

        throw Exceptional::{'NotFound,' . NotFoundException::class}(
            $type . ' has not been bound'
        );
    }

    /**
     * Look up binding without throwing an error
     */
    protected function lookupBinding(string $type): ?Binding
    {
        // Lookup alias
        if (isset($this->aliases[$type])) {
            $type = $this->aliases[$type];
        }

        // Lookup binding
        if (isset($this->bindings[$type])) {
            return $this->bindings[$type];
        }

        // Lookup provider
        if (isset($this->providers[$type])) {
            $this->providers[$type]->registerServices($this);

            // Remove provider references
            foreach ($this->providers[$type]->getProvidedServices() as $providedType) {
                unset($this->providers[$providedType]);
            }

            // Lookup binding
            if (isset($this->bindings[$type])) {
                return $this->bindings[$type];
            }
        }

        return null;
    }

    /**
     * Get all binding objects
     *
     * @return array<string, Binding>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Add a preparator to binding
     *
     * @return $this
     */
    public function prepareWith(
        string $type,
        callable $callback
    ): static {
        $this->getBinding($type)->prepareWith($callback);
        return $this;
    }

    /**
     * Add a single injection parameter
     *
     * @return $this
     */
    public function inject(
        string $type,
        string $name,
        mixed $value
    ): static {
        $this->getBinding($type)->inject($name, $value);
        return $this;
    }

    /**
     * Add an array of injection parameters
     *
     * @param array<string, mixed> $params
     * @return $this
     */
    public function addParams(
        string $type,
        array $params
    ): static {
        $this->getBinding($type)->addParams($params);
        return $this;
    }

    /**
     * Clear injected params from binding
     *
     * @return $this
     */
    public function clearParams(string $type): static
    {
        $this->getBinding($type)->clearParams();
        return $this;
    }

    /**
     * Clear injected params from all bindings
     *
     * @return $this
     */
    public function clearAllParams(): static
    {
        foreach ($this->bindings as $binding) {
            $binding->clearParams();
        }

        return $this;
    }

    /**
     * Reset everything
     *
     * @return $this
     */
    public function clear(): static
    {
        $this->bindings = [];
        $this->aliases = [];
        $this->events->clear();

        return $this;
    }

    /**
     * Create a new instanceof $type
     *
     * @phpstan-template T of object
     * @phpstan-param class-string<T> $type
     * @param array<string, mixed> $params
     * @phpstan-param class-string ...$interfaces
     * @phpstan-return T
     */
    public function newInstanceOf(
        string $type,
        array $params = [],
        string ...$interfaces
    ): object {
        // Lookup / create binding
        if (!$binding = $this->lookupBinding($type)) {
            $binding = new Binding($this, $type, $type, false);
        }

        // Add params
        $binding->addParams($params);

        /**
         * Generate instance
         * @var T
         */
        $output = $binding->getInstance();

        // Test interfaces
        $this->testInterfaces($output, ...$interfaces);

        return $output;
    }

    /**
     * Create new instance of type, no looking up binding
     *
     * @phpstan-template T of object
     * @phpstan-param class-string<T> $type
     * @param array<string, mixed> $params
     * @phpstan-param class-string ...$interfaces
     * @phpstan-return T
     */
    public function buildInstanceOf(
        string $type,
        array $params = [],
        string ...$interfaces
    ): object {
        // Create reflection
        $reflector = new ReflectionClass($type);

        // Check instantiable
        if (!$reflector->isInstantiable()) {
            throw Exceptional::{'Logic,' . ContainerException::class}(
                'Binding target ' . $type . ' cannot be instantiated'
            );
        }

        // Shortcut if no constructor
        if (!$constructor = $reflector->getConstructor()) {
            return $reflector->newInstance();
        }

        // Prepare params with reflectors
        $paramReflectors = $constructor->getParameters();
        $args = $this->prepareArgs($paramReflectors, $params);

        // Create instance
        $output = $reflector->newInstanceArgs($args);

        // Test interfaces
        $this->testInterfaces($output, ...$interfaces);

        return $output;
    }

    /**
     * Test object for interfaces
     *
     * @phpstan-param class-string ...$interfaces
     */
    protected function testInterfaces(
        object $object,
        string ...$interfaces
    ): void {
        foreach ($interfaces as $interface) {
            if (!$object instanceof $interface) {
                throw Exceptional::Implementation(
                    'Binding target does not implement ' . $interface
                );
            }
        }
    }

    /**
     * Call any function with injected params
     *
     * @param array<string, mixed> $params
     * @return mixed
     */
    public function call(
        callable $function,
        array $params = []
    ): mixed {
        if (is_array($function)) {
            // Reflect array callable
            $classRef = new ReflectionObject($function[0]);
            $reflector = $classRef->getMethod($function[1]);
        } elseif (
            $function instanceof \Closure ||
            is_string($function)
        ) {
            // Reflect closure / reference
            $reflector = new ReflectionFunction($function);
        } else {
            throw Exceptional::InvalidArgument(
                'Unable to reflect callback',
                null,
                $function
            );
        }

        // Prepare params with reflectors
        $paramReflectors = $reflector->getParameters();
        $args = $this->prepareArgs($paramReflectors, $params);

        // Call function
        return call_user_func_array($function, $args);
    }

    /**
     * Get params for function
     *
     * @param array<ReflectionParameter> $paramReflectors
     * @param array<string, mixed> $params
     * @return array<mixed>
     */
    protected function prepareArgs(
        array $paramReflectors,
        array $params
    ): array {
        $args = [];

        foreach ($paramReflectors as $reflector) {
            if (array_key_exists($reflector->name, $params)) {
                // Get param by name
                $args[] = $params[$reflector->name];
            } elseif (
                null !== ($type = $reflector->getType()) &&
                $type instanceof ReflectionNamedType &&
                !$type->isBuiltin()
            ) {
                // Instantiate type from container
                try {
                    $args[] = $this->get($type->getName());
                } catch (NotFoundException $e) {
                    if ($reflector->isOptional()) {
                        $args[] = $reflector->getDefaultValue();
                    } else {
                        throw $e;
                    }
                }
            } elseif ($reflector->isDefaultValueAvailable()) {
                // Use default value
                $args[] = $reflector->getDefaultValue();
            } else {
                throw Exceptional::{'Logic,' . ContainerException::class}(
                    'Binding param $' . $reflector->name . ' cannot be resolved'
                );
            }
        }

        return $args;
    }

    /**
     * Force a binding to forget its shared instance
     */
    public function forgetInstance(string $type): Binding
    {
        $binding = $this->getBinding($type);
        $binding->forgetInstance();
        return $binding;
    }

    /**
     * Force all bindings to forget shared instances
     *
     * @return $this
     */
    public function forgetAllInstances(): static
    {
        foreach ($this->bindings as $binding) {
            $binding->forgetInstance();
        }

        return $this;
    }

    /**
     * Add an event handler for when instances are created
     *
     * @return $this
     */
    public function afterResolving(
        string $type,
        callable $callback
    ): static {
        $this->events->after('resolving.' . $type, $callback);
        return $this;
    }

    /**
     * Trigger events on building a new instance
     */
    public function triggerAfterResolving(
        Binding $binding,
        object $instance
    ): void {
        $type = $binding->getType();

        $this->events->withAfter(['resolving.' . $type, 'resolving.*'], function ($events) use ($type, $instance) {
            $events->triggerAfter('resolving.' . $type, $instance, $this);
            $events->triggerAfter('resolving.*', $instance, $this);
        });
    }

    /**
     * Add an event handler for after rebinding
     *
     * @return $this
     */
    public function afterRebinding(
        string $type,
        callable $callback
    ): static {
        $this->events->after('rebinding.' . $type, $callback);
        return $this;
    }

    /**
     * Trigger rebinding events
     */
    public function triggerAfterRebinding(Binding $binding): void
    {
        $type = $binding->getType();

        $this->events->withAfter(['rebinding.' . $type, 'rebinding.*'], function ($events) use ($type, $binding) {
            $instance = $binding->getInstance();

            $events->triggerAfter('rebinding.' . $type, $instance, $this);
            $events->triggerAfter('rebinding.*', $instance, $this);
        });
    }

    /**
     * Alias getBinding()
     */
    public function __get(string $type): Binding
    {
        return $this->getBinding($type);
    }

    /**
     * Alias bind()
     */
    public function __set(
        string $type,
        string|object|null $target
    ): void {
        $this->bind($type, $target);
    }

    /**
     * Alias has()
     */
    public function __isset(string $type): bool
    {
        return $this->has($type);
    }

    /**
     * Alias remove()
     */
    public function __unset(string $type): void
    {
        $this->remove($type);
    }

    /**
     * Alias get()
     *
     * @param string $type
     */
    public function offsetGet(mixed $type): object
    {
        return $this->get($type);
    }

    /**
     * Alias bind()
     *
     * @param string $type
     * @param string|Closure|object|null $target
     */
    public function offsetSet(
        mixed $type,
        mixed $target
    ): void {
        $this->bind($type, $target);
    }

    /**
     * Alias has()
     *
     * @param string $type
     */
    public function offsetExists(mixed $type): bool
    {
        return $this->has($type);
    }

    /**
     * Alias remove()
     *
     * @param string $type
     */
    public function offsetUnset(mixed $type): void
    {
        $this->remove($type);
    }

    /**
     * Normalize for dump
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        $output = [];

        foreach ($this->bindings as $binding) {
            $alias = $binding->getAlias() ?? $binding->getType();
            $output[$alias] = $binding->describeInstance();
        }

        foreach ($this->providers as $type => $provider) {
            $alias = $this->autoAlias($type) ?? $type;
            $output[$alias] = 'provider : ' . get_class($provider);
        }

        return $output;
    }
}
