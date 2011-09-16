<?php
/**
 * Gearman Worker for simple osc ping message
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

require_once 'lib/OSC.php';

/**
 * builtin Osc Ping ftw
 *
 * @param GearmanJob $job  actual job instance
 * @param Array      &$log return log messages to server
 *
 * @return void
 */
function oscPing($job, &$log)
{
    $data = unserialize($job->workload());

    $log[] = sprintf("got ping from %s", $data['from']);

    $client = new OSCClient();
    $client->set_destination($data['from'], 10000);

    $client->send(
        new OSCMessage(
            "/pong",
            array(
                new Timetag,
                "PoscHP alpha"
            )
        )
    );
}
