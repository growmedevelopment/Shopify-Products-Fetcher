<?php

use JetBrains\PhpStorm\NoReturn;

require_once dirname(__DIR__, 1) . '/helpers.php';
// Load the .env file
$env = parse_ini_file(dirname(__DIR__, 2) . '/.env');
/**
 * Generates a CSV file, displays it for download, and deletes it afterward.
 *
 * @param array $products List of products to include in the CSV.
 */
#[NoReturn] function generate_and_display_csv(array $products): void {
  // Set the filename with a timestamp (temporary file)
  $filename = sys_get_temp_dir() . "/products_" . date("Y-m-d_H-i") . ".csv";

  // Define CSV headers based on provided structure
  $csv_array = [
    [
      'id',
      'title',
      'description',
      'link',
      'image_link',
      'availability',
      'price',
      'brand',
      'gtin',
      'condition',
      'google_product_category',
      'custom_label_0',
    ]
  ];

  // Append products to CSV data
  foreach ($products as $product) {

    $prepared_product = flattenProduct($product);

    $csv_array[] = [
      $prepared_product['id'] ?? '',
      $prepared_product['title'] ?? '',
      $prepared_product['description'] ?? '',
      $prepared_product['link'] ?? '',
      $prepared_product['image_link'] ?? '',
      $prepared_product['availability'] ?? '',
      $prepared_product['price'] ?? '',
      $prepared_product['brand'] ?? '',
      $prepared_product['gtin'] ?? '',
      $prepared_product['condition'] ?? 'new',
      $prepared_product['google_product_category'] ?? '',
      $prepared_product['custom_label_0'] ?? '',
    ];
  }

  // Open the temporary CSV file for writing
  $output = fopen($filename, 'w');

  if (!$output) {
    die("Error opening file for writing.");
  }

  // Write CSV rows
  foreach ($csv_array as $row) {
    fputcsv($output, $row);
  }

  // Close file after writing
  fclose($output);

  // Send headers to force CSV download
  header('Content-Type: text/csv; charset=utf-8');
  header("Content-Disposition: attachment; filename=" . basename($filename));
  header('Content-Length: ' . filesize($filename));

  // Read and output the file contents
  readfile($filename);

  // Delete the temporary file after serving it
  unlink($filename);

  exit();
}

try {
  // Fetch products from Shopify API (already returns an array)
  $products = fetch_products($env);

  // Call function to generate and show CSV (without storing it)
  generate_and_display_csv($products);
} catch (Exception $e) {
  log_exception($e); // Log the exception
  http_response_code(500); // Send HTTP 500 error response
}