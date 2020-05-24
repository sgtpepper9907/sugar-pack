<?php

namespace SugarPack\Http;

use SugarPack\Configuration\PublishProfile;

class SugarClientFactory
{
    private function __construct(){}

    public static function create(PublishProfile $publishProfile): SugarCient
    {
        return new SugarCient(
            $publishProfile->instance,
            $publishProfile->username,
            $publishProfile->password,
            $publishProfile->platform
        );
    }
}