<?php
namespace Vanderbilt\REDCap\Classes\JsonParser\Filters;

/**
 * factory for the value filters of a Node
 */
abstract class FilterConditions
{
  const IDENTICAL = '=';
  const IDENTICAL_1 = '===';
  const NOT_IDENTICAL = '!=';
  const NOT_IDENTICAL_1 = '!===';
  const EQUAL = '==';
  const NOT_EQUAL = '!==';
  const NULL = 'null';
  const NOT_NULL = 'not null';
  const BIGGER = '>';
  const BIGGER_OR_EQUAL = '>=';
  const SMALLER = '<';
  const SMALLER_OR_EQUAL = '<=';
  const IN = 'in';
  const NOT_IN = 'not in';
  const BETWEEN = '><';
  const BETWEEN1 = 'beetween';
  const NOT_BETWEEN = '!><';
  const NOT_BETWEEN_1 = 'not beetween';
  const LIKE = '~';
  const LIKE_1 = 'like';
  const NOT_LIKE = '!~';
  const NOT_LIKE_1 = 'not like';
  const ANY = '*';
  const ANY_1 = 'any';
  const IS_STRING = 'is string';
  const IS_NUMBER = 'is number';
  const IS_BOOLEAN = 'is boolean';
  const IS_ARRAY = 'is array';
  const IS_OBJECT = 'is object';
 }