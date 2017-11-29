<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */

namespace Divante\Bundle\ElasticsearchBundle\Engine;

use Doctrine\Common\Collections\Expr\Comparison;
use Elastica\Query\Exists;
use Elastica\Query\Prefix;
use Elastica\Query\Range;
use Elastica\Query\Wildcard;
use Oro\Bundle\SearchBundle\Query\Criteria\Comparison as OroComparison;
use Doctrine\Common\Collections\Expr\CompositeExpression;
use Doctrine\Common\Collections\Expr\ExpressionVisitor;
use Doctrine\Common\Collections\Expr\Value;
use Elastica\Query\BoolQuery;
use Elastica\Query\Match;
use Elastica\Query\Term;
use Elastica\Query\Terms;
use Oro\Bundle\SearchBundle\Query\Criteria\Criteria;
use Oro\Bundle\SearchBundle\Query\Query;

/**
 * Class ElasticsearchExpressionVisitor
 */
class ElasticsearchExpressionVisitor extends ExpressionVisitor
{
    const MUST = 'must';
    const MUST_OR_SHOULD = 'must_or_should';
    const SHOULD = 'should';
    const MUST_NOT = 'must_not';
    const FILTER = 'filter';


    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function walkComparison(Comparison $comparison)
    {
        $field = $comparison->getField();
        list($type, $fieldName) = $this->explodeFieldTypeName($field);
        $value = $comparison->getValue()->getValue();

        switch ($comparison->getOperator()) {
            case Comparison::CONTAINS:
                return [self::MUST_OR_SHOULD => new Match($fieldName, $value)];
            case OroComparison::NOT_CONTAINS:
                return [self::MUST_NOT => new Match($fieldName, $value)];
            case Comparison::EQ:
                if ($type == 'text') {
                    $fieldName = $fieldName . '.keyword';
                }
                return [self::FILTER => new Term([$fieldName => $value])];
            case Comparison::IN:
                return [self::FILTER => new Terms($fieldName, $value)];
            case OroComparison::LIKE:
                return [self::FILTER => new Wildcard($fieldName . '.keyword', '*' . $value . '*')];
            case OroComparison::NOT_LIKE:
                return [self::MUST_NOT => new Term([$fieldName => $value])];
            case OroComparison::STARTS_WITH:
                if ($fieldName == 'sku') {
                    $fieldName = $fieldName . '.keyword_lowercase';
                }
                return [self::FILTER => new Prefix([$fieldName => $value])];
            case Comparison::GTE:
                return [self::FILTER => new Range($fieldName, ['gte' => $value])];
            case Comparison::GT:
                return [self::FILTER => new Range($fieldName, ['gt' => $value])];
            case Comparison::LTE:
                return [self::FILTER => new Range($fieldName, ['lte' => $value])];
            case Comparison::LT:
                return [self::FILTER => new Range($fieldName, ['lt' => $value])];
            case OroComparison::EXISTS:
                return [self::MUST => new Exists($fieldName)];
            case OroComparison::NOT_EXISTS:
                return [self::MUST_NOT => new Exists($fieldName)];
            case Comparison::NEQ:
                return [self::MUST_NOT => new Term([$fieldName => $value])];
            default:
                throw new \RuntimeException(sprintf('Not implemented operator %s', $comparison->getOperator()));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function walkValue(Value $value)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function walkCompositeExpression(CompositeExpression $expr)
    {
        $expressionList = [];

        foreach ($expr->getExpressionList() as $child) {
            $expressionList[] = $this->dispatch($child);
        }

        switch ($expr->getType()) {
            case CompositeExpression::TYPE_AND:
                return $this->andExpressions($expressionList);

            case CompositeExpression::TYPE_OR:
                return $this->orExpressions($expressionList);

            default:
                throw new \RuntimeException("Unknown composite " . $expr->getType());
        }
    }

    /**
     * @param array $expressionList
     * @return BoolQuery
     */
    protected function andExpressions(array $expressionList)
    {
        $bool = new BoolQuery();
        foreach ($expressionList as $expression) {
            if ($expression instanceof BoolQuery) {
                $bool->addMust($expression);
            } else {
                $key = key($expression);
                $value = reset($expression);
                switch ($key) {
                    case self::MUST_OR_SHOULD:
                        $bool->addMust($value);
                        break;
                    case self::MUST:
                        $bool->addMust($value);
                        break;
                    case self::FILTER:
                        $bool->addFilter($value);
                        break;
                    case self::MUST_NOT:
                        $bool->addMustNot($value);
                        break;
                }
            }
        }

        return $bool;
    }

    /**
     * @param array $expressionList
     * @return BoolQuery
     */
    protected function orExpressions(array $expressionList)
    {
        $bool = new BoolQuery();
        foreach ($expressionList as $expression) {
            if ($expression instanceof BoolQuery) {
                $bool->addShould($expression);
            } else {
                $key = key($expression);
                $value = reset($expression);
                switch ($key) {
                    case self::MUST_OR_SHOULD:
                        $bool->addShould($value);
                        break;
                    case self::SHOULD:
                        $bool->addShould($value);
                        break;
                    case self::FILTER:
                        $bool->addFilter($value);
                        break;
                    case self::MUST_NOT:
                        $bool->addMustNot($value);
                        break;
                }
            }
        }

        return $bool;
    }

    /**
     * @param string $field
     *
     * @return array
     *  [0] - field type
     *  [1] - field name
     */
    protected static function explodeFieldTypeName($field)
    {
        $fieldType = Query::TYPE_TEXT;
        if (strpos($field, '.') !== false) {
            $fields = explode('.', $field);
            $fieldType = array_shift($fields);
            $field = implode('.', $fields);
        }

        return [$fieldType, $field];
    }
}
