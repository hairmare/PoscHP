<?php
/**
 * Osc Parser written in PHP - hexfloat module
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
 * Module for Hexfloats as seen in OSC
 *
 * @class
 * @category   OscPhront
 * @package    Osc
 * @subpackage Protocol
 * @author     Lucas S. Bickel <hairmare@purplehaze.ch>
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GPLv3
 * @link       http://osc.purplehaze.ch
 */
class Osc_HexFloat
{

    /**
     * BROKEN convert hex encoded float32
     *
     * @param Strign $strHex Hex encoded float32 in osc notation
     *
     * @return Float
     */
    function hexTo32Float($strHex)
    {
        $v = hexdec($strHex);
        $x = ($v & ((1 << 23) - 1)) + (1 << 23) * ($v >> 31 | 1);
        $exp = ($v >> 23 & 0xFF) - 127;
        return $x * pow(2, $exp - 23);
    }
    
    /**
     * BROKEN convert hex encoded float64
     *
     * @param Strign $strHex Hex encoded float64 in osc notation
     *
     * @return Float
     */
    function hexTo64Float($strHex)
    {
        $v = hexdec($strHex);
        $x = ($v & ((1 << 52) - 1)) + (1 << 52) * ($v >> 63 | 1);
        $exp = ($v >> 52 & 0xFF) - 1023;
        return $x * pow(2, $exp - 52);
    }
}
