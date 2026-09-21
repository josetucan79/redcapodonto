<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Providers;

use Vanderbilt\REDCap\Classes\Rewards\ClientMiddlewares\TokenAuth;
use Vanderbilt\REDCap\Classes\Rewards\Facades\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\Facades\ProjectSettings;
use Vanderbilt\REDCap\Classes\Rewards\Facades\SystemSettings;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectEntity;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\TangoProvider;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectProviderEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProviderEntity;
use Vanderbilt\REDCap\Classes\Rewards\ServiceProviders\AccessTokenProvider;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\Entities\TangoProjectSettingsVO;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\Entities\TangoSystemSettingsVO;

/**
 * Class RewardsProviderConstants
 * Contains constants for the names of all supported rewards providers.
 */
class RewardsProvider
{
    const TANGO = 'Tango';
    const TREMENDOUS = 'Tremendous';
    const GIFTBIT = 'Giftbit';

    /**
     *
     * @param int $project_id
     * @return RewardProviderInterface
     */
    public static function make($project_id) {
        $entityManager = EntityManager::get();
        $projectReference = $entityManager->getReference(ProjectEntity::class, $project_id);
        $projectProvider = $entityManager->getRepository(ProjectProviderEntity::class)->findOneBy(['project' => $projectReference]);
        if(!$projectProvider) return;
        
        $providerID = $projectProvider->getProviderId();
        $provider = $entityManager->find(ProviderEntity::class, $providerID);
        if(!$provider) return;
        $providername = $provider->getProviderName();
        switch ($providername) {
            case self::TANGO:
                /** @var TangoSystemSettingsVO $systemSettings */
                $systemSettings = SystemSettings::get(RewardsProvider::TANGO);
                /** @var TangoProjectSettingsVO $projectSettings */
                $projectSettings = ProjectSettings::get($project_id);

                if ($projectSettings->getTokenUrl() && $projectSettings->getBaseUrl()) {
                    // Use project settings
                    $tokenURL = $projectSettings->getTokenUrl();
                    $baseURL = $projectSettings->getBaseUrl();
                    $clientID = $projectSettings->getClientId();
                    $clientSecret = $projectSettings->getClientSecret();
                } else {
                    // Fall back to system settings
                    $tokenURL = $systemSettings->getTokenUrl();
                    $baseURL = $systemSettings->getBaseUrl();
                    $clientID = $systemSettings->getClientId();
                    $clientSecret = $systemSettings->getClientSecret();
                }
                $groupID = $projectSettings->getGroupIdentifier();
                $accountID = $projectSettings->getCampaignIdentifier();

                $options = [
                    'scope'=> 'raas.all',
                    // 'audience'=> $this->baseURL, // 'https://api.tangocard.com/'
                    'audience'=> 'https://api.tangocard.com/',
                    'grant_type'=> 'client_credentials',
                ];
                $baseURL = static::ensureTrailingSlash($baseURL);
                $tokenURL = static::ensureTrailingSlash($tokenURL);

                $tokenProvider = new AccessTokenProvider($project_id, $providerID, $entityManager);
                $tokenMiddleware = new TokenAuth($tokenURL, $clientID, $clientSecret, $tokenProvider, $options);

                // $auth = new ClientCredentialsAuth($tokenURL, $clientID, $clientSecret, $module);
                $provider = new TangoProvider($baseURL, $groupID, $accountID, [$tokenMiddleware()]);
                break;

            case self::TREMENDOUS:
                break;
            case self::GIFTBIT:
                break;
            default:
                break;
        }
        return $provider;
    }

    public static function ensureTrailingSlash($url)
    {
        // Remove any trailing slashes
        $url = rtrim($url, '/');
        // Add a single trailing slash
        return $url . '/';
    }

}