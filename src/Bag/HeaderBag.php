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
    // TODO : il faudrait surement aussi enlever le HTTP_ devant le nom.
    // TODO : déplacer cette méthode dans la future classe Header::class avec une méthode normalize !!!
    //https://github.com/yiisoft/yii2-framework/blob/b80830c5f8c221b8bd6a830c5b2f45544f82c4d7/filters/Cors.php#L256
    // TODO : renommer en headerize() ???
    private function normalize(string $name): string
    {
        $value = ucwords(str_replace(['-', '_'], ' ', $name));

        return str_replace(' ', '-', $value);

        //return str_replace(' ', '', ucwords(strtr($word, '_-', '  ')));  // https://github.com/guzzle/guzzle3/blob/f7778ed85e3db90009d79725afd6c3a82dab32fe/src/Guzzle/Inflection/Inflector.php#L36
    }

    /**
     * Normalize the given header name into studly-case.
     *
     * @param  string  $name
     * @return string
     */
    // TODO : déplacer cette méthode dans la future classe Header::class avec une méthode normalize !!!
    /*
    protected static function normalizeHeaderName($name)
    {
        return str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
    }*/

/*
    if (strncmp($name, 'HTTP_', 5) === 0) {
        $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
        $this->_headers->add($name, $value);
    }
    */

}
