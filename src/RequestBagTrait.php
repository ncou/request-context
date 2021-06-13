<?php

declare(strict_types=1);

namespace Chiron\RequestContext;

use Chiron\Container\SingletonInterface;
use Chiron\Facade\HttpDecorator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Chiron\Container\BindingInterface;
use Psr\Container\NotFoundExceptionInterface;
use Chiron\Core\Exception\ScopeException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\UploadedFileInterface;
use Chiron\RequestContext\Bag\ServerBag;
use Chiron\RequestContext\Bag\ParameterBag;
use Chiron\RequestContext\Bag\HeaderBag;
use Chiron\RequestContext\Bag\FileBag;

// TODO : créer un répertoire Traits et ajouter cette classe dans ce répertoire plutot que de la laisser à la racine !!!!

trait RequestBagTrait
{
    /**
     * Associations between bags and representing class/request method.
     *
     * @var array
     */
    // TODO : renommer "data" en "body" ???
    // TODO : passer cette variable en static ????
    private $bagsMapping = [
        'headers'    => [
            'class'  => HeaderBag::class,
            'source' => 'getHeaders',
        ],
        'server'     => [
            'class'  => ServerBag::class,
            'source' => 'getServerParams',
        ],
        'data'       => [
            'class'  => ParameterBag::class,
            'source' => 'getParsedBody',
        ],
        'query'      => [
            'class'  => ParameterBag::class,
            'source' => 'getQueryParams',
        ],
        'cookies'    => [
            'class'  => ParameterBag::class,
            'source' => 'getCookieParams',
        ],
        'attributes' => [
            'class'  => ParameterBag::class,
            'source' => 'getAttributes',
        ],
        'files'      => [
            'class'  => FileBag::class,
            'source' => 'getUploadedFiles',
        ],
    ];

    /**
     * @param string $name
     * @return ParameterBag
     */
    public function __get(string $name): ParameterBag
    {
        return $this->bag($name);
    }

    /**
     * Get bag instance or create new one on demand.
     *
     * @param string $name
     * @return ParameterBag
     */
    private function bag(string $name): ParameterBag
    {
        if (! isset($this->bagsMapping[$name])) {
            throw new \RuntimeException("Undefined input bag '{$name}'"); // TODO : lister les choix possible dans cette exception !!! Eventuellement remplacer cette exception par un InvalidArgumentException.  exemple : throw new InvalidOptionsException(sprintf('The options "%s" do not exist in constraint "%s".', implode('", "', $invalidOptions), static::class), $invalidOptions);
        }

        $request = $this->getRequest();
        $method = $this->bagsMapping[$name]['source'];
        // Retrieve the request data.
        $data = call_user_func([$request, $method]);

        $class = $this->bagsMapping[$name]['class'];
        $bag = new $class((array) $data);

        return $bag;
    }

    /**
     * @param string $name
     * @param mixed  $default
     * @return mixed
     *
     * @see data()
     */
    public function post(string $name, $default = null)
    {
        return $this->data($name, $default);
    }

    /**
     * Reads data from data array, if not found query array will be used as fallback.
     *
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function input(string $name, $default = null)
    {
        return $this->data($name, $this->query($name, $default));
    }

    /**
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function data(string $name, $default = null)
    {
        return $this->data->get($name, $default);
    }

    /**
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function query(string $name, $default = null)
    {
        return $this->query->get($name, $default);
    }

    /**
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public function cookie(string $name, $default = null)
    {
        return $this->cookies->get($name, $default);
    }

    /**
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function server(string $name, $default = null)
    {
        return $this->server->get($name, $default);
    }

    /**
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function attribute(string $name, $default = null)
    {
        return $this->attributes->get($name, $default);
    }

    /**
     * @param string      $name
     * @param mixed       $default
     * @param bool|string $implode Implode header lines, false to return header as array.
     * @return mixed
     */
    public function header(string $name, $default = null, $implode = ',')
    {
        return $this->headers->get($name, $default, $implode);
    }

     /**
     * @param string $name
     * @param UploadedFileInterface|null  $default
     *
     * @return UploadedFileInterface|null
     */
    public function file(string $name, ?UploadedFileInterface $default = null): ?UploadedFileInterface
    {
        return $this->files->get($name, $default);
    }
}
