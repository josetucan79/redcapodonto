<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Traits;

use User;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\UserEntity;

trait CanGetUserInfoFromId {

    private function getUserInfo(?UserEntity $userEntity) {
        if(!$userEntity) return null;
        $data = [
            'username' => $userEntity->getUsername(),
            'user_firstname' => $userEntity->getUserFirstname(),
            'user_lastname' => $userEntity->getUserLastname(),
            'user_email' => $userEntity->getUserEmail(),
        ];
        return $data;
    }

}