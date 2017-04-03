<?php
/**
 * @version    $Id: hqscoutnetauthenticator.php $
 * @package    Joomla.Tutorials
 * @subpackage Plugins
 * @license    GNU/GPL
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die();

/**
 * Authentication Plugin. Based on the example.php plugin in the Joomla! Core installation
 *
 * @package    Joomla.Tutorials
 * @subpackage Plugins
 * @license    GNU/GPL
 */
class plgAuthenticationhqscoutnetauthenticator extends JPlugin
{
    /**
     * This method should handle any authentication and report back to the subject
     * This example uses simple authentication - it checks if the password is the reverse
     * of the username (and the user exists in the database).
     *
     * @access    public
     * @param     array     $credentials    Array holding the user credentials ('username' and 'password')
     * @param     array     $options        Array of extra options
     * @param     object    $response       Authentication response object
     * @return    boolean
     * @since 1.5
     */

    function onUserAuthenticate( $credentials, $options, &$response )
    {
        /*
         * Here you would do whatever you need for an authentication routine with the credentials
         *
         * In this example the mixed variable $return would be set to false
         * if the authentication routine fails or an integer userid of the authenticated
         * user if the routine passes
         */
        $db = JFactory::getDbo();
	    $query = $db->getQuery(true)
		->select('id')
		->from('#__users')
		->where('username=' . $db->quote($credentials['username']) . ' OR email=' . $db->quote($credentials['username']));
	    $db->setQuery($query);
	    $result = $db->loadResult();

	    if (!$result) { //om username eller email INTE finns i joomlas user-tabell
	        $response->status = 'STATUS_FAILURE';
	        $response->error_message = 'Användarnamnet hittades inte. Kontrollera användarnamnet och försök igen.';
	        error_log("HQ: Username or email does not exist in Joomla. Aborting login.", 0);

	    } else { // username eller email finns i joomla -> testa inloggning mot scoutnet..
			$authUrl = $this->params->get('loginUrl')."?".$this->params->get('usernameParameterName')."=".$credentials['username']."&".$this->params->get('passwordParameterName')."=".$credentials['password'];
			$authResult = file_get_contents($authUrl);

			if ($authResult <>"") {
				$authResultObj=json_decode($authResult);	
				// var_dump($authResultObj); //DEBUG
				if (isset($authResultObj->member->member_no)) { //login success
					// echo $authResultObj->token;	//DEBUG
					// echo "Välkommen ".$authResultObj->member->first_name ." ".$authResultObj->member->last_name."!"; //DEBUG
					error_log("HQ Login TRUE for: ".$credentials['username'], 0);
					$authMedlemsnr = $authResultObj->member->member_no; 
					error_log("HQ Medlemsnr FOUND: ".$authMedlemsnr, 0);
					$query	= $db->getQuery(true) //CMJ: REDOING the query on the correct username, found in Scoutnet.
						->select('id')
						->from('#__users')
						->where('username=' . $db->quote($authMedlemsnr));
					$db->setQuery($query);
					$result = $db->loadResult();
					$userLoggedIn = JUser::getInstance($result); // Bring this in line with the rest of the system
					$response->email = $userLoggedIn->email;
					$response->username = $userLoggedIn->username;
					$response->type = "hqscoutnetauthenticator";		        
					$response->status = JAuthentication::STATUS_SUCCESS;
				} else if (isset($authResultObj->err)) { //login failure
					$response->status = JAuthentication::STATUS_FAILURE;
					$response->error_message = 'Felaktigt användarnamn ('.$credentials['username'].') eller lösenord. Kontrollera uppgifterna och försök igen.';
						error_log("HQ Login FALSE for ".$username, 0);
					// error_log($buffer, 0); //DEBUG					
				}
			} else { //inloggningen misslyckades
				$response->status = JAuthentication::STATUS_FAILURE;
				$response->error_message = 'Uppkopplingen mot Scoutnet misslyckades. Vänligen försök senare.';
					error_log("HQ Login CONNECTION FAILURE TO SCOUTNET for ".$username, 0);
				// error_log($buffer, 0); //DEBUG
			}
		}
	}
}
?>
