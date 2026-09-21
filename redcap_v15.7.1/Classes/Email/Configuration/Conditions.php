<?php

namespace Vanderbilt\REDCap\Classes\Email\Configuration;

final class Conditions {
    const EQUAL = 'is equal';
    const NOT_EQUAL = 'is not equal';
    const LESS_THAN = 'is less than';
    const LESS_THAN_EQUAL = 'is less than or equal';
    const GREATER_THAN = 'is greater than';
    const GREATER_THAN_EQUAL = 'is greater than or equal';
    const IS_BETWEEN = 'is between';
    const IS_NOT_BETWEEN = 'is not between';
    const IS_IN = 'is in';
    const IS_NOT_IN = 'is not in';
    const CONTAINS = 'contains';
    const DOES_NOT_CONTAIN = 'does not contain';
    const BEGINS_WITH = 'begins with';
    const DOES_NOT_BEGIN_WITH = 'does not begin with';
    const ENDS_WITH = 'ends with';
    const DOES_NOT_END_WITH = 'does not end with';
    const IS_NULL = 'is null';
    const IS_NOT_NULL = 'is not null';
    const IS = 'is';
    const IS_NOT = 'is not';
    const HAS = 'has';
    const HAS_NOT = 'has not';
    const IS_WITHIN = 'is within';
    const IS_NOT_WITHIN = 'is not within';
}