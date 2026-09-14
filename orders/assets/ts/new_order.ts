/**
 * @typedef {Object} BrandData
 * @property {string[]} series
 * @property {string[]} models
 */

/** @type {Record<string, BrandData>} */
const inventoryData = {
    "Dell": {
        models: ["Latitude", "Precision", "Vostro", "XPS"],
        series: ["7420", "5420", "5520", "7390", "7400", "7410", "5410", "5510", "5550", "3551", "XPS 13", "XPS 15"]
    },
    "HP": {
        models: ["EliteBook", "ProBook", "ZBook", "Pavilion", "Envy", "Spectre"],
        series: ["840 G8", "840 G7", "830 G7", "850 G8", "450 G8", "440 G8", "650 G8", "Studio G7", "Firefly 15", "Power G7"]
    },
    "Lenovo": {
        models: ["ThinkPad", "IdeaPad", "Yoga"],
        series: ["T14 Gen 2", "T14s", "T490", "T480", "X1 Carbon Gen 9", "X1 Yoga Gen 6", "L14", "P15 Gen 2", "P1 Gen 4", "IdeaPad 5"]
    },
    "Apple": {
        models: ["MacBook Pro", "MacBook Air"],
        series: ["M1 13-inch", "M1 Pro 14-inch", "M1 Max 16-inch", "M2 Air", "M2 Pro 14-inch", "M2 Max 16-inch", "Intel i7 13-inch", "Intel i9 16-inch"]
    },
    "Microsoft": {
        models: ["Surface Laptop", "Surface Book", "Surface Laptop Go", "Surface Laptop Studio"],
        series: ["Laptop 4", "Laptop 3", "Laptop 5", "Book 3", "Laptop Studio"]
    },
    "Samsung": {
        models: ["Galaxy Book", "Galaxy Book Pro", "Galaxy Book Odyssey"],
        series: ["Book Pro", "Book Pro 360", "Book Ultra", "Book 3", "Book 2"]
    },
    "Asus": {
        models: ["ZenBook", "VivoBook", "ROG Laptop", "ExpertBook"],
        series: ["UX425", "S15", "Zephyrus G14", "Flow X13", "B9450"]
    },
    "Acer": {
        models: ["Swift", "Spin", "Aspire", "TravelMate"],
        series: ["Swift 3", "Spin 5", "Aspire 5", "P6", "X5"]
    },
    "MSI": {
        models: ["Prestige", "Summit", "Stealth", "Creator"],
        series: ["Prestige 14", "Summit E13", "GS66", "Z16", "GE76 Raider"]
    }
};

// CPU Generations List
const cpuGenerations: string[] = [
    "i7-12th Gen",
    "i7-11th Gen",
    "i5-11th Gen",
    "i7-10th Gen",
    "i5-10th Gen",
    "i7-9th Gen",
    "i5-9th Gen",
    "i7-8th Gen",
    "i5-8th Gen",
    "6th - 7th Gen",
    "4th Gen & 5th",
    "2nd & 3rd Gen",
    "Ryzen 3",
    "Ryzen 5",
    "Ryzen 7",
    "AMD",
];

const brandSelect = document.getElementById('brand') as HTMLSelectElement | null;
const modelDatalist = document.getElementById('model-options');
const seriesDatalist = document.getElementById('series-options');
const cpuDatalist = document.getElementById('cpu-options');
const modelInput = document.getElementById('models') as HTMLInputElement | null;
const seriesInput = document.getElementById('series') as HTMLInputElement | null;

// Initial Population for CPU Datalist
if (cpuDatalist) {
    cpuDatalist.innerHTML = '';
    cpuGenerations.forEach(cpu => {
        const option = document.createElement('option');
        option.value = cpu;
        cpuDatalist.appendChild(option);
    });
}

if (brandSelect && modelDatalist && seriesDatalist) {
    brandSelect.addEventListener('change', (e: Event) => {
        const target = e.target as HTMLSelectElement | null;
        if (!target) return;

        const selectedBrand = target.value;
        const data = inventoryData[selectedBrand as keyof typeof inventoryData];

        // Reset inputs and clear options
        if (modelInput) modelInput.value = '';
        if (seriesInput) seriesInput.value = '';

        modelDatalist.innerHTML = '';
        seriesDatalist.innerHTML = '';

        if (data) {
            // Populate Series
            for (const seriesName of data.series) {
                const option = document.createElement('option');
                option.value = seriesName;
                seriesDatalist.appendChild(option);
            }

            // Populate Models
            for (const modelNum of data.models) {
                const option = document.createElement('option');
                option.value = modelNum;
                modelDatalist.appendChild(option);
            }
        }
    });
}
