<?php

/** Zero action, no-operation */
class Am_FlexibleAction_Nop implements Am_FlexibleAction_Interface
{
    function run(User $user){}
    function commit() {}
    function getTitle() { return 'NOP action'; }
    function getId() { return 'nop'; }
}
