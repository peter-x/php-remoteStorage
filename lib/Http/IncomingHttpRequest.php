<?php

class IncomingHttpRequestException extends Exception {

}

class IncomingHttpRequest {

    public function getRequest() {
        $required_keys = array("SERVER_NAME", "SERVER_PORT", "REQUEST_URI", "REQUEST_METHOD");
        foreach ($required_keys as $r) {
            if (!array_key_exists($r, $_SERVER) || empty($_SERVER[$r])) {
                throw new IncomingHttpRequestException("missing (one or more) required environment variables");
            }
        }
        $request = new HttpRequest($this->getRequestUri(), $_SERVER['REQUEST_METHOD']);
        $request->setContent($this->getContent());
        $request->setHeaders($this->getRequestHeaders());
        $request->setPathInfo($this->getPathInfo());
        return $request;
    }

    private function getPathInfo() {
        return array_key_exists('PATH_INFO', $_SERVER) ? $_SERVER['PATH_INFO'] : NULL;
    }

    private function getRequestUri() {
        // scheme
        if (array_key_exists("HTTPS", $_SERVER) && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $scheme = "https";
        } else {
            $scheme = "http";
        }

        // server name
        if (filter_var($_SERVER['SERVER_NAME'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === FALSE) {
            $name = $_SERVER['SERVER_NAME'];
        } else {
            $name = '[' . $_SERVER['SERVER_NAME'] . ']';
        }

        // server port
        if (($_SERVER['SERVER_PORT'] === "80" && $scheme === "http") || ($_SERVER['SERVER_PORT'] === "443" && $scheme === "https")) {
            $port = "";
        } else {
            $port = ":" . $_SERVER['SERVER_PORT'];
        }

        return $scheme . "://" . $name . $port . $_SERVER['REQUEST_URI'];
    }

    private function getContent() {
        if ($_SERVER['REQUEST_METHOD'] !== "POST" && $_SERVER['REQUEST_METHOD'] !== "PUT") {
            return NULL;
        }
        if (array_key_exists("CONTENT_LENGTH", $_SERVER) && $_SERVER['CONTENT_LENGTH'] > 0) {
            return $this->getRawContent();
        }
        return NULL;
    }

    public function getRawContent() {
        // @codeCoverageIgnoreStart
        return file_get_contents("php://input");
        // @codeCoverageIgnoreEnd
    }

    public function getRequestHeaders() {
	// The $_SERVER environment does not contain the Authorization
        // header by default. On Apache this header can be extracted with
	// apache_request_headers(), but this does not work on other
	// web servers...
        $requestHeaders = $_SERVER;
        if(function_exists("apache_request_headers")) {
                $apacheHeaders = apache_request_headers();
                if(array_key_exists("Authorization", $apacheHeaders)) {
                        // add the HTTP_AUTHORIZATION header
                        $requestHeaders['HTTP_AUTHORIZATION'] = $apacheHeaders['Authorization'];
                }
        }
        return $requestHeaders;
    }

}

?>
