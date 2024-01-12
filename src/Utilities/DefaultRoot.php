<?php
namespace AiraSoftDev\Utilities;

use AiraSoftDev\LaravelApiResponse\ApiGenerate;

class DefaultRoot extends Api {
    public function __construct(ApiGenerate $api) {
        parent::__construct($api);
    }
}