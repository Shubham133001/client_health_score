<?php

namespace WHMCS\Module\Addon\ClientHealthScore\Client;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

class ClientDispatcher {

    /**
     * Dispatch request.
     *
     * @param string $action
     * @param array $parameters
     *
     * @return array
     */
    public function dispatch($action, $parameters)
    {
        if (!$action) {
            $action = 'index';
        }

        $controller = new Controller();

        if (is_callable(array($controller, $action))) {
            return $controller->$action($parameters);
        }
    }
}
