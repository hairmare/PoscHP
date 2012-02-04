<?php
/**
 * Gearman Worker for handling osc bundles
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
 * handle a bundle
 *
 * @param GearmanJob $job  actual job instance
 * @param Array      &$log return log messages to server
 *
 * @return void
 */
function poschpHandleBundle($job, &$log)
{
    $data = unserialize($job->workload());
    $timstamp = array_shift($data['data']['data']);
    $bundles = $data['data']['data'];

    $gc = new GearmanClient();
    $gc->addServer();

    if ($timstamp <= new DateTime()) {

        $log[] = "Dispatching Bundle";

        foreach ($bundles AS $bundle) {
            $address = $bundle['address'];

            switch($address) {

            case "#bundle":
                $log[] = "Recursive Bundle";
                $function = "poschpHandleBundle";
                break;

            default:
                $log[] = "Bundled Message";
                $function = "poschpDigestMessage";
            }

            $gc->doBackground($function, serialize($bundle));
        }

    } else {
        $log[] = "Future Bundle";
        // @todo handle timestamped bundles that need storing and waiting
    }
}
