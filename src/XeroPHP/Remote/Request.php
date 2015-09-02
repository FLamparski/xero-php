<?php

namespace XeroPHP\Remote;

use XeroPHP\Application;
use XeroPHP\Exception;
use XeroPHP\Helpers;

/**
 * Handles dispatching actual requests to Xero.
 * It also allows low-level API access if you use setBody.
 */
class Request {

    const METHOD_GET    = 'GET';
    const METHOD_PUT    = 'PUT';
    const METHOD_POST   = 'POST';
    const METHOD_DELETE = 'DELETE';

    const CONTENT_TYPE_HTML = 'text/html';
    const CONTENT_TYPE_XML  = 'text/xml';
    const CONTENT_TYPE_JSON = 'application/json';
    const CONTENT_TYPE_PDF  = 'application/pdf';

    const HEADER_ACCEPT            = 'Accept';
    const HEADER_CONTENT_TYPE      = 'Content-Type';
    const HEADER_CONTENT_LENGTH    = 'Content-Length';
    const HEADER_AUTHORIZATION     = 'Authorization';
    const HEADER_IF_MODIFIED_SINCE = 'If-Modified-Since';

    private $app;
    private $url;
    private $method;
    private $headers;
    private $parameters;
    private $body;

    /**
     * @var \XeroPHP\Remote\Response;
     */
    private $response;

    /**
     * Create a new request.
     * @param Application app The application to use to access Xero
     * @param URL url The URL to call
     * @param string method The HTTP method to use
     * @throws Exception When an incorrect method is specified.
     */
    public function __construct(Application $app, URL $url, $method = self::METHOD_GET) {

        $this->app = $app;
        $this->url = $url;
        $this->headers = array();
        $this->parameters = array();

        switch($method) {
            case self::METHOD_GET:
            case self::METHOD_PUT:
            case self::METHOD_POST:
            case self::METHOD_DELETE:
                $this->method = $method;
                break;
            default:
                throw new Exception("Invalid request method [$method]");
        }

        //Default to XML so you get the  xsi:type attribute in the root node.
        $this->setHeader(self::HEADER_ACCEPT, self::CONTENT_TYPE_XML);

    }

    /**
     * Sign and send the request to Xero.
     * @throws Exception In case of a cURL error, an Exception is thrown.
     */
    public function send() {

        //Sign the request - this just sets the Authorization header
        $this->app->getOAuthClient()->sign($this);

        // configure curl
        $ch = curl_init();
        curl_setopt_array($ch, $this->app->getConfig('curl'));

        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->getMethod());

        if(isset($this->body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->body);
        }

        //build header array.  Don't provide glue so it'll return the array itself.
        //Maybe could be a but cleaner but nice to reuse code.
        $header_array = Helpers::flattenAssocArray($this->getHeaders(), '%s: %s');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header_array);

        $full_uri = $this->getUrl()->getFullURL();
        //build parameter array - the only time there's a post body is with the XML, only escape at this point
        $query_string = Helpers::flattenAssocArray($this->getParameters(), '%s=%s', '&', true);

        if(strlen($query_string) > 0)
            $full_uri .= "?$query_string";

        curl_setopt($ch, CURLOPT_URL, $full_uri);

        if($this->method === self::METHOD_POST || $this->method === self::METHOD_PUT)
            curl_setopt($ch, CURLOPT_POST, true);

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);

        if($response === false) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }

        $this->response = new Response($this, $response, $info);
        $this->response->parse();

        return $this->response;
    }

    /**
     * Set a URL parameter
     */
    public function setParameter($key, $value) {
        $this->parameters[$key] = $value;

        return $this;
    }

    public function getParameters() {
        return $this->parameters;
    }

    /**
     * @param $key string Name of the header
     * @return null|string Header or null if not defined
     */
    public function getHeader($key) {
        if(!isset($this->headers[$key]))
            return null;

        return $this->headers[$key];
    }

    public function getHeaders() {
        return $this->headers;
    }

    /**
     * Returns the response if the request has been sent, or null if it has not.
     * @return \XeroPHP\Remote\Response|null
     */
    public function getResponse() {
        if(isset($this->response))
            return $this->response;

        return null;
    }

    /**
     * @param $key string Name of the header
     * @param $val string Value of the header
     *
     * @return $this
     */
    public function setHeader($key, $val) {
        $this->headers[$key] = $val;

        return $this;
    }

    /**
     * @return string The request method
     */
    public function getMethod() {
        return $this->method;
    }

    /**
     * @return URL
     */
    public function getUrl() {
        return $this->url;
    }

    /**
     * Sets the request body and content type (for POST/PUT requests).
     *
     * You can use this to send arbitrary XML to Xero, if you need to do something
     * at a low level that XeroPHP can't easily do.
     * @param string body The request body
     * @param string content_type The MIME content type
     */
    public function setBody($body, $content_type = self::CONTENT_TYPE_XML) {

        $this->setHeader(self::HEADER_CONTENT_LENGTH, strlen($body));
        $this->setHeader(self::HEADER_CONTENT_TYPE, $content_type);

        $this->body = $body;

        return $this;
    }


}
