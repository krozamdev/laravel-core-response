<?php
namespace krozamdev\LaravelApiResponse\Utilities;

use krozamdev\LaravelApiResponse\ApiGenerate;

class DefaultRoot extends ApiServices {
    public function __construct(ApiGenerate $api) {
        parent::__construct($api);
    }
}