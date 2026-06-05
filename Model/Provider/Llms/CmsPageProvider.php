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
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;

/**
 * Emits the `## Pages` section for llms.txt / llms-full.txt.
 *
 * CMS pages get full sanitization (Page Builder, CMS directives) because the
 * source content is typically the highest-quality AEO signal a store has.
 *
 * @since 3.0.0
 */
class CmsPageProvider extends AbstractProvider
{
    private const EXCERPT_MAX_COMPACT = 500;
    private const CONTENT_MAX_FULL    = 16000;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly SanitizerInterface $sanitizer,
        private readonly UrlResolverInterface $urlResolver,
        private readonly Config $config
    ) {
    }

    public function isApplicable(OutputContextInterface $context): bool
    {
        return $this->config->isCmsIncluded($context->getStore());
    }

    public function provide(OutputContextInterface $context): iterable
    {
        $store    = $context->getStore();
        $storeId  = (int) $store->getId();
        $excluded = $this->config->getCmsExcludedIdentifiers($store);
        $baseUrl  = $context->getBaseUrl();

        $pages = $this->collectionFactory->create();
        $pages->addStoreFilter($storeId);
        $pages->addFieldToFilter('is_active', 1);
        if ($excluded !== []) {
            $pages->addFieldToFilter('identifier', ['nin' => $excluded]);
        }
        $pages->addFieldToSelect(['title', 'identifier', 'content', 'content_heading', 'meta_description']);
        $pages->setOrder('sort_order', 'ASC');

        $headerYielded = false;
        $count = 0;

        foreach ($pages as $page) {
            $title = trim((string) $page->getTitle());
            if ($title === '') {
                continue;
            }

            // Prefer URL rewrite; fall back to baseUrl + identifier.
            $url = $this->urlResolver->resolve(
                UrlResolverInterface::ENTITY_CMS_PAGE,
                (int) $page->getId(),
                $storeId
            ) ?? sprintf('%s/%s', $baseUrl, $page->getIdentifier());

            if (!$headerYielded) {
                yield "## Pages\n\n";
                $headerYielded = true;
            }

            $rawContent = (string) $page->getContent();
            $maxLength = $this->isFullTxt($context) ? self::CONTENT_MAX_FULL : self::EXCERPT_MAX_COMPACT;
            $content = $this->sanitizer->sanitize(
                (string) ($rawContent ?: $page->getMetaDescription()),
                $context,
                $maxLength
            );

            yield "### {$title}\n";
            yield "URL: {$url}\n";
            if ($content !== '') {
                yield "Content: {$content}\n";
            }
            yield "\n";

            $count++;
        }

        if ($headerYielded) {
            yield "\n";
        }

        $context->setShared('cms_count', $count);
    }
}
