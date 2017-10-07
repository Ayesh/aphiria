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
use Opulence\Net\Http\IHttpBody;

/**
 * Tests the multipart body part
 */
class MultipartBodyPartTest extends \PHPUnit\Framework\TestCase
{
    /** @var MultipartBodyPart The body part to use in tests */
    private $bodyPart = null;
    /** @var HttpHeaders The headers to use in tests */
    private $headers = null;
    /** @var IHttpBody|\PHPUnit_Framework_MockObject_MockObject The body to use in tests */
    private $body = null;

    /**
     * Sets up the tests
     */
    public function setUp() : void
    {
        $this->headers = new HttpHeaders();
        $this->body = $this->createMock(IHttpBody::class);
        $this->bodyPart = new MultipartBodyPart($this->headers, $this->body);
    }

    /**
     * Tests getting body
     */
    public function testGettingBody() : void
    {
        $this->assertSame($this->body, $this->bodyPart->getBody());
    }

    /**
     * Tests getting headers
     */
    public function testGettingHeaders() : void
    {
        $this->assertSame($this->headers, $this->bodyPart->getHeaders());
    }
}
