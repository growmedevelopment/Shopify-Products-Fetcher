<?php
require_once dirname(__DIR__, 1) . '/helpers.php';


//Function to prepare CSV file
function prepare_csv_file(array $data):array {
  $csv_array = [
    [
    'id',
    'title',
    'description',
    'link',
    'image_link',
    'price',
    'availability',
    'brand',
    'sku',

      //todo need attention
      'custom_label_0', //All Accessories,
      'custom_label_1', //
      'custom_label_2', //
      'custom_label_3', //
      'product_type', //Home & Garden > Grills . Accessories
      'gtin'

    ] // Header row
  ];

  foreach ($data as $row) {

    $csv_array[] = [
      'title' => $row[''],
      'id' => $row[''],
      //todo need to fill out it
    ];

  }
  return $csv_array;
}


try {
  // Fetch the products from Shopify API
  $products = fetch_products();

  //Create CSV
  $csv_data = prepare_csv_file($products);


  // File name for the CSV
  $fileName = 'inventory-update_' . date('Y-m-d') . '.csv';

  // Call the function to create and display the CSV
  create_and_display_csv_file('$fileName', $csv_data);

} catch (Exception $e) {
  log_exception($e); // Log the exception
  http_response_code(500); // Send an HTTP 500 Internal Server Error response
}