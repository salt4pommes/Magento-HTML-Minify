<?php

class Pommes_HtmlMinify_Model_Observer {

    const CONFIG_PATH_ENABLED           = 'pommes_htmlminify/configuration/enabled';
    const CONFIG_PATH_MINIFY_METHOD     = 'pommes_htmlminify/configuration/method';
    const CONFIG_PATH_REMOVE_COMMENTS   = 'pommes_htmlminify/configuration/remove_comments';
    const CONFIG_PATH_ACTIONS           = 'pommes_htmlminify/configuration/actions';

    const MINIFY_COMMENT_HTML   = '<!\-{2}[\s\S]*?\-{2}>';
    const MINIFY_HTML           = '<[!/]?[a-zA-Z\d:.-]+[\s\S]*?>';
    const MINIFY_HTML_ENT       = '&(?:[a-zA-Z\d]+|\#\d+|\#x[a-fA-F\d]+);';
    const MINIFY_HTML_KEEP      = '<pre(?:\s[^<>]*?)?>[\s\S]*?</pre>|<code(?:\s[^<>]*?)?>[\s\S]*?</code>|<script(?:\s[^<>]*?)?>[\s\S]*?</script>|<style(?:\s[^<>]*?)?>[\s\S]*?</style>|<textarea(?:\s[^<>]*?)?>[\s\S]*?</textarea>';
    const MINIFY_STRING         = '"(?:[^"\\\]|\\\.)*"|\'(?:[^\'\\\]|\\\.)*\'';
    const MINIFY_COMMENT_CSS    = '/\*[\s\S]*?\*/';

    /* Escape char */
    const X = "\x1A";

    /**
     * Checks if minify is enabled
     *
     * @return bool true)yes false)no
     */
    private function isEnabled () {

        /* Check if minification is enabled */
        if (strcmp(Mage::getStoreConfig(self::CONFIG_PATH_ENABLED), '1') === 0) {
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
    private function actionCanMinified () {

        /* Read allowed actions from config */
        $allowed_actions = array_map('trim', explode(',', Mage::getStoreConfig(self::CONFIG_PATH_ACTIONS)));

        /* Does allowed actions exist? */
        if (count($allowed_actions) > 0) {

            /* Get action name from request */
            $request        = Mage::app()->getRequest();
            $current_action = $request->getModuleName() . "_" . $request->getControllerName() . '_' . $request->getActionName();

            /* Check if current action is allowed to get minified */
            if (in_array(trim($current_action), $allowed_actions)) {

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
    protected function outsideTag ($m) {
        return '>' . preg_replace('/^\\s+|\\s+$/', ' ', $m[1]) . '<';
    }

    /**
     * Required function for minification
     */
    protected function comment ($m) {
        return (0 === strpos($m[1], '[') || false !== strpos($m[1], '<![')) ? $m[0] : '';
    }

    private function fn_minify_html ($input, $comment = 2, $quote = 1) {
        if (!is_string($input) || !$input = $this->n(trim($input))) {
            return $input;
        }
        $output = $prev = "";
        foreach ($this->fn_minify([
            self::MINIFY_COMMENT_HTML,
            self::MINIFY_HTML_KEEP,
            self::MINIFY_HTML,
            self::MINIFY_HTML_ENT
        ], $input) as $part) {
            if ($part === "\n") {
                continue;
            }
            if ($part !== ' ' && trim($part) === "" || $comment !== 1 && strpos($part, '<!--') === 0) {
                // Detect IE conditional comment(s) by its closing tag …
                if ($comment === 2 && substr($part, -12) === '<![endif]-->') {
                    $output .= $part;
                }
                continue;
            }
            if ($part[0] === '<' && substr($part, -1) === '>') {
                $output .= $this->fn_minify_html_union($part, $quote);
            } else {
                if ($part[0] === '&' && substr($part, -1) === ';' && $part !== '&lt;' && $part !== '&gt;' && $part !== '&amp;') {
                    $output .= html_entity_decode($part); // Evaluate HTML entit(y|ies)
                } else {
                    $output .= preg_replace('#\s+#', ' ', $part);
                }
            }
            $prev = $part;
        }
        $output = str_replace(' </', '</', $output);
        // Force space with `&#x0020;` and line–break with `&#x000A;`
        return str_ireplace([
            '&#x0020;',
            '&#x20;',
            '&#x000A;',
            '&#xA;'
        ], [
            ' ',
            ' ',
            "\n",
            "\n"
        ], trim($output));
    }

    private function n ($s) {
        return str_replace([
            "\r\n",
            "\r"
        ], "\n", $s);
    }

    private function getUrl () {
        return Mage::app()->getStore()->getBaseUrl();
    }

    private function fn_minify ($pattern, $input) {
        return preg_split('#(' . implode('|', $pattern) . ')#', $input, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    }

    private function fn_minify_html_union ($input, $quote) {
        if (strpos($input, ' ') === false && strpos($input, "\n") === false && strpos($input, "\t") === false) {
            return $input;
        }

        $url = $this->getUrl();

        return preg_replace_callback('#<\s*([^\/\s]+)\s*(?:>|(\s[^<>]+?)\s*>)#', function ($m) use ($quote, $url) {
            if (isset($m[2])) {
                // Minify inline CSS(s)
                if (stripos($m[2], ' style=') !== false) {
                    $m[2] = preg_replace_callback('#( style=)([\'"]?)(.*?)\2#i', function ($m) {
                        return $m[1] . $m[2] . $this->fn_minify_css($m[3]) . $m[2];
                    }, $m[2]);
                }
                // Minify URL(s)
                if (strpos($m[2], '://') !== false) {
                    $m[2] = str_replace([
                        $url . '/',
                        $url . '?',
                        $url . '&',
                        $url . '#',
                        $url . '"',
                        $url . "'"
                    ], [
                        '/',
                        '?',
                        '&',
                        '#',
                        '/"',
                        "/'"
                    ], $m[2]);
                }
                $a = 'a(sync|uto(focus|play))|c(hecked|ontrols)|d(efer|isabled)|hidden|ismap|loop|multiple|open|re(adonly|quired)|s((cop|elect)ed|pellcheck)';
                $a = '<' . $m[1] . preg_replace([
                        // From `a="a"`, `a='a'`, `a="true"`, `a='true'`, `a=""` and `a=''` to `a` [^1]
                        '#\s(' . $a . ')(?:=([\'"]?)(?:true|\1)?\2)#i',
                        // Remove extra white–space(s) between HTML attribute(s) [^2]
                        '#\s*([^\s=]+?)(=(?:\S+|([\'"]?).*?\3)|$)#',
                        // From `<img />` to `<img/>` [^3]
                        '#\s+\/$#'
                    ], [
                        // [^1]
                        ' $1',
                        // [^2]
                        ' $1$2',
                        // [^3]
                        '/'
                    ], str_replace("\n", ' ', $m[2])) . '>';
                return $quote !== 1 ? $this->fn_minify_html_union_attr($a) : $a;
            }
            return '<' . $m[1] . '>';
        }, $input);
    }

    private function fn_minify_html_union_attr ($input) {
        if (strpos($input, '=') === false) {
            return $input;
        }
        return preg_replace_callback('#=(' . self::MINIFY_STRING . ')#', function ($m) {
            $q = $m[1][0];
            if (strpos($m[1], ' ') === false && preg_match('#^' . $q . '[a-zA-Z_][\w-]*?' . $q . '$#', $m[1])) {
                return '=' . $this->t($m[1], $q);
            }
            return $m[0];
        }, $input);
    }

    private function t ($a, $b) {
        if ($a && strpos($a, $b) === 0 && substr($a, -strlen($b)) === $b) {
            return substr(substr($a, strlen($b)), 0, -strlen($b));
        }
        return $a;
    }

    private function fn_minify_css ($input, $comment = 2, $quote = 2) {
        if (!is_string($input) || !$input = $this->n(trim($input))) {
            return $input;
        }
        $output = $prev = "";
        foreach ($this->fn_minify([
            self::MINIFY_COMMENT_CSS,
            self::MINIFY_STRING
        ], $input) as $part) {
            if (trim($part) === "") {
                continue;
            }
            if ($comment !== 1 && strpos($part, '/*') === 0 && substr($part, -2) === '*/') {
                if ($comment === 2 && (// Detect special comment(s) from the third character. It should be a `!` or `*` → `/*! keep */` or `/** keep */`
                        strpos('*!', $part[2]) !== false || // Detect license comment(s) from the content. It should contains character(s) like `@license`
                        stripos($part, '@licence') !== false || // noun
                        stripos($part, '@license') !== false || // verb
                        stripos($part, '@preserve') !== false)
                ) {
                    $output .= $part;
                }
                continue;
            }
            if ($part[0] === '"' && substr($part, -1) === '"' || $part[0] === "'" && substr($part, -1) === "'") {
                // Remove quote(s) where possible …
                $q = $part[0];
                if ($quote !== 1 && (// <https://www.w3.org/TR/CSS2/syndata.html#uri>
                        substr($prev, -4) === 'url(' && preg_match('#\burl\($#', $prev) || // <https://www.w3.org/TR/CSS2/syndata.html#characters>
                        substr($prev, -1) === '=' && preg_match('#^' . $q . '[a-zA-Z_][\w-]*?' . $q . '$#', $part))
                ) {
                    $part = $this->t($part, $q); // trim quote(s)
                }
                $output .= $part;
            } else {
                $output .= $this->fn_minify_css_union($part);
            }
            $prev = $part;
        }
        return trim($output);
    }

    private function fn_minify_css_union ($input) {
        if (stripos($input, 'calc(') !== false) {
            // Keep important white–space(s) in `calc()`
            $input = preg_replace_callback('#\b(calc\()\s*(.*?)\s*\)#i', function ($m) {
                return $m[1] . preg_replace('#\s+#', self::X, $m[2]) . ')';
            }, $input);
        }
        $input = preg_replace([
            // Fix case for `#foo<space>[bar="baz"]`, `#foo<space>*` and `#foo<space>:first-child` [^1]
            '#(?<=[\w])\s+(\*|\[|:[\w-]+)#',
            // Fix case for `[bar="baz"]<space>.foo`, `*<space>.foo` and `@media<space>(foo: bar)<space>and<space>(baz: qux)` [^2]
            '#(\*|\])\s+(?=[\w\#.])#',
            '#\b\s+\(#',
            '#\)\s+\b#',
            // Minify HEX color code … [^3]
            '#\#([a-f\d])\1([a-f\d])\2([a-f\d])\3\b#i',
            // Remove white–space(s) around punctuation(s) [^4]
            '#\s*([~!@*\(\)+=\{\}\[\]:;,>\/])\s*#',
            // Replace zero unit(s) with `0` [^5]
            '#\b(?:0\.)?0([a-z]+\b|%)#i',
            // Replace `0.6` with `.6` [^6]
            '#\b0+\.(\d+)#',
            // Replace `:0 0`, `:0 0 0` and `:0 0 0 0` with `:0` [^7]
            '#:(0\s+){0,3}0(?=[!,;\)\}]|$)#',
            // Replace `background(?:-position)?:(0|none)` with `background$1:0 0` [^8]
            '#\b(background(?:-position)?):(?:0|none)([;\}])#i',
            // Replace `(border(?:-radius)?|outline):none` with `$1:0` [^9]
            '#\b(border(?:-radius)?|outline):none\b#i',
            // Remove empty selector(s) [^10]
            '#(^|[\{\}])(?:[^\{\}]+)\{\}#',
            // Remove the last semi–colon and replace multiple semi–colon(s) with a semi–colon [^11]
            '#;+([;\}])#',
            // Replace multiple white–space(s) with a space [^12]
            '#\s+#'
        ], [
            // [^1]
            self::X . '$1',
            // [^2]
            '$1' . self::X,
            self::X . '(',
            ')' . self::X,
            // [^3]
            '#$1$2$3',
            // [^4]
            '$1',
            // [^5]
            '0',
            // [^6]
            '.$1',
            // [^7]
            ':0',
            // [^8]
            '$1:0 0$2',
            // [^9]
            '$1:0',
            // [^10]
            '$1',
            // [^11]
            '$1',
            // [^12]
            ' '
        ], $input);
        return trim(str_replace(self::X, ' ', $input));
    }

    /**
     * Minifies HTML-Data via regex
     *
     * @param $html string HTML-Data
     *
     * @return string Minified HTML-Data
     */
    private function minifyHtml ($html) {

        /* Default Compression by minify lib */
        if (strcmp(Mage::getStoreConfig(self::CONFIG_PATH_MINIFY_METHOD), '0') === 0) {

            /* Trim lines */
            $buffer = preg_replace('/^\\s+|\\s+$/m', '', $html);

            /* Remove comments if wanted */
            if (strcmp(Mage::getStoreConfig(self::CONFIG_PATH_REMOVE_COMMENTS), '1') === 0) {
                $buffer = preg_replace_callback('/<!--([\\s\\S]*?)-->/', array(
                    $this,
                    'comment'
                ), $buffer);
            }

            /* Remove white spaces around elements */
            $buffer = preg_replace('/\\s+(<\\/?(?:area|base(?:font)?|blockquote|body' . '|caption|center|cite|col(?:group)?|dd|dir|div|dl|dt|fieldset|form' . '|frame(?:set)?|h[1-6]|head|hr|html|legend|li|link|map|menu|meta' . '|ol|opt(?:group|ion)|p|param|t(?:able|body|head|d|h||r|foot|itle)' . '|ul)\\b[^>]*>)/i', '$1', $buffer);

            /* Remove white spaces around all elements */
            $buffer = preg_replace_callback('/>([^<]+)</', array(
                $this,
                'outsideTag'
            ), $buffer);

            /* Return minified */
            return $buffer;

            /* Compression method by Jesin */
        } else {

            if (strcmp(Mage::getStoreConfig(self::CONFIG_PATH_MINIFY_METHOD), '1') === 0) {

                /* Search pattern */
                $search = array(
                    '/\>[^\S ]+/s',
                    // strip whitespaces after tags, except space
                    '/[^\S ]+\</s',
                    // strip whitespaces before tags, except space
                    '/(\s)+/s'
                    // shorten multiple whitespace sequences
                );

                /* Replace pattern */
                $replace = array(
                    '>',
                    '<',
                    '\\1'
                );

                /* Return minified data */
                $buffer = preg_replace($search, $replace, $html);

                $buffer = preg_replace('/^\\s+|\\s+$/m', '', $buffer);
                $buffer = preg_replace('/\\s+(<\\/?(?:area|base(?:font)?|blockquote|body|caption|center|cite|col(?:group)?|dd|dir|div|dl|dt|fieldset|form|frame(?:set)?|h[1-6]|head|hr|html|legend|li|link|map|menu|meta|ol|opt(?:group|ion)|p|param|t(?:able|body|head|d|h||r|foot|itle)|ul)\\b[^>]*>)/i', '$1', $buffer);
                $buffer = preg_replace_callback('/>([^<]+)</', array(
                    $this,
                    'outsideTag'
                ), $buffer);

                return $buffer;

                /* PHP-Compression v2 */
            } else {

                if (strcmp(Mage::getStoreConfig(self::CONFIG_PATH_MINIFY_METHOD), '2') === 0) {

                    $html = $this->fn_minify_html($html);

                } else {

                    $preparedHtml = mb_convert_encoding($html, 'HTML-ENTITIES', "UTF-8");

                    $dom = new DOMDocument('1.0');
                    @$dom->loadHTML($preparedHtml);

                    $dom_content                     = new DOMDocument();
                    $dom_content->preserveWhiteSpace = false;
                    $dom_content->formatOutput       = false;

                    /* Load html with trimmed whitespaces */
                    libxml_use_internal_errors(true);
                    $dom_content->loadXML($dom->saveXML());
                    libxml_clear_errors();

                    /* Trim strings */
                    $xpath       = new DOMXPath($dom_content);
                    $domNodeList = $xpath->query('//text()');

                    foreach ($domNodeList as $node) {
                        if ($node->nodeType === 3) {

                            $trimmed = trim($node->wholeText);
                            $newNode = $dom_content->createDocumentFragment();
                            $newNode->appendXML($trimmed);
                            if (is_object($node->parentNode)) {
                                $node->parentNode->replaceChild($newNode, $node);
                            }
                        }
                    }

                    $html = $dom_content->saveHTML();
                }
            }
        }

        /* Return default html if no method matches */
        return $html;
    }

    /**
     * Minify HTML-Output
     *
     * @param $observer
     */
    public function minifyHtmlObserver ($observer) {

        /* Minify enabled? */
        if ($this->isEnabled()) {

            /* Check if action is allowed */
            if ($this->actionCanMinified()) {

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
