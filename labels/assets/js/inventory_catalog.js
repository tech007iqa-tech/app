/**
 * labels/assets/js/inventory_catalog.js
 * Standalone Built-in Hardware Inventory Catalog & Presets
 *
 * Provides instant auto-completions, model/series hierarchies,
 * and 1-click presets for unexperienced users without relying on external files.
 */
'use strict';

(function() {
    window.IQA_Catalog = {
        brands: ['Dell', 'HP', 'Lenovo', 'Apple', 'Microsoft', 'Asus', 'Acer', 'MSI', 'Samsung', 'Other'],

        data: {
            "Dell": {
                models: ["Latitude", "Precision", "XPS", "Inspiron", "Vostro", "Alienware", "OptiPlex", "G-Series"],
                series: {
                    "Latitude": ["5400", "5410", "5420", "5430", "5440", "7400", "7410", "7420", "7430", "7480", "7490", "5500", "5510", "5520", "5580", "5590", "3400", "3410", "3420", "3500", "3510", "3520", "E7440", "E7450", "E7470", "E5440", "E5450", "E5470"],
                    "Precision": ["3540", "3550", "3560", "5510", "5520", "5530", "5540", "5550", "7510", "7520", "7530", "7540", "7550", "7730", "7740"],
                    "XPS": ["13 9360", "13 9370", "13 9380", "13 9300", "13 9310", "15 9550", "15 9560", "15 9570", "15 7590", "15 9500", "15 9510"],
                    "Inspiron": ["15 3521", "15 3542", "15 3567", "15 5570", "14 3452", "13 7378"],
                    "OptiPlex": ["3060 Micro", "3070 Micro", "3080 Micro", "7050 Micro", "7060 Micro", "7070 Micro", "7080 Micro"]
                }
            },
            "HP": {
                models: ["EliteBook", "ProBook", "ZBook", "Dragonfly", "Envy", "Spectre", "Pavilion", "Omen", "Victus", "EliteDesk"],
                series: {
                    "EliteBook": ["840 G3", "840 G4", "840 G5", "840 G6", "840 G7", "840 G8", "840 G9", "850 G5", "850 G6", "850 G7", "830 G5", "830 G6", "830 G7", "1030 G2", "1030 G3", "1040 G4", "1040 G5"],
                    "ProBook": ["440 G5", "440 G6", "440 G7", "440 G8", "450 G5", "450 G6", "450 G7", "450 G8", "640 G4", "640 G5", "650 G4", "650 G5"],
                    "ZBook": ["15 G3", "15 G4", "15 G5", "15 G6", "Firefly 14 G7", "Firefly 14 G8", "Studio G5", "Power G7"],
                    "Dragonfly": ["G1", "G2", "Max"],
                    "EliteDesk": ["800 G3 Mini", "800 G4 Mini", "800 G5 Mini", "800 G6 Mini"]
                }
            },
            "Lenovo": {
                models: ["ThinkPad", "IdeaPad", "ThinkBook", "Legion", "Yoga", "ThinkCentre"],
                series: {
                    "ThinkPad": ["T480", "T480s", "T490", "T490s", "T14 Gen 1", "T14 Gen 2", "T14 Gen 3", "T15", "X1 Carbon Gen 6", "X1 Carbon Gen 7", "X1 Carbon Gen 8", "X1 Carbon Gen 9", "X13", "X280", "X390", "L14", "L15", "P52", "P53", "P15"],
                    "ThinkBook": ["14s", "14 G2", "15 G2", "15 G3"],
                    "IdeaPad": ["3", "5", "Flex 5", "Gaming 3"],
                    "Legion": ["5", "5 Pro", "7", "Y540"],
                    "ThinkCentre": ["M710q Tiny", "M720q Tiny", "M920q Tiny", "M70q Gen 2"]
                }
            },
            "Apple": {
                models: ["MacBook Pro", "MacBook Air", "MacBook", "Mac mini", "iMac"],
                series: {
                    "MacBook Pro": ["13-inch M1", "13-inch M2", "14-inch M1 Pro", "14-inch M2 Pro", "14-inch M3", "16-inch M1 Pro", "16-inch M1 Max", "16-inch M2 Pro", "16-inch M2 Max", "13-inch 2017", "13-inch 2018", "13-inch 2019", "13-inch 2020", "15-inch 2017", "15-inch 2018", "16-inch 2019"],
                    "MacBook Air": ["13-inch M1", "13-inch M2", "15-inch M2", "13-inch 2017", "13-inch 2018", "13-inch 2019", "13-inch 2020"],
                    "Mac mini": ["M1", "M2", "M2 Pro", "2018"]
                }
            },
            "Microsoft": {
                models: ["Surface Laptop", "Surface Pro", "Surface Book", "Surface Go", "Surface Laptop Studio"],
                series: {
                    "Surface Laptop": ["Laptop 2", "Laptop 3", "Laptop 4", "Laptop 5", "Laptop Go"],
                    "Surface Pro": ["Pro 5", "Pro 6", "Pro 7", "Pro 7+", "Pro 8", "Pro 9", "Pro X"],
                    "Surface Book": ["Book 2 13.5", "Book 2 15", "Book 3"]
                }
            },
            "Asus": {
                models: ["ZenBook", "VivoBook", "ROG Zephyrus", "TUF Gaming", "ExpertBook"],
                series: {
                    "ZenBook": ["13", "14", "15", "Duo", "Flip"],
                    "VivoBook": ["14", "15", "S14", "S15", "Pro 15"],
                    "ROG Zephyrus": ["G14", "G15", "M16"],
                    "TUF Gaming": ["A15", "F15", "Dash F15"]
                }
            },
            "Acer": {
                models: ["Aspire", "Swift", "Nitro", "Predator", "Spin"],
                series: {
                    "Aspire": ["3", "5", "7"],
                    "Swift": ["3", "5", "X"],
                    "Nitro": ["5", "16"],
                    "Predator": ["Helios 300", "Triton 300"]
                }
            },
            "MSI": {
                models: ["GF Series", "Katana", "Stealth", "Modern", "Prestige"],
                series: {
                    "GF Series": ["GF63 Thin", "GF65 Thin"],
                    "Katana": ["GF66", "15"],
                    "Modern": ["14", "15"],
                    "Prestige": ["14", "15"]
                }
            }
        },

        // Fast One-Click Presets for Unexperienced Users
        presets: [
            {
                id: 'fleet-office',
                title: '⚡ Fleet Laptop',
                subtitle: 'Standard Office Workhorse',
                badge: 'Most Popular',
                data: {
                    brand: 'Dell',
                    model: 'Latitude',
                    series: '5400',
                    cpu_gen: '8th Gen',
                    cpu_specs: 'i5-8350U',
                    cpu_cores: '4 Cores',
                    cpu_speed: '1.70 GHz',
                    ram: '16 GB',
                    storage: '256 GB SSD',
                    battery: '1',
                    description: 'Untested',
                    status: 'In Warehouse',
                    bios_state: 'Unknown'
                }
            },
            {
                id: 'executive-pro',
                title: '🚀 Executive Workstation',
                subtitle: 'High Performance Ready',
                badge: 'Refurbished',
                data: {
                    brand: 'HP',
                    model: 'EliteBook',
                    series: '840 G7',
                    cpu_gen: '10th Gen',
                    cpu_specs: 'i7-10610U',
                    cpu_cores: '4 Cores',
                    cpu_speed: '1.80 GHz',
                    ram: '16 GB',
                    storage: '512 GB SSD',
                    battery: '1',
                    gpu: 'Intel UHD Graphics',
                    os_version: 'Win 11 Pro',
                    description: 'Refurbished',
                    status: 'Tested',
                    bios_state: 'Unlocked'
                }
            },
            {
                id: 'budget-student',
                title: '📦 Budget / Student',
                subtitle: 'Everyday Reliable Unit',
                badge: 'Tested',
                data: {
                    brand: 'Lenovo',
                    model: 'ThinkPad',
                    series: 'T480',
                    cpu_gen: '8th Gen',
                    cpu_specs: 'i5-8250U',
                    cpu_cores: '4 Cores',
                    cpu_speed: '1.60 GHz',
                    ram: '8 GB',
                    storage: '256 GB SSD',
                    battery: '1',
                    description: 'Untested',
                    status: 'In Warehouse',
                    bios_state: 'Unknown'
                }
            },
            {
                id: 'apple-m1',
                title: '🍎 MacBook M1',
                subtitle: 'Apple Silicon Standard',
                badge: 'Apple',
                data: {
                    brand: 'Apple',
                    model: 'MacBook Air',
                    series: '13-inch M1',
                    cpu_gen: 'Apple M1',
                    cpu_specs: 'M1 8-Core',
                    cpu_cores: '8 Cores',
                    cpu_speed: '3.20 GHz',
                    ram: '8 GB',
                    storage: '256 GB SSD',
                    battery: '1',
                    os_version: 'macOS Sonoma',
                    description: 'Refurbished',
                    status: 'Tested',
                    bios_state: 'Unlocked'
                }
            },
            {
                id: 'for-parts',
                title: '🛠️ Salvage / For Parts',
                subtitle: 'Defective / Incomplete Unit',
                badge: 'Parts',
                data: {
                    ram: 'No RAM',
                    storage: 'No SSD',
                    description: 'For Parts',
                    status: 'No Power',
                    battery: '0',
                    bios_state: 'Unknown'
                }
            }
        ],

        // CPU Generation auto-mapping
        cpuGenerations: [
            { label: 'Intel Core i5 · 8th Gen (Standard)', gen: '8th Gen', prefix: 'i5-8', cores: '4 Cores', speed: '1.70 GHz' },
            { label: 'Intel Core i7 · 8th Gen', gen: '8th Gen', prefix: 'i7-8', cores: '4 Cores', speed: '1.90 GHz' },
            { label: 'Intel Core i5 · 10th Gen', gen: '10th Gen', prefix: 'i5-10', cores: '4 Cores', speed: '1.60 GHz' },
            { label: 'Intel Core i7 · 10th Gen', gen: '10th Gen', prefix: 'i7-10', cores: '4 Cores', speed: '1.80 GHz' },
            { label: 'Intel Core i5 · 11th Gen', gen: '11th Gen', prefix: 'i5-11', cores: '4 Cores', speed: '2.40 GHz' },
            { label: 'Intel Core i7 · 11th Gen', gen: '11th Gen', prefix: 'i7-11', cores: '4 Cores', speed: '2.80 GHz' },
            { label: 'Intel Core i5 · 12th Gen', gen: '12th Gen', prefix: 'i5-12', cores: '10 Cores', speed: '1.30 GHz' },
            { label: 'Intel Core i7 · 12th Gen', gen: '12th Gen', prefix: 'i7-12', cores: '10 Cores', speed: '1.80 GHz' },
            { label: 'Intel Core i5 · 6th Gen (Legacy)', gen: '6th Gen', prefix: 'i5-6', cores: '2 Cores', speed: '2.40 GHz' },
            { label: 'Intel Core i5 · 7th Gen (Legacy)', gen: '7th Gen', prefix: 'i7-7', cores: '2 Cores', speed: '2.50 GHz' },
            { label: 'Apple Silicon M1', gen: 'Apple M1', prefix: 'M1', cores: '8 Cores', speed: '3.20 GHz' },
            { label: 'Apple Silicon M2', gen: 'Apple M2', prefix: 'M2', cores: '8 Cores', speed: '3.50 GHz' },
            { label: 'AMD Ryzen 5', gen: 'AMD Ryzen', prefix: 'Ryzen 5', cores: '6 Cores', speed: '2.10 GHz' },
            { label: 'AMD Ryzen 7', gen: 'AMD Ryzen', prefix: 'Ryzen 7', cores: '8 Cores', speed: '2.00 GHz' }
        ],

        // Quick RAM Pill Options
        ramOptions: ['4 GB', '8 GB', '16 GB', '32 GB', '64 GB'],

        // Quick Storage Pill Options
        storageOptions: ['128 GB SSD', '256 GB SSD', '512 GB SSD', '1 TB SSD', '2 TB SSD', 'No Drive']
    };

    // Provide legacy compatibility with window.IQA_Inventory if missing
    if (!window.IQA_Inventory) {
        window.IQA_Inventory = {};
        Object.keys(window.IQA_Catalog.data).forEach(brand => {
            const b = window.IQA_Catalog.data[brand];
            window.IQA_Inventory[brand] = {
                models: b.models,
                series: [],
                modelSeries: b.series || {}
            };
            Object.values(b.series || {}).forEach(arr => {
                window.IQA_Inventory[brand].series.push(...arr);
            });
            window.IQA_Inventory[brand].series = Array.from(new Set(window.IQA_Inventory[brand].series));
        });
    }
})();
