<?php namespace Novica89\Erply;

use Novica89\Erply\Exceptions\ErplySyncException;
use DB;
use Carbon\Carbon;

class Erply {

    /**
     * Error code for API_REQUEST_LIMIT_PER_HOUR_ERR_CODE
     *
     */
    const API_REQUEST_LIMIT_PER_HOUR_ERR_CODE = 1002;

    /**
     * Error code for VERIFY_USER_FAILURE
     *
     */
    const VERIFY_USER_FAILURE = 2001;
    /**
     * Error code for CURL_ERROR
     *
     */
    const CURL_ERROR = 2002;
    /**
     * Error code for PHP_SESSION_NOT_STARTED
     *
     */
    const PHP_SESSION_NOT_STARTED = 2003;
    /**
     * Error code for MISSING_PARAMETERS
     *
     */
    const MISSING_PARAMETERS = 2004;

    /**
     * Erply username
     *
     * @var
     */
    public $username;

    /**
     * Erply password
     *
     * @var
     */
    public $password;

    /**
     * Erply client code
     *
     * @var
     */
    public $clientCode;

    /**
     * Erply API url
     *
     * @var
     */
    public $url;

    /**
     * @var
     */
    public $sslCACertPath;

    /**
     * @var
     */
    public $response;

    /**
     * @var bool
     */
    public $requestStatus = true;

    /**
     * @var null
     */
    public $request_limit_reached_on = null;

    /**
     * Erply constructor.
     *
     * @param null $sslCACertPath
     */
    public function __construct($sslCACertPath = null) {

        $this->username = env('ERPLY_USER', config('erply.user', 'demo'));
        $this->password = env('ERPLY_PASS', config('erply.password', 'demouser'));
        $this->clientCode = env('ERPLY_CLIENT_CODE', config('erply.client_code', 'eng'));
        $this->url = "https://{$this->clientCode}.erply.com/api/";
        $this->sslCACertPath = $sslCACertPath;

    }

    /**
     * Make any possible type of request to Erply API. Ex: 'getClientGroups', 'saveCustomerGroup', 'deleteCustomerGroup' etc...
     *
     * @param $request
     * @param array $parameters
     *
     * @return mixed
     * @throws ErplySyncException
     */
    public function request($request, $parameters = []) {

        $this->incrementApiRequestAttempt($request);

        $this->abortIfApiLimitReached();

        $this->abortIfSetupIncorrectly();

        $parameters = $this->addEssentialParameters($request, $parameters);

        $handle = $this->setCurlOptions($parameters);

        $response = curl_exec($handle);
        $error = curl_error($handle);
        $errorNumber = curl_errno($handle);
        curl_close($handle);

        // if there was a CURL error on the request
        if($error) {
            $this->requestStatus = false;
            //return $this;
            throw new ErplySyncException('CURL error: '.$response.':'.$error.': '.$errorNumber, self::CURL_ERROR);
        }

        $this->response = $response;
        $this->requestStatus = true;
        $this->request_limit_reached_on = null;

        if(($error_code = $this->response()->status->errorCode) > 0){

            // if the error was because we hit the API request limit
            if($error_code == self::API_REQUEST_LIMIT_PER_HOUR_ERR_CODE) {
                $this->request_limit_reached_on = date('Y-m-d h:i', time());
            }
            // if the error wasn't because of the API request limit
            else {
                $this->request_limit_reached_on = null;
                $this->requestStatus = false;
            }
        }

        return $this;

    }

    /**
     * magic function for calling the api based on input method
     *
     * @param  string $method
     * @param  array  $parameters
     * @return array
     */
    public function __call($method, $parameters = array())
    {
        $param = (isset($parameters[0]) ? $parameters[0] : $parameters);
        return $this->request($method, $param);
    }

    /**
     * Check if the last request was a success.
     *
     * @return bool
     */
    public function wasSuccess() {
        return $this->requestStatus;
    }

    /**
     * Get back the whole response, json decoded
     *
     * @return mixed
     */
    public function response() {
        return json_decode($this->response);
    }

    /**
     * Get back only the original status from the response JSON
     *
     * @return mixed
     */
    public function responseStatus() {
        if( ! property_exists($this->response(), 'status') ) return null;

        return $this->response()->status;
    }

    /**
     * Get back all the records from the response JSON
     *
     * @return mixed
     */
    public function records() {
        if( ! property_exists($this->response(), 'records') ) return [];

        return $this->response()->records;
    }

    /**
     * Get authorization key for Erply from session, or make an API call to Erply to generate new one if the current is not existent or invalid
     *
     * @return mixed
     * @throws ErplySyncException
     */
    protected function getSessionKey()
    {

        if( ! session()) throw new ErplySyncException('PHP session not started', self::PHP_SESSION_NOT_STARTED);

        if($this->authorizationKeyNotValid()) {

            $response = $this->getNewAuthorizationKey();

            //cache the key in PHP session
            session()->put('EAPISessionKey.' . $this->clientCode . '.' . $this->username . '', $response['records'][0]['sessionKey']);
            session()->put('EAPISessionKeyExpires.' . $this->clientCode . '.' . $this->username . '', time() + $response['records'][0]['sessionLength'] - 30);

        }

        return session()->get('EAPISessionKey.' . $this->clientCode . '.' . $this->username . '');
    }

    /**
     * Check if all of the required fields are setup and if not, throw ErplySyncException
     *
     * @throws ErplySyncException
     */
    protected function abortIfSetupIncorrectly()
    {
        if(! $this->username OR ! $this->password OR ! $this->clientCode OR ! $this->url) throw new ErplySyncException('Missing parameters', self::MISSING_PARAMETERS);
    }

    /**
     * Add essential parameters ( that are always needed for the API call to Erply ) to $parameters array
     *
     * @param $request
     * @param $parameters
     *
     * @return mixed
     * @throws ErplySyncException
     */
    protected function addEssentialParameters($request, array $parameters = [])
    {
        //add extra params
        $parameters['request'] = $request; // what type of request we are trying to do over at Erply ( ex. 'getClientGroups' )
        $parameters['clientCode'] = $this->clientCode; // language string ( ex. 'eng' )
        $parameters['version'] = '1.0';

        // if the request is NOT the one to verify a user, wee need a session key in our params
        if($request != "verifyUser") $parameters['sessionKey'] = $this->getSessionKey();

        return $parameters;
    }

    /**
     * Create all the options for the CURL request and return a $handle
     *
     * @param $parameters
     *
     * @return resource
     */
    protected function setCurlOptions($parameters)
    {
        //create request
        $handle = curl_init($this->url);

        //set the payload
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $parameters);

        //return body only
        curl_setopt($handle, CURLOPT_HEADER, 0);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);

        //create errors on timeout and on response code >= 300
        curl_setopt($handle, CURLOPT_TIMEOUT, 45);
        curl_setopt($handle, CURLOPT_FAILONERROR, true);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);

        //set up host and cert verification
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($handle, CURLOPT_SSLVERSION, 3);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);

        if($this->sslCACertPath) {
            curl_setopt($handle, CURLOPT_CAINFO, $this->sslCACertPath);
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
        }

        return $handle;
    }

    /**
     * If we don't have authorization key set in session, or it does exist but it is expired, we return false
     *
     * @return bool
     */
    protected function authorizationKeyNotValid()
    {
        return !session()->has('EAPISessionKey.' . $this->clientCode . '.' . $this->username . '') ||
               !session()->has('EAPISessionKeyExpires.' . $this->clientCode . '.' . $this->username . '') ||
               session()->get('EAPISessionKeyExpires.' . $this->clientCode . '.' . $this->username . '') < time();
    }

    /**
     * Make an Erply API call to get new authorization key so that we can set it in session
     *
     * @return mixed
     * @throws ErplySyncException
     */
    protected function getNewAuthorizationKey()
    {
        $result = $this->request("verifyUser", array("username" => $this->username, "password" => $this->password));
        $response = json_decode($result->response, true);

        //check failure
        if( ! isset($response['records'][0]['sessionKey'])) {

            session()->forget('EAPISessionKey.' . $this->clientCode . '.' . $this->username . '');
            session()->forget('EAPISessionKeyExpires.' . $this->clientCode . '.' . $this->username . '');

            $e = new ErplySyncException('Verify user failure', self::VERIFY_USER_FAILURE);
            $e->response = $response;
            throw $e;

        }

        return $response;
    }

    /**
     * Set request status to FALSE if we are still in the "API limit reached zone".
     * Erply API suggests waiting for 10 minutes between calls if the API limit is reached
     *
     * @throws ErplySyncException
     */
    protected function abortIfApiLimitReached()
    {
        if($this->request_limit_reached_on && date('Y-m-d h:i', strtotime($this->request_limit_reached_on . " +10 minutes")) > date('Y-m-d h:i', time())) {
            $this->requestStatus = false;
            return $this;
            //throw new ErplySyncException('API request limit error reached on: ' . $this->request_limit_reached_on, self::API_REQUEST_LIMIT_PER_HOUR_ERR_CODE);
        }
    }

    /**
     * increment api request attempt
     *
     * @param String $request
     * @return void
     */
    protected function incrementApiRequestAttempt($request)
    {
        // first check if table exists
        $exists = DB::select("SHOW TABLES LIKE 'external_api_counters';");

        if( ! $exists ) return;

        // try to get row to increment
        $date = DB::table('external_api_counters')
                        ->where('created_at', 'LIKE', Carbon::now()->format('Y-m-d H:').'%')
                        ->where('method', $request);

        if( $date->get() ){
            // row already exists, so just increment
            $date->increment('count');
        }else{
            // row doesn't exits, so just insert new
            DB::table('external_api_counters')->insert(['api'=>'erply', 'method'=>$request, 'count'=>1, 'created_at'=>Carbon::now()]);
        }
    }
}
