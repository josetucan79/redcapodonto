<?php

namespace Vanderbilt\REDCap\Classes\Email\Rules;

interface QueryNodeInterface {

    function getType();
    function toJSON(): array;
}