<?php

/**
 * @package Pandora
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Pandora;

use Closure;

use DecodeLabs\Exceptional;

class Group extends Binding
{
    /**
     * @var array<Binding>
     */
    protected array $bindings = [];


    /**
     * Init with Container and type
     */
    public function __construct(
        Container $container,
        string $type
    ) {
        parent::__construct($container, $type, null, false, true);
        unset($this->params, $this->target);
    }



    /**
     * Noop
     */
    public function setTarget(
        string|object|null $target
    ): static {
        throw Exceptional::Implementation(
            'setTarget is not used for groups'
        );
    }

    /**
     * Noop
     */
    public function getTarget(): string|object|null
    {
        throw Exceptional::Implementation(
            'getTarget is not used for groups'
        );
    }

    /**
     * Noop
     */
    public function setFactory(Closure $factory): static
    {
        throw Exceptional::Implementation(
            'setFactory is not used for groups'
        );
    }


    /**
     * Generate a looper factory
     */
    public function getFactory(): ?Closure
    {
        return function (): array {
            $output = [];

            foreach ($this->bindings as $binding) {
                $output[] = $binding->getInstance();
            }

            return $output;
        };
    }



    /**
     * Add a binding to the list
     *
     * @return $this
     */
    public function addBinding(Binding $binding): static
    {
        $this->bindings[] = $binding;
        return $this;
    }

    /**
     * Get list of bindings
     *
     * @return array<Binding>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }


    /**
     * Are there any registered preparator callbacks?
     */
    public function hasPreparators(): bool
    {
        if (parent::hasPreparators()) {
            return true;
        }

        foreach ($this->bindings as $binding) {
            if ($binding->hasPreparators()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove all preparators
     *
     * @return $this
     */
    public function clearPreparators(): static
    {
        $this->preparators = [];

        foreach ($this->bindings as $binding) {
            $binding->clearPreparators();
        }

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
        foreach ($this->bindings as $binding) {
            $binding->inject($name, $value);
        }

        return $this;
    }

    /**
     * Look up an injected param
     */
    public function getParam(string $name): mixed
    {
        foreach ($this->bindings as $binding) {
            if ($binding->hasParam($name)) {
                return $binding->getParam($name);
            }
        }

        return null;
    }

    /**
     * Add a list of injected params
     *
     * @param array<string, mixed> $params
     * @return $this
     */
    public function addParams(array $params): static
    {
        foreach ($this->bindings as $binding) {
            foreach ($params as $key => $value) {
                $binding->inject($key, $value);
            }
        }

        return $this;
    }

    /**
     * Has a specific parameter been injected?
     */
    public function hasParam(string $name): bool
    {
        foreach ($this->bindings as $binding) {
            if ($binding->hasParam($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get rid of an injected param
     *
     * @return $this
     */
    public function removeParam(string $name): static
    {
        foreach ($this->bindings as $binding) {
            $binding->removeParam($name);
        }

        return $this;
    }

    /**
     * Get rid of all injected params
     *
     * @return $this
     */
    public function clearParams(): static
    {
        foreach ($this->bindings as $binding) {
            $binding->clearParams();
        }

        return $this;
    }



    /**
     * Noop
     */
    public function setInstance(object $instance): static
    {
        throw Exceptional::Implementation(
            'setFactory is not used for groups'
        );
    }

    /**
     * Get rid of current shared instance
     *
     * @return $this
     */
    public function forgetInstance(): static
    {
        foreach ($this->bindings as $binding) {
            $binding->forgetInstance();
        }

        return $this;
    }

    /**
     * Build new or return current instance
     */
    public function getInstance(): object
    {
        foreach ($this->bindings as $binding) {
            return $binding->getInstance();
        }

        throw Exceptional::Runtime(
            'No available bindings'
        );
    }

    /**
     * Create a new instance
     */
    public function newInstance(): object
    {
        foreach ($this->bindings as $binding) {
            return $binding->newInstance();
        }

        throw Exceptional::Runtime(
            'No available bindings'
        );
    }

    /**
     * Wrap instance in array
     *
     * @return array<object>
     */
    public function getGroupInstances(): array
    {
        $output = [];

        foreach ($this->bindings as $binding) {
            $output[] = $binding->getInstance();
        }

        return $output;
    }

    /**
     * Create a simple text representation of instance or factory
     */
    public function describeInstance(): string
    {
        return implode("\n", $this->describeInstances());
    }

    /**
     * Description list of instances for group
     *
     * @return array<string>
     */
    public function describeInstances(): array
    {
        $output = [];

        foreach ($this->bindings as $binding) {
            $output[] = $binding->describeInstance();
        }

        return $output;
    }
}
