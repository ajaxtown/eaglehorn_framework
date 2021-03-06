<?php
namespace Eaglehorn;

/**
 * EagleHorn
 * An open source application development framework for PHP 5.4 or newer
 *
 * @package        EagleHorn
 * @author         Abhishek Saha <abhisheksaha11 AT gmail DOT com>
 * @license        Available under MIT licence
 * @link           http://Eaglehorn.org
 * @since          Version 1.0
 * @filesource
 * @desc           Responsible for loading Models, Views, Templates, Controllers and Workers
 */
/**
 * Class Loader
 * @package Eaglehorn
 */
class Loader
{

    /**
     * Holds information about views to be loaded
     * @var array
     */
    public $viewset = array();

    /**
     * Holds information about models loaded
     * @var array
     */
    private $_loaded_models = array();

    /**
     * Holds information about controllers loaded
     * @var array
     */
    private $_loaded_controllers = array();

    /**
     * Holds information about template to be loaded
     * @var array
     */
    public $template = array();

    /**
     * Holds the logger instance
     * @var Logger
     */
    private $logger;

    /**
     * @var string
     */
    private $app_folder = 'application';

    /**
     * @var
     */
    private $loading;
    /**
     * @param Logger $logger
     * @internal param Logger $logger
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }


    /**
     * Responsible for loading workers
     *
     * @param        $worker
     * @param array  $params
     * @param string $method_name
     * @param array  $data
     * @return object|string
     */
    public function worker($worker, $params = array(), $method_name = "", $data = array())
    {
        //Check if this worker has been included
        if (!is_array($worker)) {
            $worker = array($worker);
        }
        $helper = "";
        foreach ($worker as $helper) {

            $helper = ucfirst($helper);
            $file = configItem('site')['workerdir'] . $helper .'/'. $helper . '.php';

            $class = ucfirst(basename($file, '.php'));

            if (!file_exists($file)) {

                $this->logger->error("The worker $file was not found");

            }
            $this->loading[] = 'worker';
            $helper = $this->$class = $this->_createInstance('Eaglehorn\worker\\'.$helper.'\\', $class, $params, $method_name, $data);
        }

        return $helper;
    }


    /**
     * Adds the view in viewset. This viewset is later used for rendering
     *
     * @param        $viewname
     * @param string $data
     * @return View
     */
    public function view($viewname, $data = '')
    {
        if (in_array($viewname, $this->viewset) === false) {
            $this->viewset[] = array($viewname, $data);
            $view = new View(Base::getInstance());
            $this->loaderHooks($view,'pre_view',$class='',$method_name='',$data=array());
            return $view;
        }
    }


    /**
     * Stores template information. This is later used while rendering templates
     *
     * @param string $template
     * @param array  $data
     * @param string $options
     * @return Template
     */
    public function template($template, $data = array(), $options = '')
    {
        $this->template = array($template, $data, $options);
        $template = new Template(Base::getInstance());
        $this->loaderHooks($template,'pre_template',$class='',$method_name='',$data=array());
        return $template;
    }

    /**
     * Creates an instance of the Constructor. Can be initialized along with
     *  - constructor parameters
     *  - method
     *  - method parameters
     *
     * @param string $controller  Controller path
     * @param mixed  $params      Parameters to constructor
     * @param string $method_name Method to be called
     * @param mixed  $data        Parameters to the method
     * @return instance
     */
    public function controller($controller, $params = array(), $method_name = "", $data = array())
    {
        $file = configItem('site')['cust_controller_dir'] . $controller . '.php';


        //base filename of the controller
        $class = basename($file, '.php');

        //Get the namespace
        $ns = $this->get_ns($file,$this->app_folder);

        //Check if this controller has been included
        if (!isset($this->_loaded_controllers[$controller])) {

            if (!file_exists($file)) {

                $this->logger->error("The controller $file was not found");

            } else {

                $this->_loaded_controllers[$controller] = $class;
            }
        }else{
            $class = $this->_loaded_controllers[$controller];
        }
        $this->loading[] = 'controller';
        $method_name = str_replace('-', '', lcfirst(ucwords($method_name, '-')));
        return $this->_createInstance($ns, $class, $params, $method_name, $data);
    }

    /**
     * Creates an instance of the Model. Can be initialized along with
     *  - constructor parameters
     *  - method
     *  - method parameters
     *
     * @param string $model       Model path
     * @param mixed  $params      Parameters to constructor
     * @param string $method_name Method to be called
     * @param mixed  $data        Parameters to the method
     * @return instance
     */
    public function model($model, $params = array(), $method_name = "", $data = array())
    {
        //Check if this model has been included

        if (!isset($this->_loaded_models[$model])) {

            $file = configItem('site')['modeldir'] . implode('/', explode('-', $model)) . '.php';

            $class = basename($file, '.php');

            if (!file_exists($file)) {

                $this->logger->error("The model $file was not found");

            } else {

                $this->_loaded_models[$model] = $class;
            }
        }else{
            $class = $this->_loaded_models[$model];
        }
        $this->loading[] = 'model';
        return $this->_createInstance('application\model\\', $class, $params, $method_name, $data);
    }


    /**
     * Creates an instance of the component which is being loaded.
     *
     * @param $namespace
     * @param $class
     * @param $params
     * @param $method_name
     * @param $data
     * @return object
     */
    private function _createInstance($namespace, $class, $params, $method_name, $data)
    {
        $ns_class = $namespace . $class;

        //create a reflection class so that we can pass parameters to the constructor

        $ref_class = new \ReflectionClass($ns_class);

        if (is_array($params) && sizeof($params) > 0) {

            $instance = $ref_class->newInstanceArgs($params);

        } else {

            $instance = new $ns_class();
        }
        
        $this->logger->info("$namespace object created for $class class");
   
        //call the method along with parameters, if they exist !
        if (method_exists($instance, $method_name))
        {
            if(is_callable(array($instance, $method_name)))
            {
                $last_hook = $this->loading[count($this->loading) - 1];
                $this->loaderHooks($instance,"pre_$last_hook",$class,$method_name,$data);
                call_user_func_array(array($instance, $method_name), $data);
                $this->loaderHooks($instance,"post_$last_hook",$class,$method_name,$data);
            }
        }elseif(is_object($instance)) 
        {
            $last_hook = $this->loading[count($this->loading) - 1];
            $this->loaderHooks($instance,"pre_$last_hook",$class,$method_name,$data);
        }
        else if(strpos($namespace,'controller') > 0)
        {
            header("HTTP/1.0 404 Not Found");
            Base::getInstance()->hook('404',array(
                'file' => $ref_class,
                'controller' => $class,
                'method' => $method_name,
                'message' => '404'
            ));
            die();
        }

        $this->clearCurrentLoader();

        return $instance;
    }

    /**
     * Clears the current Loader
     */
    protected function clearCurrentLoader()
    {
        array_pop($this->loading);
    }

    /**
     * Triggers a hook
     * @param $instance
     * @param $hook_name
     * @param string $class
     * @param string $method_name
     * @param array $data
     */
    protected function loaderHooks($instance,$hook_name,$class='',$method_name='',$data=array())
    {
        $hooks = configItem('hooks');

        if(isset($hooks[$hook_name]) && $hooks[$hook_name]['active'])
        {
            $ns         = "\\application\\".$hooks[$hook_name]['namespace'];
            $hook_class = $hooks[$hook_name]['class'];
            $class_ns   = "$ns\\$hook_class";

            $hook_instance = new $class_ns();
            if($hook_name == 'pre_template')
            {
                $para = array($instance,$class,$method_name,$data);
            }
            else
            {
                $para = array($instance,$data);
            }
            call_user_func_array(array($hook_instance, $hooks[$hook_name]['method']), $para);
        }

    }


    /**
     * Get namespace
     * @param $path
     * @param $app_dir
     * @return mixed
     */
    protected function get_ns($path,$app_dir) {
        //base filename of the controller
        $class = basename($path, '.php');
        preg_match("/(?<=$app_dir).*?(?=$class)/s", $path, $match);
        return str_replace("/","\\",$app_dir.$match[0]);
    }

}