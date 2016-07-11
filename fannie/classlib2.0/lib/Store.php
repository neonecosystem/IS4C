<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

    This file is part of CORE-POS.

    CORE-POS is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    CORE-POS is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

namespace COREPOS\Fannie\API\lib;

class Store
{
    public static function getIdByIp()
    {
        $clientIP = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
        $ranges = \FannieConfig::config('STORE_NETS');
        $dbc = \FannieDB::getReadOnly(\FannieConfig::config('OP_DB'));
        $res = $dbc->query('SELECT storeID FROM Stores');
        while ($row = $dbc->fetchRow($res)) {
            if (
                isset($ranges[$row['storeID']]) 
                && class_exists('\\Symfony\\Component\\HttpFoundation\\IpUtils')
                && \Symfony\Component\HttpFoundation\IpUtils::checkIp($clientIP, $ranges[$row['storeID']])
                ) {
                return $row['storeID'];
            }
        }

        return false;
    }
}

