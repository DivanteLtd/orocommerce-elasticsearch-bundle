<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */

namespace Divante\Bundle\ElasticsearchBundle\Tests\Unit\Engine;

use Divante\Bundle\ElasticsearchBundle\Engine\ElasticsearchExpressionVisitor;

use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Collections\Expr\Value;
use Elastica\Query\BoolQuery;
use Elastica\Query\Exists;
use Elastica\Query\Match;
use Elastica\Query\Prefix;
use Elastica\Query\Range;
use Elastica\Query\Term;
use Elastica\Query\Terms;
use Elastica\Query\Wildcard;
use Oro\Bundle\SearchBundle\Query\Criteria\Comparison as OroComparison;
use Oro\Bundle\SearchBundle\Query\Criteria\Criteria;

/**
 * Class ElasticsearchExpressionVisitorTest
 * @SuppressWarnings(PHPMD)
 */
class ElasticsearchExpressionVisitorTest extends \PHPUnit_Framework_TestCase
{
    public function testComposite()
    {
        $visitor = new ElasticsearchExpressionVisitor();

        $comp = new CompositeExpression(
            CompositeExpression::TYPE_AND,
            [
                new Comparison('text.test', Comparison::CONTAINS, new Value('aaa')),
                new Comparison('integer.visibility_anonymous', Comparison::EQ, new Value(1)),
            ]
        );
        $criteria = new Criteria($comp);

        $actual = $visitor->dispatch($criteria->getWhereExpression());

        $bool = new BoolQuery();
        $bool->addMust(new Match('test', 'aaa'));
        $bool->addFilter(new Term(['visibility_anonymous' => 1]));

        $this->assertEquals($bool, $actual);
    }

    public function testIn()
    {
        $visitor = new ElasticsearchExpressionVisitor();

        $comp = new Comparison('text.test', Comparison::IN, new Value([1, 2, 3]));

        $actual = $visitor->dispatch($comp);

        $expected = [ElasticsearchExpressionVisitor::FILTER => new Terms('test', [1, 2, 3])];

        $this->assertEquals($expected, $actual);
    }

    public function testInAsFilter()
    {
        $visitor = new ElasticsearchExpressionVisitor();

        $comp = new CompositeExpression(
            CompositeExpression::TYPE_AND,
            [
                new Comparison('text.test', Comparison::IN, new Value([1, 2, 3])),
            ]
        );

        $expected = new BoolQuery();
        $expected->addFilter(new Terms('test', [1, 2, 3]));

        $actual = $visitor->dispatch($comp);

        $this->assertEquals($expected, $actual);
    }

    public function testLike()
    {
        $visitor = new ElasticsearchExpressionVisitor();

        $comp = new Comparison('text.test', OroComparison::LIKE, new Value('a'));

        $actual = $visitor->dispatch($comp);

        $expected = [ElasticsearchExpressionVisitor::FILTER => new Wildcard('test.keyword', '*a*')];

        $this->assertEquals($expected, $actual);
    }

    public function testNotLike()
    {
        $visitor = new ElasticsearchExpressionVisitor();

        $comp = new Comparison('text.test', OroComparison::NOT_LIKE, new Value('a'));

        $actual = $visitor->dispatch($comp);

        $expected = [ElasticsearchExpressionVisitor::MUST_NOT => new Term(['test' => 'a'])];

        $this->assertEquals($expected, $actual);
    }

    public function testStartsWith()
    {
        $visitor = new ElasticsearchExpressionVisitor();

        $comp = new Comparison('text.category_path', OroComparison::STARTS_WITH, new Value('1_2_3'));

        $actual = $visitor->dispatch($comp);

        $prefix = new Prefix(['category_path' => '1_2_3']);
        $expected = [ElasticsearchExpressionVisitor::FILTER => $prefix];

        $this->assertEquals($expected, $actual);
    }

    public function testNotContains()
    {
        $visitor = new ElasticsearchExpressionVisitor();

        $comp = new CompositeExpression(
            CompositeExpression::TYPE_AND,
            [
                new Comparison('text.test', OroComparison::NOT_CONTAINS, new Value('abc'))
            ]
        );

        $actual = $visitor->dispatch($comp);

        $expected = new BoolQuery();
        $expected->addMustNot(new Match('test', 'abc'));

        $this->assertEquals($expected, $actual);
    }

    public function testEq()
    {
        $visitor = new ElasticsearchExpressionVisitor();

        $comp = new Comparison('integer.int_field', Comparison::EQ, new Value(1));

        $actual = $visitor->dispatch($comp);

        $expected = [ElasticsearchExpressionVisitor::FILTER => new Term(['int_field' => 1])];

        $this->assertEquals($expected, $actual);
    }

    public function testEqText()
    {
        $visitor = new ElasticsearchExpressionVisitor();

        $comp = new Comparison('text.text_field', Comparison::EQ, new Value('a'));

        $actual = $visitor->dispatch($comp);

        $expected = [ElasticsearchExpressionVisitor::FILTER => new Term(['text_field.keyword' => 'a'])];

        $this->assertEquals($expected, $actual);
    }

    public function testGte()
    {
        $visitor = new ElasticsearchExpressionVisitor();

        $comp = new Comparison('integer.int_field', Comparison::GTE, new Value(2));

        $actual = $visitor->dispatch($comp);

        $expected = [ElasticsearchExpressionVisitor::FILTER => new Range('int_field', ['gte' => 2])];

        $this->assertEquals($expected, $actual);
    }

    public function testGt()
    {
        $visitor = new ElasticsearchExpressionVisitor();

        $comp = new Comparison('integer.int_field', Comparison::GT, new Value(2));

        $actual = $visitor->dispatch($comp);

        $expected = [ElasticsearchExpressionVisitor::FILTER => new Range('int_field', ['gt' => 2])];

        $this->assertEquals($expected, $actual);
    }

    public function testLte()
    {
        $visitor = new ElasticsearchExpressionVisitor();

        $comp = new Comparison('integer.int_field', Comparison::LTE, new Value(2));

        $actual = $visitor->dispatch($comp);

        $expected = [ElasticsearchExpressionVisitor::FILTER => new Range('int_field', ['lte' => 2])];

        $this->assertEquals($expected, $actual);
    }

    public function testLt()
    {
        $visitor = new ElasticsearchExpressionVisitor();

        $comp = new Comparison('integer.int_field', Comparison::LT, new Value(2));

        $actual = $visitor->dispatch($comp);

        $expected = [ElasticsearchExpressionVisitor::FILTER => new Range('int_field', ['lt' => 2])];

        $this->assertEquals($expected, $actual);
    }

    public function testExists()
    {
        $visitor = new ElasticsearchExpressionVisitor();

        $comp = new Comparison('integer.int_field', OroComparison::EXISTS, new Value(null));

        $actual = $visitor->dispatch($comp);

        $expected = [ElasticsearchExpressionVisitor::MUST => new Exists('int_field')];

        $this->assertEquals($expected, $actual);
    }

    public function testNotExists()
    {
        $visitor = new ElasticsearchExpressionVisitor();

        $comp = new Comparison('integer.int_field', OroComparison::NOT_EXISTS, new Value(null));

        $actual = $visitor->dispatch($comp);

        $expected = [ElasticsearchExpressionVisitor::MUST_NOT => new Exists('int_field')];

        $this->assertEquals($expected, $actual);
    }

    public function testNotEquals()
    {
        $visitor = new ElasticsearchExpressionVisitor();

        $comp = new Comparison('text.test', Comparison::NEQ, new Value('t'));

        $actual = $visitor->dispatch($comp);

        $expected = [ElasticsearchExpressionVisitor::MUST_NOT => new Term(['test' => 't'])];

        $this->assertEquals($expected, $actual);
    }

    public function testStartsWithKeyword()
    {
        $visitor = new ElasticsearchExpressionVisitor();

        $comp = new Comparison('text.category_path', OroComparison::STARTS_WITH, new Value('1_2_3'));

        $actual = $visitor->dispatch($comp);

        $prefix = new Prefix(['category_path' => '1_2_3']);
        $expected = [ElasticsearchExpressionVisitor::FILTER => $prefix];

        $this->assertEquals($expected, $actual);
    }
    public function testStartsWithKeywordSku()
    {
        $visitor = new ElasticsearchExpressionVisitor();

        $comp = new Comparison('text.sku', OroComparison::STARTS_WITH, new Value('1_2_3'));

        $actual = $visitor->dispatch($comp);

        $prefix = new Prefix(['sku.keyword_lowercase' => '1_2_3']);
        $expected = [ElasticsearchExpressionVisitor::FILTER => $prefix];

        $this->assertEquals($expected, $actual);
    }
}
