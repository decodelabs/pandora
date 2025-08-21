<?php

/**
 * @package Pandora
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Pandora;

use Closure;
use DecodeLabs\Archetype;
use DecodeLabs\Exceptional;
use DecodeLabs\Kingdom\ContainerAdapter;
use DecodeLabs\Kingdom\Service;
use DecodeLabs\Slingshot;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface as NotFoundException;
use Throwable;

class Container implements
    ContainerInterface,
    ContainerAdapter
{
    /**
     * @var array<class-string,Binding<object>>
     */
    protected array $bindings = [];

    public protected(set) Archetype $archetype;

    public function __construct(
        ?Archetype $archetype = null
    ) {
        $this->archetype = $archetype ?? new Archetype();
        $this->bind(Archetype::class, $this->archetype);
    }


    /**
     * @template T of object
     * @param class-string<T> $type
     * @param class-string<T>|Closure():T|T|null $target
     * @return Binding<T>
     */
    public function bind(
        string $type,
        string|object|null $target = null
    ): Binding {
        // Create binding
        $binding = new Binding(
            container: $this,
            type: $type,
            target: $target
        );

        $type = $binding->type;

        // Add new binding
        $this->bindings[$type] = $binding;
        return $binding;
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @param class-string<T>|Closure():T|T|null $target
     * @return ?Binding<T>
     */
    public function tryBind(
        string $type,
        string|object|null $target = null
    ): ?Binding {
        if (isset($this->bindings[$type])) {
            return null;
        }

        return $this->bind($type, $target);
    }



    public function set(
        string $type,
        object $instance
    ): void {
        $this->bind($type, $instance);
    }

    public function setFactory(
        string $type,
        Closure $factory
    ): void {
        $this->bind($type, $factory);
    }

    public function setType(
        string $type,
        string $instanceType
    ): void {
        $this->bind($type, $instanceType);
    }



    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    public function get(
        string $type
    ): object {
        $output = $this->lookupBinding($type)?->instance;

        if (
            $output === null ||
            !is_a($output, $type)
        ) {
            throw Exceptional::Runtime(
                message: 'Type "' . $type . '" is not bound'
            );
        }

        return $output;
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return ?T
     */
    public function tryGet(
        string $type
    ): ?object {
        /** @var ?T */
        return $this->lookupBinding($type)?->instance;
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    public function getOrCreate(
        string $type
    ): object {
        $binding = $this->getBinding($type);

        if ($binding->hasInstance()) {
            /** @var T */
            return $binding->instance;
        }

        $binding->factory = fn () => new Slingshot($this, $binding->params)->newInstance($type);

        /** @var T */
        return $binding->instance;
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @param array<string,mixed> $params
     * @return T
     */
    public function getWith(
        string $type,
        array $params = []
    ): object {
        $binding = $this->getBinding($type);
        $binding->addParams($params);

        /** @var T */
        return $binding->instance;
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @param array<string,mixed> $params
     * @return T
     */
    public function tryGetWith(
        string $type,
        array $params = []
    ): ?object {
        $binding = $this->lookupBinding($type);
        $binding?->addParams($params);

        /** @var ?T */
        return $binding?->instance;
    }


    public function has(
        string $type
    ): bool {
        return isset($this->bindings[$type]);
    }


    public function remove(
        string $type
    ): void {
        unset($this->bindings[$type]);
    }


    /**
     * @template T of object
     * @param class-string<T> $type
     * @return Binding<T>
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
     * @template T of object
     * @param class-string<T> $type
     * @return ?Binding<T>
     */
    protected function lookupBinding(
        string $type
    ): ?Binding {
        // Lookup binding
        if (isset($this->bindings[$type])) {
            /** @var Binding<T> */
            return $this->bindings[$type];
        }

        // Containers
        if (
            $type === ContainerInterface::class ||
            is_subclass_of($type, ContainerInterface::class)
        ) {
            /** @var Binding<T> */
            return $this->bind($type, $this);
        }

        if ($class = $this->archetype->tryResolve($type)) {
            return $this->bind($type, $class);
        }

        return null;
    }

    /**
     * @return array<class-string,Binding<object>>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }


    /**
     * @template T of object
     * @param class-string<T> $type
     * @param Closure(T,Container):void $callback
     */
    public function prepare(
        string $type,
        Closure $callback
    ): void {
        $this->getBinding($type)->prepareWith($callback);
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     */
    public function inject(
        string $type,
        string $name,
        mixed $value
    ): void {
        $this->getBinding($type)->inject($name, $value);
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @param array<string,mixed> $params
     */
    public function addParams(
        string $type,
        array $params
    ): void {
        $this->getBinding($type)->addParams($params);
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     */
    public function clearParams(
        string $type
    ): void {
        $this->getBinding($type)->clearParams();
    }

    public function clearAllParams(): void
    {
        foreach ($this->bindings as $binding) {
            $binding->clearParams();
        }
    }



    public function clear(): void
    {
        $this->bindings = [];
    }


    public function getPsrContainer(): ContainerInterface
    {
        return $this;
    }

    /**
     * Normalize for dump
     *
     * @return array<string,string>
     */
    public function __debugInfo(): array
    {
        $output = [];

        foreach ($this->bindings as $binding) {
            $key = $binding->type;
            $output[$key] = $binding->describeInstance();
        }

        return $output;
    }
}
