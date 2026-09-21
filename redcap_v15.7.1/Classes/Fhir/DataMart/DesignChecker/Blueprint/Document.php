<?php

namespace Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Blueprint;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Exception;
use Vanderbilt\REDCap\Classes\Fhir\DataMart\DesignChecker\Blueprint\Element;

/**
 * object that can parse and extract values/attributes
 * from a XML REDCap project
 */
class Document extends DOMDocument {

    
    /**
     * cache a DomXpath object
     * based on the Document content
     *
     * @var DomXpath
     */
    private $xpath = null;

    const ROOT_NAMESPACE_ID = 'x';
    /**
     * separator using to convert an array of choices to string
     * using the same format used for REDCap enums
     */
    const ENUM_AS_STRING_SEPARATOR = '\n';
    
    /**
     *
     * @param string $version
     * @param string $encoding
     */
    public function __construct($version = '1.0', $encoding = '')
    {
        parent::__construct($version, $encoding);
        $this->registerNodeClass('DOMElement', Element::class);
        // $this->createElementNS('', 'empty');
    }

    public static function fromFile($path) {
        $doc = new self();
        $doc->load( $path );
        return $doc;
    }

    private function getXpath($new=false)
    {
        if($new==true || !$this->xpath) {
            $this->xpath = new DomXpath($this);
            $rootNamespace = $this->lookupNamespaceUri($this->namespaceURI); // http://www.cdisc.org/ns/odm/v1.3
             // asssign the root namespace to x (could be anything)
            $this->xpath->registerNamespace(self::ROOT_NAMESPACE_ID, $rootNamespace);
        }
        return $this->xpath;
    }


    /**
     * @see https://www.php.net/manual/en/domdocument.registernodeclass.php
     * 
     * example:
     * 
     * $root = $this->blueprint->getElementsByTagName( "Study" )[0];
     * $root = $doc->setRoot('root');
     * $child = $root->appendElement('child');
     * $child->setAttribute('foo', 'bar');
     *
     * @param string $name
     * @return void
     */
    function setRoot($name)
    {
        return $this->appendChild(new Element($name));
    }

    /**
     * use xpath to extract repeating
     * instruments from the document
     *
     * @return array
     */
    function getRepeatingForms()
    {
        $xpath = $this->getXpath();
        $elements = $xpath->query('//redcap:RepeatingInstrument'); //DOMNodeList
        $data = [];
        foreach ($elements as $node) {
            $attributes = [
                'name' => $name = $node->getAttribute('redcap:RepeatInstrument'),
                'custom_label' => $node->getAttribute('redcap:CustomLabel'),
                'event' => $node->getAttribute('redcap:UniqueEventName'),
            ];
            $data[$name] = $attributes;
        }
        return $data;
    }

    /**
     * since the nodes we are trying to get use a default namespace, without a prefix,
     * using plain XPath, you can only acesss them by the local-name() and namespace-uri() attributes.
     * Examples: //*[local-name()="HelloWorldResult"]/text()
     *
     * @return array
     */
    function getForms()
    {
        $xpath = $this->getXpath();
        // $elements = $xpath->query('//redcap:RepeatingInstrument'); //DOMNodeList
        $elements = $xpath->query('//x:FormDef'); //DOMNodeList
        $data = [];
        foreach ($elements as $node) {
            $attributes = [
                'name' => $name = $node->getAttribute('redcap:FormName'),
                'path' =>  $node->getNodePath(),
                'OID' => $node->getAttribute('OID'),
                'label' => $node->getAttribute('Name'),
            ];
            $data[] = $attributes;
        }
        return $data;
    }

    /**
     *
     * @param string $formName
     * @return Element|false
     */
    function getFormNodeByName($formName)
    {
        $xpath = $this->getXpath();
        $list = $xpath->query(sprintf('//x:FormDef[@redcap:FormName="%s"]', $formName)); //DOMNodeList
        if($list->length > 0) return $list->item(0);
        return false;
    }

    /**
     * get the field used as primary key (it is the very first one in the project definitions)
     * 
     * @return string|false
     */
    function getPrimaryKey()
    {
        $xpath = $this->getXpath();
        $list = $xpath->query('//x:ItemGroupDef/x:ItemRef'); //DOMNodeList
        if($list->length === 0) return false;
        /** @var Element $firstNode */
        $firstNode = $list->item(0);
        $primaryKey = $firstNode->getAttribute('redcap:Variable');
        return $primaryKey;
    }
    

    /**
     * get the fields of a form using it's name (e.g. 'Project Settings')
     *
     * @param string $formName
     * @return array [groupName, name, mandatory, choices, dataType, fieldType, validationType, label]
    */
    function getFormVariables($formName)
    {
        $xpath = $this->getXpath();

        $choicesToString = function($choices) {
            $strings = [];
            foreach ($choices as $choice) {
                $value = $choice['value'] ?? '';
                $label = $choice['label'] ?? '';
                $strings[] = $choiceToString = sprintf('%s, %s', $value, $label);
            }
            return implode(self::ENUM_AS_STRING_SEPARATOR, $strings);
        };

        $getTinyInt = function($value) {
            $booleanValue = boolval(filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)); // Returns true for "1", "true", "on" and "yes".
            $booleanValue = $booleanValue ?: preg_match('/y/i', $value); // "y"
            return intval($booleanValue);
        };

        $getLabel = function($itemDef) use($xpath) {
            $label = $xpath->query('.//x:TranslatedText', $itemDef)->item(0)->nodeValue ?: ''; 
            return $label;
        };
        
        $getRangeValue = function($itemDef, $comparator) use($xpath) {
            $validComparators = ['GE','LE',];
            if(!in_array($comparator, $validComparators)) throw new Exception("'{$comparator}' is not a valid comparator", 400);
            try {
                $query = sprintf('.//x:RangeCheck[@Comparator="%s"]/CheckValue', $comparator);
                $DOMNodeList = $xpath->query($query, $itemDef)->item(0);
                if(is_null($DOMNodeList)) return '';
                $value = $DOMNodeList->nodeValue;
                return $value;
            } catch (\Throwable $th) {
                // return empty string if the DOMNodeList selection fails
                return '';
            }
        };

        $getChoices = function($itemDef) use($xpath) {
            $choices = [];
            // $field_type = $itemDef->getAttribute('redcap:FieldType');
            // if(in_array($field_type, ["text", "textarea", "notes", "file", "yesno", "truefalse"])) return $choices; // these types use default choices
            $codeListRef = $xpath->query('.//x:CodeListRef', $itemDef)->item(0);
            if($codeListRef instanceof Element) {
                $choiceOID = $codeListRef->getAttribute('CodeListOID');
                $codeListItems = $xpath->query(sprintf('//x:CodeList[@OID="%s"]/x:CodeListItem', $choiceOID));
                $choices = [];
                foreach($codeListItems as $item){
                    $text = $xpath->query('.//x:TranslatedText', $item)->item(0)->nodeValue;
                    $choice = [
                        'value' => $item->getAttribute('CodedValue'),
                        'label' => $text,
                    ];
                    $choices[] = $choice;
                }
            }
            return $choices;
        };

        $formDef = $this->getFormNodeByName($formName);
        $itemGroupRefs = $xpath->query('./x:ItemGroupRef[ substring(@ItemGroupOID, string-length(@ItemGroupOID) - string-length("_complete") + 1)  != "_complete" ]', $formDef); //DOMNodeList
        $items = [];
        foreach ($itemGroupRefs as $itemGroupRef) {
            $groupFullName = $itemGroupRef->getAttribute('ItemGroupOID');
            /** @var Element $ItemGroupDef */
            $ItemGroupDef = $xpath->query(sprintf('//x:ItemGroupDef[@OID="%s" ]', $groupFullName))->item(0);
            $itemRefs = $xpath->query('./x:ItemRef', $ItemGroupDef);
            $groupName = $ItemGroupDef->getAttribute('Name');

            foreach ($itemRefs as $itemRef) {
                // we use an underscore as a convention to mark private values
                // this is additional data not used when comparing these values to the ones in REDCap 
                $fieldData = [
                    '_groupName' => $groupName,
                    '_choices' => [], //defaults to empty
                ];
                
                // PLEASE NOTE: the keys are the same used in REDCap as keys for the variable attributes
                $fieldData['field_name'] = $variableName = $itemRef->getAttribute('redcap:Variable');
                
                /** @var Element $itemDef */
                $itemDef = $xpath->query(sprintf('//x:ItemDef[@OID="%s"]', $variableName))->item(0);
                if(!($itemDef instanceof Element)) continue;

                $fieldData['field_label']       = $getLabel($itemDef); 
                $fieldData['field_phi']         = $getTinyInt($itemDef->getAttribute('redcap:Identifier'));
                $fieldData['field_type']        = $itemDef->getAttribute('redcap:FieldType');
                $fieldData['field_note']        = $itemDef->getAttribute('redcap:FieldNote');
                $fieldData['val_type']          = $itemDef->getAttribute('redcap:TextValidationType');
                $fieldData['field_req']         = $getTinyInt($itemDef->getAttribute('redcap:RequiredField'));
                $fieldData['field_annotation']  = $itemDef->getAttribute('redcap:FieldAnnotation');
                // get min and max
                $fieldData['val_min']           = $getRangeValue($itemDef, 'GE');
                $fieldData['val_max']           = $getRangeValue($itemDef, 'LE');
                // get choices
                $fieldData['_choices']          = $choices = $getChoices($itemDef); // this is a private value (note the underscore)
                $fieldData['element_enum']       = $choicesToString($choices);

                $items[] = $fieldData;
            }
        }
        return $items;
    }

    function getSectionHeaders($formName)
    {
        $xpath = $this->getXpath();
        $formDef = $this->getFormNodeByName($formName);
        $formLabel = $formDef->getAttribute('Name');
        $itemGroupRefs = $xpath->query('./x:ItemGroupRef[ substring(@ItemGroupOID, string-length(@ItemGroupOID) - string-length("_complete") + 1)  != "_complete" ]', $formDef); //DOMNodeList
        $sectionHeaders = [];
        foreach ($itemGroupRefs as $itemGroupRef) {
            $groupFullName = $itemGroupRef->getAttribute('ItemGroupOID');
            /** @var Element $ItemGroupDef */
            $ItemGroupDef = $xpath->query(sprintf('//x:ItemGroupDef[@OID="%s" ]', $groupFullName))->item(0);
            $groupName = $ItemGroupDef->getAttribute('Name');
            // a section header has a name different from the form label. there is also a "Form Status group that we ignore"
            if($groupName != $formLabel) {
                $associatedVariable = end(explode('.',$groupFullName)); // final part matches the redcap:Variable of the first ItemRef child
                $sectionHeader = [
                    'redcapField' => $associatedVariable,
                    'text' => $groupName,
                ];
                $sectionHeaders[] = $sectionHeader;
            }   
        }
        return $sectionHeaders;
    }

    /**
     * the blueprint setting array for a variable
     * contains private values that we do not want to compare with
     * a REDCap variable. those private items are identified by keys preceeded by an underscore
     *
     * @param string $key
     * @return boolean
     */
    public static function isVariablePrivateSetting($key) {
        return preg_match('/^_/', $key);
    }

    
    /**
     * alternative version of `getRepeatingInstruments`
     * that uses getElementsByTagName instead of xpath
     * and transforms the results from DOMNodeList to iterator
     *
     * @return array
     */
    function getRepeatingInstrumentsNamesAlt() {
        $useIterator = function($nodeList) {
           $array = iterator_to_array($nodeList);
           $form_names = array_map(function($node) {
              $b = $node->getAttribute('redcap:RepeatInstrument'); // will be 'this item'
              return $b;
           }, $array);
           return $form_names;
        };
        $elements = $this->getElementsByTagName("RepeatingInstrument");
        $form_names = $useIterator($elements);
        return $form_names;
    }

    /**
     * experimenting with xpath
     *
     * @return void
     */
    function search()
    {
        $xpath = $this->getXpath();
        $elements = $xpath->query('//*[@Name]');
        $elements1 = $xpath->query('//*[@redcap:RepeatInstrument]');
        // traverse all results
        
        foreach ($xpath->query('//*[@redcap:RepeatInstrument]') as $rowNode) {
           $a = $rowNode->nodeValue; // will be 'this item'
           $b = $rowNode->getAttribute('redcap:RepeatInstrument'); // will be 'this item'
        }
    }

}