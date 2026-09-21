<?php
namespace Vanderbilt\REDCap\Classes\JsonParser\Helpers;

/**
 * @see https://github.com/lstrojny/functional-php
 */
 class Functional
 {

  /**
   * partial application of arguments to a function
   *
   * @param callable $function the function to which we want to apply part of the arguments
   * @param mixed $... list of arguments to partially apply
   * @return callable
   */
  public static function partial($function, ...$partial_args)
  {
    return function(...$func_args) use($function, $partial_args) {
      $args = array_merge($partial_args, $func_args);
      return call_user_func_array($function, $args);
    };
  }

  /**
   * partial application supplying arguments at the right first
   * @param callable $function the function to which we want to apply part of the arguments
   * @param mixed $... list of arguments to partially apply at the end
   * @return callable
   */
  public static function partialRight($function, ...$partial_args)
  {
    return function(...$func_args) use($function, $partial_args) {
      $args = array_merge($func_args, $partial_args);
      return call_user_func_array($function, $args);
    };
  }
 }