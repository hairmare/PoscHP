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
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GPLv3
 * @link       http://osc.purplehaze.ch
 */

/**
 * Hex to Float parsing is external and broken for now
 */
require_once "Osc/HexFloat.php";

/**
 * Parser for OSC messages
 *
 * @class
 * @category   OscPhront
 * @package    Osc
 * @subpackage Protocol
 * @author     Lucas S. Bickel <hairmare@purplehaze.ch>
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GPLv3
 * @link       http://osc.purplehaze.ch
 */
class Osc_Parse
{
    /**
     * constants for internal state
     * @var Integer
     */
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

    /**
     * debug mode switch
     * @var Boolean
     */
    private $_debug = false;
    
    /**
     * current parser state
     *
     * @var Integer
     */
    private $_state = Osc_Parse::STATE_INIT;

    /**
     * buffer from socket_recvfrom()
     *
     * This buffer contains an array of single chars (in hex) from the input
     * buffer. This way the data buffer is nearly human readable.
     *
     * @var Array
     */
    private $_data;

    /**
     * store for parsed data
     *
     * @var Array
     */
    private $_store = array();

    /**
     * what store to write to
     *
     * @var String
     */
    private $_currentStore = "_store";

    /**
     * stack of stores used to handle nested arrays
     *
     * @var Array
     */
    private $_storeStack = array();

    /**
     * store for osc datagram adress
     * @var String
     */
    private $_address;

    /**
     * position in buffer during parse
     *
     * @var Integer
     */
    private $_bidx;

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
        $ordstr = array_map('ord', str_split($buffer));
        $this->_data = array_map('dechex', $ordstr);
        $this->_reset();
        if ($this->_debug) {
            var_dump($this->_data);
        }
    }

    /**
     * Set literal pre parsed data
     *
     * this is used for parsing buffers where we already have a converted
     * stream input but still need a new parser.
     *
     * @param Array $buffer Array of Hex Patterns as single byte sting
     *
     * @return void
     */ 
    public function setData($buffer)
    {
        $this->_data = $buffer;
        $this->_reset();
    }

    /**
     * Look if we have some data
     *
     * mainly used for test purposes.
     *
     * @return boolean
     */
    public function hasData()
    {
        return !empty($this->_data);
    }

    /**
     * Toggle or set debug flag
     *
     * @param Boolean $debug Debug On/Off
     *
     * @return Boolean
     */
    public function setDebug($debug = null)
    {
        if (is_null($debug)) {
            $this->_debug = ! $this->_debug;
        } else {
            $this->_debug = $debug;
        }
        return $this->_debug;
    }

    /**
     * Get the results.
     *
     * @return Array
     */
    public function getResult()
    {
        if ($this->_state != Osc_Parse::STATE_DONE) {
            trigger_error(
                "getResult called on unfinished parse",
                E_USER_WARNING
            );
        } else {
            return array(
                "address" => trim($this->_address),
                "data" => $this->_store
            );
        }
    }

    /**
     * Parse the data waiting in the internal buffer.
     *
     * Call this after having recieved a OSC datagram you would like
     * to parse.
     *
     * This method implements the first level of our state machine.
     * {@see Osc_Parse::_parseBySchema()} for the rest of the state
     * machine.
     *
     * @todo refactor large code blocks into their own methods
     *
     * @return void
     */
    public function parse() 
    {
        $this->_bidx = 1;
        while (true) {
            switch($this->_state) {
            case Osc_Parse::STATE_INIT:

                // look for OSC Address
                $this->_setState($this->_recvAddress());
                break;

            case Osc_Parse::STATE_ADDRESS:
                
                $this->_setState($this->_filterAddress());

            case Osc_Parse::STATE_ADDRESS_PARSED:

                $this->_setState($this->_recvSchema());
                break;

            case Osc_Parse::STATE_SCHEMA:

                $this->_setState($this->_digestSchema());

            case Osc_Parse::STATE_SCHEMA_PARSED:

                $this->_setState($this->_parseBySchema());
                break;

            case Osc_Parse::STATE_DATA_STRING:

                if (empty($stringdata)) {
                    static $stringdata = "";
                }
                $stringdata .= chr(hexdec(array_shift($this->_data)));
                $this->_bidx++;

                $end = !array_key_exists(0, $this->_data) 
                    || $this->_data[0] == "0";
                if ($end && $this->_bidx % 4 == 0) {

                    if ($this->_debug) {
                        printf("Found String '%s'\n", $stringdata);
                    }

                    $this->_appendStore($stringdata);
                    $stringdata = null;
                    array_shift($this->_data);
                    $this->_bidx++;

                    if (empty($this->_data) && empty($this->_schema)) {
                        $this->_setState(Osc_Parse::STATE_DONE);
                    } else {
                        $this->_setState(Osc_Parse::STATE_SCHEMA_PARSED);
                    }
                }
                
                break;
                
            case Osc_Parse::STATE_DATA_CHAR:

                if ($this->_debug) {
                    printf("Found Char %s\n", chr(hexdec($this->_data[0])));
                }
                $this->_appendStore(chr(hexdec(array_shift($this->_data))));
                $this->_bidx++;
                $this->_setState(Osc_Parse::STATE_SCHEMA_PARSED);

                break;
            case Osc_Parse::STATE_DONE:
                if (empty($this->_data)) {
                    return;
                }
            default:
                $this->remains .= chr(hexdec(array_shift($this->_data)));
                $this->_bidx++;
            }
        }
    }

    /**
     * clear all internal storage except data
     *
     * This is needed before parsing and is called immediatly after
     * loading new data into an instance.
     *
     * @return void
     */
    private function _reset()
    {
        unset($this->_address);
        unset($this->_schema);
        $this->_store = array();
        $this->_setState(Osc_Parse::STATE_INIT);
    }

    /**
     * Set the new state.
     *
     * @param Integer $state new State to set
     *
     * @return void
     */
    private function _setState($state)
    {
        if ($this->_state == $state) {
            return;
        }
        if ($this->_debug) {
            printf("Switching state from %1s to %2s.\n", $this->_state, $state);
        }
        $this->_state = $state;
    }

    /**
     * set which storage stack we are writing to
     *
     * @param String $name Store name
     *
     * @return void
     */
    private function _setStore($name)
    {
        $vname = "_".$name;
        array_push($this->_storeStack, $this->_currentStore);
        $this->_currentStore = $vname;
        if (empty($this->$vname)) {
            $this->$vname = array();
        }
    }

    /**
     * pop the last value off of storestack and use that for storage
     *
     * @return void
     */
    private function _popStore()
    {
        $vname = array_pop($this->_storeStack);
        $this->_currentStore = $vname;
    }

    /**
     * append data to _store
     *
     * @param Mixed $data Data to append
     *
     * @return void
     */
    private function _appendStore($data)
    {
        $vname = $this->_currentStore;
        array_push($this->$vname, $data);
    }

    /**
     * get an array from the array store
     *
     * @param Integer $arraylevel how deeply nested we are
     *
     * @return Array
     */
    private function _getArray($arraylevel)
    {
        $varname = "_array$arraylevel";
        $array = $this->$varname;
        unset($this->$varname);
        return $array;
    }

    /**
     * Shift multiple bytes off of _data
     *
     * This is used to shift bytes off of data. For OSC it does not make
     8 sense calling this with anything other than a $count that is divisible
     * by 4. This is due to OSC being 32 bit safe.
     * The various operators define how the data is concatenated and they are
     * based on the use cases i ran across so far.
     *
     * @param Integer $count    number of bytes to return
     * @param String  $operator how to combine string (sprintf02, concat, array)
     *
     * @return String
     */
    private function _multiByteShift($count, $operator = 'sprintf02')
    {
        if ($operator == 'array') {
            $v = array();
        } else {
            $v = '';
        }
        for ($i = 0; $i < $count; $i++) {
            switch ($operator) {
            case "concat":

                $v .= array_shift($this->_data);
                break;

            case "array":

                $v[] = array_shift($this->_data);
                break;

            case "sprintf02":
            default:
                $v .= sprintf("%02s", array_shift($this->_data));
                break;
            }
            $this->_bidx++;
        }
        return $v;
    }

    /**
     * Read data until OSC address is complete.
     *
     * @return Integer State
     */
    private function _recvAddress()
    {
        if (empty($this->_data)) {
            // just an address? probably wrong but lets not loop over that
            $state = Osc_Parse::STATE_ADDRESS;
        } else if ($this->_data[0] == "0" && $this->_bidx % 4 == 0) {
            // complete adress detected
            array_shift($this->_data);
            $state = Osc_Parse::STATE_ADDRESS;
        } else {
            $this->_address .= chr(hexdec(array_shift($this->_data)));
            $state = $this->_state;
        }
        $this->_bidx++;
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
        $state = Osc_Parse::STATE_ADDRESS_PARSED;

        // special handling for bundles with "register" #bundle address
        if (substr($this->_address, 0, 7) == "#bundle") {
            $state = $this->_parseBundles();
        }

        return $state;
    }

    /**
     * parse data until no more buffers remain
     *
     * after this method we are always in the done state
     * since messages may not contain osc bundles.
     *
     * @return void
     */
    private function _parseBundles() 
    {
        $state = $this->_state;

        // @todo add bundle ts handling
        $bundlets = $this->_multiByteShift(8, 'array');

        while (!empty($this->_data)) {
            $bundlesize = hexdec($this->_multiByteShift(4));
            if ($bundlesize % 4 !== 0) {
                $bundlesize++;
            }
            $bundledata = $this->_multiByteShift($bundlesize, 'array');

            if ($this->_debug) {
                printf("Found Bundle of length %s\n", $bundlesize);
            }

            $bundleparse = new Osc_Parse();
            $bundleparse->setDebug($this->_debug);
            $bundleparse->setData($bundledata);
            $bundleparse->parse();

            if ($this->_debug) {
                printf("Parsed bundle.");
                var_dump($bundleparse->getResult());
            }

            $this->_appendStore($bundleparse->getResult());
        }

        $state = Osc_Parse::STATE_DONE;
        return $state;
    }

    /**
     * Read data until OSC data schema is complete.
     *
     * @return Integer State
     */
    private function _recvSchema()
    {
        $state = $this->_state;
        if (empty($this->_data)) {
            // this is most likely a bundle!
            $state = Osc_Parse::STATE_DONE;

        } else if ($this->_data[0] == "0" && $this->_bidx % 4 == 0) {
            // schema is complete
            if (!empty($this->_schema)) {
                $state = Osc_Parse::STATE_SCHEMA;
            }
            array_shift($this->_data);

        } else if ($this->_data[0] != "0") {
            // read next byte from schema
            $this->_schema .= chr(hexdec(array_shift($this->_data)));

        } else {
            // discard anything else (most likely \0 padding)
            array_shift($this->_data);
        }
        $this->_bidx++;
        return $state;
    }

    /**
     * Split schema into tokens (aka str_split)
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
     * This is the second large part of our state machine. The first
     * part {@see Osc_Parse::parse()) was responsible for parsing
     * the packet at large. Here we what remains of a packet after
     * detecting an osc format string (aka schema).
     *
     * @todo refactor large code blocks into their own methods
     *
     * @return Integer State
     */
    private function _parseBySchema()
    {
        $state = $this->_state;
        while (!empty($this->_schema)) {
            if ($this->_debug) {
                printf("Considering %s\n", $this->_schema[0]);
            }

            switch(array_shift($this->_schema)) {
            case "i":

                $v = $this->_multiByteShift(4);

                if ($this->_debug) {
                    printf("Got Int %s = %s\n", $v, hexdec($v));
                }
                $this->_appendStore(hexdec($v));
                break 2;

            case "h":

                $v = '';
                for ($i = 0; $i < 8; $i++) {
                    $v .= sprintf("%02s", array_shift($this->_data));
                    $this->_bidx++;
                }
                if ($this->_debug) {
                    printf("Got LargeInt %s = %s\n", $v, hexdec($v));
                }
                $this->_appendStore(hexdec($v));
                break 2;

            case "f":
                $v = '';

                for ($i = 0; $i < 4; $i++) {
                     $v .= sprintf("%02s", array_shift($this->_data));
                     $this->_bidx++;
                }
                $r = Osc_HexFloat::hexTo32Float($v);
                if ($this->_debug) {
                    printf("Got Float %s = %s\n", $v, var_export($r, true));
                }
                trigger_error(
                    "Float support is broken, returning 0", 
                    E_USER_WARNING
                );
                $r = 0;
                $this->_appendStore($r);
                break 2;

            case "d":

                $r = Osc_HexFloat::hexTo64Float($this->_multiByteShift(8));
                if ($this->_debug) {
                    printf("Got Float %s = %s\n", $v, var_export($r, true));
                }
                trigger_error(
                    "Float support is broken, returning 0", 
                    E_USER_WARNING
                );
                $r = 0;
                $this->_appendStore($r);
                break 2;

            case "S":
            case "s":

                $state = Osc_Parse::STATE_DATA_STRING;
                break 2;

            case "t":
                $sec = hexdec($this->_multiByteShift(4));
                $msec = hexdec($this->_multiByteShift(4));


                // set osc special case to now
                if ($sec == 0 && $msec == 1) {
                    $this->_appendStore(new DateTime);
                } else {
                    $date = new DateTime('1900/1/1');
                    $date->add(new DateInterval(sprintf("PT%sS.", $sec)));
                    $this->_appendStore($date);
                }
                break 2;
                
            case "b":
                // @todo support blob
                trigger_error(
                    "Blob support is broken,", 
                    E_USER_WARNING
                );
                $size = hexdec($this->_multiByteShift(4));
                while ($size % 4 != 0) {
                    $size++;
                }
                $blob = $this->_multiByteShift($size, 'array');
                $this->_appendStore($blob);
                break 2;

            case "c":
                $state = Osc_Parse::STATE_DATA_CHAR;
                break 2;
            case "m":
                $v = '';
                for ($i = 0; $i < 4; $i++) {
                    $v .= sprintf("%02s", array_shift($this->_data));
                    $this->_bidx++;
                }
                $this->_appendStore($v);
                break 2;
            case "T":
                $this->_appendStore(true);
                break 2;
            case "F":
                $this->_appendStore(false);
                break 2;
            case "N":
                $this->_appendStore(null);
                break 2;
            case "I":
                $this->_appendStore(log(0));
                break 2;
            case "[":
                if (empty($this->_alvl)) {
                    $this->_alvl = 0;
                }
                $this->_setStore("array".$this->_alvl++);
                break 2;
            case "]":
                $this->_popStore();
                $array = $this->_getArray(--$this->_alvl);
                $this->_appendStore($array);
                break;

            default:

                if ($this->_debug) {
                    printf("Discarding value from Schema\n");
                }
                break 2;
            }
        }
        if (empty($this->_schema)) {
            $state = Osc_Parse::STATE_DONE;
        }
        return $state;
    }
}


