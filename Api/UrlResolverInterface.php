<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Api;

/**
 * Batch URL resolver — warms all URL rewrites for a store in O(1) queries and
 * answers per-entity URL lookups from memory.
 *
 * Provides O(1) lookups for the canonical frontend URLs of products, categories,
 * and CMS pages, avoiding the per-entity DB round-trip that {@see \Magento\Catalog\Model\Product::getProductUrl()}
 * (and its category equivalent) trigger on a cold URL cache.
 *
 * Use case: a 5,000-product catalog rendering with `$product->getProductUrl()`
 * issues up to 5,000 url_rewrite queries; the resolver issues one bulk query and
 * resolves all 5,000 in memory.
 *
 * @api
 * @since 3.0.0
 */
interface UrlResolverInterface
{
    /**
     * Entity type constants.
     */
    public const ENTITY_PRODUCT  = 'product';
    public const ENTITY_CATEGORY = 'category';
    public const ENTITY_CMS_PAGE = 'cms-page';

    /**
     * Eagerly load every URL rewrite for the given store into memory.
     *
     * Idempotent — calling twice for the same store is a no-op. Reset between
     * stores via {@see reset()}.
     *
     * @param int $storeId
     */
    public function warmUp(int $storeId): void;

    /**
     * Resolve the absolute frontend URL for an entity.
     *
     * Falls back to the entity's native URL method if no rewrite is found.
     *
     * @param string $entityType  One of the ENTITY_* constants.
     * @param int    $entityId
     * @param int    $storeId
     * @return string|null  Absolute URL, or null if unresolvable.
     */
    public function resolve(string $entityType, int $entityId, int $storeId): ?string;

    /**
     * Return the absolute base URL for a store, including store code for non-default stores.
     *
     * @param int $storeId
     * @return string
     */
    public function getBaseUrl(int $storeId): string;

    /**
     * Clear the in-memory cache (call between stores).
     */
    public function reset(): void;
}
