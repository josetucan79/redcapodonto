<?php

namespace Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\States;

use ReflectionClass;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\FhirLauncher;
use Vanderbilt\REDCap\Classes\Fhir\FhirLauncher\Middlewares\MiddlewareInterface;

abstract class State implements StateInterface
{

	protected $middlewares = [];

	/**
	 *
	 * @var FhirLauncher
	 */
	protected $context;

	/**
	 *
	 * @param FhirLauncher $context
	 */
	public function __construct($context)
	{
		$this->context = $context;
	}

	public function run() {
		return;
	}

	/**
	 *
	 * @param MiddlewareInterface $middleware
	 * @return static
	 */
	public function add(MiddlewareInterface $middleware) {
		$this->middlewares[] = $middleware;
		return $this;
	}

	/**
	 *
	 * @return MiddlewareInterface[]
	 */
	public function middlewares() {
		return $this->middlewares;
	}

	public function __toString()
	{
		$reflect = new ReflectionClass($this);
		return $reflect->getShortName();
	}

}