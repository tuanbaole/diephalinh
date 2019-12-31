<?php

require_once plugin_dir_path(__FILE__) . 'cf7gsc/Client.php';
include_once ( plugin_dir_path(__FILE__) . 'cf7gsc/autoload.php' );
include_once ( plugin_dir_path(__FILE__) . 'spreadsheet_autoload.php' );

use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;

class cf7gsc_googlesheet {

   private $token;
   private $spreadsheet;
   private $worksheet;

   const clientId = '1021473022177-agam4fkd36jkefe9ru8bvrsrara7b7s3.apps.googleusercontent.com';
   const clientSecret = 'TdJm0Dg8xe5VleeqqZOdH_Yo';
   const redirect = 'urn:ietf:wg:oauth:2.0:oob';

   public function __construct() {
      
   }

   //constructed on call
   public static function preauth($access_code) {
      $client = new cf7gsc_Client();
      $client->setClientId(cf7gsc_googlesheet::clientId);
      $client->setClientSecret(cf7gsc_googlesheet::clientSecret);
      $client->setRedirectUri(cf7gsc_googlesheet::redirect);
      $client->setScopes(array('https://spreadsheets.google.com/feeds'));
      $results = $client->authenticate($access_code);
      $tokenData = json_decode($client->getAccessToken(), true);
      cf7gsc_googlesheet::updateToken($tokenData);
   }

   public static function updateToken($tokenData) {
      $tokenData['expire'] = time() + intval($tokenData['expires_in']);
      try {
         $tokenJson = json_encode($tokenData);
         update_option('gs_token', $tokenJson);
      } catch (Exception $e) {
         Gs_Connector_Utility::gs_debug_log("Token write fail! - " . $e->getMessage());
      }
   }

   public function auth() {
      $tokenData = json_decode(get_option('gs_token'), true);

      if (time() > $tokenData['expire']) {
         $client = new cf7gsc_Client();
         $client->setClientId(cf7gsc_googlesheet::clientId);
         $client->setClientSecret(cf7gsc_googlesheet::clientSecret);
         $client->refreshToken($tokenData['refresh_token']);
         $tokenData = array_merge($tokenData, json_decode($client->getAccessToken(), true));
         cf7gsc_googlesheet::updateToken($tokenData);
      }

      /* this is needed */
      $serviceRequest = new DefaultServiceRequest($tokenData['access_token']);
      ServiceRequestFactory::setInstance($serviceRequest);
   }

   //preg_match is a key of error handle in this case
   public function settitleSpreadsheet($title) {
      $this->spreadsheet = $title;
   }

   //finished setting the title
   public function settitleWorksheet($title) {
      $this->worksheet = $title;
   }

   //choosing the worksheet
   public function add_row($data) {
      $spreadsheetService = new Google\Spreadsheet\SpreadsheetService();
      $spreadsheetFeed = $spreadsheetService->getSpreadsheets();
      $spreadsheet = $spreadsheetFeed->getByTitle($this->spreadsheet);
      $worksheetFeed = $spreadsheet->getWorksheets();
      $worksheet = $worksheetFeed->getByTitle($this->worksheet);
      // if worksheet feeds data is not empty
      if (!empty($worksheet)) {
         $listFeed = $worksheet->getListFeed();
         $listFeed->insert($data);
      }
   }

   }

?>