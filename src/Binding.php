<?php

/**
 * @package Pandora
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Pandora;

use Closure;
use DecodeLabs\Exceptional;
use DecodeLabs\Glitch\Proxy as Glitch;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\NotFoundExceptionInterface as NotFoundException;
use ReflectionFunction;

class Binding
{
    /**
     * @var class-string
     */
    protected(set) string $type;
    protected(set) ?string $alias = null;

    protected(set) ?Closure $factory = null {
        get => $this->factory;
        set {
            $oldFactory = $this->factory;
            $this->factory = $value;

            if ($oldFactory !== null) {
                $this->container->triggerAfterRebinding($this);
            }
        }
    }

    public bool $shared = false;

    protected string|object|null $target;
    protected ?object $instance = null;

    /**
     * @var array<string,Closure>
     */
    protected array $preparators = [];

    /**
     * @var array<string,mixed>
     */
    protected array $params = [];

    protected(set) Container $container;


    /**
     * Create new instance referencing base container
     */
    public function __construct(
        Container $container,
        string $type,
        string|object|null $target,
        bool $autoAlias = true,
        bool $ignoreTarget = false
    ) {
        $this->container = $container;

        if (
            !interface_exists($type) &&
            !class_exists($type)
        ) {
            throw Exceptional::InvalidArgument(
                message: 'Binding type must be a valid interface'
            );
        }

        $this->type = $type;

        if (!$ignoreTarget) {
            $this->setTarget($target);
        }

        if (
            $autoAlias &&
            null !== ($alias = $this->container->autoAlias($this->type))
        ) {
            $this->alias($alias);
        }
    }



    /**
     * Prepare factory or instance
     */
    public function setTarget(
        string|object|null $target
    ): static {
        // Use current type for null target
        if ($target === null) {
            $target = $this->type;
        }

        // Set target
        $this->target = $target;

        if (!$target instanceof Closure) {
            if (is_object($target)) {
                // Get object class, use instance
                $this->setInstance($target);
                $target = get_class($target);
            }

            if (
                class_exists($target) ||
                interface_exists($target)
            ) {
                if ($target !== $this->type) {
                    $this->container->registerAlias($this->type, $target);
                }

                // Build instance with type string
                $target = function () use ($target) {
                    if (
                        $target !== $this->type &&
                        $this->container->has($target)
                    ) {
                        $binding = $this->container->getBinding($target);

                        if ($binding !== $this) {
                            return $binding->getInstance();
                        }
                    }

                    return $this->container->buildInstanceOf($target, $this->params);
                };
            } else {
                throw Exceptional::InvalidArgument(
                    message: 'Binding target for ' . $this->type . ' cannot be converted to a factory',
                    interfaces: [NotFoundException::class]
                );
            }
        }

        $this->factory = $target;
        return $this;
    }

    /**
     * Get originally bound target
     */
    public function getTarget(): string|object|null
    {
        if ($this->instance) {
            return $this->instance;
        }

        return $this->target;
    }



    /**
     * Get target type
     *
     * @return class-string
     */
    public function getTargetType(): ?string
    {
        if (null === ($target = $this->getTarget())) {
            return null;
        }

        if (is_string($target)) {
            /** @var class-string $target */
            return $target;
        }

        return get_class($target);
    }



    /**
     * Set an alias for the binding
     *
     * @return $this
     */
    public function alias(
        string $alias
    ): static {
        // Check for backslashes
        if (false !== strpos($alias, '\\')) {
            throw Exceptional::InvalidArgument(
                message: 'Aliases must not contain \\ character',
                data: $alias,
                interfaces: [ContainerException::class]
            );
        }

        // Skip if same as current
        if ($alias === $this->alias) {
            return $this;
        }

        // Check alias not used elsewhere
        if (
            $this->container->hasAlias($alias) &&
            $this->container->getAliasedType($alias) !== $this->type
        ) {
            throw Exceptional::Logic(
                message: 'Alias "' . $alias . '" has already been bound',
                interfaces: [ContainerException::class]
            );
        }

        // Clear current
        if ($this->alias !== null) {
            $this->container->unregisterAlias($this->alias);
        }

        // Register new
        $this->alias = $alias;
        $this->container->registerAlias($this->type, $alias);

        return $this;
    }

    /**
     * Has an alias been set?
     */
    public function hasAlias(): bool
    {
        return $this->alias !== null;
    }

    /**
     * Unregister the alias with the container
     *
     * @return $this
     */
    public function removeAlias(): static
    {
        if ($this->alias !== null) {
            $this->container->unregisterAlias($this->alias);
        }

        $this->alias = null;
        return $this;
    }



    /**
     * Add a preparator callback
     *
     * @return $this
     */
    public function prepareWith(
        callable $callback
    ): static {
        if (
            is_array($callback) &&
            is_object($callback[0])
        ) {
            $id = spl_object_id($callback[0]);
        } elseif ($callback instanceof Closure) {
            $id = spl_object_id($callback);
        } elseif (is_string($callback)) {
            $id = $callback;
        } else {
            throw Exceptional::InvalidArgument(
                message: 'Unable to hash callback',
                data: $callback
            );
        }

        $this->preparators[(string)$id] = Closure::fromCallable($callback);
        return $this;
    }

    /**
     * Are there any registered preparator callbacks?
     */
    public function hasPreparators(): bool
    {
        return !empty($this->preparators);
    }

    /**
     * Remove all preparators
     *
     * @return $this
     */
    public function clearPreparators(): static
    {
        $this->preparators = [];
        return $this;
    }


    /**
     * Add an injected call parameter
     *
     * @return $this
     */
    public function inject(
        string $name,
        mixed $value
    ): static {
        $this->params[$name] = $value;
        return $this;
    }

    /**
     * Get provided injected parameter
     */
    public function getParam(
        string $name
    ): mixed {
        return $this->params[$name] ?? null;
    }

    /**
     * Add a list of injected params
     *
     * @param array<string, mixed> $params
     * @return $this
     */
    public function addParams(
        array $params
    ): static {
        foreach ($params as $key => $value) {
            $this->inject($key, $value);
        }

        return $this;
    }

    /**
     * Has a specific parameter been injected?
     */
    public function hasParam(
        string $name
    ): bool {
        return array_key_exists($name, $this->params);
    }

    /**
     * Get rid of an injected param
     *
     * @return $this
     */
    public function removeParam(
        string $name
    ): static {
        unset($this->params[$name]);
        return $this;
    }

    /**
     * Get rid of all injected params
     *
     * @return $this
     */
    public function clearParams(): static
    {
        $this->params = [];
        return $this;
    }


    /**
     * Manually set a shared instance
     *
     * @return $this
     */
    public function setInstance(
        object $instance
    ): static {
        $this->target = null;
        $this->instance = $this->prepareInstance($instance);
        return $this;
    }

    /**
     * Get rid of current shared instance
     *
     * @return $this
     */
    public function forgetInstance(): static
    {
        $this->instance = null;
        return $this;
    }

    /**
     * Build new or return current instance
     */
    public function getInstance(): ?object
    {
        if ($this->instance) {
            $output = $this->instance;
        } else {
            $output = $this->newInstance();

            if ($this->shared) {
                $this->instance = $output;
            }
        }

        return $output;
    }

    /**
     * Does this binding have a concrete instance?
     */
    public function hasInstance(): bool
    {
        return $this->instance !== null;
    }

    /**
     * Create a new instance
     */
    public function newInstance(): ?object
    {
        if ($this->factory === null) {
            return null;
        }

        /** @var ?object $output */
        $output = $this->container->call($this->factory, $this->params);

        if ($output === null) {
            return $output;
        }

        return $this->prepareInstance($output);
    }

    /**
     * Wrap instance in array
     *
     * @return array<object>
     */
    public function getGroupInstances(): array
    {
        $output = $this->getInstance();
        return $output === null ? [] : [$output];
    }

    /**
     * Create a simple text representation of instance or factory
     */
    public function describeInstance(): string
    {
        $output = $this->shared ? '* ' : '';

        if (isset($this->instance)) {
            $output .= 'instance : ' . get_class($this->instance);
        } elseif (is_string($this->target)) {
            $output .= 'type : ' . $this->target;
        } elseif ($this->target instanceof Closure) {
            $ref = new ReflectionFunction($this->target);
            $output .= 'closure @ ' . Glitch::normalizePath((string)$ref->getFileName()) . ' : ' . $ref->getStartLine();
        } else {
            $output .= 'null';
        }

        return $output;
    }

    /**
     * Description list of instances for group
     *
     * @return array<string>
     */
    public function describeInstances(): array
    {
        return [$this->describeInstance()];
    }


    /**
     * Run instance through preparators
     */
    protected function prepareInstance(
        object $instance
    ): object {
        foreach ($this->preparators as $callback) {
            /** @var object $instance */
            $instance = $callback($instance, $this->container);
        }

        if (!$instance instanceof $this->type) {
            throw Exceptional::Logic(
                message: 'Binding instance does not implement type ' . $this->type,
                data: $instance,
                interfaces: [ContainerException::class]
            );
        }

        $this->container->triggerAfterResolving($this, $instance);
        return $instance;
    }


    /**
     * Add a resolver event handler
     *
     * @return $this
     */
    public function afterResolving(
        callable $callback
    ): static {
        $this->container->afterResolving($this->type, $callback);
        return $this;
    }

    /**
     * Add a rebind event handler
     *
     * @return $this
     */
    public function afterRebinding(
        callable $callback
    ): static {
        $this->container->afterRebinding($this->type, $callback);
        return $this;
    }
}
