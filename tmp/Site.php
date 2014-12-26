<?php
/**
 * Zentraler Einstiegspunkt fuer die Bearbeitung eines HTTP Requests.
 *
 * @created 30.11.2012 12:50:59
 * @author mregner
 * @version $Id$ */
require_once('RequestPipeline.php');
require_once('fblib/core/globals/Request.php');
class Site {
    /**
     * @author mregner */
    public static function run()
    {
        RequestPipeline::getInstance()->run(Request::getData());
    }
}