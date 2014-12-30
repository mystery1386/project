<?php require_once("fblib/core/init/BootstrapInterface.php");
require_once("fblib/core/globals/Environment.php");
require_once("fblib/util/system/ReflectionUtil.php");
require_once("fblib/util/fs/FileSystemUtil.php");
require_once("fblib/util/array/ArrayUtil.php");

class ResourceManagerException extends Exception
{
}

/**
 * Der ResourceManager lokalisiert und verwalten alle Resourcen, wobei
 * auch Controller Resourcen sind, die dann auch noch selber wieder
 * Resourcen haben.
 *
 * Resourcen werden grundsätzlich über die Dateiendung einem Typ zugeordnet,
 * die Ausnahme davon bilden hier Controller und Models, die über ihre Position
 * in der Verzeichnisstruktur und ihren Dateinamen erkannt werden, die einer
 * definierten Konvention folgen.
 */
class ResourceManager implements BootstrapInterface
{
    const RESOURCE_WORKER_PROCESSOR = 'processor';
    const RESOURCE_WORKER_CREATOR = 'creator';
    /**      * Resourcentypen      */
    const RESOURCE_TYPE_TEMPLATE = "tpl";
    const RESOURCE_TYPE_JAVASCRIPT = "js";
    const RESOURCE_TYPE_CSS = "css";
    const RESOURCE_TYPE_FORMULAR = "formular";
    const RESOURCE_TYPE_IMAGE = "image";
    const RESOURCE_TYPE_MODEL = "model";
    const RESOURCE_TYPE_HOOK = "hook";
    const RESOURCE_TYPE_FLASH = "swf";
    const RESOURCE_TYPE_CONTROLLER = "controller";
    const RESOURCE_TYPE_XML = "xml";
    const RESOURCE_TYPE_KEY2XPATH = "key2xpath";
    const RESOURCE_TYPE_XPATH2KEY = "xpath2key";
    const RESOURCE_TYPE_MULTIPASS = "multipass";
    const RESOURCE_TYPE_FONT = "font";
    const RESOURCE_TYPE_DEFINITION = "definition";
    const RESOURCE_TYPE_LESS = "less";
    const RESOURCE_TYPE_HTML = "html";
    const RESOURCE_TYPE_FUNCTION = "function";
    const RESOURCE_TYPE_TABLE = "table";
    const RESOURCE_TYPE_SCHEMA = 'schema';
    /**
     * Resourcealiase
     * Map resources found in the filesystem to different creators.
     * E.g. forms/casedata -> schema/casedata
     */
    protected static $ALIASES_MAP = array(self::RESOURCE_TYPE_FORMULAR => array("schema"),);
    /**
     * Mapping Extension => Typ
     *
     * @var array
     */
    protected static $EXTENSION_TYPE_MAP = array(
        "jpeg" => self::RESOURCE_TYPE_IMAGE,
        "jpg" => self::RESOURCE_TYPE_IMAGE,
        "png" => self::RESOURCE_TYPE_IMAGE,
        "ico" => self::RESOURCE_TYPE_IMAGE,
        "gif" => self::RESOURCE_TYPE_IMAGE,
        "svg" => self::RESOURCE_TYPE_IMAGE,
        "js" => self::RESOURCE_TYPE_JAVASCRIPT,
        "css" => self::RESOURCE_TYPE_CSS,
        "tpl" => self::RESOURCE_TYPE_TEMPLATE,
        "frm" => self::RESOURCE_TYPE_FORMULAR,
        "swf" => self::RESOURCE_TYPE_FLASH,
        "less" => self::RESOURCE_TYPE_LESS,
        "ttf" => self::RESOURCE_TYPE_FONT,
        "eot" => self::RESOURCE_TYPE_FONT,
        "woff" => self::RESOURCE_TYPE_FONT,
        "html" => self::RESOURCE_TYPE_HTML,
        "xml" => self::RESOURCE_TYPE_XML,
        'tbl' => self::RESOURCE_TYPE_TABLE
    );
    /**
     * Mapping Typ => Verzeichnis
     *
     * @var array
     */
    protected static $TYPE_DIR_MAP = array(
        "image" => "images",
        "jpeg" => "images",
        "jpg" => "images",
        "png" => "images",
        "gif" => "images",
        "ico" => "images",
        "svg" => "images",
        "js" => "js",
        "css" => "css",
        "tpl" => "templates",
        "frm" => "forms",
        "swf" => "flash",
        "less" => "css",
        "font" => "fonts",
        "ttf" => "fonts",
        "eot" => "fonts",
        "woff" => "fonts",
        "tbl" => "tables"
    );
    /**
     * Typen, die nach /assets/ geschoben werden
     *
     * @var array
     */
    protected static $SYNC_TYPES = array(
        self::RESOURCE_TYPE_IMAGE => true,
        self::RESOURCE_TYPE_JAVASCRIPT => true,
        self::RESOURCE_TYPE_CSS => true,
        self::RESOURCE_TYPE_LESS => true,
        self::RESOURCE_TYPE_FONT => true,
        self::RESOURCE_TYPE_FLASH => true,
        self::RESOURCE_TYPE_HTML => true,
    );
    /**
     * Besondere Verzeichnisse, wo die Dateiendung nicht entscheident ist für den Typ
     *
     * @var array
     */
    protected static $CONTROLLER_SPECIAL_RESOURCE_DIRS = array(
        "models" => self::RESOURCE_TYPE_MODEL,
        "hooks" => self::RESOURCE_TYPE_HOOK,
        "xmltemplates" => self::RESOURCE_TYPE_XML,
        "key2xpath" => self::RESOURCE_TYPE_KEY2XPATH,
        "xpath2key" => self::RESOURCE_TYPE_XPATH2KEY,
        "multipass" => self::RESOURCE_TYPE_MULTIPASS,
        "definitions" => self::RESOURCE_TYPE_DEFINITION,
        "controllers" => self::RESOURCE_TYPE_CONTROLLER,
        "functions" => self::RESOURCE_TYPE_FUNCTION,
        "forms" => self::RESOURCE_TYPE_FORMULAR,
        "tables" => self::RESOURCE_TYPE_TABLE,
        "schemata" => self::RESOURCE_TYPE_SCHEMA
    );
    /**
     * ResourceTypen die gemerged werden können
     *
     * @var array
     */
    protected static $MERGE_TYPES = array(
        self::RESOURCE_TYPE_JAVASCRIPT => true,
        self::RESOURCE_TYPE_CSS => true
    );
    /**
     * @var ResourceManager
     */
    protected static $instance = null;
    /**
     * @var array
     */
    protected $resourceCatalog = null;
    /**
     * Config Keys die in jedem Fall benoetigt werden.
     * @var array
     */
    private static $REQUIRED_CONFIG_KEYS = array();
    /**
     * @var array
     */
    protected $config = array();
    /**
     * @var array
     */
    protected $resourceWorkerCatalog = array();
    /**
     * @var array
     */
    protected $resourceWorkers = array();
    /**
     * @var array
     */
    protected $specialTypes = null;
    /**
     * @var array
     */
    protected $preferredResources = null;

    /**
     * @param array $config
     */
    protected function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @param array $config
     * @throws ResourceManagerException
     * @author mregner
     */
    public static function initialize(array $config)
    {
        if (!isset(self::$instance)) {
            $checkConfigArray = array_intersect(self::$REQUIRED_CONFIG_KEYS, array_keys($config));
            if (count($checkConfigArray) === count(self::$REQUIRED_CONFIG_KEYS)) {
                self::$instance = new ResourceManager($config);
                self::$instance->generateResourceCatalogue();
            } else {
                throw new ResourceManagerException("Incomplete or invalid ResourceManager configuration!");
            }
        }
    }

    /**
     * @return ResourceManager
     * @author mregner
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new ResourceManager(array());
        }
        return self::$instance;
    }

    /**
     * @return array
     * @author mregner
     */
    public function getResourceCatalogue()
    {
        if (!isset($this->resourceCatalog)) {
            return $this->generateResourceCatalogue();
        }
        return $this->resourceCatalog;
    }

    /**
     * @return string
     * @author mregner
     */
    public function getDefaultScope()
    {
        return !empty($this->config["default_scope"]) ? $this->config["default_scope"] : "";
    }

    /**
     * Hierarchie: TempScope > SessionScope > ConfigScope > EnvironmentScope > DefaultScope
     *
     * @return array
     */
    public function getResourceScopes()
    {
        $resourceScopes = array();
        $envScope = Environment::getScope();
        if (empty($envScope)) {
            !empty($this->config["default_scope"]) && ($resourceScopes[] = $this->config["default_scope"]);
        } else {
            $resourceScopes[] = $envScope;
        }
        // Für die aktuelle Session am ResourceManager konfigurierte Scopes hinzufügen
        $sessionScopes = $this->getAppendedSessionResourceScopes();
        if (!empty($sessionScopes)) {
            foreach ($sessionScopes as $scope) {
                if (
                    array_search($scope, $resourceScopes) === false
                ) {
                    $resourceScopes[] = $scope;
                }
            }
        }

        return $resourceScopes;
    }

    /**
     * Löscht alle für diese Session hinzugefügten Scopes
     */
    public function resetSessionResourceScopes()
    {
        Session::delete("ResourceManager.appendedScopes");
    }

    /**
     * Scope für die aktuelle Session hinzufügen
     *
     * @param string $scope
     */
    public function appendSessionResourceScope($scope)
    {
        if (!empty($scope)) {
            $scopes = $this->getAppendedSessionResourceScopes();
            isset($scopes) || $scopes = array();
            $scopes[] = $scope;
            Session::set("ResourceManager.appendedScopes", $scopes);
        }
    }

    /**
     * Für die aktuelle Session am ResourceManager konfigurierte Scopes
     *
     * @return array
     */
    protected function getAppendedSessionResourceScopes()
    {
        if (Session::has("ResourceManager.appendedScopes")) {
            return Session::get("ResourceManager.appendedScopes");
        }
        return null;
    }

    /**
     * @return string
     * @author mregner
     */
    protected function getPacket()
    {
        if (Config::has('packet')) {
            return Config::get('packet');
        }
        return '';
    }

    /**
     * @return array
     * @author mregner
     */
    public function generateResourceCatalogue()
    {
        $rebuild = Config::get('REBUILD_RESOURCE_CATALOGUE');
        $resourceCatalogFile = Environment::getTempPath('meta') . "resource_catalogue.php";
        if (!file_exists($resourceCatalogFile) || $rebuild === true) {
            $this->resourceCatalog = $this->getAllControllerResources();
            $this->resourceCatalog = array_merge($this->resourceCatalog, $this->getGlobalResources());
            $this->resourceCatalog['_packets'] = $this->getPacketResources();
            // Katalog speichern so das wir den nicht nochmal holen muessen.
            $content = "<?php\n";
            $content .= "//GENERATED BY " . __FILE__ . "\n";
            $content .= "\$_RESOURCE_CATALOGUE_DATA = " . var_export($this->resourceCatalog, true) . ";\n";
            file_put_contents($resourceCatalogFile, $content);
        } else {
            $_RESOURCE_CATALOGUE_DATA = array();
            include($resourceCatalogFile);
            $this->resourceCatalog = $_RESOURCE_CATALOGUE_DATA;
        }
        return $this->resourceCatalog;
    }

    /**
     * Gibt die Infos zu alle vorhandenen Resourcen-Worker zurück
     * @param string $category
     * @return array
     */
    private function getResourceWorkerCatalog($category)
    {
        if (!isset($this->resourceWorkerCatalog[$category])) {
            $resourceWorkersFile = Environment::getTempFile($category, $category, '.php');
            if (!file_exists($resourceWorkersFile)) {
                $suffix = ucfirst($category);
                $workers = FileSystemUtil::scan(__DIR__ . "/{$category}", "{$suffix}\.php$", FileSystemUtil::TYPE_FILE);
                $this->resourceWorkerCatalog[$category] = array();
                foreach ($workers as $worker) {
                    if (preg_match("~^([a-z0-9]+){$suffix}\.php$~i", basename($worker), $matches)) {
                        $type = strtolower($matches[1]);
                        $this->resourceWorkerCatalog[$category][$type] = array("class" => "{$matches[1]}{$suffix}", "file" => $worker,);
                    }
                }
                $content = "<?php //Generated by ResourceManager\n";
                $content .= "return " . var_export($this->resourceWorkerCatalog[$category], true) . ";";
                file_put_contents($resourceWorkersFile, $content);
            } else {
                $this->resourceWorkerCatalog[$category] = require($resourceWorkersFile);
            }
        }
        return $this->resourceWorkerCatalog[$category];
    }

    /**
     * Gibt die Instanz eines
     * Workers zurück .
     *
     * @param string $category
     * @param string $type
     *
     * @return mixed
     */
    protected function getWorker($category, $type)
    {
        if (!isset($this->resourceWorkers[$category][$type])) {
            $catalog = $this->getResourceWorkerCatalog($category);
            if (isset($catalog[$type])) {
                require_once($catalog[$type]['file']);
                $class = $catalog[$type]['class'];
                $this->resourceWorkers[$category][$type] = new $class();
            } else {
                return null;
            }
        }
        return $this->resourceWorkers[$category][$type];
    }

    /**
     * @param $type
     * @return AbstractResourceCreator
     *
     * @author mregner
     */
    protected function getCreator($type)
    {
        return $this->getWorker(self::RESOURCE_WORKER_CREATOR, $type);
    }

    /**
     * @param string $name
     * @param string $context
     * @param string $type
     * @param array $params
     *
     * @return mixed
     *
     * @throws ResourceManagerException
     * @author mregner
     */
    public function get($name, $context = 'global', $type = null, array $params = null)
    {
        $resourceFile = $this->getPath($name, $context, $type);
        if (file_exists($resourceFile)) {
            isset($type) || ($type = $this->getType($name));
            /** @var AbstractResourceCreator $creator */
            $creator = $this->getCreator($type);
            if (isset($creator)) {
                return $creator->create($resourceFile, $context, $params);
            } else {
                throw new ResourceManagerException("No creator found for type {$type}.");
            }
        } else {
            throw new ResourceManagerException("Resource {$name} in context {$context} not available.");
        }
    }

    /**
     * @param string $name
     * @param string $context
     * @param string $type
     * @return bool
     * @author mregner
     */
    public function has($name, $context = 'global', $type = null)
    {
        $resourceFile = $this->getPath($name, $context, $type);
        return file_exists(($resourceFile));
    }

    /**
     * @param string $dir resource location that takes precedence
     * during $execute
     * @param callable $execute function in which $dir has precedence
     * as resource location
     * @return mixed the return value of $execute
     * @author tregner
     */
    public function with($dir, $execute)
    {
        $resourceCatalogue = Environment::getTempPath('meta/with_' . md5($dir)) . "resource_map.fblib";
        if (file_exists($resourceCatalogue)) {
            include($resourceCatalogue);
        } else {
            $this->preferredResources = $this->getContextResources($dir);
            file_put_contents($resourceCatalogue,
                "<?php\n" . '$this->preferredResources = unserialize(\'' . serialize($this->preferredResources) . '\');'
            );
        }
        $result = $execute();
        $this->preferredResources = null;
        return $result;
    }

    /**
     * Gibt die Instanz des Processors für die Erweiterung zurück
     *
     * @param string $type
     *
     * @return ProcessorInterface
     */
    protected function getProcessor($type)
    {
        $configuredWorkers = isset($this->config['processors']) ? $this->config['processors'] : array();
        if (in_array($type, $configuredWorkers)) {
            return $this->getWorker(self::RESOURCE_WORKER_PROCESSOR, $type);
        }
        return null;
    }

    /**
     * Bearbeitet die Datei mit dem Processor für ihre Extension, sofern
     * er exisitert und konfiguriert ist. Ein Zielverzeichnis oder ein
     * neuer Dateiname/Pfad für die überarbeitete Datei kann angegeben werden.
     *
     * @param string $filename
     * @param string $targetDirectory
     * @param string $outputFilename
     * @return string
     */
    protected function processResource($filename, $targetDirectory = null,
                                       $outputFilename = null)
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $type = self::$EXTENSION_TYPE_MAP[$extension];
        try {
            $processorInstance = $this->getProcessor($type);
            if ($processorInstance != null) {
                $config = array();
                if (isset($targetDirectory)) {
                    $config["outputDirectory"] = $targetDirectory;
                }
                if (isset($outputFilename)) {
                    $config["outputFilename"] = $outputFilename;
                }
                return $processorInstance->processFile($filename, $config);
            }
        } catch (Exception $exception) {
            Logger::error("Could not process Resource {$filename}.");
            Logger::error($exception);
        }
        return null;
    }

    /**
     * @param $file
     * @param $type
     * @return string
     * @throws ResourceManagerException
     * @author mregner
     */
    protected function syncResource($file, $type)
    {
        if (isset(self::$SYNC_TYPES[$type])) {
            $assetPath = Environment::getAssetPath();
            $globalResourceDir = $this->getGlobalResourceDir();
            $applicationBaseDir = "/" . basename(Environment::getApplicationPath()) . "/";
            $typeDir = self::$TYPE_DIR_MAP[$type];
            if (preg_match("~{$globalResourceDir}.*?(global/.*)\$~", $file, $matches)) {
                $targetFile = "{$assetPath}/{$matches[1]}";
            } else {
                if (preg_match("~/_?includes/tests/.*?/([a-z]+/[a-z]+/{$typeDir}/.*)\$~", $file, $matches)) {
                    $targetFile = "{$assetPath}tests/{$matches[1]}";
                } else {
                    if (preg_match("~{$applicationBaseDir}(.*)\$~", $file, $matches)) {
                        $targetFile = "{$assetPath}/{$matches[1]}";
                    } else {
                        throw new ResourceManagerException("Resource {$file} could not be synced!");
                    }
                }
            }
            // Unter Umständen wird die Resource noch durch einen Processor bearbeitet, wodurch sich der endgültige Dateiname ändern kann
            $processedFile = $this->processResource($file, dirname($targetFile));
            if ($processedFile != null) {
                $targetFile = $processedFile;
                $targetType = $this->getProcessor($type)->getOutputExtension();
            } else {
                $targetType = $type;
            }
            if (!file_exists($targetFile)) {
                $targetDir = dirname($targetFile);
                file_exists($targetDir) || mkdir($targetDir, 0777, true);
                copy($file, $targetFile);
            }
            return array($targetFile, $targetType);
        }
        return array($file, $type);
    }

    /**      * @return array      * @author mregner */
    protected function getControllerFiles()
    {
        $controllerFiles = array();
        $searchDirs = $this->config["controller_dirs"];
        $configuredControllers = Config::getInstance()->find(array('controller', 'instances'));
        foreach ($configuredControllers as $controllerConfig) {
            $controllerName = $controllerConfig['name'];
            foreach ($searchDirs as $searchDir) {
                if (!isset($controllerFiles[$controllerName]) && file_exists("{$searchDir}/{$controllerName}")) {
                    $foundFiles = FileSystemUtil::scan("{$searchDir}/{$controllerName}", "{$controllerName}.*?\.php\$", FileSystemUtil::TYPE_FILE, 1);
                    if (!empty($foundFiles)) {
                        $creator = $this->getCreator(self::RESOURCE_TYPE_CONTROLLER);
                        isset($creator) && $creator->test($foundFiles[0], $controllerName, array('config' => $controllerConfig));
                        $controllerFiles[$controllerName] = $foundFiles[0];
                    }
                }
            }
        }
        return $controllerFiles;
    }

    /**
     * @param string $controller_file
     * @return array
     */
    protected function getControllerInheritanceChain($controller_file)
    {
        require_once($controller_file);
        $className = pathinfo($controller_file, PATHINFO_FILENAME);
        $inheritsFrom = array();
        $class = ReflectionUtil::getReflectionClass($className);
        // Bis ganz nach oben durchgehen, da von AbstractComponent auch Resourcen geerbt werden (können)
        while (($class = $class->getParentClass()) !== false) {
            $inheritsFrom[] = $class->getName();
        }
        return $inheritsFrom;
    }

    /**      * @return array      * @author mregner */
    protected function getAllControllerResources()
    {
        $controllers = $this->getControllerFiles();
        $controllerResources = array("_controllers" => $controllers,);
        $cachedInheritedResources = array();
        foreach ($controllers as $controllerName => $controllerFile) {
            $resources = $this->getControllerResources($controllerName);
            $inheritanceChain = $this->getControllerInheritanceChain($controllerFile);
            // Resourcen des Controllers laden
            if (!empty($resources)) {
                $controllerResources[$controllerName] = $resources;
            }
            // Resourcen jedes geerbten Controllers bei Bedarf laden dazu mergen
            foreach ($inheritanceChain as $inheritedController) {
                $inheritedController = strtolower($inheritedController);
                if (isset($controllerResources[$inheritedController])) {
                    // Controller schon geladen
                    $inheritedResources = $controllerResources[$inheritedController];
                } else if (isset($cachedInheritedResources[$inheritedController])) {
                    // Controller wurde schon von einem anderen Controller geerbt und geladen
                    $inheritedResources = $cachedInheritedResources[$inheritedController];
                } else { // Resourcen für andere Controller in diesem Loop cachen
                    $inheritedResources = $cachedInheritedResources[$inheritedController] = $this->getControllerResources($inheritedController);
                }
                isset($controllerResources[$controllerName]) || ($controllerResources[$controllerName] = array());
                $controllerResources[$controllerName] = ArrayUtil::mergeRecursivePreserveKeys($inheritedResources, $controllerResources[$controllerName]);
            }
        }
        return $controllerResources;
    }

    /**
     * Holt alle Resourcen für einen Controller aus allen Verzeichnissen im Suchpfad, in dem der Controller liegt
     *
     * @param string $controller_name
     * @return array
     */
    protected function getControllerResources($controller_name)
    {
        $controllerResources = array();
        $searchDirs = $this->config["controller_dirs"];
        foreach ($searchDirs as $searchDirectory) {
            if (file_exists($searchDirectory . "/" . $controller_name)) {
                $resources = $this->getContextResources($searchDirectory . "/" . $controller_name);
                foreach ($resources as $type => $files) {
                    isset($controllerResources[$type]) || $controllerResources[$type] = array();
                    $controllerResources[$type] = array_merge($files, $controllerResources[$type]);
                }
            }
        }
        return $controllerResources;
    }

    /**      * @return string      * @author mregner */
    protected function getGlobalResourceDir()
    {
        if (isset($this->config['global_dir']) && is_dir($this->config['global_dir'])) {
            return $this->config['global_dir'];
        } else {
            return Environment::getRootPath() . "resources/";
        }
    }

    /**      * @return array      * @author mregner */
    protected function getGlobalResources()
    {
        $globalResources = array();
        $globalResourcesDir = $this->getGlobalResourceDir();
        if (file_exists($globalResourcesDir)) {
            $globalDirs = FileSystemUtil::scan($globalResourcesDir, "", FileSystemUtil::TYPE_DIRECTORY, 1);
            if (!empty($globalDirs)) {
                foreach ($globalDirs as $dir) {
                    $context = basename($dir);
                    $globalResources[$context] = $this->getContextResources($dir);
                }
            }
        }
        return $globalResources;
    }

    /**      * @return string      * @author mregner */
    protected
    function getPacketsDir()
    {
        return Environment::getApplicationPath() . "packets/";
    }

    /**
     * @return array
     * @author mregner
     */
    protected function getPacketResources()
    {
        $packetResources = array();
        $packetsDir = $this->getPacketsDir();
        if (file_exists($packetsDir)) {
            $packetDirs = FileSystemUtil::scan($packetsDir, "", FileSystemUtil::TYPE_DIRECTORY, 1);
            $dependencies = array();
            if (!empty($packetDirs)) {
                foreach ($packetDirs as $dir) {
                    $packet = basename($dir);
                    $contextDirs = FileSystemUtil::scan($dir, "", FileSystemUtil::TYPE_DIRECTORY, 1);
                    foreach ($contextDirs as $contextDir) {
                        $context = basename($contextDir);
                        $packetResources[$packet][$context] = $this->getContextResources($contextDir);
                    }
                    if (file_exists("{$dir}/config.packet.yml")) {
                        if (preg_match("~use:\s+([a-z]+)~", file_get_contents("{$dir}/config.packet.yml"), $matches)) {
                            $dependencies[$packet] = $matches[1];
                        }
                    }
                }
                foreach ($dependencies as $packet => $parent) {
                    while (isset($parent)) {
                        if (isset($packetResources[$parent])) {
                            isset($packetResources[$packet]) || $packetResources[$packet] = array();
                            $packetResources[$packet] = ArrayUtil::mergeRecursivePreserveKeys($packetResources[$parent], $packetResources[$packet]);
                        }
                        $parent = isset($dependencies[$parent]) ? $dependencies[$parent] : null;
                    }
                }
            }
        }
        return $packetResources;
    }

    /**
     * @param string $path
     * @return array
     * @throws ResourceManagerException
     * @author mregner
     */
    protected function getContextResources($path)
    {
        $contextResources = array();
        $context = basename($path);
        $resourceFiles = FileSystemUtil::scan($path, "", FileSystemUtil::TYPE_FILE);
        foreach ($resourceFiles as $file) {
            $relativePath = str_replace($path, "", $file);
            // Remove the beginning slash
            (strpos($relativePath, "/") === 0) && $relativePath = substr($relativePath, 1);
            $type = $this->getType($relativePath);
            if ($type !== null) {
                list($file, $type) = $this->syncResource($file, $type);
                $creator = $this->getCreator($type);
                isset($creator) && $creator->test($file, $context);
                $name = $this->getResourceName($relativePath, basename($file));
                if ($this->isSpecialType($type)) {
                    $name = preg_replace("~(\\..*)$~", "", $name);
                }
                if (!isset($contextResources[$type][$name])) {
                    isset($contextResources[$type]) || $contextResources[$type] = array();
                    $contextResources[$type][$name] = $file;
                    isset(self::$ALIASES_MAP[$type]) && array_walk(self::$ALIASES_MAP[$type],
                        function ($alias) use (&$contextResources, &$name, &$file) {
                            isset($contextResources[$alias]) || $contextResources[$alias] = array();
                            $contextResources[$alias][$name] = $file;
                        });
                }
            }
        }

        return $contextResources;
    }

    /**
     * @param string $name
     * @param string $context
     * @param string $type
     * @return string
     * @author mregner
     */
    public function getPath($name, $context = 'global', $type = null)
    {
        return $this->getResource($name, $context, $type);
    }

    /**
     * Gibt alle Resourcen für einen Resource-Typ zurück
     *
     * @param string $type
     * @param string $context
     * @return array
     */
    public function getResources($type, $context)
    {
        $resourceCatalogue = $this->getResourceCatalogue();
        if ($type == self::RESOURCE_TYPE_IMAGE) {
            $images = array();
            foreach (self::$TYPE_DIR_MAP as $resourceType => $typeDir) {
                if ($typeDir == self::RESOURCE_TYPE_IMAGE) {
                    if (isset($resourceCatalogue[$context][$resourceType])) {
                        $images = array_merge($resourceCatalogue[$context][$type], $images);
                    }
                }
            }
            if (!empty($images)) {
                return $images;
            }
        } else {
            if (isset($resourceCatalogue[$context][$type])) {
                return $resourceCatalogue[$context][$type];
            }
        }
        return null;
    }

    /**
     * @param string $name
     * @param string $filename
     * @return string
     */
    protected function getResourceName($name, $filename = null)
    {
        if (strpos($name, "/") !== false) {
            (strpos($name, "/") === 0) && $name = substr($name, 1);
            $parts = explode("/", $name);
            array_shift($parts);
            if (!isset($filename)) {
                $filename = array_pop($parts);
            } else {
                array_pop($parts);
            }
            $resourceName = strtolower(implode("/", $parts) . "/{$filename}");
            (strpos($resourceName, "/") === 0) && $resourceName = substr($resourceName, 1);
            return $resourceName;
        } else {
            return strtolower($name);
        }
    }

    /**
     * @param string $filename
     * @return string
     */
    protected function getType($filename)
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $type = $this->getTypeByExtension($extension);
        if ($type === null) {
            if (strpos($filename, "/") !== false) {
                (strpos($filename, "/") === 0) && $filename = substr($filename, 1);
                $parts = explode("/", $filename);
                $type = $this->getTypeByDir($parts[0]);
            }
        }
        return $type;
    }

    /**
     * @param string $type
     * @return mixed
     */
    protected function isSpecialType($type)
    {
        if (empty($this->specialTypes)) {
            $this->specialTypes = array_flip(self::$CONTROLLER_SPECIAL_RESOURCE_DIRS);
        }
        return isset($this->specialTypes[$type]);
    }

    /**
     * @param string $extension
     * @return string
     */
    protected function getTypeByExtension($extension)
    {
        $extension = strtolower($extension);
        if (isset(self::$EXTENSION_TYPE_MAP[$extension])) {
            return self::$EXTENSION_TYPE_MAP[$extension];
        }
        return null;
    }

    /**
     * @param string $dir
     * @return string
     */
    protected function getTypeByDir($dir)
    {
        $dir = strtolower($dir);
        if (isset(self::$EXTENSION_TYPE_MAP[$dir])) {
            return self::$EXTENSION_TYPE_MAP[$dir];
        }
        if (isset(self::$CONTROLLER_SPECIAL_RESOURCE_DIRS[$dir])) {
            return self::$CONTROLLER_SPECIAL_RESOURCE_DIRS[$dir];
        }
        return null;
    }

    /**
     * Type wird per Referenz übergeben, da der Type sich zB bei Bildern auf die jeweilige
     * Extension des Bildes ändert. Gleiches auch bei Context, hier wird der Context auch unter
     * Umständen modifiziert zurückgegeben, damit man nach dem Aufruf weiss, in welchem Context
     * nun die Resource (nicht) gefunden wurde.
     *
     * @param string $name
     * @param string $context
     * @param string $type
     * @return string
     * @author mregner
     */
    protected function getResource($name, $context = 'global', $type = null)
    {
        $resourceCatalogue = $this->getResourceCatalogue();
        isset($type) || ($type = $this->getType($name));
        if ($type === null) {
            Logger::error("[{$context}] Type can't be inferred: {$name}");
            return null;
        }
        $baseName = $this->getResourceName($name);
        if ($type == self::RESOURCE_TYPE_CONTROLLER) {
            if (isset($resourceCatalogue["_controllers"][$baseName])) {
                return $resourceCatalogue["_controllers"][$baseName];
            }
            return null;
        } else {
            $scopeHierarchy = $this->getResourceScopes();
            $packet = $this->getPacket();
            // Scopes nacheinander auf Vorhandensein prüfen.
            // Z.B.: Scope "aks/external" prüft erst ob es "[Dateiname]_external" gibt, und dann ob es "[Dateiname]_aks" gibt.
            while (!empty($scopeHierarchy)) {
                $scope = array_pop($scopeHierarchy);
                if (strpos($baseName, ".") !== false) {
                    $normalizedName = preg_replace("~(.*)\\.(.*?)$~", "$1_{$scope}.$2", $baseName);
                } else {
                    $normalizedName = "{$baseName}_{$scope}";
                }
                // Inside a with() call
                if (!empty($this->preferredResources) && isset($this->preferredResources[$type][$normalizedName])) {
                    return $this->preferredResources[$type][$normalizedName];
                }
                // Resourcen aus Paketen werden zuerst berücksichtigt
                if (!empty($packet) && isset($resourceCatalogue["_packets"][$packet][$context][$type][$normalizedName])) {
                    return $resourceCatalogue["_packets"][$packet][$context][$type][$normalizedName];
                }
                if (isset($resourceCatalogue[$context][$type][$normalizedName])) {
                    return $resourceCatalogue[$context][$type][$normalizedName];
                }
            }
            // Inside a with() call
            if (!empty($this->preferredResources) && isset($this->preferredResources[$type][$baseName])) {
                return $this->preferredResources[$type][$baseName];
            }
            // Resourcen aus Paketen werden wieder zuerst berücksichtigt
            if (!empty($packet) && isset($resourceCatalogue["_packets"][$packet][$context][$type][$baseName])) {
                return $resourceCatalogue["_packets"][$packet][$context][$type][$baseName];
            }
            // Keine Datei für einen Scope gefunden, Datei ohne Scope versuchen zu holen
            if (isset($resourceCatalogue[$context][$type][$baseName])) {
                return $resourceCatalogue[$context][$type][$baseName];
            }
            if ($context !== "global") {
                $context = "global";
                return $this->getResource($name, $context);
            }
        }
        return null;
    }

    /**
     * Erweitert in CSS-Dateien angegebene relative URLs auf relative URLs vom asset Verzeichnis aus, z.B.:
     *
     * ../images/abc.png wird zu /assets/controllers/page/images/abc.png
     *
     *
     * @param string $cssContent
     * @param string $context
     * @return string
     */
    protected function expandCSSRelativePaths($cssContent, $context)
    {
        // Alle relativen Pfade finden
        if (preg_match_all("~url\\(['\"]*..(\\/[^\\)]+?)(\\?[^\\)'\"]+)?['\"]*\\)~s", $cssContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $extension = pathinfo($match[1], PATHINFO_EXTENSION);
                if (!isset(self::$EXTENSION_TYPE_MAP[$extension])) {
                    continue;
                }
                $relativeUrl = $this->getRelativeUrl($match[1], $context);
                if (!empty($match[2])) {
                    $relativeUrl = "{$relativeUrl}{$match[2]}";
                }
                $cssContent = str_replace($match[0], "url({$relativeUrl})", $cssContent);
            }
        }
        return $cssContent;
    }

    /**      * Führt mehrere Resourcen in eine zusammen und speichert sie für den nächsten Aufruf zwischen      *      * @param array $resources * @return string      * @throws ResourceManagerException */
    public function getMergedResourceUrl(array $resources)
    {
        $currentContoller = ControllerRegistry::current();
        $currentContollerName = $currentContoller->getName();
        // Sortieren, damit der Identifier-Hash bei gleichen Resourcen auch gleich ist, jedoch nicht die eigentliche Reihenfolge ändern
        $sortedResources = $resources;
        sort($sortedResources);
        $resourceId = implode(" | ", $sortedResources);
        $scopeId = implode(" | ", $this->getResourceScopes());
        $type = $this->getType(current($resources));
        $packet = $this->getPacket();
        $proxyPath = Environment::getProxyPath();
        // Auch den Resource-Scope und ProxyPath hier beachten!
        $identifierSource = "{$scopeId}-{$packet}-{$currentContollerName}-{$resourceId}-" . Environment::getProxyPath();
        $identifier = md5($identifierSource);
        $assetPath = Environment::getAssetPath();
        $generatedFile = "{$assetPath}/generated /{$identifier}.{$type}";
        if (!file_exists($generatedFile)) {
            $mergedContent = "";
            // Bei Javascript sicherhaltshalber ein Semikolon zwischen die Scripte setzen,
            // sollten sie minifiziert werden kann es sonst ein Problem geben, wenn alles auf
            // der selben Zeile in der Datei landet.
            $divider = ($type == self::RESOURCE_TYPE_JAVASCRIPT) ? ";" : "";
            foreach ($resources as $resourceRelativePath) {
                // Gesonderte Variablen deklarieren, da getResource $context u.U. modifiziert
                $resourceContext = $currentContollerName;
                $resourceFile = $this->getResource($resourceRelativePath, $resourceContext);
                if (!isset($resourceFile)) {
                    throw new ResourceManagerException("Could not find resource '{$resourceRelativePath}' in controller '{$currentContollerName}' or 'global'!");
                }
                $resourceContent = @file_get_contents($resourceFile);
                // Wenn es sich um CSS dreht müssen relative Pfade angepasst werden
                if ($type == self::RESOURCE_TYPE_CSS) {
                    $resourceContent = $this->expandCSSRelativePaths($resourceContent, $resourceContext);
                }
                $mergedContent .= $divider . $resourceContent . "\n";
            }
            if (!file_exists(dirname($generatedFile))) {
                mkdir(dirname($generatedFile), 0777, true);
            }
            if (@file_put_contents($generatedFile, $mergedContent) === false) {
                throw new ResourceManagerException("Could not write merged file '{$generatedFile}'!");
            }
        }
        $relativeUrl = " / assets / generated /{$identifier}.{$type}";
        if (!empty($proxyPath)) {
            $relativeUrl = $proxyPath . $relativeUrl;
        }
        return $relativeUrl;
    }

    /**  * @param $name * @param string $context  * @return string  * @throws ResourceManagerException  * @author mregner */
    public function getRelativeUrl($name, $context = null)
    {
        $resource = $this->getResource($name, $context);
        $relativeUrl = null;
        if (isset($resource)) {
            $applicationBaseDir = " / " . basename(Environment::getApplicationPath()) . " / ";
            if (preg_match("~{
                $applicationBaseDir}.*?(assets /|) .*?(global/.*)\$~", $resource, $matches)
            ) {
                $relativeUrl = " / assets /{
                $matches[2]}";
            } else if (preg_match("~{
                $applicationBaseDir}.*?(assets /|) .*?(.*)\$~", $resource, $matches)
            ) {
                $relativeUrl = " / assets /{
                $matches[2]}";
            } else {
                throw new ResourceManagerException("Resource {
                $name} in context {
                $context}
could not be found in '{$applicationBaseDir}'!");
            }
            $proxyPath = Environment::getProxyPath();
            if (!empty($proxyPath)) {
                $relativeUrl = $proxyPath . $relativeUrl;
            }
        }
        return $relativeUrl;
    }

    /**  * @param string $name * @param string $context  * @param bool $relative  * @return string  * @author mregner */
    public function getUrl($name, $context = null, $relative = false)
    {
        $relativeUrl = $this->getRelativeUrl($name, $context);
        if (isset($relativeUrl)) {
            $baseUrl = "";
            if ($relative === false) {
                $scheme = Server::isHttps() ? "https" : "http";
                $host = Server::get("HTTP_HOST");
                if (isset($host)) {
                    $baseUrl = "{
                $scheme}://{$host}";
                }
            }
            return "{$baseUrl}{$relativeUrl}";
        }
        return null;
    }
}