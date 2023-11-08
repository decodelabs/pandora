<?php

/**
 * This file is part of the Decode Framework
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Pandora;

use DecodeLabs\Exceptional;

class Events
{
    /**
     * @var array<string, array<string, callable>>
     */
    protected array $events = [];

    /**
     * Register an event for before trigger
     *
     * @return $this
     */
    public function before(
        string $id,
        callable $callback
    ): static {
        $this->events['<' . $id][$this->hashCallable($callback)] = $callback;
        return $this;
    }

    /**
     * Register an event for after trigger
     *
     * @return $this
     */
    public function after(
        string $id,
        callable $callback
    ): static {
        $this->events['>' . $id][$this->hashCallable($callback)] = $callback;
        return $this;
    }

    /**
     * Extract id from callable
     */
    protected function hashCallable(
        callable $callback
    ): string {
        if (is_array($callback)) {
            return (string)spl_object_id($callback[0]);
        } elseif ($callback instanceof \Closure) {
            return (string)spl_object_id($callback);
        } elseif (is_string($callback)) {
            return $callback;
        } else {
            throw Exceptional::InvalidArgument(
                'Unable to hash callback',
                null,
                $callback
            );
        }
    }

    /**
     * Trigger before handlers
     *
     * @return $this
     */
    public function triggerBefore(
        string $id,
        mixed ...$args
    ): static {
        foreach ($this->events['<' . $id] ?? [] as $callback) {
            $callback(...$args);
        }

        return $this;
    }

    /**
     * Trigger after handlers
     *
     * @return $this
     */
    public function triggerAfter(
        string $id,
        mixed ...$args
    ): static {
        foreach ($this->events['>' . $id] ?? [] as $callback) {
            $callback(...$args);
        }

        return $this;
    }


    /**
     * Is this before event registered?
     */
    public function hasBefore(
        string ...$ids
    ): bool {
        foreach ($ids as $id) {
            if (isset($this->events['<' . $id])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this before event registered?
     */
    public function hasAfter(
        string ...$ids
    ): bool {
        foreach ($ids as $id) {
            if (isset($this->events['>' . $id])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this before event registered?
     */
    public function has(
        string ...$ids
    ): bool {
        foreach ($ids as $id) {
            if (
                isset($this->events['>' . $id]) ||
                isset($this->events['<' . $id])
            ) {
                return true;
            }
        }

        return false;
    }



    /**
     * Check ids and run callback
     *
     * @param array<string> $ids
     * @return $this
     */
    public function withBefore(
        array $ids,
        callable $callback
    ): static {
        if ($this->hasBefore(...$ids)) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Check ids and run callback
     *
     * @param array<string> $ids
     * @return $this
     */
    public function withAfter(
        array $ids,
        callable $callback
    ): static {
        if ($this->hasAfter(...$ids)) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Check ids and run callback
     *
     * @param array<string> $ids
     * @return $this
     */
    public function with(
        array $ids,
        callable $callback
    ): static {
        if ($this->has(...$ids)) {
            $callback($this);
        }

        return $this;
    }



    /**
     * Remove before handler(s)
     *
     * @return $this
     */
    public function removeBefore(
        string $id,
        callable $callback = null
    ): static {
        if ($callback) {
            unset($this->events['<' . $id][$this->hashCallable($callback)]);
        } else {
            unset($this->events['<' . $id]);
        }

        return $this;
    }

    /**
     * Remove after handler(s)
     *
     * @return $this
     */
    public function removeAfter(
        string $id,
        callable $callback = null
    ): static {
        if ($callback) {
            unset($this->events['>' . $id][$this->hashCallable($callback)]);
        } else {
            unset($this->events['>' . $id]);
        }

        return $this;
    }

    /**
     * Remove before and after handler(s)
     *
     * @return $this
     */
    public function remove(
        string $id,
        callable $callback = null
    ): static {
        $this->removeBefore($id, $callback);
        $this->removeAfter($id, $callback);

        return $this;
    }

    /**
     * Clear all events
     *
     * @return $this
     */
    public function clear(): static
    {
        $this->events = [];
        return $this;
    }
}
