<?php
namespace ExternalModules;

class Form extends ProjectChild
{
    /**
     * @return (int|string)[]
     */
    function getFieldNames(){
        return array_keys(\REDCap::getDataDictionary($this->getProject()->getProjectId(), 'array', false, null, $this->getName()));
    }
}