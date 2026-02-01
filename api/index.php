<?php
error_reporting(E_ALL);
ini_set('display_errors',1);
const CREATE_USER_URL = 'https://api-eval.signnow.com/oauth2/token';
const CLIENT_ID="";
const CLIENT_SECRET="";
// Can be found in email sent after requesting an API key at https://university.cudasign.com/api/
$encoded_credentials ="ZjdjZmMyNzhjZDc4Njg0YWE2YWNkNzRiMmYwOTg1NDM6MjZkZTE3NjUxY2JhOWYwODRmMGYwZmU0OWVhNGQyOTk=";
// If you are on production using your own client_id and client_secret.
// $encoded_credentials = base64_encode(CLIENT_ID.":".CLIENT_SECRET);
//print_r(rawurlencode($encoded_credentials));
print_r("\n");
print_r($encoded_credentials);
$headers = array('Accept:application/json','Authorization: Basic ' . $encoded_credentials);
$username = "dm_n@mail.ru";
$password = "mb8941";
$first_name = "Dmitry";
$last_name = "Naydenov";
$param = array('email' => $username,'password' => $password, 'first_name' => $first_name, 'last_name' => $last_name);
$handle = curl_init();
curl_setopt($handle, CURLOPT_URL, CREATE_USER_URL);
curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($handle, CURLOPT_POST, true);
curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($param));
print_r("\n");
$response = curl_exec($handle);
print_r("\n");
print_r("\n");
print_r($response);
?>