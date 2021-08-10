<?php

interface Am_FlexibleAction_Interface
{
    /** add user to queue or run action immediately */
    function run(User $user);

    /** this function will be executed after run of @see run() method */
    function commit();

    /** @return string */
    function getTitle();

    /** @return string */
    function getId();
}

