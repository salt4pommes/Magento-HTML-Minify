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
        );
    }
}