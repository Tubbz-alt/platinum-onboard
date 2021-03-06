<?php
//
// Global Variables
//
date_default_timezone_set('EST');
$startdate=date('m/d/Y 00:01');
$enddate=date('m/d/Y 23:59');
$post_header = array(
  "Content-Type: application/vnd.com.cisco.ise.identity.guestuser.2.0+xml",
  "Accept: application/vnd.com.cisco.ise.identity.guestuser.2.0+xml"
);
//
// Start of functions Section
//

//
// Random Password Generator for ISE Guest User
//
function passgen(
    $length,
    $keyspace = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
)
{
    $genpass = '';
    $maxlen = mb_strlen($keyspace, '8bit') - 1;
    for ($i = 0; $i < $length; ++$i)
    {
        $genpass .= $keyspace[random_int(0, $maxlen)];
    }
    return $genpass;
}
//
// API Function for send back to broker
//
function updateAPI($APIurl)
{
  $retmsg='';
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $APIurl);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $result = curl_exec($ch);
  if(curl_errno($ch) !== 0)
  {
      $retmsg= 'cURL error when connecting to ' . $url . ': ' . curl_error($ch);
  }
  else
  {
      echo "\r\nInformation successfully sent to API.\r\n";
  }
  curl_close($ch);
  return $retmsg;
}

//
// API function for ISE cURL for GET
//
function checkISE($url,$headers)
{
  $ch = curl_init();

  // Set URL and other appropriate options
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  // Connect to URL and pull down information from API
  $output = curl_exec($ch);
  if ( ! $output )
  {
    print curl_errno($ch) .':'. curl_error($ch);
  }
  curl_close($ch);
  return $output;
}

//
// API Function for ISE cURL and POST
//
function postISE($url,$post_string,$post_header)
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $post_header);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FAILONERROR, true);
  $output = curl_exec($ch);
  $error_msg = curl_error($ch);
  curl_close($ch);
  return $error_msg;
}

//
// API Function for ISE cURL and PUT
//
function putISE($url,$post_string,$post_header)
{
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
  curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $post_header);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $output = curl_exec($ch);
  $error_msg = curl_error($ch);
  curl_close($ch);
  return $error_msg;
}
//
// End of functions section
//

//
// Start of main
//

//
// Check ISE to see if User exists based on passed email ID
//
if(isset($_SERVER['REQUEST_METHOD'] ))
{
  parse_str($_SERVER['QUERY_STRING'], $output);
	$emailaddy=$output['emailid'];
  $url = "https://python-guest:LkjLkj@192.168.1.129:9060/ers/config/guestuser/?filter=emailAddress.EQ." . $emailaddy;
  $headers = [
    'Accept: application/vnd.com.cisco.ise.identity.guestuser.2.0+xml'
  ];
  //
  // Check ISE to see if User already array_key_exists
  //
  $retOutput=checkISE($url,$headers);
  //
  // Take data and parse it to see if it exists
  //
  $p = xml_parser_create();
  xml_parse_into_struct($p, $retOutput, $vals, $index);
  xml_parser_free($p);
  $passed_id=$vals[2]['attributes']['ID'];
  $passed_email=$vals[2]['attributes']['NAME'];
  if ($passed_email != $emailaddy)
  {
    //
    // Email address doesn't exist, so we need to create
    //
    echo "\r\nCreating User....\r\n\r\n";
	  $passwd=passgen(10);
	  $post_string = '<?xml version="1.0" encoding="utf-8" standalone="yes"?>
	   <ns0:guestuser xmlns:ns0="identity.ers.ise.cisco.com">
	    <customFields>
	    </customFields>
	    <guestAccessInfo>
        <fromDate>'.$startdate.'</fromDate>
      	<location>San Jose</location>
      	<toDate>'.$enddate.'</toDate>
      	<validDays>1</validDays>
	    </guestAccessInfo>
	    <guestInfo>
 	     <emailAddress>'.$emailaddy.'</emailAddress>
 	     <enabled>true</enabled>
 	     <password>'.$passwd.'</password>
 	     <userName>'.$emailaddy.'</userName>
	    </guestInfo>
	    <guestType>Contractor (default)</guestType>
	    <portalId>c945bfc2-f761-11e8-a29a-aa0cee21782f</portalId>
	    <sponsorUserName>python-guest</sponsorUserName>
	   </ns0:guestuser>';
    $url= "https://python-guest:LkjLkj@192.168.1.129:9060/ers/config/guestuser";
    $retError=postISE($url,$post_string,$post_header);
    if (!empty($retError))
	  {
      echo "Error in POST....(".$error_msg.") exiting";
		  exit;
	  }
	  else
	  {
      //
      // Need to  call dbAPI and update status to created
      //
      $dbURL="http://24.239.120.11:9999/api/update-status-guest-account?emailid=".$emailaddy."&status=completed&guestpassword=".$passwd;
      $message=updateAPI($dbURL);
      echo $message;
    }
  }
  elseif ($passed_email == $emailaddy)
  {
    //
    // This ELSEIF means the user already exists as a guest user.  In this case, we need to re-enable the user.
    //
    echo "\r\nRe-enabling the User....\r\n\r\n";
    $status="0";
    $url = "https://python-guest:LkjLkj@192.168.1.129:9060/ers/config/guestuser/" . $passed_id;
    $headers = [
      'Accept: application/vnd.com.cisco.ise.identity.guestuser.2.0+xml'
	  ];
    $retOutput=checkISE($url,$headers);
	  $p = xml_parser_create();
    xml_parse_into_struct($p, $retOutput, $vals, $index);
    xml_parser_free($p);
    if(array_key_exists("value",$vals[20]))
    {
      $status=$vals[20]['value'];
	    if($status == "EXPIRED")
	    {
        //
        //  User exists but is EXPIRED.  So we need to re-enable
        //
        $url = "https://python-guest:LkjLkj@192.168.1.129:9060/ers/config/guestuser/" . $passed_id;
	      $passwd=passgen(10);
	      $post_string = '<?xml version="1.0" encoding="utf-8" standalone="yes"?>
	      <ns0:guestuser xmlns:ns0="identity.ers.ise.cisco.com">
		          <customFields>
		          </customFields>
		          <guestAccessInfo>
		            <fromDate>'.$startdate.'</fromDate>
		            <location>San Jose</location>
		            <toDate>'.$enddate.'</toDate>
		            <validDays>1</validDays>
  		        </guestAccessInfo>
		          <guestInfo>
		            <emailAddress>'.$emailaddy.'</emailAddress>
		            <enabled>true</enabled>
		            <password>'.$passwd.'</password>
		            <userName>'.$emailaddy.'</userName>
		          </guestInfo>
		          <guestType>Contractor (default)</guestType>
		          <portalId>c945bfc2-f761-11e8-a29a-aa0cee21782f</portalId>
		          <sponsorUserName>python-guest</sponsorUserName>
		        </ns0:guestuser>';
        //
        //  Need to update ISE guest user DB now.
        //
        $retError=putISE($url,$post_string,$post_header);
	      if (!empty($retError))
        {
          echo "Error in POST....(".$error_msg.") exiting";
        }
	      else
	      {
          //
          // Need to  call dbAPI and update status to created
          //
	        echo "User Re-enabled.\r\n\r\n";
	        $dbURL="http://24.239.120.11:9999/api/update-status-guest-account?emailid=".$emailaddy."&status=Updated&guestpassword=".$passwd;
          $message=updateAPI($dbURL);
          echo $message;
	      }
	    }
    }
    elseif($status == "AWAITING_INITIAL_LOGIN")
    {
      //
      //  If here, the user already exists and is active.
      //
	    echo "\r\nAccount is not expired and already active\r\n\r\n";
    }
    else
    {
      //
      //  If here, there was an unknown status from ISE.
      //
	    echo "\r\nError in status.  Unknown status found\r\n\r\n";
    }
  }
  else
  {
    //
    //  If here, there was an error with the username compare from the script to ISE.
    //
    echo "\r\nError in username compare.";
  }
}
else
{
  //
  //  If here, there was no email ID that was passed from the broker.
  //
  echo "No email ID passed to the API.";
}
?>
