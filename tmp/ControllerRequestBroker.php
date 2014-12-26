<?php
/**
 * Created by PhpStorm.
 * User: mregner
 * Date: 26.02.14
 * Time: 17:36
 */
require_once('ControllerRegistry.php');

class ControllerRequestBroker
{
    const ACTION_EXECUTED_HEADER = 'X-Action';

    /**
     * @param RequestWrapper $request
     * @param ResponseWrapper $response
     * @author mregner
     */
    public static function handleRequest(RequestWrapper $request, ResponseWrapper $response)
    {
        self::handleActions($request, $response);
        // Dieses meint den Requestparameter data und nicht die array variable
        if (!$response->hasRewrite() && isset($request['data'])) {
            self::handleData($request, $response);
        } else if (!$response->hasRewrite()) {
            self::handleView($request, $response);
        }
        if (!$response->hasRewrite()) {
            self::handleError($request, $response);
        }
    }

    /**
     * @param RequestWrapper $request
     * @param ResponseWrapper $response
     * @return bool
     * @throws
     * @author mregner
     */
    protected static function handleActions(RequestWrapper $request, ResponseWrapper $response)
    {
        try {
            foreach ($request as $key => $value) {
                if (preg_match("~action-([a-z]+)~", $key, $matches)) {
                    $controllerName = $matches[1];
                    $method = $value;
                    if (ControllerRegistry::get($controllerName)->isActionMethod($method)) {
                        ControllerRegistry::get($controllerName)->$method($request, $response);
                        //Wir markieren im Response das eine Action ausgefuehrt wurde.
                        $response->setHeader(self::ACTION_EXECUTED_HEADER, true);
                    } else {
                        throw new ControllerException("Method {$controllerName}->{$method} does not exist or ist not of type @action!");
                    }
                }
            }
            return true;
        } catch (Exception $exception) {
            $response->setError('action', $exception);
        }
        return false;
    }

    /**
     * @param RequestWrapper $request
     * @param ResponseWrapper $response
     * @return bool
     * @throws
     *
     * @author mregner
     */
    protected static function handleData(RequestWrapper $request, ResponseWrapper $response)
    {
        try {
            if (isset($request['controller'])) {
                $controllerName = $request['controller'];
            } else if (isset($request['component'])) {
                $controllerName = $request['component'];
            } else {
                throw new Exception("No controller specified for data method {$request['data']}.");
            }
            $dataMethods = is_array($request['data']) ? $request['data'] : array($request['data']);
            foreach ($dataMethods as $dataMethod) {
                if (ControllerRegistry::get($controllerName)->isDataMethod($dataMethod)) {
                    ControllerRegistry::get($controllerName)->$dataMethod($request, $response);
                } else {
                    throw new ControllerException("Method {$controllerName}->{$dataMethod} does not exist or ist not of type @data.");
                }
            }
            return true;
        } catch (Exception $exception) {
            $response->setError('data', $exception);
        }
        return false;
    }

    /**
     * @param RequestWrapper $request
     * @param ResponseWrapper $response
     * @return bool
     * @author mregner
     * @throws
     */
    protected static function handleView(RequestWrapper $request, ResponseWrapper $response)
    {
        try {
            if (isset($request['controller']) && !empty($request['controller'])) {
                $controllerName = $request['controller'];
                $viewName = !isset($request['component']) && !empty($request['view']) ? $request['view'] : 'main';
            } else {
                $controllerName = 'default';
                $viewName = 'main';
            }
            if (!ControllerRegistry::exists($controllerName)) {
                throw new ControllerException("Controller with alias {$controllerName} does not exist");
            }
            if ($viewName != 'noview' && $viewName != 'none') {
                if (ControllerRegistry::get($controllerName)->isViewMethod($viewName)) {
                    ControllerRegistry::get($controllerName)->$viewName($request, $response);
                } else {
                    throw new ControllerException("Method {$controllerName}->{$viewName} does not exist or ist not of type @view.");
                }
            }
            return true;
        } catch (Exception $exception) {
            $response->setError('view', $exception);
        }
        return false;
    }

    /**
     * @param RequestWrapper $request
     * @param ResponseWrapper $response
     * @return boolean
     * @throws Exception
     * @author mregner
     */
    protected static function handleError(RequestWrapper $request, ResponseWrapper $response)
    {
        if ($response->hasError()) {
            $data = array('errors' => $response->getErrors(),);
            if (isset($request['data'])) {
                foreach ($data["errors"] as $type => $exceptions) {
                    foreach ($exceptions as $exception) {
                        Logger::info("Exception for request type {$type}!");
                        Logger::error($exception);
                    }
                }
                $response->addOutput('data', 'error', $data);
            } else if (ControllerRegistry::exists('error')) {
                $newRequest = new RequestWrapper($data);
                /** @var Error $error */
                $error = ControllerRegistry::get('error');
                // Wenn der ErrorController ein spezifisches Template für die Exception hat dann zeigen wir das an,
                // ansonten gehts hoch zum ErrorHandler, der dann je nach DEVMODE-Config das entsprechende anzeigt
                if (isset($error) && $error->hasErrorTemplate($newRequest, $response)) {
                    $error->setErrors($newRequest, $response);
                    $error->error_view($newRequest, $response);
                } else {
                    /**
                     * @var Exception $error
                     */
                    foreach($data['errors'] as $exceptions) {
                        $exception = array_shift($exceptions);
                        throw $exception;
                    }
                }
            }
        }
        return true;
    }
}
