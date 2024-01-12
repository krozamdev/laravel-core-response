<?php
namespace AiraSoftDev\Contracts;

use Illuminate\Http\JsonResponse;

interface ApiContract {
    function debug(bool $debug=false) : ApiContract;
    function generate() : JsonResponse;
    function code(int $code) : ApiContract;
    function skipPaginateKey() : ApiContract;
    function isUpdate() : ApiContract;
    function isDelete() : ApiContract;
    function failed() : ApiContract;
    function data(mixed $data) : ApiContract;
    function message(string $message) : ApiContract;
    function time() : ApiContract;
    function CustomKey(array $array) : ApiContract;
    function paginationKey(array $array) : ApiContract;
}