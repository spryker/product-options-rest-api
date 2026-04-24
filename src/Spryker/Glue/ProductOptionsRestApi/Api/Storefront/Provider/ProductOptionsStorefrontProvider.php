<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Glue\ProductOptionsRestApi\Api\Storefront\Provider;

use Generated\Api\Storefront\ProductOptionsStorefrontResource;
use Generated\Shared\Transfer\ProductAbstractOptionStorageTransfer;
use Generated\Shared\Transfer\ProductOptionGroupStorageTransfer;
use Generated\Shared\Transfer\ProductOptionValueStorageTransfer;
use Spryker\ApiPlatform\State\Provider\AbstractStorefrontProvider;
use Spryker\Client\Currency\CurrencyClientInterface;
use Spryker\Client\GlossaryStorage\GlossaryStorageClientInterface;
use Spryker\Client\ProductOptionStorage\ProductOptionStorageClientInterface;
use Spryker\Client\ProductStorage\ProductStorageClientInterface;

class ProductOptionsStorefrontProvider extends AbstractStorefrontProvider
{
    protected const string MAPPING_TYPE_SKU = 'sku';

    protected const string URI_VAR_ABSTRACT_SKU = 'abstractProductSku';

    public function __construct(
        protected ProductStorageClientInterface $productStorageClient,
        protected ProductOptionStorageClientInterface $productOptionStorageClient,
        protected GlossaryStorageClientInterface $glossaryStorageClient,
        protected CurrencyClientInterface $currencyClient,
    ) {
    }

    /**
     * @return array<\Generated\Api\Storefront\ProductOptionsStorefrontResource>
     */
    protected function provideCollection(): array
    {
        if (!$this->hasUriVariable(static::URI_VAR_ABSTRACT_SKU)) {
            return [];
        }

        $sku = (string)$this->getUriVariable(static::URI_VAR_ABSTRACT_SKU);

        if ($sku === '') {
            return [];
        }

        $localeName = $this->getLocale()->getLocaleNameOrFail();
        $productAbstractIds = $this->productStorageClient->getBulkProductAbstractIdsByMapping(
            static::MAPPING_TYPE_SKU,
            [$sku],
            $localeName,
        );

        if ($productAbstractIds === []) {
            return [];
        }

        $productAbstractOptionStorageTransfers = $this->productOptionStorageClient->getBulkProductOptions(
            array_values($productAbstractIds),
        );

        if ($productAbstractOptionStorageTransfers === []) {
            return [];
        }

        $this->applyTranslations($productAbstractOptionStorageTransfers, $localeName);
        $currencyIsoCode = $this->currencyClient->getCurrent()->getCode();

        $resources = [];
        foreach ($productAbstractOptionStorageTransfers as $productAbstractOptionStorageTransfer) {
            $resources = array_merge(
                $resources,
                $this->buildResourcesFromOptionStorage($productAbstractOptionStorageTransfer, $currencyIsoCode),
            );
        }

        return $resources;
    }

    /**
     * @return array<\Generated\Api\Storefront\ProductOptionsStorefrontResource>
     */
    protected function buildResourcesFromOptionStorage(
        ProductAbstractOptionStorageTransfer $productAbstractOptionStorageTransfer,
        string $currencyIsoCode
    ): array {
        $resources = [];
        foreach ($productAbstractOptionStorageTransfer->getProductOptionGroups() as $productOptionGroupStorageTransfer) {
            $resources = array_merge(
                $resources,
                $this->buildResourcesFromOptionGroup($productOptionGroupStorageTransfer, $currencyIsoCode),
            );
        }

        return $resources;
    }

    /**
     * @return array<\Generated\Api\Storefront\ProductOptionsStorefrontResource>
     */
    protected function buildResourcesFromOptionGroup(
        ProductOptionGroupStorageTransfer $productOptionGroupStorageTransfer,
        string $currencyIsoCode
    ): array {
        $resources = [];
        foreach ($productOptionGroupStorageTransfer->getProductOptionValues() as $productOptionValueStorageTransfer) {
            $resources[] = $this->mapToResource(
                $productOptionGroupStorageTransfer,
                $productOptionValueStorageTransfer,
                $currencyIsoCode,
            );
        }

        return $resources;
    }

    protected function mapToResource(
        ProductOptionGroupStorageTransfer $group,
        ProductOptionValueStorageTransfer $value,
        string $currencyIsoCode
    ): ProductOptionsStorefrontResource {
        $resource = new ProductOptionsStorefrontResource();
        $resource->sku = $value->getSku();
        $resource->optionGroupName = $group->getName();
        $resource->optionName = $value->getValue();
        $resource->price = $value->getPrice();
        $resource->currencyIsoCode = $currencyIsoCode;

        return $resource;
    }

    /**
     * @param array<\Generated\Shared\Transfer\ProductAbstractOptionStorageTransfer> $productAbstractOptionStorageTransfers
     */
    protected function applyTranslations(array $productAbstractOptionStorageTransfers, string $localeName): void
    {
        $glossaryKeys = $this->collectGlossaryKeys($productAbstractOptionStorageTransfers);

        if ($glossaryKeys === []) {
            return;
        }

        $translations = $this->glossaryStorageClient->translateBulk($glossaryKeys, $localeName);

        foreach ($productAbstractOptionStorageTransfers as $storageTransfer) {
            $this->applyTranslationsToStorageTransfer($storageTransfer, $translations);
        }
    }

    /**
     * @param array<\Generated\Shared\Transfer\ProductAbstractOptionStorageTransfer> $productAbstractOptionStorageTransfers
     *
     * @return array<string>
     */
    protected function collectGlossaryKeys(array $productAbstractOptionStorageTransfers): array
    {
        $glossaryKeys = [];

        foreach ($productAbstractOptionStorageTransfers as $storageTransfer) {
            foreach ($storageTransfer->getProductOptionGroups() as $group) {
                $glossaryKeys = array_merge($glossaryKeys, $this->collectGlossaryKeysFromOptionGroup($group));
            }
        }

        return array_values(array_unique($glossaryKeys));
    }

    /**
     * @return array<string>
     */
    protected function collectGlossaryKeysFromOptionGroup(ProductOptionGroupStorageTransfer $group): array
    {
        $glossaryKeys = [];

        if ($group->getName() !== null) {
            $glossaryKeys[] = $group->getName();
        }

        foreach ($group->getProductOptionValues() as $value) {
            if ($value->getValue() !== null) {
                $glossaryKeys[] = $value->getValue();
            }
        }

        return $glossaryKeys;
    }

    /**
     * @param array<string, string> $translations
     */
    protected function applyTranslationsToStorageTransfer(
        ProductAbstractOptionStorageTransfer $storageTransfer,
        array $translations
    ): void {
        foreach ($storageTransfer->getProductOptionGroups() as $group) {
            $this->applyTranslationsToOptionGroup($group, $translations);
        }
    }

    /**
     * @param array<string, string> $translations
     */
    protected function applyTranslationsToOptionGroup(
        ProductOptionGroupStorageTransfer $group,
        array $translations
    ): void {
        if ($group->getName() !== null && isset($translations[$group->getName()])) {
            $group->setName($translations[$group->getName()]);
        }

        foreach ($group->getProductOptionValues() as $value) {
            if ($value->getValue() !== null && isset($translations[$value->getValue()])) {
                $value->setValue($translations[$value->getValue()]);
            }
        }
    }
}
