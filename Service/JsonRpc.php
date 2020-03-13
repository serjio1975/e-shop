<?php
namespace JsonRpc\Service;

use Zend\Db;
use Zend\Db\TableGateway\TableGateway;

class JsonRpc
{

    private $db;
    private $config;

    public function __construct($db, $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function log($msg)
    {
        file_put_contents('logs/'.__CLASS__ . '.log', date("Y-m-d H:i:s") . " | " . $msg . "\n", FILE_APPEND);
    }


}