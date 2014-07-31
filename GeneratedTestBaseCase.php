<?php
/**
 * @team ATEAM <ateam@corp.badoo.com>
 * @maintainer Ilya Bogunov <i.bogunov@corp.badoo.com>
 * Базовый класс для авто-генирируемых тестов
 */

class GeneratedTestBaseCase extends GeneratorTestBaseCase//BadooTestCase
{
    static $disabled = array();
    protected $conflicts = false;
    protected $mocked_props;
    protected $construct_params;
    protected $instance_class_for;
    protected $returned;
    protected $mocked_prop_methods;

    public function setUp()
    {
        parent::setUp();

        if ($this->conflicts) {
            if (isset(self::$disabled[$this->conflicts])) {
                $defined_in = self::$disabled[$this->conflicts];
                $this->markTestSkipped("Only one instance allowed of any GeneratedTest conflicts with $this->conflicts declaration in $defined_in");
            } else {
                self::$disabled[$this->conflicts] = get_class($this);
            }
        }
    }

    /**
     * @var ReflectionClass
     */
    protected $class;
    protected $assert_ignored_keys;

    protected $include_line;
    protected $self_file;
    protected $assert_line;

    private $class_expected = '';

    protected $class_for;
    protected $func_name;

    /**
     * @var array|MethodMock[]
     */
    protected $function_catches = array(
        'memcache_set' => true,
        'memcache_get' => true,
    );

    /**
     * @var ReflectionMethod
     */
    protected $func;
    /*DIRTY START*/
    protected $mocks_metadata;
    /*DIRTY END*/
    protected $params;
    protected $class_file_name;

    protected $return = array('user_id' => 1);

    public function returnOnce()
    {
        if (!$this->returned) {
            $this->returned = true;
            return $this->return;
        }

        return null;
    }

    protected function catchFunction($function, $only_from_function = false, $call_parents = array('MCache', 'core\\Memcache'))
    {
        // special hack to ignore some calls
        unset($GLOBALS["skip_results_for"][$function]);
        $code = '$count = count($params);
            ' . ($only_from_function ? '
                $class = "' . $this->class_for . '";
                $function_lookup = "' . $this->func_name . '";
                $function = "' . $function . '";
                $call_parents = ' . var_export($call_parents, true) . ';
                $trace = debug_backtrace();
                
                $allowed = false;
                $count_trace = count($trace);
                for ($i = 0; $i < $count_trace; $i++) {
                    $correct_trace_no = isset($trace[$i]["class"]) && isset($trace[$i-1]["class"]) && $trace[$i]["class"] == $class && $trace[$i]["function"] == $function_lookup;
                    if ($correct_trace_no && in_array($trace[$i-1]["class"], $call_parents)) {
                        $allowed = true;
                    }
                }

                $GLOBALS["skip_results_for"][$function][] = $allowed;
            ' : '') . '
                            
            if ($count == 1) {
                $x = {__ORIGINAL__}($params[0]);
            } else if ($count == 2) {
                $x = {__ORIGINAL__}($params[0], $params[1]);
            } else if ($count == 3) {
                $x = {__ORIGINAL__}($params[0], $params[1], $params[2]);
            } else if ($count == 4) {
                $x = {__ORIGINAL__}($params[0], $params[1], $params[2], $params[3]);
            } else if ($count == 5) {
                $x = {__ORIGINAL__}($params[0], $params[1], $params[2], $params[3], $params[4]);
            } else {
                die("add $count $params to catchFunctionMoc;");
            }
             
            return $x;';
        $mock = MethodMock::interceptFunctionByCode(
            $function,
            $code
        );

        return $mock;
    }

    /**
     * @return mixed
     */
    protected function runFunctionCode($mock_props = true)
    {
        $func_name = $this->func_name;

        $this->func->setAccessible(true);

        if ($this->func->isStatic()) {
            return $this->func->invokeArgs(null, $this->params);
        } else {
            if ($this->construct_params) {
                $object = $this->class->newInstanceArgs($this->construct_params);
            } else {
                $constructMethod = $this->class->hasMethod('__construct') ? $this->class->getMethod('__construct') : null;
                if ($constructMethod && ($constructMethod->isProtected() || $constructMethod->isPrivate())) {
                    if ($this->class->hasMethod('getInstance')) {
                        $class = $this->class_for;
                        $object = $class::getInstance();
                    } else if ($this->class->hasMethod('instance')) {
                        $class = $this->class_for;
                        $object = $class::instance();
                    }
                } else {
                    $class = $this->instance_class_for ? : $this->class_for;
                    $object = new $class();
                }
            }

            if ($func_name == '__construct') {
                return $object;
            }

            if ($mock_props) {
                /**
                 * @var $props ReflectionProperty[] 
                 */
                $props = $this->class->getProperties();

                foreach ($props as $prop) {
                    $prop->setAccessible(true);
                    $comment = $prop->getDocComment();
                    
                    // special case of metmagick props generation(
                    $prop_name = $prop->getName();
                    if (isset($this->mocked_props[$prop_name])) {
                        $prop->setValue($object, $this->mocked_props[$prop_name]);
                    } else {
                        if ($prop_name == 'requestDescription') {
                            $meta_props = $prop->getValue($object);

                            foreach ($meta_props as $key => $opts) {
                                if (isset($opts['type']) && $opts['type'] == 4) {
                                    $object->$key = array();
                                } else {
                                    $object->$key = 1;
                                }
                            }
                        }

                        $old_value = $prop->getValue($object);

                        if (is_object($old_value) && strpos(get_class($old_value), 'Mock') !== false) {
                            continue;
                        }

                        if (preg_match('~@var\s*([a-zA-Z_]*)~', $comment, $matches)) {
                            $class = $matches[1];
                            if ($class == 'string') {
                                $valueMock = 'string';
                            } else if ($class == 'int' || $class == 'interger') {
                                $valueMock = 1;
                            } else if ($class == 'array') {
                                $valueMock = array();
                            } else {
                                $methods = array();

                                $valueMock = $this->getMock($class, array(), array(), $class . 'ValueMock', false, false, true, false);

                                if (isset($this->mocked_prop_methods[$prop_name])) {
                                    foreach ($this->mocked_prop_methods[$prop_name] as $method => $return) {
                                        $valueMock->expects($this->any())->method($method)->will($this->returnValue($return));
                                    }
                                }

                                if ($class == 'User') {
                                    $valueMock->expects($this->any())->method('setSlaveConnection')->will($this->returnValue(true));
                                    $valueMock->expects($this->any())->method('canWrite')->will($this->returnValue(true));
                                    $valueMock->db_slave = $this->getMock('DB', array(), array(), '', false, false, true, false);
                                    $valueMock->data['created'] = '11111';
                                    $valueMock->data['user_id'] = 1;
                                    $valueMock->data['photoset_personal_id'] = 1;
                                }
                                
                                // file include failed
                                if (is_array($valueMock)) {
                                    if ($this instanceof GeneratorTestBaseCase) {
                                        $this->addIInclude($this->self_file, $this->include_line, $class);
                                    }
                                    throw new \Exception("Added include of required class $class");
                                }
                            }
                            $prop->setValue($object, $valueMock);
                        } else {
                            if (empty($old_value)) {
                                $prop->setValue($object, 1);
                            }
                        }
                    }
                }
            }

            return $this->func->invokeArgs($object, $this->params);
        }
    }

    protected function assertOkEnought($expected, $got)
    {
        if (is_array($expected) && isset($expected['resource']) && is_resource($got)) {
            $this->assertEquals($expected['resource'], get_resource_type($got));
        } else if (is_array($expected) && isset($expected['class']) && is_object($got)) {
            $this->assertEquals($expected['class'], get_class($got));
            $got_array = (array)$got;
            $this->assertOkEnought($expected['array'], $got_array);
        } else if (is_array($expected)) {
            foreach ($expected as $k => $expectedV) {
                $cleanK = $this->cleanup($k);
                $got = $this->cleanup($got);
                if (isset($this->assert_ignored_keys[$cleanK])) {
                    // special case of ignored keys for memcache host_port change
                } else {
                    $this->assertOkEnought($expectedV, $this->cleanup($got[$cleanK]));
                }
            }
        } else {
            if ($expected != $this->cleanup($got)) {
                $x = 123123;
            }
            $this->assertEquals($expected, $this->cleanup($got));
        }
    }

    protected function cleanup($got)
    {
        if (is_array($got)) {
            foreach ($got as $k => $v) {
                $cleanK = $this->cleanup($k);
                if ($cleanK != $k) {
                    unset($got[$k]);
                    $got[$cleanK] = $this->cleanup($v);
                } else {
                    $got[$k] = $this->cleanup($v);
                }
            }
        } else if (is_string($got)) {
            $got = str_replace(\core\Memcache::getGlobalPrefix(), '', $got);
        }
        return $got;
    }

    /**
     * @param $mocks
     * @return mixed
     */
    protected function prepareMocks($mocks)
    {
        $return = array();
        foreach ($mocks as $class => $functions) {
            foreach ($functions as $function => $expected) {
                if ($class) {
                    $return[$class][$function] = MethodMock::mockMethodResult($class, $function, $expected);
                } else {
                    $return[$class][$function] = MethodMock::interceptFunction($function, $expected);
                }
            }
        }

        return $return;
    }

    /**
     * @return MethodMock
     */
    protected function disablePageConstruct()
    {
        $mock = MethodMock::interceptMethodByCode(
            'Application',
            '__construct',
            '
            throw new \Exception("Page construct forbidden!");
             
            return $x;'
        );
        return $mock;
    }

    protected function getClassMetadata()
    {
        try {
            /*DIRTY END*/
            $refl = new ReflectionClass($this->class_for);

            $this->class = $refl;

            $this->func = $refl->getMethod($this->func_name);
            $this->class_file_name = $class_file_name = $refl->getFileName();
            /*DIRTY START*/
        } catch (Exception $e) {
            if (preg_match('~Class ([\\a-zA-Z_]*) does not exist~', $e->getMessage(), $matches)) {
                // add include
                if ($this instanceof GeneratorTestBaseCase) {
                    $this->addIInclude($this->self_file, $this->include_line, $matches[1]);
                }
            }
            throw $e;
        }
    }

    /**
     * @param $asserts
     * @param $assert_file
     * @param $assert_line
     * @param array $ignore_keys
     */
    protected function runAsserts($asserts, $ignore_keys = array('host_port'))
    {
        $this->assert_ignored_keys = array_flip($ignore_keys);

        foreach ($this->function_catches as $function_lookup => $func_asserts) {
            /**
             * @var $mock MethodMock
             */
            $mock = $this->function_catches[$function_lookup];
            $mock_params = $mock->getCalledParams();
            $mock_results = $mock->getCalledResults();

            if (isset($GLOBALS["skip_results_for"][$function_lookup])) {
                foreach ($GLOBALS["skip_results_for"][$function_lookup] as $k => $res) {
                    if (!$res) {
                        unset($mock_params[$k]);
                        unset($mock_results[$k]);
                    }
                }

                $mock_params = array_values($mock_params);
                $mock_results = array_values($mock_results);
                unset($GLOBALS["skip_results_for"][$function_lookup]);
            }

            if (empty($asserts[$function_lookup]) && $this->self_file && $this->assert_line) {
                if (empty($mock_params)) {
                    $this->assertFalse(true, "$function_lookup haven`t been called!");
                }
                if ($this instanceof GeneratorTestBaseCase) {
                    $this->generateTestAsserts($mock_params, $mock_results, $this->self_file, $this->assert_line, $function_lookup);
                }

                $this->assertFalse(true, 'generated asserts, CONTINUE!');
            }
            $func_asserts = $asserts[$function_lookup];

            foreach ($func_asserts['params'] as $k => $expected) {
                // special case: Mcache::get does single key get, and Memcache::instance()->get() does array-get with single key
                if ($function_lookup == 'memcache_get'
                    // signle key get
                    && is_array($func_asserts['params'][$k][1]) && count($func_asserts['params'][$k][1]) == 1 && !is_array($mock_params[$k][1])) {
                    if (($mock_results[$k] === false && empty($func_asserts['params'][$k]['results']))
                        || ($mock_results[$k] === true && $func_asserts['params'][$k]['results'][$mock_params[$k][1]] == true)) {
                        // assert same
                    } else {
                        $this->assertOkEnought($expected, $mock_params[$k]);
                        $this->assertEquals($func_asserts['results'][$k], $mock_results[$k]);
                    }
                } else if ($function_lookup == 'memcache_get'
                    // signle key get
                    && !is_array($func_asserts['params'][$k][1]) && is_array($mock_params[$k][1]) && count($mock_params[$k][1]) == 1) {
                    if (($func_asserts['results'][$k] === false && empty($mock_results[$k]))
                        || ($func_asserts['results'][$k] === true && $mock_results[$k][$func_asserts['params'][$k][1]] == true)) {
                        // assert same
                    } else {
                        /**
                         * @var $real_key string
                         */
                        $real_key = $expected[1];
                        if (!is_array($real_key) && is_array($mock_params[$k][1]) && !is_array($func_asserts['results'][$k]) && is_array($mock_results[$k])) {
                            $expected[1] = array($expected[1]);
                            $func_asserts['results'][$k] = array($real_key => $func_asserts['results'][$k]);
                        }
                        $this->assertOkEnought($expected, $mock_params[$k]);
                        $this->assertOkEnought($func_asserts['results'][$k], $mock_results[$k]);
                    }
                } else {
                    $this->assertOkEnought($expected, $mock_params[$k]);
                    $this->assertEquals($func_asserts['results'][$k], $this->cleanup($mock_results[$k]));
                }
            }
        }
    }

    /**
     * @return int|string
     */
    protected function prepareFunctionCatches($only_from_function = false)
    {
        foreach ($this->function_catches as $function_lookup => $_) {
            $this->function_catches[$function_lookup] = $this->catchFunction($function_lookup, $only_from_function);
        }
    }

    protected function setMockedProps($props)
    {
        $this->mocked_props = $props;
    }

    protected function disableScriptFramework()
    {
        if (!defined('SCRIPT_NAME')) {
            define('SCRIPT_NAME', 'test');
        }
        MethodMock::mockMethodResult('Script', 'cycle', 1);
        MethodMock::mockMethodResult('Script', 'log', 1);
    }

    protected function mockUserGet()
    {
        MethodMock::mockMethodResult('User', 'User', $this->getMock('User', array(), array(), '', false, false, true, false));
    }

    protected function mockTime()
    {
        MethodMock::interceptFunction('time', 1111);
        MethodMock::interceptFunction('mt_rand', 1);
        MethodMock::interceptFunction('rand', 1);
    }

    protected function setMockedPropMethods($array)
    {
        $this->mocked_prop_methods = $array;
    }
}
