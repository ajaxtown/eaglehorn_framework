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
 * @desc           Fires up the application.
 */

class bootstrap
{

    var $route_callback;

    /**
     * @param Base $base
     * @internal param $config
     */
    function __construct($base)
    {
        $this->route_callback = Router::execute($base);

        if(is_callable($this->route_callback[0]))
        {
            call_user_func_array($this->route_callback[0],$this->route_callback[1]);
        }
        else
        {
            $base->load->controller($this->route_callback[0], array(), $this->route_callback[1], $this->route_callback[2]);
        }

    }

}

$base = new Base($extended = false);
require root . 'application/router.php';
new bootstrap($base);