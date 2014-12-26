<?php /**  * @created 03.12.2012 11:59:17  * @author mregner  * @version $Id$ */
require_once('fblib/core/app/AbstractRequestStage.php');
require_once('output/DefaultOutput.php');
require_once('output/PlainOutput.php');

class OutputStage extends AbstractRequestStage
{
    /**      * (non-PHPdoc)      * @see AbstractRequestStage::handleRequest() */
    public function handleRequest(RequestWrapper $request, ResponseWrapper $response)
    {
        ob_start();
        $output = $this->getOutputObject($request);
        if (isset($request['data'])) {
            $output->outputData($request, $response);
        } else if ($response->hasOutput('view')) {
            $output->outputView($request, $response);
        } else if ($response->hasOutput('action')) {
            $output->outputAction($request, $response);
        }
        ob_end_flush();
    }

    /**      * @param RequestWrapper $request * @return AbstractOutput      * @author mregner */
    protected function getOutputObject(RequestWrapper $request)
    {
        if ($request['_output'] === 'plain') {
            $format = isset($request['_format']) ? $request['_format'] : PlainOutput::DEFAULT_FORMAT;
            return new PlainOutput($format);
        } else {
            return new DefaultOutput();
        }
    }
}
