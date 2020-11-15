<?php

declare(strict_types=1);

namespace Chiron\RequestContext\Bag;

use LogicException;
use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

/**
 * Generic parameters accessor, used to read key/value pairs.
 */
class ParameterBag implements ArrayAccess, Countable, IteratorAggregate
{
    /** @var array */
    private $parameters = [];

    /**
     * @param array  $parameters
     */
    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }

    /**
     * Returns all the parameter key/value pairs.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->parameters;
    }

    /**
     * Returns the parameter keys.
     *
     * @return array An array of parameter keys
     */
    public function keys()
    {
        return array_keys($this->parameters);
    }

    /**
     * Filter key.
     *
     * @param string $key
     * @param mixed  $default The default value if the parameter key does not exist
     * @param int    $filter  FILTER_* constant
     * @param mixed  $options Filter options
     *
     * @see https://php.net/filter-var
     *
     * @return mixed
     */
    public function filter(string $key, $default = null, int $filter = FILTER_DEFAULT, $options = [])
    {
        $value = $this->get($key, $default);

        // Always turn $options into an array - this allows filter_var option shortcuts.
        if (! is_array($options) && $options) {
            $options = ['flags' => $options];
        }

        // Add a convenience check for arrays.
        if (is_array($value) && ! isset($options['flags'])) {
            $options['flags'] = FILTER_REQUIRE_ARRAY;
        }

        return filter_var($value, $filter, $options);
    }

    /**
     * Returns a parameter by name (default value used if not found).
     *
     * @param string $key
     * @param mixed  $default The default value if the parameter key does not exist
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->has($key) ? $this->parameters[$key] : $default;
    }

    /**
     * Check if the parameter is defined.
     *
     * @param string $key
     *
     * @return bool true if the parameter exists, false otherwise
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * {@inheritdoc}
     *
     * @throws InputException
     */
    public function offsetSet($offset, $value): void
    {
        throw new LogicException('ParameterBag is immutable');
    }

    /**
     * {@inheritdoc}
     *
     * @throws InputException
     */
    public function offsetUnset($offset): void
    {
        throw new LogicException('ParameterBag is immutable');
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->parameters);
    }
}
