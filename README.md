# 🛍️ Shopify Products Fetcher (Barbecues Galore)

This PHP project **fetches product data** from a **Shopify store using the GraphQL API**, processes the data, and exports it into a **CSV file**. The application is containerized with **Docker**, making deployment simple.

## 🚀 Features
- ✅ **Fetches all products** from Shopify using **GraphQL API**
- ✅ **Optimized multi-cURL requests** for faster fetching (3-5x speed boost)
- ✅ **Processes product data** (cleans descriptions, extracts images, assigns labels)
- ✅ **Generates a CSV file** for data export
- ✅ **Containerized with Docker** for easy setup and deployment
- ✅ **Error logging** to track API issues

---

## 📂 Project Structure

---
## 🔧 Shopify API Configuration

To fetch product data from Shopify, you must configure an **API token** by creating a **Custom App** in your Shopify Admin Panel.

### **1️⃣ Create a Shopify Custom App**
1. **Log in** to your Shopify Admin Panel:  
   👉 [https://your-store.myshopify.com/admin](https://your-store.myshopify.com/admin)
2. **Go to** `Apps` > `Develop apps` (You may need to enable app development).
3. **Click "Create an app"** and enter a name (e.g., `Product Fetcher`).
4. **Under "Configuration"**, select:
    - **Admin API access scopes**
    - Enable the following permissions:
        - ✅ `read_products`
        - ✅ `read_inventory`
        - ✅ `read_product_listings`
5. **Click "Save"** and then **"Install App"**.
6. **Copy the Admin API Access Token** (It will be shown only once).


---
## ⚙️ **Setup Instructions**

### 1️⃣ Clone the Repository

git clone https://github.com/growmedevelopment/Shopify-Products-Fetcher.git
cd barbecuesgalore_products

### 2️⃣ Configure Shopify API Credentials

After generating the API token, store it in the **`.env` file** inside the `app/` directory:
```dotenv
    SHOPIFY_ACCESS_TOKEN=your-shopify-access-token
    SHOPIFY_STORE_DOMAIN=your-store.myshopify.com
```


🚀 Make sure to add .env to .gitignore to keep it private!

---

## 🐳 Running with Docker
This project is containerized using Docker. To run the application:

🔹 Start the Docker Containers

```shell
    docker-compose up -d
```

This will:
•	Start a PHP container to run the scripts.
•	Configure the environment automatically.

🔹 Stop Containers
```shell
    docker-compose down
```

---

### 🛠️ Running the Product Fetcher

Once the Docker containers are running, execute:

```shell 
  docker exec -it php-container php /app/backend/google/index.php
```

This will fetch Shopify products and save them as products.csv.

## 🌐 API Endpoint

This project includes an API to **fetch all products** from Shopify.  
The API is accessible at:

```sh
    http://localhost/api/products.php
```

### 🛠️ Example API Response

```json
[
  {
    "id": "gid://shopify/Product/6615735074894",
    "title": "Lynx Professional Power Burner - Natural Gas",
    "handle": "lynx-professional-power-burner-natural-gas",
    "vendor": "LYNX",
    "productType": "Outdoor Cooking > Side Burners",
    "image_link": "https://cdn.shopify.com/...image.jpg",
    "availability": "in_stock",
    "price": "4249.99",
    "custom_label": "label1"
  }
]

```
---

## 🔄 How It Works

### 1️⃣ Fetches product data from Shopify
•	Uses GraphQL API to request up to 250 products per call
•	Supports pagination and multi-threaded requests

### 2️⃣ Processes product data
•	Cleans HTML descriptions
•	Extracts image URLs, variants, and availability
•	Assigns custom labels based on product types

### 3️⃣ Exports the final data to a CSV file
•	Saves as products.csv in the project root
•	Adds a UTF-8 BOM for compatibility with Excel

---

## 📜 Customization Options

Modify Custom Labels

Edit the function getCustomLabel() in helpers.php to change label assignments:

```php
function getCustomLabel(?string $productType): string {
    $labels = [
        'Thermometers' => 'label1',
        'Tools & Gadgets' => 'label2',
        'Covers & Mats > Covers' => 'label4',
    ];
    return $labels[$productType] ?? 'unknown';
}
```

Adjust Product Fields

Modify flattenProduct() to include or remove Shopify product fields:

```php
    return [
        'id' => $productNode['id'] ?? '',
        'title' => $productNode['title'] ?? '',
        'availability' => ($productNode['variants']['edges'][0]['node']['inventoryQuantity'] ?? 0) > 0 ? 'in_stock' : 'out_of_stock',
    ];
```

---

## 🛠️ Troubleshooting

### ❓ Common Issues

If you encounter any issues while running the script, refer to the table below for possible solutions.

| 🛑 Error                              | ✅ Solution                                      |
|--------------------------------------|------------------------------------------------|
| **Empty response from Shopify API**   | Ensure your **API token** and **permissions** are correct. |
| **JSON Decode Error**                 | Verify that **Shopify API** is responding correctly. |
| **CSV File Not Found**                | Ensure the script has **write permissions** in the directory. |
### 📄 Check the Log File
If any errors occur, check error_log.txt for details:

```shell
  cat app/backend/error_log.txt
```
---

## 📜 License

This project is MIT Licensed. Feel free to modify and use it for your Shopify store.

---

## 🤝 Contributing

1.	Fork the repo 🍴
2.	Create a new branch: git checkout -b feature-xyz
3.	Commit your changes: git commit -m "Added XYZ feature"
4.	Push the branch: git push origin feature-xyz
5.	Open a Pull Request
---

##  ⭐ Support & Feedback

If this project helped you, leave a star ⭐ on GitHub!
For any issues or feature requests, open a GitHub issue.

---

