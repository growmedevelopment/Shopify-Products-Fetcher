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
 * Fetches all products from Shopify using concurrent requests.
 */
function fetch_products(array $env): array {
  $graphql_endpoint = "https://barbecues-galore.myshopify.com/admin/api/2025-01/graphql.json";
  $access_token = $env['SHOPIFY_ACCESS_TOKEN'];
  $all_products = [];

  // Start with an initial cursor
  $cursors = [null];
  $active_requests = 10; // Number of concurrent requests

  try {
    while (!empty($cursors)) {
      $multiHandle = curl_multi_init();
      $handles = [];

      // Create up to $active_requests concurrent requests
      for ($i = 0; $i < $active_requests && !empty($cursors); $i++) {
        $cursor = array_shift($cursors);
        $ch = createShopifyCurlHandle($graphql_endpoint, $access_token, $cursor);
        curl_multi_add_handle($multiHandle, $ch);
        $handles[] = $ch;
      }

      // Execute all requests simultaneously
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

        // Handle API errors
        if (isset($data['errors'])) {
          throw new Exception("Shopify API Error: " . json_encode($data['errors']));
        }

        // Store retrieved products
        foreach ($data['data']['products']['edges'] ?? [] as $productEdge) {
          $all_products[] = $productEdge['node'];
        }

        // Add new cursors if more pages exist
        if ($data['data']['products']['pageInfo']['hasNextPage'] ?? false) {
          $cursors[] = $data['data']['products']['pageInfo']['endCursor'];
        }
      }

      curl_multi_close($multiHandle);
    }
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
    query ProductsQuery {
        products(first: 15) {  # Fetch ONLY 5 products
            edges { 
                node { 
                    id 
                    title 
                    handle 
                    descriptionHtml 
                    vendor 
                    productType 
                    images(first: 1) { edges { node { url } } } 
                    variants(first: 2) { edges { node { price inventoryQuantity sku image { url } } } } 
                } 
            }
        }
    }
    GQL;


  //  $query = <<<GQL
//    query ProductsQuery(\$cursor: String) {
//        products(first: 250, after: \$cursor) {
//            edges {
//                node {
//                    id title handle descriptionHtml vendor productType
//                    images(first: 1) { edges { node { url } } }
//                    variants(first: 2) { edges { node { price inventoryQuantity sku image { url } } } }
//                }
//            }
//            pageInfo { hasNextPage endCursor }
//        }
//    }
//    GQL;
//
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
 * Removes "gid://shopify/Product/" from a given string.
 *
 * @param string $gid The Shopify GID string.
 * @return string The cleaned ID without prefix.
 */
function cleanShopifyGid(string $gid): string {
  return str_replace("gid://shopify/Product/", "", $gid);
}

/**
 * Flattens a Shopify product response into a simple array.
 */
function flattenProduct(array $productNode): array {
  $price = !empty($productNode['variants']['edges'][0]['node']['price']) ? floatval($productNode['variants']['edges'][0]['node']['price']) : 0;

  return [
    'id' => cleanShopifyGid($productNode['id']) ?? '',
    'title' => $productNode['title'] ?? '',
    'description' => cleanAndTrimText($productNode['descriptionHtml'] ?? ''),
    'link' => isset($productNode['handle']) ? "https://barbecuesgalore.ca/products/" . $productNode['handle'] : '',
    'image_link' => $productNode['images']['edges'][0]['node']['url'] ?? '',
    'availability' => ($productNode['variants']['edges'][0]['node']['inventoryQuantity'] ?? 0) > 0 ? 'in_stock' : 'out_of_stock',
    'price' => $price,
    'brand' => $productNode['vendor'] ?? '',
    'sku' => $productNode['variants']['edges'][0]['node']['sku'] ?? '',
    'condition' => 'new',
    'product_type' => $productNode['productType'] ?? '',
    'custom_label_0' => getCustomLabel($productNode['productType']) ?? '',
  ];
}

/**
 * Flattens all products in the array.
 */
function createProductsArray(array $products): array {
  return array_map('flattenProduct', $products);
}