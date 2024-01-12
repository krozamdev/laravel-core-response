<?php
namespace AiraSoftDev\LaravelApiResponse\Utilities;

use AiraSoftDev\LaravelApiResponse\ApiGenerate;

class ApiServices {
    protected $api;
    public function __construct(ApiGenerate $api)
    {
        $this->api = $api;
        $this->api->debug(env("APIFORMATTERDEBUG",false));
    }
}