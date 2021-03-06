<?php
/*******************************************************************************

    Copyright 2001, 2004 Wedge Community Co-op

    This file is part of IT CORE.

    IT CORE is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    IT CORE is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

use COREPOS\pos\lib\gui\NoInputCorePage;
use COREPOS\pos\lib\Authenticate;
use COREPOS\pos\lib\Database;
use COREPOS\pos\lib\FormLib;
use COREPOS\pos\lib\MiscLib;
use COREPOS\pos\lib\TransRecord;

/* this module is intended for re-use. 
 * Pass the name of a class with the
 * static properties: 
 *  - adminLoginMsg (message to display)
 *  - adminLoginLevel (employees.frontendsecurity requirement)
 * and static method:
 *  - adminLoginCallback(boolean $success)
 *
 * The callback should return a URL or True (for pos2.php)
 * when $success is True. When $success is False, the return
 * value is irrelevant. That call is provided in case any
 * cleanup is necessary after a failed login.
 */

include_once(dirname(__FILE__).'/../lib/AutoLoader.php');

class adminlogin extends NoInputCorePage 
{
    private $box_color;
    private $msg;
    private $heading;

    private function getClass()
    {
        $class = FormLib::get('class');
        $class = str_replace('-', '\\', $class);
        // make sure calling class implements required
        // method and properties
        $method = new ReflectionMethod($class, 'adminLoginCallback');
        if (!$method->isStatic() || !$method->isPublic())
            throw new Exception('bad method adminLoginCallback');
        $property = new ReflectionProperty($class, 'adminLoginMsg');
        if (!$property->isStatic() || !$property->isPublic())
            throw new Exception('bad property adminLoginMsg');
        $property = new ReflectionProperty($class, 'adminLoginLevel');
        if (!$property->isStatic() || !$property->isPublic())
            throw new Exception('bad property adminLoginLevel');

        return $class;
    }

    function preprocess()
    {
        $this->box_color="coloredArea";
        $this->msg = _("enter admin password");

        $pos_home = MiscLib::base_url().'gui-modules/pos2.php';
        // get calling class (required)
        try {
            $class = $this->getClass();
        } catch (Exception $ex) {
            $class = '';
        }
        if ($class === '' || !class_exists($class)){
            $this->change_page($pos_home);
            return False;
        }

        $this->heading = $class::$adminLoginMsg;

        if (FormLib::get('reginput') !== '' || FormLib::get('userPassword') !== '') {
            $passwd = FormLib::get('reginput');
            if ($passwd === '') {
                $passwd = FormLib::get('userPassword');
            }

            if (strtoupper($passwd) == "CL") {
                $class::adminLoginCallback(false);
                $this->change_page($this->page_url."gui-modules/pos2.php");
                return false;    
            } elseif (empty($passwd)) {
                $this->box_color="errorColoredArea";
                $this->msg = _("re-enter admin password");
            } else {
                $dbc = Database::pDataConnect();
                if (Authenticate::checkPermission($passwd, $class::$adminLoginLevel)) {
                    $this->approvedAction($class, $passwd);

                    return false;
                } else {
                    $this->box_color="errorColoredArea";
                    $this->msg = _("re-enter admin password");

                    TransRecord::add_log_record(array(
                        'upc' => $passwd,
                        'description' => substr($class::$adminLoginMsg,0,30),
                        'charflag' => 'PW'
                    ));

                    $this->beep();
                }
            }
        } else {
            // beep on initial page load
            $this->beep();
        }

        return true;
    }

    private function beep()
    {
        if (CoreLocal::get('LoudLogins') == 1) {
            UdpComm::udpSend('errorBeep');
        }
    }

    private function approvedAction($class, $passwd)
    {
        $row = Authenticate::getEmployeeByPassword($passwd);
        TransRecord::add_log_record(array(
            'upc' => $row['emp_no'],
            'description' => substr($class::$adminLoginMsg . ' ' . $row['FirstName'],0,30),
            'charflag' => 'PW',
            'num_flag' => $row['emp_no']
        ));
        $this->beep();
        $result = $class::adminLoginCallback(True);
        if ($result === true) {
            $this->change_page(MiscLib::base_url().'gui-modules/pos2.php');
        } else {
            $this->change_page($result);
        }
    }

    function head_content(){
        $this->default_parsewrapper_js();
        $this->scanner_scale_polling(True);
    }

    function body_content()
    {
        ?>
        <div class="baseHeight">
        <div class="<?php echo $this->box_color; ?> centeredDisplay">
        <span class="larger">
        <?php echo $this->heading ?>
        </span><br />
        <form name="form" id="formlocal" method="post" 
            autocomplete="off" action="<?php echo filter_input(INPUT_SERVER, 'PHP_SELF'); ?>">
        <input type="password" id="userPassword" name="userPassword" tabindex="0" onblur="$('#userPassword').focus();" />
        <input type="hidden" name="reginput" id="reginput" value="" />
        <input type="hidden" name="class" value="<?php echo FormLib::get('class'); ?>" />
        </form>
        <p>
        <?php echo $this->msg ?>
        </p>
        </div>
        </div>
        <?php
        $this->add_onload_command("\$('#userPassword').focus();");
    } // END true_body() FUNCTION
}

AutoLoader::dispatch();

