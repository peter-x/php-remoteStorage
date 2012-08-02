<?php

class HttpRequestException extends Exception {

}

class HttpRequest {

    private $_uri;
    private $_method;
    private $_headers;
    private $_content;
    private $_pathInfo;

    public function __construct($requestUri, $requestMethod = "GET") {
        $this->setRequestUri(new Uri($requestUri));
        $this->setRequestMethod($requestMethod);
        $this->_headers = array();
        $this->_content = NULL;
        $this->_pathInfo = NULL;
    }

    public function setRequestUri(Uri $u) {
        $this->_uri = $u;
    }

    public function getRequestUri() {
        return $this->_uri;
    }

    public function setRequestMethod($method) {
        if (!in_array($method, array("GET", "POST", "PUT", "DELETE", "HEAD", "OPTIONS"))) {
            throw new HttpRequestException("invalid or unsupported request method");
        }
        $this->_method = $method;
    }

    public function getRequestMethod() {
        return $this->_method;
    }

    public function setPostParameters(array $parameters) {
        if ($this->getRequestMethod() !== "POST") {
            throw new HttpRequestException("request method should be POST");
        }
        $this->setHeader("Content-Type", "application/x-www-form-urlencoded");
        $this->setContent(http_build_query($parameters));
    }

    public function getQueryParameters() {
        if ($this->_uri->getQuery() === NULL) {
            return array();
        }
        $parameters = array();
        parse_str($this->_uri->getQuery(), $parameters);
        return $parameters;
    }

    public function getQueryParameter($key) {
        $parameters = $this->getQueryParameters();
        return (array_key_exists($key, $parameters) && !empty($parameters[$key])) ? $parameters[$key] : NULL;
    }

    public function getPostParameters() {
        if ($this->getRequestMethod() !== "POST") {
            throw new HttpRequestException("request method should be POST");
        }
        $parameters = array();
        parse_str($this->getContent(), $parameters);
        return $parameters;
    }

    public function setHeaders(array $headers) {
        foreach ($headers as $k => $v) {
            $this->setHeader($k, $v);
        }
    }

    public function setHeader($headerKey, $headerValue) {
        $foundHeaderKey = $this->_getHeaderKey($headerKey);
        if ($foundHeaderKey === NULL) {
            $this->_headers[$headerKey] = $headerValue;
        } else {
            $this->_headers[$foundHeaderKey] = $headerValue;
        }
    }

    public function getHeader($headerKey) {
        $headerKey = $this->_getHeaderKey($headerKey);
        if ($headerKey === NULL) {
            throw new HttpRequestException("no such header");
        }
        return $this->_headers[$headerKey];
    }

    /**
     * Look for a header in a case insensitive way. It is possible to have a 
     * header key "Content-type" or a header key "Content-Type", these should
     * be treated as the same.
     * 
     * @param headerName the name of the header to search for
     * @returns The name of the header as it was set (original case)
     *
     */
    private function _getHeaderKey($headerKey) {
        $headerKeys = array_keys($this->_headers);
        $keyPositionInArray = array_search(strtolower($headerKey), array_map('strtolower', $headerKeys));
        return ($keyPositionInArray === FALSE) ? NULL : $headerKeys[$keyPositionInArray];
    }

    public function headerExists($headerKey) {
        return $this->_getHeaderKey($headerKey) !== NULL;
    }

    public function getHeaders($formatted = FALSE) {
        if (!$formatted) {
            return $this->_headers;
        }
        $hdrs = array();
        foreach ($this->_headers as $k => $v) {
            array_push($hdrs, $k . ": " . $v);
        }
        return $hdrs;
    }

    public function setContent($content) {
        $this->_content = $content;
    }

    public function getContent() {
        return $this->_content;
    }

    public function setPathInfo($pathInfo) {
        $this->_pathInfo = $pathInfo;
    }

    public function getPathInfo() {
        return $this->_pathInfo;
    }

    public function getRestInfo() {
        return new RestInfo($this->getPathInfo(), $this->getRequestMethod());
    }

    public function getBasicAuthUser() {
        return $this->headerExists("PHP_AUTH_USER") ? $this->getHeader("PHP_AUTH_USER") : NULL;
    }

    public function getBasicAuthPass() {
        return $this->headerExists("PHP_AUTH_PW") ? $this->getHeader("PHP_AUTH_PW") : NULL;
    }

}

class RestInfo {

    private $_pathInfo;
    private $_requestMethod;
    private $_explodedPath;

    public function __construct($pathInfo, $requestMethod) {
        $this->_pathInfo = $pathInfo;
        $this->_requestMethod = $requestMethod;
        $this->_explodedPath = NULL;

        if(NULL !== $this->_pathInfo) {
            if(1 < strlen($this->_pathInfo)) {
                $this->_explodedPath = explode("/", substr($this->_pathInfo, 1));
            }
        }
    }

    private function _getRequestMethod() {
        return $this->_requestMethod;
    }

    public function getCollection() {
        if(is_array($this->_explodedPath) && 0 < count($this->_explodedPath)) {
            return $this->_explodedPath[0];
        }
        return NULL;
    }

    public function getResource() {
        if(is_array($this->_explodedPath) && 1 < count($this->_explodedPath)) {
            return $this->_explodedPath[1];
        }
        return NULL;
    }

    private function _hasTrailingSlash() {
        return empty($this->_explodedPath[count($this->_explodedPath)-1]);
    }

    public function match($requestMethod, $collectionName, $requireResource) {
        if($requestMethod !== $this->_getRequestMethod()) {
            return FALSE;
        }
        if($collectionName !== $this->getCollection()) {
            return FALSE;
        }
        if($requireResource) {
            if(NULL === $this->getResource()) {
                return FALSE;
            }
            if($this->_hasTrailingSlash()) {
                return FALSE;
            }
        } else {
            if(!$this->_hasTrailingSlash()) {
                return FALSE;
            }
        }
        return TRUE;
    }

}

?>
