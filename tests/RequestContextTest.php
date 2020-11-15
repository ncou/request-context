<?php

declare(strict_types=1);

namespace Chiron\Http\Test;

use Closure;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Nyholm\Psr7\ServerRequest;
use Chiron\Container\Container;
use Chiron\Http\Request\RequestContext;
use Chiron\Http\Request\Bag\ServerBag;
use Chiron\Http\Request\Bag\ParameterBag;
use Chiron\Http\Request\Bag\HeaderBag;
use Chiron\Http\Request\Bag\FileBag;
use Psr\Http\Message\ServerRequestInterface;
use Nyholm\Psr7\UploadedFile;

class RequestContextTest extends TestCase
{
    /**
     * @var Container
     */
    private $container;
    /**
     * @var RequestContext
     */
    private $context;
    /**
     * @var ServerRequestInterface
     */
    private $request;

    public function setUp(): void
    {
        $this->container = new Container();
        $this->context = new RequestContext($this->container);
        $this->request = new ServerRequest('GET', 'http://domain.com/hello-world');
    }

    public function testGetBag(): void
    {
        $this->container->bind(ServerRequestInterface::class, $this->request);

        $this->assertInstanceOf(ServerBag::class, $this->context->server);
        $this->assertInstanceOf(ParameterBag::class, $this->context->attributes);
        $this->assertInstanceOf(ParameterBag::class, $this->context->data);
        $this->assertInstanceOf(ParameterBag::class, $this->context->cookies);
        $this->assertInstanceOf(ParameterBag::class, $this->context->query);
        $this->assertInstanceOf(FileBag::class, $this->context->files);
        $this->assertInstanceOf(HeaderBag::class, $this->context->headers);
    }

    public function testWrongBag(): void
    {
        $this->container->bind(ServerRequestInterface::class, $this->request);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Undefined input bag 'invalid'");

        $this->context->invalid;
    }


    public function testParameterBagShortcuts(): void
    {
        $request = $this->request->withParsedBody([
            'user' => 'foobar',
            'name'  => 'xx'
        ])->withQueryParams([
            'name' => 'value',
            'key'  => 'hi'
        ])->withAttribute('attr', 'value')->withCookieParams([
            'cookie' => 'cookie-value'
        ]);

        $this->container->bind(ServerRequestInterface::class, $request);

        $this->assertSame('foobar', $this->context->data('user'));
        $this->assertSame('foobar', $this->context->post('user'));

        $this->assertSame('value', $this->context->query('name'));
        $this->assertSame('hi', $this->context->query('key'));

        $this->assertSame('xx', $this->context->input('name'));
        $this->assertSame('hi', $this->context->input('key'));
        $this->assertSame('value', $this->context->attribute('attr'));

        $this->assertSame('cookie-value', $this->context->cookie('cookie'));
    }

    public function testFileBagShortcut(): void
    {
        $file = new UploadedFile(
                fopen(__FILE__, 'r'),
                filesize(__FILE__),
                0,
                __FILE__
            );

        $request = $this->request->withUploadedFiles([
            'my_file' => $file
        ]);

        $this->container->bind(ServerRequestInterface::class, $request);

        $this->assertSame($file, $this->context->file('my_file'));
        $this->assertNull($this->context->file('non_existing_file'));
    }
}
