<?php

namespace BW;

/**
 * The Vkontakte PHP SDK
 *
 * @author Bocharsky Victor, https://github.com/Vastly
 */
class VkontakteApiTransport
{

    const VERSION = '5.5';

    /**
     * The application ID
     * @var integer
     */
    private $appId;

    /**
     * The application secret code
     * @var string
     */
    private $secret;

    /**
     * The scope for login URL
     * @var array
     */
    private $scope = array();

    /**
     * The URL to which the user will be redirected
     * @var string
     */
    private $redirect_uri;

    /**
     * The response type of login URL
     * @var string
     */
    private $responceType = 'code';

    /**
     * The current access token
     * @var \StdClass
     */
    private $accessToken;

    /**
     * The last error number
     * @var string
     */
    private $iErrorNo=0;

    /**
     * The last error text
     * @var string
     */
    private $sError='';

    /**
     * The last error header from response
     * @var string
     */
    private $sErrorHeader='';


    /**
     * The Vkontakte instance constructor for quick configuration
     * @param array $config
     */
    public function __construct(array $config)
    {
        if (isset($config['access_token'])) {
            $this->setAccessToken(json_encode(array('access_token' => $config['access_token'])));
        }
        if (isset($config['app_id'])) {
            $this->setAppId($config['app_id']);
        }
        if (isset($config['secret'])) {
            $this->setSecret($config['secret']);
        }
        if (isset($config['scopes'])) {
            $this->setScope($config['scopes']);
        }
        if (isset($config['redirect_uri'])) {
            $this->setRedirectUri($config['redirect_uri']);
        }
        if (isset($config['response_type'])) {
            $this->setResponceType($config['response_type']);
        }
    }


    /**
     * Get the user id of current access token
     * @return integer
     */
    public function getUserId()
    {

        return $this->accessToken->user_id;
    }

    /**
     * Set the application id
     * @param integer $appId
     * @return \Vkontakte
     */
    public function setAppId($appId)
    {
        $this->appId = $appId;

        return $this;
    }

    /**
     * Get the application id
     * @return integer
     */
    public function getAppId()
    {

        return $this->appId;
    }

    /**
     * Set the application secret key
     * @param string $secret
     * @return \Vkontakte
     */
    public function setSecret($secret)
    {
        $this->secret = $secret;

        return $this;
    }

    /**
     * Get the application secret key
     * @return string
     */
    public function getSecret()
    {

        return $this->secret;
    }

    /**
     * Set the scope for login URL
     * @param array $scope
     * @return \Vkontakte
     */
    public function setScope(array $scope)
    {
        $this->scope = $scope;

        return $this;
    }

    /**
     * Get the scope for login URL
     * @return array
     */
    public function getScope()
    {

        return $this->scope;
    }

    /**
     * Set the URL to which the user will be redirected
     * @param string $redirect_uri
     * @return \Vkontakte
     */
    public function setRedirectUri($redirect_uri)
    {
        $this->redirect_uri = $redirect_uri;

        return $this;
    }

    /**
     * Get the URL to which the user will be redirected
     * @return string
     */
    public function getRedirectUri()
    {

        return $this->redirect_uri;
    }

    /**
     * Set the response type of login URL
     * @param string $responceType
     * @return \Vkontakte
     */
    public function setResponceType($responceType)
    {
        $this->responceType = $responceType;

        return $this;
    }

    /**
     * Get the response type of login URL
     * @return string
     */
    public function getResponceType()
    {

        return $this->responceType;
    }

    /**
     * Get the login URL via Vkontakte
     * @return string
     */
    public function getLoginUrl($sDisplayType = '')
    {

        return 'https://oauth.vk.com/authorize'
        . '?client_id=' . urlencode($this->getAppId())
        . ($sDisplayType ? '&display='.$sDisplayType : '')
        . '&scope=' . urlencode(implode(',', $this->getScope()))
        . '&redirect_uri=' . urlencode($this->getRedirectUri())
        . '&response_type=' . urlencode($this->getResponceType())
        . '&v=' . urlencode(self::VERSION);
    }

    /**
     * Check is access token expired
     * @return boolean
     */
    public function isAccessTokenExpired()
    {

        return time() > $this->accessToken->created + $this->accessToken->expires_in;
    }

    /**
     * Authenticate user and get access token from server
     * @param string $code
     * @return \Vkontakte
     */
    public function authenticate($code = NULL)
    {
        $code = $code ? $code : $_GET['code'];

        $url = 'https://oauth.vk.com/access_token'
            . '?client_id=' . urlencode($this->getAppId())
            . '&client_secret=' . urlencode($this->getSecret())
            . '&code=' . urlencode($code)
            . '&redirect_uri=' . urlencode($this->getRedirectUri());

        $token = $this->curl($url);
        $data = json_decode($token);
        $data->created = time(); // add access token created unix timestamp
        $token = json_encode($data);

        $this->setAccessToken($token);

        return $this;
    }

    /**
     * Set the access token
     * @param string $token The access token in json format
     * @return \Vkontakte
     */
    public function setAccessToken($token)
    {
        $this->accessToken = json_decode($token);

        return $this;
    }

    /**
     * Get the access token
     * @param string $code
     * @return string The access token in json format
     */
    public function getAccessToken()
    {

        return json_encode($this->accessToken);
    }

    /**
     * Make an API call to https://api.vk.com/method/
     * @return string The response, decoded from json format
     */
    public function api($method, array $query = array())
    {
        if (!isset($query['v'])) {
            $query['v'] = self::VERSION;
        }
        /* Generate query string from array */
        $parameters = array();
        foreach ($query as $param => $value) {
            $q = $param . '=';
            if (is_array($value)) {
                $q .= urlencode(implode(',', $value));
            } else {
                $q .= urlencode($value);
            }

            $parameters[] = $q;
        }

        $q = implode('&', $parameters);
        if (count($query) > 0) {
            $q .= '&'; // Add "&" sign for access_token if query exists
        }
        $url = 'https://api.vk.com/method/' . $method . '?' . $q . 'access_token=' . $this->accessToken->access_token;
        $result = json_decode($this->curl($url));

        if (isset($result->response)) {

            return $result->response;
        }

        return $result;
    }

    /**
     * Make the curl request to specified url
     * @param string $url The url for curl() function
     * @return mixed The result of curl_exec() function
     * @throws \Exception
     */
    protected function curl($url, $method = 'GET', $postfields = array())
    {
        // create curl resource
        $ch = curl_init();

        $opts = array(
            CURLOPT_USERAGENT => 'VK/1.0 (+https://github.com/vladkens/VK))',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST => ($method == 'POST'),
            CURLOPT_POSTFIELDS => $postfields,
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYHOST => false
        );

        curl_setopt_array($ch, $opts);

        $out = curl_exec($ch);

        $err = curl_errno($ch);
        if ($err){
            $this->iErrorNo = $err;
            $this->sError = curl_error($ch);
            $this->sErrorHeader = curl_getinfo($ch);
        }


        return $out;
    }

    /**
     * Get last error info
     * @return array
     */
    public function getLastError(){
        if ($this->iErrorNo){
            return array(
                'iErrorNo' => $this->iErrorNo,
                'sError' => $this->sError,
                'sErrorHeader' => $this->sErrorHeader,
            );
        }
        else{
            return array();
        }
    }

    /**
     * Clear last error info
     */
    public function clearLastError(){
        $this->iErrorNo = 0;
        $this->sError = '';
        $this->sErrorHeader = '';
    }

    /**
     * @param $url
     * @param $postfields
     * @return mixed
     */
    public function uploadMedia($url, $postfields)
    {
        return $this->curl($url, 'POST', $postfields);
    }


}
