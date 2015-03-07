<?php
/*******************************************************************************

    Copyright 2014 Whole Foods Co-op

    This file is part of Fannie.

    Fannie is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    Fannie is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

include(dirname(__FILE__) . '/../../config.php');
if (!class_exists('FannieAPI')) {
    include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class SmartMovementReport extends FannieReportPage 
{
    protected $report_cache = 'none';
    protected $title = "Fannie :  Movement Report";
    protected $header = "Movement Report";

    protected $required_fields = array('date1', 'date2');

    public $description = '[Department Movement] lists sales for a department or group of departments over a given date range.';
    public $report_set = 'Movement Reports';
    public $themed = true;
    private $mode = 'PLU';

    public function preprocess()
    {
        $this->mode = FormLib::get('sort', 'PLU');

        return parent::preprocess();
    }

    public function report_description_content()
    {
        if ($this->report_format != 'html') {
            return array();
        }

        $url = $this->config->get('URL');
        $this->add_script($url . 'src/javascript/jquery.js');
        $this->add_script($url . 'src/javascript/jquery-ui.js');
        $this->add_css_file($url . 'src/javascript/jquery-ui.css');

        $dates_form = '<form method="post" action="' . $_SERVER['PHP_SELF'] . '">';
        foreach ($_GET as $key => $value) {
            if ($key != 'date1' && $key != 'date2') {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $dates_form .= sprintf('<input type="hidden" name="%s[]" value="%s" />', $key, $v);
                    }
                } else {
                    $dates_form .= sprintf('<input type="hidden" name="%s" value="%s" />', $key, $value);
                }
            }
        }
        foreach ($_POST as $key => $value) {
            if ($key != 'date1' && $key != 'date2') {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $dates_form .= sprintf('<input type="hidden" name="%s[]" value="%s" />', $key, $v);
                    }
                } else {
                    $dates_form .= sprintf('<input type="hidden" name="%s" value="%s" />', $key, $value);
                }
            }
        }
        $dates_form .= '
            <label>Start Date</label>
            <input class="date-field" type="text" name="date1" value="' . FormLib::get('date1') . '" /> 
            <label>End Date</label>
            <input class="date-field" type="text" name="date2" value="' . FormLib::get('date2') . '" /> 
            <input type="hidden" name="excel" value="" id="excel" />
            <button type="submit" onclick="$(\'#excel\').val(\'\');return true;">Change Dates</button>
            <button type="submit" onclick="$(\'#excel\').val(\'csv\');return true;">Download</button>
            </form>';

        $this->add_onload_command("\$('.date-field').datepicker({dateFormat:'yy-mm-dd'});");
        
        return array($dates_form);
    }

    public function fetch_report_data()
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $query = '';
        $from_where = FormLib::standardItemFromWhere();
        switch ($this->mode) {
            case 'PLU':
                $query = "
                    SELECT t.upc,
                        CASE WHEN p.description IS NULL THEN t.description ELSE p.description END as description, 
                        SUM(CASE WHEN trans_status IN('','0') THEN 1 WHEN trans_status='V' THEN -1 ELSE 0 END) as rings,"
                        . DTrans::sumQuantity('t')." as qty,
                        SUM(t.total) AS total,
                        t.department,
                        d.dept_name,
                        m.super_name,
                        COALESCE(v.vendorName,x.distributor) AS distributor
                    " . $from_where['query'] . "
                    GROUP BY t.upc,
                        CASE WHEN p.description IS NULL THEN t.description ELSE p.description END,
                        CASE WHEN t.trans_status='R' THEN 'Refund' ELSE 'Sale' END,
                        t.department,
                        d.dept_name,
                        m.super_name,
                        COALESCE(v.vendorName,x.distributor)
                    ORDER BY SUM(t.total) DESC";
                break;
            case 'Department':
                $query = "
                    SELECT t.department,
                        d.dept_name, "
                        . DTrans::sumQuantity('t')." AS qty,
                        SUM(total) AS total 
                    " . $from_where['query'] . "
                    GROUP BY t.department,
                        d.dept_name
                    ORDER BY SUM(t.total) DESC";
                break;
            case 'Date':
                $query = "
                    SELECT YEAR(t.tdate) AS year,
                        MONTH(t.tdate) AS month,
                        DAY(t.tdate) AS day, "
                        . DTrans::sumQuantity('t')." AS qty,
                        SUM(total) AS total 
                    " . $from_where['query'] . "
                    GROUP BY YEAR(t.tdate),
                        MONTH(t.tdate),
                        DAY(t.tdate)
                    ORDER BY YEAR(t.tdate),
                        MONTH(t.tdate),
                        DAY(t.tdate)";
                break;
            case 'Weekday':
                $cols = $dbc->dayofweek("tdate")." AS dayNumber,CASE 
                    WHEN ".$dbc->dayofweek("tdate")."=1 THEN 'Sun'
                    WHEN ".$dbc->dayofweek("tdate")."=2 THEN 'Mon'
                    WHEN ".$dbc->dayofweek("tdate")."=3 THEN 'Tue'
                    WHEN ".$dbc->dayofweek("tdate")."=4 THEN 'Wed'
                    WHEN ".$dbc->dayofweek("tdate")."=5 THEN 'Thu'
                    WHEN ".$dbc->dayofweek("tdate")."=6 THEN 'Fri'
                    WHEN ".$dbc->dayofweek("tdate")."=7 THEN 'Sat'
                    ELSE 'Err' END";
                $query = "
                    SELECT " . $cols . " AS dayName, "
                        . DTrans::sumQuantity('t') . " as qty,
                        SUM(total) as total 
                    " . $from_where['query'] . "
                    GROUP BY " . str_replace(' AS dayNumber', '', $cols) . "
                    ORDER BY " . $dbc->dayofweek('t.tdate');
                break;
        }

        $prep = $dbc->prepare($query);
        $result = $dbc->execute($prep, $from_where['args']);
        $data = array();
        while ($row = $dbc->fetch_row($result)) {
            switch ($this->mode) {
                case 'PLU':
                    $data[] = array(
                        $row['upc'],
                        $row['description'],
                        $row['rings'],
                        sprintf('%.2f', $row['qty']),
                        sprintf('%.2f', $row['total']),
                        $row['department'],
                        $row['dept_name'],
                        $row['super_name'],
                        $row['distributor'],
                    );
                    break;
                case 'Department':
                    $data[] = array(
                        $row['department'],
                        $row['dept_name'],
                        sprintf('%.2f', $row['qty']),
                        sprintf('%.2f', $row['total']),
                    );
                    break;
                case 'Date':
                    $tstamp = mktime(0, 0, 0, $row['month'], $row['day'], $row['year']);
                    $data[] = array(
                        date('m/d/Y', $tstamp),
                        date('l', $tstamp),
                        sprintf('%.2f', $row['qty']),
                        sprintf('%.2f', $row['total']),
                    );
                    break;
                case 'Weekday':
                    $data[] = array(
                        $row['dayNumber'],
                        $row['dayName'],
                        sprintf('%.2f', $row['qty']),
                        sprintf('%.2f', $row['total']),
                    );
                    break;
            }
        }

        return $data;
    }

    public function calculate_footers($data)
    {
        switch ($this->mode) {
            case 'PLU':
                $this->report_headers = array('UPC','Description','Rings','Qty','$',
                    'Dept#','Department','Super','Vendor');
                $this->sort_column = 4;
                $this->sort_direction = 1;
                $sumQty = 0.0;
                $sumSales = 0.0;
                $sumRings = 0.0;
                foreach($data as $row) {
                    $sumRings += $row[2];
                    $sumQty += $row[3];
                    $sumSales += $row[4];
                }
                return array('Total',null,$sumRings,$sumQty,$sumSales,'',null,null,null);
                break;
            case 'Weekday':
            case 'Date':
                $this->report_headers = array(($this->mode=='Date' ? 'Date' : 'Day'), 'Day', 'Qty', '$');
                $this->sort_column = 0;
                $this->sort_direction = 0;
                $sumQty = 0.0;
                $sumSales = 0.0;
                foreach($data as $row) {
                    $sumQty += $row[2];
                    $sumSales += $row[3];
                }

                return array('Total',null,$sumQty,$sumSales);
                break;
            case 'Department':
                $this->report_headers = array('Dept#','Department','Qty','$');
                $this->sort_column = 3;
                $this->sort_direction = 1;
                $sumQty = 0.0;
                $sumSales = 0.0;
                foreach($data as $row) {
                    $sumQty += $row[2];
                    $sumSales += $row[3];
                }

                return array('Total',null,$sumQty,$sumSales);
                break;
        }
    }

    public function form_content()
    {
        ob_start();
        ?>
        <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>">
        <div class="row">
            <?php echo FormLib::standardItemFields(); ?>
            <?php echo FormLib::standardDateFields(); ?>
        </div>
        <div class="row form-horizontal">
            <div class="form-group">
                <label class="col-sm-2 control-label">Sum movement by?</label>
                <div class="col-sm-2">
                    <select name="sort" class="form-control">
                        <option>PLU</option>
                        <option>Date</option>
                        <option>Department</option>
                        <option>Weekday</option>
                    </select> 
                </div>
                <label class="col-sm-1 control-label">Store</label>
                <div class="col-sm-2">
                    <?php $s = FormLib::storePicker(); echo $s['html']; ?>
                </div>
                <label class="col-sm-2 control-label">
                    <input type="checkbox" name="excel" value="csv" />
                    Excel
                </label>
            </div>
        </div>
        <p>
            <button type="submit" class="btn btn-default">Get Report</button>
            <button type="reset" class="btn btn-default">Reset Form</button>
        </p>
        </form>
        <?php

        return ob_get_clean();
    }
}

FannieDispatch::conditionalExec();

