<?php
class HttpServer
{
    protected $buffers = array();
    protected $nparsed = array();

    function clearBuffer($fd)
    {
        $this->buffers[$fd] = "";
        $this->nparsed[$fd] = 0;
    }

    function onClose($serv, $fd, $from_id)
    {
        $this->clearBuffer($fd);
    }

    function onReceive($serv, $fd, $from_id, $data)
    {
//        echo "Receive : fd=$fd|data=$data\n";

        $parser = new HttpParser;
        if(empty($this->buffers[$fd]))
        {
            $this->clearBuffer($fd);
        }
        $this->buffers[$fd] .= $data;
        $buffer = &$this->buffers[$fd];
        $nparsed = &$this->nparsed[$fd];
        $nparsed = $parser->execute($buffer, $nparsed);
//        echo "nread=".strlen($data)."|nparsed=$nparsed\n";
        if ($parser->hasError())
        {
            $serv->close($fd, $from_id);
        }
        else if ($parser->isFinished())
        {
            $this->clearBuffer($fd);
            //$env = $parser->getEnvironment();

           // $result = '<form action="" method="post"><input type="submit" name="testvar" value="Testing!" /></form><pre>';
           // foreach ($env as $k => $v)
           //     $result .= sprintf("%s -> '%s'\n", $k, $v);

            $result = "hello world";

            $response = join(
                "\r\n",
                array(
                    'HTTP/1.1 200 OK',
                    'Content-Type: text/html',
                    'Content-Length: '.strlen($result),
                    '',
                    $result));
            $serv->send($fd, $response);
            $serv->close($fd);
        }
    }
}

$server = new swoole_server('127.0.0.1', 9506);
$php_http_server = new HttpServer;
$server->on('Receive', array($php_http_server, 'onReceive'));
$server->set(array('worker_num'=>1));
$server->start();