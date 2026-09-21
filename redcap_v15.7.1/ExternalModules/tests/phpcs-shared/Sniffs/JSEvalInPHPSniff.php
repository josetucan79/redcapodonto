<?php namespace ExternalModules\Sniffs\Misc;

use ExternalModules\SniffMessages;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Psalm already does a good job of detecting risky eval() calls in PHP.
 * This sniff detects them in JS embedded in PHP files.
 * We considered using PHPCS's eslint support, but that would require
 * a Node & Rhino dependency which is not worth the hassle for now.
 */
class JSEvalInPHPSniff implements Sniff{
    function register()
    {
        return [T_INLINE_HTML];
    }

    function process(File $file, $position)
    {
        $content = $file->getTokens()[$position]['content'];
        if(SniffMessages::doesLineContainJSEval($content)){
            $file->addError(SniffMessages::JS_EVAL, $position, 'Found');
        }
    }
}