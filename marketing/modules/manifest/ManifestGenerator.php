<?php
/**
 * ManifestGenerator Class
 * Handles pulling inventory data and formatting it for marketing outreach.
 */

class ManifestGenerator
{
    private $marketingDb;
    private $labelsDb;

    public function __construct($marketingDb, $labelsDb)
    {
        $this->marketingDb = $marketingDb;
        $this->labelsDb = $labelsDb;
    }

    /**
     * Fetches high-volume items ready for marketing.
     */
    public function getMarketableInventory($minQty = 10)
    {
        // In a real scenario, this would JOIN or query the labels.sqlite
        // For now, we simulate the logic described: QTY > 10 and Status = 'In Warehouse'
        $sql = "SELECT brand, model, COUNT(*) as qty, cpu_gen, ram, storage
                FROM items
                WHERE status = 'In Warehouse'
                GROUP BY brand, model
                HAVING qty >= :minQty";

        try {
            $stmt = $this->labelsDb->prepare($sql);
            $stmt->execute(['minQty' => $minQty]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Formats the inventory into a "Weekly Stock List" text string.
     */
    public function generateTextAd($inventory, $tier = 'B2B')
    {
        if (empty($inventory))
            return "No bulk inventory currently available.";

        $company = class_exists('Company') ? strtoupper(Company::getName()) : 'IQA Metal';
        $ad = "🚀 **WEEKLY STOCK MANIFEST - {$company}** 🚀\n";
        $ad .= "High-volume hardware ready for immediate dispatch:\n\n";

        foreach ($inventory as $item) {
            $ad .= "🔹 " . $item['qty'] . "x " . $item['brand'] . " " . $item['model'] . "\n";
            $ad .= "   Specs: " . $item['cpu_gen'] . " | " . $item['ram'] . " | " . $item['storage'] . "\n";
            $ad .= "   Condition: Certified Refurbished / Clean\n\n";
        }

        $ad .= "DM for volume pricing! #B2B #Hardware #Refurbished";

        return $ad;
    }
}
?>