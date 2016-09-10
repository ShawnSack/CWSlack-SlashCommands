<?php
/* 	
	CWSlack-SlashCommands
    Copyright (C) 2016  jundis

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>. 
*/


ini_set('display_errors', 1); //Display errors in case something occurs
header('Content-Type: application/json'); //Set the header to return JSON, required by Slack
require_once 'config.php';


$apicompanyname = strtolower($companyname); //Company name all lower case for api auth. 
$authorization = base64_encode($apicompanyname . "+" . $apipublickey . ":" . $apiprivatekey); //Encode the API, needed for authorization.

if(empty($_GET['token']) || ($_GET['token'] != $slacktoken)) die; //If Slack token is not correct, kill the connection. This allows only Slack to access the page for security purposes.
if(empty($_GET['text'])) die; //If there is no text added, kill the connection.
$exploded = explode(" ",$_GET['text']); //Explode the string attached to the slash command for use in variables.

//This section checks if the ticket number is not equal to 6 digits (our tickets are in the hundreds of thousands but not near a million yet) and kills the connection if it's not.
if(!is_numeric($exploded[0])) {
	//Check to see if the first command in the text array is actually help, if so redirect to help webpage detailing slash command use.
	if ($exploded[0]=="help") {
		$test=json_encode(array("parse" => "full", "response_type" => "in_channel","text" => "Please visit " . $helpurl . " for more help information","mrkdwn"=>true));
		echo $test;
		return;
	}
	else //Else close the connection.
	{
		echo "Unknown entry for ticket number.";
		return;
	}; 
}
$ticketnumber = $exploded[0]; //Set the ticket number to the first string
$command=NULL; //Create a command variable and set it to Null
$option3=NULL; //Create a option variable and set it to Null
if (array_key_exists(1,$exploded)) //If a second string exists in the slash command array, make it the command.
{
	$command = $exploded[1];
}
if (array_key_exists(2,$exploded)) //If a third string exists in the slash command array, make it the option for the command.
{
	$option3 = $exploded[2];
}
//Set URLs
$urlticketdata = $connectwise . "/v4_6_release/apis/3.0/service/tickets/" . $ticketnumber; //Set ticket API url
$noteurl = $connectwise . "/v4_6_release/apis/3.0/service/tickets/" . $ticketnumber . "/notes?orderBy=id%20desc";
$ticketurl = $connectwise . "/v4_6_release/services/system_io/Service/fv_sr100_request.rails?service_recid="; //Ticket URL for connectwise.
$timeurl = $connectwise . "/v4_6_release/apis/3.0/time/entries?conditions=chargeToId=" . $ticketnumber . "&chargeToType=%27ServiceTicket%27&orderBy=dateEntered%20desc"; //Set the URL required for cURL requests to the time entry API.


//Set noteurl to use ascending if an initial note command is passed.
if($command == "initial" || $command == "first" || $command == "note") 
{
	$noteurl = $connectwise . "/v4_6_release/apis/3.0/service/tickets/" . $ticketnumber . "/notes?orderBy=id%20asc";
}

$utc = time(); //Get the time.
// Authorization array. Auto encodes API key for auhtorization above.
$header_data =array(
 "Authorization: Basic ". $authorization,
);
// Authorization array, with extra json content-type used in patch commands to change tickets.
$header_data2 =array(
"Authorization: Basic " . $authorization,
 "Content-Type: application/json"
);

//Need to create 3 arrays before hand to ensure no errors occur.
$dataTNotes = array();
$dataTData = array();
$dataTCmd = array();

//-
//Ticket data section
//-
$ch = curl_init(); //Initiate a curl session_cache_expire

//Create curl array to set the API url, headers, and necessary flags.
$curlOpts = array(
	CURLOPT_URL => $urlticketdata,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HTTPHEADER => $header_data,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_HEADER => 1,
);
curl_setopt_array($ch, $curlOpts); //Set the curl array to $curlOpts

$answerTData = curl_exec($ch); //Set $answerTData to the curl response to the API.
$headerLen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);  //Get the header length of the curl response
$curlBodyTData = substr($answerTData, $headerLen); //Remove header data from the curl string.

// If there was an error, show it
if (curl_error($ch)) {
	die(curl_error($ch));
}
curl_close($ch);

$dataTData = json_decode($curlBodyTData); //Decode the JSON returned by the CW API.

if($posttext==1) //Block for curl to get latest note
{
	$createdby = "Error"; //Create with error just in case.
	$notetext = "Error"; //Create with error just in case.
	
	$ch1 = curl_init(); //Initiate a curl session_cache_expire

	//Create curl array to set the API url, headers, and necessary flags.
	$curlOpts1 = array(
		CURLOPT_URL => $noteurl,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => $header_data,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HEADER => 1,
	);
	curl_setopt_array($ch1, $curlOpts1); //Set the curl array to $curlOpts

	$answerTNotes = curl_exec($ch1); //Set $answerTData to the curl response to the API.
	$headerLen = curl_getinfo($ch1, CURLINFO_HEADER_SIZE);  //Get the header length of the curl response
	$curlBodyTNotes = substr($answerTNotes, $headerLen); //Remove header data from the curl string.

	// If there was an error, show it
	if (curl_error($ch1)) {
		die(curl_error($ch1));
	}
	curl_close($ch1);

	$dataTNotes = json_decode($curlBodyTNotes); //Decode the JSON returned by the CW API.
	
	//Block for cURL connections to Time Entries API
	$ch2 = curl_init(); //Initiate a curl session

	//Create curl array to set the API url, headers, and necessary flags.
	$curlOpts2 = array(
		CURLOPT_URL => $timeurl,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => $header_data,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HEADER => 1,
	);
	curl_setopt_array($ch2, $curlOpts2); //Set the curl array to $curlOpts

	$answerTimeData = curl_exec($ch2); //Set $answerTData to the curl response to the API.
	$headerLen = curl_getinfo($ch2, CURLINFO_HEADER_SIZE);  //Get the header length of the curl response
	$curlBodyTimeData = substr($answerTimeData, $headerLen); //Remove header data from the curl string.

	// If there was an error, show it
	if (curl_error($ch2)) {
		die(curl_error($ch2));
	}
	curl_close($ch2);

	$dataTimeData = json_decode($curlBodyTimeData); //Decode the JSON returned by the CW API.
	//End time entry block.
	
	if($command == "full" || $command == "notes" || $command == "all")
	{
		$ch1 = curl_init(); //Initiate a curl session_cache_expire
		$noteurl = $connectwise . "/v4_6_release/apis/3.0/service/tickets/" . $ticketnumber . "/notes?orderBy=id%20asc";
		//Create curl array to set the API url, headers, and necessary flags.
		$curlOpts1 = array(
			CURLOPT_URL => $noteurl,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $header_data,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HEADER => 1,
		);
		curl_setopt_array($ch1, $curlOpts1); //Set the curl array to $curlOpts

		$answerTNotes = curl_exec($ch1); //Set $answerTData to the curl response to the API.
		$headerLen = curl_getinfo($ch1, CURLINFO_HEADER_SIZE);  //Get the header length of the curl response
		$curlBodyTNotes = substr($answerTNotes, $headerLen); //Remove header data from the curl string.

		// If there was an error, show it
		if (curl_error($ch1)) {
			die(curl_error($ch1));
		}
		curl_close($ch1);

		$dataTNotes2 = json_decode($curlBodyTNotes); //Decode the JSON returned by the CW API.
	}
	$createdby = $dataTNotes[0]->createdBy; //Set $createdby to the ticket note creator.
	$text = $dataTNotes[0]->text; //Set $text to the ticket text.
	if(array_key_exists(0,$dataTNotes) && array_key_exists(0,$dataTimeData) && $command != "initial" && $command != "first" && $command != "note") //Check if arrays exist properly.
	{
		$timetime = new DateTime($dataTimeData[0]->dateEntered); //Create new time object based on time entry note.
		$notetime = new DateTime($dataTNotes[0]->dateCreated); //Create new datetime object based on ticketnote note.
		
		if($timetime>$notetime) //If the time entry is newer than latest ticket note.
		{
			$createdby = $dataTimeData[0]->enteredBy; //Set $createdby to the time entry creator.
			$text = $dataTimeData[0]->notes; //Set $text to the time entry text.
		}
	}
}
//-
//Priority command
//- 
if($command=="priority") { //Check if the second string in the text array from the slash command is priority
$ch = curl_init();
$priority = "0"; //Set priority = 0.

//Check what $option3 was set to, the third string in the text array from the slash command.
if ($option3 == "moderate") //If moderate
{
	$priority = "4"; //Set to moderate ID
} else if ($option3=="critical")
{
	$priority = "1";
} else if ($option3=="low")
{
	$priority = "3";
}
else //If unknown
{
	echo "Failed to get priority code"; //Send error message. Anything not Slack JSON formatted will return just to the user who submitted the slash command. Don't need to spam errors.
	return;
}
$postfieldspre = array(array("op" => "replace", "path" => "/priority/id", "value" => $priority)); //Command array to replace the priority ID
$postfields = json_encode($postfieldspre); //Format the array as JSON

//Same as previous curl array but includes reequired information for PATCH commands.
$curlOpts = array(
	CURLOPT_URL => $urlticketdata,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HTTPHEADER => $header_data2,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_CUSTOMREQUEST => "PATCH",
	CURLOPT_POSTFIELDS => $postfields,
	CURLOPT_POST => 1,
	CURLOPT_HEADER => 1,
);
curl_setopt_array($ch, $curlOpts);

$answerTCmd = curl_exec($ch);
$headerLen = curl_getinfo($ch, CURLINFO_HEADER_SIZE); 
$curlBodyTCmd = substr($answerTCmd, $headerLen);
// If there was an error, show it
if (curl_error($ch)) {
	die(curl_error($ch));
}
curl_close($ch);
$dataTCmd = json_decode($curlBodyTCmd);
}

//-
//Ticket Status change command.
//-
if($command=="status") {
$ch = curl_init();
$status = "0";
if ($option3 == "scheduled" || $option3=="schedule")
{
	$status = "124";
} else if ($option3=="completed")
{
	$status = "31";
} else if ($option3=="n2s" || $option3=="needtoschedule" || $option3=="ns")
{
	$status = "121";
}
else
{
	echo "Failed to get status code";
	return;
}
$postfieldspre = array(array("op" => "replace", "path" => "/status/id", "value" => $status)); //Command array to replace the status of a ticket.
$postfields = json_encode($postfieldspre);
$curlOpts = array(
	CURLOPT_URL => $urlticketdata,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HTTPHEADER => $header_data2,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_CUSTOMREQUEST => "PATCH",
	CURLOPT_POSTFIELDS => $postfields,
	CURLOPT_POST => 1,
	CURLOPT_HEADER => 1,
);
curl_setopt_array($ch, $curlOpts);

$answerTCmd = curl_exec($ch);
$headerLen = curl_getinfo($ch, CURLINFO_HEADER_SIZE); 
$curlBodyTCmd = substr($answerTCmd, $headerLen);
// If there was an error, show it
if (curl_error($ch)) {
	die(curl_error($ch));
}
curl_close($ch);
$dataTCmd = json_decode($curlBodyTCmd);
}

if(array_key_exists("code",$dataTData)) { //Check if array contains error code
	if($dataTData->code == "NotFound") { //If error code is NotFound
		echo "Connectwise ticket " . $ticketnumber . " was not found."; //Report that the ticket was not found.
		return;
	}
	if($dataTData->code == "Unauthorized") { //If error code is an authorization error
		echo "401 Unauthorized, check API key to ensure it is valid."; //Fail case.
		return;
	}
	else {
		echo "Unknown Error Occurred, check API key and other API settings." . $dataTData->code; //Fail case.
		return;
	}
}
if(array_key_exists("code",$dataTCmd)) { //Check if array contains error code
	if($dataTCmd->code == "NotFound") { //If error code is NotFound
		echo "Connectwise ticket " . $ticketnumber . " was not found."; //Report that the ticket was not found.
		return;
	}
	if($dataTCmd->code == "Unauthorized") { //If error code is an authorization error
		echo "401 Unauthorized, check API key to ensure it is valid."; //Fail case.
		return;
	}
	else {
		echo "Unknown Error Occurred, check API key and other API settings. Error: " . $dataTCmd->code; //Fail case.
		return;
	}
}

$date=strtotime($dataTData->dateEntered); //Convert date entered JSON result to time.
$dateformat=date('m-d-Y g:i:sa',$date); //Convert previously converted time to a better time string.
$return="Nothing!"; //Create return value and set to a basic message just in case.
$contact="None"; //Set None for contact in case no contact exists for "Catch All" tickets.

if(!$dataTData->contact==NULL) { //Check if contact name exists in array.
	$contact = $dataTData->contact->name; //Set contact variable to contact name.
}


if($command == "priority") //If command is priority.
{
	$return =array(
		"parse" => "full", //Parse all text.
		"response_type" => "in_channel", //Send the response in the channel
		"attachments"=>array(array(
			"fallback" => "Info on Ticket #" . $dataTData->id, //Fallback for notifications
			"title" => "Ticket Summary: " . $dataTData->summary, //Set bolded title text
			"pretext" => "Ticket #" . $dataTData->id . "'s priority has been set to " . $option3, //Set pretext
			"text" => "Click <" . $ticketurl . $dataTData -> id . "&companyName" . $companyname . "|here> to open the ticket.", //Set text to be returned
			"mrkdwn_in" => array( //Set markdown values
				"text",
				"pretext"
				)
			))
		);
}
else if($command == "status") //If command is status.
{
	$return =array(
		"parse" => "full",
		"response_type" => "in_channel",
		"attachments"=>array(array(
			"fallback" => "Info on Ticket #" . $dataTData->id, //Fallback for notifications
			"title" => "Ticket Summary: " . $dataTData->summary,
			"pretext" => "Ticket #" . $dataTData->id . "'s status has been set to " . $option3,
			"text" => "Click <" . $ticketurl . $dataTData -> id . "&companyName" . $companyname . "|here> to open the ticket.",
			"mrkdwn_in" => array(
				"text",
				"pretext"
				)
			))
		);
}
else if($command == "initial" || $command == "first" || $command == "note")
{
	if($posttext==0)
	{
		$return =array(
			"parse" => "full",
			"response_type" => "in_channel",
			"attachments"=>array(array(
				"fallback" => "Info on Ticket #" . $dataTData->id, //Fallback for notifications
				"title" => "<" . $ticketurl . $dataTData -> id . "&companyName=" . $companyname . "|#" . $dataTData->id . ">: " . $dataTData->summary, //Return clickable link to ticket with ticket summary.
				"pretext" => "Info on Ticket #" . $dataTData->id, //Return info string with ticket number.
				"text" =>  $dataTData->company->identifier . " / " . $contact . //Return "Company / Contact" string
				"\n" . $dateformat . " | " . $dataTData->status->name . //Return "Date Entered / Status" string
				"\n" . $dataTData->resources . " | Time: " . $dataTData->actualHours . " Hours", //Return assigned resources
				"mrkdwn_in" => array(
					"text",
					"pretext"
					)
				))
			);
	}
	else
	{
		$return =array(
			"parse" => "full",
			"response_type" => "ephemeral",
			"attachments"=>array(array(
				"fallback" => "Info on Ticket #" . $dataTData->id, //Fallback for notifications
				"title" => "<" . $ticketurl . $dataTData -> id . "&companyName=" . $companyname . "|#" . $dataTData->id . ">: " . $dataTData->summary, //Return clickable link to ticket with ticket summary.
				"pretext" => "Info on Ticket #" . $dataTData->id, //Return info string with ticket number.
				"text" =>  $dataTData->company->identifier . " / " . $contact . //Return "Company / Contact" string
				"\n" . $dateformat . " | " . $dataTData->status->name . //Return "Date Entered / Status" string
				"\n" . $dataTData->resources . " | Time: " . $dataTData->actualHours . " Hours", //Return assigned resources
				"mrkdwn_in" => array(
					"text",
					"pretext"
					)
				),
				array(
					"pretext" => "Initial ticket note from: " . $createdby,
					"text" =>  $text,
					"mrkdwn_in" => array(
						"text",
						"pretext",
						"title"
						)
				))
			);

	}
}
else if($command == "full" || $command == "notes" || $command == "all")
{
	if($posttext==0)
	{
		$return =array(
			"parse" => "full",
			"response_type" => "in_channel",
			"attachments"=>array(array(
				"fallback" => "Info on Ticket #" . $dataTData->id, //Fallback for notifications
				"title" => "<" . $ticketurl . $dataTData -> id . "&companyName=" . $companyname . "|#" . $dataTData->id . ">: " . $dataTData->summary, //Return clickable link to ticket with ticket summary.
				"pretext" => "Info on Ticket #" . $dataTData->id, //Return info string with ticket number.
				"text" =>  $dataTData->company->identifier . " / " . $contact . //Return "Company / Contact" string
				"\n" . $dateformat . " | " . $dataTData->status->name . //Return "Date Entered / Status" string
				"\n" . $dataTData->resources . " | Time: " . $dataTData->actualHours . " Hours", //Return assigned resources
				"mrkdwn_in" => array(
					"text",
					"pretext"
					)
				))
			);
	}
	else
	{
		$return =array(
			"parse" => "full",
			"response_type" => "ephemeral",
			"attachments"=>array(array(
				"fallback" => "Info on Ticket #" . $dataTData->id, //Fallback for notifications
				"title" => "<" . $ticketurl . $dataTData -> id . "&companyName=" . $companyname . "|#" . $dataTData->id . ">: " . $dataTData->summary, //Return clickable link to ticket with ticket summary.
				"pretext" => "Info on Ticket #" . $dataTData->id, //Return info string with ticket number.
				"text" =>  $dataTData->company->identifier . " / " . $contact . //Return "Company / Contact" string
				"\n" . $dateformat . " | " . $dataTData->status->name . //Return "Date Entered / Status" string
				"\n" . $dataTData->resources . " | Time: " . $dataTData->actualHours . " Hours", //Return assigned resources
				"mrkdwn_in" => array(
					"text",
					"pretext"
					)
				),
				array(
					"pretext" => "Latest Note from: " . $createdby,
					"text" =>  $text,
					"mrkdwn_in" => array(
						"text",
						"pretext",
						"title"
						)
				),
				array(
					"pretext" => "Initial ticket note from: " . $dataTNotes2[0]->createdBy,
					"text" =>  $dataTNotes2[0]->text,
					"mrkdwn_in" => array(
						"text",
						"pretext",
						"title"
						)
				))
			);

	}
}
else //If no command is set, or if it's just random gibberish after ticket number.
{
	if($posttext==0)
	{
		$return =array(
			"parse" => "full",
			"response_type" => "in_channel",
			"attachments"=>array(array(
				"fallback" => "Info on Ticket #" . $dataTData->id, //Fallback for notifications
				"title" => "<" . $ticketurl . $dataTData -> id . "&companyName=" . $companyname . "|#" . $dataTData->id . ">: " . $dataTData->summary, //Return clickable link to ticket with ticket summary.
				"pretext" => "Info on Ticket #" . $dataTData->id, //Return info string with ticket number.
				"text" =>  $dataTData->company->identifier . " / " . $contact . //Return "Company / Contact" string
				"\n" . $dateformat . " | " . $dataTData->status->name . //Return "Date Entered / Status" string
				"\n" . $dataTData->resources . " | Time: " . $dataTData->actualHours . " Hours", //Return assigned resources
				"mrkdwn_in" => array(
					"text",
					"pretext"
					)
				))
			);
	}
	else
	{
		$return =array(
			"parse" => "full",
			"response_type" => "in_channel",
			"attachments"=>array(array(
				"fallback" => "Info on Ticket #" . $dataTData->id, //Fallback for notifications
				"title" => "<" . $ticketurl . $dataTData -> id . "&companyName=" . $companyname . "|#" . $dataTData->id . ">: " . $dataTData->summary, //Return clickable link to ticket with ticket summary.
				"pretext" => "Info on Ticket #" . $dataTData->id, //Return info string with ticket number.
				"text" =>  $dataTData->company->identifier . " / " . $contact . //Return "Company / Contact" string
				"\n" . $dateformat . " | " . $dataTData->status->name . //Return "Date Entered / Status" string
				"\n" . $dataTData->resources . " | Time: " . $dataTData->actualHours . " Hours", //Return assigned resources
				"mrkdwn_in" => array(
					"text",
					"pretext"
					)
				),
				array(
					"pretext" => "Latest Note from: " . $createdby,
					"text" =>  $text,
					"mrkdwn_in" => array(
						"text",
						"pretext",
						"title"
						)
				))
			);

	}
}

echo json_encode($return, JSON_PRETTY_PRINT); //Return properly encoded arrays in JSON for Slack parsing.
?>
