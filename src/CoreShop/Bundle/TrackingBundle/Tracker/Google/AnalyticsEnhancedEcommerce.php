<?php
/**
 * CoreShop.
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015-2020 Dominik Pfaffenbauer (https://www.pfaffenbauer.at)
 * @license    https://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 */

declare(strict_types=1);

namespace CoreShop\Bundle\TrackingBundle\Tracker\Google;

use CoreShop\Bundle\TrackingBundle\Resolver\ConfigResolverInterface;
use CoreShop\Bundle\TrackingBundle\Tracker\AbstractEcommerceTracker;
use Pimcore\Analytics\Google\Tracker as GoogleTracker;
use Pimcore\Analytics\TrackerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AnalyticsEnhancedEcommerce extends AbstractEcommerceTracker
{
    /**
     * @var TrackerInterface
     */
    public $tracker;

    /**
     * @var ConfigResolverInterface
     */
    public $config;

    /**
     * Dependencies to include before any tracking actions.
     *
     * @var array
     */
    protected $dependencies = ['ec'];

    /**
     * @var bool
     */
    protected $dependenciesIncluded = false;

    /**
     * {@inheritdoc}
     */
    public function setTracker(TrackerInterface $tracker): void
    {
        $this->tracker = $tracker;
    }

    /**
     * {@inheritdoc}
     */
    public function setConfigResolver(ConfigResolverInterface $config): void
    {
        $this->config = $config;
    }

    /**
     * @param OptionsResolver $resolver
     */
    protected function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'template_prefix' => '@CoreShopTracking/Tracking/analytics/enhanced',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function trackProduct($product): void
    {
        if ($this->isGlobalSiteTagMode() === true) {
            return;
        }

        $this->ensureDependencies($product['currency']);

        $parameters['productData'] = $this->transformProductAction($product);

        unset($parameters['productData']['price']);

        $result = $this->renderTemplate('product_view', $parameters);
        $this->tracker->addCodePart($result, GoogleTracker::BLOCK_BEFORE_TRACK);
    }

    /**
     * {@inheritdoc}
     */
    public function trackProductImpression($product): void
    {
        if ($this->isGlobalSiteTagMode() === true) {
            return;
        }

        $this->ensureDependencies($product['currency']);

        $parameters = [
            'productData' => $this->transformProductAction($product),
        ];

        $result = $this->renderTemplate('product_impression', $parameters);
        $this->tracker->addCodePart($result, GoogleTracker::BLOCK_BEFORE_TRACK);
    }

    /**
     * {@inheritdoc}
     */
    public function trackCartAdd($cart, $product, float $quantity = 1.0): void
    {
        if ($this->isGlobalSiteTagMode() === true) {
            return;
        }

        $this->ensureDependencies($cart['currency']);
        $this->trackCartAction($product, 'add', $quantity);
    }

    /**
     * {@inheritdoc}
     */
    public function trackCartRemove($cart, $product, float $quantity = 1.0): void
    {
        if ($this->isGlobalSiteTagMode() === true) {
            return;
        }

        $this->ensureDependencies($cart['currency']);
        $this->trackCartAction($product, 'remove', $quantity);
    }

    /**
     * {@inheritdoc}
     */
    public function trackCheckoutStep($cart, $stepIdentifier = null, bool $isFirstStep = false, $checkoutOption = null): void
    {
        if ($this->isGlobalSiteTagMode() === true) {
            return;
        }

        $this->ensureDependencies($cart['currency']);

        $parameters = [];
        $parameters['items'] = $cart['items'];
        $parameters['calls'] = [];
        $parameters['actionData'] = [];

        if (null !== $stepIdentifier || null !== $checkoutOption) {
            $actionData = ['step' => $stepIdentifier];
            if (null !== $checkoutOption) {
                $actionData['option'] = $checkoutOption;
            }

            $parameters['actionData'] = $actionData;
        }

        $result = $this->renderTemplate('checkout', $parameters);
        $this->tracker->addCodePart($result, GoogleTracker::BLOCK_BEFORE_TRACK);
    }

    /**
     * {@inheritdoc}
     */
    public function trackCheckoutComplete($order): void
    {
        if ($this->isGlobalSiteTagMode() === true) {
            return;
        }

        $this->ensureDependencies($order['currency']);

        $orderData = $this->transformOrder($order);
        $items = $order['items'];

        $parameters = [];
        $parameters['order'] = $orderData;
        $parameters['items'] = $items;

        $calls = [];
        foreach ($items as $item) {
            $calls[] = $this->transformProductAction($item);
        }

        $parameters['calls'] = $calls;

        $result = $this->renderTemplate('checkout_complete', $parameters);
        $this->tracker->addCodePart($result, GoogleTracker::BLOCK_BEFORE_TRACK);
    }

    /**
     * {@inheritdoc}
     */
    protected function trackCartAction($product, $action, int $quantity = 1): void
    {
        if ($this->isGlobalSiteTagMode() === true) {
            return;
        }

        $this->ensureDependencies($product['currency']);

        $product = $this->transformProductAction($product);
        $product['quantity'] = $quantity;

        $parameters = [];
        $parameters['productData'] = $product;
        $parameters['action'] = $action;

        $result = $this->renderTemplate('product_action', $parameters);
        $this->tracker->addCodePart($result, GoogleTracker::BLOCK_BEFORE_TRACK);
    }

    /**
     * Transform ActionData into classic analytics data array.
     *
     * @param array $actionData
     *
     * @return array
     */
    protected function transformOrder(array $actionData): array
    {
        return [
            'id' => $actionData['id'],
            'affiliation' => $actionData['affiliation'] ?: '',
            'total' => $actionData['total'],
            'tax' => $actionData['totalTax'],
            'shipping' => $actionData['shipping'],
            'currency' => $actionData['currency'],
        ];
    }

    /**
     * Transform product action into enhanced data object.
     *
     * @param array $item
     *
     * @return array
     */
    protected function transformProductAction(array $item): array
    {
        return $this->filterNullValues([
            'id' => $item['id'],
            'name' => $item['name'],
            'category' => $item['category'],
            'brand' => $item['brand'],
            'variant' => $item['variant'],
            'price' => round($item['price'], 2),
            'quantity' => $item['quantity'] ?: 1,
            'position' => $item['position'],
            'currency' => $item['currency'],
        ]);
    }

    /**
     * @param array $items
     *
     * @return array
     */
    protected function buildCheckoutCalls(array $items): array
    {
        $calls = [];
        foreach ($items as $item) {
            $calls[] = $this->transformProductAction($item);
        }

        return $calls;
    }

    /**
     * Makes sure dependencies are included once before any call.
     */
    protected function ensureDependencies($currency): void
    {
        if ($this->dependenciesIncluded || empty($this->dependencies)) {
            return;
        }

        $result = $this->renderTemplate('dependencies', [
            'dependencies' => $this->dependencies,
            'currency' => $currency,
        ]);

        $this->tracker->addCodePart($result, GoogleTracker::BLOCK_BEFORE_TRACK);

        $this->dependenciesIncluded = true;
    }

    /**
     * @return bool
     */
    protected function isGlobalSiteTagMode(): bool
    {
        $config = $this->config->getGoogleConfig();
        if ($config === false) {
            return false;
        }

        return (bool) $config->get('gtagcode');
    }
}
