<?php
/**
 * Override defaults of GearmanManager on a low level.
 *
 * PHP Version 5
 *
 * @category   PoscHP
 * @package    Server
 * @subpackage Gearman
 * @author     Lucas S. Bickel <hairmare@purplehaze.ch>
 * @copyright  2011 Lucas S. Bickel 2011 - Alle Rechte vorbehalten
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GPLv3
 * @link       http://osc.purplehaze.ch
 */

/**
 * load pecl implementation of gearman manager
 */
require_once 'GearmanManager/pecl-manager.php';

/**
 * stub class for gearman api.
 *
 * @class
 * @category   PoscHP
 * @package    Server
 * @subpackage Gearman
 * @author     Lucas S. Bickel <hairmare@purplehaze.ch>
 * @copyright  2011 Lucas S. Bickel 2011 - Alle Rechte vorbehalten
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GPLv3
 * @link       http://osc.purplehaze.ch
 */
class Gearman_Manager extends GearmanPeclManager
{
}
