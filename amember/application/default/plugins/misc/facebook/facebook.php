<?php

use Facebook\Facebook;
use Facebook\GraphNodes\GraphUser;
use Facebook\Helpers\FacebookJavaScriptHelper;


/**
 * @am_plugin_api 6.0
*/
class Am_Plugin_Facebook extends Am_Plugin
{

    use Am_Mvc_Controller_User_Create;

    const PLUGIN_STATUS = self::STATUS_BETA;
    const PLUGIN_REVISION = '6.3.6';
    const FACEBOOK_UID = 'facebook-uid';
    const FACEBOOK_LOGOUT = 'facebook-logout';
    const NOT_LOGGED_IN = 0;
    const LOGGED_IN = 1;
    const LOGGED_AND_LINKED = 2;
    const LOGGED_OUT = 3;
    const FB_APP_ID = 'app_id';
    const FB_APP_SECRET = 'app_secret';

    protected $status = null; //self::NOT_LOGGED_IN;
    /** @var User */
    protected $linkedUser;

    /** @var GraphUser */
    protected $fbProfile = null;
    private $_api_loaded = false;
    private $_api_error = null;
    private $sdkIncluded = false;

    /**
     * @return FacebookSession;
     */
    protected $session = null;
    
    /**
     * @var Facebook
     */
    protected $api;

    protected function loadAPI()
    {
        if ($this->_api_loaded)
            return true;

        try {
            $this->api = new Facebook([
                'app_id' => $this->getConfig(self::FB_APP_ID),
                'app_secret' => $this->getConfig(self::FB_APP_SECRET),
                'default_graph_version' => 'v2.4',
            ]);
            $this->_api_loaded = true;
        } catch (Exception $ex) {
            $this->_api_error = $ex->getMessage();
        }

        return $this->_api_loaded;
    }

    public function onAdminWarnings(Am_Event $event)
    {
        if (!$this->_api_loaded) {
            $event->addReturn(___('Facebook SDK was  not loaded. Got an error: %s', $this->_api_error));
        } else {
            parent::onAdminWarnings($event);
        }
    }

    public function isConfigured()
    {
        return $this->getConfig(self::FB_APP_ID) && $this->getConfig(self::FB_APP_SECRET) && $this->loadAPI();
    }

    function onSetupForms(Am_Event_SetupForms $event)
    {
        $form = new Am_Form_Setup('facebook');
        $form->setTitle('Facebook');

        $fs = $form->addFieldset()->setLabel(___('FaceBook Application'));
        $fs->addText(self::FB_APP_ID)->setLabel(___('FaceBook App ID'));
        $fs->addText(self::FB_APP_SECRET, array('size' => 40))->setLabel(___('Facebook App Secret'));

        $fs = $form->addFieldset()->setLabel(___('Features'));
        $size = array('icon', 'small', 'medium', 'large', 'xlarge');
        $fs->addSelect('size')
            ->setLabel(___('Login Button Size'))
            ->loadOptions(array_combine($size, $size));

        $fs->addSelect("login_postion")
            ->setLabel("Login Button Position")
            ->loadOptions(array(
                'login/form/after' => ___("Below Login Form"),
                'login/form/before' => ___("Above Login Form")
            ));

        $fs->addAdvCheckbox('no_signup')
            ->setLabel(___('Do not add to Signup Form'));
        $fs->addAdvCheckbox('no_login')
            ->setLabel(___('Do not add to Login Form'));

        $gr = $fs->addGroup()
            ->setLabel(___('Add "Like" button'));
        $gr->addAdvCheckbox('like', array('id' => 'like-settings'));
        $gr->addStatic()->setContent(' <span>' . ___('Like Url') . '</span> ');
        $gr->addText('likeurl', array('size' => 40));

        $layout = array('standard', 'button_count', 'button', 'box_count');
        $fs->addSelect('layout', array('rel' => 'like-settings'))
            ->setLabel(___('Like Button Layout'))
            ->loadOptions(array_combine($layout, $layout));
        $action = array('like', 'recommend');
        $fs->addSelect('action', array('rel' => 'like-settings'))
            ->setLabel(___('Like Button Action'))
            ->loadOptions(array_combine($action, $action));

        $form->addScript()
            ->setScript(<<<CUT
jQuery(function($){
    jQuery('#like-settings').change(function(){
        jQuery('#like-settings').nextAll().toggle(this.checked);
        jQuery('[rel=like-settings]').closest('.am-row').toggle(this.checked);
    }).change();
})
CUT
            );

        $form->setDefault('likeurl', ROOT_URL);

        $form->addMagicSelect('add_access', ['class' => 'am-combobox-fixed'])
            ->setLabel("Add free access to a product\n" .
                "if user signup from Facebook")
            ->loadOptions($this->getDi()->productTable->getOptions());
        $form->addFieldsPrefix('misc.facebook.');
        $this->_afterInitSetupForm($form);
        $event->addForm($form);
    }

    function onInitFinished(Am_Event $event)
    {
        $blocks = $this->getDi()->blocks;
        if (!$this->getConfig('no_login'))
            $blocks->add(
                $this->getConfig('login_postion', 'login/form/after'), new Am_Block_Base(null, 'fb-login', $this, 'fb-login.phtml'));
        if (!$this->getConfig('no_signup'))
            $blocks->add(
                'signup/form/before',new Am_Block_Base(null, 'fb-signup', $this, 'fb-signup.phtml'));
        if ($this->getConfig('like'))
            $blocks->add(
                'member/main/right/bottom', new Am_Block_Base(null, 'fb-like', $this, 'fb-like.phtml'));
    }

    function includeJSSDK()
    {
        $locale = $this->getDi()->locale->getId();
        echo <<<CUT
<div id="fb-root"></div>
<script>
jQuery(document).ready(function($) {
  jQuery.ajaxSetup({ cache: true });
  jQuery.getScript('//connect.facebook.net/$locale/sdk.js', function(){
    FB.init({
      appId: '{$this->getConfig(self::FB_APP_ID)}',
      version: 'v2.3',
      status: true,
      cookie: true,
      xfbml: true,
      oauth: true
    });
    jQuery('#loginbutton,#feedbutton').removeAttr('disabled');
  });
});
</script>
CUT;
    }

    function includeLoginJS()
    {
        echo <<<OUT
<script type="text/javascript">
function facebook_login_login()
{
    var loginRedirect = function(){
        var href = window.location.href;
        if (href.indexOf('?') < 0)
            href += '?fb_login=1';
        else
            href += '&fb_login=1';
        window.location.href=href;
    }

    FB.getLoginStatus(function(response) {

        if(response.status == 'connected')
            loginRedirect();
        else
            FB.login(function(response) {
                if (response.status=='connected')  loginRedirect();
                }, {scope: 'email'});

    });
}
</script>
OUT;
    }


    /**
     * Create account in aMember for user who is logged in facebook.
     */
    function createAccount()
    {
        if (!$this->getFbProfile('email')) {
            throw new Am_Exception_InputError('It is not possible to use Facebook account without email to login, please follow standard signup flow.');
        }

        /* Search for account by email address */
        $user = $this->getDi()->userTable->findFirstByEmail($this->getFbProfile('email'));
        if (empty($user)) {
            // Create account for user;

            $user = $this->createUser([
                'email' => $this->getFbProfile('email'),
                'name_f' => $this->getFbProfile('first_name') ?: '',
                'name_l' => $this->getFbProfile('last_name') ?: '',
            ]);
        }

        if(!$user->data()->get(self::FACEBOOK_UID) && ($pids = $this->getConfig('add_access')))
        {
            foreach ($pids as $pid) {
                if ($product = $this->getDi()->productTable->load($pid, false)) {
                    $billingPlan = $product->getBillingPlan();

                    $access = $this->getDi()->accessRecord;
                    $access->product_id = $pid;
                    $access->begin_date = $this->getDi()->sqlDate;

                    $period = new Am_Period($billingPlan->first_period);
                    $access->expire_date = $period->addTo($access->begin_date);

                    $access->user_id = $user->pk();
                    $access->comment = 'from Facebook login';
                    $access->insert();
                }
            }
        }

        $user->data()->set(self::FACEBOOK_UID, $this->getFbProfile('id'))->update();


        return $user;
    }

    function onAuthCheckLoggedIn(Am_Event_AuthCheckLoggedIn $event)
    {
        $status = $this->getStatus();
        if ($status == self::LOGGED_AND_LINKED) {
            $event->setSuccessAndStop($this->linkedUser);
        } elseif ($status == self::LOGGED_OUT && !empty($_GET['fb_login'])) {
            $this->linkedUser->data()->set(self::FACEBOOK_LOGOUT, null)->update();
            $event->setSuccessAndStop($this->linkedUser);
        } elseif ($status == self::LOGGED_IN && $this->getDi()->request->get('fb_login')) {
            $this->linkedUser = $this->createAccount();
            $event->setSuccessAndStop($this->linkedUser);
        }
    }

    function onAuthAfterLogout(Am_Event_AuthAfterLogout $event)
    {
        $domain = $this->getDi()->request->getHttpHost();
        Am_Cookie::set('fbsr_' . $this->getConfig('app_id'), null, time() - 3600 * 24, "/");
        Am_Cookie::set('fbm_' . $this->getConfig('app_id'), null, time() - 3600 * 24, "/");
        Am_Cookie::set('fbsr_' . $this->getConfig('app_id'), null, time() - 3600 * 24, "/", $domain, false);
        Am_Cookie::set('fbm_' . $this->getConfig('app_id'), null, time() - 3600 * 24, "/", $domain, false);
        $event->getUser()->data()->set(self::FACEBOOK_LOGOUT, true)->update();
    }

    function onAuthAfterLogin(Am_Event_AuthAfterLogin $event)
    {
        if (($this->getStatus() == self::LOGGED_IN) && $this->getFbUid()) {
            $event->getUser()->data()->set(self::FACEBOOK_UID, $this->getFbUid())->update();
        }
    }

    function getStatus()
    {
        if ($this->status !== null)
            return $this->status;

        $this->linkedUser = null;

        if ($id = $this->getFbUid()) {
            $user = $this->getDi()->userTable->findFirstByData(self::FACEBOOK_UID, $id);
            if ($user) {
                $this->linkedUser = $user;
                if ($user->data()->get(self::FACEBOOK_LOGOUT)) {
                    $this->status = self::LOGGED_OUT;
                } else {
                    $this->status = self::LOGGED_AND_LINKED;
                }
            } else {
                $this->status = self::LOGGED_IN;
            }
        } else {
            $this->status = self::NOT_LOGGED_IN;
        }
        return $this->status;
    }

    /** @return User */
    function getLinkedUser()
    {
        return $this->linkedUser;
    }

    /** @return int FbUid */
    function getFbUid()
    {
        $helper = $this->api->getJavaScriptHelper();
        
        return $helper->getUserId();
    }

    /** @return facebook info */
    function getFbProfile($fieldName)
    {
        if (is_null($this->fbProfile) && $this->getFbUid()) {
            
            try {
                $response = $this->api->get('/me?fields=email,first_name,last_name', $this->api->getJavaScriptHelper()->getAccessToken());
                
                $user_profile = $response->getGraphNode(GraphUser::class);
                $this->fbProfile = $user_profile;
            } catch (Exception $e) {
                return null;
            }
        }
        return $this->fbProfile->getField($fieldName);
    }

}