<?php
// core/Inventory.php
/**
 * IQA Metal Store - Core Inventory Service
 * Manages storefront catalog, warehouse synchronization, and product media.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/StoreImageProcessor.php';
require_once __DIR__ . '/UI.php';

class Inventory {
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->ensureSchema();
    }

    /**
     * Self-healing database schema verification
     * Guarantees is_posted column and index exist without manual migrations.
     */
    private function ensureSchema() {
        try {
            $cols = $this->db->query("PRAGMA table_info(inventory)")->fetchAll(PDO::FETCH_ASSOC);
            $names = array_column($cols, 'name');
            if (!empty($names) && !in_array('is_posted', $names)) {
                $this->db->exec("ALTER TABLE inventory ADD COLUMN is_posted INTEGER DEFAULT 0");
                $this->db->exec("CREATE INDEX IF NOT EXISTS idx_inv_is_posted ON inventory(is_posted)");
                $this->db->exec("UPDATE inventory SET is_posted = 1 WHERE user_owner = 'STORE'");
            }
        } catch (Exception $e) {
            // Silently continue if database or table is not ready
        }
    }

    /**
     * Fetch active storefront products (posted items or store-created products)
     *
     * @param string|null $category Filter by hardware sector (e.g. 'Laptops', 'Desktops')
     * @return array Formatted product cards
     */
    public function getProducts($category = null) {
        $sql = "
            SELECT 
                i.id,
                i.sector as category,
                i.brand || ' ' || i.model as title,
                i.specs_json as description,
                i.brand,
                i.model,
                i.price,
                i.quantity,
                i.location_code,
                i.user_owner,
                i.is_posted,
                i.updated_at,
                i.created_at,
                p.optimized_path as image,
                p.thumbnail_path as thumb
            FROM inventory i
            LEFT JOIN location_photos p ON i.location_code = p.location_code
            WHERE (i.is_posted = 1 OR i.user_owner = 'STORE')
        ";

        $params = [];
        if (!empty($category)) {
            $sql .= " AND i.sector LIKE ?";
            $params[] = '%' . $category . '%';
        }

        $sql .= " GROUP BY i.id ORDER BY i.updated_at DESC, i.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->formatProducts($items);
    }

    /**
     * Get available physical warehouse products ready for posting to the storefront
     *
     * @param string|null $sector Optional category filter
     * @param string|null $search Optional search term across brand, model, specs, shelf
     * @return array Formatted warehouse products
     */
    public function getWarehouseAvailableProducts($sector = null, $search = null) {
        $sql = "
            SELECT 
                i.id,
                i.sector as category,
                i.brand || ' ' || i.model as title,
                i.specs_json as description,
                i.brand,
                i.model,
                i.price,
                i.quantity,
                i.location_code,
                i.user_owner,
                i.is_posted,
                i.updated_at,
                i.created_at,
                p.optimized_path as image,
                p.thumbnail_path as thumb
            FROM inventory i
            LEFT JOIN location_photos p ON i.location_code = p.location_code
            WHERE (i.is_posted = 0 OR i.is_posted IS NULL)
              AND (i.user_owner != 'STORE' OR i.user_owner IS NULL)
              AND i.quantity > 0
        ";

        $params = [];
        if (!empty($sector) && strtolower($sector) !== 'all') {
            $sql .= " AND i.sector LIKE ?";
            $params[] = '%' . $sector . '%';
        }
        if (!empty($search)) {
            $sql .= " AND (i.brand LIKE ? OR i.model LIKE ? OR i.specs_json LIKE ? OR i.location_code LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " GROUP BY i.id ORDER BY i.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->formatProducts($items);
    }

    /**
     * Count unposted physical warehouse items in stock
     */
    public function getWarehouseStockCount() {
        try {
            $stmt = $this->db->query("
                SELECT COUNT(*) FROM inventory 
                WHERE (is_posted = 0 OR is_posted IS NULL) 
                  AND (user_owner != 'STORE' OR user_owner IS NULL) 
                  AND quantity > 0
            ");
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Publish a warehouse product to the store with customized retail pricing and specs
     */
    public function postFromWarehouse($id, $data = [], $file = null) {
        $fields = ["is_posted = 1", "updated_at = CURRENT_TIMESTAMP"];
        $params = [];

        if (isset($data['price']) && $data['price'] !== '') {
            $fields[] = "price = ?";
            $params[] = (float)$data['price'];
        }
        if (isset($data['quantity']) && $data['quantity'] !== '') {
            $fields[] = "quantity = ?";
            $params[] = (int)$data['quantity'];
        }
        if (!empty($data['brand'])) {
            $fields[] = "brand = ?";
            $params[] = trim($data['brand']);
        }
        if (!empty($data['model'])) {
            $fields[] = "model = ?";
            $params[] = trim($data['model']);
        }
        if (isset($data['specs_json']) && $data['specs_json'] !== '') {
            $fields[] = "specs_json = ?";
            $params[] = $data['specs_json'];
        }
        if (!empty($data['sector'])) {
            $fields[] = "sector = ?";
            $params[] = trim($data['sector']);
        }

        $params[] = (int)$id;
        $sql = "UPDATE inventory SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $res = $stmt->execute($params);

        // If custom photo uploaded, isolate location code to prevent overwriting shared shelf photos
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $stmt = $this->db->prepare("SELECT location_code, sector FROM inventory WHERE id = ?");
            $stmt->execute([(int)$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($item) {
                $storeLoc = 'STORE-WH-' . (int)$id;
                $this->ensureLocationExists($storeLoc);
                $updateLoc = $this->db->prepare("UPDATE inventory SET location_code = ? WHERE id = ?");
                $updateLoc->execute([$storeLoc, (int)$id]);
                $this->handlePhotoUpload($storeLoc, $item['sector'], $file);
            }
        }

        return $res;
    }

    /**
     * Unpost a product from storefront (leaves physical warehouse inventory untouched)
     */
    public function unpostProduct($id) {
        $stmt = $this->db->prepare("UPDATE inventory SET is_posted = 0, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }

    /**
     * Create a new manual custom store item
     */
    public function add($data, $file = null) {
        $brand = trim($data['brand'] ?? '');
        $model = trim($data['model'] ?? '');
        $specs = $data['specs_json'] ?? '';
        $sector = $data['sector'] ?? 'Laptops';
        $price = (float)($data['price'] ?? 0);
        $qty = (int)($data['quantity'] ?? 1);

        $loc = 'STORE-FRONT-' . strtoupper(substr(md5(uniqid()), 0, 6));
        $this->ensureLocationExists($loc);

        $stmt = $this->db->prepare("
            INSERT INTO inventory (user_owner, sector, location_code, brand, model, specs_json, quantity, price, is_posted) 
            VALUES ('STORE', ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$sector, $loc, $brand, $model, $specs, $qty, $price]);

        $this->handlePhotoUpload($loc, $sector, $file);
    }

    /**
     * Update an existing product
     */
    public function update($id, $data, $file = null) {
        $brand = trim($data['brand'] ?? '');
        $model = trim($data['model'] ?? '');
        $specs = $data['specs_json'] ?? '';
        $sector = $data['sector'] ?? 'Laptops';
        $price = (float)($data['price'] ?? 0);
        $qty = isset($data['quantity']) ? (int)$data['quantity'] : null;

        $stmt = $this->db->prepare("SELECT location_code FROM inventory WHERE id = ?");
        $stmt->execute([$id]);
        $loc = $stmt->fetchColumn();
        
        if (!$loc) {
            $loc = 'STORE-FRONT-' . strtoupper(substr(md5(uniqid()), 0, 6));
        }
        $this->ensureLocationExists($loc);

        if ($qty !== null) {
            $stmt = $this->db->prepare("
                UPDATE inventory 
                SET sector = ?, location_code = ?, brand = ?, model = ?, specs_json = ?, price = ?, quantity = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$sector, $loc, $brand, $model, $specs, $price, $qty, $id]);
        } else {
            $stmt = $this->db->prepare("
                UPDATE inventory 
                SET sector = ?, location_code = ?, brand = ?, model = ?, specs_json = ?, price = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$sector, $loc, $brand, $model, $specs, $price, $id]);
        }

        $this->handlePhotoUpload($loc, $sector, $file);
    }

    /**
     * Deduct product quantity on purchase
     */
    public function reduceQuantity($id, $amount) {
        $stmt = $this->db->prepare("UPDATE inventory SET quantity = MAX(0, quantity - ?) WHERE id = ?");
        $stmt->execute([(int)$amount, $id]);
    }

    /**
     * Delete product (only used for custom store items)
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM inventory WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Resolve image URL safely with store-local preference, warehouse fallback, and SVG placeholder
     */
    public static function resolveImagePath($path) {
        if (empty($path)) {
            return 'images/placeholder.svg';
        }

        $cleanPath = ltrim(str_replace('\\', '/', $path), '/');

        // 1. Direct path in store/
        if (file_exists(__DIR__ . '/../' . $cleanPath)) {
            return $cleanPath;
        }

        // 2. Basename in store/images/store/
        $storeBase = basename($cleanPath);
        if (file_exists(__DIR__ . '/../images/store/' . $storeBase)) {
            return 'images/store/' . $storeBase;
        }

        // 3. Central app marketing photo bucket
        if (file_exists(__DIR__ . '/../../marketing/' . $cleanPath)) {
            return '../marketing/' . $cleanPath;
        }

        // 4. Central app orders location_photos
        if (file_exists(__DIR__ . '/../../orders/' . $cleanPath)) {
            return '../orders/' . $cleanPath;
        }

        // 5. Fallback: serverWarehouse marketing photo bucket
        if (file_exists(__DIR__ . '/../../serverWarehouse/marketing/' . $cleanPath)) {
            return '../serverWarehouse/marketing/' . $cleanPath;
        }

        // 6. Fallback: serverWarehouse orders location_photos
        if (file_exists(__DIR__ . '/../../serverWarehouse/orders/' . $cleanPath)) {
            return '../serverWarehouse/orders/' . $cleanPath;
        }

        // 7. Default placeholder
        return 'images/placeholder.svg';
    }

    /**
     * Format raw database records into standardized product models
     */
    private function formatProducts($items) {
        $products = [];
        foreach ($items as $item) {
            $imagePath = self::resolveImagePath($item['image'] ?? '');
            $thumbPath = self::resolveImagePath(!empty($item['thumb']) ? $item['thumb'] : ($item['image'] ?? ''));

            // Parse specs & details using intelligent parser
            $rawSpecs = $item['description'] ?: 'As-is warehouse item.';
            $details = UI::parseDetails($rawSpecs);

            // Formatted update timestamp
            $dateStr = $item['updated_at'] ?: ($item['created_at'] ?? null);
            $lastUpdate = '';
            if ($dateStr) {
                $time = strtotime($dateStr);
                $lastUpdate = (date('Y', $time) === date('Y')) ? date('n/j', $time) : date('n/j/Y', $time);
            }

            $products[$item['id']] = [
                'id' => $item['id'],
                'brand' => $item['brand'] ?? '',
                'model' => $item['model'] ?? '',
                'title' => $item['title'] ?: 'Unknown Product',
                'description' => $details['summary'],
                'raw_specs' => $rawSpecs,
                'details' => $details,
                'price' => (float)$item['price'],
                'quantity' => (int)($item['quantity'] ?? 0),
                'location_code' => $item['location_code'] ?? '',
                'user_owner' => $item['user_owner'] ?? '',
                'is_warehouse' => ($item['user_owner'] ?? '') !== 'STORE',
                'is_posted' => (int)($item['is_posted'] ?? 0),
                'image' => $imagePath,
                'thumb' => $thumbPath,
                'category' => $item['category'] ?? '',
                'last_update' => $lastUpdate
            ];
        }
        return $products;
    }

    /**
     * Ensure a location code exists in the locations table to satisfy foreign key integrity
     */
    public function ensureLocationExists($loc) {
        if (empty($loc)) return;
        try {
            $stmt = $this->db->prepare("
                INSERT OR IGNORE INTO locations (location_code, status, working_zone_name, updated_at) 
                VALUES (?, 'Storefront', 'Storefront', CURRENT_TIMESTAMP)
            ");
            $stmt->execute([$loc]);
        } catch (Exception $e) {
            // Ignore if locations table does not exist or duplicate
        }
    }

    /**
     * Process image upload via StoreImageProcessor and register in location_photos
     */
    private function handlePhotoUpload($loc, $sector, $file) {
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $result = StoreImageProcessor::processUpload($file);
            if (!empty($result['success'])) {
                $rawPath = $result['raw'];
                $optPath = $result['opt'];
                $thumbPath = $result['thumb'];

                // Ensure location exists in locations table to satisfy foreign key constraint
                $this->ensureLocationExists($loc);

                $stmt = $this->db->prepare("SELECT id FROM location_photos WHERE location_code = ?");
                $stmt->execute([$loc]);
                $photoId = $stmt->fetchColumn();

                if ($photoId) {
                    $stmt = $this->db->prepare("
                        UPDATE location_photos 
                        SET original_filename = ?, optimized_path = ?, thumbnail_path = ?, archive_path = ?, archive_driver = 'store_local', uploaded_by = 'STORE', sector = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$file['name'], $optPath, $thumbPath, $rawPath, $sector, $photoId]);
                } else {
                    $stmt = $this->db->prepare("
                        INSERT INTO location_photos (location_code, original_filename, archive_driver, archive_path, optimized_path, thumbnail_path, uploaded_by, sector) 
                        VALUES (?, ?, 'store_local', ?, ?, ?, 'STORE', ?)
                    ");
                    $stmt->execute([$loc, $file['name'], $rawPath, $optPath, $thumbPath, $sector]);
                }
            } elseif (!empty($result['error'])) {
                throw new Exception("Photo processing error: " . $result['error']);
            }
        }
    }
}
