<?php /**  * @created 30.11.2012 10:12:01  * @author mregner  * @version $Id$ */
require_once('AbstractRequestStage.php');
require_once('RequestWrapper.php');
require_once('ResponseWrapper.php');
require_once('fblib/core/init/BootstrapInterface.php');
require_once('fblib/text/StringCrypt.php');

class RequestPipeline implements BootstrapInterface
{
    /**      * @var RequestPipeline */
    private static $instance = null;
    /**      * @var AbstractRequestStage[] */
    private $pipeline = array();
    /**      * @var AbstractRequestStage */
    private $current = null;
    /**      * @var bool */
    private $rewrite = false;

    /**      * @return RequestPipeline      * @author mregner */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new RequestPipeline();
        }
        return self::$instance;
    }

    /**      * @param array $config * @author mregner */
    public static function initialize(array $config)
    {
        if (is_array($config)) {
            foreach ($config as $handlerConfig) {
                if (isset($handlerConfig['file'])) {
                    $file = $handlerConfig['file'];
                    require_once($file);
                    $class = basename($file, '.php');
                    self::getInstance()->addRequestStage(new $class($handlerConfig));
                }
            }
        }
    }

    /**      * @author mregner */
    protected function __construct()
    {
    }

    /**      * @param array $data * @author mregner */
    public function run(array $data)
    {
        ob_start();
        if (is_array($this->pipeline)) {
            $request = new RequestWrapper($data);
            $response = new ResponseWrapper();
            foreach ($this->pipeline as $this->current) {
                if ($this->current instanceof AbstractRequestStage) {
                    $this->current->handleRequest($request, $response);
                    if ($response->hasRewrite() && !$this->rewrite) {
                        ob_end_clean();
                        $newData = $response->getRewrite();
                        if ($newData['rewrite_as_redirect'] === true) {
                            $response->setHeader("Location", $response->getRewriteURL(), true, 302);
                            $response->sendHeader();
                        } else {
                            $this->rewrite = true;
                            $this->run($newData);
                            $this->rewrite = false;
                        }
                        return;
                    }
                }
            }
        }
        ob_end_flush();
    }

    /**      * @param AbstractRequestStage $request_stage * @author mregner */
    public function addRequestStage(AbstractRequestStage $request_stage)
    {
        if (is_array($this->pipeline)) {
            $this->pipeline[] = $request_stage;
        }
    }
}


