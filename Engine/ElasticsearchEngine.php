<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */

namespace Divante\Bundle\ElasticsearchBundle\Engine;

use Doctrine\Common\Collections\Expr\ExpressionVisitor;
use Elastica\Client;
use Elastica\Index;
use Elastica\Query\BoolQuery;
use Elastica\Query\Prefix;
use Oro\Bundle\SearchBundle\Query\Query;
use Oro\Bundle\SearchBundle\Query\Result;
use Oro\Bundle\WebsiteSearchBundle\Engine\AbstractEngine;
use Oro\Bundle\SearchBundle\Query\Result\Item;
use Oro\Bundle\WebsiteSearchBundle\Engine\Mapper;

/**
 * Class ElasticsearchEngine
 */
class ElasticsearchEngine extends AbstractEngine
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Index
     */
    protected $index;

    /**
     * @var Mapper
     */
    protected $mapper;

    /**
     * @var ExpressionVisitor
     */
    protected $expressionVisitor;

    /**
     * @param Client $client
     */
    public function setClient(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param ExpressionVisitor $expressionVisitor
     */
    public function setExpressionVisitor(ExpressionVisitor $expressionVisitor)
    {
        $this->expressionVisitor = $expressionVisitor;
    }

    /**
     * @param Index $index
     */
    public function setIndex(Index $index)
    {
        $this->index = $index;
    }

    /**
     * @param Mapper $mapper
     */
    public function setMapper(Mapper $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * {@inheritdoc}
     */
    protected function doSearch(Query $query, array $context = [])
    {
        $from = $query->getFrom();
        $from = reset($from);

        $type = $this->index->getType($from);

        $criteria = $query->getCriteria();
        $esExpression = $this->expressionVisitor->dispatch($criteria->getWhereExpression());
        $esQuery = new \Elastica\Query($esExpression);
        $esQuery->setSize($criteria->getMaxResults());
        $this->addSort($criteria, $esQuery);
        if ($criteria->getFirstResult()) {
            $esQuery->setFrom($criteria->getFirstResult());
        }
        $this->checkPrefixRescore($esExpression, $esQuery);
        $searchResults = $type->search($esQuery);
        $results = [];

        foreach ($searchResults as $searchResult) {
            $item = $searchResult->getType();

            $entityName = 'Oro\Bundle\ProductBundle\Entity\Product';
            $results[] = new Item(
                $entityName,
                $searchResult->getId(),
                null,
                null,
                $this->mapper->mapSelectedData($query, $searchResult->getData()),
                $this->mappingProvider->getEntityConfig($entityName)
            );
        }

        return new Result($query, $results, $searchResults->getTotalHits());
    }

    /**
     * @param $criteria
     * @param $esQuery
     */
    protected function addSort($criteria, $esQuery)
    {
        foreach ($criteria->getOrderings() as $field => $order) {
            $field = explode('.', $field);
            if ($field[0] == 'text') {
                $field = $field[1].'.keyword';
            } else {
                $field = $field[1];
            }

            $esQuery->addSort(
                [
                    $field => [
                        'order' => strtolower($order),
                    ],
                ]
            );
        }
    }

    /**
     * @param BoolQuery $esExpression
     * @param \Elastica\Query $esQuery
     */
    protected function checkPrefixRescore(BoolQuery $esExpression, \Elastica\Query $esQuery)
    {
        $prefixes = [];
        $this->getPrefixes($esExpression, $prefixes);

        if (count($prefixes) == 0) {
            return;
        }

        $rescore = [];

        foreach ($prefixes as $prefix) {
            $rescore[] = [
                'window_size' => $esQuery->getParam('size'),
                'query' => [
                    'rescore_query' => [
                        'term' => $prefix
                    ]
                ]
            ];
        }

        $esQuery->setRescore($rescore);
    }

    /**
     * @param $esExpression
     * @param $data
     */
    protected function getPrefixes($esExpression, &$data)
    {
        if ($esExpression instanceof BoolQuery) {
            foreach ($esExpression->getParams() as $paramKey => $paramValue) {
                foreach ($paramValue as $item) {
                    if ($item instanceof BoolQuery) {
                        $this->getPrefixes($item, $data);
                    } else {
                        if ($item instanceof Prefix) {
                            $data[] = $item->getParams();
                        }
                    }
                }
            }
        }
    }
}
