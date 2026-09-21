<?php

namespace Vanderbilt\REDCap\Classes\Email\Rules\Strategies;

interface HasKeyInterface {
    public static function key(): string;
}