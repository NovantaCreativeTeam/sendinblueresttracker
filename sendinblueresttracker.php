<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

$autoloadPath = dirname(__FILE__) . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

class SendinblueRestTracker extends Module
{
    public function __construct()
    {
        $this->name = 'sendinblueresttracker';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.1';
        $this->author = 'Novanta';

        parent::__construct();

        $this->displayName = $this->trans('Sendinblue REST tracker', [], 'Modules.Sendinblueresttracker.Admin');
        $this->description = $this->trans('Adding Sendinblue Tracker to track some custom event', [], 'Modules.Sendinblueresttracker.Admin');
        $this->ps_versions_compliancy = [
            'min' => '1.7.6.0',
            'max' => _PS_VERSION_,
        ];
    }

    public function install()
    {
        // if(!$this->get('prestashop.module.manager')->isInstalled('sendinblue')) {
        //     return false;
        // }

        return parent::install()
            && $this->registerHook('actionOrderHistoryAddAfter')
            && $this->registerHook('displayHeader');
    }

    public function hookActionOrderHistoryAddAfter(array $params)
    {
        $order_history = $params['order_history'];

        $order = new Order($order_history->id_order);
        if ($order) {
            $customer = $order->getCustomer();
            $eventdata = $this->get('Novanta\Sendinblue\Service\RestTracker')->getOrderEventData($order);
            $this->get('Novanta\Sendinblue\Service\RestTracker')->trackEvent($customer, 'order_status_update', $eventdata);
        }
    }

    public function hookDisplayHeader(array $params)
    {
        if ($this->context->controller instanceof OrderController) {
            if ($this->isFirstCheckoutStep()) {
                $customer = $this->context->customer;

                $eventdata = $this->get('Novanta\Sendinblue\Service\RestTracker')->getCartEventData($this->context->cart);
                $this->get('Novanta\Sendinblue\Service\RestTracker')->trackEvent($customer, 'initiate_checkout', $eventdata);
            }
        }
    }

    private function isFirstCheckoutStep()
    {
        if (!$this->context->controller instanceof OrderController) {
            return false;
        }

        $checkoutSteps = $this->getAllOrderSteps();

        /* Get the checkoutPaymentKey from the $checkoutSteps array */
        foreach ($checkoutSteps as $stepObject) {
            if ($stepObject instanceof CheckoutAddressesStep) {
                return (bool) $stepObject->isCurrent();
            }
        }

        return false;
    }

    private function getAllOrderSteps()
    {
        $isPrestashop177 = version_compare(_PS_VERSION_, '1.7.7.0', '>=');

        if (true === $isPrestashop177) {
            return $this->context->controller->getCheckoutProcess()->getSteps();
        }

        /* Reflect checkoutProcess object */
        $reflectedObject = (new ReflectionObject($this->context->controller))->getProperty('checkoutProcess');
        $reflectedObject->setAccessible(true);

        /* Get Checkout steps data */
        $checkoutProcessClass = $reflectedObject->getValue($this->context->controller);

        return $checkoutProcessClass->getSteps();
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }
}
