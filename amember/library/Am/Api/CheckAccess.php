<?php

class Am_Api_CheckAccess extends Am_ApiController_Base
{
    protected function checkUser($response, User $user = null, $errCode = null, $errMsg = null, $ip = null)
    {
        if ($user)
        {
            $ns = $this->getDi()->session->ns('amember_auth');
            $auth = new Am_Auth_User($ns, $this->getDi());
            if($res = $auth->checkUser($user, $ip)) {
                $ret = [
                    'ok' => false,
                    'code' => $res->getCode(),
                    'msg'  => $res->getMessage(),
                ];
            } else {
                $resources = $this->getDi()->resourceAccessTable
                    ->getAllowedResources($user, ResourceAccess::USER_VISIBLE_TYPES);

                $res = [];
                foreach ($resources as $k => $r) {
                    if ($link = $r->renderLink())
                        $res[] = $link;
                }

                $ret = [
                    'ok' => true,
                    'user_id' => $user->pk(),
                    'name' => $user->getName(),
                    'name_f' => $user->name_f,
                    'name_l' => $user->name_l,
                    'email' => $user->email,
                    'login' => $user->login,
                    'subscriptions' => $user->getActiveProductsExpiration(),
                    'categories' => $user->getActiveCategoriesExpiration(),
                    'groups' => $user->getGroups(),
                    'resources' => $res
                ];
            }
        } else {
            if (empty($errCode)) $errCode = -1;
            if (empty($errMsg)) $errMsg = "Failure";
            $ret = [
                'ok' => false,
                'code' => $errCode,
                'msg'  => $errMsg,
            ];
        }

        return $response->withJson($ret);
    }

    /**
     * Check access by username/password
     */
    function byLoginPass($request, $response, $args)
    {
        $code = null;
        $user = $this->getDi()->userTable->getAuthenticatedRow($request->getParam('login'), $request->getParam('pass'), $code);
        $res = new Am_Auth_Result($code);
        return $this->checkUser($response, $user, $res->getCode(), $res->getMessage());
    }

    /**
     * Check access by username
     */
    function byLogin($request, $response, $args)
    {
        $user = $this->getDi()->userTable->findFirstByLogin($request->getParam('login'));
        return $this->checkUser($response, $user);
    }

    /**
     * Check access by email address
     */
    function byEmail($request, $response, $args)
    {
        $user = $this->getDi()->userTable->findFirstByEmail($request->getParam('email'));
        return $this->checkUser($response, $user);
    }

    /**
     * Check access by username/password/ip
     */
    function byLoginPassIp($request, $response, $args)
    {
        $code = null;
        $user = $this->getDi()->userTable->getAuthenticatedRow($request->getParam('login'), $request->getParam('pass'), $code);
        $res = new Am_Auth_Result($code);
        return $this->checkUser($response, $user, $res->getCode(), $res->getMessage(), $request->getParam('ip'));
    }

    function sendPass($request, $response, $args)
    {
        $login = trim($request->getParam('login'));

        if (!$user = $this->getDi()->userTable->findFirstByLogin($login)) {
            $user = $this->getDi()->userTable->findFirstByEmail($login);
        }

        if (!$user) {
            return $response->withJson(['ok' => false]);
        }

        $c = new SendpassController($request, $response, ['di' => $this->getDi()]);
        $c->sendSecurityCode($user);

        return $response->withJson([
            'ok' => true,
            'msg' => ___('A link to reset your password has been emailed to you. Please check your mailbox.')
        ]);
    }
}
