<?php
namespace Vanderbilt\REDCap\Classes\Utility;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Class HtmlSanitizer
 *
 * A configurable HTML sanitizer utility using DOMDocument.
 * Supports restrictive (allow-list) or permissive (block-list) modes.
 */
class HtmlSanitizer
{
    public const MODE_RESTRICTIVE = 'restrictive'; // Only allow listed tags
    public const MODE_PERMISSIVE  = 'permissive';  // Allow all except disallowed

    /**
     * @var array List of allowed HTML tags (used in restrictive mode)
     */
    protected array $allowedTags;

    /**
     * @var array List of disallowed HTML tags (removed in all modes)
     */
    protected array $disallowedTags;

    /**
     * @var array List of disallowed attributes (e.g. onclick, style)
     */
    protected array $disallowedAttributes;

    /**
     * @var string Mode: either 'restrictive' or 'permissive'
     */
    protected string $mode;

    /**
     * HtmlSanitizer constructor.
     *
     * @param array $options {
     *     @type array  $allowedTags         Tags allowed (restrictive mode only)
     *     @type array  $disallowedTags      Tags to always remove
     *     @type array  $disallowedAttributes Attributes to always remove
     *     @type string $mode                One of self::MODE_RESTRICTIVE or self::MODE_PERMISSIVE
     * }
     */
    public function __construct(array $options = [])
    {
        $this->allowedTags = $options['allowedTags'] ?? [
            'p', 'br', 'b', 'i', 'u', 'strong', 'em', 'a', 'ul', 'ol', 'li'
        ];

        $this->disallowedTags = $options['disallowedTags'] ?? [
            'script', 'iframe', 'style', 'object', 'embed'
        ];

        $this->disallowedAttributes = $options['disallowedAttributes'] ?? [
            'onclick', 'onload', 'style', 'onerror', 'onmouseover', 'onfocus'
        ];

        $this->mode = in_array($options['mode'] ?? self::MODE_RESTRICTIVE, [self::MODE_RESTRICTIVE, self::MODE_PERMISSIVE])
            ? $options['mode']
            : self::MODE_RESTRICTIVE;
    }

    /**
     * Sanitize the provided HTML input.
     *
     * @param string $html The raw HTML to sanitize.
     * @return string Cleaned and safe HTML output.
     */
    public function sanitize(string $html): string
    {
        libxml_use_internal_errors(true);

        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $this->removeDisallowedTags($doc);

        if ($this->mode === self::MODE_RESTRICTIVE) {
            $this->removeUnallowedTags($doc);
        }

        $this->removeDisallowedAttributes($doc);

        libxml_clear_errors();
        return $doc->saveHTML();
    }

    /**
     * Remove tags that are explicitly disallowed.
     *
     * @param DOMDocument $doc
     * @return void
     */
    protected function removeDisallowedTags(DOMDocument $doc): void
    {
        foreach ($this->disallowedTags as $tag) {
            $elements = $doc->getElementsByTagName($tag);
            for ($i = $elements->length - 1; $i >= 0; $i--) {
                $el = $elements->item($i);
                $el->parentNode?->removeChild($el);
            }
        }
    }

    /**
     * Remove tags not in the allowed list (only used in restrictive mode).
     *
     * @param DOMDocument $doc
     * @return void
     */
    protected function removeUnallowedTags(DOMDocument $doc): void
    {
        $xpath = new DOMXPath($doc);
        foreach ($xpath->query('//*') as $node) {
            if (!in_array($node->nodeName, $this->allowedTags)) {
                $fragment = $doc->createDocumentFragment();
                while ($node->childNodes->length > 0) {
                    $fragment->appendChild($node->childNodes->item(0));
                }
                $node->parentNode?->replaceChild($fragment, $node);
            }
        }
    }

    /**
     * Remove disallowed or suspicious attributes from all elements.
     *
     * @param DOMDocument $doc
     * @return void
     */
    protected function removeDisallowedAttributes(DOMDocument $doc): void
    {
        $xpath = new DOMXPath($doc);
        foreach ($xpath->query('//*') as $node) {
            if (!($node instanceof DOMElement)) continue;

            foreach ($this->disallowedAttributes as $attr) {
                if ($node->hasAttribute($attr)) {
                    $node->removeAttribute($attr);
                }
            }

            // Remove event handler attributes like onClick, onMouseOver
            foreach (iterator_to_array($node->attributes ?? []) as $attr) {
                if (stripos($attr->nodeName, 'on') === 0) {
                    $node->removeAttribute($attr->nodeName);
                }
            }
        }
    }

    /**
     * Set allowed HTML tags.
     *
     * @param array $tags
     */
    public function setAllowedTags(array $tags): void
    {
        $this->allowedTags = $tags;
    }

    /**
     * Set disallowed HTML tags.
     *
     * @param array $tags
     */
    public function setDisallowedTags(array $tags): void
    {
        $this->disallowedTags = $tags;
    }

    /**
     * Set disallowed HTML attributes.
     *
     * @param array $attrs
     */
    public function setDisallowedAttributes(array $attrs): void
    {
        $this->disallowedAttributes = $attrs;
    }

    /**
     * Set the sanitization mode.
     *
     * @param string $mode One of self::MODE_RESTRICTIVE or self::MODE_PERMISSIVE
     */
    public function setMode(string $mode): void
    {
        if (in_array($mode, [self::MODE_RESTRICTIVE, self::MODE_PERMISSIVE])) {
            $this->mode = $mode;
        }
    }
}
