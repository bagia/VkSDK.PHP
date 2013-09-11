<?php
/**
 * @brief Helper class to use the Vk REST API
 * @author bagia
 * @license MIT
 */

require_once('RestClient.php');

class VkSDK {

    /**
     * $appSecret and $redirectURI are not necessary if you can restore
     * the access token and the user id by using the setAccessToken(access_token)
     * and the setUserId(user_id) methods.
     * @param $appId Client ID of the app
     * @param string $appSecret (optional) Client secret of the app
     * @param string $redirectURI (optional) URL were the "code" parameter will be passed
     */
    public function __construct($appId, $appSecret = '', $redirectURI = '') {
        self::startSessionIfNecessary();

        $this->_restClient = new RestClient();

        $this->_appId = $appId;
        $this->_appSecret = $appSecret;
        $this->_redirectURI = $redirectURI;
    }

    /**
     * Generate the vk.com login URL to start the authorization process
     * @param $permissionScope Permission scope (e.g. 'offline,groups,wall')
     * @return string URL to redirect the user to
     */
    public function getLoginURL($permissionScope) {
        $this->_permissionScope = $permissionScope;

        $parameters = array(
            'client_id' => $this->_appId,
            'scope' => $this->_permissionScope,
            'redirect_uri' => $this->_redirectURI,
            'response_type' => 'code',
        );

        return $this->_authorizeURL . '?' . http_build_query($parameters);
    }

    /**
     * Login with a given code. Usually this would be called on the Redirect URI
     * page with the 'code' GET parameter as the parameter:
     * $sdk->loginWithCode($_GET['code']);
     * @param $code Code returned by vk.com after the user gave their credentials
     * @throws Exception if the code is invalid or the obtaining the access token failed
     */
    public function loginWithCode($code) {
        if (empty($code)) {
            throw new Exception("Invalid code. Check the code parameter is correct.");
        }

        $parameters = array(
            'client_id' => $this->_appId,
            'client_secret' => $this->_appSecret,
            'code' => $code,
            'redirect_uri' => $this->_redirectURI,
        );

        $result = $this->_restClient->HTTP($this->_accessTokenURL, $parameters, 'GET');

        if ($result['Code'] != 200) {
            throw new Exception("Unable to log in: " . $result['Response']);
        }

        $response = json_decode($result['Response']);
        $this->setAccessToken($response->access_token);
        $this->setUserId($response->user_id);
        $this->_expiresIn = $response->expires_in;
    }

    /**
     * Tries to restore the access token from the session. If it is not
     * possible, returns NULL.
     * @return string The access token or NULL
     */
    public function getAccessToken() {
        if (empty($this->_accessToken)) {
            if (isset($_SESSION[$this->_sessionKey]['access_token'])) {
                $this->_accessToken = $_SESSION[$this->_sessionKey]['access_token'];
            }
        }
        return $this->_accessToken;
    }

    /**
     * Stores the access token in the session.
     * @param $accessToken The access token
     */
    public function setAccessToken($accessToken) {
        $this->_accessToken = $accessToken;
        $_SESSION[$this->_sessionKey]['access_token'] = $accessToken;
    }

    /**
     * Tries to restore the user id from the session. If it is not possible,
     * returns NULL.
     * @return string The user id or NULL
     */
    public function getUserId() {
        if (empty($this->_userId)) {
            if (isset($_SESSION[$this->_sessionKey]['user_id'])) {
                $this->_userId = $_SESSION[$this->_sessionKey]['user_id'];
            }
        }
        return $this->_userId;
    }

    /**
     * Stores the user id in the session.
     * @param $userId The user id
     */
    public function setUserId($userId) {
        $this->_userId = $userId;
        $_SESSION[$this->_sessionKey]['user_id'] = $userId;
    }

    /**
     * Access an API method
     * @param string $method API method (e.g. user.get, wall.post...)
     * @param array $parameters Array of the parameters to send to the method
     * @return object The object returned by the API
     */
    public function api($method, $parameters) {
        if (!isset($parameters['access_token']))
            $parameters['access_token'] = $this->getAccessToken();
        $result = $this->_restClient->HTTP($this->_methodBaseURL . $method, $parameters, 'POST');
        return json_decode($result['Response']);
    }

    /**
     * @return object Result of users.get for active user
     */
    public function getUser() {
        return $this->api('users.get', array(
            'user_ids' => $this->getUserId(),
            'v' => '5.0',
        ));
    }

    private $_appId;
    private $_appSecret;
    private $_restClient;
    private $_accessToken;
    private $_redirectURI;
    private $_permissionScope;
    private $_userId;
    private $_expiresIn;
    private $_sessionKey = 'VkSDK';
    private $_authorizeURL = 'https://oauth.vk.com/authorize';
    private $_accessTokenURL = 'https://oauth.vk.com/access_token';
    private $_methodBaseURL = 'https://api.vk.com/method/';

    private static function startSessionIfNecessary() {
        $sessionId = session_id();
        if(empty($sessionId))
            session_start();
    }

}