<?php
/**
 * proof of concept osc reciever
 *
 * start this script on a shell using php oscsend.php and enter some
 * oscsend command on another.
 * 
 * oscsend localhost 10000 /replay/status \
 *         iisisi 1 10047 "20110606-190002" 3600 "SGVsbGFzIFJhZGlvCg==" 1
 * oscsend localhost 10000 /1/2 sss "asdf" "" "qwer"
 * oscsend localhost 10000 /box/chan0 1
 * oscsend localhost 10000 /box/chan1 0
 * oscsend localhost 10000 // TTFFNNI 0 0 0 0 0 0 0
 * oscsend localhost 10000 /SYN/0/1 im 2 000000ff
 *
 * PHP Version 5
 *
 * @category   OscPhront
 * @package    Osc
 * @subpackage Test
 * @author     Lucas S. Bickel <hairmare@purplehaze.ch>
 * @copyright  2011 Lucas S. Bickel 2011 - Alle Rechte vorbehalten
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GPLv3
 * @link       http://osc.purplehaze.ch
 */

$debug = false;
$ip = 'localhost';
$port = 10000;
ini_set('include_path', 'src/:'.ini_get('include_path'));

/**
 * load osc lib
 */
require_once 'src/Osc/Parse.php';

/**
 * load broken hexfloat lib
 * 
 * i plan on groking pack/unpack to the extent that i 
 * can do this right in Osc_Parse without needing this.
 */
require_once 'src/Osc/HexFloat.php';


// create a socket
$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
$r = socket_bind($socket, $ip, $port);

// loop until user interrupt
while (true) {

    // this is what gets each datagram
    if (socket_recvfrom($socket, $b, 9999, 0, $f, $p)) {

        // a parser gets instanciated for each datagram
        $osc = new Osc_Parse;
        $osc->setDebug($debug);

        // pass the datagram buffer
        $osc->setDataString($b);

        // start the parser
        $osc->parse();

        // make some output
        if ($debug) {
            var_dump($osc);
        }
        $r = $osc->getResult();
        $r["sourceip"] = $f;
        $r["sourceport"] = $p;
        var_dump($r);
    }
}
