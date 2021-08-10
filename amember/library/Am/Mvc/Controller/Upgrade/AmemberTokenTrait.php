<?php

/**
 * Fetch and use API token to interact with amember.com API
 */

trait Am_Mvc_Controller_Upgrade_AmemberTokenTrait
{
    /** @var \League\OAuth2\Client\Token\AccessToken */
    private $amemberAccessToken;
    /**
     * @return bool
     */
    protected function loadAmemberToken($refreshIfNecessary = false)
    {
        $jsonToken = json_decode($this->getDi()->store->getBlob('amemberSiteToken'), true);
        if ($jsonToken)
        {
            $accessToken = new \League\OAuth2\Client\Token\AccessToken($jsonToken);
            if ($accessToken->hasExpired() && $refreshIfNecessary)
            {
                $provider = $this->createAmemberSiteProvider();
                $newAccessToken = $provider->getAccessToken('refresh_token', [
                    'refresh_token' => $accessToken->getRefreshToken()
                ]);
                $this->saveAmemberToken($newAccessToken);
                $accessToken = $newAccessToken;
            }

            $tokenValid = ! $accessToken->hasExpired();
            if ($tokenValid)
            {
                $this->amemberAccessToken = $accessToken;
                return true;
            }
        }
        return false;
    }

    protected function saveAmemberToken(\League\OAuth2\Client\Token\AccessToken $accessToken)
    {
        // We have an access token, which we may use in authenticated
        // requests against the service provider's API.
        $this->getDi()->store->setBlob('amemberSiteToken', json_encode($accessToken->jsonSerialize()));
        $this->amemberAccessToken = $accessToken;
    }

    protected function removeAmemberToken()
    {
        $this->getDi()->store->delete('amemberSiteToken');
        $this->getDi()->store->delete('amemberSiteResources');
        $this->amemberAccessToken = null;
        unset($this->getDi()->session->oauth2state);
    }

    protected function fetchAmemberResourcesWithErrorHandling(& $tokenValid)
    {
        try
        {
            $tokenValid = $this->loadAmemberToken(true);
        } catch (\Exception $e) {
            $tokenValid = false;
            // $this->getDi()->logger->info("Unable to loadAmemberToken {exception}", ['exception'=>$e]);
        }
        $amemberSiteResources = [
            'am_subscriptions' => ['_' => '_'],
        ];
        if ($tokenValid)
        {
            try
            {
                $useCache =  empty($_REQUEST['refresh']);
                $amemberSiteResources = $this->fetchAmemberSiteResources( $useCache );
            } catch (Exception $e) {
                $tokenValid = false;
            } finally {
                if (empty($amemberSiteResources['am_subscriptions']))
                {
                    $amemberSiteResources['am_subscriptions'] = ['_' => '_'];
                } // to keep it object in js and not array
            }
        }
        return $amemberSiteResources;
    }

    /**
     * @param bool $useCache
     * @return array|null
     */
    protected function fetchAmemberSiteResources($useCache = true)
    {
        if ($useCache)
        {
            $amemberSiteResources = json_decode($this->getDi()->store->getBlob('amemberSiteResources'), true);
            if ($amemberSiteResources)
                return $amemberSiteResources;
        }
        if ($this->amemberAccessToken)
        {
            if ($arr = $this->fetchAmemberSiteResourcesNoCache())
            {
                $this->storeAmemberSiteResources($arr);
                return $arr;
            }
        }
        return null;
    }

    protected function storeAmemberSiteResources($arr)
    {
        $this->getDi()->store->setBlob('amemberSiteResources', json_encode($arr), '+2 hours');
    }

    protected function fetchAmemberSiteResourcesNoCache()
    {
        $provider = $this->createAmemberSiteProvider();
        $resourceOwner = $provider->getResourceOwner($this->amemberAccessToken);
        $arr = $resourceOwner->toArray();
        return $arr['data'];
    }

    protected function amemberGetAuthenticatedRequest($method, $url)
    {
        if (!$this->amemberAccessToken)
            throw new Am_Exception_InternalError("amemberMakeRequest called with empty amemberAccessToken");
        $provider = $this->createAmemberSiteProvider();
        return $provider->getAuthenticatedRequest($method, $url, $this->amemberAccessToken);
    }

    protected function createAmemberSiteProvider($withAccessToken = false)
    {
        check_demo();
        if (defined('CUSTOM_AMEMBER_OAUTH2_CLIENT_PROVIDER_CALLBACK')) // for testing
            return call_user_func(CUSTOM_AMEMBER_OAUTH2_CLIENT_PROVIDER_CALLBACK);
        else
            return new \League\OAuth2\Client\Provider\GenericProvider([
                'clientId'                => 'bdc1fcae6d1748fa8e1beebfd770f27c',
                'clientSecret'            => '07fdd7c3eb7aa78915ea997303c7fae8c2e72792878d1d9ebe8d4df83e53b035',
                'redirectUri'             => Am_Di::getInstance()->url('admin-plugins/callback', [], false, true),
                'urlAuthorize'            => 'https://www.amember.com/amember/oauth/authorize',
                'urlAccessToken'          => 'https://www.amember.com/amember/oauth/token',
                'urlResourceOwnerDetails' => 'https://www.amember.com/amember/api2/cgi',
                'scopes' => 'cgi',
            ]);
    }

    protected function getOauthLoginLink(Am_Di $di, $returnUrl = null, $escape = false)
    {
        $p = [];
        if ($returnUrl)
        {
            if (empty($di->session->oauth2csrf))
                $di->session->oauth2csrf = $di->security->randomString(12);
            $p['csrf'] = $di->session->oauth2csrf;
            $p['back'] = $returnUrl;
        }
        return $di->url('admin-plugins/callback', $p, $escape);
    }

    protected function getOauthLogoutLink(Am_Di $di, $returnUrl = null, $escape = false)
    {
        $p = [];
        if ($returnUrl)
        {
            if (empty($di->session->oauth2csrf))
                $di->session->oauth2csrf = $di->security->randomString(12);
            $p['csrf'] = $di->session->oauth2csrf;
            $p['back'] = $returnUrl;
        }
        return $di->url('admin-plugins/logout', $p, $escape);
    }

    /**
     * Must be called inside controller
     * @param Am_Di $di
     * @return bool
     * @throws Am_Exception_InputError
     * @throws Am_Exception_Redirect
     */
    protected function runOauthCallback(Am_Di $di)
    {
        $provider = $this->createAmemberSiteProvider();
        // If we don't have an authorization code then get one
        $getCode = $di->request->get('code');
        $getState = $di->request->get('state');
        $oauth2State = $di->session->oauth2state;
        $oauth2Redirect = $di->session->oauth2redirect;
        $oauth2Csrf = $di->session->oauth2csrf;
        if (!$getCode) {
            if ($di->request->error && $di->request->error_description)
                throw new Am_Exception_InputError("Authorization server returned error [" . Am_Html::escape($di->request->error) . "] " . Am_Html::escape($di->request->error_description));

            if ($di->session->oauth2csrf && ($di->session->oauth2csrf == $di->request->csrf) && !empty($di->request->back))
            {
                $di->session->oauth2back = $di->request->back;
            } else {
                throw new Am_Exception_InputError("Incorrect or expired URL, please return and try again");
            }
            // Fetch the authorization URL from the provider; this returns the
            // urlAuthorize option and generates and applies any necessary parameters
            // (e.g. state).
            $authorizationUrl = $provider->getAuthorizationUrl();

            // Get the state generated for you and store it to the session.
            $di->session->oauth2state = $provider->getState();
            $di->response->setRedirect($authorizationUrl);
            throw new Am_Exception_Redirect($authorizationUrl);
            return false;
        // Check given state against previously stored one to mitigate CSRF attack
        } elseif (empty($getState) || (!empty($oauth2State) && $getState !== $oauth2State)) {

            if (isset($di->session->oauth2state)) {
                $di->session->oauth2state = null;
            }
            throw new Am_Exception_InputError("Invalid oAuth2 state");
            return false;
        } else {
            try {
                // Try to get an access token using the authorization code grant.
                $accessToken = $provider->getAccessToken('authorization_code', [
                    'code' => $_GET['code']
                ]);
                $this->saveAmemberToken($accessToken);
                $url = $di->session->oauth2back;
                if (empty($url)) $url = $di->url('admin-plugins'); // fallback url!
                $di->session->oauth2back = null;
                $di->response->setRedirect($url);
                throw new Am_Exception_Redirect($url);
            } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
                $di->logger->error("Error fetching token from amember.com", ['exception' => $e]);
                throw new Am_Exception_InputError("Could not get token");
            } catch (UnexpectedValueException $e) {
                $di->logger->error("Error fetching token from amember.com", ['exception' => $e]);
                throw new Am_Exception_InputError("Could not get token");
            }
        }
        return false;
    }

    protected function runOauthLogout(Am_Di $di)
    {
        if ($di->session->oauth2csrf && ($di->session->oauth2csrf == $di->request->csrf) && !empty($di->request->back))
        {
            $url = $di->request->back;
        } else {
            $url = $di->url('admin-plugins', false);
        }

        $this->removeAmemberToken();
        $di->response->setRedirect($url);
        throw new Am_Exception_Redirect($url);
    }

}