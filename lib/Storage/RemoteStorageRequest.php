<?php

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . "Http" . DIRECTORY_SEPARATOR . "HttpRequest.php";

class RemoteStorageRequest extends HttpRequest {

    public function getCategory() {
        $collection = parent::getCollection(TRUE);
        if($this->isPublicRequest()) {
            return (FALSE === $collection || count($collection) < 3) ? NULL : $collection[2];
        } else {
            return (FALSE === $collection || count($collection) < 2) ? NULL : $collection[1];
        }
    }

    public function getResourceOwner() {
        $collection = parent::getCollection(TRUE);
        return (FALSE === $collection || count($collection) < 1) ? NULL : $collection[0];
    }

    public function isPublicRequest() {
        $collection = parent::getCollection(TRUE);
        return (FALSE === $collection || count($collection) < 2) ? FALSE : "public" === $collection[1];
    }

    public function isDirectoryRequest() {
        $pathInfo = parent::getPathInfo();
        return strrpos($pathInfo, "/") === strlen($pathInfo) - 1;
    }

}

?>
