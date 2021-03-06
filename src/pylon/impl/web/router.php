<?php

namespace pylon\impl ;
use XInterceptor ;
use XLogKit ;
use XLogSpeed ;
use XContext ;
use XProperty ;
use XSetting ;
use XBox ;
use XAop ;
use XDBC ;
use Exception ;
use XExceptionUtls ;
use XErrCode ;

class XInterceptorRuner extends XInterceptor
{
    private $beforedItcs = null ;
    private $allItcs     = null ;
    private $plog        = null ;
    public function __construct($itcs)
    {
        $this->allItcs     = $itcs ;
        $this->beforedItcs = array() ;
        $this->plog        = XLogKit::logger("_pylon") ;
    }
    public function _before($xcontext,$request,$response)
    {
        foreach($this->allItcs as $itc)
        {
            array_unshift($this->beforedItcs,$itc) ;
            $itc->_before($xcontext,$request,$response);
            $this->plog->debug( get_class($itc) . "._before  " ) ;
        }

    }
    public function _exception($e,$xcontext,$request,$response)
    {
        static::doException($this->beforedItcs,$e,$xcontext,$request,$response) ;
    }

    static private function doException($intcs,$e,$xcontext,$request,$response)
    {
            $plog     = XLogKit::logger("_pylon") ;
            foreach( $intcs  as $itc )
            {
                $end = $itc->_exception($e,$xcontext,$request,$response) ;
                if ($end === true)
                {
                    break ;
                }
            }
            XExceptionUtls::logException($plog,$e) ;
            $response->exception($e);
    }


    public function _after($xcontext,$request,$response)
    {
        $unAfterItcs = $this->beforedItcs ;
        foreach( $this->beforedItcs as $itc )
        {
            try
            {
                $itc->_after($xcontext,$request,$response) ;
                $this->plog->debug( get_class($itc) . "._after" ) ;
                array_shift($unAfterItcs) ;
            }
            catch(Exception $e)
            {
                static::doException($unAfterItcs,$e,$xcontext,$request,$response) ;
            }
        }

    }

}
class XRouter
{
    static private function callService($conf,$xcontext,$request,$response)
    {

        $method  = $_SERVER['REQUEST_METHOD'];
        $plog    = XLogKit::logger("_pylon") ;
        $conf    = json_decode($conf,true);
        $itarget = new XIntercepterTarget($request);
        if(isset($conf['uri']))
            $request->mergeArray($conf['uri']);


        $plog->debug("request uri: $uri");
        $itc   = new XInterceptorRuner(XAop::using($itarget)) ;
        try
        {
            $cls                     = $conf['cls'];
            if (!class_exists($cls))
            {
                throw new \XNotFound("Not found Router Process Class: $cls ") ;
            }

            $plog->debug(" service cls : $cls " );
            $xcontext->__service_run = true ;
            $xcontext->__service_cls = $cls ;
            $itc->_before($xcontext,$request,$response) ;
            if ( $xcontext->__service_run === true )
            {
                $obj     = new $cls;
                call_user_func(array($obj , "_before" ), $xcontext,$request,$response);
                call_user_func(array($obj , "_$method"), $xcontext,$request,$response);
                call_user_func(array($obj , "_after"  ), $xcontext,$request,$response);
            }
            else
            {
                $plog->debug(" $xcontext->__service_run[false]  service passed!" );
            }
        }
        catch(Exception $e)
        {
            $itc->_exception($e,$xcontext,$request,$response) ;
        }
        $itc->_after($xcontext,$request,$response);
    }
    static private function log_request($logger,$level='info')
    {
        $ip             = $_SERVER['REMOTE_ADDR'];
        $uri            = $_SERVER['REQUEST_URI'];
        $method         = $_SERVER['REQUEST_METHOD'];
        $logger->$level("ip: $ip , method: $method , uri : $uri","request");
        if(! empty($_POST))
        {
            $logger->$level("data : " .  http_build_query($_POST),"request");
        }
    }
    static public function serving($http_status = true )
    {

        ob_start();
        if ($http_status)
        {
            PYL_HttpHeader::out_header(500);
        }
        $restLog      = XLogKit::logger("_rest");
        $uri          = $_SERVER['REQUEST_URI'];
        $autoSpeed    = new XLogSpeed("rest[$uri]");
        $xcontext     = new XContext ;
        $request      = new XProperty($_REQUEST) ;
        $response     = null;
        if (is_callable(XSetting::$respInsFun))
        {
            $response = call_user_func(XSetting::$respInsFun,$uri) ;
            XDBC::requireNotNull($response,"XSetting::\$respInsFun return null ") ;
        }
        else
        {
            $response     = new XSetting::$respClass ;
        }
        static::log_request($restLog,'info');
        $request->uri = $uri ;

        //优先配置
        $rest_conf      = static::find_conf($uri);
        //分析约定
        if ($rest_conf == null)
        {
            $rest_conf  = static::parse_conf($uri);
        }
        if ($rest_conf == null)
        {
            $response->error("UnFound Service for $uri ",XErrCode::UNKNOW,404);
        }
        else
        {
            static::callService($rest_conf,$xcontext,$request,$response);
        }
        $response ->send($restLog,$http_status);
        ob_flush();
        unset($autoSpeed) ;
        return  $response ;
    }
    static private function find_conf($uri)
    {
        $finder = XBox::get(XBox::ROUTER);
        if($finder === null)
        {
            throw new XLogicException("没有找到 router ,可能是你没有注册.");
        }
        return $finder->_find($uri);
    }

    static private function parse_conf($uri)
    {
        $uri = trim($uri, '/');
        $pos = strpos($uri, '?');
        if ($pos > 0)
        {
            $uri = substr($uri, 0, $pos);
        }
        $uri_arr = explode('/', $uri);
        $uri_arr_count = count($uri_arr);

        $conf = array();
        $cls_name = implode('_', array_slice($uri_arr, 0, $uri_arr_count - 1));
        if (empty($cls_name))
        {
            return  null;
        }
        $conf['cls'] = $cls_name;
        $method = $uri_arr[$uri_arr_count - 1];
        $conf['uri'] = array("method" => $method);
        return @json_encode($conf);
    }
}
