<?php

namespace App\Support;

use App\Models\Client;

class PosDefaultClient
{
    public const NAME = 'Client Comptoir';

    public const EMAIL = 'client-comptoir@pos.internal';

    public static function ensure(): Client
    {
        return Client::firstOrCreate(
            ['name' => self::NAME],
            ['email' => self::EMAIL]
        );
    }
}
