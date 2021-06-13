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

// Méthode pour détecter le "base_path" de l'application.
//https://github.com/drupal/drupal/blob/9.0.x/core/lib/Drupal/Core/DrupalKernel.php#L1087



//https://github.com/spiral/http/blob/6d95493328f40dc71840119e006eaa42a443f82f/src/Request/InputManager.php#49
//https://github.com/symfony/http-foundation/blob/master/Request.php#L273

//https://github.com/symfony/symfony/blob/e60a876201b5b306d0c81a24d9a3db997192079c/src/Symfony/Component/HttpFoundation/UrlHelper.php

//https://github.com/symfony/symfony/blob/fb123e4fcafd684907ac2b72c293392dac0caa26/src/Symfony/Component/HttpFoundation/Request.php
//https://github.com/illuminate/http/blob/master/Request.php

//https://github.com/slimphp/Slim-Http/blob/master/src/ServerRequest.php


// PROXY :
//*********************
//https://symfony.com/doc/2.8/components/http_foundation/trusting_proxies.html
//https://github.com/symfony/symfony/blob/2.8/src/Symfony/Component/HttpFoundation/Request.php#L1948
//https://github.com/symfony/symfony/blob/e60a876201b5b306d0c81a24d9a3db997192079c/src/Symfony/Component/HttpFoundation/IpUtils.php#L37

//https://github.com/akrabat/proxy-detection-middleware/blob/master/src/ProxyDetection.php
//https://github.com/akrabat/ip-address-middleware/blob/master/src/IpAddress.php

//https://github.com/yiisoft/yii2/blob/65e56408104b702fe21eb1639dbaa6ffaa47900f/framework/web/Request.php#L211




// TODO : mettre dans une classe xxxTrait tout ce qui touche à la partie "bags" ca rendra le code plus propre de le spéarer dans une autre classe !!!!
// TODO : utiliser aussi une classe RouteContext qui serait retournée par une méthode getRouteContext() de cette classe ???? https://github.com/slimphp/Slim/blob/4.x/Slim/Routing/RouteContext.php
// TODO : ajouter un __call() pour permettre d'appeller l'ensemble des méthodes existantes dans la classe ServerRequestInterface.
// TODO : ajouter des méthodes pour récupérer l'ip le host et port + scheme derriére un proxy => ajouter a liste des ip pour les trustiesProxy + gérer les headers X-Forwarded
// TODO : empécher de cloner cette classe ????
final class RequestContext implements SingletonInterface
{
    use RequestBagTrait;

    /** @var ServerRequestInterface */
    private $request = null;
    /** @var ContainerInterface */
    private $container = null;

    /**
     * @param ContainerInterface $container
     */
    // TODO : lui ajouter en paramétre une classe ProxiesConfig ou ProxyConfig ce qui permettra de récupérer les adresses IP ou plage d'ip qui sont "trusted" pour récupérer l'adresse ip et host/pot/scheme depuis les headers X-Forwarded-xxx, éventuellement au lieu de créer un fichier de config proxy.php.dist, on pourrait utiliser le fichier http.php.dist pour stocker ces infos, et utiliser un subset dans la classe de config pour récupérer que la partie proxy.
    // TODO : utiliser directement un objet de type "Chiron\Container::class"
    // TODO : récupérer un objet RequestScope::class dans le constructeur plutot qu'un container, et appeller la méthode ->getRequest()
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Get active instance of ServerRequestInterface and reset all bags if instance changed.
     *
     * @return ServerRequestInterface
     */
    public function getRequest(): ServerRequestInterface
    {
        // ensure the request instance is fresh (get it from the container).
        $this->refreshRequestInstance();
        //$this->request = get_current_request();

        return $this->request;
    }

    /**
     * Grab the latest Request instance 'existing' in the container.
     *
     * @throws ScopeException In case the Request is not found in the container.
     */
    private function refreshRequestInstance(): void
    {
        try {
            $this->request = $this->container->get(ServerRequestInterface::class); // externaliser cet appel dans une fonction globale du genre get_request() ou current_request()
        } catch (NotFoundExceptionInterface $e) {
            throw new ScopeException(
                'Unable to get "ServerRequestInterface" in active container scope.',
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Gets the request's scheme.
     *
     * @return string
     */
    public function getScheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    /**
     * Checks whether the request is secure or not.
     *
     * This method can read the client protocol from the "X-Forwarded-Proto" header
     * when trusted proxies were set via "setTrustedProxies()".
     *
     * The "X-Forwarded-Proto" header must contain the protocol: "https" or "http".
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        /*
        if ($this->isFromTrustedProxy() && $proto = $this->getTrustedValues(self::HEADER_X_FORWARDED_PROTO)) {
            return \in_array(strtolower($proto[0]), ['https', 'on', 'ssl', '1'], true);
        }

        $https = $this->server->get('HTTPS');

        return !empty($https) && 'off' !== strtolower($https);
        */

        $https = $this->request()->getServerParams()['HTTPS'] ?? null;

        return !empty($https) && strtolower($https) !== 'off';
    }



    /**
     * Get the root URL for the application.
     *
     * @return string
     */
    public function root()
    {
        return rtrim($this->getSchemeAndHttpHost().$this->getBaseUrl(), '/');
    }

    /**
     * Gets the scheme and HTTP host.
     *
     * If the URL was called with basic authentication, the user
     * and the password are not added to the generated string.
     *
     * @return string The scheme and HTTP host
     */
    public function getSchemeAndHttpHost()
    {
        return $this->getScheme().'://'.$this->getHttpHost();
    }

    /**
     * Returns the HTTP host being requested.
     *
     * The port name will be appended to the host if it's non-standard.
     *
     * @return string
     */
    public function getHttpHost()
    {
        $scheme = $this->getScheme();
        $port = $this->getPort();

        if (('http' == $scheme && 80 == $port) || ('https' == $scheme && 443 == $port)) {
            return $this->getHost();
        }

        return $this->getHost().':'.$port;
    }


    /**
     * Returns the host name.
     *
     * This method can read the client host name from the "X-Forwarded-Host" header
     * when trusted proxies were set via "setTrustedProxies()".
     *
     * The "X-Forwarded-Host" header must contain the client host name.
     *
     * @return string
     */
    public function getHost()
    {
        return $this->request()->getServerParams()['HTTP_HOST'];
    }

    /**
     * Returns the port on which the request is made.
     *
     * This method can read the client port from the "X-Forwarded-Port" header
     * when trusted proxies were set via "setTrustedProxies()".
     *
     * The "X-Forwarded-Port" header must contain the client port.
     *
     * @return int|string can be a string if fetched from the server bag
     */
    public function getPort()
    {
        return $this->request()->getServerParams()['SERVER_PORT'];
    }




    /**
     * Prepares the base URL.
     *
     * @return string
     */
    // TODO : stocker le résultat du baseurl et le vider uniquement lors du flush dans la méthode du getRequest() quand la request à changé.
    public function getBaseUrl()
    {
        $server = $this->request()->getServerParams();



        $filename = basename($server['SCRIPT_FILENAME']);

        if (basename($server['SCRIPT_NAME']) === $filename) {
            $baseUrl = $server['SCRIPT_NAME'];
        } elseif (basename($server['PHP_SELF']) === $filename) {
            $baseUrl = $server['PHP_SELF'];
        } elseif (basename($server['ORIG_SCRIPT_NAME']) === $filename) {
            $baseUrl = $server['ORIG_SCRIPT_NAME']; // 1and1 shared hosting compatibility
        } else {
            // Backtrack up the script_filename to find the portion matching
            // php_self
            $path = $server['PHP_SELF'] ?? '';
            $file = $server['SCRIPT_FILENAME'] ?? '';
            $segs = explode('/', trim($file, '/'));
            $segs = array_reverse($segs);
            $index = 0;
            $last = \count($segs);
            $baseUrl = '';
            do {
                $seg = $segs[$index];
                $baseUrl = '/'.$seg.$baseUrl;
                ++$index;
            } while ($last > $index && (false !== $pos = strpos($path, $baseUrl)) && 0 != $pos);
        }

        // Does the baseUrl have anything in common with the request_uri?
        $requestUri = $this->getRequestUri();
        if ('' !== $requestUri && '/' !== $requestUri[0]) {
            $requestUri = '/'.$requestUri;
        }

        if ($baseUrl && null !== $prefix = $this->getUrlencodedPrefix($requestUri, $baseUrl)) {
            // full $baseUrl matches
            return $prefix;
        }

        if ($baseUrl && null !== $prefix = $this->getUrlencodedPrefix($requestUri, rtrim(\dirname($baseUrl), '/'.\DIRECTORY_SEPARATOR).'/')) {
            // directory portion of $baseUrl matches
            return rtrim($prefix, '/'.\DIRECTORY_SEPARATOR);
        }

        $truncatedRequestUri = $requestUri;
        if (false !== $pos = strpos($requestUri, '?')) {
            $truncatedRequestUri = substr($requestUri, 0, $pos);
        }

        $basename = basename($baseUrl);
        if (empty($basename) || !strpos(rawurldecode($truncatedRequestUri), $basename)) {
            // no match whatsoever; set it blank
            return '';
        }

        // If using mod_rewrite or ISAPI_Rewrite strip the script filename
        // out of baseUrl. $pos !== 0 makes sure it is not matching a value
        // from PATH_INFO or QUERY_STRING
        if (\strlen($requestUri) >= \strlen($baseUrl) && (false !== $pos = strpos($requestUri, $baseUrl)) && 0 !== $pos) {
            $baseUrl = substr($requestUri, 0, $pos + \strlen($baseUrl));
        }

        return rtrim($baseUrl, '/'.\DIRECTORY_SEPARATOR);
    }

    /*
     * The following methods are derived from code of the Zend Framework (1.10dev - 2010-01-24)
     *
     * Code subject to the new BSD license (https://framework.zend.com/license).
     *
     * Copyright (c) 2005-2010 Zend Technologies USA Inc. (https://www.zend.com/)
     */

    private function getRequestUri()
    {

        $server = $this->request()->getServerParams();


        $requestUri = '';

        if (isset($server['REQUEST_URI'])) {
            $requestUri = $server['REQUEST_URI'];

            if ('' !== $requestUri && '/' === $requestUri[0]) {
                // To only use path and query remove the fragment.
                if (false !== $pos = strpos($requestUri, '#')) {
                    $requestUri = substr($requestUri, 0, $pos);
                }
            } else {
                // HTTP proxy reqs setup request URI with scheme and host [and port] + the URL path,
                // only use URL path.
                $uriComponents = parse_url($requestUri);

                if (isset($uriComponents['path'])) {
                    $requestUri = $uriComponents['path'];
                }

                if (isset($uriComponents['query'])) {
                    $requestUri .= '?'.$uriComponents['query'];
                }
            }
        }

        return $requestUri;
    }

    /**
     * Returns the prefix as encoded in the string when the string starts with
     * the given prefix, null otherwise.
     */
    private function getUrlencodedPrefix(string $string, string $prefix): ?string
    {
        if (0 !== strpos(rawurldecode($string), $prefix)) {
            return null;
        }

        $len = \strlen($prefix);

        if (preg_match(sprintf('#^(%%[[:xdigit:]]{2}|.){%d}#', $len), $string, $match)) {
            return $match[0];
        }

        return null;
    }

    //*******************************************
    // YII REQUEST Helpers :
    //*******************************************

    /**
     * Returns whether this is a GET request.
     * @return bool whether this is a GET request.
     */
    public function isGet()
    {
        return $this->getMethod() === 'GET';
    }

    /**
     * Returns whether this is an OPTIONS request.
     * @return bool whether this is a OPTIONS request.
     */
    public function isOptions()
    {
        return $this->getMethod() === 'OPTIONS';
    }

    /**
     * Returns whether this is a HEAD request.
     * @return bool whether this is a HEAD request.
     */
    public function isHead()
    {
        return $this->getMethod() === 'HEAD';
    }

    /**
     * Returns whether this is a POST request.
     * @return bool whether this is a POST request.
     */
    public function isPost()
    {
        return $this->getMethod() === 'POST';
    }

    /**
     * Returns whether this is a DELETE request.
     * @return bool whether this is a DELETE request.
     */
    public function isDelete()
    {
        return $this->getMethod() === 'DELETE';
    }

    /**
     * Returns whether this is a PUT request.
     * @return bool whether this is a PUT request.
     */
    public function isPut()
    {
        return $this->getMethod() === 'PUT';
    }

    /**
     * Returns whether this is a PATCH request.
     * @return bool whether this is a PATCH request.
     */
    public function isPatch()
    {
        return $this->getMethod() === 'PATCH';
    }

    /**
     * Returns whether this is an AJAX (XMLHttpRequest) request.
     *
     * Note that in case of cross domain requests, browser doesn't set the X-Requested-With header by default:
     * https://stackoverflow.com/questions/8163703/cross-domain-ajax-doesnt-send-x-requested-with-header
     *
     * In case you are using `fetch()`, pass header manually:
     *
     * ```
     * fetch(url, {
     *    method: 'GET',
     *    headers: {'X-Requested-With': 'XMLHttpRequest'}
     * })
     * ```
     *
     * @return bool whether this is an AJAX (XMLHttpRequest) request.
     */
    public function isAjax()
    {
        return $this->headers->get('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Returns whether this is a PJAX request.
     * @return bool whether this is a PJAX request
     */
    public function isPjax()
    {
        return $this->isAjax() && $this->headers->has('X-Pjax');
    }

    /**
     * Returns the raw HTTP request body.
     * @return string the request body
     */
    public function getRawBody()
    {
        if ($this->_rawBody === null) {
            $this->_rawBody = file_get_contents('php://input');
        }

        return $this->_rawBody;
    }

    /**
     * Returns the URL referrer.
     * @return string|null URL referrer, null if not available
     */
    public function getReferrer()
    {
        return $this->headers->get('Referer');
    }

    /**
     * Returns the URL origin of a CORS request.
     *
     * The return value is taken from the `Origin` [[getHeaders()|header]] sent by the browser.
     *
     * Note that the origin request header indicates where a fetch originates from.
     * It doesn't include any path information, but only the server name.
     * It is sent with a CORS requests, as well as with POST requests.
     * It is similar to the referer header, but, unlike this header, it doesn't disclose the whole path.
     * Please refer to <https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Origin> for more information.
     *
     * @return string|null URL origin of a CORS request, `null` if not available.
     * @see getHeaders()
     * @since 2.0.13
     */
    public function getOrigin()
    {
        return $this->getHeaders()->get('origin');
    }

    /**
     * Returns the user agent.
     * @return string|null user agent, null if not available
     */
    public function getUserAgent()
    {
        return $this->headers->get('User-Agent');
    }

    /**
     * @return string|null the username sent via HTTP authentication, `null` if the username is not given
     * @see getAuthCredentials() to get both username and password in one call
     */
    public function getAuthUser()
    {
        return $this->getAuthCredentials()[0];
    }

    /**
     * @return string|null the password sent via HTTP authentication, `null` if the password is not given
     * @see getAuthCredentials() to get both username and password in one call
     */
    public function getAuthPassword()
    {
        return $this->getAuthCredentials()[1];
    }

    /**
     * @return array that contains exactly two elements:
     * - 0: the username sent via HTTP authentication, `null` if the username is not given
     * - 1: the password sent via HTTP authentication, `null` if the password is not given
     * @see getAuthUser() to get only username
     * @see getAuthPassword() to get only password
     * @since 2.0.13
     */
    public function getAuthCredentials()
    {
        $username = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : null;
        $password = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : null;
        if ($username !== null || $password !== null) {
            return [$username, $password];
        }

        /*
         * Apache with php-cgi does not pass HTTP Basic authentication to PHP by default.
         * To make it work, add the following line to to your .htaccess file:
         *
         * RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
         */
        $auth_token = $this->getHeaders()->get('HTTP_AUTHORIZATION') ?: $this->getHeaders()->get('REDIRECT_HTTP_AUTHORIZATION');
        if ($auth_token !== null && strncasecmp($auth_token, 'basic', 5) === 0) {
            $parts = array_map(function ($value) {
                return strlen($value) === 0 ? null : $value;
            }, explode(':', base64_decode(mb_substr($auth_token, 6)), 2));

            if (count($parts) < 2) {
                return [$parts[0], null];
            }

            return $parts;
        }

        return [null, null];
    }

    /**
     * Returns request content-type
     * The Content-Type header field indicates the MIME type of the data
     * contained in [[getRawBody()]] or, in the case of the HEAD method, the
     * media type that would have been sent had the request been a GET.
     * For the MIME-types the user expects in response, see [[acceptableContentTypes]].
     * @return string request content-type. Null is returned if this information is not available.
     * @link https://tools.ietf.org/html/rfc2616#section-14.17
     * HTTP 1.1 header field definitions
     */
    public function getContentType()
    {
        if (isset($_SERVER['CONTENT_TYPE'])) {
            return $_SERVER['CONTENT_TYPE'];
        }

        //fix bug https://bugs.php.net/bug.php?id=66606
        return $this->headers->get('Content-Type');
    }

    /**
     * Gets the Etags.
     *
     * @return array The entity tags
     */
    public function getETags()
    {
        if ($this->headers->has('If-None-Match')) {
            return preg_split('/[\s,]+/', str_replace('-gzip', '', $this->headers->get('If-None-Match')), -1, PREG_SPLIT_NO_EMPTY);
        }

        return [];
    }

    /**
     * Returns the content types acceptable by the end user.
     *
     * This is determined by the `Accept` HTTP header. For example,
     *
     * ```php
     * $_SERVER['HTTP_ACCEPT'] = 'text/plain; q=0.5, application/json; version=1.0, application/xml; version=2.0;';
     * $types = $request->getAcceptableContentTypes();
     * print_r($types);
     * // displays:
     * // [
     * //     'application/json' => ['q' => 1, 'version' => '1.0'],
     * //      'application/xml' => ['q' => 1, 'version' => '2.0'],
     * //           'text/plain' => ['q' => 0.5],
     * // ]
     * ```
     *
     * @return array the content types ordered by the quality score. Types with the highest scores
     * will be returned first. The array keys are the content types, while the array values
     * are the corresponding quality score and other parameters as given in the header.
     */
    public function getAcceptableContentTypes()
    {
        if ($this->_contentTypes === null) {
            if ($this->headers->get('Accept') !== null) {
                $this->_contentTypes = $this->parseAcceptHeader($this->headers->get('Accept'));
            } else {
                $this->_contentTypes = [];
            }
        }

        return $this->_contentTypes;
    }


    /**
     * Returns the languages acceptable by the end user.
     * This is determined by the `Accept-Language` HTTP header.
     * @return array the languages ordered by the preference level. The first element
     * represents the most preferred language.
     */
    public function getAcceptableLanguages()
    {
        if ($this->_languages === null) {
            if ($this->headers->has('Accept-Language')) {
                $this->_languages = array_keys($this->parseAcceptHeader($this->headers->get('Accept-Language')));
            } else {
                $this->_languages = [];
            }
        }

        return $this->_languages;
    }


    /**
     * Parses the given `Accept` (or `Accept-Language`) header.
     *
     * This method will return the acceptable values with their quality scores and the corresponding parameters
     * as specified in the given `Accept` header. The array keys of the return value are the acceptable values,
     * while the array values consisting of the corresponding quality scores and parameters. The acceptable
     * values with the highest quality scores will be returned first. For example,
     *
     * ```php
     * $header = 'text/plain; q=0.5, application/json; version=1.0, application/xml; version=2.0;';
     * $accepts = $request->parseAcceptHeader($header);
     * print_r($accepts);
     * // displays:
     * // [
     * //     'application/json' => ['q' => 1, 'version' => '1.0'],
     * //      'application/xml' => ['q' => 1, 'version' => '2.0'],
     * //           'text/plain' => ['q' => 0.5],
     * // ]
     * ```
     *
     * @param string $header the header to be parsed
     * @return array the acceptable values ordered by their quality score. The values with the highest scores
     * will be returned first.
     */
    public function parseAcceptHeader($header)
    {
        $accepts = [];
        foreach (explode(',', $header) as $i => $part) {
            $params = preg_split('/\s*;\s*/', trim($part), -1, PREG_SPLIT_NO_EMPTY);
            if (empty($params)) {
                continue;
            }
            $values = [
                'q' => [$i, array_shift($params), 1],
            ];
            foreach ($params as $param) {
                if (strpos($param, '=') !== false) {
                    list($key, $value) = explode('=', $param, 2);
                    if ($key === 'q') {
                        $values['q'][2] = (float) $value;
                    } else {
                        $values[$key] = $value;
                    }
                } else {
                    $values[] = $param;
                }
            }
            $accepts[] = $values;
        }

        usort($accepts, function ($a, $b) {
            $a = $a['q']; // index, name, q
            $b = $b['q'];
            if ($a[2] > $b[2]) {
                return -1;
            }

            if ($a[2] < $b[2]) {
                return 1;
            }

            if ($a[1] === $b[1]) {
                return $a[0] > $b[0] ? 1 : -1;
            }

            if ($a[1] === '*/*') {
                return 1;
            }

            if ($b[1] === '*/*') {
                return -1;
            }

            $wa = $a[1][strlen($a[1]) - 1] === '*';
            $wb = $b[1][strlen($b[1]) - 1] === '*';
            if ($wa xor $wb) {
                return $wa ? 1 : -1;
            }

            return $a[0] > $b[0] ? 1 : -1;
        });

        $result = [];
        foreach ($accepts as $accept) {
            $name = $accept['q'][1];
            $accept['q'] = $accept['q'][2];
            $result[$name] = $accept;
        }

        return $result;
    }

    /**
     * Returns the user-preferred language that should be used by this application.
     * The language resolution is based on the user preferred languages and the languages
     * supported by the application. The method will try to find the best match.
     * @param array $languages a list of the languages supported by the application. If this is empty, the current
     * application language will be returned without further processing.
     * @return string the language that the application should use.
     */
    public function getPreferredLanguage(array $languages = [])
    {
        if (empty($languages)) {
            return Yii::$app->language;
        }
        foreach ($this->getAcceptableLanguages() as $acceptableLanguage) {
            $acceptableLanguage = str_replace('_', '-', strtolower($acceptableLanguage));
            foreach ($languages as $language) {
                $normalizedLanguage = str_replace('_', '-', strtolower($language));

                if (
                    $normalizedLanguage === $acceptableLanguage // en-us==en-us
                    || strpos($acceptableLanguage, $normalizedLanguage . '-') === 0 // en==en-us
                    || strpos($normalizedLanguage, $acceptableLanguage . '-') === 0 // en-us==en
                ) {
                    return $language;
                }
            }
        }

        return reset($languages);
    }
}
