<?php
/**
 * Gearman Worker for starting socket connetions
 *
 * PHP Version 5
 *
 * @category   PoscHP
 * @package    Server
 * @subpackage Socket
 * @author     Lucas S. Bickel <hairmare@purplehaze.ch>
 * @copyright  2011 Lucas S. Bickel 2011 - Alle Rechte vorbehalten
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GPLv3
 * @link       http://osc.purplehaze.ch
 */

ini_set('include_path', 'src/:'.ini_get('include_path'));
require_once 'Osc/Parse.php';

/**
 * digest and dispatch a package
 *
 * @param GearmanJob $job  actual job instance
 * @param Array      &$log return log messages to server
 *
 * @return void
 */
function poschpSocketStart($job, &$log)
{

    //$workload = $job->workload();

    $ip = '192.168.1.104';
    $port = 10000;

    $log[] = "Creating Socket and starting Listener";

    $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    $r = socket_bind($socket, $ip, $port);

    $osc = new Osc_Parse;

    while (true) {
        if (socket_recvfrom($socket, $b, 9999, 0, $f, $p)) {

            // parse incoming buffer
            $osc->setDataString($b);
            $osc->parse();

            // digest result in background
            $gc = new GearmanClient();
            $gc->addServer();
            $gc->doBackground(
                'poschpDigestMessage',
                serialize(
                    array(
                        'from'=>$f,
                        'data'=>$osc->getResult()
                    )
                )
            );
        }
    }

    return true;
}

