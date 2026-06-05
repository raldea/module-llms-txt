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
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Emits the `## Categories` section for llms.txt / llms-full.txt.
 *
 * - COMPACT: `- [Name](url): short description`
 * - FULL:    each category gets a `### Name` subheading followed by its full description.
 *
 * @since 3.0.0
 */
class CategoryProvider extends AbstractProvider
{
    private const DESC_MAX_COMPACT = 200;
    private const DESC_MAX_FULL    = 4000;

    public function __construct(
        private readonly CollectionFactory $categoryCollectionFactory,
        private readonly SanitizerInterface $sanitizer,
        private readonly UrlResolverInterface $urlResolver,
        private readonly Config $config
    ) {
    }

    public function isApplicable(OutputContextInterface $context): bool
    {
        return $this->config->isCategoriesIncluded($context->getStore());
    }

    /**
     * @param OutputContextInterface $context
     * @return iterable
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function provide(OutputContextInterface $context): iterable
    {
        $store = $context->getStore();
        $storeId = (int) $store->getId();
        $rootCategoryId = (int) $store->getRootCategoryId();

        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect(['name', 'description', 'meta_description', 'url_key', 'path']);
        $collection->addAttributeToFilter('is_active', 1);
        $collection->addAttributeToFilter('path', ['like' => '1/' . $rootCategoryId . '/%']);
        $collection->setLoadProductCount(true);
        $collection->setOrder('position', 'ASC');

        $nameMap = [];
        foreach ($collection as $cat) {
            $nameMap[(int) $cat->getId()] = trim((string) $cat->getName());
        }

        $headerYielded = false;
        $count = 0;

        foreach ($collection as $category) {
            $name = trim((string) $category->getName());
            if ($name === '') {
                continue;
            }

            $url = $this->urlResolver->resolve(
                UrlResolverInterface::ENTITY_CATEGORY,
                (int) $category->getId(),
                $storeId
            );
            if ($url === null) {
                continue;
            }

            if (!$headerYielded) {
                yield "## Categories\n\n";
                $headerYielded = true;
            }

            $rawDesc = (string) ($category->getDescription() ?: $category->getMetaDescription());
            $desc = $this->sanitizer->sanitize(
                $rawDesc,
                $context,
                $this->isFullTxt($context) ? self::DESC_MAX_FULL : self::DESC_MAX_COMPACT
            );

            $path = $this->buildPath((string) $category->getPath(), $nameMap, $rootCategoryId);
            $productCount = (int) $category->getProductCount();

            yield "### {$name}\n";
            yield "Path: {$path}\n";
            yield "URL: {$url}\n";
            yield "Product Count: {$productCount}\n";
            if ($desc !== '') {
                yield "Description: {$desc}\n";
            }
            yield "\n";

            $count++;
        }

        if ($headerYielded) {
            yield "\n";
        }

        $context->setShared('category_count', $count);
    }

    /**
     * @param string $pathStr
     * @param array $nameMap
     * @param int $rootCategoryId
     * @return string
     */
    private function buildPath(string $pathStr, array $nameMap, int $rootCategoryId): string
    {
        $parts = [];
        foreach (explode('/', $pathStr) as $id) {
            $id = (int) $id;
            if ($id <= 1 || $id === $rootCategoryId) {
                continue;
            }
            $parts[] = $nameMap[$id] ?? $id;
        }
        return implode(' > ', $parts);
    }
}
