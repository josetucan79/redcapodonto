<?php

class UserController extends Controller
{
    public function downloadCurrentUsersList()
    {
        User::downloadProjectUsersList();
    }
}