<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */

namespace Divante\Bundle\ElasticsearchBundle\Event;

use Elastica\Type\Mapping;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class BeforeSendMappingEvent
 */
class BeforeSendMappingEvent extends Event
{
    const NAME = 'divante_elasticsearch.before_send_mapping';

    /**
     * @var Mapping
     */
    protected $mapping;

    /**
     * BeforeSendMappingEvent constructor.
     * @param Mapping $mapping
     */
    public function __construct(Mapping $mapping)
    {
        $this->mapping = $mapping;
    }

    /**
     * @return Mapping
     */
    public function getMapping()
    {
        return $this->mapping;
    }

    /**
     * @param Mapping $mapping
     */
    public function setMapping(Mapping $mapping)
    {
        $this->mapping = $mapping;
    }
}
