<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Soap;

use Zend\Server\Reflection;
use Zend\Soap\AutoDiscover\DiscoveryStrategy\DiscoveryStrategyInterface as DiscoveryStrategy;
use Zend\Soap\AutoDiscover\DiscoveryStrategy\ReflectionDiscovery;
use Zend\Soap\Exception;
use Zend\Soap\Wsdl;
use Zend\Soap\Wsdl\ComplexTypeStrategy\ComplexTypeStrategyInterface as ComplexTypeStrategy;
use Zend\Uri;

/**
 * \Zend\Soap\AutoDiscover
 *
 */
class AutoDiscover
{
    /**
     * @var string
     */
    protected $serviceName;

    /**
     * @var \Zend\Server\Reflection
     */
    protected $reflection = null;

    /**
     * Service function names
     * @var array
     */
    protected $functions = array();

    /**
     * Service class name
     * @var string
     */
    protected $class;

    /**
     * @var bool
     */
    protected $strategy;

    /**
     * Url where the WSDL file will be available at.
     * @var WSDL Uri
     */
    protected $uri;

    /**
     * soap:body operation style options
     * @var array
     */
    protected $operationBodyStyle = array(
        'use' => 'encoded',
        'encodingStyle' => "http://schemas.xmlsoap.org/soap/encoding/"
    );

    /**
     * soap:operation style
     * @var array
     */
    protected $bindingStyle = array(
        'style' => 'rpc',
        'transport' => 'http://schemas.xmlsoap.org/soap/http'
    );

    /**
     * Name of the class to handle the WSDL creation.
     * @var string
     */
    protected $wsdlClass = 'Zend\Soap\Wsdl';

    /**
     * Class Map of PHP to WSDL types.
     * @var array
     */
    protected $classMap = array();

    /**
     * Discovery strategy for types and other method details.
     * @var DiscoveryStrategy
     */
    protected $discoveryStrategy;

    /**
     * Constructor
     *
     * @param ComplexTypeStrategy $strategy
     * @param string|Uri\Uri $endpointUri
     * @param string $wsdlClass
     * @param array $classMap
     */
    public function __construct(ComplexTypeStrategy $strategy = null, $endpointUri = null, $wsdlClass = null, array $classMap = array())
    {
        $this->reflection = new Reflection();
        $this->setDiscoveryStrategy(new ReflectionDiscovery());

        if ($strategy !== null) {
            $this->setComplexTypeStrategy($strategy);
        }

        if ($endpointUri !== null) {
            $this->setUri($endpointUri);
        }

        if ($wsdlClass !== null) {
            $this->setWsdlClass($wsdlClass);
        }
    }

    /**
     * Set the discovery strategy for method type and other information.
     *
     * @param  DiscoveryStrategy $discoveryStrategy
     *
     * @return AutoDiscover
     */
    public function setDiscoveryStrategy(DiscoveryStrategy $discoveryStrategy)
    {
        $this->discoveryStrategy = $discoveryStrategy;

        return $this;
    }

    /**
     * Get the discovery strategy.
     *
     * @return DiscoveryStrategy
     */
    public function getDiscoveryStrategy()
    {
        return $this->discoveryStrategy;
    }

    /**
     * Get the class map of php to wsdl mappings.
     *
     * @return array
     */
    public function getClassMap()
    {
        return $this->classMap;
    }

    /**
     * Set the class map of php to wsdl mappings.
     *
     * @return AutoDiscover
     */
    public function setClassMap($classMap)
    {
        $this->classMap = $classMap;

        return $this;
    }

    /**
     * Set service name
     *
     * @param string $serviceName
     * @throws Exception\InvalidArgumentException
     *
     * @return AutoDiscover
     */
    public function setServiceName($serviceName)
    {
        $matches = array();
        // first character must be letter or underscore {@see http://www.w3.org/TR/wsdl#_document-n}
        $i = preg_match('/^[a-z\_]/ims', $serviceName, $matches);
        if ($i != 1) {
            throw new Exception\InvalidArgumentException('Service Name must start with letter or _');
        }

        $this->serviceName = $serviceName;

        return $this;
    }

    /**
     * Get service name
     *
     * @throws Exception\RuntimeException
     *
     * @return string
     */
    public function getServiceName()
    {
        if (!$this->serviceName) {
            if ($this->class) {
                return $this->reflection->reflectClass($this->class)->getShortName();
            } else {
                throw new Exception\RuntimeException('No service name given. Call AutoDiscover::setServiceName().');
            }
        }

        return $this->serviceName;
    }


    /**
     * Set the location at which the WSDL file will be available.
     *
     * @param  Uri\Uri|string $uri
     * @throws Exception\InvalidArgumentException
     *
     * @return AutoDiscover
     */
    public function setUri($uri)
    {
        if (!is_string($uri) && !($uri instanceof Uri\Uri)) {
            throw new Exception\InvalidArgumentException(
                'Argument to \Zend\Soap\AutoDiscover::setUri should be string or \Zend\Uri\Uri instance.'
            );
        }

        $uri = trim($uri);
        $uri = htmlspecialchars($uri, ENT_QUOTES, 'UTF-8', false);

        if (empty($uri)) {
            throw new Exception\InvalidArgumentException('Uri contains invalid characters or is empty');
        }

        $this->uri = $uri;

        return $this;
    }

    /**
     * Return the current Uri that the SOAP WSDL Service will be located at.
     *
     * @throws Exception\RuntimeException
     *
     * @return Uri\Uri
     */
    public function getUri()
    {
        if ($this->uri === null) {
            throw new Exception\RuntimeException(
                'Missing uri. You have to explicitly configure the Endpoint Uri by calling AutoDiscover::setUri().'
            );
        }
        if (is_string($this->uri)) {
            $this->uri = Uri\UriFactory::factory($this->uri);
        }

        return $this->uri;
    }

    /**
     * Set the name of the WSDL handling class.
     *
     * @param  string $wsdlClass
     * @throws Exception\InvalidArgumentException
     *
     * @return AutoDiscover
     */
    public function setWsdlClass($wsdlClass)
    {
        if (!is_string($wsdlClass) && !is_subclass_of($wsdlClass, '\Zend\Soap\Wsdl')) {
            throw new Exception\InvalidArgumentException(
                'No \Zend\Soap\Wsdl subclass given to Zend\Soap\AutoDiscover::setWsdlClass as string.'
            );
        }

        $this->wsdlClass = $wsdlClass;

        return $this;
    }

    /**
     * Return the name of the WSDL handling class.
     *
     * @return string
     */
    public function getWsdlClass()
    {
        return $this->wsdlClass;
    }

    /**
     * Set options for all the binding operations soap:body elements.
     *
     * By default the options are set to 'use' => 'encoded' and
     * 'encodingStyle' => "http://schemas.xmlsoap.org/soap/encoding/".
     *
     * @param  array $operationStyle
     * @throws Exception\InvalidArgumentException
     *
     * @return AutoDiscover
     */
    public function setOperationBodyStyle(array $operationStyle=array())
    {
        if (!isset($operationStyle['use'])) {
            throw new Exception\InvalidArgumentException('Key "use" is required in Operation soap:body style.');
        }
        $this->operationBodyStyle = $operationStyle;

        return $this;
    }

    /**
     * Set Binding soap:binding style.
     *
     * By default 'style' is 'rpc' and 'transport' is 'http://schemas.xmlsoap.org/soap/http'.
     *
     * @param  array $bindingStyle
     *
     * @return AutoDiscover
     */
    public function setBindingStyle(array $bindingStyle=array())
    {
        if (isset($bindingStyle['style'])) {
            $this->bindingStyle['style'] = $bindingStyle['style'];
        }
        if (isset($bindingStyle['transport'])) {
            $this->bindingStyle['transport'] = $bindingStyle['transport'];
        }

        return $this;
    }

    /**
     * Set the strategy that handles functions and classes that are added AFTER this call.
     *
     * @param  ComplexTypeStrategy $strategy
     *
     * @return AutoDiscover
     */
    public function setComplexTypeStrategy(ComplexTypeStrategy $strategy)
    {
        $this->strategy = $strategy;

        return $this;
    }

    /**
     * Set the Class the SOAP server will use
     *
     * @param string $class Class Name
     *
     * @return AutoDiscover
     */
    public function setClass($class)
    {
        $this->class = $class;

        return $this;
    }

    /**
     * Add a Single or Multiple Functions to the WSDL
     *
     * @param string $function Function Name
     * @throws Exception\InvalidArgumentException
     *
     * @return AutoDiscover
     */
    public function addFunction($function)
    {
        if (is_array($function)) {
            foreach($function as $row){
                $this->addFunction($row);
            }
        } elseif (is_string($function)) {
            if (function_exists($function)) {
                $this->functions[] = $function;
            } else {
                throw new Exception\InvalidArgumentException(
                    'Argument to Zend\Soap\AutoDiscover::addFunction should be a valid function name.'
                );
            }

        } else {
            throw new Exception\InvalidArgumentException(
                'Argument to Zend\Soap\AutoDiscover::addFunction should be string or array of strings.'
            );
        }

        return $this;
    }

    /**
     * Generate the WSDL for a service class.
     *
     * @return Wsdl
     */
    protected function _generateClass()
    {
        return $this->_generateWsdl($this->reflection->reflectClass($this->class)->getMethods());
    }

    /**
     * Generate the WSDL for a set of functions.
     *
     * @return Wsdl
     */
    protected function _generateFunctions()
    {
        $methods = array();
        foreach (array_unique($this->functions) as $func) {
            $methods[] = $this->reflection->reflectFunction($func);
        }

        return $this->_generateWsdl($methods);
    }

    /**
     * Generate the WSDL for a set of reflection method instances.
     *
     * @param array $reflectionMethods
     *
     * @return Wsdl
     */
    protected function _generateWsdl(array $reflectionMethods)
    {
        $uri = $this->getUri();

        $serviceName = $this->getServiceName();

        /** @var $wsdl \Zend\Soap\Wsdl */
        $wsdl = new $this->wsdlClass($serviceName, $uri, $this->strategy, $this->classMap);

        // The wsdl:types element must precede all other elements (WS-I Basic Profile 1.1 R2023)
        $wsdl->addSchemaTypeSection();

        $port = $wsdl->addPortType($serviceName . 'Port');
        $binding = $wsdl->addBinding($serviceName . 'Binding', Wsdl::TYPES_NS . ':' . $serviceName . 'Port');

        $wsdl->addSoapBinding($binding, $this->bindingStyle['style'], $this->bindingStyle['transport']);
        $wsdl->addService($serviceName . 'Service', $serviceName . 'Port', Wsdl::TYPES_NS . ':' . $serviceName . 'Binding', $uri);

        foreach ($reflectionMethods as $method) {
            $this->_addFunctionToWsdl($method, $wsdl, $port, $binding);
        }

        return $wsdl;
    }

    /**
     * Add a function to the WSDL document.
     *
     * @param $function \Zend\Server\Reflection\AbstractFunction function to add
     * @param $wsdl     \Zend\Soap\Wsdl WSDL document
     * @param $port     \DOMElement wsdl:portType
     * @param $binding  \DOMElement wsdl:binding
     * @throws Exception\InvalidArgumentException
     *
     * @return void
     */
    protected function _addFunctionToWsdl($function, $wsdl, $port, $binding)
    {
        $uri = $this->getUri();

        // We only support one prototype: the one with the maximum number of arguments
        $prototype = null;
        $maxNumArgumentsOfPrototype = -1;
        /** @var $tmpPrototype \Zend\Server\Reflection\Prototype */
        foreach ($function->getPrototypes() as $tmpPrototype) {
            $numParams = count($tmpPrototype->getParameters());
            if ($numParams > $maxNumArgumentsOfPrototype) {
                $maxNumArgumentsOfPrototype = $numParams;
                $prototype = $tmpPrototype;
            }
        }
        if ($prototype === null) {
            throw new Exception\InvalidArgumentException(
                'No prototypes could be found for the "' . $function->getName() . '" function'
            );
        }

        $functionName = $wsdl->translateType($function->getName());

        // Add the input message (parameters)
        $args = array();
        if ($this->bindingStyle['style'] == 'document') {
            // Document style: wrap all parameters in a sequence element
            $sequence = array();
            /** @var $param Reflection\ReflectionParameter */
            foreach ($prototype->getParameters() as $param) {
                $sequenceElement = array(
                    'name' => $param->getName(),
                    'type' => $wsdl->getType($this->discoveryStrategy->getFunctionParameterType($param))
                );
                if ($param->isOptional()) {
                    $sequenceElement['nillable'] = 'true';
                }
                $sequence[] = $sequenceElement;
            }

            $element = array(
                'name'      => $functionName,
                'sequence'  => $sequence
            );

            // Add the wrapper element part, which must be named 'parameters'
            $args['parameters'] = array('element' => $wsdl->addElement($element));

        } else {
            // RPC style: add each parameter as a typed part
            /** @var $param Reflection\ReflectionParameter */
            foreach ($prototype->getParameters() as $param) {
                $args[$param->getName()] = array(
                    'type' => $wsdl->getType($this->discoveryStrategy->getFunctionParameterType($param))
                );
            }
        }
        $wsdl->addMessage($functionName . 'In', $args);

        $isOneWayMessage = $this->discoveryStrategy->isFunctionOneWay($function, $prototype);

        if ($isOneWayMessage == false) {
            // Add the output message (return value)
            $args = array();
            if ($this->bindingStyle['style'] == 'document') {
                // Document style: wrap the return value in a sequence element
                $sequence = array();
                if ($prototype->getReturnType() != "void") {
                    $sequence[] = array(
                        'name' => $functionName . 'Result',
                        'type' => $wsdl->getType($this->discoveryStrategy->getFunctionReturnType($function, $prototype))
                    );
                }

                $element = array(
                    'name'      => $functionName . 'Response',
                    'sequence'  => $sequence
                );

                // Add the wrapper element part, which must be named 'parameters'
                $args['parameters'] = array('element' => $wsdl->addElement($element));

            } elseif ($prototype->getReturnType() != "void") {
                // RPC style: add the return value as a typed part
                $args['return'] = array(
                    'type' => $wsdl->getType($this->discoveryStrategy->getFunctionReturnType($function, $prototype))
                );
            }

            $wsdl->addMessage($functionName . 'Out', $args);
        }

        // Add the portType operation
        if ($isOneWayMessage == false) {
            $portOperation = $wsdl->addPortOperation(
                $port,
                $functionName,
                Wsdl::TYPES_NS . ':' . $functionName . 'In', Wsdl::TYPES_NS . ':' . $functionName . 'Out'
            );
        } else {
            $portOperation = $wsdl->addPortOperation(
                $port,
                $functionName,
                Wsdl::TYPES_NS . ':' . $functionName . 'In', false
            );
        }
        $desc = $this->discoveryStrategy->getFunctionDocumentation($function);

        if (strlen($desc) > 0) {
            $wsdl->addDocumentation($portOperation, $desc);
        }

        // When using the RPC style, make sure the operation style includes a 'namespace'
        // attribute (WS-I Basic Profile 1.1 R2717)
        $operationBodyStyle = $this->operationBodyStyle;
        if ($this->bindingStyle['style'] == 'rpc' && !isset($operationBodyStyle['namespace'])) {
            $operationBodyStyle['namespace'] = '' . $uri;
        }

        // Add the binding operation
        if ($isOneWayMessage == false) {
            $operation = $wsdl->addBindingOperation($binding, $functionName, $operationBodyStyle, $operationBodyStyle);
        } else {
            $operation = $wsdl->addBindingOperation($binding, $functionName, $operationBodyStyle);
        }
        $wsdl->addSoapOperation($operation, $uri . '#' . $functionName);
    }

    /**
     * Generate the WSDL file from the configured input.
     *
     * @throws Exception\RuntimeException
     *
     * @return Wsdl
     */
    public function generate()
    {
        if ($this->class && $this->functions) {
            throw new Exception\RuntimeException('Can either dump functions or a class as a service, not both.');
        }

        if ($this->class) {
            $wsdl = $this->_generateClass();
        } else {
            $wsdl = $this->_generateFunctions();
        }

        return $wsdl;
    }

    /**
     * Proxy to WSDL dump function
     *
     * @param string $filename
     * @throws \Zend\Soap\Exception\RuntimeException
     *
     * @return bool
     */
    public function dump($filename)
    {
        return $this->generate()->dump($filename);
    }

    /**
     * Proxy to WSDL toXml() function
     *
     * @throws \Zend\Soap\Exception\RuntimeException
     *
     * @return string
     */
    public function toXml()
    {
        return $this->generate()->toXml();
    }

    /**
     * Handle WSDL document.
     *
     * @return void
     */
    public function handle()
    {
        header('Content-Type: text/xml');
        echo $this->toXml();
    }
}
