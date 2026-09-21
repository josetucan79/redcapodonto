<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\AccountExpirationNotifier\AccountExpirationNotifier;
use Vanderbilt\REDCap\Classes\AccountExpirationNotifier\MessageFactory;
use Vanderbilt\REDCap\Classes\AccountExpirationNotifier\MessageTemplateFactory;
use Vanderbilt\REDCap\Classes\AccountExpirationNotifier\Placeholders;

class AccountExpirationNotifierTest extends TestCase
{
	public function setUp(): void
	{
	}

	protected function getMessageFactory() {
		$messageFactory = new class extends MessageFactory {
			/**
			 *
			 * @var TestCase
			 */
			public static $testCase;

			public static function make() {
				$messageMock = self::$testCase->getMockBuilder(Message::class)
					->onlyMethods(['send'])
					->getMock();
				$messageMock->method('send')->willReturnCallback(function () {
					return (bool)random_int(0, 1); // Returns true or false randomly
				});
				
				return $messageMock;
			}
		};
		$messageFactory::$testCase = $this;
		return $messageFactory;
	}

	public function getMessageTemplateFactory() {
		$customUserOnlyText = 'Hello [user_firstname], [user_lastname] - [username], ([user_email])!';
		$customUserWithSponsorText = 'Hello [user_sponsor], we inform you about [user_firstname], [user_lastname] - [username], ([user_email])!';
		return new MessageTemplateFactory($customUserOnlyText, $customUserWithSponsorText);
	}

	public function testFindUsers() {
		// extract($GLOBALS);

        $messageFactory = $this->getMessageFactory();
		$messageTemplateFactory = $this->getMessageTemplateFactory();
									
		$warningDays=[1,2,3,4,10,11,12,13,14];

		$mockNotifier = $this->getMockBuilder(AccountExpirationNotifier::class)
							->setConstructorArgs([$messageFactory, $messageTemplateFactory, $warningDays])
							->onlyMethods(['findEligibleUsersForWarning', 'makePlaceholders'])
							->getMock();
		$mockNotifier->method('makePlaceholders')->willReturnCallback(function($user) {
			return [
				// user info
				Placeholders::USERNAME => $user['username'] ?? '',
				Placeholders::USER_EMAIL => $user['user_email'] ?? '',
				Placeholders::USER_EXPIRATION => $user['user_expiration'] ?? '',
				Placeholders::USER_SPONSOR => $user['user_sponsor'] ?? '',
				Placeholders::USER_FIRSTNAME => $user['user_firstname'] ?? '',
				Placeholders::USER_LASTNAME => $user['user_lastname'] ?? '',
			];
		});
		$mockNotifier->method('findEligibleUsersForWarning')->willReturn($this->getMockUsers());
		
		/** @var AccountExpirationNotifier $mockNotifier */
		$mockNotifier->sendWarnings($users, $sentWarnings, $failedWarnings);
		print("Sent warnings: ".count($sentWarnings).PHP_EOL);
		print("Failed warnings: ".count($failedWarnings).PHP_EOL);

		$this->assertIsArray($users);
	}

	protected function getMockUsers() {
		return [
			[
				'username' => "0003d5a8-e6d7-5008-a6be-e096ac19708d",
				'user_email' => "gag@hev.gh",
				'user_expiration' => "2024-02-14 11:10:00",
				'user_sponsor' => null,
				'user_firstname' => "Ora",
				'user_lastname' => "Kennedy",
			],
			[
				'username' => "0043dfa8-eb70-52a1-bbe0-79adf4023078",
				'user_email' => "cuimade@zal.py",
				'user_expiration' => "2024-02-14 11:10:00",
				'user_sponsor' => 'delacqf',
				'user_firstname' => "Sam",
				'user_lastname' => "Benson",
			]
		];
	}

	




	
}

