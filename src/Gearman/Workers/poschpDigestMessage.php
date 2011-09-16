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
 * digest and dispatch a package
 *
 * @param GearmanJob $job  actual job instance
 * @param Array      &$log return log messages to server
 *
 * @return void
 *
 * @todo this will need to handle the osc address patterns as per spec
 * @todo i need a way to find all the available _osc jobs from gearman
 */
function poschpDigestMessage($job, &$log)
{
    // @todo remove this dirty hack soon as i have some stable mapping algo
    $_osc_map = array(
        '/ping' => 'oscPing'
    );

    $data = unserialize($job->workload());

    $address = $data['data']['address'];

    file_put_contents('adr', $address, FILE_APPEND);

    switch($address) {

    case "#bundle":
        $log[] = "Handling Bundle";
        break;

    default:
        // @todo fix trailing \0 byte probles in parser where they arise
        $function = $_osc_map[str_replace("\0", '', $address)];
        $log[] = sprintf(
            "Handling Message for %s with %s",
            $address,
            $function
        );

        $gc = new GearmanClient();
        $gc->addServer();
        $gc->doBackground($function, serialize($data));
    }
}

