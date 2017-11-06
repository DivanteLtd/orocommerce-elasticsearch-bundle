<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */

namespace Divante\Bundle\ElasticsearchBundle\Engine;

use Divante\Bundle\ElasticsearchBundle\Event\BeforeSendMappingEvent;
use Elastica\Client;
use Elastica\Document;
use Elastica\Index;
use Elastica\Type;
use Elastica\Type\Mapping;
use Oro\Bundle\WebsiteSearchBundle\Engine\AbstractIndexer;
use Oro\Bundle\WebsiteSearchBundle\Engine\Context\ContextTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class ElasticsearchIndexer
 */
class ElasticsearchIndexer extends AbstractIndexer
{
    use ContextTrait;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var string
     */
    protected $indexName;

    /**
     * @inheritDoc
     */
    public function reindex($classOrClasses = null, array $context = [])
    {
        if (!$classOrClasses) {
            $this->resetIndex($classOrClasses, $context);
        }

        return parent::reindex($classOrClasses, $context);
    }

    /**
     * Resets data for one or several classes in index
     * Resets data for all indexed classes if $class is null
     *
     * @param string|string[] $class
     * @param array $context
     */
    public function resetIndex($class = null, array $context = [])
    {
        list($entityClassesToIndex, $websiteIdsToIndex) =
            $this->inputValidator->validateReindexRequest(
                $class,
                $context
            );

        $entityClassesToIndex = $this->getClassesForReindex($entityClassesToIndex);

        $index = $this->getIndex();
        $indexData = [];
        $index->create($indexData, true);


        foreach ($websiteIdsToIndex as $websiteId) {
            if (!$this->ensureWebsiteExists($websiteId)) {
                continue;
            }
            $websiteContext = $this->indexDataProvider->collectContextForWebsite($websiteId, $context);
            foreach ($entityClassesToIndex as $entityClass) {
                $type = $this->getType($entityClass, $websiteContext, $index);
                $mapping = new Mapping();
                $mapping->setType($type);

                $entityConfig = $this->mappingProvider->getEntityConfig($entityClass);

                $properties = [];
                foreach ($entityConfig['fields'] as $fieldName => $field) {
                    if ($field['type'] == 'text') {
                        $type = 'text';
                    } elseif ($field['type'] == 'decimal') {
                        $type = 'double';
                    } elseif ($field['type'] == 'datetime') {
                        $type = 'date';
                    } else {
                        $type = $field['type'];
                    }
                    $properties[$fieldName] = [
                        'type' => $type,
                    ];
                    if ($field['type'] == 'text') {
                        $properties[$fieldName]['fields'] = [
                            'keyword' => [
                                'type' => 'keyword'
                            ]
                        ];
                    }
                }

                $mapping->setProperties($properties);

                $event = new BeforeSendMappingEvent($mapping);
                $this->dispatcher->dispatch(BeforeSendMappingEvent::NAME, $event);

                $mapping->send();
            }
        }
    }

    /**
     * @return \Elastica\Index
     */
    protected function getIndex()
    {
        $index = $this->client->getIndex($this->getIndexName());

        return $index;
    }

    /**
     * @param $entityClass
     * @param $websiteContext
     * @param Index $index
     * @return Type
     */
    protected function getType($entityClass, $websiteContext, Index $index)
    {
        $entityAlias = $this->getEntityAlias($entityClass, $websiteContext);
        $type = $index->getType($entityAlias);

        return $type;
    }

    /**
     * @param Client $client
     */
    public function setClient(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param EventDispatcherInterface $dispatcher
     */
    public function setEventDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Delete one or several entities from search index
     *
     * @param object|array $entities
     * @param array $context
     *
     * @return bool
     */
    public function delete($entities, array $context = [])
    {
        $entities = is_array($entities) ? $entities : [$entities];

        $ids = [];

        $index = $this->getIndex();

        foreach ($entities as $entity) {
            $entityClass = $this->doctrineHelper->getEntityMetadata(get_class($entity))->name;
            $type = $this->getType($entityClass, $context, $index);
            $ids[$type->getName()][] = $entity->getId();
        }

        foreach ($ids as $type => $typeIds) {
            $this->client->deleteIds($typeIds, $index, $type);
        }
    }

    /**
     * Saves index data for batch of entities
     * @param string $entityClass
     * @param array $entitiesData
     * @param string $entityAliasTemp
     * @param array $context
     * @return int
     */
    protected function saveIndexData(
        $entityClass,
        array $entitiesData,
        $entityAliasTemp,
        array $context
    ) {
        $documents = [];
        $index = $this->getIndex();
        $type = $this->getType($entityClass, $context, $index);
        foreach ($entitiesData as $entityId => $entitiesDatum) {
            $data = [];
            foreach ($entitiesDatum as $typeData) {
                foreach ($typeData as $field => $value) {
                    $data[$field] = $value;
                }
            }
            $documents[] = new Document($entityId, $data, $type, $index);
        }
        $documentCount = count($documents);
        if ($documentCount) {
            $this->client->addDocuments($documents);
        }

        return $documentCount;
    }

    /**
     * Rename old index by aliases to new index
     * @param string $temporaryAlias
     * @param string $currentAlias
     * @throws \LogicException
     */
    protected function renameIndex($temporaryAlias, $currentAlias)
    {
    }

    /**
     * @return string
     */
    public function getIndexName()
    {
        return $this->indexName;
    }

    /**
     * @param string $indexName
     */
    public function setIndexName($indexName)
    {
        $this->indexName = $indexName;
    }
}
