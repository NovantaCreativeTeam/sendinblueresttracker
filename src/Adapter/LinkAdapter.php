<?php

namespace Novanta\Sendinblue\Adapter;

use ImageType;
use Link;
use Product;

class LinkAdapter
{
    private $link;

    public function __construct()
    {
        $this->link = new Link();    
    }

    public function getProductLink(Product $product, $id_lang = null) {
        return $this->link->getProductLink($product, null, null, null, $id_lang);
    }

    public function getProductImageLink($id_product, $id_product_attribute = null, $id_lang = null) {
        $product = new Product($id_product);
        $image_link = '';

        if($product) {
            $image_type = ImageType::getFormattedName('home');
            $image = null;

            if($id_product_attribute) {
                $image = $product->getCombinationImageById($id_product_attribute, $id_lang);
            } 

            if(empty($image)) {
                $image = $product->getCover($id_product);
            }

            $image_link = $image && \array_key_exists('id_image', $image) ? $this->link->getImageLink($product->link_rewrite[$id_lang], $image['id_image'], $image_type) : '';
        }

        return $image_link;
    }
    
}
