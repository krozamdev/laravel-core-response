<?php
namespace AiraSoftDev\LaravelApiResponse\Utilities;

use AiraSoftDev\LaravelApiResponse\ApiGenerate;

class DefaultRoot extends ApiServices {
    public function __construct(ApiGenerate $api) {
        parent::__construct($api);
    }
}