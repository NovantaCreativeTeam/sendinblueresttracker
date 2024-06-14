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

namespace Novanta\Sendinblue\Adapter;

if (!defined('_PS_VERSION_')) {
    exit;
}

class LinkAdapter
{
    private $link;

    public function __construct()
    {
        $this->link = new \Link();
    }

    public function getProductLink(\Product $product, $id_lang = null)
    {
        return $this->link->getProductLink($product, null, null, null, $id_lang);
    }

    public function getProductImageLink($id_product, $id_product_attribute = null, $id_lang = null)
    {
        $product = new \Product($id_product);
        $image_link = '';

        if ($product) {
            $image_type = \ImageType::getFormattedName('home');
            $image = null;

            if ($id_product_attribute) {
                $image = $product->getCombinationImageById($id_product_attribute, $id_lang);
            }

            if (empty($image)) {
                $image = $product->getCover($id_product);
            }

            $image_link = $image && \array_key_exists('id_image', $image) ? $this->link->getImageLink($product->link_rewrite[$id_lang], $image['id_image'], $image_type) : '';
        }

        return $image_link;
    }
}
