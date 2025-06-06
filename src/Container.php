<?php

/**
 * @package Pandora
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Pandora;

use ArrayAccess;
use Closure;
use DecodeLabs\Archetype;
use DecodeLabs\Exceptional;
//use DecodeLabs\Reactor\Dispatcher as EventDispatcher;
use DecodeLabs\Pandora\Events as EventDispatcher;
use DecodeLabs\Slingshot;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface as NotFoundException;

use Throwable;

/**
 * @implements ArrayAccess<string, mixed>
 */
class Container implements
    ContainerInterface,
    ArrayAccess
{
    /**
     * @var array<string,mixed>
     */
    protected array $store = [];

    /**
     * @var array<string,Binding>
     */
    protected array $bindings = [];

    /**
     * @var array<class-string,Provider>
     */
    protected array $providers = [];

    /**
     * @var array<string,string>
     */
    protected array $aliases = [];

    /**
     * @var array<string,callable>
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
     * @param class-string<Provider> ...$providers
     */
    public function registerProviders(
        string ...$providers
    ): void {
        foreach ($providers as $provider) {
            $this->registerProvider($provider);
        }
    }

    /**
     * Instantiate provider and register
     *
     * @param class-string<Provider> $provider
     */
    public function registerProvider(
        string $provider
    ): void {
        if (!class_exists($provider)) {
            throw Exceptional::{'Implementation,NotFound'}(
                message: 'Service provider ' . $provider . ' could not be found'
            );
        }

        $provider = $this->newInstanceOf($provider);
        $this->registerProviderInstance($provider);
    }

    /**
     * Register provider instance
     */
    public function registerProviderInstance(
        Provider $provider
    ): void {
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
     * @return array<string,Provider>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }



    /**
     * Generate an automated alias for a type
     *
     * @param class-string $type
     */
    public function autoAlias(
        string $type
    ): ?string {
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
    public function unregisterAutoAliaser(
        string $name
    ): void {
        unset($this->autoAliasers[$name]);
    }


    /**
     * Set value in key-value store
     *
     * @return $this
     */
    public function store(
        string $key,
        mixed $value
    ): static {
        if (isset($this->bindings[$key])) {
            throw Exceptional::Runtime(
                message: 'Key "' . $key . '" is already registered as a bound type'
            );
        } elseif (isset($this->aliases[$key])) {
            throw Exceptional::Runtime(
                message: 'Key "' . $key . '" is already registered as a bound type alias'
            );
        }

        $this->store[$key] = $value;
        return $this;
    }


    /**
     * Bind a concrete type or instance to interface
     */
    public function bind(
        string $type,
        string|object|null $target = null
    ): Binding {
        if (isset($this->store[$type])) {
            throw Exceptional::Runtime(
                message: 'Type "' . $type . '" is already registered in the key-value store'
            );
        }

        // Create binding
        $binding = new Binding($this, $type, $target);
        $type = $binding->type;

        // Remove old binding
        if ($oldBinding = ($this->bindings[$type] ?? null)) {
            $this->remove($type);
        }

        // Remove type aliases and provider reference
        unset($this->aliases[$type]);
        unset($this->providers[$type]);

        // Add new binding
        $this->bindings[$type] = $binding;


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
        ?callable $callback = null
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
        $binding = $this->bind($type, $target);
        $binding->shared = true;
        return $binding;
    }

    /**
     * Bind single instance only if it's not bound already
     */
    public function bindSharedLocked(
        string $type,
        string|object|null $target = null,
        ?callable $callback = null
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
        $binding = $this->bindToGroup($type, $target);
        $binding->shared = true;
        return $binding;
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
    public function getAlias(
        string $type
    ): ?string {
        // Return existing binding type alias
        if (isset($this->bindings[$type])) {
            return $this->bindings[$type]->alias;
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
    public function hasAlias(
        string $alias
    ): bool {
        return isset($this->aliases[$alias]);
    }

    /**
     * Has this bound type been aliased?
     */
    public function isAliased(
        string $type
    ): bool {
        return in_array($type, $this->aliases);
    }

    /**
     * Lookup alias
     */
    public function getAliasedType(
        string $alias
    ): ?string {
        return $this->aliases[$alias] ?? null;
    }


    /**
     * Quietly add $alias to the reference list
     */
    public function registerAlias(
        string $type,
        string $alias
    ): void {
        if (isset($this->store[$alias])) {
            throw Exceptional::Runtime(
                message: 'Alias "' . $alias . '" is already registered in the key-value store'
            );
        }

        $this->aliases[$alias] = $type;
    }

    /**
     * Quietly remove $alias from the reference list
     */
    public function unregisterAlias(
        string $alias
    ): void {
        unset($this->aliases[$alias]);
    }



    /**
     * Build or retrieve an instance
     *
     * @template T of object
     * @param string|class-string<T> $type
     * @phpstan-return ($type is class-string<T> ? T : mixed)
     */
    public function get(
        string $type
    ): mixed {
        if (array_key_exists($type, $this->store)) {
            return $this->store[$type];
        }

        return $this->getBinding($type)
            ->getInstance();
    }

    /**
     * Build or retrieve an instance
     *
     * @template T of object
     * @param string|class-string<T> $type
     * @phpstan-return ($type is class-string<T> ? T|null : mixed)
     */
    public function tryGet(
        string $type
    ): mixed {
        if (array_key_exists($type, $this->store)) {
            return $this->store[$type];
        }

        return $this->lookupBinding($type)
            ?->getInstance();
    }

    /**
     * Get from value store
     */
    public function getFromStore(
        string $key
    ): mixed {
        return $this->store[$key] ?? null;
    }

    /**
     * Get values
     *
     * @return array<string, mixed>
     */
    public function getStore(): array
    {
        return $this->store;
    }

    /**
     * Build or retrieve an instance using params
     *
     * @template T of object
     * @param string|class-string<T> $type
     * @param array<string,mixed> $params
     * @phpstan-return ($type is class-string<T> ? T : mixed)
     */
    public function getWith(
        string $type,
        array $params = []
    ): mixed {
        return $this->getBinding($type)
            ->addParams($params)
            ->getInstance();
    }

    /**
     * Build or retrieve an instance using params
     *
     * @template T of object
     * @param string|class-string<T> $type
     * @param array<string,mixed> $params
     * @phpstan-return ($type is class-string<T> ? T|null : mixed)
     */
    public function tryGetWith(
        string $type,
        array $params = []
    ): mixed {
        return $this->lookupBinding($type)
            ?->addParams($params)
            ?->getInstance();
    }

    /**
     * Return array of bound instances
     *
     * @template T of object
     * @param string|class-string<T> $type
     * @return array<object>
     * @phpstan-return ($type is class-string<T> ? array<T> : array<object>)
     */
    public function getGroup(
        string $type
    ): array {
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
    public function has(
        string $type
    ): bool {
        return
            isset($this->store[$type]) ||
            isset($this->bindings[$type]) ||
            isset($this->aliases[$type]) ||
            isset($this->providers[$type]);
    }


    /**
     * Remove a current binding
     *
     * @return $this
     */
    public function remove(
        string $type
    ): static {
        // Remove provider reference
        unset(
            $this->store[$type],
            $this->providers[$type]
        );


        // Get binding
        if (isset($this->bindings[$type])) {
            $binding = $this->bindings[$type];
        } elseif (isset($this->aliases[$type])) {
            $binding = $this->bindings[$this->aliases[$type]];
        } else {
            return $this;
        }

        $type = $binding->type;


        // Remove alias
        if (null !== ($alias = $binding->alias)) {
            unset($this->aliases[$alias]);
        }

        if (null !== ($alias = $binding->getTargetType())) {
            unset($this->aliases[$alias]);
        }

        // Remove binding
        unset($this->bindings[$type]);
        return $this;
    }


    /**
     * Look up existing binding
     */
    public function getBinding(
        string $type
    ): Binding {
        if ($binding = $this->lookupBinding($type)) {
            return $binding;
        }

        throw Exceptional::NotFound(
            message: $type . ' has not been bound',
            interfaces: [NotFoundException::class]
        );
    }

    /**
     * Look up binding without throwing an error
     */
    protected function lookupBinding(
        string $type
    ): ?Binding {
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


        // Containers
        if (
            $type === ContainerInterface::class ||
            is_subclass_of($type, ContainerInterface::class)
        ) {
            return $this->bindShared($type, $this);
        }

        // Generate from Archetype
        // @phpstan-ignore-next-line
        if ($class = Archetype::tryResolve($type)) {
            return $this->bindShared($type, $class);
        }

        return null;
    }

    /**
     * Get all binding objects
     *
     * @return array<string,Binding>
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
     * @param array<string,mixed> $params
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
    public function clearParams(
        string $type
    ): static {
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
        $this->store = [];
        $this->bindings = [];
        $this->aliases = [];
        $this->events->clear();

        return $this;
    }



    /**
     * Create a new instanceof $type
     *
     * @template T of object
     * @param class-string<T> $type
     * @param array<string,mixed> $params
     * @param class-string ...$interfaces
     * @return T
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
     * @template T of object
     * @param class-string<T> $type
     * @param array<string,mixed> $params
     * @param class-string ...$interfaces
     * @return T
     */
    public function buildInstanceOf(
        string $type,
        array $params = [],
        string ...$interfaces
    ): object {
        try {
            $output = (new Slingshot($this, $params))->newInstance($type);
        } catch (Throwable $e) {
            throw Exceptional::Logic(
                message: 'Binding target ' . $type . ' cannot be instantiated',
                previous: $e,
                interfaces: [ContainerException::class]
            );
        }

        // Test interfaces
        $this->testInterfaces($output, ...$interfaces);

        return $output;
    }

    /**
     * Test object for interfaces
     *
     * @param class-string ...$interfaces
     */
    protected function testInterfaces(
        object $object,
        string ...$interfaces
    ): void {
        foreach ($interfaces as $interface) {
            if (!$object instanceof $interface) {
                throw Exceptional::Implementation(
                    message: 'Binding target does not implement ' . $interface
                );
            }
        }
    }

    /**
     * Call any function with injected params
     *
     * @param array<string,mixed> $params
     * @return mixed
     */
    public function call(
        callable $function,
        array $params = []
    ): mixed {
        return (new Slingshot($this, $params))->invoke(
            $function
        );
    }


    /**
     * Force a binding to forget its shared instance
     */
    public function forgetInstance(
        string $type
    ): Binding {
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
        $type = $binding->type;

        $this->events->withAfter(
            ['resolving.' . $type, 'resolving.*'],
            function (
                Events $events
            ) use ($type, $instance) {
                $events->triggerAfter('resolving.' . $type, $instance, $this);
                $events->triggerAfter('resolving.*', $instance, $this);
            }
        );
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
        $type = $binding->type;

        $this->events->withAfter(
            ['rebinding.' . $type, 'rebinding.*'],
            function (
                Events $events
            ) use ($type, $binding) {
                $instance = $binding->getInstance();

                $events->triggerAfter('rebinding.' . $type, $instance, $this);
                $events->triggerAfter('rebinding.*', $instance, $this);
            }
        );
    }




    /**
     * Alias getBinding()
     */
    public function __get(
        string $type
    ): Binding {
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
    public function __isset(
        string $type
    ): bool {
        return $this->has($type);
    }

    /**
     * Alias remove()
     */
    public function __unset(
        string $type
    ): void {
        $this->remove($type);
    }




    /**
     * Alias get()
     *
     * @template T of object
     * @param string|class-string<T> $type
     * @phpstan-return ($type is class-string<T> ? T : mixed)
     */
    public function offsetGet(
        mixed $type
    ): mixed {
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
    public function offsetExists(
        mixed $type
    ): bool {
        return $this->has($type);
    }

    /**
     * Alias remove()
     *
     * @param string $type
     */
    public function offsetUnset(
        mixed $type
    ): void {
        $this->remove($type);
    }



    /**
     * Normalize for dump
     *
     * @return array<string,string>
     */
    public function __debugInfo(): array
    {
        $output = [];

        foreach ($this->store as $key => $value) {
            $output[$key] = 'value : ' . gettype($value);
        }

        foreach ($this->bindings as $binding) {
            $key = $binding->type;

            if (null !== ($alias = $binding->alias)) {
                $key = $alias . ' : ' . $key;
            }

            $output[$key] = $binding->describeInstance();
        }

        foreach ($this->providers as $type => $provider) {
            $alias = $this->autoAlias($type) ?? $type;
            $output[$alias] = 'provider : ' . get_class($provider);
        }

        return $output;
    }
}
