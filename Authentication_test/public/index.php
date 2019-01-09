<?php

session_start();
$checkedInUsers = loadCheckedInUsers();


  define( 'SESSION_DURATION_SECONDS' , '60' );
  define( 'COOKIE_DURATION_SECONDS' , '3600' );


// *** load the currently checked in users
function loadCheckedInUsers() {
  $result = Array();
  $checkedInUsers = file_exists('checkedInUsers.txt') ? json_decode(file_get_contents('checkedInUsers.txt'), true) : Array();

  if(empty($checkedInUsers))
    return $checkedInUsers;

  foreach ($checkedInUsers as $user) { 
    if( $user['expiry'] - time() > 0) { // only include if not expired
      $result[] = $user;
    } else {
      echo "expired session:" . $user['user_id'] . "<br/><br/>";
    }
  }

  if(empty($result))
    $result = Array();

  file_put_contents('checkedInUsers.txt', json_encode($result));

  return $result;
}

// Close out the session
function endSession() {
  session_unset();
  $_COOKIE["user_id"] = "";
  $_SESSION['user_id'] = "";
  setcookie("user_id", NULL, time()-COOKIE_DURATION_SECONDS, "/", "", 0, 0);
}

// *** Logout
function logout($user_id, $checkedInUsers) {
  checkOutUser($user_id, $checkedInUsers);
  endSession();
  return "";
}

// *** Login
function login($user_id, $checkedInUsers) {
  $_SESSION["user_id"] = $user_id; // set when succesfully logged in
  $hashed_user_id = hash("sha256", $user_id, false);
  $_COOKIE["user_id"] = $hashed_user_id;
  setcookie("user_id", $hashed_user_id, time()+COOKIE_DURATION_SECONDS, "/", "", 0, 0);
  checkInUser($user_id, $checkedInUsers);
  return $hashed_user_id;
}

function checkInUser($user_id, $checkedInUsers) {
  if(!isUserCheckedIn($user_id, $checkedInUsers)) {
    $checkedInUsers[] = [ 'user_id' => $user_id, 'expiry' => time() + SESSION_DURATION_SECONDS ];
    file_put_contents('checkedInUsers.txt', json_encode($checkedInUsers));
  } else {
    renewCheckedInStatus($user_id, $checkedInUsers);
  }
}

function checkOutUser($user_id, $checkedInUsers) {
  if(empty($user_id))
    return;
  if(empty($checkedInUsers))
    return;

  $checkedInUsers = array_diff_user_id_values($checkedInUsers, $user_id );
  file_put_contents('checkedInUsers.txt', json_encode($checkedInUsers));
}

function isUserCheckedIn($user_id, $checkedInUsers) {
  if(empty($checkedInUsers) ) 
    return false;

  foreach($checkedInUsers as $values) {
    if ($values['user_id'] === $user_id )
      return true;
  }
  return false;
}

function getCheckedInUserData($user_id, $checkedInUsers) {
  if(empty($checkedInUsers) ) 
    return null;

  foreach($checkedInUsers as $values) {
    if ($values['user_id'] === $user_id )
      return $values;
  }
  return null;
}

// Remove any users that contain the $user_id 
function array_diff_user_id_values($checkedInUsers, $user_id) 
{ 
  $result = array(); 
  foreach($checkedInUsers as $values) {
    if( $values['user_id'] !== $user_id ) 
      $result[] = $values; 
  }
  return $result; 
} 

function renewCheckedInStatus($user_id, $checkedInUsers){
  $i=0;
  foreach($checkedInUsers as $values) {
    if ($values['user_id'] === $user_id )
      $checkedInUsers[$i]['expiry'] = time() + SESSION_DURATION_SECONDS;
    $i++;
  }
  file_put_contents('checkedInUsers.txt', json_encode($checkedInUsers));
}

// **********************************************************************
// COMMANDS HERE : Login / logout
// $hashed_user_id = login("admin", $checkedInUsers); // LOGIN from login form
// isset($_SESSION["user_id"]) ? logout($_SESSION["user_id"] , $checkedInUsers) : null; // Logout


echo "checkedInUsers.txt:<br/>";
if (file_exists('checkedInUsers.txt'))
   print_r(json_decode(file_get_contents('checkedInUsers.txt'), true));
echo "<br/><br/>";

// reload the currently active users after a login
$checkedInUsers = loadCheckedInUsers();

// *** Check login
$session_user_id = isset($_SESSION["user_id"]) ? $_SESSION["user_id"] : "";
$cookie_id_hash = isset($_COOKIE["user_id"]) ? $_COOKIE["user_id"] : "";

echo "session_user_id = " . $session_user_id . "<br/><br/>";

// Does hashed user_id from client match our user_id for this session?
if ( hash("sha256", $session_user_id, false) === $cookie_id_hash ) {
    if (isUserCheckedIn($session_user_id, $checkedInUsers))  {
      echo "Hashes matched, logged in as: ". $session_user_id .".<br/>";

      // check if session has expired
      $user = getCheckedInUserData($session_user_id, $checkedInUsers);
      if($user !== null) {
        $expire_time = number_format( ( $user['expiry'] - time()) / 60, 2, ".", "");
        echo "Expires in " . $expire_time . " min <br/><br/>";
        if ( $expire_time < 0) {
          echo "Session maximum time has expired!<br/>";
          echo "Logging out this client.<br/>";
          logout($session_user_id, $checkedInUsers);
        } else {
          // Renew this session
          renewCheckedInStatus($session_user_id, $checkedInUsers);
          $checkedInUsers = loadCheckedInUsers();
          echo "Session renewed.<br/>";
        }
      }

    } else {
      echo "Login Hash matched but NOT checked in - possible old open browser or expired session?<br/>";
      echo "Ending session for this client.<br/>";
      endSession();
    }
  } else {
    logout("", $checkedInUsers);
    echo "Logged out<br/>";
}

echo "<br/>";
echo "_COOKIE:";
echo  var_dump($_COOKIE);

echo "-------------------------------------<br/>";

echo "_SESSION:";
echo  var_dump($_SESSION);

?>