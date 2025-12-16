<?php
namespace krozamdev\LaravelApiResponse\Contracts;

use Illuminate\Http\JsonResponse;

interface ApiContract {
    function setDebug(bool $debug=false) : ApiContract;
    function skipPaginateKey() : ApiContract;
    function usePaginateKey() : ApiContract;
    function time() : ApiContract;
    function CustomKey(array $array) : ApiContract;
    function data($array) : ApiContract;
    function generate() : JsonResponse;
    function isUpdate() : ApiContract;
    function isCreate() : ApiContract;
    function isDelete() : ApiContract;
    function setCode(int $code) : ApiContract;
    function setMessage($message) : ApiContract;
    function paginationKey(array $array) : ApiContract;
    static function validData($data) : void;
    
}