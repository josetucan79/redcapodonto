<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Utility\TemplateEngine;

class TemplateEngineTest extends TestCase
{


	public function testRenderCallbackWithParameters() {
		$template = "This is some text with a name [name] and a date [date:l\, F j\, Y].";
		$user = [
			'name' => 'Marlon',
			'dob' => '1970-12-03 14:35:32',
		];
		$placeholders = $this->getPlaceholders($user);

		$text = TemplateEngine::render($template, $placeholders);
		$this->assertSame('This is some text with a name Marlon and a date Thursday, December 3, 1970.', $text);
	}

	public function testRenderCallbackWithoutParameters() {
		$template = "This is some text with a name [add_something_to_name] and a date [date:l\, F j\, Y].";
		$user = [
			'name' => 'Marlon',
			'dob' => '1970-12-03 14:35:32',
		];
		$placeholders = $this->getPlaceholders($user);

		$text = TemplateEngine::render($template, $placeholders);
		$this->assertSame('This is some text with a name Marlon-something and a date Thursday, December 3, 1970.', $text);
	}

	public function testRenderNoMatchingPlaceholder() {
		$template = "This is some text with a name [invalid] and a date [another_invalid:l\, F j\, Y].";
		$user = [
			'name' => 'Marlon',
			'dob' => '1970-12-03 14:35:32',
		];
		$placeholders = $this->getPlaceholders($user);

		$text = TemplateEngine::render($template, $placeholders);
		$this->assertSame('This is some text with a name [invalid] and a date [another_invalid:l\, F j\, Y].', $text);
	}

	protected function getPlaceholders($user) {
		return [
			'name' => $user['name'],
			'date' => function($format) use($user) {
				try {
					// Create a DateTime object from the date string
					$date = new DateTime($user['dob']);
					
					// Format the date according to the specified format
					return $date->format($format);
				} catch (Exception $e) {
					// Handle any exceptions (like invalid date formats)
					return "Invalid date: " . $e->getMessage();
				}
			},
			'test' => 'test 123',
			'add_something_to_name' => function() use($user) {
				return $user['name'].'-something';
			},
		];
	}

	
	
}

