<?php

namespace ExternalModules;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Use this file to add framework methods to twig as necessary.
 * @link https://twig.symfony.com/doc/3.x/advanced.html#creating-an-extension
 */
class FrameworkTwigExtensions extends AbstractExtension
{
	/**
	 * @var Framework
	 */
	private $framework;

	public function __construct(Framework $framework)
	{
		$this->framework = $framework;
	}

	public function getFunctions()
	{
		$twigFunctions = [];
		foreach ($this->getFrameworkMethodNamesForTwig() as $methodName){
			$twigFunctions[] = new TwigFunction($methodName, [$this->framework, $methodName]);
		}
		return $twigFunctions;
	}

	private function getFrameworkMethodNamesForTwig()
	{
		return [
			'getChoiceLabel',
			'getChoiceLabels',
			'getCSRFToken',
			'getDAG',
			'getEventId',
			'getFieldLabel',
			'getFieldNames',
			'getFormsForEventId',
			'getModuleName',
			'getProjectId',
			'getProjectSetting',
			'getPublicSurveyHash',
			'getPublicSurveyUrl',
			'getRecordId',
			'getRecordIdField',
			'getRepeatingForms',
			'getSubSettings',
			'getSystemSetting',
			'getUrl',
			'getUserSetting',
			'isAuthenticated',
			'isModulePage',
			'isPage',
			'isREDCapPage',
			'isRoute',
			'isSuperUser',
			'isSurveyPage',
		];
	}
}
