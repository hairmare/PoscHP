<?php
/**
 * Gearman Worker for handling and dispatching an osc message
 *
 * PHP Version 5
 *
 * @category   PoscHP
 * @package    Server
 * @subpackage OSC
 * @author     Lucas S. Bickel <hairmare@purplehaze.ch>
 * @copyright  2011 Lucas S. Bickel 2011 - Alle Rechte vorbehalten
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GPLv3
 * @link       http://osc.purplehaze.ch
 */

/**
 * Gearman Manager interface from PEAR extension 
 * 
 * this has support for getting a list of all the workers
 */
require_once 'Net/Gearman/Manager.php';

/**
 * digest and dispatch a package
 *
 * @param GearmanJob $job  actual job instance
 * @param Array      &$log return log messages to server
 *
 * @return void
 *
 * @todo this will need to handle the osc address patterns as per spec
 * @todo refactor me into heaps of classes
 */
function poschpDigestMessage($job, &$log)
{
    // get workers
    $ngm = new Net_Gearman_Manager('127.0.0.1:4730');
    $workers = $ngm->workers();
    $ngm->disconnect();

    // derive osc message names
    foreach ($workers AS $worker) {
        foreach ($worker['abilities'] AS $ability) {
            if (substr($ability, 0, 3) == 'osc') {
                $address = substr($ability, 3);
                $address = preg_replace('/([A-Z]{1})/', '/$1',  $address);
                $address = strtolower($address);
                $_osc_map[$address] = $ability;
            }
        }
    }

    $data = unserialize($job->workload());
    $address = $data['data']['address'];

    $gc = new GearmanClient();
    $gc->addServer();


    switch($address) {

    case "#bundle":
        $log[] = "Handling Bundle";
        $gc->doBackground('poschpHandleBundle', serialize($data));
        break;

    default:
        // @todo fix trailing \0 byte probles in parser where they arise
        $function = $_osc_map[str_replace("\0", '', $address)];
        $log[] = sprintf(
            "Handling Message for %s with %s",
            $address,
            $function
        );
        $delegator_stack[$function] = $data;
        $gc->doBackground($function, serialize($data));
    }
}
