<?php
namespace krozamdev\LaravelApiResponse;

use krozamdev\LaravelApiResponse\Contracts\ApiContract;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use krozamdev\LaravelApiResponse\Utilities\CustomValidator;

class ApiGenerate implements ApiContract {
    protected $debug = false;
    protected $data = null;
    protected $done = true;
    protected $message;
    protected $code = 200;
    protected $isUpdate;
    protected $isDelete;
    protected $dataFinal = [];
    protected $time;
    protected $customArray = [];
    protected $isPagination = false;
    protected $isCustomPagination = true;

    /**
     * setDebug is used to override debug mode or not.
     * 
     * @param bool $debug mode debug = true will create an error message complete with its line code
     * @return ApiGenerate Instance of ApiGenerate for method chaining.
     */
    public function setDebug(bool $debug=false) : ApiGenerate
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * skipPaginateKey is used to not use paginate.
     * 
     * @return ApiGenerate Instance of ApiGenerate for method chaining.
     */
    public function skipPaginateKey() : ApiGenerate
    {
        $this->isPagination = false;
        return $this;
    }
    
    /**
     * usePaginateKey is used to recompile laravel paginate.
     * 
     * @return ApiGenerate Instance of ApiGenerate for method chaining.
     */
    public function usePaginateKey() : ApiGenerate
    {
        $this->isPagination = true;
        return $this;
    }

    /**
     * time is used to initiate the initial time the process is run.
     * 
     * @return ApiGenerate Instance of ApiGenerate for method chaining.
     */
    public function time() : ApiGenerate
    {
        $this->time = Carbon::now();
        return $this;
    }


    /**
     * CustomKey is used to merge response properties that are 1 level with data, message, errors and metadata.
     * 
     * @param array $array use this parameter to create props `Array<string, any>`
     * @return ApiGenerate Instance of ApiGenerate for method chaining.
     */
    public function CustomKey(array $array) : ApiGenerate
    {
        $this->customArray = array_merge($this->customArray,$array);
        return $this;
    }

    /**
     * data is used to override data that will be sent with the data prop in the response, it can also be used for error data to automatically recompile into an error message.
     * 
     * @param mix $data can be an array, string, Throwable data if error, and others
     * @return ApiGenerate Instance of ApiGenerate for method chaining.
     */
    public function data($data) : ApiGenerate
    {
        $this->data = $data;
        return $this;
    }

    /**
     * generate is used to generate responses from the data that has been collected.
     * 
     * @return JsonResponse
     */
    public function generate() : JsonResponse
    {
        // set status code and pure errors message
        $this->setStatusCode();
        // end set status code

        $this->dataFinal["status"] = $this->done;
        if ($this->isPagination) { // checking laravel pagination key setting
            if (!empty($this->dataFinal["data"])) {
                $this->dataFinal["data"] = $this->recompilePagination($this->dataFinal["data"]);
                $this->paginationKey($this->dataFinal["data"]);
            }
        }else if ($this->isCustomPagination) {
            if (is_array($this->dataFinal["data"]) && !empty($this->dataFinal["data"]["pagination"])) {
                $this->paginationKey($this->dataFinal["data"]);
            }
        }
        if (count($this->customArray)>0) { // added custom property in response first tier level
            $this->dataFinal = array_merge($this->dataFinal,$this->customArray);
        }

        $this->generateMetaData();

        // set the prop type between message or errors
        $metaKeyInfo = $this->done ? "message" : "errors";
        $this->dataFinal[$metaKeyInfo] = $this->message;
        
        
        unset($this->dataFinal["status_code"]);
        return response()->json($this->dataFinal,$this->code);
    }

    private function generateMetaData()
    {
        $setTime = true;
        if (!$this->time) {
            $setTime = false;
            $this->time = Carbon::now();
        }
        $difTime = $this->time->diffInMilliseconds(Carbon::now());
        
        // transform units of milliseconds or seconds
        if ($difTime >= 1000) {
            $difTime = number_format((float) $difTime / 1000, 2). " s";
        }else{
            $difTime = $difTime. " ms";
        }

        // there is a setTimeBeforeActions prop when debug mode
        $timeFormat = $this->debug ? [
            "value"=> $difTime,
            "setTimeBeforeActions"=> $setTime
        ] : $difTime;

        $metaData = [
            "time" => $timeFormat,
            "status_code" => $this->dataFinal["status_code"]
        ];

        if ($this->debug && $this->data instanceof \Throwable) {
            $metaData['trace_debug'] = [
                'file' => $this->data->getFile(),
                'line' => $this->data->getLine(),
                'trace' => $this->data->getTrace()
            ];
        }

        $this->dataFinal["metadata"] = $metaData;
    }

    /**
     * isUpdate is used to override the message update record with a status code according to the REST API standard.
     * 
     * @return ApiGenerate Instance of ApiGenerate for method chaining.
     * 
     */
    public function isUpdate() : ApiGenerate
    {
        $this->isUpdate = true;
        return $this;
    }
    
    /**
     * isCreate is used to override the message create record with a status code according to the REST API standard.
     * 
     * @return ApiGenerate Instance of ApiGenerate for method chaining.
     * 
     */
    public function isCreate() : ApiGenerate
    {
        $this->code = Response::HTTP_CREATED;
        return $this;
    }
    
    /**
     * isDelete is used to override the message delete record with a status code according to the REST API standard.
     * 
     * @return ApiGenerate Instance of ApiGenerate for method chaining.
     * 
     */
    public function isDelete() : ApiGenerate
    {
        $this->isDelete = true;
        return $this;
    }

    /**
     * setStatusCode `private` is used by selft to generate http status code when there is an indication of request error, this process also generates several error messages
     * 
     * @return ApiGenerate Instance of ApiGenerate for method chaining.
     * 
     */
    private function setStatusCode() : ApiGenerate
    {
        if ($this->data instanceof \Throwable) { // handle all errors
            $this->done = false;
        }
        $result = $this->code;
        if (!$this->done){ // there is an indication of an error in the request process
            if ($this->code == Response::HTTP_OK) {
                $this->dataFinal["data"] = null;
                
                if ($this->data instanceof ValidationException) { // handle error request laravel validate

                    $errors = collect($this->data->validator->errors()->messages())
                    ->mapWithKeys(function($messages, $field){
                        return [$field => $messages[0]];
                    })->toArray();
                    $this->setMessage($errors);
                    $result = $this->data->status ?? Response::HTTP_UNPROCESSABLE_ENTITY;
                }
                else if ($this->data instanceof CustomException) {
                    $this->setMessage($this->data->errors());
                    $result = $this->data->getCode();
                }
                else if (isset($this->data->status)) {
                    $result = $this->data->status;
                }else{
                    // handle get code method exists
                    if (method_exists($this->data,'getCode')) {
                        $code = $this->data->getCode();
                        $code = intval($code);
                        $result = $code;
                        // check code length
                        if (strlen($code) !== 3) {
                            $result = $code;
                            $result = Response::HTTP_BAD_GATEWAY;
                        }
                        // check number range
                        if ($code < 100 || $code > 599) {
                            $result = Response::HTTP_BAD_GATEWAY;
                        }
                    }else{
                        $result = Response::HTTP_BAD_GATEWAY;
                    }

                    if ($this->data instanceof ModelNotFoundException) { // handle ModelNotFoundException specifically
                        $result = Response::HTTP_NOT_FOUND;
                    }

                    if (method_exists($this->data,'getMessage')) {
                        if (preg_match('/already exists|Conflict|Duplicate entry/i',$this->data->getMessage())) {
                            $pattern = "/for key '(.*?)' \(SQL:/"; // for mysql | mariadb
                            preg_match($pattern, $this->data->getMessage(), $matches);
                            if (isset($matches[1])) {
                                $tempField = explode('.',$matches[1]);
                                $finalMessage = str_replace([$tempField[0] . '_', '_unique'], '', $tempField[1]);
                                $formattedMessage = ucwords(str_replace(['_', '-'], ' ', $finalMessage)) . ' already exists';
                                $resultArray[$finalMessage] = [$formattedMessage]; // set default format errors
                                $this->setMessage($resultArray);
                            }
                            $result = Response::HTTP_CONFLICT;
                        }
                        if (preg_match('/Cannot delete or update a parent row: a foreign key constraint fails/i',$this->data->getMessage())) {
                            $result = Response::HTTP_CONFLICT;
                            if (!$this->debug) {
                                $this->setMessage("The data cannot be updated or deleted as it is associated or linked to other data.");
                            }
                        }
                    }
                }
            }
        }else{
            $this->dataFinal["data"] = $this->data;
        }
        $this->dataFinal["status_code"] = $result;
        $this->code = $result;
        $this->generateMessage();
        return $this;
    }

    /**
     * setCode is used to override the http status code that will be sent to the response.
     * 
     * @param int $code HTTP status code, please take the http code from the package `Symfony\Component\HttpFoundation\Response` see http status code [here](https://symfony.com/doc/current/components/http_foundation.html#response).
     * @return ApiGenerate Instance of ApiGenerate for method chaining.
     * 
     */
    public function setCode(int $code) : ApiGenerate
    {
        $this->code = $code;
        return $this;
    }

    /**
     * setMessage is used to override the message that will be sent to the response
     * 
     * @param array|string $message The message can be a string or an array.
     * @return ApiGenerate Instance of ApiGenerate for method chaining.
     * 
     */
    public function setMessage($message) : ApiGenerate
    {
        $this->message = $message;
        return $this;
    }

    /**
     * generateMessage `private` is used to create a message according to the http status code generated after generating the status code
     * 
     * @return void
     * 
     */
    private function generateMessage() : void
    {
        if (!$this->message) {
            if (!$this->done) {
                if ($this->debug) { // show all error messages while in debug mode
                    $this->message = $this->transformFinalMessage($this->data->getMessage());
                }else{
                    switch ($this->code) {
                        
                        case 409:
                            $msg = "Data already exists.";
                            break;
                            
                        case 422:
                        case 400:
                        case 401:
                        case 404:
                        case 403:
                            $msg = $this->transformFinalMessage($this->data->getMessage());
                            break;
                        
                        default:
                            $msg = "Internal Server Error.";
                            break;
                    }
                    $this->message = $msg;
                }
            }else{
                $msgRecord = "record successfully";
                if ($this->code == 201) {
                    $this->message = "Created $msgRecord";
                }else{
                    if ($this->code == 200) {
                        $this->message = "get $msgRecord";
                        if ($this->isUpdate) {
                            $this->message = "Updated $msgRecord";
                        }
                        if ($this->isDelete) {
                            $this->message = "Deleted $msgRecord";
                        }
                    }else if ($this->code == 202){
                        $this->message = "Request accepted for processing.";
                    }else{
                        $this->message = "Unknown message";
                    }
                }
            }
        }
    }
    
    /**
     * set pagination generated from laravel paginate
     */
    public function paginationKey(array $data) : ApiGenerate
    {
        if (!empty($data["pagination"])) {
            $this->CustomKey(['pagination'=>$data["pagination"]]);
        }
        $this->dataFinal["data"] = $data["data"] ?? $data;
        return $this;
    }

    /**
     * Recompile Paginate for custom paginate data generated by laravel
     */
    private function recompilePagination($response) : array {
        try {
            $response = json_decode(json_encode($response));
            if (isset($response->per_page) && isset($response->current_page)) {
                $pagination = [
                    'perPage' => $response->per_page,
                    'pageCurrent' => $response->current_page,
                    'pageLast' => $response->last_page,
                    'dataFrom' => $response->from,
                    'dataTo' => $response->to,
                    'dataTotal' => $response->total,
                    'navigation' => [
                        'nextUrl' => $response->next_page_url,
                        'prevUrl' => $response->prev_page_url,
                        'firstUrl' => $response->first_page_url,
                        'lastUrl' => $response->last_page_url,
                    ],
                    'links' => $response->links
                ];
                return [
                  'data' => $response->data,  
                  'pagination' => $pagination,
                ];
            }else{
                return [
                    "data" => $response
                ];
            }
        } catch (\Exception $e) {
            return [
                "data" => $response
            ];
        }
    }

    /**
     * transform message to handle object response
     */
    private function transformFinalMessage(string $message) {
        if ($array = json_decode($message, true)) {
            return $array;
        }
        return $message;
    }

    public static function validData($data) : void
    {
        if (!$data || empty($data)) {
            throw new \Exception("Record not found!", 404);
        }
    }
}