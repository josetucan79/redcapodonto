<?php
namespace Vanderbilt\REDCap\Classes\Utility;

use DOMNode;
use DOMDocument;

/**
 * A utility class to truncate HTML content without exceeding a specified maximum length.
 */
class HTMLTruncator {
    /**
     * Maximum size of a field in the REDCap database.
     */
    const MAX_FIELD_SIZE = 65535;

    /**
     * Default notice text to be prepended to the HTML content when truncation occurs because the content exceeds the maximum allowed length.
     */
    const TOO_LARGE_NOTICE = 'DATA TOO LARGE, TRUNCATED';

    /**
     * Key for specifying the maximum length of the HTML content in the truncate method's $params array.
     */
    const OPTION_MAX_LENGTH = 'max_length';

    /**
     * Key for specifying the optional notice text to prepend to the HTML content if truncation occurs, in the truncate method's $params array.
     */
    const OPTION_TOO_LARGE_NOTICE = 'notice';

    /**
     * The maximum length for the truncated HTML.
     *
     * @var int
     */
    private $max_length;
    
    /**
     * Optional text to add at the beginning if truncation is needed.
     *
     * @var string|null
     */
    private $truncation_notice;

    /**
     * Constructor to set the maximum length of HTML content and optional truncation notice.
     *
     * @param int $max_length Maximum length of the HTML content. Default is the maximum field size.
     * @param string|null $truncation_notice Optional notice to prepend if truncation occurs.
     */
    public function __construct($max_length, $truncation_notice=null) {
        $this->max_length = $max_length;
        $this->truncation_notice = $truncation_notice;
    }
    
    /**
     * Static function to create an instance and truncate the HTML string. Allows passing parameters to
     * configure truncation behavior, such as maximum length and an optional notice to prepend if truncation occurs.
     *
     * Parameters are passed as an associative array with specific keys.
     *
     * @param string $html_string HTML content to be truncated.
     * @param array $params Configuration options including:
     *        - self::OPTION_MAX_LENGTH (int): Maximum length of the HTML content. Defaults to the maximum field size.
     *        - self::OPTION_TOO_LARGE_NOTICE (string|null): Optional notice to prepend if truncation occurs. Defaults to a predefined notice.
     * @return string Truncated HTML content.
     */
    public static function truncate($html_string, $params=[]) {
        $max_length = $params[self::OPTION_MAX_LENGTH] ?? self::MAX_FIELD_SIZE;
        $truncation_notice = $params[self::OPTION_TOO_LARGE_NOTICE] ?? self::TOO_LARGE_NOTICE;
        $instance = new self($max_length, $truncation_notice);
        return $instance->truncateHTML($html_string);
    }

    /**
     * Truncate the HTML content to ensure it does not exceed the maximum length.
     *
     * @param string $html_string HTML content to be truncated.
     * @return string Truncated HTML content.
     */
    public function truncateHTML($html_string) {
        if (strlen($html_string) <= $this->max_length) return $html_string;

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html_string, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
        libxml_clear_errors();

        // check for truncation notice
        if (!empty($this->truncation_notice) && is_string($this->truncation_notice)) {
            $noticeNode = $dom->createElement('p', htmlspecialchars($this->truncation_notice));
            $dom->documentElement->insertBefore($noticeNode, $dom->documentElement->firstChild);
        }
        
        $output = '';
        $totalLength = 0;
        $root = $dom->documentElement;
        
        $useWrapper = $dom->childNodes->length > 1;
        
        if ($useWrapper) {
            $wrapper = $dom->createElement('div');
            while ($root->firstChild) {
                $wrapper->appendChild($root->firstChild);
            }
            $root = $wrapper;
        }
        
        $this->traverseDOM($root, $totalLength, $output);
        return $dom->saveHTML($root);
        
    }
    
    /**
     * Recursively traverse the DOM nodes and truncate their content if necessary.
     *
     * @param DOMNode $node Current node being processed.
     * @param int $currentLength Current accumulated length of the text content.
     * @return int Updated length after processing the current node.
     */
    private function traverseDOM(DOMNode $node, $currentLength) {
        if ($node->nodeType === XML_TEXT_NODE) {
            $nodeTextLength = strlen($node->nodeValue);
            if ($currentLength + $nodeTextLength > $this->max_length) {
                $node->nodeValue = substr($node->nodeValue, 0, $this->max_length - $currentLength);
                return $this->max_length; // Reached max length, stop further processing
            }
            return $currentLength + $nodeTextLength;
        }

        $newLength = $currentLength;
        if ($node->hasChildNodes()) {
            $childNodes = iterator_to_array($node->childNodes);
            foreach ($childNodes as $child) {
                $newLength = $this->traverseDOM($child, $newLength);
                if ($newLength >= $this->max_length) {
                    // Remove all subsequent nodes since the max length is reached
                    while ($child->nextSibling) {
                        $node->removeChild($child->nextSibling);
                    }
                    break;
                }
            }
        }

        // If after processing children the new length is still too much, remove the node entirely
        if ($node->parentNode && $newLength + strlen($node->ownerDocument->saveHTML($node)) > $this->max_length) {
            $node->parentNode->removeChild($node);
        }

        return $newLength;
    }

}