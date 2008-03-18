<?php
/*
	*********************************************************************
	* Copyright by Adiscon GmbH | 2008!									*
	* -> www.phplogcon.org <-											*
	*																	*
	* Use this script at your own risk!									*
	* -----------------------------------------------------------------	*
	* Main Index File													*
	*																	*
	* -> Loads the main PhpLogCon Site									*
	*																	*
	* All directives are explained within this file						*
	*********************************************************************
*/

// *** Default includes	and procedures *** //
define('IN_PHPLOGCON', true);
$gl_root_path = './';
include($gl_root_path . 'include/functions_common.php');
include($gl_root_path . 'include/functions_frontendhelpers.php');
include($gl_root_path . 'include/functions_filters.php');

InitPhpLogCon();
InitSourceConfigs();
InitFrontEndDefaults();	// Only in WebFrontEnd

// Init Langauge first!
IncludeLanguageFile( $gl_root_path . '/lang/' . $LANG . '/main.php' );

// Helpers for frontend filtering!
InitFilterHelpers();	
// ***					*** //

// --- CONTENT Vars
if ( isset($_GET['uid']) ) 
{
	$currentUID = intval($_GET['uid']);
}
else
	$currentUID = UID_UNKNOWN;

// Init Pager variables
$content['uid_previous'] = UID_UNKNOWN;
$content['uid_next'] = UID_UNKNOWN;
$content['uid_first'] = UID_UNKNOWN;
$content['uid_last'] = UID_UNKNOWN;

// Init Sorting variables
$content['sorting'] = "";
$content['searchstr'] = "";

//if ( isset($content['myserver']) ) 
//	$content['TITLE'] = "PhpLogCon :: Home :: Server '" . $content['myserver']['Name'] . "'";	// Title of the Page 
//else
	$content['TITLE'] = "PhpLogCon :: Home";

// Read and process filters from search dialog!
if ( isset($_POST['search']) && $_POST['search'] == $content['LN_SEARCH_PERFORMADVANCED']) 
{
	if ( isset($_POST['filter_datemode']) )
	{
		$filters['filter_datemode'] = intval($_POST['filter_datemode']);
		if ( $filters['filter_datemode'] == DATEMODE_RANGE )
		{
			// Read range values 
			if ( isset($_POST['filter_daterange_from_year']) ) 
				$filters['filter_daterange_from_year'] = intval($_POST['filter_daterange_from_year']);
			if ( isset($_POST['filter_daterange_from_month']) ) 
				$filters['filter_daterange_from_month'] = intval($_POST['filter_daterange_from_month']);
			if ( isset($_POST['filter_daterange_from_day']) ) 
				$filters['filter_daterange_from_day'] = intval($_POST['filter_daterange_from_day']);
			if ( isset($_POST['filter_daterange_to_year']) ) 
				$filters['filter_daterange_to_year'] = intval($_POST['filter_daterange_to_year']);
			if ( isset($_POST['filter_daterange_to_month']) ) 
				$filters['filter_daterange_to_month'] = intval($_POST['filter_daterange_to_month']);
			if ( isset($_POST['filter_daterange_to_day']) ) 
				$filters['filter_daterange_to_day'] = intval($_POST['filter_daterange_to_day']);
			
			// Append to searchstring
			$content['searchstr'] .= "datefrom:" .	$filters['filter_daterange_from_year'] . "-" . 
													$filters['filter_daterange_from_month'] . "-" . 
													$filters['filter_daterange_from_day'] . "T00:00:00 ";
			$content['searchstr'] .= "dateto:" .	$filters['filter_daterange_to_year'] . "-" . 
													$filters['filter_daterange_to_month'] . "-" . 
													$filters['filter_daterange_to_day'] . "T00:00:00 ";

		}
		else if ( $filters['filter_datemode'] == DATEMODE_LASTX )
		{
			if ( isset($_POST['filter_daterange_last_x']) ) 
			{
				$filters['filter_daterange_last_x'] = intval($_POST['filter_daterange_last_x']);
				$content['searchstr'] .= "datefrom:" .	$filters['filter_daterange_last_x'] . " ";
			}
		}
	}

	if ( isset($_POST['filter_facility']) && count($_POST['filter_facility']) < 18 ) // If we have more than 18 elements, this means all facilities are enabled
	{
		$tmpStr = "";
		foreach ($_POST['filter_facility'] as $tmpfacility) 
		{
			if ( strlen($tmpStr) > 0 )
				$tmpStr .= ",";
			$tmpStr .= $tmpfacility;  
		}
		$content['searchstr'] .= "facility:" . $tmpStr . " ";
	}

	if ( isset($_POST['filter_severity']) && count($_POST['filter_severity']) < 7 ) // If we have more than 7 elements, this means all facilities are enabled)
	{
		$tmpStr = "";
		foreach ($_POST['filter_severity'] as $tmpfacility) 
		{
			if ( strlen($tmpStr) > 0 )
				$tmpStr .= ",";
			$tmpStr .= $tmpfacility;  
		}
		$content['searchstr'] .= "severity:" . $tmpStr . " ";
	}

	// Spaces need to be converted!
	if ( isset($_POST['filter_syslogtag']) && strlen($_POST['filter_syslogtag']) > 0 )
	{
		$content['searchstr'] .= "syslogtag:" . $_POST['filter_syslogtag'] . " ";
	}
	
	// Spaces need to be converted!
	if ( isset($_POST['filter_source']) && strlen($_POST['filter_source']) > 0 )
	{
		$content['searchstr'] .= "source:" . $_POST['filter_source'] . " ";
	}
	
	// Message is just appended
	if ( isset($_POST['filter_message']) && strlen($_POST['filter_message']) > 0 )
		$content['searchstr'] .= $_POST['filter_message'];

}

// --- 

// --- BEGIN Custom Code
if ( isset($content['Sources'][$currentSourceID]) && $content['Sources'][$currentSourceID]['SourceType'] == SOURCE_DISK )
{
	require_once($gl_root_path . 'classes/enums.class.php');
	require_once($gl_root_path . 'classes/logstream.class.php');
	require_once($gl_root_path . 'classes/logstreamdisk.class.php');
	require_once($gl_root_path . 'include/constants_errors.php');
	require_once($gl_root_path . 'include/constants_logstream.php');

	// Obtain Config Object
	$stream_config = $content['Sources'][$currentSourceID]['ObjRef'];

	// Create LogStream Object 
	$stream = $stream_config->LogStreamFactory($stream_config);
	$stream->SetFilter($content['searchstr']);
	$stream->Open( array ( SYSLOG_DATE, SYSLOG_FACILITY, SYSLOG_FACILITY_TEXT, SYSLOG_SEVERITY, SYSLOG_SEVERITY_TEXT, SYSLOG_HOST, SYSLOG_SYSLOGTAG, SYSLOG_MESSAGE, SYSLOG_MESSAGETYPE ), true);
	$stream->SetReadDirection(EnumReadDirection::Backward);
	
	$uID = $currentUID;
	$counter = 0;

	while ($stream->ReadNext($uID, $logArray) == SUCCESS && $counter <= 30)
	{
		// Copy Obtained array 
		$content['syslogmessages'][] = $logArray;
		
		// Copy UID
		$content['syslogmessages'][$counter]['UID'] = $uID;

		// Set truncasted message for display
		if ( isset($logArray[SYSLOG_MESSAGE]) )
			$content['syslogmessages'][$counter][SYSLOG_MESSAGETRUNSCATED] = strlen($logArray[SYSLOG_MESSAGE]) > 100 ? substr($logArray[SYSLOG_MESSAGE], 0, 100 ) . " ..." : $logArray[SYSLOG_MESSAGE];
		else
			$content['syslogmessages'][$counter][SYSLOG_MESSAGETRUNSCATED] = "";

		// Increment Counter
		$counter++;
	}

	if ( $stream->ReadNext($uID, $logArray) == SUCCESS ) 
	{
		$content['uid_next'] = $uID;
		// Enable Player Pager
		$content['main_pagerenabled'] = "true";
	}

	// Close file!
	$stream->Close();
}


// DEBUG, create TESTING DATA!
//$content['syslogmessages'][0] = array ( SYSLOG_DATE => "Feb  7 17:56:24", SYSLOG_FACILITY => 0, SYSLOG_FACILITY_TEXT => "kernel", SYSLOG_SEVERITY => 5, SYSLOG_SEVERITY_TEXT => "notice", SYSLOG_HOST => "localhost", SYSLOG_SYSLOGTAG => "RSyslogTest", SYSLOG_MESSAGE => "Kernel log daemon terminating.", SYSLOG_MESSAGETYPE => IUT_Syslog, );
//$content['syslogmessages'][1] = array ( SYSLOG_DATE => "Feb  6 18:56:24", SYSLOG_FACILITY => 0, SYSLOG_FACILITY_TEXT => "kernel", SYSLOG_SEVERITY => 5, SYSLOG_SEVERITY_TEXT => "notice", SYSLOG_HOST => "localhost", SYSLOG_SYSLOGTAG => "RSyslogTest", SYSLOG_MESSAGE => "Kernel log daemon terminating.", SYSLOG_MESSAGETYPE => IUT_Syslog, );

if ( isset($content['syslogmessages']) && count($content['syslogmessages']) > 0 )
{
	// This will enable to Main SyslogView
	$content['syslogmessagesenabled'] = "true";

	for($i = 0; $i < count($content['syslogmessages']); $i++)
	{
		// --- Set CSS Class
		if ( $i % 2 == 0 )
			$content['syslogmessages'][$i]['cssclass'] = "line1";
		else
			$content['syslogmessages'][$i]['cssclass'] = "line2";
		// --- 
	}
}
// --- 

// --- Parsen and Output
InitTemplateParser();
$page -> parser($content, "index.html");
$page -> output(); 
// --- 

?>