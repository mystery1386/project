<?php
/**
 * @created 23.01.2013 10:35:03
 * @author mregner
 * @version $Id$
 */
require_once('fblib/core/globals/Environment.php');
require_once('fblib/core/init/Config.php');
require_once('fblib/util/fs/FileSystemUtil.php');
require_once('fblib/core/mvc/controller/build/ControllerCompiler.php');
require_once('fblib/core/mvc/controller/build/ControllerWrapperCompiler.php');
require_once('fblib/core/mvc/controller/coupling/ControllerRemoteCallBindings.php');
require_once('fblib/core/mvc/controller/coupling/ControllerRequirementBindings.php');
require_once('fblib/core/mvc/controller/coupling/ControllerSoapActionMappings.php');
require_once('fblib/core/mvc/controller/ControllerWrapper.php');
require_once('fblib/core/mvc/controller/ControllerException.php');

class ControllerRegistry {
    /**
     * @var ControllerRegistry
     */
    protected static $instance = null;
    /**
     * @var array
     */
    protected $controllerStack = array();
    /**
     * Alle registrierten Komponenten.
     *
     * @var array
     */
    protected $controller = array();
    /**
     * Komponenten Katalog.
     * @var array
     */
    protected $catalogue = null;
    /**
     * @var array
     */
    protected $controllerReference = null;
    /**
     * @var ControllerRemoteCallBindings
     */
    protected $controllerBindings = null;
    /**
     * @var ControllerRequirementBindings
     */
    protected $requirementBindings = null;
    /**
     * @var ControllerSoapActionMappings
     */
    protected $soapActionMappings = null;

    /**
     *
     */
    protected function __construct()
    {
    }

    /**
     * @return ControllerRegistry
     * @author mregner
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new ControllerRegistry();
            self::$instance->init();
        }
        return self::$instance;
    }

    /**
     * @author mregner
     */
    public function init() {
        //Wir generieren alles einmal.
        $this->generateCatalogue();
        $this->generateControllerReference();
        $this->generateControllerRemoteCallBindings();
        $this->generateControllerRequirementBindings();
        $this->getSoapActionMappings()->generateMapping();
    }
    /**
     * @param string $alias
     * @return ControllerWrapper
     * @author mregner
     */
    public static function get($alias) {
        if($alias === 'current') {
            return self::current();
        } else {
            return self::getInstance()->getController($alias);
        }
    }
    /**
     * @param string $alias
     * @return bool
     * @author mregner
     */
    public static function exists($alias) {
        if ($alias === "current") {
            return true;
        }
        if($alias === 'default') {
            $alias = Config::getInstance()->find(array('controller','default','name'));
        }
        return Config::has("controller/instances/{$alias}");
    }
    /**
     * @return ControllerWrapper
     * @author mregner
     */
    public static function current() {
        return self::getInstance()->getCurrentController();
    }
    /**
     * @return array
     * @author mregner
     */
    public static function getAllControllerRequirements() {
        $allRequirements = array();
        $catalogue = array_keys(self::getInstance()->getCatalogue());
        foreach($catalogue as $controller) {
            $allRequirements[$controller] = self::getInstance()->getRequirementBindings()->getControllerRequirements($controller);
        }
        return $allRequirements;
    }
    /**
     * @param $soapaction
     * @return array
     */
    public static function getSoapActionProvider($soapaction) {
        return self::getInstance()->getSoapActionMappings()->get($soapaction);
    }
    /**
     * Diese Methode wird vor dem rendern eines Templates aufgerufen.
     * Uebergeben wird hier immer der Wrapper.
     *
     * @param ControllerInterface $controller
     * @author mregner
     */
    public function pushController(ControllerInterface $controller) {
        array_unshift($this->controllerStack, $controller);
    }
    /**
     * Diese Methode wird nach dem rendern eines Templates aufgerufen.
     *
     * @return ControllerInterface
     * @author mregner
     */
    public function popController() {
        return array_shift($this->controllerStack);
    }
    /**
     * pushController und popController werden vor und nach dem rendern eines Templates aufgerufen.
     * Current ist somit der aktuell rendernde Controller.
     * @throws ControllerException
     * @return ControllerWrapper
     * @author mregner
     */
    public function getCurrentController() {
        if(isset($this->controllerStack[0])) {
            return $this->controllerStack[0];
        } else {
            throw new ControllerException("No controller on the stack.");
        }
    }
    /**
     * @param string $alias
     * @return ControllerWrapper
     * @author mregner
     */
    public function getController($alias) {
        $normalizedAlias = strtolower($alias);
        if(!isset($this->controller[$normalizedAlias])) {
            $this->controller[$normalizedAlias] = $this->createController($normalizedAlias);
        }
        return $this->controller[$normalizedAlias];
    }
    /**
     * @param string $alias
     * @return ControllerWrapper
     * @author mregner
     */
    protected function createController($alias) {
        if($alias === 'default') {
            $alias = Config::getInstance()->find(array('controller','default','name'));
        }
        $controllerConfig = $this->getControllerConfig($alias);
        if(is_array($controllerConfig)) {
            $params = array(
                'config' => $controllerConfig,
                'bindings' => $this->getControllerBindings($alias),
            );
            return ResourceManager::getInstance()->get($controllerConfig['name'], 'global', ResourceManager::RESOURCE_TYPE_CONTROLLER, $params);
        }
        return null;
    }
    /**
     * Ermittelt zu einem Alias die kongigurierte Komponente.
     *
     * @param string $name
     * @return array
     * @throws ControllerException
     * @author mregner
     */
    protected function getControllerConfig($name) {
        $controllerConfig = Config::getInstance()->find(array('controller', 'instances', $name));
        if(is_array($controllerConfig) && isset($controllerConfig['name'])) {
            $controllerConfig['alias'] = $name;
            $controllerConfig['configured'] = true;
        } else {
            $controllerName = $name;
            $controllerConfig = array(
                'name' => $controllerName,
                'alias' => $controllerName,
                'configured' => false,
            );
        }
        return $controllerConfig;
    }
    /**
     * @author mregner
     */
    protected function getControllerBindings($name) {
        return array(
            'requirements' => $this->getRequirementBindings()->getControllerRequirements($name),
            'requirement_bindings' => $this->getRequirementBindings()->get($name),
            'requirement_dependencies' => $this->getRequirementBindings()->getRequirementDependencies($name),
            'provisions' => $this->getRequirementBindings()->getControllerProvisions($name),
            'remotecall_bindings' => $this->getRemoteCallBindings()->get($name),
        );
    }
    /**
     * Sucht nach allen verfuegbaren Komponenten und Controllern und erstellt einen
     * Katalog so das der Zugriff schneller geht.
     *
     * @return array
     * @throws ControllerException
     * @author mregner
     */
    protected function getCatalogue() {
        if(!isset($this->catalogue)) {
            return $this->generateCatalogue();
        }
        return $this->catalogue;
    }
    /**
     * Erzeugt einen Katalog mit den Namen der konfigurierten controller und den jeweiligen Wrappern.
     * Sowohl die Final-Variante des Controllers als auch der Wrapper werden hier kompiliert.
     *
     * @return array
     *
     * @throws ControllerException
     * @author mregner
     */
    protected function generateCatalogue() {
        $rebuild = Config::get('REBUILD_CATALOGUE');
        $catalogFile = Environment::getTempPath('meta') . "catalogue.php";
        if(!file_exists($catalogFile) || $rebuild === true) {
            $controllerConfigs = Config::getInstance()->find(array('controller', 'instances'));
            $this->catalogue = array();
            $errors = array();
            foreach ($controllerConfigs as $alias => $controllerConfig) {
                $controllerFile = ResourceManager::getInstance()->getPath("controllers/{$controllerConfig["name"]}");
                if($controllerFile != null) {
                    if(!isset($this->catalogue[$alias])) {
                        $finalController = ControllerCompiler::compile($controllerFile);
                        if(!empty($finalController)) {
                            $controllerWrapper = ControllerWrapperCompiler::compile($finalController);
                            $this->catalogue[$alias] = $controllerWrapper;
                        } else {
                            $errors[] = "Cound not compile final controller {$finalController}!";
                        }
                    }
                } else {
                    $errors[] = "No controller named '{$alias}' found. Check your config you may have a typo here!!!";
                }
            }
            if (!empty($errors)) {
                unset($this->catalogue);
                throw new ControllerException(implode("\n", $errors));
            }
            // Katalog speichern so das wir den nicht nochmal holen muessen.
            $content = "<?php\n";
            $content .= "//GENERATED BY " . __FILE__ . "\n";
            $content .= "return " . var_export($this->catalogue, true) . ";\n";
            file_put_contents($catalogFile, $content);
        } else {
            $this->catalogue = require ($catalogFile);
        }

        return $this->catalogue;
    }
    /**
     * Erzeugt die öffentliche Referenz der Controller aus dem Katalog.
     * @return array
     */
    protected function generateControllerReference() {
        $rebuild = Config::get('REBUILD_CONTROLLER_REFERENCE');
        $classReferencesFile = Environment::getTempPath('meta') . "controllerreference.php";
        if (!file_exists($classReferencesFile) || $rebuild === true) {
            $this->controllerReference = array();
            $catalogue = $this->getCatalogue();
            foreach ($catalogue as $name => $file) {
                if (preg_match('~abstract~i', $file) && !(preg_match('~tests~', $file))) {
                    continue;
                }
                require_once($file);
                $wrapperClass = basename($file, ".php");
                /** @var ControllerWrapper $wrapper */
                $wrapper = new $wrapperClass($this->getControllerConfig($name));
                $reference = $wrapper->getMetaData();
                $this->controllerReference[strtolower($name)] = $reference;
            }
            if (!empty($this->controllerReference)) {
                $content = "<?php //GENERATED BY " . __FILE__ . "\n";
                $content .= "return " . var_export($this->controllerReference, true) . ";\n";
                file_put_contents($classReferencesFile, $content);
            }
        } else {
            $this->controllerReference = require($classReferencesFile);
        }
        return $this->controllerReference;
    }
    /**
     * @author mregner
     */
    protected function generateControllerRemoteCallBindings() {
        $this->getRemoteCallBindings()->generate();
    }

    /**
     * @author mregner
     */
    protected function generateControllerRequirementBindings() {
        $this->getRequirementBindings()->generate();
        $this->getRequirementBindings()->generateRequirementDependencies();
    }

    /**
     * @return array
     * @throws ControllerException
     */
    protected function getControllerReference()
    {
        if (!isset($this->controllerReference)) {
            return $this->generateControllerReference();
        }
        return $this->controllerReference;
    }

    /**
     * @return ControllerRemoteCallBindings
     * @author mregner
     */
    public  function getRemoteCallBindings() {
        if (!isset($this->controllerBindings)) {
            $this->controllerBindings = new ControllerRemoteCallBindings($this->getControllerReference());
        }
        return $this->controllerBindings;
    }

    /**
     * @return ControllerRequirementBindings
     * @author mregner
     */
    public function getRequirementBindings()    {
        if (!isset($this->requirementBindings)) {
            $this->requirementBindings = new ControllerRequirementBindings($this->getControllerReference());
        }
        return $this->requirementBindings;
    }

    /**
     * @return ControllerSoapActionMappings
     */
    public function getSoapActionMappings() {
        if (!isset($this->soapActionMappings)) {
            $this->soapActionMappings = new ControllerSoapActionMappings($this->getControllerReference());
        }
        return $this->soapActionMappings;
    }
}


