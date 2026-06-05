<?php declare(strict_types=1);
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */

namespace Angeo\LlmsTxt\Model\Provider\Llms;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\UrlResolverInterface;
use Angeo\LlmsTxt\Model\Provider\AbstractProvider;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Appends an "## Other Available Stores" section listing sibling store views
 * on the same website, each linking to their own llms.txt.
 *
 * Only stores belonging to the same website as the current store are listed.
 * The current store is excluded. Inactive stores are skipped.
 */
class OtherStoresProvider extends AbstractProvider
{
    private const XML_PATH_DEFAULT_COUNTRY = 'general/country/default';

    /**
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param CountryFactory $countryFactory
     * @param UrlResolverInterface $urlResolver
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CountryFactory $countryFactory,
        private readonly UrlResolverInterface $urlResolver
    ) {
    }

    /**
     * @param OutputContextInterface $context
     * @return iterable
     */
    public function provide(OutputContextInterface $context): iterable
    {
        $currentStore = $context->getStore();
        $websiteId = (int) $currentStore->getWebsiteId();

        $siblings = $this->getSiblingStores((int) $currentStore->getId(), $websiteId);
        if (empty($siblings)) {
            return;
        }

        yield "## Other Available Stores\n\n";

        foreach ($siblings as $store) {
            $storeId = (int) $store->getId();
            $storeName = $store->getName();
            $websiteName = $store->getWebsite()->getName();
            $countryCode = (string) $this->scopeConfig->getValue(
                self::XML_PATH_DEFAULT_COUNTRY,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            $country = $this->countryFactory->create()->loadByCode($countryCode)->getName() ?: $countryCode;
            $url = $this->urlResolver->getBaseUrl($storeId) . '/llms.txt';

            yield sprintf("- [%s - %s](%s)\n", $websiteName, $storeName, $url);
        }

        yield "\n";
    }

    /**
     * @param int $currentStoreId
     * @param int $websiteId
     * @return array
     */
    private function getSiblingStores(int $currentStoreId, int $websiteId): array
    {
        $siblings = [];
        
        foreach ($this->storeManager->getStores() as $store) {
            if ((int) $store->getId() === $currentStoreId
                || (int) $store->getWebsiteId() !== $websiteId
                || !$store->isActive()
                || $this->scopeConfig->isSetFlag('angeo_llms/general/exclude_store', ScopeInterface::SCOPE_STORE, $store->getId())) {
                continue;
            }

            $siblings[] = $store;
        }
        return $siblings;
    }
}
