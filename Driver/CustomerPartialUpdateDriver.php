<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */

namespace Divante\Bundle\ElasticsearchBundle\Driver;

use Elastica\Bulk;
use Elastica\Client;
use Elastica\Document;
use Elastica\Index;
use Elastica\Request;
use Elastica\Type;
use Oro\Bundle\CustomerBundle\Entity\Customer;
use Oro\Bundle\VisibilityBundle\Driver\AbstractCustomerPartialUpdateDriver;
use Oro\Bundle\VisibilityBundle\Entity\VisibilityResolved\BaseVisibilityResolved;
use Oro\Bundle\WebsiteSearchBundle\Entity\IndexInteger;

/**
 * Class CustomerPartialUpdateDriver
 */
class CustomerPartialUpdateDriver extends AbstractCustomerPartialUpdateDriver
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
     * @var Type
     */
    protected $type;

    /**
     * {@inheritdoc}
     */
    protected function addCustomerVisibility(
        array $productIds,
        $productAlias,
        $customerVisibilityFieldName
    ) {
        $bulk = new Bulk($this->client);
        $bulk->setIndex($this->index);
        $bulk->setType($this->type);
        foreach ($productIds as $productId) {
            $document = new Document(
                $productId,
                [$customerVisibilityFieldName => BaseVisibilityResolved::VISIBILITY_VISIBLE],
                $this->type,
                $this->index
            );
            $action = new Bulk\Action\UpdateDocument($document);
            $bulk->addAction($action);
        }
        $bulk->send();
    }

    /**
     * {@inheritdoc}
     */
    public function createCustomerWithoutCustomerGroupVisibility(Customer $customer)
    {
        $fieldName = $this->getCustomerVisibilityFieldName($customer);
        $data = <<<DATA
{
  "script": {
    "inline": "ctx._source.__FIELD__ = ctx._source.visibility_new"
  },
  "query": {
    "bool": {
      "filter": {
        "script": {
          "script": "doc['is_visible_by_default'].value != doc['visibility_new'].value"
        }
      }
    }
  }
}
DATA;
        $data = str_replace('__FIELD__', $fieldName, $data);

        $this->client->request(
            sprintf('%s/%s/_update_by_query?conflicts=proceed', $this->index->getName(), $this->type->getName()),
            Request::POST,
            $data
        );
    }

    /**
     * {@inheritdoc}
     */
    public function deleteCustomerVisibility(Customer $customer)
    {
        $fieldName = $this->getCustomerVisibilityFieldName($customer);
        $data = <<<DATA
{
  "script": {
    "inline": "ctx._source.remove(\"__FIELD__\")"
  },
  "query": {
    "bool": {
      "must": [
        {
          "exists": {
            "field": "__FIELD__"
          }
        }
      ]
    }
  }
}
DATA;
        $data = str_replace('__FIELD__', $fieldName, $data);

        $this->client->request(
            sprintf('%s/%s/_update_by_query?conflicts=proceed', $this->index->getName(), $this->type->getName()),
            Request::POST,
            $data
        );
    }

    /**
     * @param Client $client
     */
    public function setClient(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param Index $index
     */
    public function setIndex(Index $index)
    {
        $this->index = $index;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $this->index->getType($type);
    }
}
