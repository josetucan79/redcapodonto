<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Blueprint;

use SimpleXMLElement;

/**
 * this class was created for testing the SimpleXMLElement capabilities
 */
class BlueprintSimple extends SimpleXMLElement
{

    
    
    public function test() {
        echo 'ok';
    }

    function getProjectName() {
        foreach($this->getDocNamespaces() as $strPrefix => $strNamespace) {
            if(strlen($strPrefix)==0) {
                $strPrefix="_"; //Assign an arbitrary namespace prefix.
                $this->registerXPathNamespace($strPrefix,$strNamespace);
            }
        }
        $test = $this->children()->attributes();
        $attributes = $this->attributes();
        $test1 = $this->xpath('//_:StudyName');
        $a = $this->getRepeatingInstrumentsAttributes();
        $b = $this->getRepeatingInstruments();

        return $this->Study->GlobalVariables->StudyName;
    }
    
    function getRepeatingInstrumentsAttributes() {        
        return $list = $this->xpath('//redcap:RepeatingInstrument/@*');
    }

    function getRepeatingInstruments() {
        $list = $this->xpath('//redcap:RepeatingInstrument/@redcap:RepeatInstrument');
        $map = array_map(function($node) {
            $a = $node->attributes();
            $c = $node->children();
            echo $node;
            return $node->children();
        }, $list);
        return $map;
    }

    
}

