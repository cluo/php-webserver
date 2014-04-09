<?php
class HttpServer
{
    protected $buffers = array();
    protected $nparsed = array();

    protected $config = array(
        'dispatch_mode' => 3,
    );
    protected $_onRequest;

    protected $currentFd;
    protected $headerInfo;
    /**
     * @var \swoole_server
     */
    protected $serv;
    protected $charset = 'utf-8';

    static $HttpStatus = array(
        200 => 'OK',
        404 => 'Not Found',
    );

    protected function clearBuffer($fd)
    {
        $this->buffers[$fd] = "";
        $this->nparsed[$fd] = 0;
    }

    function setCharset($charset)
    {
        $this->charset = $charset;
    }

    /**
     * @param \swoole_server $serv
     * @param $fd
     * @param $from_id
     */
    function onClose($serv, $fd, $from_id)
    {
        $this->clearBuffer($fd);
    }

    /**
     * @param \swoole_server $serv
     * @param $fd
     * @param $from_id
     * @param $data
     */
    function onReceive($serv, $fd, $from_id, $data)
    {
        $parser = new \HttpParser;
        if(empty($this->buffers[$fd]))
        {
            $this->clearBuffer($fd);
        }
        $this->buffers[$fd] .= $data;
        $buffer = &$this->buffers[$fd];
        $nparsed = &$this->nparsed[$fd];
        $nparsed = $parser->execute($buffer, $nparsed);

        if ($parser->hasError())
        {
            $serv->close($fd, $from_id);
        }
        else if ($parser->isFinished())
        {
            $this->clearBuffer($fd);
            $_SERVER = $parser->getEnvironment();
            $_GET = array();
            if(!empty($_SERVER['QUERY_STRING']))
            {
                parse_str($_SERVER['QUERY_STRING'], $_GET);
            }
            if(!empty($_SERVER['REQUEST_BODY']))
            {
                parse_str($_SERVER['REQUEST_BODY'], $_POST);
            }
            if(!empty($_SERVER['HTTP_COOKIE']))
            {
                $_COOKIE = self::parseCookie($_SERVER['HTTP_COOKIE']);
            }
            $this->currentFd = $fd;
            call_user_func($this->_onRequest, $this);
        }
    }

    function http404($content = null)
    {
        if($content == null)
        {
            $content = "<h1>Page Not Found</h1><hr />Swoole Web Server v".SWOOLE_VERSION;
        }
        $this->response($content, 404);
    }

    function header($key, $value)
    {
        $this->headerInfo[$key] = $value;
    }

    function response($respData, $code = 200)
    {
        if(!isset($this->headerInfo['Content-Type']))
        {
            $this->headerInfo['Content-Type'] = 'text/html; charset='.$this->charset;
        }
        $response = implode("\r\n", array(
            'HTTP/1.1 '.$code.' '.self::$HttpStatus[$code],
            'Cache-Control: must-revalidate,no-cache',
            'Content-Language: zh-CN',
            'Server: swoole-'.SWOOLE_VERSION,
            'Content-Type: '.$this->headerInfo['Content-Type'],
            'Content-Length: ' . strlen($respData),
            '',
            $respData));
        $this->serv->send($this->currentFd, $response);
        //$this->serv->close($this->currentFd);
    }

    static function parseCookie($strHeaders)
    {
        $result = array();
        $aHeaders = explode(';', $strHeaders);
        foreach ($aHeaders as $line)
        {
            list($k, $v) = explode('=', trim($line), 2);
            $result[$k] = urldecode($v);
        }
        return $result;
    }

    function onRequest($callback)
    {
        $this->_onRequest = $callback;
    }

    function config(array $config)
    {
        $this->config = $config;
    }

    function daemon()
    {
        $this->config['daemonize'] = 1;
    }

    function run($host = '0.0.0.0', $port = 9999, $process_num = 8)
    {
        register_shutdown_function(array($this, 'handleFatal'));
        $server = new \swoole_server($host, $port);
        $this->serv = $server;
        $server->on('Receive', array($this, 'onReceive'));
        $server->on('Close', array($this, 'onClose'));
        $this->config['worker_num'] = $process_num;
        $server->set($this->config);
        $server->start();
    }

    /**
     * Fatal Error的捕获
     * @codeCoverageIgnore
     */
    public function handleFatal()
    {
        $error = error_get_last();
        if (!isset($error['type'])) return;
		switch ($error['type'])
		{
			case E_ERROR :
			case E_PARSE :
			case E_DEPRECATED:
			case E_CORE_ERROR :
			case E_COMPILE_ERROR :
				break;
			default:
			return;
        }
        $message = $error['message'];
		$file = $error['file'];
		$line = $error['line'];
		$log = "$message ($file:$line)\nStack trace:\n";
		$trace = debug_backtrace();
		foreach ($trace as $i => $t)
		{
			if (!isset($t['file']))
			{
				$t['file'] = 'unknown';
			}
			if (!isset($t['line']))
			{
				$t['line'] = 0;
			}
			if (!isset($t['function']))
			{
				$t['function'] = 'unknown';
			}
			$log .= "#$i {$t['file']}({$t['line']}): ";
			if (isset($t['object']) && is_object($t['object']))
			{
				$log .= get_class($t['object']) . '->';
			}
			$log .= "{$t['function']}()\n";
		}
		if (isset($_SERVER['REQUEST_URI']))
		{
			$log .= '[QUERY] ' . $_SERVER['REQUEST_URI'];
		}
		error_log($log);
		$this->response($this->currentFd, $log);
    }
}

$server = new HttpServer;
$server->onRequest(function($server){
	$server->response("<h1>hello world</h1>");
});
$server->run();
