<?php
/**
 * @team ATEAM <ateam@corp.badoo.com>
 * @maintainer Ilya Bogunov <i.bogunov@corp.badoo.com>
 * Класс-генератор тестов.
 */

class GeneratorTestBaseCase extends BadooTestCase
{
    protected $cleanup_performed = false;
    static $run_once = false;

    /**
     * @var ReflectionClass
     */
    protected $class;
    protected $assert_ignored_keys;

    protected $include_line;
    protected $mocks_line;
    protected $self_file;
    protected $assert_line;
    protected $params_line;
    protected $construct_line;

    private $class_expected = '';

    protected $class_for;
    protected $func_name;

    /**
     * @var array|MethodMock
     */
    protected $function_catches = array(
        'memcache_set' => true,
        'memcache_get' => true,
    );

    /**
     * @var ReflectionMethod
     */
    protected $func;
    protected $mocks_metadata;
    protected $params;
    protected $class_file_name;

    // GENERATORS PART

    protected function addParamMock($file_name, $line, $param_num, $class_name)
    {
        $file = file($file_name);
        $file[$line] .= "\$params[$param_num] = \$this->getMock('$class_name', array(), array(), '${class_name}Mock', false, false, true, false);\n";
        if ($class_name == 'User') {
            $file[$line] .= "\$params[$param_num]->expects(\$this->any())->method('getId')->will(\$this->returnValue('1'));\n";
            $file[$line] .= "\$params[$param_num]->expects(\$this->any())->method('id')->will(\$this->returnValue('1'));\n";
            $file[$line] .= "\$params[$param_num]->data['created'] = 1;\n";
        }
        file_put_contents($file_name, implode('', $file));
    }

    /**
     * @param $param_num
     * @param array|string|int $value
     * @param bool $add_to_constuctor
     */
    protected function addParamMockValue($param_num, $value = 1, $add_to_constuctor = false)
    {
        // [TODO] drop old value param
        $file = file($this->self_file);
        $value = str_replace(array("\n", "\r"), '', var_export($value, true));
        $file[$add_to_constuctor ? $this->construct_line : $this->params_line] .= "\$params[$param_num] = $value;\n";
        file_put_contents($this->self_file, implode('', $file));
    }

    protected function addVariableMockParam($variable_name, $class_file_name, $line, $mocks_metdata, $param_name, $param_value = 1)
    {
        // find place there variable is initialized

        $file = file($class_file_name);

        // static call
        $assigment_regexp = "~\\\$$variable_name\s*=\s*([a-z_A-Z]*)::([a-z_A-Z]*)~";
        
        // some_parameter in function definition
        $in_param_regexp = "~.*function.*$this->func_name.*\((.*\\\$$variable_name.*)\)$~";

        $found = false;

        $line_from = $line - 1;

        while (!$found && $line_from > 0) {
            if (preg_match($assigment_regexp, $file[$line_from], $static_assigment_match)) {
                $class = $static_assigment_match[1];
                $method = $static_assigment_match[2];

                $this->generateMockArrayParam($mocks_metdata, $param_name, $param_value, $class, $method);

                $found = true;
            }
            if (preg_match($in_param_regexp, $file[$line_from], $in_param_match)) {
                $params_line = $in_param_match[1] . ' ';
                if (preg_match_all('~\$([a-zA-Z_]*)[,\s=]~', $params_line, $matches)) {
                    foreach ($matches[1] as $k => $value) {
                        if ($value == $variable_name) {
                            $found = true;

                            $this->generateParamArrayParam($k, $param_name, $param_value);
                        }
                    }
                }
            }

            $line_from--;
        }

        if (!$found) {
            return false;
        }

        return true;
    }

    protected function addForeachVariableMock($variable_name, $class_file_name, $line)
    {
        // find variable assigment op
        $assigment_regexp = "~\\\$$variable_name\s*=\s*\\\$([a-zA-Z_]*)->([a-zA-Z_]*)\(~";

        $in_param_regexp = "~.*function.*$this->func_name.*\((.*\\\$$variable_name.*)\)\s*{?\s*$~";

        $file = file($class_file_name);

        $found = false;

        $line_from = $line - 1;

        while (!$found && $line_from > 0) {
            if (preg_match($assigment_regexp, $file[$line_from], $static_assigment_match)) {
                $variable = $static_assigment_match[1];
                $method = $static_assigment_match[2];

                $class = $this->getVariableClassAt($variable, $line_from, $class_file_name);;

                $this->makeMockAsArray($class, $method, $variable);

                $found = true;
            }
            if (preg_match($in_param_regexp, $file[$line_from], $in_param_match)) {
                $params_line = $in_param_match[1] . ' ';
                if (preg_match_all('~\$([a-zA-Z_]*)[,\s=]~', $params_line, $matches)) {
                    foreach ($matches[1] as $k => $value) {
                        if ($value == $variable_name) {
                            $found = true;

                            $this->addParamMockValue($k + 1, array(1));
                        }
                    }
                }
            }
            $line_from--;
        }

        if (!$found) {
            return false;
        }

        return true;
    }

    protected function getVariableClassAt($variable, $line_from, $class_file_name)
    {
        // apply patch
        $file_origin = $file = file($class_file_name);

        $rand = rand();

        $generator_file = "/dev/shm/class$rand";

        $file[$line_from] .= "file_put_contents('$generator_file', get_class(\$$variable));die();\n";

        file_put_contents($class_file_name, implode('', $file));

        // reload class & run code
        $this->reRun();
        // revert patch
        file_put_contents($class_file_name, implode('', $file_origin));

        $class = file_get_contents($generator_file);

        unlink($generator_file);

        return $class;
    }

    /**
     * @param $mocks_metdata
     * @param $param_name
     * @param $param_value
     * @param $class
     * @param $method
     */
    protected function generateMockArrayParam($mocks_metdata, $param_name, $param_value, $class, $method)
    {
        list($mocks, $mocks_file, $mocks_line) = $mocks_metdata;
        if (!isset($mocks[$class][$method])) {
            $mocks[$class][$method] = array();
        }
        $mocks[$class][$method][$param_name] = $param_value;

        $this->putMocks($mocks, $mocks_file, $mocks_line);
    }

    protected function makeMockAsArray($class, $method, $variable)
    {
        list($mocks, $mocks_file, $mocks_line) = $this->mocks_metadata;
        if (!isset($mocks[$class][$method])) {
            $mocks[$class][$method] = array();
        }
        $mocks[$class][$method] = array(1);

        $this->putMocks($mocks, $mocks_file, $mocks_line);
    }

    /**
     * @param $mocks
     * @param $mocks_file
     * @param $mocks_line
     */
    protected function putMocks($mocks, $mocks_file, $mocks_line)
    {
        $serialized = addslashes(serialize($mocks));
        $mock_file = file($mocks_file);
        $mock_file[$mocks_line - 1] = "\$mocks = unserialize(\"$serialized\"); \$this->mocks_line = __LINE__;\n";
        file_put_contents($mocks_file, implode('', $mock_file));
    }

    /**
     * @param $mocks
     * @param $mocks_file
     * @param $mocks_line
     */
    protected function putMocksAsArray()
    {
        list($mocks, $mocks_file, $mocks_line) = $this->mocks_metadata;
        $mock_file = file($mocks_file);
        $mock_file[$mocks_line - 1] = "\$mocks = " . var_export($mocks, true) . ";
        
        /*DIRTY START*/ \$this->cleanup_performed = true; /*DIRTY END*/
        
        \n";
        file_put_contents($mocks_file, implode('', $mock_file));
    }

    protected function reRun()
    {
        //$cmd = implode(' ', $GLOBALS['argv']);
        $cmd = escapeshellcmd("phpunit " . __FILE__);
        exec($cmd);
    }

    protected function generateTestAsserts($getCalledParams, $getCalledResults, $assert_file, $assert_line, $function_lookup)
    {
        $file = file($assert_file);

        $full_line = $file[$assert_line - 1];

        $full_line .= '$asserts[\'' . $function_lookup . '\'][\'results\'] = ' . var_export($this->prepareAssertParam($getCalledResults), true) . ';';

        foreach ($getCalledParams as $k => $toBeExpected) {
            $full_line .= '$asserts[\'' . $function_lookup . '\'][\'params\'][' . $k . '] = ' . var_export($this->prepareAssertParam($toBeExpected), true) . ';';
        }

        $file[$assert_line - 1] = $full_line;

        file_put_contents($assert_file, implode('', $file));
    }

    protected function prepareAssertParam($toBeExpected)
    {
        if (is_resource($toBeExpected)) {
            return array('resource' => get_resource_type($toBeExpected));
        } else if (is_object($toBeExpected)) {
            return array('class' => get_class($toBeExpected), 'array' => $this->prepareAssertParam((array)$toBeExpected));
        } else if (is_array($toBeExpected)) {
            $full = array();
            foreach ($toBeExpected as $k => $value) {
                $full[$this->cleanup($k)] = $this->prepareAssertParam($value);
            }
            return $full;
        } else {
            return $this->cleanup($toBeExpected);
        }
    }

    /**
     * @param $e
     * @param $params_file
     * @param $params_line
     * @param $class_file_name
     * @param $refl
     * @throws Exception
     */
    protected function generateMocksCode(Exception $e, $params_file)
    {
        $params_line = $this->params_line;
        $constructs_line = $this->construct_line;
        $trace = $e->getTrace();

        $run_else = false;

        if (preg_match('~Missing argument (\d+) for ([\\\\a-zA-Z]+)::([a-zA-Z0-9_]+)~', $e->getMessage(), $matches)) {
            $param_num = $matches[1];
            $class_name = $matches[2];

            if ($class_name != $this->class_for && $this->class->getParentClass()->getName() != $class_name) {
                throw $e;
            }
            $function_name = $matches[3];
            if ($function_name == '__construct' || $function_name == $this->class_for) {
                $this->addParamMockValue($param_num, 1, true);
            } else if ($function_name != $this->func_name) {
                throw $e;
            } else {
                $this->addParamMockValue($param_num);
            }
        } else if (preg_match(
            '~Argument (\d+) passed to ([\\\\a-zA-Z]+)::([a-zA-Z_0-9]+)\(.* must implement interface ([a-zA-Z_\\\\]+), none given~',
            $e->getMessage(),
            $matches
        )) {
            $param_num = $matches[1];
            $class_name_should = $matches[4];
            $class_name_failed = $matches[2];
            $function_failed = $matches[3];
            if ($class_name_failed == $this->class_for) {
                if ($this->func_name == $function_failed) {
                    $this->addParamMock($params_file, $params_line, $param_num, $class_name_should);
                } elseif ($function_failed == '__construct') {
                    $this->addParamMock($params_file, $constructs_line, $param_num, $class_name_should);
                }
            } else {
                throw $e;
            }
        } else if (preg_match(
            '~Argument (\d+) passed to ([\\\\a-zA-Z]+)::([a-zA-Z_0-9]+)\(.* must be an instance of ([a-zA-Z_\\\\]+), none given~',
            $e->getMessage(),
            $matches
        )) {
            $param_num = $matches[1];
            $class_name_should = $matches[4];
            $class_name_failed = $matches[2];
            $function_failed = $matches[3];
            if ($class_name_failed == $this->class_for) {
                if ($this->func_name == $function_failed) {
                    $this->addParamMock($params_file, $params_line, $param_num, $class_name_should);
                } elseif ($function_failed == '__construct') {
                    $this->addParamMock($params_file, $constructs_line, $param_num, $class_name_should);
                }
            } else {
                throw $e;
            }
        } else if (preg_match('~Argument (\d+) passed to .* must be an array, none given~', $e->getMessage(), $matches)) {
            $param_num = $matches[1];
            $value = array();
            $this->addParamMockValue($param_num, $value);
        } else if (preg_match('~Undefined index: (.*)~', $e->getMessage(), $matches) && isset($trace[1]['class']) && $trace[1]['class'] == $this->class_for) {
            $param_name = $matches[1];

            $line = $trace[0]['line'];
            $file = file($this->class_file_name);
            $regexp = "~\\\$([a-zA-Z_]*)\\[([\"\'])$param_name\\2\\]~";
            if (preg_match($regexp, $file[$line - 1], $line_matches)) {
                $variable_name = $line_matches[1];

                if (!$this->addVariableMockParam($variable_name, $this->class_file_name, $line, $this->mocks_metadata, $param_name)) {
                    throw new \Exception("Couldnt add param $param_name to var $variable_name to file $this->class_file_name nock", 0, $e);
                } else {
                    echo "Added param $param_name to var $variable_name to file $this->class_file_name on line $line mock\n";
                }
            } else {
                $run_else = true;
            }
        } else if (preg_match('~Invalid argument supplied for foreach()~', $e->getMessage(), $matches)) {
            $trace = $e->getTrace();

            if ($trace[0]['file'] == $this->class_file_name) {
                $line = $trace[0]['line'];
                $file = file($this->class_file_name);
                $foreach_regexp = "~foreach\s*\(\\\$([a-zA-Z_]*) as~";
                if (preg_match($foreach_regexp, $file[$line - 1], $line_matches)) {
                    $variable_name = $line_matches[1];

                    if (!$this->addForeachVariableMock($variable_name, $this->class_file_name, $line)) {
                        throw new \Exception("Couldnt add array $variable_name to file $this->class_file_name mock", 0, $e);
                    } else {
                        echo "Added param \n";
                    }
                } else {
                    throw $e;
                }
            }
        } else {
            $run_else = true;
        }

        if ($run_else) {
            // find our class in trace and mock next called class return
            $found = $i = 0;
            while ($i < count($trace) && !$found) {
                if (isset($trace[$i]['class']) && $trace[$i]['class'] == $this->class_for) {
                    if ($trace[$i]['function'] == $this->func_name) {
                        if (isset($trace[$i - 1]['class'])) {
                            $this->makeMockAsArray($trace[$i - 1]['class'], $trace[$i - 1]['function'], '');
                        } else {
                            $this->makeMockAsArray('', $trace[$i - 1]['function'], '');
                        }
                    } else {
                        $this->makeMockAsArray($trace[$i]['class'], $trace[$i]['function'], '');
                    }
                    $found = true;
                }
                $i++;
            }

            if (!$found) {
                throw $e;
            }
        }

        return true;
    }

    protected function addIInclude($include_file, $include_line, $class)
    {
        $list = explode('\\', $class);
        $class = array_pop($list);
        chdir(PHPWEB_PATH_SYSTEM);
        $last_string = exec('git grep "class ' . $class . '" ' . PHPWEB_PATH_SYSTEM);

        list($path) = explode(':', $last_string);

        $file = file($include_file);

        $dir = dirname($path);

        $file[$include_line - 1] .= "chdir(PHPWEB_PATH_SYSTEM . '$dir'); include_once(PHPWEB_PATH_SYSTEM . '$path');\n";

        file_put_contents($include_file, implode('', $file));
    }

    protected function cleanupDirtySelf($last_line)
    {
        list($mocks, $mocks_file) = $this->mocks_metadata;
        $file = file($mocks_file);

        $content = implode('', array_slice($file, 0, $last_line));
        $other_part = implode('', array_slice($file, $last_line));

        $content2 = preg_replace('~/\*DIRTY START\*/.*?/\*DIRTY END\*/~s', '', $content);
        file_put_contents($mocks_file, $content2 . $other_part);
        if (count($file) - $last_line < 5) {
            exec('phpcf apply-git ' . $mocks_file);
        }
    }

    /**
     * @param $run_succesfull
     * @param $asserts
     */
    protected function doCleanings($run_succesfull, $asserts, $last_line)
    {
        if (get_class($this) != 'GeneratorTemplate' && $run_succesfull && !empty($asserts) && !empty($this->mocks_metadata)) {
            if (!$this->cleanup_performed) {
                $this->putMocksAsArray();
            } else {
                $this->cleanupDirtySelf($last_line);
            }
        }
    }

    private function generateParamArrayParam($k, $param_name, $param_value)
    {
        $k++;
        $file = file($this->self_file);
        $value = str_replace(array("\n", "\r"), '', var_export($param_value, true));
        $file[$this->params_line + count($this->params)] .= "\$params[$k]['$param_name'] = $value;\n";
        file_put_contents($this->self_file, implode('', $file));
    }
}
