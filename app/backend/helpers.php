<?php
// Load the .env file
$env = parse_ini_file(dirname(__DIR__, 2) . '/.env');

// Function to log exceptions
function log_exception(Exception $e): void {
  $log_file = __DIR__ . DIRECTORY_SEPARATOR . 'error_log.txt';
  $error_message = "[" . date('Y-m-d H:i:s') . "] " . $e->getMessage() . PHP_EOL;
  file_put_contents($log_file, $error_message, FILE_APPEND);
}


// Function to getting data from Shopify API
function fetch_products(): array {
  // GraphQL Query Template
  global $env;
  $graphql_endpoint = "https://frat-coffee-prod.myshopify.com/admin/api/2024-10/graphql.json";
  $access_token = $env['SHOPIFY_ACCESS_TOKEN'];
  $query_template = <<<GQL
    {
      products(first: 250{after}) {
        edges {
          node {
            id
            title
            handle
            variants(first: 10) {
              edges {
                node {
                  id
                  title
                  price
                  sku
                }
              }
            }
          }
          cursor
        }
        pageInfo {
          hasNextPage
          endCursor
        }
      }
    }
    GQL;
  $all_products = [];
  $cursor = null;

  try {
    do {
      // Replace the placeholder with the actual cursor if available
      $after = $cursor ? ', after: "' . $cursor . '"' : '';
      $query = str_replace("{after}", $after, $query_template);

      // cURL setup
      $ch = curl_init();
      if (!$ch) {
        throw new Exception("Failed to initialize cURL.");
      }

      curl_setopt($ch, CURLOPT_URL, $graphql_endpoint);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "X-Shopify-Access-Token: $access_token"
      ]);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["query" => $query]));

      // Execute the API call
      $response = curl_exec($ch);

      if (curl_errno($ch)) {
        throw new Exception("cURL Error: " . curl_error($ch));
      }

      curl_close($ch);

      if ($response === false) {
        throw new Exception("Empty response from Shopify API.");
      }

      $data = json_decode($response, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON Decode Error: " . json_last_error_msg());
      }

      // Error Handling from Shopify API response
      if (isset($data['errors'])) {
        throw new Exception("Shopify API Error: " . json_encode($data['errors']));
      }

      // Extract and store products
      $products = $data['data']['products']['edges'] ?? [];
      foreach ($products as $productEdge) {
        $all_products[] = $productEdge['node'];
      }

      // Handle Pagination
      $pageInfo = $data['data']['products']['pageInfo'] ?? null;
      $cursor = $pageInfo['hasNextPage'] ? $pageInfo['endCursor'] : null;

    } while ($cursor);
  } catch (Exception $e) {
    log_exception($e); // Log the exception
    return []; // Return an empty array if an error occurs
  }

  return $all_products;
}


// Function to create, overwrite, and display a CSV file
function create_and_display_csv_file(string $file_name, array $data): void {
  try {
    // Define the full path of the file
    $file_path = __DIR__ . DIRECTORY_SEPARATOR . $file_name;

    // Open the file for writing (overwrite if it exists)
    $file = fopen($file_path, 'w');

    if (!$file) {
      throw new Exception('Unable to open the file for writing.');
    }

    // Check if the first row is associative (to add headers)
    if (!empty($data) && is_array($data[0]) && array_keys($data[0]) !== range(0, count($data[0]) - 1)) {
      fputcsv($file, array_keys($data[0])); // Write headers
    }

    // Write each row of data to the file
    foreach ($data as $row) {
      if (!fputcsv($file, $row)) {
        throw new Exception('Error writing data to CSV file.');
      }
    }

    // Close the file after writing
    fclose($file);

    // Read and display the file content in the browser
    if (file_exists($file_path)) {
      // Set headers for CSV display
      header('Content-Type: text/csv');
      header('Content-Disposition: inline; filename="' . basename($file_name) . '"');
      readfile($file_path);
    } else {
      throw new Exception('CSV file does not exist after writing.');
    }
  } catch (Exception $e) {
    log_exception($e); // Log the exception
    http_response_code(500); // Send an HTTP 500 Internal Server Error response
  }
}
