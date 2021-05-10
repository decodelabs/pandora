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
     * @var string
     * @phpstan-var class-string
     */
    protected $type;

    /**
     * @var string|null
     */
    protected $alias;

    /**
     * @var string|Closure|object|null
     */
    protected $target;


    /**
     * @var Closure
     */
    protected $factory;

    /**
     * @var bool
     */
    protected $shared = false;

    /**
     * @var object|null
     */
    protected $instance;


    /**
     * @var array<string, callable>
     */
    protected $preparators = [];

    /**
     * @var array<string, mixed>
     */
    protected $params = [];


    /**
     * @var Container
     */
    protected $container;


    /**
     * Create new instance referencing base container
     *
     * @param string|Closure|object|null $target
     */
    public function __construct(
        Container $container,
        string $type,
        $target,
        bool $autoAlias = true,
        bool $ignoreTarget = false
    ) {
        $this->container = $container;

        if (
            !interface_exists($type) &&
            !class_exists($type)
        ) {
            throw Exceptional::InvalidArgument(
                'Binding type must be a valid interface'
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
     * Get referenced base container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get interface type
     *
     * @phpstan-return class-string
     */
    public function getType(): string
    {
        return $this->type;
    }


    /**
     * Prepare factory or instance
     *
     * @param mixed $target
     */
    public function setTarget($target): Binding
    {
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
                is_string($target) &&
                class_exists($target)
            ) {
                // Build instance with type string
                $target = function () use ($target) {
                    return $this->container->buildInstanceOf($target, $this->params);
                };
            } else {
                throw Exceptional::{'InvalidArgument,' . NotFoundException::class}(
                    'Binding target for ' . $this->type . ' cannot be converted to a factory'
                );
            }
        }

        return $this->setFactory($target);
    }

    /**
     * Get originally bound target
     *
     * @return string|Closure|object|null
     */
    public function getTarget()
    {
        if ($this->instance) {
            return $this->instance;
        }

        return $this->target;
    }


    /**
     * Set resolver factory closure
     *
     * @return $this
     */
    public function setFactory(Closure $factory): Binding
    {
        $oldFactory = $this->factory;
        $this->factory = $factory;

        if ($oldFactory !== null) {
            $this->container->triggerAfterRebinding($this);
        }

        return $this;
    }

    /**
     * Get resolver factory closure if set
     */
    public function getFactory(): ?Closure
    {
        return $this->factory;
    }


    /**
     * Set an alias for the binding
     *
     * @return $this
     */
    public function alias(string $alias): Binding
    {
        // Check for backslashes
        if (false !== strpos($alias, '\\')) {
            throw Exceptional::{'InvalidArgument,' . ContainerException::class}(
                'Aliases must not contain \\ character',
                null,
                $alias
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
            throw Exceptional::{'Logic,' . ContainerException::class}(
                'Alias "' . $alias . '" has already been bound'
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
     * Get alias if it's been set
     */
    public function getAlias(): ?string
    {
        return $this->alias;
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
    public function removeAlias(): Binding
    {
        if ($this->alias !== null) {
            $this->container->unregisterAlias($this->alias);
        }

        $this->alias = null;
        return $this;
    }


    /**
     * Is this item singleton?
     */
    public function isShared(): bool
    {
        return $this->shared;
    }

    /**
     * Make this item a singleton
     *
     * @return $this
     */
    public function setShared(bool $shared): Binding
    {
        $this->shared = $shared;
        return $this;
    }


    /**
     * Add a preparator callback
     *
     * @return $this
     */
    public function prepareWith(callable $callback): Binding
    {
        if (is_array($callback)) {
            $id = spl_object_id($callback[0]);
        } elseif ($callback instanceof Closure) {
            $id = spl_object_id($callback);
        } elseif (is_string($callback)) {
            $id = $callback;
        } else {
            throw Exceptional::InvalidArgument(
                'Unable to hash callback',
                null,
                $callback
            );
        }

        $this->preparators[(string)$id] = $callback;
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
    public function clearPreparators(): Binding
    {
        $this->preparators = [];
        return $this;
    }


    /**
     * Add an injected call parameter
     *
     * @param mixed $value
     * @return $this
     */
    public function inject(string $name, $value): Binding
    {
        $this->params[$name] = $value;
        return $this;
    }

    /**
     * Get provided injected parameter
     *
     * @return mixed
     */
    public function getParam(string $name)
    {
        return $this->params[$name] ?? null;
    }

    /**
     * Add a list of injected params
     *
     * @param array<string, mixed> $params
     * @return $this
     */
    public function addParams(array $params): Binding
    {
        foreach ($params as $key => $value) {
            $this->inject($key, $value);
        }

        return $this;
    }

    /**
     * Has a specific parameter been injected?
     */
    public function hasParam(string $name): bool
    {
        return array_key_exists($name, $this->params);
    }

    /**
     * Get rid of an injected param
     *
     * @return $this
     */
    public function removeParam(string $name): Binding
    {
        unset($this->params[$name]);
        return $this;
    }

    /**
     * Get rid of all injected params
     *
     * @return $this
     */
    public function clearParams(): Binding
    {
        $this->params = [];
        return $this;
    }


    /**
     * Manually set a shared instance
     *
     * @return $this
     */
    public function setInstance(object $instance): Binding
    {
        $this->target = null;
        $this->instance = $this->prepareInstance($instance);
        return $this;
    }

    /**
     * Get rid of current shared instance
     *
     * @return $this
     */
    public function forgetInstance(): Binding
    {
        $this->instance = null;
        return $this;
    }

    /**
     * Build new or return current instance
     */
    public function getInstance(): object
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
    public function newInstance(): object
    {
        $output = $this->container->call($this->factory, $this->params);
        return $this->prepareInstance($output);
    }

    /**
     * Wrap instance in array
     *
     * @return array<object>
     */
    public function getGroupInstances(): array
    {
        return [$this->getInstance()];
    }

    /**
     * Create a simple text representation of instance or factory
     */
    public function describeInstance(): string
    {
        $output = $this->isShared() ? '* ' : '';

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
    protected function prepareInstance(object $instance): object
    {
        foreach ($this->preparators as $callback) {
            /** @var object $instance */
            $instance = $callback($instance, $this->container);
        }

        if (!$instance instanceof $this->type) {
            throw Exceptional::{'Logic,' . ContainerException::class}(
                'Binding instance does not implement type ' . $this->type,
                null,
                $instance
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
    public function afterResolving(callable $callback): Binding
    {
        $this->container->afterResolving($this->type, $callback);
        return $this;
    }

    /**
     * Add a rebind event handler
     *
     * @return $this
     */
    public function afterRebinding(callable $callback): Binding
    {
        $this->container->afterRebinding($this->type, $callback);
        return $this;
    }
}
