<?php namespace ExternalModules;

class HeaderTaintAnalyzer
{
    private $urlEncoded = false;

    /**
     * @psalm-suppress PossiblyUnusedMethod
     */
    static function shouldIgnore($issue){
        return (new HeaderTaintAnalyzer())->process($issue);
    }

    private function process($issue){
        // Instead OF $lastLabel, does psalm already store the position in the string where the tainted var starts?
        $lastLabel = null;
        foreach($issue->journey as $step){
            $label = $step['label'];

            if(static::doesStepEscapeTaint($step, $lastLabel)){
                return true;
            }

            if($label !== 'concat'){
                $lastLabel = $label;
            }
        }

        return false;
    }

    private function doesStepEscapeTaint($step, $lastLabel){
        if($step['label'] === 'call to urlencode'){
            $this->urlEncoded = true;
        }

        $location = $step['location'];
        if($location === null || empty($lastLabel)){
            return false;
        }

        $text = strtolower($location->getSelectedText());
        $text = str_replace(' ', '', $text);

        if(
            /**
             * A tainted 'filename' is often included next in this header.
             * This is considered a low risk expected use case, and should be ignored.
             */
            str_contains($text, 'content-disposition:attachment')
        ){
            return true;
        }

        $parts = explode($lastLabel, $text);
        if(count($parts) === 2 && $parts[0] !== ''){
            // Something exists before the tainted variable on this line.
            if($this->urlEncoded && str_contains($parts[0], 'location:')){
                /**
                 * This is a location header, and the tainted value has been urlencoded.
                 * This should be safe.
                 */
                return true;
            }
        }

        return false;
    }
}