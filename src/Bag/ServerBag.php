<?php

declare(strict_types=1);

namespace Chiron\RequestContext\Bag;

/**
 * Access to server parameters of request, every requested key will be normalized for better
 * usability.
 */
final class ServerBag extends ParameterBag
{
    private const LOWER = '-abcdefghijklmnopqrstuvwxyz';
    private const UPPER = '_ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * {@inheritdoc}
     */
    public function get(string $key, $default = null)
    {
        return parent::get($this->normalize($key), $default);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return parent::has($this->normalize($key));
    }

    /**
     * Normalizes a name to X_UPPER_NAME
     *
     * @param string $name
     *
     * @return string
     */
    private function normalize(string $name): string
    {
        return strtr($name, self::LOWER, self::UPPER);
    }
}
