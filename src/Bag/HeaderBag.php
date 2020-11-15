<?php

declare(strict_types=1);

namespace Chiron\RequestContext\Bag;

/**
 * Provides access to headers property of server request, will normalize every requested name for
 * use convenience.
 */
final class HeaderBag extends ParameterBag
{
    /**
     * {@inheritdoc}
     *
     *
     * @param bool|string $implode Implode header lines, false to return header as array.

     * @return string|array
     */
    public function get(string $key, $default = null, $implode = ',')
    {
        $value = parent::get($this->normalize($key), $default);

        if (!empty($implode) && is_array($value)) {
            return implode($implode, $value);
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return parent::has($this->normalize($key));
    }

    /**
     * Normalizes a name to X-Capitalized-Name
     *
     * @param string $name

     * @return string
     */
    private function normalize(string $name): string
    {
        $normalized = str_replace(['-', '_'], ' ', $name);
        $normalized = ucwords(strtolower($normalized));

        return str_replace(' ', '-', $normalized);
    }
}
