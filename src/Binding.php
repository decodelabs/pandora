<?php

/**
 * @package Pandora
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Pandora;

use Closure;
use DecodeLabs\Exceptional;
use DecodeLabs\Kingdom\EagreService;
use DecodeLabs\Kingdom\PureService;
use DecodeLabs\Kingdom\Service;
use DecodeLabs\Monarch;
use DecodeLabs\Slingshot;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\NotFoundExceptionInterface as NotFoundException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionProperty;

/**
 * @template T of object
 */
class Binding
{
    /**
     * @var class-string<T>
     */
    public protected(set) string $type;

    /**
     * @var ?Closure():T
     */
    public ?Closure $factory = null;

    /**
     * @var ?class-string<T>
     */
    public protected(set) ?string $target {
        /**
         * @param class-string<T>|Closure():T|T|null $target
         */
        set(string|object|null $target) {
            if ($target === null) {
                $this->target = null;
                return;
            }

            if ($target instanceof Closure) {
                $this->factory = $target;
                $this->target = null;
                return;
            }

            if (is_object($target)) {
                $this->factory = null;
                $this->instance = $target;
                $this->target = null;
                return;
            }

            if (
                class_exists($target) ||
                interface_exists($target)
            ) {
                $this->target = $target;
                return;
            }

            throw Exceptional::InvalidArgument(
                message: 'Binding target for ' . $this->type . ' cannot be converted to a factory',
                interfaces: [NotFoundException::class]
            );
        }
    }

    /**
     * @var ?T
     */
    public ?object $instance = null {
        get {
            if (isset($this->instance)) {
                return $this->instance;
            }

            $target = $this->target ?? $this->type;

            if ($this->factory) {
                if (new ReflectionFunction($this->factory)->getNumberOfParameters() === 0) {
                    $output = ($this->factory)();
                } else {
                    $output = new Slingshot($this->container, $this->params)->invoke($this->factory);
                }
            } elseif (
                $target !== $this->type &&
                $this->container->has($target) &&
                // @phpstan-ignore-next-line
                ($binding = $this->container->getBinding($target)) &&
                $binding !== $this
            ) {
                $output = $binding->instance;
            } elseif (is_a($target, PureService::class, true)) {
                $output = $target::providePureService();
            } elseif (is_a($target, Service::class, true)) {
                $output = $target::provideService($this->container);
            } else {
                $ref = new ReflectionClass($target);
                $params = $this->params;

                $output = $ref->newLazyProxy(
                    fn () => new Slingshot($this->container, $params)->newInstance($target)
                );

                if (is_a($target, EagreService::class, true)) {
                    $ref->initializeLazyObject($output);
                }
            }

            $this->target = null;
            $this->factory = null;

            if ($output !== null) {
                $output = $this->prepareInstance($output);
            }

            return $this->instance = $output;
        }
        set(object|null $instance) {
            if ($instance === null) {
                $this->instance = null;
                return;
            }

            $this->target = null;
            $this->factory = null;
            $this->instance = $this->prepareInstance($instance);
        }
    }


    /**
     * @var array<int,Closure(T,Container):mixed>
     */
    public protected(set) array $preparators = [];

    /**
     * @var array<string,mixed>
     */
    final public protected(set) array $params = [];

    public protected(set) Container $container;


    /**
     * @param class-string<T> $type
     * @param class-string<T>|Closure():T|T|null $target
     */
    public function __construct(
        Container $container,
        string $type,
        string|object|null $target,
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
        $this->target = $target ?? $type;
    }



    /**
     * @param Closure(T,Container):mixed $callback
     */
    public function prepareWith(
        Closure $callback
    ): void {
        $this->preparators[spl_object_id($callback)] = $callback;
    }


    public function hasPreparators(): bool
    {
        return !empty($this->preparators);
    }

    public function clearPreparators(): void
    {
        $this->preparators = [];
    }


    public function inject(
        string $name,
        mixed $value
    ): void {
        $this->params[$name] = $value;
    }

    public function getParam(
        string $name
    ): mixed {
        return $this->params[$name] ?? null;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function addParams(
        array $params
    ): void {
        foreach ($params as $key => $value) {
            $this->inject($key, $value);
        }
    }

    public function hasParam(
        string $name
    ): bool {
        return array_key_exists($name, $this->params);
    }

    public function removeParam(
        string $name
    ): void {
        unset($this->params[$name]);
    }

    public function clearParams(): void
    {
        $this->params = [];
    }




    public function hasInstance(): bool
    {
        return new ReflectionProperty($this, 'instance')->getRawValue($this) !== null;
    }

    /**
     * @return array<object>
     */
    public function getGroupInstances(): array
    {
        $output = $this->instance;
        return $output === null ? [] : [$output];
    }

    public function describeInstance(): string
    {
        $output = '';

        if ($this->hasInstance()) {
            /** @var T $instance */
            $instance = $this->instance;

            if ($instance instanceof Service) {
                $output .= 'service';
            } else {
                $output .= 'instance';
            }

            $output .= ' : ' . get_class($instance);
        } elseif (isset($this->factory)) {
            $ref = new ReflectionFunction($this->factory);
            $path = (string)$ref->getFileName();

            if (class_exists(Monarch::class)) {
                $path = Monarch::getPaths()->prettify($path);
                /** @var string $path */
            } else {
                $path = basename($path);
            }

            $output .= 'factory @ ' . $path . ' : ' . $ref->getStartLine();
        } elseif (is_string($this->target)) {
            if (is_a($this->target, Service::class, true)) {
                $output .= 'service ';
            }

            $output .= 'type : ' . $this->target;
        } else {
            $output .= 'null';
        }

        return $output;
    }

    /**
     * @return array<string>
     */
    public function describeInstances(): array
    {
        return [$this->describeInstance()];
    }


    /**
     * @param T $instance
     * @return T
     */
    protected function prepareInstance(
        object $instance
    ): object {
        foreach ($this->preparators as $callback) {
            $origInstance = $instance;
            $instance = $callback($instance, $this->container);

            if (!$instance instanceof $this->type) {
                $instance = $origInstance;
            }
        }

        return $instance;
    }
}
