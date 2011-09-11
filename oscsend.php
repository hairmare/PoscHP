<?php
/**
 * send some OSC packets to test osctest.php
 *
 * This shows how to send the most important datatypes and how to
 * send nested data as array or as osc bundle.
 *
 * PHP Version 5
 *
 * @category   PoscHP
 * @package    Osc
 * @subpackage Test
 * @author     Lucas S. Bickel <hairmare@purplehaze.ch>
 * @copyright  2011 Lucas S. Bickel 2011 - Alle Rechte vorbehalten
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GPLv3
 * @link       http://osc.purplehaze.ch
 */

/**
 * load andy schmeders osc client
 */
require_once 'lib/OSC.php';

/**
 * setup OSCClient
 */
$client = new OSCClient();
$client->set_destination("localhost", 10000);

/**
 * send a osc message with nested array data 
 */
$m = new OSCMessage(
    "/container/method", //    OSC Address
    array(                  // Array of data
        1,                  // int
        12,                 // int
        array(              // array in data
            array(          // nesting supported
                "channel",  // string
                1,          // int
                "state",    // string
                false       // boolean
            ), 
            false,          // boolean
            true            // boolean
        )
    )
);
$client->send($m);

/**
 * send osc bundle with multiple messages
 */
$b = new OSCBundle();
$b->add_datagram(
    new OSCMessage(
        "/foo",            
        array(
            100,
            "like a boss",
        )
    )
);
$b->add_datagram(
    new OSCMessage(
        "/bar",
        array(
            new Timetag,
            true,
            255
        )
    )
);
$client->send($b);

/**
 * send last osc bundle nested in a osc bundle
 */
$b = new OSCBundle(array($b));
$client->send($b);

