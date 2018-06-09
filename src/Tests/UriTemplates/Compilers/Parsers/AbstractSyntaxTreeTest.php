<?php

/*
 * Opulence
 *
 * @link      https://www.opulencephp.com
 * @copyright Copyright (C) 2017 David Young
 * @license   https://github.com/opulencephp/route-matcher/blob/master/LICENSE.md
 */

namespace Opulence\Routing\Tests\UriTemplates\Compilers\Parsers;

use Opulence\Routing\UriTemplates\Compilers\Parsers\AbstractSyntaxTree;
use Opulence\Routing\UriTemplates\Compilers\Parsers\Nodes\Node;
use Opulence\Routing\UriTemplates\Compilers\Parsers\Nodes\NodeTypes;

/**
 * Tests the route abstract syntax tree
 */
class AbstractSyntaxTreeTest extends \PHPUnit\Framework\TestCase
{
    /** @var AbstractSyntaxTree The tree to use in tests */
    private $tree;

    public function setUp()
    {
        $this->tree = new AbstractSyntaxTree();
    }

    public function testClearingNodesRetainsARootNode()
    {
        /** @var Node|\PHPUnit_Framework_MockObject_MockObject $childNode */
        $childNode = new Node('foo', 'bar');
        $this->tree->getCurrentNode()->addChild($childNode);
        $this->tree->clearNodes();
        $this->assertInstanceOf(Node::class, $this->tree->getCurrentNode());
        $this->assertEquals(NodeTypes::ROOT, $this->tree->getRootNode()->getType());
        $this->assertEquals([], $this->tree->getRootNode()->getChildren());
    }

    public function testGettingCurrentNodeWhenNoneIsSetReturnsRootNode()
    {
        $this->assertInstanceOf(Node::class, $this->tree->getCurrentNode());
        $this->assertEquals(NodeTypes::ROOT, $this->tree->getCurrentNode()->getType());
        $this->assertEquals([], $this->tree->getCurrentNode()->getChildren());
    }

    public function testGettingRootNodeReturnsProperlySetNode()
    {
        $this->assertInstanceOf(Node::class, $this->tree->getRootNode());
        $this->assertEquals(NodeTypes::ROOT, $this->tree->getRootNode()->getType());
        $this->assertEquals([], $this->tree->getRootNode()->getChildren());
    }

    public function testSettingCurrentNode()
    {
        /** @var Node $currentNode */
        $currentNode = new Node('foo', 'bar');
        $this->assertSame($currentNode, $this->tree->setCurrentNode($currentNode));
        $this->assertSame($currentNode, $this->tree->getCurrentNode());
    }
}
