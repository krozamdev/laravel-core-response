<?php
namespace AiraSoftDev\LaravelApiResponse;

use AiraSoftDev\LaravelApiResponse\Contracts\ApiContract;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class ApiGenerate implements ApiContract {
    protected bool $debug = false;
    protected $data = null;
    protected bool $done = true;
    protected $message;
    protected int $code = 200;
    protected $isUpdate;
    protected $isDelete;
    protected array $dataFinal = [];
    protected $time;
    protected array $customArray = [];
    private bool $isPagination = true;

    public function debug(bool $debug=false) : ApiGenerate
    {
        $this->debug = $debug;
        return $this;
    }

    public function skipPaginateKey() : ApiGenerate
    {
        $this->isPagination = false;
        return $this;
    }

    public function time() : ApiGenerate
    {
        $this->time = Carbon::now();
        return $this;
    }

    public function failed() : ApiGenerate
    {
        $this->done = false;
        return $this;
    }

    public function CustomKey(array $array) : ApiGenerate
    {
        $this->customArray = array_merge($this->customArray,$array);
        return $this;
    }

    public function data(mixed $data) : ApiGenerate
    {
        $this->data = $data;
        return $this;
    }

    public function generate() : JsonResponse
    {
        $this->setStatusCode();
        $this->dataFinal["status"] = $this->done;
        if ($this->isPagination) {
            $this->dataFinal["data"] = $this->recompilePagination($this->dataFinal["data"]);
            $this->paginationKey($this->dataFinal["data"]);
        }
        if (count($this->customArray)>0) {
            $this->dataFinal = array_merge($this->dataFinal,$this->customArray);
        }
        $setTime = true;
        if (!$this->time) {
            $setTime = false;
            $this->time = Carbon::now();
        }
        $this->dataFinal["metadata"] = [
            "time" => [
                "value"=> $this->time->diffInMilliseconds(Carbon::now())." ms",
                "setTimeBeforeActions"=> $setTime
            ],
            "status_code" => $this->dataFinal["status_code"],
            "message" => $this->message
        ];
        unset($this->dataFinal["status_code"]);
        return response()->json($this->dataFinal,$this->code);
    }

    public function isUpdate() : ApiGenerate
    {
        $this->isUpdate = true;
        return $this;
    }
    
    public function isDelete() : ApiGenerate
    {
        $this->isDelete = true;
        return $this;
    }

    private function setStatusCode() : ApiGenerate
    {
        $result = $this->code;
        if (!$this->done){
            if ($this->code == 200) {
                $this->dataFinal["data"] = null;
                if (isset($this->data->status)) {
                    $result = $this->data->status;
                }else{
                    if (method_exists($this->data,'getCode')) {
                        $code = $this->data->getCode();
                        $code = intval($code);
                        $result = $code;
                        // Periksa panjang kode
                        if (strlen($code) !== 3) {
                            $result = $code;
                            $result = 500;
                        }
                        // Periksa rentang angka
                        if ($code < 100 || $code > 599) {
                            $result = 500;
                        }
                    }else{
                        $result = 500;
                    }
                    if (method_exists($this->data,'getMessage')) {
                        if (preg_match('/already exists|Conflict|Duplicate entry/i',$this->data->getMessage())) {
                            $result = 409;
                        }
                    }
                    if (method_exists($this->data,'getMessage')) {
                        if (preg_match('/Cannot delete or update a parent row: a foreign key constraint fails/i',$this->data->getMessage())) {
                            $result = 409;
                            if (!$this->debug) {
                                $this->message("The data cannot be updated or deleted as it is associated or linked to other data.");
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
        $this->setMessage();
        return $this;
    }

    public function code(int $code) : ApiGenerate
    {
        $this->code = $code;
        return $this;
    }

    public function message(string $message) : ApiGenerate
    {
        $this->message = $message;
        return $this;
    }

    private function setMessage() : void
    {
        if (!$this->message) {
            if (!$this->done) {
                if ($this->debug) {
                    $this->message = $this->data->getMessage();
                }else{
                    switch ($this->code) {
                        case 422:
                            $msg = "Validation error occurred. Please complete the required parameters.";
                            break;

                        case 409:
                            $msg = "Data already exists.";
                            break;
                            
                        case 400:
                        case 401:
                        case 404:
                        case 403:
                            $msg = $this->data->getMessage();
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
                            $this->message = "Delete $msgRecord";
                        }
                    }else{
                        $this->message = "Unknown Message";
                    }
                }
            }
        }
    }
    
    public function paginationKey(array $data) : ApiGenerate
    {
        if (!empty($data["pagination"])) {
            $this->CustomKey(['pagination'=>$data["pagination"]]);
        }
        $this->dataFinal["data"] = $data["data"];
        return $this;
    }

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
}