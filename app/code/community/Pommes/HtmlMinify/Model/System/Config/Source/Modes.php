<?php

class Pommes_HtmlMinify_Model_System_Config_Source_Modes {

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 0, 'label'=>Mage::helper('pommes_htmlminify')->__('PHP-Minify')),
            array('value' => 1, 'label'=>Mage::helper('pommes_htmlminify')->__('Jesin')),
            array('value' => 2, 'label'=>Mage::helper('pommes_htmlminify')->__('PHP-Minify v2')),
            array('value' => 3, 'label'=>Mage::helper('pommes_htmlminify')->__('DOM-Minifier')),
        );
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            0 => Mage::helper('pommes_htmlminify')->__('PHP-Minify'),
            1 => Mage::helper('pommes_htmlminify')->__('Jesin'),
            2 => Mage::helper('pommes_htmlminify')->__('PHP-Minify v2'),
            3 => Mage::helper('pommes_htmlminify')->__('DOM-Minifier'),
        );
    }
}