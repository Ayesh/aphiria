<?php

/*
 * Opulence
 *
 * @link      https://www.opulencephp.com
 * @copyright Copyright (C) 2017 David Young
 * @license   https://github.com/opulencephp/Opulence/blob/master/LICENSE.md
 */

namespace Opulence\Net\Http\Requests;

use Opulence\Net\Http\HttpHeaders;

/**
 * Tests the HTTP request header parser
 */
class HttpRequestHeaderParserTest extends \PHPUnit\Framework\TestCase
{
    /** @var HttpRequestHeaderParser The parser to use in tests */
    private $parser = null;
    /** @var HttpHeaders The headers to use in tests */
    private $headers = null;

    /**
     * Sets up the tests
     */
    public function setUp() : void
    {
        $this->parser = new HttpRequestHeaderParser();
        $this->headers = new HttpHeaders();
    }

    /**
     * Tests checking if the headers indicate a JSON response with the value of the content type header
     */
    public function testCheckingIfJsonChecksContentTypeHeader()
    {
        $this->headers->add('Content-Type', 'text/plain');
        $this->assertFalse($this->parser->isJson($this->headers));
        $this->headers->remove('Content-Type');
        $this->headers->add('Content-Type', 'application/json');
        $this->assertTrue($this->parser->isJson($this->headers));
        $this->headers->remove('Content-Type');
        $this->headers->add('Content-Type', 'application/json; charset=utf-8');
        $this->assertTrue($this->parser->isJson($this->headers));
    }

    /**
     * Tests checking if the headers indicate an XHR request with the value of the X-Requested-With header
     */
    public function testCheckingIfXhrChecksXRequestedWithHeader()
    {
        $this->headers->add('X-Requested-With', 'XMLHttpRequest');
        $this->assertTrue($this->parser->isXhr($this->headers));
        $this->headers->remove('X-Requested-With');
        $this->assertFalse($this->parser->isXhr($this->headers));
    }
}
