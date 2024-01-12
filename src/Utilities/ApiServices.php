<?php
namespace AiraSoftDev\Utilities;

use AiraSoftDev\LaravelApiResponse\ApiGenerate;

class Api {
    protected $api;
    public function __construct(ApiGenerate $api)
    {
        $this->api = $api;
        $this->api->debug(env("APIFORMATTERDEBUG",false));
    }
}