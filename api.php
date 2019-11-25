<?php
/**
 * This is where the request is sent to the API endpoint using guzzlehttp since I could not send the
 * request via AJAX. So here is it, The request is sent here, from here sent to http://api.clicknship.com.ng/
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
require __DIR__.'/vendor/autoload.php';

if(isset($_POST['endpoint']) && isset($_POST['method'])){
    try{
        $authorization = null;
        $client = new GuzzleHttp\Client(['base_uri' => 'http://api.clicknship.com.ng/']);
        $response = $client->post('Token', [
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'form_params' => [
                /**
                 * This is a demo credential provided by clickNship API.
                 */
                'username' => 'cnsdemoapiacct',
                'password' => 'ClickNShip$12345',
                'grant_type' => 'password'
            ]
        ]);
        if($response->getStatusCode() == 200){
          $authorization = json_decode($response->getBody());
          $headers = [
              'Authorization' => 'Bearer '.$authorization->access_token,
              'Content-Type' => isset($_POST['header_content_type']) ? $_POST['header_content_type'] : 'application/json'
            ];
          $request = $client->request($_POST['method'],$_POST['endpoint'], [
            'http_errors' => false,
            'headers' => $headers,
           // 'form_params' => ['Origin' => 'IBADAN', 'Destination' => 'ABUJA']
         'form_params' => $_POST,
          ]);
        echo $request->getBody();
    }
        
    }
    catch(Exception $e){
        echo ['exception' => $e->getMessage()];
    }

}
?>