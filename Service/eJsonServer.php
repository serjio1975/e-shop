<?php

namespace Api\Service;

use Zend\Json\Server\Response as JsonResponse;
use Zend\Json\Server\Server as JsonServer;
use Zend\Json\Server\ReflectionClass;
use Zend\Server\AbstractServer;
use Zend\Server\Definition;
use Zend\Server\Method;
use Zend\Server\Reflection;


use Zend\XmlRpc\Request as XmlRpcRequest;
use Zend\XmlRpc\Response as XmlRpcResponse;
use Zend\XmlRpc\Server as XmlRpcServer;
use Zend\XmlRpc\Server\Fault as XmlRpcFault;


use Zend\Db\TableGateway\TableGateway;

use Api\Service\ApiFlow;

class eJsonServer extends JsonServer {

    protected function _buildSignature(Reflection\AbstractFunction $reflection, $class = null)
    {
        // @codingStandardsIgnoreEnd
        $ns         = $reflection->getNamespace();
        $name       = $reflection->getName();
        $method     = empty($ns) ? $name : $ns . '.' . $name;
        
        if (! $this->overwriteExistingMethods && $this->table->hasMethod($method)) {
            throw new Exception\RuntimeException('Duplicate method registered: ' . $method);
        }
        
        $definition = new Method\Definition();
        $definition->setName($method)
        ->setCallback($this->_buildCallback($reflection))
        ->setMethodHelp($reflection->getDescription())
        ->setInvokeArguments($reflection->getInvokeArguments());
        
        foreach ($reflection->getPrototypes() as $proto) {
            $prototype = new Method\Prototype();
            $prototype->setReturnType($this->_fixType($proto->getReturnType()));
            foreach ($proto->getParameters() as $parameter) {
                $param = new Method\Parameter([
                    'type'     => $this->_fixType($parameter->getType()),
                    'name'     => $parameter->getName(),
                    'optional' => $parameter->isOptional(),
                ]);
                if ($parameter->isDefaultValueAvailable()) {
                    $param->setDefaultValue($parameter->getDefaultValue());
                }
                $prototype->addParameter($param);
            }
            $definition->addPrototype($prototype);
        }
        if (is_object($class)) {
            $definition->setObject($class);
        }
        $this->table->addMethod($definition);
        return $definition;
    }

    public  function loadApiFunctions($db, $config){
        
        $apiFlow = new ApiFlow($db, (array) $config);
        
        array_walk($apiFlow->getFunctionsCustomer(), function ($item, $key) use ($db, $config) {
            
            $obj = new $item[0]($db, $config);
            
            if (method_exists($obj, $item[1])) {

                $argv = func_get_args();
                $argv = array_slice($argv, 2);

                $function = [$obj, $item[1]];
                
                $argv=null;
                $namespace = '';
                
                $class  = array_shift($function);
                $action = array_shift($function);
                $reflection = Reflection::reflectClass($class, $argv, $namespace);
                $methods = $reflection->getMethods();
                $found   = false;
                foreach ($methods as $method) {
                    if ($action == $method->getName()) {
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    $this->fault('Method not found', Error::ERROR_INVALID_METHOD);
                    return $this;
                }
                
                $definition = $this->_buildSignature($method, $class);
                
                $aDef = array($item[2]=>$definition);
                $this->loadFunctions($aDef);
                
            }
        });
    }
}
