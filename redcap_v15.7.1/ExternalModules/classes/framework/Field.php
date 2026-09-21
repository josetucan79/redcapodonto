<?php
namespace ExternalModules;

class Field extends ProjectChild
{
   function getType(){
        $fieldName = $this->getName();
        $type = $this->getProject()->getREDCapProjectObject()->metadata[$fieldName]['element_type'] ?? null;

        if(empty($type)){
            throw new \Exception(ExternalModules::tt('em_errors_144', $fieldName, $this->getProject()->getProjectId()));
        }

        return $type;
    }
}