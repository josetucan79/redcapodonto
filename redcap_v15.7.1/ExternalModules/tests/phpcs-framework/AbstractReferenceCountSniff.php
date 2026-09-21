<?php namespace ExternalModules\Sniffs\Misc;

use PHP_CodeSniffer\Sniffs\Sniff;

abstract class AbstractReferenceCountSniff implements Sniff
{
    private $expectedReferences;
    private $referenceCounts = [];
    
    function __construct($expectedReferences){
        $this->expectedReferences = $expectedReferences;
    }

    function countReference($string){
        if(isset($this->expectedReferences[$string])){
            @$this->referenceCounts[$string]++;
        }
    }

    function __destruct()
    {
        foreach($this->expectedReferences as $name=>$limit){
            $count = $this->referenceCounts[$name] ?? null;
            if($count === null){
                $count = 0;
            }

            if($count !== $limit){
                throw new \Exception("
                    Expected $limit reference(s) to '$name' in the " . get_class($this) . " sniff, but found $count.
                    Please review any recently added/removed references.
                    The counts at the top of this Sniff's class should be updated only if the changes respect the comment for the line with each count.
                ");
            }
        }
    }
}