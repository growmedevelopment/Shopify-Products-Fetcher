<?php

/**
 * Logs exceptions to a file.
 */
function log_exception(Exception $e): void {
  $log_file = __DIR__ . DIRECTORY_SEPARATOR . 'error_log.txt';
  $error_message = "[" . date('Y-m-d H:i:s') . "] " . $e->getMessage() . PHP_EOL;
  file_put_contents($log_file, $error_message, FILE_APPEND);
}


/**
 * Fetches product data from the Shopify API.
 */
function fetch_products(array $env): array {
  $graphql_endpoint = "https://barbecues-galore.myshopify.com/admin/api/2025-01/graphql.json";
  $access_token = $env['SHOPIFY_ACCESS_TOKEN'];
  $all_products = [];

  // Track cursor positions for pagination
  $cursors = [null];
  $active_requests = 5; // Number of concurrent requests

  try {
    $multiHandle = curl_multi_init();
    $handles = [];

    // Create initial batch of requests
    for ($i = 0; $i < $active_requests; $i++) {
      if (!empty($cursors)) {
        $cursor = array_shift($cursors);
        $ch = createShopifyCurlHandle($graphql_endpoint, $access_token, $cursor);
        curl_multi_add_handle($multiHandle, $ch);
        $handles[] = $ch;
      }
    }

    do {
      $status = curl_multi_exec($multiHandle, $running);
      curl_multi_select($multiHandle);
    } while ($running > 0);

    // Process responses
    foreach ($handles as $ch) {
      $response = curl_multi_getcontent($ch);
      curl_multi_remove_handle($multiHandle, $ch);
      curl_close($ch);

      $data = json_decode($response, true);

      // Handle errors
      if (isset($data['errors'])) {
        throw new Exception("Shopify API Error: " . json_encode($data['errors']));
      }

      // Store products
      foreach ($data['data']['products']['edges'] ?? [] as $productEdge) {
        $all_products[] = $productEdge['node'];
      }

      // Add next page cursor if available
      if ($data['data']['products']['pageInfo']['hasNextPage'] ?? false) {
        $cursors[] = $data['data']['products']['pageInfo']['endCursor'];
      }
    }

    curl_multi_close($multiHandle);
  } catch (Exception $e) {
    log_exception($e);
    return [];
  }

  return $all_products;
}
/**
 * Creates a Shopify GraphQL cURL handle.
 */
function createShopifyCurlHandle(string $endpoint, string $token, ?string $cursor): CurlHandle|false {
  $query = <<<GQL
    query ProductsQuery(\$cursor: String) {
        products(first: 250, after: \$cursor) {
            edges { node { id title handle descriptionHtml vendor productType images(first: 1) { edges { node { url } } } variants(first: 10) { edges { node { price inventoryQuantity sku image { url } } } } } }
            pageInfo { hasNextPage endCursor }
        }
    }
    GQL;

  $variables = ['cursor' => $cursor];
  $ch = curl_init($endpoint);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      "Content-Type: application/json",
      "X-Shopify-Access-Token: $token",
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(["query" => $query, "variables" => $variables]),
  ]);

  return $ch;
}

/**
 * Cleans and trims text, removes HTML, and limits length.
 */
function cleanAndTrimText(string $html, int $maxLength = 5000): string {
  return trim(mb_substr(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, $maxLength), "\n");
}

/**
 * Assigns a custom label based on product type.
 *
 * @param string|null $productType The product type/category.
 * @return string The corresponding custom label.
 */
function getCustomLabel(?string $productType): string {
  if (!$productType) {
    return 'unknown'; // Default label for missing product type
  }

  // Define product type to label mappings
  $customLabels = [
    'Thermometers' => 'Thermometers',
    'Tools & Gadgets' => 'Tools and gadgets',
    'Covers & Mats > Covers' => 'Barbecue covers',
    'Seasonings' => 'label4',
    'Sauces & Spices' => 'label5',
    'Charcoal Accessories > Fire Starters' => 'label6',
    'Charcoal Accessories > Heat Diffusers' => 'label6',
  ];

  // Check for a match and return the corresponding label
  foreach ($customLabels as $keyword => $label) {
    if (stripos($productType, $keyword) !== false) {
      return $label;
    }
  }

  return 'unknown'; // Default if no match is found
}

/**
 * Flattens a Shopify product response into a simple array.
 */
function flattenProduct(array $productNode): array {
  return [
    'id' => $productNode['id'] ?? '',
    'title' => $productNode['title'] ?? '',
    'description' => cleanAndTrimText($productNode['descriptionHtml'] ?? ''),
    'link' => isset($productNode['handle']) ? "https://barbecuesgalore.ca/products/" . $productNode['handle'] : '',
    'image_link' => $productNode['images']['edges'][0]['node']['url'] ?? '',
    'availability' => ($productNode['variants']['edges'][0]['node']['inventoryQuantity'] ?? 0) > 0 ? 'in_stock' : 'out_of_stock',
    'price' => $productNode['variants']['edges'][0]['node']['price'] ?? '',
    'brand' => $productNode['vendor'] ?? '',
    'gtin' => $productNode['variants']['edges'][0]['node']['sku'] ?? '',
    'condition' => 'new',
    'google_product_category' => $productNode['productType'] ?? '',
    'custom_label_0' => getCustomLabel($productNode['productType']),
  ];
}

/**
 * Flattens all products in the array.
 */
function createProductsArray(array $products): array {
  return array_map('flattenProduct', $products);
}

/**
 * Creates, saves, and displays a CSV file.
 */
function createAndDisplayCsvFile(string $file_name, array $data): void {
  try {
    $file_path = __DIR__ . DIRECTORY_SEPARATOR . $file_name;
    $file = fopen($file_path, 'w');

    if (!$file) {
      throw new Exception('Unable to open the file for writing.');
    }

    // Add UTF-8 BOM for Excel compatibility
    fwrite($file, "\xEF\xBB\xBF");

    if (!empty($data) && is_array($data[0]) && array_keys($data[0]) !== range(0, count($data[0]) - 1)) {
      fputcsv($file, array_keys($data[0]));
    }

    foreach ($data as $row) {
      if (!fputcsv($file, $row)) {
        throw new Exception('Error writing data to CSV file.');
      }
    }

    fclose($file);

    if (file_exists($file_path)) {
      header('Content-Type: text/csv');
      header('Content-Disposition: inline; filename="' . basename($file_name) . '"');
      readfile($file_path);
    } else {
      throw new Exception('CSV file does not exist after writing.');
    }
  } catch (Exception $e) {
    log_exception($e);
    http_response_code(500);
  }
}