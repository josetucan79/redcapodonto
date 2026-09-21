<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Blueprint;

use DOMElement;

class Element extends DOMElement {

   /**
   * allows to appendChild with just one method call
   *
   * @param string $name
   * @see https://www.php.net/manual/en/domdocument.registernodeclass.php
   * @return DOMNode
   */
   function appendElement($name) { 
      return $this->appendChild(new self($name));
   }

}