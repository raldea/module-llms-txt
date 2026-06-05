<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Provider\Llms;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\SanitizerInterface;
use Angeo\LlmsTxt\Api\UrlResolverInterface;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Provider\AbstractProvider;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;

/**
 * Emits the products section (`## Products` or under `## Optional`) for llms.txt
 * and llms-full.txt.
 *
 * Streaming: yields one line at a time. Uses entity_id-cursor pagination so new
 * products inserted mid-run cannot cause duplicates or skips.
 *
 * Spec-compliance: when products_under_optional = yes (default per llmstxt.org),
 * products go under `## Optional` so context-budget-constrained LLMs can drop
 * them without losing the more semantically-important categories and pages.
 *
 * @since 3.0.0
 */
class ProductProvider extends AbstractProvider
{
    private const DESC_MAX_COMPACT = 500;
    private const DESC_MAX_FULL    = 5000;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly SanitizerInterface $sanitizer,
        private readonly UrlResolverInterface $urlResolver,
        private readonly StockRegistryInterface $stockRegistry,
        private readonly Config $config
    ) {
    }

    public function isApplicable(OutputContextInterface $context): bool
    {
        return $this->config->isProductsIncluded($context->getStore());
    }

    public function provide(OutputContextInterface $context): iterable
    {
        $store    = $context->getStore();
        $storeId  = (int) $store->getId();
        $pageSize = $this->config->getCollectionPageSize($store);
        $limit    = $this->config->getProductLimit($store);
        $excludeOos = $this->config->isExcludeOutOfStock($store);
        $lastId   = 0;
        $emitted  = 0;
        $headerYielded = false;

        $underOptional = $this->config->areProductsUnderOptional($store)
            && !$this->isFullTxt($context); // full file ignores ## Optional — every section is verbose.

        while (true) {
            $collection = $this->collectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addStoreFilter($storeId);
            $collection->addAttributeToSelect(['name', 'price', 'short_description', 'description', 'sku', 'url_key']);
            $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
            $collection->addAttributeToFilter('visibility', [
                'in' => [
                    Visibility::VISIBILITY_IN_CATALOG,
                    Visibility::VISIBILITY_IN_SEARCH,
                    Visibility::VISIBILITY_BOTH,
                ],
            ]);
            $collection->addAttributeToFilter('entity_id', ['gt' => $lastId]);
            $collection->setOrder('entity_id', 'ASC');
            $collection->setPageSize($pageSize);
            $collection->setCurPage(1);

            $hasRows = false;
            foreach ($collection as $product) {
                $hasRows = true;
                $lastId  = (int) $product->getId();

                if ($excludeOos && !$this->isInStock($product, $storeId)) {
                    continue;
                }

                $url = $this->urlResolver->resolve(
                    UrlResolverInterface::ENTITY_PRODUCT,
                    (int) $product->getId(),
                    $storeId
                );
                if ($url === null) {
                    continue;
                }

                if (!$headerYielded) {
                    if ($underOptional) {
                        yield "## Optional\n\n";
                        yield "### Products\n\n";
                    } else {
                        yield "## Products\n\n";
                    }
                    $headerYielded = true;
                }

                yield $this->renderProduct($product, $url, $context);

                $emitted++;
                if ($limit > 0 && $emitted >= $limit) {
                    if ($headerYielded) {
                        yield "\n";
                    }
                    $context->setShared('product_count', $emitted);
                    return;
                }
            }

            $collection->clear();

            if (!$hasRows) {
                break;
            }
        }

        if ($headerYielded) {
            yield "\n";
        }
        $context->setShared('product_count', $emitted);
    }

    private function renderProduct(
        \Magento\Catalog\Model\Product $product,
        string $url,
        OutputContextInterface $context
    ): string {
        $name = trim((string) $product->getName());
        $sku = trim((string) $product->getSku());
        $price = $this->resolvePrice($product, $context);
        $inStock = $this->isInStock($product, (int) $context->getStore()->getId());
        $maxLen = $this->isFullTxt($context) ? self::DESC_MAX_FULL : self::DESC_MAX_COMPACT;
        $rawDesc = (string) ($product->getShortDescription() ?: $product->getDescription());
        $desc = $this->sanitizer->sanitize($rawDesc, $context, $maxLen);

        $out = "### {$name}\n";
        if ($sku !== '') {
            $out .= "SKU: {$sku}\n";
        }
        $out .= "URL: {$url}\n";
        if ($price !== null) {
            $out .= sprintf("Price: %s %s\n", $price, $context->getCurrencyCode());
        }
        $out .= 'In Stock: ' . ($inStock ? 'Yes' : 'No') . "\n";
        if ($desc !== '') {
            $out .= "Description: {$desc}\n";
        }
        $out .= "\n";

        return $out;
    }

    private function resolvePrice(
        \Magento\Catalog\Model\Product $product,
        OutputContextInterface $context
    ): ?string {
        // Use final_price so special prices in window are reflected.
        $product->setCustomerGroupId($context->getCustomerGroupId());
        $price = $product->getFinalPrice();
        if ($price === null || (float) $price <= 0.0) {
            return null;
        }
        return number_format((float) $price, 2, '.', '');
    }

    private function isInStock(\Magento\Catalog\Model\Product $product, int $storeId): bool
    {
        try {
            $status = $this->stockRegistry->getProductStockStatus(
                (int) $product->getId(),
                $product->getStore() ? (int) $product->getStore()->getWebsiteId() : null
            );
            return (int) $status === 1;
        } catch (\Throwable) {
            // If stock isn't resolvable, default to "in stock" — better to over-include than under-include.
            return true;
        }
    }
}
