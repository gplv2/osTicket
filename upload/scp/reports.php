<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<?php
$VERSION = 'Version 3.4';
// sudobash.net/?p=821

// Get date for export file
$time = $_SERVER['REQUEST_TIME'];
$file = "Report_" .$time. ".csv";
$file = "reports/$file";

// echo getcwd() . "\n";

require('staff.inc.php');
 
$page='';
$answer=null; //clean start.
 
$nav->setTabActive('Reports');
$nav->addSubMenu(array('desc'=>'Reports','href'=>'reports.php','iconclass'=>''));

if($thisuser->isAdmin()){
$nav->addSubMenu(array('desc'=>'Report Settings','href'=>'reports_admin.php','iconclass'=>''));
}
require_once(STAFFINC_DIR.'header.inc.php');

$vquery = "SELECT ostversion from ".CONFIG_TABLE;
$versionCheck = mysql_query($vquery) or die(mysql_error());
while($versionRow = mysql_fetch_array($versionCheck)){
$version=$versionRow['ostversion'];
}

//echo $version;

?>
<br><br>
    <!--Load the AJAX API-->
    <script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <!--Load the Styles associated with reports-->
    <link rel="stylesheet" href="css/reports.css" type="text/css" />
<?$range = $_POST['range'];?>
<form method="POST" name="reportForm" action="reports.php">
 <div name="formTable" class="reporttable">
   <div name="rangeField" class="reportselect">
    <fieldset>
    <legend>Select date range</legend>
    <div>
      <input type="radio" name="dateRange" value="timePeriod" <?if($_POST['dateRange']=='timePeriod'){echo "selected";}?> checked /> 
      <select name="range" onclick="document.reportForm.dateRange[0].checked=true">
      <option value="today" <?if($_POST['range']=='today'){echo "selected";}?>>Today</option>
      <option value="yesterday" <?if($_POST['range']=='yesterday'){echo "selected";}?>>Yesterday</option>
      <option value="thisMonth" <?if($_POST['range']=='thisMonth'){echo "selected";}?>>This Month</option>
      <option value="lastMonth" <?if($_POST['range']=='lastMonth'){echo "selected";}?>>Last Month</option>
      <option value="lastThirty" <?if($_POST['range']=='lastThirty'){echo "selected";}?>>Last 30 days</option>
      <option value="thisWeek" <?if($_POST['range']=='thisWeek'){echo "selected";}?>>This Week (Sun-Sat)</option>
      <option value="lastWeek" <?if($_POST['range']=='lastWeek'){echo "selected";}?>>Last Week (Sun-Sat)</option>
      <option value="thisBusWeek" <?if($_POST['range']=='thisBusWeek'){echo "selected";}?>>This business week (Mon-Fri)</option>
      <option value="lastBusWeek" <?if($_POST['range']=='lastBusWeek'){echo "selected";}?>>Last business week (Mon-Fri)</option>
      <option value="thisYear" <?if($_POST['range']=='thisYear'){echo "selected";}?>>This year</option>
      <option value="lastYear" <?if($_POST['range']=='lastYear'){echo "selected";}?>>Last year</option>
      <option value="allTime" <?if($_POST['range']=='allTime'){echo "selected";}?>>All time</option>
      </select>
    </div>
    <div style="margin-top: 15px;">
     <input type="radio" name="dateRange" value="timeRange" <?if($_POST['dateRange']=='timeRange'){echo "checked";}?>/>From <input type="text" name="fromDate" value="<?if($_POST['fromDate']!=''){echo $_POST['fromDate'];}else{echo date("Y-m-d");}?>" onclick="document.reportForm.dateRange[1].checked=true"/> 
      To <input type="text" name="toDate" value="<?if($_POST['toDate']!=''){echo $_POST['toDate'];}else{echo date("Y-m-d");}?>"     onclick="document.reportForm.dateRange[1].checked=true"/>
    </fieldset>
  </div>
   <div name="typeField" class="reportselect">
   <fieldset>
   <legend>Report Type</legend>
    <select name="type">
     <option value="tixPerDept" <?if($_POST['type']=='tixPerDept'){echo "selected";}?>>Tickets per Department</option>
     <option value="tixPerDay" <?if($_POST['type']=='tixPerDay'){echo "selected";}?>>Tickets per Day</option>
     <option value="tixPerMonth" <?if($_POST['type']=='tixPerMonth'){echo "selected";}?>>Tickets per Month</option>
     <option value="tixPerStaff" <?if($_POST['type']=='tixPerStaff'){echo "selected";}?>>Tickets per Staff</option>
     <? if($version == '1.6 ST'){?>
     <option value="tixPerTopic" <?if($_POST['type']=='tixPerTopic'){echo "selected";}?>>Tickets per Help Topic</option>
     <?}?>
     <option value="repliesPerStaff" <?if($_POST['type']=='repliesPerStaff'){echo "selected";}?>>Replies per Staff</option>
     <option value="tixPerClient" <?if($_POST['type']=='tixPerClient'){echo "selected";}?>>Tickets per Client</option>
    </select>
    <input type="submit" name="submit" /><input type="reset" name="reset" />
   </fieldset>
 </div>
</form>

<? if(isset($_POST['submit'])){ 

// Get the report options 
$OptionsQuery = "SELECT 3d,graphWidth,graphHeight,resolution,viewable from ost_reports LIMIT 1";
$OptionsResult = mysql_query($OptionsQuery) or die(mysql_error());
while($graphOptions = mysql_fetch_array($OptionsResult)){

//// Prepare the select query depending on the report we want.

// Report type department
// Probably not as clean as it could be but.... it works.
// I'm not using the closed count but I'm leaving it here in case anyone wants to easily reference it.

if($_POST['type'] == 'tixPerClient'){
$qselect = "SELECT name,email,
            COUNT(DISTINCT(ost_ticket.ticket_id)) AS number,
            COUNT(DISTINCT(CASE WHEN ost_ticket.status='open' THEN ost_ticket.ticket_id END)) as opened,
            COUNT(DISTINCT(CASE WHEN ost_ticket.status='closed' THEN ost_ticket.ticket_id END)) as closed
            FROM ost_ticket";
}
elseif($_POST['type'] == 'repliesPerStaff'){
$qselect = "SELECT
            ost_staff.lastname,ost_staff.firstname,ost_ticket_response.response_id,
	                ost_ticket_response.staff_id,ost_ticket_response.created,
            COUNT(DISTINCT(ost_ticket_response.response_id)) as responses FROM ost_ticket_response
            LEFT JOIN ost_ticket ON ost_ticket.ticket_id=ost_ticket_response.ticket_id
            LEFT JOIN ost_staff ON ost_staff.staff_id=ost_ticket_response.staff_id ";
}
elseif($_POST['type'] == 'tixPerTopic'){
$qselect = "SELECT                                         
            ost_ticket.helptopic,
            COUNT(DISTINCT(ost_ticket.ticket_id)) AS number FROM ost_ticket";
}else{
$qselect = "SELECT
            ROUND(AVG(TIMESTAMPDIFF(HOUR, ost_ticket.created, ost_ticket.closed)),2) AS hoursAVG,
            ROUND(AVG(TIMESTAMPDIFF(DAY, ost_ticket.created, ost_ticket.closed)),2) AS daysAVG,
            ost_ticket.dept_id,ost_ticket.staff_id,ost_staff.staff_id,ost_staff.firstname,ost_staff.lastname,
            ost_ticket.created,ost_ticket.updated,ost_ticket.closed,ost_department.dept_name,
            COUNT(DISTINCT(ost_ticket.ticket_id)) AS number,
            COUNT(DISTINCT(CASE WHEN ost_ticket.status='open' THEN ost_ticket.ticket_id END)) as opened,
            COUNT(DISTINCT(CASE WHEN ost_ticket.status='closed' THEN ost_ticket.ticket_id END)) as closed   
            FROM ost_ticket
            LEFT JOIN ost_ticket_response ON ost_ticket_response.ticket_id=ost_ticket.ticket_id
            LEFT JOIN ost_staff ON ost_ticket.staff_id=ost_staff.staff_id
            LEFT JOIN ost_department ON ost_ticket.dept_id=ost_department.dept_id";
}

// Create CSV file column headers

if($_POST['type'] == 'repliesPerStaff'){
 $fh = fopen($file, 'w') or die("Can't open $file");
 $columnHeaders = "Last Name,First Name,Replies";
 fwrite($fh, $columnHeaders);
}
elseif($_POST['type'] == 'tixPerDept'){
 $fh = fopen($file, 'w') or die("Can't open $file");
 $columnHeaders = "Department,Assigned,Tickets Open,Tickets Closed,Time to Resolution";
 fwrite($fh, $columnHeaders);
}
elseif($_POST['type'] == 'tixPerDay'){
 $fh = fopen($file, 'w') or die("Can't open $file");
 $columnHeaders = "Day,Tickets Created,Tickets Open,Tickets Closed";
 fwrite($fh, $columnHeaders);
}
elseif($_POST['type'] == 'tixPerMonth'){
 $fh = fopen($file, 'w') or die("Can't open $file");
 $columnHeaders = "Month,Tickets Created,Tickets Open,Tickets Closed";
 fwrite($fh, $columnHeaders);
}
elseif($_POST['type'] == 'tixPerStaff'){
 $fh = fopen($file, 'w') or die("Can't open $file");
 $columnHeaders = "Staff,Assigned,Tickets,Tickets Open,Tickets Closed,Time to Resolution";
 fwrite($fh, $columnHeaders);
}
elseif($_POST['type'] == 'tixPerTopic'){
 $fh = fopen($file, 'w') or die("Can't open $file");
 $columnHeaders = "Help Topic,Tickets Created";
 fwrite($fh, $columnHeaders);
}
elseif($_POST['type'] == 'tixPerClient'){
 $fh = fopen($file, 'w') or die("Can't open $file");
 $columnHeaders = "Client,Tickets Created,Tickets Open,Tickets Closed";
 fwrite($fh, $columnHeaders);
}

// Now for the time ranges

if($_POST['dateRange'] == 'timePeriod'){

  // Today
  if($_POST['range'] == 'today'){
  $qwhere = "WHERE ost_ticket.created>=CURDATE() ";
  }

  // Yesterday
  if($_POST['range'] == 'yesterday'){     
  $qwhere = "WHERE ost_ticket.created>=DATE_ADD(CURDATE(), INTERVAL -1 DAY) AND ost_ticket.created<CURDATE()";
  }

  // This month
  if($_POST['range'] == 'thisMonth'){
  $qwhere = "WHERE YEAR(ost_ticket.created) = YEAR(CURDATE()) AND MONTH(ost_ticket.created) >= MONTH(CURDATE())";
  }

  // Last month
  if($_POST['range'] == 'lastMonth'){
  $qwhere = "WHERE YEAR(ost_ticket.created) = YEAR(CURDATE()) AND MONTH(ost_ticket.created) >= MONTH(DATE_ADD(CURDATE(),INTERVAL -1 MONTH)) AND MONTH(ost_ticket.created) < MONTH(CURDATE())";
  }

  // Last 30 days
  if($_POST['range'] == 'lastThirty'){
  $qwhere = "WHERE ost_ticket.created>=DATE_ADD(CURDATE(), INTERVAL -30 DAY) ";
  }

  // This week (Sun-Sat)
  if($_POST['range'] == 'thisWeek'){
  $qwhere = "WHERE ost_ticket.created>=DATE_ADD(CURDATE(), interval(1 - DAYOFWEEK(CURDATE()) ) DAY) AND ost_ticket.created<=DATE_ADD(CURDATE(), interval(7 - DAYOFWEEK(CURDATE()) ) DAY)";
  }

  // Last week (Sun-Sat)
  if($_POST['range'] == 'lastWeek'){
  $qwhere = "WHERE ost_ticket.created>=DATE_ADD(DATE_ADD(CURDATE(), interval(1 - DAYOFWEEK(CURDATE()) ) DAY), INTERVAL - 1 WEEK) AND ost_ticket.created<=DATE_ADD(DATE_ADD(CURDATE(), interval(7 - DAYOFWEEK(CURDATE()) ) DAY), interval  - 1 week)";               
  }

  // Last week (Mon-Fri)
  if($_POST['range'] == 'thisBusWeek'){
  $qwhere = "WHERE ost_ticket.created>=DATE_ADD(CURDATE(), interval(2 - DAYOFWEEK(CURDATE()) ) DAY) AND ost_ticket.created<=DATE_ADD(CURDATE(), interval(6 - DAYOFWEEK(CURDATE()) ) DAY)";
  }

  // Last Business week (Mon-Fri)
  if($_POST['range'] == 'lastBusWeek'){
  $qwhere = "WHERE ost_ticket.created>=DATE_ADD(DATE_ADD(CURDATE(), interval(2 - DAYOFWEEK(CURDATE()) ) DAY), INTERVAL - 1 WEEK) AND ost_ticket.created<=DATE_ADD(DATE_ADD(CURDATE(), interval(6 - DAYOFWEEK(CURDATE()) ) DAY), interval - 1 week)";
  }

  // This year
  if($_POST['range'] == 'thisYear'){
  $qwhere = "WHERE YEAR(ost_ticket.created) = YEAR(CURDATE()) ";
  }

  // Last year
  if($_POST['range'] == 'lastYear'){
  $qwhere = "WHERE YEAR(ost_ticket.created) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 YEAR)) "; 
  }

  // All time
  if($_POST['range'] == 'allTime'){
  $qwhere = "";
  }
} // End timePeriod drop down options


// Specified time range
if($_POST['dateRange'] == 'timeRange'){
  $fromDate = $_POST['fromDate'];
  $toDate = $_POST['toDate'];
  $qwhere = "WHERE ost_ticket.created>=\"$fromDate 00:00:00\" AND ost_ticket.created<=\"$toDate 23:59:59\" ";
}

// Setup groupings

// By department
if($_POST['type'] == 'tixPerDept'){
$qgroup = "GROUP BY ost_department.dept_id ORDER BY number DESC";
}
elseif($_POST['type'] == 'tixPerStaff'){
$qgroup = "GROUP BY ost_staff.staff_id ORDER BY ost_staff.lastname ";
}
elseif($_POST['type'] == 'repliesPerStaff'){
$qgroup = "GROUP BY ost_staff.staff_id ORDER BY ost_staff.lastname ";
}
elseif($_POST['type'] == 'tixPerDay'){
$qgroup = "GROUP BY DATE_FORMAT(ost_ticket.created, '%d %M %Y') ORDER BY ost_ticket.created  ";
}
elseif($_POST['type'] == 'tixPerMonth'){
$qgroup = "GROUP BY DATE_FORMAT(ost_ticket.created, '%M %Y') ORDER BY ost_ticket.created";
}
elseif($_POST['type'] == 'tixPerTopic'){
$qgroup = "GROUP BY ost_ticket.helptopic ORDER BY ost_ticket.helptopic";
}
elseif($_POST['type'] == 'tixPerClient'){
$qgroup = "GROUP BY email ORDER BY number DESC";
}

// Form the entire query
$query="$qselect $qwhere $qgroup";
// echo $query;

// Run the query
$result = mysql_query($query) or die(mysql_error());
$graphResult = mysql_query($query) or die(mysql_error());
// Depending on how many rows we are using lets change our graphic
// Count the rows
$num_rows = mysql_num_rows($graphResult);


?>
<script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript">
    
      // Load the Visualization API and the piechart package.
      google.load('visualization', '1', {'packages':['corechart']});
      
      // Set a callback to run when the Google Visualization API is loaded.
      google.setOnLoadCallback(drawChart);
      
      // Callback that creates and populates a data table, 
      // instantiates the pie chart, passes in the data and
      // draws it.
      function drawChart() {

      // Create our data table.
      var data = new google.visualization.DataTable();
      data.addColumn('string', 'Department');
      data.addColumn('number', 'Tickets');
      data.addRows([

   // Get the total of each ticket category - start with a 0
<?
   $Total = 0;
   $resolutionTotal = 0;
   $responseTotal = 0;
   $closedTotal = 0;
   $openedTotal = 0;

   while($graphRow = mysql_fetch_array($graphResult)){

   // Now add each new row to the total
   $Total += $graphRow['number'];
   $closedTotal += $graphRow['closed'];
   $openedTotal += $graphRow['opened'];

   if($graphOptions['resolution']=='hours'){
   $resolutionTotal += $graphRow['hoursAVG'];
   }
   elseif($graphOptions['resolution']=='days'){
   $resolutionTotal += $graphRow['daysAVG'];
   }

   $responseTotal += $graphRow['responses'];
   $resolutionAVG = round($resolutionTotal/$num_rows,2);


        if($_POST['type'] == 'tixPerDept'){?>
          ['<?=$graphRow['dept_name']?>', <?=$graphRow['number']?>],
          <?}?>

        <?if($_POST['type'] == 'tixPerTopic'){?>
          <? if($graphRow['helptopic']==NULL){ $graphRow['helptopic']='None'; } ?>
           ['<?=$graphRow['helptopic']?>', <?=$graphRow['number']?>],
          <?}?>

        <?if($_POST['type'] == 'tixPerStaff'){
          if($graphRow['staff_id'] == NULL){
          $graphRow['lastname'] = Unassigned;
          $graphRow['firstname'] = Tickets;
          }?>
          ['<?=$graphRow['lastname']?>, <?=$graphRow['firstname']?>', <?=$graphRow['number']?>],
          <?}?>

      <?if($_POST['type'] == 'tixPerDay'){?>
          ['<?=date("F j Y", strtotime($graphRow['created']));?>', <?=$graphRow['number']?>],   
          <?}?>
 
        <?if($_POST['type'] == 'repliesPerStaff'){
        if(($graphRow['lastname'] == NULL) && ($graphRow['firstname'] == NULL)){                     
        $graphRow['lastname'] = Deleted;     
        $graphRow['firstname'] = Staff; 
          }?>
          ['<?=$graphRow['lastname']?>, <?=$graphRow['firstname']?>', <?=$graphRow['responses']?>],
          <?}?>  

      <?if($_POST['type'] == 'tixPerMonth'){?>
          ['<?=date("F, Y", strtotime($graphRow['created']));?>', <?=$graphRow['number']?>],
          <?}?>

      <?if($_POST['type'] == 'tixPerClient'){
      $graphEmail = $graphRow['email']; 
      preg_match('/(([a-z0-9&*\+\-\=\?^_`{|\}~][a-z0-9!#$%&*+-=?^_`{|}~.]*[a-z0-9!#$%&*+-=?^_`{|}~])|[a-z0-9!#$%&*+-?^_`{|}~]|("[^"]+"))\@([-a-z0-9]+\.)+([a-z]{2,})/im', $graphEmail, $graphMatches);
      $graphEmail = $graphMatches[0];
      if($graphEmail == NULL){
       $graphEmail = $graphRow['name'];
      }?>
       ['<?php echo $graphEmail;?>', <?=$graphRow['number']?>],
    <?}?>  


      <?}?>
      <!-- This next entry has to be here or IE will throw a fit and not show graphs, last entry cannot end with a , -->
      ['', 0]
      ]);

      // Instantiate and draw our chart, passing in some options.
      var chart = new google.visualization.PieChart(document.getElementById('chart_div'));
      <? if($graphOptions['3d']=='1'){ $threeD='true'; }else{ $threeD='false'; }?>
      chart.draw(data, {width: <?=$graphOptions['graphWidth'];?>, height: <?=$graphOptions['graphHeight'];?>, is3D: <?=$threeD;?>, sliceVisibilityThreshold: 1/72000});

    }
    </script>

    <!-- Div that will hold the pie chart -->
    <div id="chart_div"></div>

<table class="dtable" width="100%">
<?

// Print out results of query for department reports
 if($graphOptions['resolution']=='hours'){ $time = 'Hours'; }else{ $time = 'Days'; }

if($_POST['type'] == 'tixPerDept'){
 echo "<tr><th>Department</th><th>Assigned</th><th>Tickets Open<th>Tickets Closed</th><th>$time to Resolution (Avg)</th></tr>";
 }
 elseif($_POST['type'] == 'tixPerStaff'){
 echo "<tr><th>Staff</th><th>Assigned</th><th>Tickets Open<th>Tickets Closed</th><th>$time to Resolution (Avg)</th></tr>";
 }
 elseif($_POST['type'] == 'tixPerTopic'){
 echo "<tr><th>Help Topic</th><th>Tickets</th></tr>";
 }
 elseif($_POST['type'] == 'repliesPerStaff'){
 echo "<tr><th>Staff</th><th>Replies</th></tr>";
 }
 elseif($_POST['type'] == 'tixPerDay'){
 echo "<tr><th>Day</th><th>Tickets Created</th><th>Tickets Open<th>Tickets Closed</th></tr>";
 }
 elseif($_POST['type'] == 'tixPerMonth'){
 echo "<tr><th>Month</th><th>Tickets Created</th><th>Tickets Open<th>Tickets Closed</th></tr>";
 }
 elseif($_POST['type'] == 'tixPerClient'){
 echo "<tr><th>Client</th><th>Tickets Created</th><th>Tickets Open<th>Tickets Closed</th></tr>";
}

while($row = mysql_fetch_array($result)){
if($graphOptions['resolution']=='hours'){ $time = $row['hoursAVG']; }elseif($graphOptions['resolution']=='days'){ $time = $row['daysAVG']; }
if($row['open']=='0'){ $row['open']=''; }
if($_POST['type'] == 'tixPerDept'){
echo "<tr style='font-weight: bold;'><td>" . $row['dept_name']. "</td><td>" . $row['number'] ." </td><td>" .$row['opened']. "</td><td>" .$row['closed']. "</td><td>" .$time. "</td></tr> ";

  // Now for the file
 $columnHeaders = "\n" .$row['dept_name']. "," .$row['number']. "," .$row['opened']. "," .$row['closed']. "," .$time;
 fwrite($fh, $columnHeaders);

  }
elseif($_POST['type'] == 'tixPerTopic'){
 if($row['helptopic'] == NULL){
 $row['helptopic'] = None;
}
echo "<tr style='font-weight: bold;'><td>" . $row['helptopic']. "</td><td>" . $row['number'] ." </td></tr> ";;
 
 // Now for the file
 $columnHeaders = "\n" .$row['helptopic']. "," .$row['number'];
 fwrite($fh, $columnHeaders);

 }
elseif($_POST['type'] == 'tixPerStaff'){
 if($row['staff_id'] == NULL){
 $row['lastname'] = Unassigned;
 $row['firstname'] = Tickets;
 }
 echo "<tr style='font-weight: bold;'><td>" . $row['lastname']. ", " .$row['firstname'] . "</td><td>" . $row['number'] ." </td><td>" .$row['opened']. "</td><td>" .$row['closed']. "</td><td>" .$time. "</td></tr> ";

 // Now for the file
 $columnHeaders = "\n" .$row['lastname']. "," .$row['firstname']. "," .$row['number']. "," .$row['opened']. "," .$row['closed']. "," .$time;
 fwrite($fh, $columnHeaders);

  }
elseif($_POST['type'] == 'repliesPerStaff'){
 if($row['lastname'] == NULL){
 $row['lastname'] = Deleted;
 $row['firstname'] = Employee;
 }
 echo "<tr style='font-weight: bold;'><td>" . $row['lastname']. ", " .$row['firstname'] . "</td><td>" . $row['responses'] ." </td></tr> ";

 // Now for the file
 $columnHeaders = "\n" .$row['lastname']. "," .$row['firstname']. "," .$row['responses']; 
 fwrite($fh, $columnHeaders);

  }

elseif($_POST['type'] == 'tixPerClient'){
 $email = $row['email'];
 preg_match('/(([a-z0-9&*\+\-\=\?^_`{|\}~][a-z0-9!#$%&*+-=?^_`{|}~.]*[a-z0-9!#$%&*+-=?^_`{|}~])|[a-z0-9!#$%&*+-?^_`{|}~]|("[^"]+"))\@([-a-z0-9]+\.)+([a-z]{2,})/im', $email, $matches);
 $email = $matches[0];
  if($email == NULL){
 $email = $row['name'];
 }
 echo "<tr style='font-weight: bold;'><td>" . $email. "</td><td>" . $row['number'] ." </td><td>" .$row['opened']. "</td><td>" .$row['closed']. "</td></tr>";

 // Now for the file
 $columnHeaders = "\n" .$email. "," .$row['number']. "," .$row['opened']. "," .$row['closed'];
 fwrite($fh, $columnHeaders);

  }

elseif($_POST['type'] == 'tixPerDay'){
 echo "<tr style='font-weight: bold;'><td>" . date("j F Y", strtotime($row['created'])). "</td><td>" . $row['number'] ." </td><td>" .$row['opened']. "</td><td>" .$row['closed']. "</td></tr> ";

 // Now for the file
 $columnHeaders = "\n" .date("j F Y", strtotime($row['created'])). "," .$row['number']. "," .$row['opened']. "," .$row['closed'];
 fwrite($fh, $columnHeaders);

  }
elseif($_POST['type'] == 'tixPerMonth'){
 echo "<tr style='font-weight: bold;'><td>" . date("F, Y", strtotime($row['created'])). "</td><td>" . $row['number'] ." </td><td>" .$row['opened']. "</td><td>" .$row['closed']. "</td></tr> ";

   // Now for the file
 $columnHeaders = "\n" .date("j F Y", strtotime($row['created'])). "," .$row['number']. "," .$row['opened']. "," .$row['closed'];
 fwrite($fh, $columnHeaders);

  }
 } 
}

?>
<?if($_POST['type'] == 'tixPerDept'){?>
 <tr style='font-weight: bold; background-color: #E0E0E0;'><td>Total</td><td><?=$Total;?></td><td><?=$openedTotal?></td><td><?=$closedTotal?></td><td><?=$resolutionAVG;?></td></tr>

 <? // Now for the file
 $columnHeaders = "\n" . "Total" . "," .$Total. "," .$openedTotal. "," .$closedTotal. "," .$resolutionAVG;
 fwrite($fh, $columnHeaders);
 ?>

 <? }
elseif($_POST['type'] == 'tixPerTopic'){?>
 <tr style='font-weight: bold; background-color: #E0E0E0;'><td>Total</td><td><?=$Total;?></td></tr>

 <? // Now for the file
 $columnHeaders = "\n" . "Total" . "," .$Total;
 fwrite($fh, $columnHeaders);
 ?>

 <? }
elseif($_POST['type'] == 'tixPerStaff'){?>
 <tr style='font-weight: bold; background-color: #E0E0E0;'><td>Total</td><td><?=$Total;?></td><td><?=$openedTotal?></td><td><?=$closedTotal?></td><td><?=$resolutionAVG;?></td></tr>     

 <? // Now for the file
 $columnHeaders = "\n" . "Total" . ",," .$Total. "," .$openedTotal. "," .$closedTotal. "," .$resolutionAVG;
 fwrite($fh, $columnHeaders);
 ?>

 <? }
elseif($_POST['type'] == 'repliesPerStaff'){?>
 <tr style='font-weight: bold; background-color: #E0E0E0;'><td>Total</td><td><?=$responseTotal;?></td></tr>

 <? // Now for the file    
 $columnHeaders = "\n" . "Total" . ",," .$responseTotal;
 fwrite($fh, $columnHeaders);
 ?>

 <? }
elseif($_POST['type'] == 'tixPerDay'){?>
 <tr style='font-weight: bold; background-color: #E0E0E0;'><td>Total</td><td><?=$Total;?></td><td><?=$openedTotal?></td><td><?=$closedTotal?></td></tr>     

 <? // Now for the file
 $columnHeaders = "\n" . "Total" . "," .$Total. "," .$openedTotal. "," .$closedTotal;
 fwrite($fh, $columnHeaders);
 ?>

  <? }
elseif($_POST['type'] == 'tixPerClient'){?>
 <tr style='font-weight: bold; background-color: #E0E0E0;'><td>Total</td><td><?=$Total;?></td><td><?=$openedTotal?></td><td><?=$closedTotal?></td></tr>

 <? // Now for the file
 $columnHeaders = "\n" . "Total" . "," .$Total. "," .$openedTotal. "," .$closedTotal;
 fwrite($fh, $columnHeaders);
 ?> 

 <? }
elseif($_POST['type'] == 'tixPerMonth'){?>
 <tr style='font-weight: bold; background-color: #E0E0E0;'><td>Total</td><td><?=$Total;?></td><td><?=$openedTotal?></td><td><?=$closedTotal?></td></tr>

 <? // Now for the file
 $columnHeaders = "\n" . "Total" . "," .$Total. "," .$openedTotal. "," .$closedTotal;
 fwrite($fh, $columnHeaders);
 ?>

<?}?>

</table>

<div style='float: right;'>
<a href="<?=$file?>" /><img src='images/csv.png' width="50px" height="50px"/></a>
</div>

<br />

<script type="text/javascript">

    function toggle_visibility(id) {
       var e = document.getElementById(id);
       if(e.style.display == 'block')
          e.style.display = 'none';
       else
          e.style.display = 'block';
    }

</script>
<a href="#" onclick="toggle_visibility('legend');" />Show/Hide Legend</a><br /><br />
<div id="legend" style="display:none">
<b>Department</b><br /> Department tickets are assigned to.<br /><br />
<b>Staff</b><br /> Staff member the ticket is assigned to.<br /><br />
<b>Assigned</b><br /> Number of tickets assigned to this department or staff during the given time period.<br /><br />
<b>Tickets Open</b><br /> Number of tickets that are PRESENTLY open that were created during the given time period.<br /><br />
<b>Tickets Created</b><br /> Number of tickets that were created during given time period.<br /><br />
<b>Tickets Closed</b><br /> Number of tickets that were closed during the given time period.<br /><br />
<b>Days/Hours to Resolution</b><br /> Amount of time in hours or days from ticket creation to ticket being closed.
</div>

<?

fclose($fh); // Close up our csv file

} // Close our while loop for getting report options
mysql_close();
require_once(STAFFINC_DIR.'footer.inc.php');

?>
