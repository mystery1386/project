<?php
/**
 * Requestbroker Stage fuer die RequestPipeline um Komponenten von dort
 * ansprechen zu koennen.
 *
 * @created 03.12.2012 08:47:11
 * @author mregner
 * @version $Id$
 */
require_once('fblib/core/app/AbstractRequestStage.php');
require_once('ControllerRequestBroker.php');

class ControllerRequestBrokerStage extends AbstractRequestStage
{
    /**
     * (non-PHPdoc)
     * @see AbstractRequestStage::handleRequest()
     */
    public function handleRequest(RequestWrapper $request, ResponseWrapper $response)
    {
        ControllerRequestBroker::handleRequest($request, $response);
    }
}
