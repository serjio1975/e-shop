<?php
namespace JsonRpc\Service\Factory;

use Interop\Container\ContainerInterface;
use JsonRpc\Service\JsonRpc;

class JsonRpcFactory
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {        
        $db = $container->get(\Zend\Db\Adapter\Adapter::class);

        return new JsonRpc($db,null);
    }
}
