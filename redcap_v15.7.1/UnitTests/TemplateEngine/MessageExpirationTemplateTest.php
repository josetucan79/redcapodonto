<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Utility\TemplateEngine;
use Vanderbilt\REDCap\Classes\AccountExpirationNotifier\MessageTemplateFactory;
use Vanderbilt\REDCap\Classes\AccountExpirationNotifier\AccountExpirationNotifierFacade;

class MessageExpirationTemplateTest extends TestCase
{


	private function getUser() {
		return $user = [
			'ui_id'=>"2",
			'username'=>"someuser",
			'user_email'=>"some.user@vumc.org",
			'user_email2'=>"",
			'user_email3'=>"",
			'user_phone'=>null,
			'user_phone_sms'=>null,
			'user_firstname'=>"Some",
			'user_lastname'=>"User",
			'user_inst_id'=>"",
			'super_user'=>"1",
			'account_manager'=>"1",
			'access_system_config'=>"1",
			'access_system_upgrade'=>"1",
			'access_external_module_install'=>"1",
			'user_expiration' => '2024-05-01 00:00',
			'user_sponsor' => 'site_admin',
		];
	}

	public function testRenderCallbackWithParameters() {
		global $user_sponsor_dashboard_enable;

		$user_sponsor_dashboard_enable = true;
		$notifier = AccountExpirationNotifierFacade::make();
		$templateFactory = new MessageTemplateFactory();
		$user = $this->getUser();
		$placeholders = $notifier->makePlaceholders($user);

		$message = $templateFactory->getDefaultUserOnlyEmail($user);
		$text = TemplateEngine::render($message, $placeholders);
		$this->assertIsString($text);

		$message1 = $templateFactory->getDefaultUserWithSponsorEmail($user);
		$text1 = TemplateEngine::render($message1, $placeholders);
		$this->assertIsString($text1);
	}



	

	
	
}

