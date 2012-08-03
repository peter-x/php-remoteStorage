<?php

class RemoteStorageRestInfo {

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

    public function getCollection() {
        if(is_array($this->_explodedPath) && 1 < count($this->_explodedPath)) {
            return $this->_explodedPath[1];
        }
        return NULL;
    }

    public function getPathInfo() {
        return $this->_pathInfo;
    }

    public function getRequestMethod() {
        return $this->_requestMethod;
    }

    public function getResourceOwner() {
        if(is_array($this->_explodedPath) && 0 < count($this->_explodedPath)) {
            return $this->_explodedPath[0];
        }
        return NULL;
    }

    public function isDirectoryRequest() {
        return empty($this->_explodedPath[count($this->_explodedPath)-1]);
    }

    public function isResourceRequest() {
        return !$this->isDirectory();
    }

    public function isPublicRequest() {
        if(is_array($this->_explodedPath) && 1 < count($this->_explodedPath)) {
            return $this->_explodedPath[1] === "public";
        }
        return FALSE;
    }

}

?>
