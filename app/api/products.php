<?php

// Allow from any origin
header("Access-Control-Allow-Origin: *");

// Allow specific HTTP methods (GET, POST, PUT, DELETE, OPTIONS)
header("Access-Control-Allow-Methods: GET");

// Allow specific headers
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header('Content-Type: application/json');
// Optional: If your server allows credentials, add this line


// Handle preflight requests (OPTIONS method)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  // Send appropriate headers and exit for preflight
  header("HTTP/1.1 200 OK");
  exit();
}

require_once dirname(__DIR__, 1) . '/backend/helpers.php';

try {
  // Fetch the data from Algolia API
  $products = fetch_products();

  // Prepare the response with the received data
  $response = [
    'status' => 'success',
    'code'=> 200,
    'products' => $products,
    'message' => 'POST data received successfully'
  ];

} catch (Exception $e) {
  $response = [
    'status' => 'error',
    'code'=> $e->getCode(),
    'products' => array(),
    'message' => $e->getMessage(),
  ];
}

echo json_encode($response);