<?php

namespace Tests\Vanderbilt\REDCap\Classes\Fhir\TokenManager;

use DateTime;
use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\FhirTokenDTO;

class FhirTokenDTOTest extends TestCase
{
    private FhirTokenDTO $token;

    protected function setUp(): void
    {
        $this->token = new FhirTokenDTO();
    }

    // Test basic getters and setters
    public function testPatientGetterAndSetter(): void
    {
        $patient = 'patient123';
        $this->token->setPatient($patient);
        $this->assertEquals($patient, $this->token->getPatient());
    }

    public function testMrnGetterAndSetter(): void
    {
        $mrn = 'MRN456789';
        $this->token->setMrn($mrn);
        $this->assertEquals($mrn, $this->token->getMrn());
    }

    public function testTokenOwnerGetterAndSetter(): void
    {
        $owner = 'user123';
        $this->token->setTokenOwner($owner);
        $this->assertEquals($owner, $this->token->getTokenOwner());
    }

    public function testAccessTokenGetterAndSetter(): void
    {
        $accessToken = 'access_token_123';
        $this->token->setAccessToken($accessToken);
        $this->assertEquals($accessToken, $this->token->getAccessToken());
    }

    public function testRefreshTokenGetterAndSetter(): void
    {
        $refreshToken = 'refresh_token_456';
        $this->token->setRefreshToken($refreshToken);
        $this->assertEquals($refreshToken, $this->token->getRefreshToken());
    }

    public function testEhrIdGetterAndSetter(): void
    {
        $ehrId = 'ehr_789';
        $this->token->setEhrId($ehrId);
        $this->assertEquals($ehrId, $this->token->getEhrId());
    }

    // Test expiration date handling
    public function testExpirationSetterWithDateTime(): void
    {
        $dateTime = new DateTime('2025-12-31 23:59:59');
        $this->token->setExpiration($dateTime);
        $this->assertEquals($dateTime, $this->token->getExpiration());
    }

    public function testExpirationSetterWithString(): void
    {
        $dateString = '2025-12-31 23:59:59';
        $this->token->setExpiration($dateString);
        
        $expectedDateTime = new DateTime($dateString);
        $this->assertEquals($expectedDateTime, $this->token->getExpiration());
    }

    public function testSetExpirationFromSeconds(): void
    {
        $seconds = 3600; // 1 hour
        $this->token->setExpirationFromSeconds($seconds);
        
        $now = new DateTime();
        $expected = clone $now;
        $expected->add(new \DateInterval("PT{$seconds}S"));
        
        // Allow for small time differences (within 5 seconds)
        $actualExpiration = $this->token->getExpiration();
        $diff = abs($expected->getTimestamp() - $actualExpiration->getTimestamp());
        $this->assertLessThan(5, $diff, 'Expiration time should be within 5 seconds of expected');
    }

    // Test validity checks
    public function testIsValidWithNoAccessToken(): void
    {
        $this->token->setExpiration(new DateTime('+1 hour'));
        $this->assertFalse($this->token->isValid());
    }

    public function testIsValidWithAccessTokenButNoExpiration(): void
    {
        $this->token->setAccessToken('valid_token');
        $this->assertTrue($this->token->isValid());
    }

    public function testIsValidWithAccessTokenAndFutureExpiration(): void
    {
        $this->token->setAccessToken('valid_token');
        $this->token->setExpiration(new DateTime('+1 hour'));
        $this->assertTrue($this->token->isValid());
    }

    public function testIsValidWithAccessTokenAndPastExpiration(): void
    {
        $this->token->setAccessToken('valid_token');
        $this->token->setExpiration(new DateTime('-1 hour'));
        $this->assertFalse($this->token->isValid());
    }

    public function testIsExpired(): void
    {
        $this->token->setAccessToken('valid_token');
        $this->token->setExpiration(new DateTime('-1 hour'));
        $this->assertTrue($this->token->isExpired());
        
        $this->token->setExpiration(new DateTime('+1 hour'));
        $this->assertFalse($this->token->isExpired());
    }

    // Test status handling
    public function testStatusWithNoAccessToken(): void
    {
        $this->token->setExpiration(new DateTime('+1 hour'));
        $this->assertEquals(FhirTokenDTO::STATUS_INVALID, $this->token->getStatus());
    }

    public function testStatusWithValidToken(): void
    {
        $this->token->setAccessToken('valid_token');
        $this->token->setExpiration(new DateTime('+1 hour'));
        $this->assertEquals(FhirTokenDTO::STATUS_VALID, $this->token->getStatus());
    }

    public function testStatusWithExpiredToken(): void
    {
        $this->token->setAccessToken('expired_token');
        $this->token->setExpiration(new DateTime('-1 hour'));
        $this->assertEquals(FhirTokenDTO::STATUS_EXPIRED, $this->token->getStatus());
    }

    public function testStatusWithValidTokenNoExpiration(): void
    {
        $this->token->setAccessToken('valid_token');
        $this->assertEquals(FhirTokenDTO::STATUS_VALID, $this->token->getStatus());
    }

    public function testManualStatusSetting(): void
    {
        $this->token->setStatus(FhirTokenDTO::STATUS_FORBIDDEN);
        $this->assertEquals(FhirTokenDTO::STATUS_FORBIDDEN, $this->token->getStatus());
    }

    public function testStatusUpdatesWhenAccessTokenSet(): void
    {
        // Start with invalid status (no access token)
        $this->assertEquals(FhirTokenDTO::STATUS_INVALID, $this->token->getStatus());
        
        // Add access token - should become valid
        $this->token->setAccessToken('valid_token');
        $this->assertEquals(FhirTokenDTO::STATUS_VALID, $this->token->getStatus());
    }

    public function testStatusUpdatesWhenExpirationSet(): void
    {
        $this->token->setAccessToken('valid_token');
        
        // Set future expiration - should be valid
        $this->token->setExpiration(new DateTime('+1 hour'));
        $this->assertEquals(FhirTokenDTO::STATUS_VALID, $this->token->getStatus());
        
        // Set past expiration - should be expired
        $this->token->setExpiration(new DateTime('-1 hour'));
        $this->assertEquals(FhirTokenDTO::STATUS_EXPIRED, $this->token->getStatus());
    }

    // Test edge cases
    public function testExpirationAtExactCurrentTime(): void
    {
        $this->token->setAccessToken('valid_token');
        $now = new DateTime();
        $this->token->setExpiration($now);
        
        // Token should be considered expired if expiration is exactly now
        // (depends on microsecond timing, but generally should be expired)
        $this->assertFalse($this->token->isValid());
    }

    public function testToStringMethod(): void
    {
        // With no access token
        $this->assertEquals('', (string) $this->token);
        
        // With access token
        $accessToken = 'test_access_token';
        $this->token->setAccessToken($accessToken);
        $this->assertEquals($accessToken, (string) $this->token);
    }

    // Test status constants
    public function testStatusConstants(): void
    {
        $this->assertEquals('valid', FhirTokenDTO::STATUS_VALID);
        $this->assertEquals('expired', FhirTokenDTO::STATUS_EXPIRED);
        $this->assertEquals('forbidden', FhirTokenDTO::STATUS_FORBIDDEN);
        $this->assertEquals('invalid', FhirTokenDTO::STATUS_INVALID);
        $this->assertEquals('revoked', FhirTokenDTO::STATUS_REVOKED);
        $this->assertEquals('pending', FhirTokenDTO::STATUS_PENDING);
        $this->assertEquals('unknown', FhirTokenDTO::STATUS_UNKNOWN);
    }

    // Test complete token lifecycle
    public function testCompleteTokenLifecycle(): void
    {
        // Create a fresh token
        $this->token->setPatient('patient123');
        $this->token->setMrn('MRN456');
        $this->token->setTokenOwner('user789');
        $this->token->setAccessToken('access_token_abc');
        $this->token->setRefreshToken('refresh_token_def');
        $this->token->setEhrId('ehr_123');
        $this->token->setExpirationFromSeconds(3600); // 1 hour
        
        // Verify all properties
        $this->assertEquals('patient123', $this->token->getPatient());
        $this->assertEquals('MRN456', $this->token->getMrn());
        $this->assertEquals('user789', $this->token->getTokenOwner());
        $this->assertEquals('access_token_abc', $this->token->getAccessToken());
        $this->assertEquals('refresh_token_def', $this->token->getRefreshToken());
        $this->assertEquals('ehr_123', $this->token->getEhrId());
        
        // Token should be valid
        $this->assertTrue($this->token->isValid());
        $this->assertFalse($this->token->isExpired());
        $this->assertEquals(FhirTokenDTO::STATUS_VALID, $this->token->getStatus());
        
        // String representation should be the access token
        $this->assertEquals('access_token_abc', (string) $this->token);
    }

    // Test data provider for various expiration scenarios
    /**
     * @dataProvider expirationScenarioProvider
     */
    public function testExpirationScenarios(string $timeModifier, bool $expectedValid, string $expectedStatus): void
    {
        $this->token->setAccessToken('test_token');
        $this->token->setExpiration(new DateTime($timeModifier));
        
        $this->assertEquals($expectedValid, $this->token->isValid());
        $this->assertEquals($expectedStatus, $this->token->getStatus());
    }

    public function expirationScenarioProvider(): array
    {
        return [
            'Future expiration (1 hour)' => ['+1 hour', true, FhirTokenDTO::STATUS_VALID],
            'Future expiration (1 day)' => ['+1 day', true, FhirTokenDTO::STATUS_VALID],
            'Future expiration (1 minute)' => ['+1 minute', true, FhirTokenDTO::STATUS_VALID],
            'Past expiration (1 hour)' => ['-1 hour', false, FhirTokenDTO::STATUS_EXPIRED],
            'Past expiration (1 day)' => ['-1 day', false, FhirTokenDTO::STATUS_EXPIRED],
            'Past expiration (1 minute)' => ['-1 minute', false, FhirTokenDTO::STATUS_EXPIRED],
        ];
    }
}