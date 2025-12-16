<?php
namespace krozamdev\LaravelApiResponse\Utilities;

use krozamdev\LaravelApiResponse\ApiGenerate;

class ApiServices {
    protected $api;
    public function __construct(ApiGenerate $api)
    {
        $this->api = $api;
        $this->api->setDebug(env("APP_DEBUG",false));
    }
}