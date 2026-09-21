<?php
namespace Vanderbilt\REDCap\Classes\JsonParser\Filters;

use Vanderbilt\REDCap\Classes\JsonParser\Helpers\Functional;
use Vanderbilt\REDCap\Classes\JsonParser\Filters\FilterConditions as FC;

/**
 * factory for the value filters of a Node
 */
class FilterFactory
{
  public static function make($condition, ...$args)
  {

    $filter = new Filter();
    $condition = strtolower($condition);
    switch($condition) {
      case FC::IDENTICAL: // alternate shorter syntax (most common usage)
      case FC::IDENTICAL_1:
        $callback = Functional::partialRight([$filter, 'isIdentical'], ...$args);
        break;
      case FC::NOT_IDENTICAL: // alternate shorter syntax (most common usage)
      case FC::NOT_IDENTICAL_1:
        $callback = Functional::partialRight([$filter, 'isNotIdentical'], ...$args);
        break;
      case FC::EQUAL:
        $callback = Functional::partialRight([$filter, 'isEqual'], ...$args);
        break;
      case FC::NOT_EQUAL:
        $callback = Functional::partialRight([$filter, 'isNotEqual'], ...$args);
        break;
      case FC::NULL:
        $callback = Functional::partialRight([$filter, 'isNull']);
        break;
      case FC::NOT_NULL:
        $callback = Functional::partialRight([$filter, 'isNotNull']);
        break;
      case FC::BIGGER:
        $callback = Functional::partialRight([$filter, 'isBigger'], ...$args);
        break;
      case FC::BIGGER_OR_EQUAL:
        $callback = Functional::partialRight([$filter, 'isBiggerOrEqual'], ...$args);
        break;
      case FC::SMALLER:
        $callback = Functional::partialRight([$filter, 'isSmaller'], ...$args);
        break;
      case FC::SMALLER_OR_EQUAL:
        $callback = Functional::partialRight([$filter, 'isSmallerOrEqual'], ...$args);
        break;
      case FC::IN:
        $callback = Functional::partialRight([$filter, 'isIn'], ...$args);
        break;
      case FC::NOT_IN:
        $callback = Functional::partialRight([$filter, 'isNotIn'], ...$args);
        break;
      case FC::BETWEEN:
      case FC::BETWEEN1:
        $callback = Functional::partialRight([$filter, 'isBeetween'], ...$args);
        break;
      case FC::NOT_BETWEEN:
      case FC::NOT_BETWEEN_1:
        $callback = Functional::partialRight([$filter, 'isNotBeetween'], ...$args);
        break;
      case FC::LIKE:
      case FC::LIKE_1:
        $callback = Functional::partialRight([$filter, 'isLike'], ...$args);
        break;
      case FC::NOT_LIKE:
      case FC::NOT_LIKE_1:
        $callback = Functional::partialRight([$filter, 'isNotLike'], ...$args);
        break;
      case FC::ANY:
      case FC::ANY_1:
        $callback = Functional::partialRight([$filter, 'isAny']);
        break;
      case FC::IS_STRING:
        $callback = Functional::partialRight([$filter, 'isString']);
        break;
      case FC::IS_NUMBER:
        $callback = Functional::partialRight([$filter, 'isNumber']);
        break;
      case FC::IS_BOOLEAN:
        $callback = Functional::partialRight([$filter, 'isBoolean']);
        break;
      case FC::IS_ARRAY:
        $callback = Functional::partialRight([$filter, 'isArray']);
        break;
      case FC::IS_OBJECT:
        $callback = Functional::partialRight([$filter, 'isObject']);
        break;
    }
    return $callback;
  }
 }