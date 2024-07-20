<?php
namespace krozamdev\LaravelApiResponse\Utilities;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomValidator {

    public static function validate(Request $request,array $rules) : array
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            throw new \Exception($validator->errors(),422);
        }
        return $validator->validated();
    }
}