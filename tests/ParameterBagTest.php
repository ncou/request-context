<?php

declare(strict_types=1);

namespace Chiron\Http\Test;

use Closure;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Nyholm\Psr7\ServerRequest;
use Chiron\Container\Container;
use Chiron\Http\Request\Bag\ParameterBag;
use Psr\Http\Message\ServerRequestInterface;

// TODO : ajouter les tests pour la partie "ArrayAccess" !!!

class ParameterBagTest extends TestCase
{

    public function testConstructor()
    {
        $this->testAll();
    }

    public function testAll()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        $this->assertEquals(['foo' => 'bar'], $bag->all());
    }

    public function testKeys()
    {
        $bag = new ParameterBag(['foo' => 'bar']);
        $this->assertEquals(['foo'], $bag->keys());
    }

    public function testFilter()
    {
        $bag = new ParameterBag([
            'digits' => '0123ab',
            'email' => 'example@example.com',
            'url' => 'http://example.com/foo',
            'dec' => '256',
            'hex' => '0x100',
            'array' => ['bang'],
            ]);

        $this->assertEmpty($bag->filter('nokey'));
        $this->assertEquals('0123', $bag->filter('digits', '', \FILTER_SANITIZE_NUMBER_INT));
        $this->assertEquals('example@example.com', $bag->filter('email', '', \FILTER_VALIDATE_EMAIL));
        $this->assertEquals('http://example.com/foo', $bag->filter('url', '', \FILTER_VALIDATE_URL, ['flags' => \FILTER_FLAG_PATH_REQUIRED]));
        // This test is repeated for code-coverage
        $this->assertEquals('http://example.com/foo', $bag->filter('url', '', \FILTER_VALIDATE_URL, \FILTER_FLAG_PATH_REQUIRED));
        $this->assertEquals(['bang'], $bag->filter('array', ''));

        $this->assertFalse($bag->filter('dec', '', \FILTER_VALIDATE_INT, [
            'flags' => \FILTER_FLAG_ALLOW_HEX,
            'options' => ['min_range' => 1, 'max_range' => 0xff],
        ]));

        $this->assertFalse($bag->filter('hex', '', \FILTER_VALIDATE_INT, [
            'flags' => \FILTER_FLAG_ALLOW_HEX,
            'options' => ['min_range' => 1, 'max_range' => 0xff],
        ]));
    }

    public function testGet()
    {
        $bag = new ParameterBag(['foo' => 'bar', 'null' => null]);

        $this->assertEquals('bar', $bag->get('foo'), '->get() gets the value of a parameter');
        $this->assertEquals('default', $bag->get('unknown', 'default'), '->get() returns second argument as default if a parameter is not defined');
        $this->assertNull($bag->get('null', 'default'), '->get() returns null if null is set');
    }

    public function testGetDoesNotUseDeep()
    {
        $bag = new ParameterBag(['foo' => ['bar' => 'moo']]);

        $this->assertNull($bag->get('foo[bar]'));
    }

    public function testHas()
    {
        $bag = new ParameterBag(['foo' => 'bar']);

        $this->assertTrue($bag->has('foo'), '->has() returns true if a parameter is defined');
        $this->assertFalse($bag->has('unknown'), '->has() return false if a parameter is not defined');
    }

    public function testCount()
    {
        $parameters = ['foo' => 'bar', 'hello' => 'world'];
        $bag = new ParameterBag($parameters);

        $this->assertCount(\count($parameters), $bag);
    }

    public function testGetIterator()
    {
        $parameters = ['foo' => 'bar', 'hello' => 'world'];
        $bag = new ParameterBag($parameters);

        $i = 0;
        foreach ($bag as $key => $val) {
            ++$i;
            $this->assertEquals($parameters[$key], $val);
        }

        $this->assertEquals(\count($parameters), $i);
    }
}
