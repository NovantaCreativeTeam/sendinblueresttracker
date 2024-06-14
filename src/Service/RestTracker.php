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

namespace Novanta\Sendinblue\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Customer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Novanta\Sendinblue\Adapter\LinkAdapter;
use Order;
use PrestaShop\PrestaShop\Adapter\Entity\PrestaShopLogger;
use PrestaShop\PrestaShop\Adapter\Presenter\Cart\CartPresenter;
use PrestaShop\PrestaShop\Core\ConfigurationInterface;
use PrestaShop\PrestaShop\Core\Currency\CurrencyDataProviderInterface;
use Product;

class RestTracker
{
    private $configuration;
    private $currencyDataProvider;
    private $link;

    private $client;

    private $automation_key;

    public function __construct(
        ConfigurationInterface $configuration,
        CurrencyDataProviderInterface $currencyDataProvider,
        LinkAdapter $link
    ) {
        $this->configuration = $configuration;
        $this->currencyDataProvider = $currencyDataProvider;
        $this->link = $link;

        $this->automation_key = $this->configuration->get('Sendin_marketingAutomationKey');

        $this->client = new Client([
            'base_url' => 'https://in-automate.brevo.com/api/v2/',
            'defaults' => [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'ma-key' => $this->automation_key,
                    'User-Agent' => 'sendinblue_plugins/prestashop_1_7',
                ],
            ],
        ]);
    }

    public function identify(\Customer $customer)
    {
        try {
            $response = $this->client->post('identify', [
                'json' => [
                    'email' => $customer->email,
                    'attributes' => [
                        'FIRSTNAME' => $customer->firstname,
                        'LASTNAME' => $customer->lastname,
                    ],
                ],
            ]);

            return $response->getStatusCode() == 204;
        } catch (ClientException $e) {
            PrestaShopLogger::addLog(sprintf('Brevo - Unable to identify user, API call fail - %s', $e->getMessage()), 3, $e->getCode());

            return false;
        }
    }

    public function trackEvent(\Customer $customer, $event, $eventdata = null)
    {
        try {
            $response = $this->client->post('trackEvent', [
                'json' => [
                    'email' => $customer->email,
                    'event' => $event,
                    'properties' => [
                        'FIRSTNAME' => $customer->firstname,
                        'LASTNAME' => $customer->lastname,
                    ],
                    'eventdata' => $eventdata,
                ],
            ]);

            return $response->getStatusCode() == 204;
        } catch (ClientException $e) {
            PrestaShopLogger::addLog(sprintf('Brevo - Unable to track event %s: API call fail - %s', $event, $e->getMessage()), 3, $e->getCode());

            return false;
        }
    }

    public function trackLink(\Customer $customer, $link)
    {
        try {
            $response = $this->client->post('trackLink', [
                'json' => [
                    'email' => $customer->email,
                    'link' => $link,
                    'properties' => [
                        'FIRSTNAME' => $customer->firstname,
                        'LASTNAME' => $customer->lastname,
                    ],
                ],
            ]);

            return $response->getStatusCode() == 204;
        } catch (ClientException $e) {
            PrestaShopLogger::addLog(sprintf('Brevo - Unable to track link %s: API call fail - %s', $link, $e->getMessage()), 3, $e->getCode());

            return false;
        }
    }

    public function trackPage(\Customer $customer, $page)
    {
        try {
            $response = $this->client->post('trackPage', [
                'json' => [
                    'email' => $customer->email,
                    'link' => $page,
                    'properties' => [
                        'FIRSTNAME' => $customer->firstname,
                        'LASTNAME' => $customer->lastname,
                    ],
                ],
            ]);

            return $response->getStatusCode() == 204;
        } catch (ClientException $e) {
            PrestaShopLogger::addLog(sprintf('Brevo - Unable to track page %s: API call fail - %s', $page, $e->getMessage()), 3, $e->getCode());

            return false;
        }
    }

    public function getOrderEventData(\Order $order)
    {
        // 1. Initialize Order Data
        $order_data = [
            'id' => $order->reference,
            'date' => date('m-d-Y', strtotime($order->date_add)),
            'subtotal' => round($order->total_paid_tax_excl, 2),
            'shipping' => $order->total_shipping,
            'shipping_tax_exc' => $order->total_shipping_tax_excl,
            'tax' => $order->total_paid - $order->total_paid_tax_excl,
            'discount' => $order->total_discounts,
            'total' => round($order->total_paid, 2),
            'revenue' => round($order->total_paid, 2),
            'currency' => $this->currencyDataProvider->getCurrencyById($order->id_currency)->iso_code,
            'state_id' => $order->getCurrentState(),
            'state_name' => $order->getCurrentOrderState()->name[$order->id_lang],
            'has_been_paid' => $order->hasBeenPaid() > 0 ? 1 : 0,
        ];

        // 2. Load Order Product data
        $products_data = [];

        foreach ($order->getProductsDetail() as $product_detail) {
            $product = new \Product($product_detail['product_id']);

            if ($product) {
                $price_predisc_taxexc = $base_price = $product_detail['product_price']; // Retail price, excluding tax, excluding sales discounts
                $price_taxexc = $product_detail['unit_price_tax_excl']; // Retail price, excluding tax, including sales discounts
                $price_taxinc = $product_detail['unit_price_tax_incl']; // Retail price, including tax, including sales discounts
                $tax_amount_on_disc = $price_taxinc - $price_taxexc; // tax amount on discount
                $tax_rate = round(($tax_amount_on_disc / $price_taxexc) * 100, 2); // The tax rate percentage
                $tax_amount = ($base_price * $tax_rate) / 100; // The monetary value of tax
                $price_predisc_taxinc = $price_predisc_taxexc + $tax_amount; // Retail price, including tax, excluding sales discounts
                $disc_amt_taxexc = $price_predisc_taxexc - $price_taxexc; // The monetary value of discount, excluding tax
                $disc_rate = round(($disc_amt_taxexc / $base_price) * 100, 2); // The discount percentage
                $disc_amt_taxinc = ($disc_rate * $price_predisc_taxinc) / 100; // The monetary value of discount, including tax

                $products_data[] = [
                    'id' => $product->id,
                    'name' => $product->name[$order->id_lang],
                    'category' => $product->category[$order->id_lang],
                    'description_short' => $product->description_short[$order->id_lang],
                    'available_now' => $product->available_now,
                    'price' => $product_detail['total_price_tax_incl'],
                    'quantity' => $product_detail['product_quantity'],
                    'variant_id' => $product_detail['product_attribute_id'],
                    'sku' => $product_detail['product_reference'],
                    'url' => $this->link->getProductLink($product, $order->id_lang),
                    'image' => $this->link->getProductImageLink($product->id, $product_detail['product_attribute_id'], $order->id_lang),
                    'price_predisc' => round($price_predisc_taxexc, 2),
                    'price_predisc_taxinc' => round($price_predisc_taxinc, 2),
                    'price_taxinc' => round($price_taxinc, 2),
                    'tax_amount' => round($tax_amount, 2),
                    'tax_rate' => $tax_rate,
                    'tax_name' => $product_detail['tax_name'],
                    'disc_amount' => round($disc_amt_taxexc, 2),
                    'disc_amount_taxinc' => round($disc_amt_taxinc, 2),
                    'disc_rate' => $disc_rate,
                ];
            }
        }

        if (!empty($products_data)) {
            $order_data['items'] = $products_data;
        }

        // 3. TODO - Load Customer Address

        return ['data' => $order_data];
    }

    public function getCartEventData(\Cart $cart)
    {
        $presenter = new CartPresenter();
        $cartPresented = $presenter->present($cart);

        $cart_data = [
            'total' => $cartPresented['totals']['total']['value'],
            'total_amount' => $cartPresented['totals']['total']['amount'],
            'total_including_tax' => $cartPresented['totals']['total_including_tax']['amount'],
            'total_excluding_tax' => $cartPresented['totals']['total_excluding_tax']['amount'],
            'subtotal' => $cartPresented['subtotals']['products']['value'],
            'subtotal_amount' => $cartPresented['subtotals']['products']['amount'],
            'shipping' => $cartPresented['subtotals']['shipping']['value'],
            'shipping_amount' => $cartPresented['subtotals']['shipping']['amount'],
            'tax' => $cartPresented['subtotals']['tax']['value'] ?? null,
            'tax_amount' => $cartPresented['subtotals']['tax']['amount'] ?? 0,
            'discount' => $cartPresented['subtotals']['discounts']['value'] ?? null,
            'discount_amount' => $cartPresented['subtotals']['discounts']['amount'] ?? 0,
        ];

        $products_data = [];
        foreach ($cartPresented['products'] as $product) {
            $products_data[] = [
                'id' => $product->id_product,
                'name' => $product->name,
                'attributes' => $product->attributes,
                'category' => $product->category_name,
                'description' => $product->description,
                'description_short' => $product->description_short,
                'sku' => $product->reference,
                'quantity' => $product->quantity,
                'regular_price' => $product->regular_price,
                'regular_price_amount' => $product->regular_price_amount,
                'price' => $product->price,
                'price_amount' => $product->price_amount,
                'discount' => $product->discount_to_display,
                'unit_price' => $product->unit_price,
                'unity' => $product->unity,
                'url' => $product->url,
                'image' => $product->cover['small']['url'],
            ];
        }

        if (!empty($products_data)) {
            $cart_data['items'] = $products_data;
        }

        return [
            'id' => 'cart:' . $cart->id,
            'data' => $cart_data,
        ];
    }
}
