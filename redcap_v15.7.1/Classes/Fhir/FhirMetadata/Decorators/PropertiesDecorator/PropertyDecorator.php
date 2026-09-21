<?php
namespace Vanderbilt\REDCap\Classes\Fhir\FhirMetadata\Decorators\PropertiesDecorator;

use Vanderbilt\REDCap\Classes\Fhir\Resources\AbstractResource as FhirResource;

abstract class PropertyDecorator implements DataFunctionsInterface
{
    /**
    *
    * @param FhirResource $resource
    * @param string $functionKey
    * @param mixed ...$additionalParams
    * @return mixed
    */
    public function callDataFunction($resource, $functionKey, ...$additionalParams)
    {
        // Get the dataFunctions map
        $dataFunctions = $this->dataFunctions();
        $closure = $dataFunctions[$functionKey] ?? null;
        
        if (!$closure) throw new \Exception("Function '$functionKey' does not exist.");
        if (!is_callable($closure)) throw new \Exception("Function '$functionKey' is not a function.");
        
        return call_user_func_array($closure, array_merge([$resource], $additionalParams));
    }

    // abstract function dataFunctions(): array;
}
