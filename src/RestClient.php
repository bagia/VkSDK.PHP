<?php
/**
 * @brief Helper class to browse the web
 * @author bagia
 * @license MIT
 */
class RestClient
{
    private $ch; // cURL handle

    private $url; // current URL
    private $maxRedirs; // max allowed redirects
    private $currentRedirs; // current number of redirects followed

    private $headers; // HTTP headers
    private $cookie; // current cookie value
    private $referer; // current referer
    private $response; // current HTML content

    /**
     * Navigate to the specified URL
     * Raises a warning if too many redirects have been followed
     * Can post a file if its post value is prefixed by @
     * @param string $url URL to navigate to
     * @param array $parameters Parameters to send
     * @param string $method Either GET, POST, PUT, etc
     * @return mixed FALSE or the HTML content of the page
     */
    public function HTTP($url, $parameters = array(), $method = 'GET')
    {
        $method = strtoupper($method);

        if ($this->currentRedirs > $this->maxRedirs)
        {
            // We overtook the allowed number of redirects.
            $this->currentRedirs = 0;
            trigger_error('Too many redirects.', E_USER_WARNING);
            return FALSE;
        }

        if (!empty($this->referer))
            curl_setopt($this->ch, CURLOPT_REFERER, $this->referer);

        curl_setopt($this->ch, CURLOPT_COOKIE, $this->cookie);

        if ($method == 'GET') {
            curl_setopt($this->ch, CURLOPT_HTTPGET, TRUE);
            $query = http_build_query($parameters);
            $url .= '?' . $query;
        } else if ($method == 'POST') {
            curl_setopt($this->ch, CURLOPT_POST, TRUE);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $parameters);
        } else if ($method == 'PUT') {
            curl_setopt($this->ch, CURLOPT_PUT, TRUE);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $parameters);
        } else {
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $parameters);
        }
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array('X-HTTP-Method-Override: ' . $method));

        $this->url = $url;
        curl_setopt($this->ch, CURLOPT_URL, $url);

        $result = $this->exec();

        // Moved
        if ($this->currentRedirs <= $this->maxRedirs
            && ($result['Code'] >= 300 && $result['Code'] < 400)) {
            $url = $this->getHeader('Location');
            if ( !is_null($url) ) {
                if (stripos($url, 'http') !== 0) {
                    if (stripos($url, '/') !== 0)
                        $url = '/' . $url;

                    $url = $this->getRootUrl() . $url;
                }

                $this->currentRedirs++;
                return $this->HTTP($url);
            }

        }

        // We arrived to destination
        $this->currentRedirs = 0;

        return $result;
    }

    /**
     * Get the value of a specific header
     * @param $name Header name
     * @return mixed the value of the header, NULL if the header is not available
     */
    public function getHeader($name)
    {
        $headers = $this->getHeaders();
        if ( isset($headers[$name]) )
            return $headers[$name];

        return NULL;
    }

    public function __construct()
    {
        $this->maxRedirs = 5;
        $this->currentRedirs = 0;
        $this->url = '';
        $this->headers = array();
        $this->cookie = '';
        $this->referer = '';
        $this->response = '';

        $this->init();
    }

    public function __destruct()
    {
        $this->close();
    }

    private function init()
    {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_USERAGENT, "Mozilla/6.0 (Windows NT 6.2; WOW64; rv:16.0.1) Gecko/20121011 Firefox/16.0.1");
        curl_setopt($this->ch, CURLINFO_HEADER_OUT, TRUE);
        curl_setopt($this->ch, CURLOPT_HEADER, TRUE);
        curl_setopt($this->ch, CURLOPT_FORBID_REUSE, FALSE);
    }

    private function exec()
    {
        // Reset member variables
        $this->response = '';
        $this->headers = array();

        $headers = array();
        $html = '';

        ob_start();
        curl_exec($this->ch);
        $output = ob_get_contents();
        ob_end_clean();

        $retcode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

        // Separate Headers and content
        $content = $output;
        do {
            $split = explode("\r\n\r\n", $content, 2);
            $head = reset($split);
            $content = end($split);
        } while (preg_match('#http/[0-9].[0-9] 100 continue#i', $head));

        if (count($split) > 1)
            $html = trim($split[1]);
        $h = trim($split[0]);
        $lines = explode("\n", $h);
        foreach($lines as $line) {
            $kv = explode(':', $line, 2);
            $k = trim($kv[0]);
            $v = '';
            if (count($kv) > 1)
                $v = trim($kv[1]);
            $headers[$k] = $v;
        }

        $this->referer = $this->url;

        // Set cookie
        if (!empty($headers['Set-Cookie']))
            $this->cookie = $headers['Set-Cookie'];

        // Set member variables
        $this->response = $html;
        $this->headers = $headers;

        return array('Code' => $retcode, 'Headers' => $headers, 'Response' => $html);
    }

    private function close()
    {
        curl_close($this->ch);
    }

    //
    // Some helpers
    //

    /**
     * @return string
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * The URL of the current page. It is updated when a redirect is followed.
     * @return string URL of the current page
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set the maximum number of redirects to follow before failing.
     * @param int $maxRedirs max number of redirects
     */
    public function setMaxRedirs($maxRedirs = 5) {
        $this->maxRedirs = $maxRedirs;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getRootUrl()
    {
        $user = $this->urlUser();
        if (!empty($user)) {
            $pass = $this->urlPass();
            if (!empty($pass))
                $user .= ':' . $pass;

            $user .= '@';
        }

        $port = $this->urlPort();
        if (!empty($port))
            $port = ':' . $port;

        return $this->urlScheme() . '://' . $user . $this->urlHost() . $port;
    }

    public function urlScheme()
    {
        return parse_url($this->url, PHP_URL_SCHEME);
    }

    public function urlHost()
    {
        return parse_url($this->url,  PHP_URL_HOST);
    }

    public function urlPort()
    {
        return parse_url($this->url,  PHP_URL_PORT);
    }

    public function urlUser()
    {
        return parse_url($this->url,  PHP_URL_USER);
    }

    public function urlPass()
    {
        return parse_url($this->url,  PHP_URL_PASS);
    }

    public function urlPath()
    {
        return parse_url($this->url,  PHP_URL_PATH);
    }

    public function urlQuery()
    {
        return parse_url($this->url,  PHP_URL_QUERY);
    }

    public function urlFragment()
    {
        return parse_url($this->url,  PHP_URL_FRAGMENT);
    }
}