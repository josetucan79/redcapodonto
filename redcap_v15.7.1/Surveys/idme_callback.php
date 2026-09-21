<?php


// Disable authentication so this page can be used as general documentation
define("NOAUTH", true);
include_once dirname(dirname(__FILE__)) . '/Config/init_global.php';


$provider = new \League\OAuth2\Client\Provider\GenericProvider([
    'clientId'                => '598785912aa930a14bb422c886c70f25',
    'clientSecret'            => '0689340aca0165e5623a9f0fc7b89db7',
    'redirectUri'             => 'http://localredcap:8080/Redcap/redcap_v11.1.0/Surveys/idme_callback.php?pid=621',
    'urlAuthorize'            => 'https://api.id.me/oauth/authorize',
    'urlAccessToken'          => 'https://api.id.me/oauth/token',
    'urlResourceOwnerDetails' => 'https://api.id.me/api/public/v3/attributes.json'
]);
if (!isset($_GET['code'])) {
    // Fetch the authorization URL from the provider; this returns the
    // urlAuthorize option and generates and applies any necessary parameters
    // (e.g. state).
    $authorizationUrl = $provider->getAuthorizationUrl();

    // Get the state generated for you and store it to the session.
    $_SESSION['oauth2state'] = $provider->getState();

    // Redirect the user to the authorization URL.
    //echo filter_var($authorizationUrl, FILTER_SANITIZE_URL); die;
    header('Location: ' . filter_var($authorizationUrl, FILTER_SANITIZE_URL));
    exit;

} else {
    try {
        // Try to get an access token using the authorization code grant.
        // Pass in the affiliation in the scope
        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code'], 'scope' => 'military'
        ]);

        // // Using the access token, we may look up details about the user
        $resourceOwner = $provider->getResourceOwner($accessToken);

        // // The JSON payload of the response
        $_SESSION['payload'] = json_encode($resourceOwner->toArray());

        header('Location: ' . '/');
        exit;

    } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

        // Failed to get the access token or user details.
        exit($e->getMessage());
    }
}
