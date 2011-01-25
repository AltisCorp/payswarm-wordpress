<?php
require_once('../../../wp-config.php');
require_once('payswarm-session.inc');
require_once('payswarm-database.inc');
require_once('payswarm-oauth.inc');

// FIXME: Check to ensure connection was made via SSL connection

// the session ID that is associated with this request
$session = 'payswarm-registration';

// get the scope that is associated with this request
$scope = 'payswarm-registration';

// retrieve the PaySwarm token, creating it if it doesn't already exist
$ptoken = payswarm_database_get_token($session, $scope, true);

// If we are authorizing, then there should be an oauth_token, if not, start
// the process over.
if($ptoken['state'] === 'authorizing' && !isset($_GET['oauth_token']))
{
   $ptoken['state'] = 'initializing';
}

try
{
   // setup the OAuth client
   $client_id = get_option('payswarm_client_id');
   $client_secret = get_option('payswarm_client_secret');
   $oauth = new OAuth($client_id, $client_secret, OAUTH_SIG_METHOD_HMACSHA1, 
      OAUTH_AUTH_TYPE_FORM);

   // FIXME: Disable debug output for OAuth for production software
   $oauth->enableDebug();

   // FIXME: Enable SSL checks for production software
   $oauth->disableSSLChecks();

   // check the state of the payment token
   if($ptoken['state'] === 'initializing')
   {
      $request_url = get_option('payswarm_request_url') . "?scope=$scope";
      $details = '{}';

      payswarm_oauth1_initialize(
         $oauth, $session, $scope, $request_url, $details);
   }
   else if($ptoken['state'] === 'authorizing')
   {
      // State 1 - Handle callback from PaySwarm
      if(array_key_exists('oauth_verifier', $_GET))
      {
         // get and store an access token
         $access_url = get_option('payswarm_access_url');
         $oauth->setToken($_GET['oauth_token'], $ptoken['secret']);
         $details = '{}';

         payswarm_oauth1_authorize(
            $oauth, $session, $scope, $access_url, $details);
      }
      else
      {
         // if access was denied, print out an appropriate error
         payswarm_registration_denied($post);
      }
   }
   else if($ptoken['scope'] === 'payswarm-registration' && 
      $ptoken['state'] === 'valid')
   {
      $oauth = new OAuth($client_id, $client_secret, OAUTH_SIG_METHOD_HMACSHA1);
      // FIXME: Disable debug output for OAuth for production software
      // FIXME: Enable SSL checks for production software
      $oauth->enableDebug();
      $oauth->disableSSLChecks();

      // State: authorized - we can just use the stored access token
      $oauth->setToken($ptoken['token'], $ptoken['secret']);

      // Make the call to the PaySwarm Authority to get the complete
      // PaySwarm endpoint information
      $config_url = get_option('payswarm_client_config');
      $oauth->fetch($config_url, array(), OAUTH_HTTP_METHOD_GET);
      $response_info = $oauth->getLastResponseInfo();
      $json = $oauth->getLastResponse();
      payswarm_config_endpoints($json);

      // Get the newly retrieved endpoint URLs
      $keys_url = get_option('payswarm_keys_url');
      $preferences_url = get_option('payswarm_preferences_url');

      // FIXME: Register the public key using the OAuth registration token
      $keys = payswarm_generate_keypair();
      
      // FIXME: Signature error when registering public key
      //$oauth->fetch($keys_url, 
      //   array('publicKey' => $keys['public']), OAUTH_HTTP_METHOD_POST);
      $items = explode('&', $oauth->getLastResponse());
      $public_key_url = 'http://example.com/INVALID-KEY';
      $webid_url = 'http://example.com/INVALID-WEBID';
      foreach($items as $item)
      {
         $kv = explode('=', $item, 2);
         if($kv[0] === 'public_key_url')
         {
            $public_key_url = $kv[1];
         }
         if($kv[0] === 'webid_url')
         {
            $webid_url = $kv[1];
         }
      }
      update_option('payswarm_public_key', $keys['public']);
      update_option('payswarm_private_key', $keys['private']);
      update_option('payswarm_webid_url', $webid_url);
      update_option('payswarm_public_key_url', $public_key_url);

      // FIXME: Get the default license information
      // FIXME: Get the default financial account and currency information
      // FIXME: OAuth error when using this call, defaults to POST?
      //$oauth->fetch($preferences_url, NULL, OAUTH_HTTP_METHOD_GET);
      $items = explode('&', $oauth->getLastResponse());
      $default_currency = 'INVALID';
      $default_account = 'http://example.com/INVALID-ACCOUNT';
      $default_license = 'http://example.com/INVALID-LICENSE';
      foreach($items as $item)
      {
         $kv = explode('=', $item, 2);
         if($kv[0] === 'default_currency')
         {
            $default_currency = $kv[1];
         }
         if($kv[0] === 'default_destination_account')
         {
            $default_account = $kv[1];
         }
         if($kv[0] === 'default_license')
         {
            $default_license = $kv[1];
         }
      }
      update_option('payswarm_default_license_url', $default_license);
      update_option('payswarm_default_currency', $default_currency);
      update_option('payswarm_destination_account', $default_account);
      
      // FIXME: Get the default license and license hash
      $default_license_hash = "INVALID-LICENSE-HASH";
      // FIXME: Waiting for Lehn to finish implementing this call
      //$oauth->fetch($licenses_url, NULL, OAUTH_HTTP_METHOD_GET);
      update_option('payswarm_default_license_hash', $default_license_hash);
      
      header('Location: ' . admin_url() . 'plugins.php?page=payswarm');
   }
}
catch(OAuthException $E)
{
   $err = json_decode($E->lastResponse);
   print_r('<pre>' . $E . "\nError details: \n" . 
      print_r($err, true) . '</pre>');
}

/**
 * Generates a public-private X509 encoded keys.
 * 
 * @package payswarm
 * @since 1.0
 * 
 * @return Array containing two keys 'public' and 'private' each with the
 *    public and private keys encoded in X509 format.
 */
function payswarm_generate_keypair()
{
   $rval = array();

   // Create the keypair
   $keypair = openssl_pkey_new();

   // Get private key
   openssl_pkey_export($keypair, $privkey);

   // Get public key
   $pubkey = openssl_pkey_get_details($keypair);
   $pubkey = $pubkey["key"];

   // free the keypair
   openssl_free_key($keypair);

   $rval['public'] = $pubkey;
   $rval['private'] = $privkey;
   
   return $rval;
}

function payswarm_access_denied($post)
{
   // FIXME: Unfortunately, this generates a PHP Notice error for
   // WP_Query::$is_paged not being defined. Need to figure out which file
   // declares that variable.
   get_header();

   echo '
<div class="category-uncategorized"> 
  <h2 class="entry-title">Access Denied when Registering</h2> 
  <div class="entry-content"> 
    <p>
      Access to the PaySwarm registration information was denied because this 
      website was not allowed to access your PaySwarm account. This usually 
      happens because you did not allow this website to access your 
      PaySwarm account information by not assigning it a Registration Token.
    </p>

    <p><a href="' . site_url() . "/wp-admin/plugins.php?page=payswarm" . 
      '">Go back to the administrative page</a>.</p>
  </div>
</div>';
   
   get_footer();
}
?>