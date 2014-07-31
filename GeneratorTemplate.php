<?php
/**
 * @team ATEAM <ateam@corp.badoo.com>
 * @maintainer Ilya Bogunov <i.bogunov@corp.badoo.com>
 */
require_once __DIR__ . '/../../../test.helper.php';

class GeneratorTemplate extends GeneratedTestBaseCase
{

    public function testMemcache()
    {
        //$this->disableScriptFramework();
        //$this->mockUserGet();
        $this->mockTime();
        /*DIRTY START*/
        if (get_class($this) == 'GeneratorTemplate') {
            $this->markTestSkipped('GeneratorTemplate - unrunnable! Rename class to be run!');
        }
        if (!static::$run_once) {
            static::$run_once = true;
        } else {
            $this->markTestSkipped('Only one functon can be generated at time');
        }
        /*DIRTY END*/

        $this->function_catches = array(
            'memcache_get' => true,
            'memcache_set' => true,
        );
        
        $this->func_name = 'functionName';
        $this->class_for = 'className';
        /*DIRTY START*/
        //$mock = $this->disablePageConstruct();

        // INCLUDES
        //try {
        //    runkit_constant_remove('XSS_AUTOESCAPE');
        /*DIRTY END*/
            /*DIRTY START*/ $this->include_line = __LINE__ + 1; /*DIRTY END*/


        /*DIRTY START*/
        //} catch (\Exception $e) {
        //    if ($e->getMessage() != "Page construct forbidden!") {
        //        throw $e;
        //    }
        //}

        //$mock->revertIt();
        /*DIRTY END*/
        
        /*DIRTY START*/$this->self_file = __FILE__; /*DIRTY END*/
        
        $this->getClassMetadata();

        $params = array();
        /*DIRTY START*/ $this->params_line = $params_line = __LINE__; /*DIRTY END*/
        
        ksort($params); $this->params = $params;

        $params = array();
        /*DIRTY START*/        $this->construct_line = __LINE__; /*DIRTY END*/

        ksort($params); $this->construct_params = $params;


        /*DIRTY START*/$mocks = array(); $this->mocks_line = __LINE__; /*DIRTY END*/

        /*DIRTY START*/
        $this->mocks_metadata = array($mocks, $this->self_file, $this->mocks_line);
        /*DIRTY END*/

        $this->prepareMocks($mocks);

        /*DIRTY START*/
        $run_succesfull = false;
        /*DIRTY END*/

        $this->prepareFunctionCatches(true);

        /*DIRTY START*/
        try {
            /*DIRTY END*/
            $return = $this->runFunctionCode($this->func_name, $params);
            // in case something is done in transaction commit
            ConnectionManager::commit();
            /*DIRTY START*/
            $run_succesfull = true;
        } catch (\Exception $e) {
            $this->generateMocksCode($e, $this->self_file);
            throw new \Exception('Fixed', null , $e);
        }
        /*DIRTY END*/

        $asserts = array();
        /*DIRTY START*/
        if ($run_succesfull) {
            /*DIRTY END*/
            // ASSERT
            /*DIRTY START*/ $this->assert_line = __LINE__; /*DIRTY END*/

            $this->runAsserts($asserts);
            /*DIRTY START*/
        }
        /*DIRTY END*/

        /*DIRTY START*/ $this->doCleanings($run_succesfull, $asserts, __LINE__ + 2); /*DIRTY END*/
    }
}
