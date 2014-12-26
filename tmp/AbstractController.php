<?php  /**   * @created 04.12.2012 12:47:10   * @author mregner   * @version $Id$ */
require_once('fblib/core/app/resources/ResourceManager.php');
require_once('fblib/core/globals/Session.php');
require_once('fblib/core/io/formular/Formular.php');
require_once('fblib/text/SmartyExt.php');
require_once('fblib/text/StringCrypt.php');
require_once('fblib/util/array/ReadOnlyArrayWrapper.php');
// This is needed by the Final Controllers.
require_once('fblib/cache/CacheClient.php');
require_once('ControllerInterface.php');
require_once('ControllerException.php');
require_once('coupling/ProvisionHandler.php');

/**
 * Basisklasse für alle Controller
 */
abstract class AbstractController implements ControllerInterface
{
    const DEFAULT_VIEW = "main";
    /**       * @var array */
    private $redirect = array();
    /**       * @var int */
    protected $created = 0;
    /**       * @var array */
    protected static $viewStack = array();
    /**       * @var array */
    protected static $renderingTemplates = array();
    /**       * @var string */
    protected $name = null;
    /**       * @var array */
    protected $data = array();
    /**       * @var AbstractModel[]       * */
    protected $models = array();
    /**       * @var array */
    protected $config = array();
    /**       * @var array */
    protected $metaData = array();
    /**       * @var array */
    protected $requirements = array();
    /**       * @var array */
    protected $provisions = array();
    /**       * @var ProvisionHandler */
    protected $provisionHandler = null;
    /**       * @var array */
    protected $bindings = array();
    /**       * @var DefinitionWrapper[] */
    protected $definitions = array();
    /**       * @var Formular[] */
    protected $formulars = array();
    /**       * @var Formular[] */
    protected $tables = array();
    /**       * @var MultipassFilterQuery[] */
    protected $multipassFilterQueries = array();

    /**
     * @param array $config
     * @param array $meta_data
     * @param array $bindings
     * @author mregner
     */
    final public function __construct(array &$config, array $meta_data = array(), array $bindings = array())
    {
        //Scope wird über das environment gesetzt.
        $config["scope"] = Environment::getScope();
        $this->config = new ReadOnlyArrayWrapper($config);
        $this->metaData = new ReadOnlyArrayWrapper($meta_data);
        $this->bindings = new ReadOnlyArrayWrapper($bindings);
        $this->setup();
    }

    /**
     * Diese Methode wird im Konstruktor aufgerufen.
     * Die Kindcontroller koennen diese Methode ueberschreiben und
     * sich ggf. initialisieren.
     *
     * @author mregner
     */
    protected function setup()
    {
    }

    /**
     * @return bool
     * @author mregner
     */
    public function clear()
    {
        $this->data = array();
        return true;
    }

    /**
     * @return string
     * @author mregner
     */
    public function getName()
    {
        if (!isset($this->name)) {
            if (isset($this->config['name'])) {
                $this->name = strtolower($this->config['name']);
            } else {
                $this->name = strtolower(get_class($this));
            }
        }
        return $this->name;
    }

    /**
     * @return bool
     * @author mregner
     */
    protected function isRequestController()
    {
        $name = $this->getName();
        if (Request::get('controller') === $name || Request::get('component') === $name) {
            return true;
        } else if (Request::has("action-{$name}")) {
            return true;
        }
        return false;
    }

    /**
     * @return string
     * @author mregner
     */
    public function getFile()
    {
        return $this->config['file'];
    }

    /**
     * @param string $key
     * @param mixed $value
     * @author mregner
     */
    public function assign($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @author mregner
     */
    public function assignOnce($key, $value)
    {
        !isset($this->data[$key]) && $this->assign($key, $value);
    }

    /**
     * @return ResourceManager
     * @author mregner
     */
    protected function getResourceManager()
    {
        return ResourceManager::getInstance();
    }

    /**
     * @param string $filename
     * @return string
     * @author mregner
     */
    public function getResource($filename)
    {
        return $this->getResourceManager()->getPath($filename, $this->getName());
    }

    /**
     * @param string $relative_path
     * @param bool $relative
     * @return string
     * @author mregner
     */
    public function getResourceUrl($relative_path, $relative = true)
    {
        $resourceUrl = $this->getResourceManager()->getUrl($relative_path, $this->getName(), $relative);
        if (!isset($resourceUrl)) {
            return "<!-- Resource {$relative_path} in template {$this->data['current_view']} is not available! -->";
        }
        return $resourceUrl;
    }

    /**
     * @param string $name
     * @param array $source_params
     * @return AbstractModel
     * @throws ControllerException
     * @author mregner
     */
    public function getModel($name, array $source_params = array())
    {
        if (!isset($this->models[$name])) {
            $this->models[$name] = $this->getResourceManager()->get("models/{$name}Model", $this->getName());
            if (isset($this->config["resource_alias"])) {
                try {
                    $this->models[$name]->setResourceAlias($this->config['resource_alias']);
                } catch (Exception $exception) {
                    throw new ControllerException("No Model {$name} defined.");
                }
            }
        }
        $model = $this->models[$name];
        // Parameter in der Source des Models ersetzen - macht natürlich nur bei Sourcen die SQL-Statements sind etwas
        $model->setSourceParams($source_params);
        return $model;
    }

    /**
     * Zur Verwendung innerhalb der generierten Wrapper. Die generieren für jedes Model eine
     * Methode.
     *
     * @param string $model_name
     * @param array $request
     * @return mixed
     * @author mregner
     */
    public function getModelData($model_name, array $request)
    {
        $model = $this->getModel($model_name);
        $fields = $filter = $sort = $groupFields = null;
        isset($request['$fields']) && ($fields = explode(",", $request['$fields']));
        isset($request['$filter']) && ($filter = FilterParser::parseString($request['$filter']));
        isset($request['$sort']) && ($sort = explode(",", $request['$sort']));
        isset($request['$fields']) && ($groupFields = explode(",", $request['$fields']));
        $model->prepare($fields, $filter, $sort, $groupFields);
        $limit = isset($request['$top']) ? $request['$top'] : 0;
        $offset = isset($request['$skip']) ? $request['$skip'] : 0;
        return $model->fetchAll($limit, $offset);
    }

    /**
     * @param string $name
     * @return DefinitionWrapper
     * @throws ControllerException
     */
    public function getDefinition($name)
    {
        if (!isset($this->definitions[$name])) {
            $this->definitions[$name] = ResourceManager::getInstance()->get("definitions/{$name}", $this->getName());
        }
        return $this->definitions[$name];
    }

    /**
     * @param string $name
     * @param string $namespace
     * @return Formular
     * @throws ResourceManagerException
     * @author mregner
     */
    public function getFormular($name, $namespace = null)
    {
        $key = isset($namespace) ? "{$namespace}_{$name}" : $name;
        if (!isset($this->formulars[$key])) {
            $this->formulars[$key] = $this->getResourceManager()->get("forms/{$name}", $this->getName());
            $this->formulars[$key]->setNamespace($namespace);
            $this->initFormular($this->formulars[$key]);
        }
        return $this->formulars[$key];
    }

    /**
     * @param string $name
     * @return Table
     * @throws ResourceManagerException
     * @author mregner
     */
    public function getTable($name)
    {
        if (!isset($this->tables[$name])) {
            $this->tables[$name] = $this->getResourceManager()->get("tables/{$name}", $this->getName());
        }
        return $this->tables[$name];
    }

    /**
     * @param string $name
     * @return MultipassFilterQuery
     * @throws ResourceManagerException
     * @author mregner
     */
    public function getMultipassFilterQuery($name)
    {
        if (!isset($this->multipassFilterQueries[$name])) {
            $this->multipassFilterQueries[$name] = $this->getResourceManager()->get("multipass/{$name}", $this->getName());
        }
        return $this->multipassFilterQueries[$name];
    }

    /**
     * @param string $name
     * @return bool
     * @author mregner
     */
    public function hasMethod($name)
    {
        return isset($this->metaData['methods'][strtolower($name)]);
    }

    /**
     * @param string $name
     * @return array
     * @author mregner
     */
    public function getMethodMeta($name)
    {
        return $this->metaData['methods'][strtolower($name)];
    }

    /**
     * @param string $method
     * @return null
     * @author mregner
     */
    public function getRemoteCallBinding($method)
    {
        if (isset($this->bindings['remotecall_bindings'][$method])) {
            return $this->bindings['remotecall_bindings'][$method];
        }
        return null;
    }

    /**
     * @param array $params
     * @param bool $with_amps
     * @return string
     * @author mregner
     */
    protected function buildQuery(array $params, $with_amps = false)
    {
        // We keep all parameters from the query string
        if (isset($params['keep_query'])) {
            $queryParams = Request::getQueryParams();
            foreach ($queryParams as $key => $value) {
                if (preg_match("~^(action-|component|controller|view|data)~", $key)) {
                    unset($queryParams[$key]);
                }
            }
            $params = array_merge($queryParams, $params);
            unset($params['keep_query']);
        }
        // Check and set controller/component params
        if (!isset($params['controller']) && !isset($params['component'])) {
            $queryParams = Request::getQueryParams();
            isset($queryParams['controller']) && ($params['controller'] = $queryParams['controller']);
            isset($queryParams['component']) && ($params['component'] = $queryParams['component']);
        }
        if (isset($params["action"])) {
            if ($this->hasMethod($params["action"])) {
                $params["action-{$this->getName()}"] = $params["action"];
            }
            unset($params["action"]);
        }
        return http_build_query($params, null, ($with_amps) ? '&amp;' : '&');
    }

    /**
     * @param array $params
     * @param bool $with_amps
     * @param bool $relative
     * @param bool $include_script
     * @return string
     * @author mregner
     */
    public function buildUrl(array $params, $with_amps = false, $relative = true, $include_script = true)
    {
        $queryString = $this->buildQuery($params, $with_amps);
        $baseUrl = '';
        if ($relative === false) {
            $scheme = Server::isHttps() ? 'https' : 'http';
            $host = Server::get('HTTP_HOST');
            $baseUrl = "{$scheme}://{$host}";
        }
        $proxyPath = Environment::getProxyPath();
        if (!empty($proxyPath)) {
            $baseUrl .= $proxyPath;
        }
        $scope = Environment::getScope();
        if (!empty($scope)) {
            $baseUrl .= "/{$scope}";
        }
        $script = $include_script ? Server::getScriptName() : '/';
        if (Config::get('SCRAMBLE_URLS') === true) {
            return "{$baseUrl}{$script}?scramble=" . StringCrypt::scramble($queryString);
        } else {
            return "{$baseUrl}{$script}?{$queryString}";
        }
    }

    /**
     * @param string $name
     * @return string
     * @author mregner
     */
    protected function getTemplate($name)
    {
        return $this->getResource("{$name}.tpl");
    }

    /**
     * Ermittelt anhand des Template Namens den Templatepfad und laesst es
     * rendern.
     *
     * @param string $template_name
     * @return string
     * @throws ControllerException
     * @throws Exception
     * @author mregner
     */
    public function render($template_name)
    {
        $methodMeta = $this->getMethodMeta($template_name);
        if (isset($methodMeta['template'])) {
            $basename = $methodMeta['template'];
        } else {
            $basename = $template_name;
        }
        $templateFile = $this->getTemplate($basename);
        if (!isset($templateFile)) {
            throw new ControllerException("Template {$template_name} not found for controller " . $this->getName() . "!");
        } else if (isset(self::$renderingTemplates[$templateFile])) {
            return "<!-- Already rendered view {$template_name}! -->";
        } // Um Endlosschleifenzu vermeiden merken wir uns das Template.
        self::$renderingTemplates[$templateFile] = true;
        $content = '';
        // Aliase unter denen $this->data verfügbar gemacht wird mergen. Es wird immer unter
        // dem Namen das Controllers, "current" und den Namen aller Controller, von denen
        // geerbt wird, verfügbar gemacht
        $registerDataAliases = array(
            "current",
            $this->getName(),
        );
        $registerDataAliases = array_unique(array_merge($registerDataAliases, $this->metaData['inheritance']));
        $renderException = null;
        $isTopView = empty(self::$viewStack);
        try {
            SmartyExt::getInstance()->clearAssign('is_top_view');
            if ($isTopView) {
                SmartyExt::getInstance()->assign('is_top_view', true);
            }
            $this->pushView($template_name);
            $this->data['controller_config'] = $this->config;
            $this->data['current_view'] = $template_name;
            $this->data['top_view'] = $this->getTopView();
            // $this->data-Aliase bekannt machen
            foreach ($registerDataAliases as $alias) {
                SmartyExt::getInstance()->assignByRef(strtolower($alias), $this->data);
            }
            // SessionID im Template verfügbar machen
            SmartyExt::getInstance()->assign("session_id", Session::getID());
            // Packet verfügbar machen wenn vorhanden.
            if (Config::has('packet')) {
                SmartyExt::getInstance()->assign("packet", Config::get('packet'));
            }
            // Scope verfügbar machen wenn vorhanden.
            if (Environment::getScope() !== '') {
                SmartyExt::getInstance()->assign("scope", Environment::getScope());
            }
            $content = SmartyExt::getInstance()->fetch($templateFile, null, $this->getName());
        } catch (Exception $exception) {
            $renderException = $exception;
        }
        $this->popView();
        unset(self::$renderingTemplates[$templateFile]);
        if ($renderException != null) {
            throw $renderException;
        }
        // Template Comments schicken den alten IE in den Quirksmode...
        //if (!$isTopView && Config::get('DEVMODE') === true) {
        //    $content = "<!-- Start Template {$templateFile} -->\n{$content}\n<!-- End Template {$templateFile} -->";
        //}
        return $content;
    }

    /**
     * @param string $view
     * @author mregner
     */
    protected function pushView($view)
    {
        self::$viewStack[] = $view;
    }

    /**
     * @return string
     * @author mregner
     */
    protected function popView()
    {
        //Can't pop from static array directly.
        $viewStack = & self::$viewStack;
        return array_pop($viewStack);
    }

    /**
     * @return string
     * @author mregner
     */
    protected function getTopView()
    {
        if (!empty(self::$viewStack)) {
            return self::$viewStack[0];
        } else if (Request::has('view')) {
            return Request::get('view');
        }
        return null;
    }

    /**
     * Diese Methode wird immer aufgerufen, wenn ein Formular geholt wird und
     * kann überschrieben werden um das Formular mit Daten zu initialisieren.
     *
     * @param Formular $formular
     */
    public function initFormular(Formular $formular)
    {
    }

    /**
     * @data
     * @noacl
     *
     * @param string $name
     * @param integer $index
     * @return array
     */
    public function getFormularFields($name, $index = 0)
    {
        return $this->getFormular($name)->getFields($index);
    }

    /**
     * @param string $method_name
     * @param array $params
     * @return string
     * @author mregner
     */
    protected function getMethodCallHash($method_name, array $params)
    {
        $source = $this->getName();
        $source = "{$source}->{$method_name}";
        $source = "{$source}[{$this->created}]";
        isset($params) && ($source .= "-" . serialize($params));
        $methodMeta = $this->getMethodMeta($method_name);
        if (isset($methodMeta['sessionkeys'])) {
            $sessionkeys = explode(",", $methodMeta['sessionkeys']);
            foreach ($sessionkeys as $key) {
                $sessionValue = $this->getSessionValue($key);
                isset($sessionValue) && ($source .= "-" . serialize($sessionValue));
            }
        }
        return md5($source);
    }

    /**
     * @return bool
     * @author mregner
     */
    public function hasRedirect()
    {
        return !empty($this->redirect);
    }

    /**
     * @param array $data *       * @author mregner */
    protected function setRedirect(array $data)
    {
        if (!isset($data['component']) && !isset($data['controller'])) {
            $data['component'] = $this->getName();
        }
        $this->redirect = $data;
    }

    /**       * @return array       * @author mregner */
    public function getRedirect()
    {
        return $this->redirect;
    }

    /**       * @return string       * @author mregner */
    protected function getBucketScope()
    {
        return Environment::getScope();
    }

    /**       * @return array       * @author mregner */
    public function getRequirements()
    {
        if (isset($this->requirements['#configuration'])) {
            foreach ($this->config['requirements'] as $requirement) {
                $this->requirements[$requirement] = 'strong';
            }
            unset($this->requirements['#configuration']);
        }
        return $this->requirements;
    }

    /**       * @return array       * @author mregner */
    public function getProvisions()
    {
        return $this->provisions;
    }

    /**       * @param string $key * @return bool       * @author mregner */
    public function requires($key)
    {
        $requirements = $this->getRequirements();
        return isset($requirements[$key]);
    }

    /**       * @param string $key * @param string $namespace       * @return bool       * @author mregner */
    public function provides($key, $namespace = null)
    {
        $provisions = $this->getProvisions();
        if (isset($provisions[$key])) {
            return true;
        } else if (isset($namespace)) {
            return isset($provisions["{$namespace}/{$key}"]);
        }
        return false;
    }

    /**       * @return ProvisionHandler       * @author mregner */
    protected function getProvisionHandler()
    {
        if (!isset($this->provisionHandler)) {
            $this->provisionHandler = new ProvisionHandler($this->getBucketScope());
        }
        return $this->provisionHandler;
    }

    /**       * @param string $key * @param mixed $data       *       * @throws ControllerException       *       * @author mregner */
    public function provide($key, $data)
    {
        if ($this->provides($key)) {
            $this->getProvisionHandler()->set($key, $data, $this->getName());
        } else if ($this->provides($key, $this->getName())) {
            $this->getProvisionHandler()->set($key, $data, $this->getName(), true);
        } else {
            throw new ControllerException("Invalid call of provide. Controller {$this->getName()} does not provide {$key}!");
        }
    }

    /**       * @param string $key * @return array|mixed       * @throws ControllerException       * @author mregner */
    protected final function getRequirement($key)
    {
        $requirements = $this->getRequirements();
        if (isset($requirements[$key]) || $this->provides($key)) {
            $result = $this->getProvisionHandler()->get($key);
            if ($result === null) {
                $result = $this->getDefaultProvision($key);
            }
            return $result;
        } else {
            throw new ControllerException("Invalid call of getRequirement. Controller {$this->getName()} neither provides nor requires {$key}!");
        }
    }

    /**       * @param string $key * @throws ControllerException       * @return array */
    public function getDefaultProvision($key)
    {
        if (isset($key)) {
            return null;
        }
        throw new ControllerException("Invalid provision key 'null' requested.");
    }

    /**       * @param string $requirement *       * @author mregner */
    protected function invalidateRequirementDependencies($requirement)
    {
        if (isset($this->bindings['requirement_dependencies'][$requirement])) {
            foreach ($this->bindings['requirement_dependencies'][$requirement] as $dependentRequirement) {
                $this->unsetRequirement($dependentRequirement);
            }
        }
    }

    /**       * @param string $key * @param mixed $data       * @throws ControllerException       *       * @deprecated       *       * @author mregner */
    protected final function provideRequirement($key, $data)
    {
        $this->provide($key, $data);
    }

    /**       * @param string $key * @return bool       * @author mregner */
    public function checkRequirement($key)
    {
        return $this->getProvisionHandler()->has($key) || ($this->getDefaultProvision($key) !== null);
    }

    /**       * @deprecated use getDefaultProvision instead.       *       * @param string $key       * @return array */
    public function getRequirementDefaultData($key)
    {
        return $this->getDefaultProvision($key);
    }

    /**       * @param string $key * @author mregner */
    protected function unsetRequirement($key)
    {
        $this->getProvisionHandler()->delete($key);
    }

    /**       * @return string|null       * @author mregner */
    public function getNextRequirement()
    {
        $requirements = $this->getRequirements();
        foreach ($requirements as $requirement => $predicate) {
            if (!$this->checkRequirement($requirement) && $predicate !== ProvisionHandler::PREDICATE_WEAK) {
                return $requirement;
            }
        }
        return null;
    }

    /**       * @param string $requirement * @return array       * @author mregner */
    public function getRequirementProvider($requirement)
    {
        if (isset($this->bindings['requirement_bindings'][$requirement])) {
            return $this->bindings['requirement_bindings'][$requirement];
        }
        return array();
    }

    /**       * @return array       * @author mregner */
    public function getAllRequirementData()
    {
        $requirementData = array();
        $requirements = $this->getRequirements();
        foreach ($requirements as $requirement => $value) {
            $requirementData[$requirement] = $this->getRequirement($requirement);
        }
        return $requirementData;
    }

    /**
     * Provided Daten für ein Requirement ganz explizit ohne zu prüfen, das der Controller dieses auch als
     * @provides-Tag
     * definiert hat. Das ist die absolute Ausnahme und wird (im Moment) nur für das Archiv benutzt.
     *
     * @param string $key
     * @param mixed $data
     */
    protected function provideUncheckedRequirement($key, $data)
    {
        if ($this instanceof UncheckedRequirementProvider) {
            /** @var AbstractController $this */
            $this->getProvisionHandler()->set($key, $data, $this->getName());
        }
    }

    /**       * @param string $key * @return mixed       * @author mregner */
    protected function getUncheckedRequirement($key)
    {
        if ($this instanceof UncheckedRequirementProvider) {
            /** @var AbstractController $this */
            $result = $this->getProvisionHandler()->get($key);
            if ($result === null) {
                $provider = $this->getRequirementProvider($key);
                if (isset($provider['component'])) {
                    return ControllerRegistry::get($provider['component'])->getDefaultProvision($key);
                } else {
                    $result = $this->getRequirementDefaultData($key);
                }
            }
            return $result;
        }
        return null;
    }        /**       * The session stuff.       */
    /**       *       */
    protected function invalidateSessionValues()
    {
        Session::deleteScopedStorage(Environment::getScope());
    }

    /**       * @param string $key * @param mixed $value       * @author mregner */
    protected function setSessionValue($key, $value)
    {
        Session::getScopedStorage(Environment::getScope())->offsetSet("{$this->getName()}.{$key}", $value);
    }

    /**       * @param string $key * @return mixed       * @author mregner */
    protected function getSessionValue($key)
    {
        return Session::getScopedStorage(Environment::getScope())->offsetGet("{$this->getName()}.{$key}");
    }

    /**       * @param string $key * @author mregner */
    protected function removeSessionValue($key)
    {
        Session::getScopedStorage(Environment::getScope())->offsetUnset("{$this->getName()}.{$key}");
    }

    /**       * @param string $key * @return bool */
    protected function hasSessionValue($key)
    {
        return Session::getScopedStorage(Environment::getScope())->offsetExists("{$this->getName()}.{$key}");
    }
}