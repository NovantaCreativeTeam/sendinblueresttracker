<?php 

class SendinblueRestTracker extends Module
{
    public function __construct()
    {
        $this->name = 'sendinblueresttracker';
        $this->version = '1.0.1';
        $this->author = 'Novanta';

        parent::__construct();

        $this->displayName = $this->trans('Sendinblue REST tracker', array(), 'Modules.Sendinblueresttracker.Admin');
        $this->description = $this->trans('Adding Sendinblue Tracker to track some custom event', array(), 'Modules.Sendinblueresttracker.Admin');
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

        return parent::install() &&
            $this->registerHook('actionOrderHistoryAddAfter') &&
            $this->registerHook('displayHeader');
    }

    public function hookActionOrderHistoryAddAfter(array $params)
    {
        $order_history = $params['order_history'];

        $order = new Order($order_history->id_order);
        if($order) {
            $customer = $order->getCustomer();
            $eventdata = $this->get('novanta.sendinblue.resttracker')->getOrderEventData($order);
            $this->get('novanta.sendinblue.resttracker')->trackEvent($customer, 'order_status_update', $eventdata);
        }
    }

    public function hookDisplayHeader(array $params) 
    {
        if ($this->context->controller instanceof OrderController) {
            if ($this->isFirstCheckoutStep()) {
                $customer = $this->context->customer;
                
                $eventdata = $this->get('novanta.sendinblue.resttracker')->getCartEventData($this->context->cart);
                $this->get('novanta.sendinblue.resttracker')->trackEvent($customer, 'initiate_checkout', $eventdata);
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
