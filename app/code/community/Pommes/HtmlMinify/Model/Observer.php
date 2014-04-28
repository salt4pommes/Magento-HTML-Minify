<?php

class Pommes_HtmlMinify_Model_Observer {

    const CONFIG_PATH_ENABLED         = 'pommes_htmlminify/configuration/enabled';
    const CONFIG_PATH_MINIFY_METHOD   = 'pommes_htmlminify/configuration/method';
    const CONFIG_PATH_REMOVE_COMMENTS = 'pommes_htmlminify/configuration/remove_comments';
    const CONFIG_PATH_ACTIONS         = 'pommes_htmlminify/configuration/actions';

    /**
     * Checks if minify is enabled
     *
     * @return bool true)yes false)no
     */
    private function isEnabled() {

        /* Check if minification is enabled */
        if(strcmp(Mage::getStoreConfig(self::CONFIG_PATH_ENABLED), '1') === 0) {
            return true;
        }

        /* Not enabled */
        return false;
    }

    /**
     * Checks if current action can be minified
     *
     * @return bool true)yes false)no
     */
    private function actionCanMinified() {

        /* Read allowed actions from config */
        $allowed_actions = array_map('trim', explode(',', Mage::getStoreConfig(self::CONFIG_PATH_ACTIONS)));

        /* Does allowed actions exist? */
        if(count($allowed_actions) > 0) {

            /* Get action name from request */
            $request = Mage::app()->getRequest();
            $current_action = $request->getModuleName()."_".$request->getControllerName().'_'.$request->getActionName();

            /* Check if current action is allowed to get minified */
            if(in_array(trim($current_action), $allowed_actions)) {

                /* Can be minfied */
                return true;
            }
        }

        /* No minification possible */
        return false;
    }

    /**
     * Required function for minification
     */
    protected function outsideTag($m) {
        return '>' . preg_replace('/^\\s+|\\s+$/', ' ', $m[1]) . '<';
    }

    /**
     * Required function for minification
     */
    protected function comment($m) {
        return (0 === strpos($m[1], '[') || false !== strpos($m[1], '<![')) ? $m[0] : '';
    }

    /**
     * Minifies HTML-Data via regex
     *
     * @param $html string HTML-Data
     * @return string Minified HTML-Data
     */
    private function minifyHtml($html) {

        /* Default Compression by minify lib */
        if(strcmp(Mage::getStoreConfig(self::CONFIG_PATH_MINIFY_METHOD), '0') === 0) {

            /* Trim lines */
            $buffer = preg_replace('/^\\s+|\\s+$/m', '', $html);

            /* Remove comments if wanted */
            if(strcmp(Mage::getStoreConfig(self::CONFIG_PATH_REMOVE_COMMENTS), '1') === 0) {
                $buffer = preg_replace_callback('/<!--([\\s\\S]*?)-->/', array($this, 'comment'), $buffer);
            }

            /* Remove white spaces around elements */
            $buffer = preg_replace('/\\s+(<\\/?(?:area|base(?:font)?|blockquote|body'
                . '|caption|center|cite|col(?:group)?|dd|dir|div|dl|dt|fieldset|form'
                . '|frame(?:set)?|h[1-6]|head|hr|html|legend|li|link|map|menu|meta'
                . '|ol|opt(?:group|ion)|p|param|t(?:able|body|head|d|h||r|foot|itle)'
                . '|ul)\\b[^>]*>)/i', '$1', $buffer);

            /* Remove white spaces around all elements */
            $buffer = preg_replace_callback('/>([^<]+)</', array($this, 'outsideTag'), $buffer);

            /* Return minified */
            return $buffer;

        /* Compression method by Jesin */
        } else if(strcmp(Mage::getStoreConfig(self::CONFIG_PATH_MINIFY_METHOD), '1') === 0) {

            /* Search pattern */
            $search = array(
                '/\>[^\S ]+/s',  // strip whitespaces after tags, except space
                '/[^\S ]+\</s',  // strip whitespaces before tags, except space
                '/(\s)+/s'       // shorten multiple whitespace sequences
            );

            /* Replace pattern */
            $replace = array(
                '>',
                '<',
                '\\1'
            );

            /* Return minified data */
            return preg_replace($search, $replace, $html);
        }

        /* Return default html if no method matches */
        return $html;
    }

    /**
     * Minify HTML-Output
     *
     * @param $observer
     */
    public function minifyHtmlObserver($observer) {

        /* Minify enabled? */
        if($this->isEnabled()) {

            /* Check if action is allowed */
            if($this->actionCanMinified()) {

                /* Get current html output */
                $response = $observer->getEvent()->getControllerAction()->getResponse();

                /* Compress HTML :) */
                $html = $this->minifyHtml($response->getBody());

                /* Set new compressed html */
                $response->setBody($html);
            }
        }
    }
}