<?php
/**
 * Osc Parser written in PHP
 *
 * PHP Version 5
 *
 * @category   OscPhront
 * @package    Osc
 * @subpackage Protocol
 * @author     Lucas S. Bickel <hairmare@purplehaze.ch>
 * @copyright  2011 Lucas S. Bickel 2011 - Alle Rechte vorbehalten
 * @license    http://osc.purplehaze.ch/LICENSE GPLv3
 * @link       http://osc.purplehaze.ch
 */

/**
 * Parser for OSC messages
 *
 * @class
 * @category   OscPhront
 * @package    Osc
 * @subpackage Protocol
 * @author     Lucas S. Bickel <hairmare@purplehaze.ch>
 * @license    http://osc.purplehaze.ch/LICENSE GPLv3
 * @link       http://osc.purplehaze.ch
 */
class Osc_Parse
{
    const STATE_INIT = 0;
    const STATE_ADDRESS = 1;
    const STATE_ADDRESS_PARSED = 2;
    const STATE_SCHEMA = 3;
    const STATE_SCHEMA_PARSED = 4;
    const STATE_DATA_INT = 5;
    const STATE_DATA_STRING = 6;
    const STATE_DATA_CHAR = 7;
    const STATE_DATA_DEFAULT = 22;
    const STATE_DONE = 99;

    private $_debug = true;

    private $_state = Osc_Parse::STATE_INIT;

    private $_data;

    /**
     * Pass data recieved with socket_recvfrom() here.
     *
     * @param String $buffer Binary Stream from socket_recvfrom()
     *
     * @return void
     */
    public function setDataString($buffer)
    {
        // serialize it right away
        $this->_data = array_map('dechex', array_map('ord', str_split($buffer)));
    }

    /**
     * Parse the data waiting in the internal buffer.
     *
     * @return void
     */
    public function parse() 
    {
        $byteindex = 1;
        while (!empty($this->_data)) {
            switch($this->_state) {
            case Osc_Parse::STATE_INIT:

                // look for OSC Address
                $this->_state = $this->_recvAddress($byteindex++);
                break;

            case Osc_Parse::STATE_ADDRESS:
                
                $this->_state = $this->_filterAddress();

            case Osc_Parse::STATE_ADDRESS_PARSED:

                $this->_state = $this->_recvSchema($byteindex++);
                break;

            case Osc_Parse::STATE_SCHEMA:

                $this->_state = $this->_digestSchema();

            case Osc_Parse::STATE_SCHEMA_PARSED:

                $this->_state = $this->_parseBySchema(&$byteindex);
                break;

            case Osc_Parse::STATE_DATA_STRING:

                if (empty($stringdata)) {
                    static $stringdata = "";
                }
                $stringdata .= chr(hexdec(array_shift($this->_data)));
                $byteindex++;

                if ($this->_data[0] == "0") {

                    if ($this->_debug) {
                        printf("Found String '%s'\n", $stringdata);
                    }

                    // @todo fix empty string handling
                    $this->_store[] = $stringdata;
                    $stringdata = null;
                    array_shift($this->_data);
                    $byteindex++;

                    $this->_state = Osc_Parse::STATE_SCHEMA_PARSED;
                }
                
                break;
                
            case Osc_Parse::STATE_DATA_CHAR:

                if ($this->_debug) {
                    printf("Found Char %s\n", chr(hexdec($this->_data[0])));
                }
                $this->_store[] = chr(hexdec(array_shift($this->_data)));
                $byteindex++;
                $this->_state = Osc_Parse::STATE_SCHEMA_PARSED;

                break;
            case Osc_Parse::STATE_DONE:
                // break
            default:
                $this->remains .= chr(hexdec(array_shift($this->_data)));
                $byteindex++;
            }

            printf("Loop with state %s\n", $this->_state);
        }
    }

    /**
     * Read data until OSC address is complete.
     *
     * @param Integer $byteindex What position of the stream we are looking at.
     *
     * @return Integer State
     */
    private function _recvAddress($byteindex)
    {

        if ($this->_data[0] == "0" && $byteindex % 4 == 0) {
            // complete adress detected
            array_shift($this->_data);
            $state = Osc_Parse::STATE_ADDRESS;
        } else {
            $this->_address .= chr(hexdec(array_shift($this->_data)));
            $state = $this->_state;
        }
        return $state;
    }

    /**
     * Filter OSC address.
     *
     * @return Integer State
     */
    private function _filterAddress()
    {
        if ($this->_debug) {
            printf("Got Adress %s\n", $this->_address);
        }

        // check if we are interested in the address
        // @todo add filtering through nice delegation framework
        
        return Osc_Parse::STATE_ADDRESS_PARSED;
    }

    /**
     * Read data until OSC data schema is complete.
     *
     * @param Integer $byteindex What position of the stream we are looking at.
     *
     * @return Integer State
     */
    private function _recvSchema($byteindex)
    {
        $state = $this->_state;
        if ($this->_data[0] == "0" && $byteindex % 4 == 0) {
            if (!empty($this->_schema)) {
                $state = Osc_Parse::STATE_SCHEMA;
            }
            array_shift($this->_data);
        } else if ($this->_data[0] != "0") {
            $this->_schema .= chr(hexdec(array_shift($this->_data)));
        } else {
            array_shift($this->_data);
        }
        return $state;
    }

    /**
     * Split schema into tokens
     *
     * @return Integer State
     */
    private function _digestSchema()
    {
        if ($this->_debug) {
            printf("Got Schema %s\n", var_export($this->_schema, true));
        }

        $this->_schema = str_split($this->_schema);

        return Osc_Parse::STATE_SCHEMA_PARSED;
    }

    /**
     * Parse values into storage until schema stack is empty.
     *
     * @param Integer &$byteindex What position of the stream we are looking at.
     *
     * @return Integer State
     */
    private function _parseBySchema(&$byteindex)
    {
        $state = $this->_state;
        while (!empty($this->_schema)) {
            if ($this->_debug) {
                printf("Considering %s\n", $this->_schema[0]);
            }

            switch(array_shift($this->_schema)) {
            case "i":

                $v = '';
                for ($i = 0; $i < 4; $i++) {
                    $v .= sprintf("%02s", array_shift($this->_data));
                    $byteindex++;
                }
                if ($this->_debug) {
                    printf("Got Int %s = %s\n", $v, hexdec($v));
                }
                $this->_store[] = hexdec($v);
                break 2;

            case "h":

                $v = '';
                for ($i = 0; $i < 8; $i++) {
                    $v .= sprintf("%02s", array_shift($this->_data));
                    $byteindex++;
                }
                if ($this->_debug) {
                    printf("Got LargeInt %s = %s\n", $v, hexdec($v));
                }
                $this->_store[] = hexdec($v);
                break 2;

            case "f":
                $v = '';

                for ($i = 0; $i < 4; $i++) {
                     $v .= sprintf("%02s", array_shift($this->_data));
                     $byteindex++;
                }
                $r = hexTo32Float($v);
                if ($this->_debug) {
                    printf("Got Float %s = %s\n", $v, var_export($r, true));
                }
                trigger_error(
                    "OSC Float support is extremely broken, do not " .
                    "rely on these numbers", E_USER_WARNING,
                    E_USER_WARNING
                );
                $this->_store[] = $r;
                break 2;

            case "d":

                $v = '';
                for ($i = 0; $i < 8; $i++) {
                    $v .= sprintf("%02s", array_shift($this->_data));
                    $byteindex++;
                }
                $r = hexTo64Float($v);
                if ($this->_debug) {
                    printf("Got Float %s = %s\n", $v, var_export($r, true));
                }
                $this->_store[] = $r;
                break 2;

            case "S":
            case "s":

                $state = Osc_Parse::STATE_DATA_STRING;
                break 2;

            case "c":
                $state = Osc_Parse::STATE_DATA_CHAR;
                break 2;
            case "T":
                $this->_store[] = true;
                break 2;
            case "F":
                $this->_store[] = false;
                break 2;
            case "N":
                $this->_store[] = null;
                break 2;
            case "I":
                $this->_store[] = 1/0;
                break 2;

            default:

                if ($this->_debug) {
                    printf("Discarding value from Schema\n");
                }
                break 2;
            }
        }
        return $state;
    }
}


