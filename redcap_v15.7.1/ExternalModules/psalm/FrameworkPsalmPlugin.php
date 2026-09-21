<?php namespace ExternalModules;

use Psalm\Issue\TaintedCallable;
use Psalm\Plugin\EventHandler\Event\BeforeAddIssueEvent;

/**
 * @psalm-suppress UnusedClass
 */
class FrameworkPsalmPlugin implements \Psalm\Plugin\EventHandler\BeforeAddIssueInterface
{
    public static function beforeAddIssue(BeforeAddIssueEvent $event): ?bool
    {
        $issue = $event->getIssue();

        if(is_a($issue, TaintedCallable::class)){
            $inPath = [];
            foreach($issue->journey as $step){
                $location = $step['location'];
                if($location === null){
                    continue;
                }
                
                $text = $location->getSelectedText();

                $inPath[$text] = true;
            }

            $lastStep = $issue->journey[array_key_last($issue->journey)];
            if(
                $lastStep['label'] === '$classNameWithNamespace'
                &&
                isset($inPath['getAdditionalFieldChoices'])
            ){
                return false;
            }
        }

        return null;
    }
}