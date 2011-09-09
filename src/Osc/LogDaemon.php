<?php
/**
 * Simple logger for data from OSC parse
 *
 * PHP Version 5
 *
 * @category   OscPhront
 * @package    Osc
 * @subpackage Protocol
 * @author     Lucas S. Bickel <hairmare@purplehaze.ch>
 * @copyright  2011 Lucas S. Bickel 2011 - Alle Rechte vorbehalten
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GPLv3
 * @link       http://osc.purplehaze.ch
 */

/**
 * Load System_Daemon
 */
require_once "System/Daemon.php";

/**
 * Load Logger
 */
require_once "Log.php";

/**
 * Load Osc Parser
 */
require_once "Osc/Parse.php";

/**
 * All in one logger based on PEAR
 *
 * @class
 * @category   OscPhront
 * @package    Osc
 * @subpackage Protocol
 * @author     Lucas S. Bickel <hairmare@purplehaze.ch>
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GPLv3
 * @link       http://osc.purplehaze.ch
 */
class OSC_LogDaemon
{

    private $_options = array(
        "ip" => "localhost",
        "port" => "10000",
        "loghandler" => "file",
        "logname" => "/var/log/osc.log",
        "logconf" => array(),
        "loglevel" => PEAR_LOG_DEBUG,
    );
    
    /**
     * constructor
     *
     * @param Array $options Configuration
     *
     * @return void
     */
    public function __construct($options = array())
    {

        $this->_options = array_merge($this->_options, $options);

        $this->_osc = new Osc_Parse();
        $this->_log = Log::Factory(
            $this->_options['loghandler'],
            $this->_options['logname'],
            'OSC',
            $this->_options['logconf'],
            $this->_options['loglevel']
        );
        $this->_daemonStart();
        $this->_startListener();
        $this->_main();
    }

    /**
     * start pcntl based daemon
     *
     * @return void
     */
    private function _daemonStart()
    {
        $options = array(
            'appName' => 'osclog',
            'appDir' => dirname(__FILE__),
            'appDescription' => 'logs osc messages',
            'sysMaxExecutionTime' => '0',
            'sysMaxInputTime' => '0',
            'sysMemoryLimit' => '1024M',
            //'appRunAsGID' => 1000,
            //'appRunAsUID' => 1000
        );
        System_Daemon::setOptions($options);
        System_Daemon::start();
    }

    /**
     * start socket lister
     *
     * @return void
     */
    private function _startListener()
    {
        $this->_socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_bind(
            $this->_socket,
            $this->_options['ip'],
            $this->_options['port']
        );
    }

    /**
     * wait for messages and log them
     *
     * @return void
     */
    private function _main()
    {
        while (true) {
            if (socket_recvfrom(
                $this->_socket, 
                $buffer, 
                9999, 
                0, 
                $srcip, 
                $srcport
            )) {
                $this->_osc->setDataString($buffer);
                $this->_osc->parse();
                $result = $this->_osc->getResult();
                $this->_log->log(
                    sprintf(
                        "OSC datagram to %s recieved from %s:%s contained %s",
                        $result['address'],
                        $srcip,
                        $srcport,
                        implode(', ', $result['data'])
                    )
                );
            }
        }
    }

}


new Osc_LogDaemon;
