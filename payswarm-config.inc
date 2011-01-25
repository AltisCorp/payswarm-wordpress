<?php

/**
 * Updates the given options that exist and are valid in the $_POST environment.
 * 
 * @package payswarm
 * @since 1.0
 * 
 * @param array $options An array of strings that are the names of the option 
 *    values that exist in the $_POST environment variable.
 * @return true if setting all options was successful, false otherwise.
 */
function payswarm_config_update($options)
{
   $rval = true;
   
   // for each option in the list of options, check to see if it exists in
   // the $_POST variable and that it's length is greater than 1
   foreach($options as $option)
   {
      if(isset($_POST[$option]))
      {
         if(strlen($_POST[$option]) > 1)
         {
            update_option($option, $_POST[$option]);
         }
         else
         {
            $rval = false;
         }
      }
      else
      {
         $rval = false;
      }
   }

   return $rval;
}

/**
 * Cleans the '<' and '>' off of a URL from a JSON-LD object.
 * 
 * @return a URL with the '<' and '>' stripped, if they existed.
 */
function payswarm_config_strip_jsonld_url($url)
{
   $rval = $url;
   $length = strlen($rval);
   
   // check to see if the $url starts and ends with < and > characters, 
   // respectively, and if it does, strip them from the return value
   if($rval[0] === '<' && $rval[$length - 1] === '>')
   {
      $rval = substr($rval, 1, $length - 2);
   }
   
   return $rval;
}

/**
 * Updates the WordPress PaySwarm options given a PaySwarm client configuration
 * retrieved from a PaySwarm authority, a URL-based property in the 
 * configuration and the name of the option to use when saving the value.
 * 
 * @param array $config an associative array of config values returned by a
 *    PaySwarm client config JSON-LD service
 * @param string $property a URL string specifying the property in the JSON-LD
 *    object.
 * @param string $option the name of the WordPress option to update.
 */
function payswarm_config_set_value($config, $property, $option)
{
   $rval = false;
   
   if(array_key_exists($property, $config))
   {
      $value = payswarm_config_strip_jsonld_url($config[$property]);
      update_option($option, $value);
      
      $rval = true;
   }
   
   return $rval;
}

/**
 * Updates the PaySwarm client configuration given a blob of JSON text.
 *
 * @package payswarm
 * @since 1.0
 *
 * @param string $json A blob of JSON text that is encoded in JSON-LD format,
 *    usually returned by the PaySwarm Authority after hitting the client
 *    configuration URL.
 *
 * @return true if the update was successful, false otherwise.
 */
function payswarm_config_oauth($json)
{
   $rval = true;
   $client_config = json_decode($json, true);
   
   $authorize_ws = "<http://purl.org/payswarm/webservices#oAuthAuthorize>";
   $request_ws = "<http://purl.org/payswarm/webservices#oAuthRequest>";
   $token_ws = "<http://purl.org/payswarm/webservices#oAuthTokens>";
   
   if($client_config != NULL)
   {
      $rval &= payswarm_config_set_value(
         $client_config, $authorize_ws, 'payswarm_authorize_url');
      $rval &= payswarm_config_set_value(
         $client_config, $request_ws, 'payswarm_request_url');
      $rval &= payswarm_config_set_value(
         $client_config, $token_ws, 'payswarm_access_url');
   }
   else
   {
      $rval = false;
   }
   
   return $rval;
}

/**
 * Updates the PaySwarm client configuration given a blob of JSON text.
 *
 * @package payswarm
 * @since 1.0
 *
 * @param string $json A blob of JSON text that is encoded in JSON-LD format,
 *    usually returned by the PaySwarm Authority after hitting the client
 *    configuration URL.
 *
 * @return true if the update was successful, false otherwise.
 */
function payswarm_config_endpoints($json)
{
   $rval = true;
   $client_config = json_decode($json, true);
   
   $authorize_ws = "<http://purl.org/payswarm/webservices#oAuthAuthorize>";
   $request_ws = "<http://purl.org/payswarm/webservices#oAuthRequest>";
   $token_ws = "<http://purl.org/payswarm/webservices#oAuthTokens>";
   $contract_ws = "<http://purl.org/payswarm/webservices#oAuthContracts>";
   $license_ws = "<http://purl.org/payswarm/webservices#oAuthLicenses>";
   $keys_ws = "<http://purl.org/payswarm/webservices#oAuthKeys>";
   $preferences_ws = "<http://purl.org/payswarm/webservices#oAuthPreferences>";
   
   if($client_config != NULL)
   {
      $rval &= payswarm_config_set_value(
         $client_config, $authorize_ws, 'payswarm_authorize_url');
      $rval &= payswarm_config_set_value(
         $client_config, $request_ws, 'payswarm_request_url');
      $rval &= payswarm_config_set_value(
         $client_config, $token_ws, 'payswarm_access_url');
      $rval &= payswarm_config_set_value(
         $client_config, $contract_ws, 'payswarm_contracts_url');
      $rval &= payswarm_config_set_value(
         $client_config, $license_ws, 'payswarm_licenses_url');
      $rval &= payswarm_config_set_value(
         $client_config, $keys_ws, 'payswarm_keys_url');
      $rval &= payswarm_config_set_value(
         $client_config, $preferences_ws, 'payswarm_preferences_url');
   }
   else
   {
      $rval = false;
   }
   
   return $rval;
}

?>