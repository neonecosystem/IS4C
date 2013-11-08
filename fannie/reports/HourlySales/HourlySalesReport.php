<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

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

include('../../config.php');
include($FANNIE_ROOT.'classlib2.0/FannieAPI.php');

class HourlySalesReport extends FannieReportPage 
{

    protected $title = "Fannie : Hourly Sales Report";
    protected $header = "Hourly Sales";

    protected $sortable = false;
    protected $no_sort_but_style = true;

	public function preprocess()
    {
		$this->report_cache = 'none';

		if (isset($_REQUEST['date1'])){
			$this->content_function = "report_content";
			$this->has_menus(False);
		
			if (isset($_REQUEST['excel']) && $_REQUEST['excel'] == 'xls') {
				$this->report_format = 'xls';
			} elseif (isset($_REQUEST['excel']) && $_REQUEST['excel'] == 'csv') {
				$this->report_format = 'csv';
            }
		}
		else 
			$this->add_script("../../src/CalendarControl.js");

		return true;
	}

    public function fetch_report_data()
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);

        $date1 = FormLib::get('date1', date('Y-m-d'));
        $date2 = FormLib::get('date2', date('Y-m-d'));
        $deptStart = FormLib::get('deptStart');
        $deptEnd = FormLib::get('deptEnd');
        $weekday = FormLib::get('weekday', 0);
	
        $buyer = FormLib::get('buyer', '');

        // args/parameters differ with super
        // vs regular department
        $args = array($date1.' 00:00:00', $date2.' 23:59:59');
        $where = ' 1=1 ';
        if ($buyer !== '') {
            if ($buyer != -1) {
                $where = ' s.superID=? ';
                $args[] = $buyer;
            }
        } else {
            $where = ' d.department BETWEEN ? AND ? ';
            $args[] = $deptStart;
            $args[] = $deptEnd;
        }

        $date_selector = 'year(tdate), month(tdate), day(tdate)';
        $day_names = array();
        if ($weekday == 1) {
            $date_selector = $dbc->dayofweek('tdate');

            $timestamp = strtotime('next Sunday');
            for ($i = 1; $i <= 7; $i++) {
                $day_names[$i] = strftime('%a', $timestamp);
                $timestamp = strtotime('+1 day', $timestamp);
            }
        }
        $hour = $dbc->hour('tdate');

        $dlog = DTransactionsModel::selectDlog($date1, $date2);

        $query = "SELECT $date_selector, $hour as hour, 
                    sum(d.total) AS ttl, avg(d.total) as avg
                  FROM $dlog AS d ";
        // join only needed with specific buyer
        if ($buyer !== '' && $buyer > -1) {
            $query .= 'LEFT JOIN superdepts AS s ON d.department=s.dept_ID ';
        }
        $query .= "WHERE d.trans_type IN ('I','D')
                    AND d.tdate BETWEEN ? AND ?
                    AND $where
                   GROUP BY $date_selector, $hour
                   ORDER BY $date_selector, $hour";

        $prep = $dbc->prepare_statement($query);
        $result = $dbc->exec_statement($query, $args);

        $dataset = array();
        $minhour = 24;
        $maxhour = 0;
        while($row = $dbc->fetch_row($result)) {
            $hour = (int)$row['hour'];

            $date = '';
            if ($weekday == 1) {
                $date = $day_names[$row[0]];
            } else {
                $date = sprintf('%d/%d/%d', $row[1], $row[2], $row[0]);
            }
            
            if (!isset($dataset[$date])) {
               $dataset[$date] = array(); 
            }

            $dataset[$date][$hour] = $row['ttl'];

            if ($hour < $minhour) {
                $minhour = $hour;
            }
            if ($hour > $maxhour) {
                $maxhour = $hour;
            }
        }

        /**
          # of columns is dynamic depending on the
          date range selected
        */
        $this->report_headers = array('Day');
        foreach($dataset as $day => $info) {
            $this->report_headers[] = $day; 
        }
        $this->report_headers[] = 'Total';

        $data = array();
        /**
          # of rows is dynamic depending when
          the store was open
        */
        for($i=$minhour; $i<=$maxhour; $i++) {
            $record = array();
            $sum = 0;

            if ($i < 12) {
                $record[] = str_pad($i,2,'0',STR_PAD_LEFT).':00 AM';
            } else if ($i == 12) {
                $record[] = $i.':00 PM';
            } else {
                $record[] = str_pad(($i-12),2,'0',STR_PAD_LEFT).':00 PM';
            }

            // each day's sales for the given hour
            foreach($dataset as $day => $info) {
                $sales = isset($info[$i]) ? $info[$i] : 0;
                $record[] = sprintf('%.2f', $sales);
                $sum += $sales;
            }

            $record[] = $sum;
            $data[] = $record;
        }
        
        return $data;
	}

    public function calculate_footers($data)
    {
        if (count($data) == 0) {
            return array();
        }

        $ret = array('Totals');
        for($i=1; $i<count($data[0]); $i++) {
            $ret[] = 0.0;
        }

        foreach($data as $row) {
            for($i=1; $i < count($row); $i++) {
                $ret[$i] += $row[$i];
            }
        }

        for($i=1; $i<count($ret); $i++) {
            $ret[$i] = sprintf('%.2f', $ret[$i]); 
        }

        return $ret;
    }

    public function form_content()
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);

        $deptsQ = $dbc->prepare_statement("select dept_no,dept_name from departments order by dept_no");
        $deptsR = $dbc->exec_statement($deptsQ);
        $deptsList = "";

        $deptSubQ = $dbc->prepare_statement("SELECT superID,super_name FROM superDeptNames
                WHERE superID <> 0 
                ORDER BY superID");
        $deptSubR = $dbc->exec_statement($deptSubQ);

        $deptSubList = "";
        while($deptSubW = $dbc->fetch_array($deptSubR)) {
            $deptSubList .=" <option value=$deptSubW[0]>$deptSubW[1]</option>";
        }
        while ($deptsW = $dbc->fetch_array($deptsR)) {
            $deptsList .= "<option value=$deptsW[0]>$deptsW[0] $deptsW[1]</option>";
        }

        ob_start();
        ?>
<script type="text/javascript">
function swap(src,dst){
    var val = document.getElementById(src).value;
    document.getElementById(dst).value = val;
}
</script>
<div id=main>	
<form method = "get" action="HourlySalesReport.php">
	<table border="0" cellspacing="0" cellpadding="5">
		<tr>
			<td><b>Select Buyer/Dept</b></td>
			<td><select id=buyer name=buyer>
			   <option value=""></option>
			   <?php echo $deptSubList; ?>
			   <option value=-1 >All</option>
			   </select>
 			</td>
			<td><b>Send to Excel</b></td>
			<td><input type=checkbox name=excel id=excel value=1></td>
		</tr>
		<tr>
			<td colspan=5><i>Selecting a Buyer/Dept overrides Department Start/Department End, but not Date Start/End.
			To run reports for a specific department(s) leave Buyer/Dept or set it to 'blank'</i></td>
		</tr>
		<tr> 
			<td> <p><b>Department Start</b></p>
			<p><b>End</b></p></td>
			<td> <p>
 			<select id=deptStartSel onchange="swap('deptStartSel','deptStart');">
			<?php echo $deptsList ?>
			</select>
			<input type=text name=deptStart id=deptStart size=5 value=1 />
			</p>
			<p>
			<select id=deptEndSel onchange="swap('deptEndSel','deptEnd');">
			<?php echo $deptsList ?>
			</select>
			<input type=text name=deptEnd id=deptEnd size=5 value=1 />
			</p></td>

			 <td>
			<p><b>Date Start</b> </p>
		         <p><b>End</b></p>
		       </td>
		            <td>
		             <p>
		               <input type=text id=date1 name=date1 onfocus="this.value='';showCalendarControl(this);">
		               </p>
		               <p>
		                <input type=text id=date2 name=date2 onfocus="this.value='';showCalendarControl(this);">
		         </p>
		       </td>

		</tr>
		<tr> 
             <td colspan="2"><input type=checkbox name=weekday value=1>Group by weekday?</td>
			<td colspan="2" rowspan="2">
                <?php echo FormLib::date_range_picker(); ?>
            </td>
		</tr>
        <tr>
			<td> <input type=submit name=submit value="Submit"> </td>
			<td> <input type=reset name=reset value="Start Over"> </td>
		</tr>
	</table>
</form>
        <?php
        return ob_get_clean();
    }
}

FannieDispatch::go();

?>
