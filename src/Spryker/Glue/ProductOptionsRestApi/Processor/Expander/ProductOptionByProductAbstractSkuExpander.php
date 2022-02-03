<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Glue\ProductOptionsRestApi\Processor\Expander;

use Spryker\Glue\GlueApplication\Rest\Request\Data\RestRequestInterface;
use Spryker\Glue\ProductOptionsRestApi\Processor\Reader\ProductOptionStorageReaderInterface;

class ProductOptionByProductAbstractSkuExpander implements ProductOptionByProductAbstractSkuExpanderInterface
{
    /**
     * @var \Spryker\Glue\ProductOptionsRestApi\Processor\Reader\ProductOptionStorageReaderInterface
     */
    protected $productOptionStorageReader;

    /**
     * @param \Spryker\Glue\ProductOptionsRestApi\Processor\Reader\ProductOptionStorageReaderInterface $productOptionStorageReader
     */
    public function __construct(ProductOptionStorageReaderInterface $productOptionStorageReader)
    {
        $this->productOptionStorageReader = $productOptionStorageReader;
    }

    /**
     * @param array<\Spryker\Glue\GlueApplication\Rest\JsonApi\RestResourceInterface> $resources
     * @param \Spryker\Glue\GlueApplication\Rest\Request\Data\RestRequestInterface $restRequest
     *
     * @return void
     */
    public function addResourceRelationships(array $resources, RestRequestInterface $restRequest): void
    {
        $productAbstractSkus = [];
        foreach ($resources as $restResource) {
            $productAbstractSkus[] = $restResource->getId();
        }

        $productOptionRestResources = $this->productOptionStorageReader->getProductOptionsByProductAbstractSkus(
            $productAbstractSkus,
            $restRequest->getMetadata()->getLocale(),
            $restRequest->getSort(),
        );
        foreach ($resources as $restResource) {
            if (!isset($productOptionRestResources[$restResource->getId()])) {
                continue;
            }

            foreach ($productOptionRestResources[$restResource->getId()] as $productOptionRestResource) {
                $restResource->addRelationship($productOptionRestResource);
            }
        }
    }
}
