<?php

namespace App\Models;

use RedBeanPHP\OODBBean;

class UserModel
{
    public $id;
    public $password;
    public $email;
    public $teamId;
    public $name;
    public $isAdmin;

    public static function fromBean(?OODBBean $bean): UserModel
    {
        $m = new self;

        if (!$bean) {
            return $m;
        }

        $m->id = $bean->id;
        $m->password = $bean->password;
        $m->email = $bean->email;
        $m->teamId = $bean->team_id;
        $m->name = $bean->name;
        $m->isAdmin = (bool)$bean->is_admin;

        return $m;
    }

    public function passwordMatches($password): bool
    {
        return password_verify($password, $this->password);
    }
}