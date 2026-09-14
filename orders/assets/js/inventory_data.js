/**
 * Global Inventory Data for System
 * Separated by sector for cleaner management.
 */

var IQA_LaptopInventory = {
  "Dell": {
    models: ["Alienware","G-Series","Inspiron","Latitude","Precision","Vostro","XPS"],
    series: {
      "Latitude": ["3300","3380","3390","3400","3410","3420","3480","3490","3500","3510","3520","3580","3590","5285","5300","5310","5320","5400","5401","5410","5411","5420","5421","5430","5431","5440","5480","5490","5491","5500","5501","5510","5520","5520/5530","5580","5590","5591","7280","7290","7310","7330","7380","7390","7400","7410","7420","7430","7480","7490","9410","9420","9510","9520","E3340","E3350","E3440","E3450","E4200","E4300","E4310","E5400","E5410","E5420","E5420M","E5430","E5440","E5450","E5470","E5500","E5510","E5520","E5520M","E5530","E5540","E5550","E5570","E6220","E6230","E6320","E6330","E6400","E6400 ATG","E6400 XFR","E6410","E6410 ATG","E6420","E6420 ATG","E6420 XFR","E6430","E6430 ATG","E6430s","E6440","E6500","E6510","E6520","E6530","E6540","E7240","E7250","E7270","E7440","E7450","E7470"],
      "Precision": ["3510","3540","3541","3550","3551","3571","3591","5510","5520","5530","5540","5550","5560","7510","7520","7530","7540","7550","7710","7720","7730","7740","M2400","M4300","M4400","M4500","M4600","M4700","M4800","M6300","M6400","M6500","M6600","M6700","M6800"],
      "XPS": ["12 9250","12 9Q23","12 9Q33","13","13 7390","13 9300","13 9310","13 9333","13 9343","13 9350","13 9360","13 9365","13 9370","13 9380","13 L321X","13 L322X","14 L401X","14 L421X","14Z L412Z","15","15 7590","15 9500","15 9530","15 9550","15 9560","15 9570","15 9575","15 L501X","15 L502X","15 L521X","15Z L511Z"],
      "Inspiron": ["11 3135","11 3137","11 3147","13 5368","13 5378","13 7347","13 7348","13 7352","13 7359","13 7368","13 7378","14 3421","14 3437","14 3442","14 3443","14 3451","14 3452","14 3458","14R 5420","14R 5421","14R 7420","14R SE","15","15 3521","15 3537","15 3541","15 3542","15 3543","15 3551","15 3552","15 3555","15 3558","15 3565","15 3567","15 3568","15 5521","15 5537","15 5542","15 5543","15 5545","15 5547","15 5551","15 5555","15 5558","15 5559","15 5565","15 5567","15 5568","15 5578","15 7537","15 7548","15 7558","15 7559","15 7568","15 7579","15R 5520","15R 5521","15R 7520","15R SE","17 3721","17 3737","17 3743","17 5748","17 5749","17 5755","17 5758","17 5759","17 5765","17 5767","17 7737","17 7746","17 7773","17R 5720","17R 7720","3593","5481","N4050","N4110","N5050","N5110","N7110"],
      "Vostro": [],
      "Alienware": ["17"],
      "G-Series": [],
    }
  },
  "HP": {
    models: ["ChromeBook","Dragonfly","EliteBook","Envy","Omen","Pavilion","ProBook","Spectre","Victus","ZBook"],
    series: {
      "EliteBook": ["1020 G1","1030 G1","1030 G2","1030 G3","1030 G4","1030 G7","1030 G8","1040 G1","1040 G2","1040 G3","1040 G4","1040 G5","1040 G6","1040 G7","1040 G8","1040 G9","1050 G1","435","640 G1","640 G2","640 G3","640 G4","640 G5","640 G8","640 G9","645 G1","645 G2","645 G3","645 G4","650 G1","650 G2","650 G3","650 G4","650 G5","650 G8","650 G9","655 G1","655 G2","655 G3","745 G6","820 G1","820 G2","820 G3","820 G4","830 G5","830 G6","830 G7","830 G8","830 G9","840 G1","840 G2","840 G3","840 G4","840 G5","840 G6","840 G7","840 G8","840 G9","850 G1","850 G2","850 G3","850 G4","850 G5","850 G6","850 G7","850 G8","860 G9","9470m","9480m","x360"],
      "ProBook": ["430 G1","430 G2","430 G3","430 G4","430 G5","430 G6","430 G7","430 G8","435 G7","440 G0","440 G1","440 G2","440 G3","440 G4","440 G5","440 G6","440 G7","440 G8","440 G9","445 G6","445 G7","445 G8","445 G9","450 G0","450 G1","450 G2","450 G3","450 G4","450 G5","450 G6","450 G7","450 G8","450 G9","455 G1","455 G2","455 G3","455 G4","455 G5","455 G6","455 G7","455 G8","455 G9","460","470 G0","470 G1","470 G2","470 G3","470 G4","470 G5","470 G7","640 G1","640 G2","640 G4","640 G5","650 G1","650 G3","650 G5","650 G8","6570b","820 G1","820 G3","840 G5","850 G3","850 G6"],
      "ZBook": ["15 G3","15 G5","15 G6","17 G5","17 G6","725 G2","725 G3","725 G4","735 G5","735 G6","745 G2","745 G3","745 G4","745 G5","745 G6","755 G2","755 G3","755 G4","755 G5","Create G7","Firefly 14 G7","Firefly 14 G8","Firefly 14 G9","Firefly 15 G7","Firefly 15 G8","Firefly 15 G9","Fury","Fury 15 G7","Fury 15 G8","Fury 16 G9","Fury 17 G7","Fury 17 G8","Power G7","Power G8","Power G9","Studio G3","Studio G4","Studio G5","Studio G7","Studio G8","Studio G9","Studio x360 G5"],
      "Dragonfly": ["630 G8","635 G7","635 G8","Folio G1","x2 G4","x360"],
      "Envy": ["13","14","15","17","17M-CG0013DX","17m-bw","x360"],
      "Spectre": ["13","14 G2","15 G2","x360"],
      "Pavilion": ["14","15","15-cs1065cl","17","Gaming"],
      "Omen": ["15 G2","15 G3","15 G4","15 G5","15 G6","16","17 G2","17 G3","17 G4","17 G5","17 G6"],
      "Victus": ["15","16"],
      "ChromeBook": ["11 G1","11MK G9","14","14 G2","15","15 G3","15 G4","15 G5","15 G6"],
    }
  },
  "Lenovo": {
    models: ["ChromeBook","IdeaPad","LOQ","Legion","ThinkBook","ThinkPad","Yoga"],
    series: {
      "ThinkPad": ["Detail","E Series","E14","E15","E570","L Series","L13","L14","L15","L390","P Series","P1","P1 Gen 1","P1 Gen 2","P1 Gen 3","P1 Gen 4","P1 Gen 5","P15","P15s","P50","P50s","P51","P52","P53","P53s","P70","P71","P73","T Series","T14","T14 Gen 1","T14 Gen 2","T14 Gen 3","T15","T15p","T450","T460","T470","T480","T490","X Series","X1","X1 Carbon Gen 10","X1 Carbon Gen 3","X1 Carbon Gen 4","X1 Carbon Gen 5","X1 Carbon Gen 6","X1 Carbon Gen 7","X1 Carbon Gen 8","X1 Carbon Gen 9","X13","X250","X260","X270","X280","X390"],
      "IdeaPad": ["1","3","5","7","Duet","Flex 5","Gaming 3","Gaming 5","Slim 3","Slim 5","Slim 7"],
      "Yoga": ["13s","14","15","16","7i","Flex","Pro","Slim 7","Slim 9"],
      "Legion": ["5","5 Pro","5i","7","7i","Slim 5","Slim 5i","Slim 7","Y530-15ICH","Y545","Y740-15IRH"],
      "ThinkBook": ["13s","14","14s","15","15s","16"],
      "LOQ": ["15","16"],
      "ChromeBook": ["Duet","IdeaPad Flex 3","Slim 3"],
    }
  },
  "Apple": {
    models: ["MacBook","MacBook Air","MacBook Pro"],
    series: {
      "MacBook Pro": ["13-inch M1","13-inch M2","14-inch M1 Max","14-inch M1 Pro","14-inch M2 Max","14-inch M2 Pro","16-inch M1 Max","16-inch M1 Pro","16-inch M2 Max","16-inch M2 Pro","Intel i7","Intel i9"],
      "MacBook Air": ["11-inch","13-inch M1","13-inch M2","15-inch M2"],
      "MacBook": ["12-inch","Retina"],
    }
  },
  "Microsoft": {
    models: ["Surface Book","Surface Go","Surface Laptop","Surface Laptop Go","Surface Laptop Studio","Surface Pro"],
    series: {
      "Surface Laptop": ["Laptop 1","Laptop 2","Laptop 3","Laptop 4","Laptop 5"],
      "Surface Book": ["Book 1","Book 2","Book 3"],
      "Surface Pro": ["Pro 4","Pro 5","Pro 6","Pro 7","Pro 7+","Pro 8","Pro 9","Pro X"],
      "Surface Laptop Go": ["Laptop Go 1","Laptop Go 2"],
      "Surface Laptop Studio": ["Laptop Studio 1"],
      "Surface Go": ["Go 1","Go 2","Go 3"],
    }
  },
  "Samsung": {
    models: ["ChromeBook","Galaxy Book","Galaxy Book Flex","Galaxy Book Go","Galaxy Book Ion","Galaxy Book Odyssey","Galaxy Book Pro","Notebook"],
    series: {
      "Galaxy Book": ["360","Ion 1","Ion 2"],
      "Galaxy Book Pro": ["Pro","Pro 360"],
      "Galaxy Book Odyssey": ["Odyssey"],
      "Galaxy Book Go": ["Go"],
      "Galaxy Book Flex": ["Flex","Flex Alpha"],
      "Galaxy Book Ion": ["Ion 1","Ion 2"],
      "Notebook": ["Notebook 5","Notebook 7","Notebook 9","Notebook Odyssey"],
      "ChromeBook": ["4","4+","Plus V2"],
    }
  },
  "Asus": {
    models: ["ChromeBook","ExpertBook","ProArt Studiobook","ROG","TUF Gaming","VivoBook","ZenBook"],
    series: {
      "ZenBook": ["13","14","15","Duo","Flip","Fold","Go","Pro","S","UX431FN"],
      "VivoBook": ["13","14","15","17","Flip","Pro","S","X1605ZA"],
      "ROG": ["Flow X13","Flow Z13","Strix G15","Strix G17","Strix SCAR","Zephyrus G14","Zephyrus G15","Zephyrus M16"],
      "TUF Gaming": ["A15","A17","Dash F15","F15","F17"],
      "ExpertBook": ["B1","B3","B5","B7","B9"],
      "ProArt Studiobook": ["16","16 OLED","Pro 16"],
      "ChromeBook": ["CX9","Detachable CM3","Flip C436"],
    }
  },
  "Acer": {
    models: ["Aspire","ChromeBook","ConceptD","Enduro","Nitro","Predator","Spin","Swift","TravelMate"],
    series: {
      "Swift": ["1","3","3x","5","7","Edge","Go","Vero"],
      "Spin": ["1","3","5","7"],
      "Aspire": ["1","3","5","7"],
      "TravelMate": ["P2","P4","P6"],
      "Predator": ["Helios 300","Helios 500","Helios 700","Triton 300","Triton 500"],
      "Nitro": ["5","7"],
      "ConceptD": ["3","5","7"],
      "Enduro": ["N3","Urban N3"],
      "ChromeBook": ["314","315","514","Spin 311","Spin 514","Spin 713","Tab"],
    }
  },
  "MSI": {
    models: ["Creator","Crosshair","GF","GL","GP","GV","Katana","Modern","Prestige","Pulse","Raider","Stealth","Summit","Sword","Titan","Vector"],
    series: {
      "Prestige": ["14","15","15M","E13","E14","E16"],
      "Summit": ["E13","E14","E16","Z16","Z17"],
      "Stealth": ["15M","8RE","GS66","GS77"],
      "Creator": ["15","M16","Z16","Z17"],
      "Modern": ["14","15"],
      "Titan": ["GT75","GT76","GT77"],
      "Raider": ["GE66","GE76"],
      "Vector": ["GP66","GP76"],
      "Pulse": ["GL66","GL76"],
      "Katana": ["GF66","GF76"],
      "Sword": ["15","17"],
      "Crosshair": ["15","17"],
      "GL": ["GF66","GF76"],
      "GP": ["GP66","GP76"],
      "GF": ["GF66","GF76"],
      "GV": ["GV62","GV72"],
    }
  },
  "Protectli": {
    models: [],
    series: {
    }
  },
};

// Desktop and Gaming configurations remain structured similarly
var IQA_DesktopInventory = {
    "Dell": {
        models: ["OptiPlex", "Precision Tower", "Vostro Desktop", "Inspiron Desktop"],
        series: {
            "OptiPlex": ["7040 SFF", "7050 SFF", "7060 SFF", "7070 SFF", "5040 SFF", "5050 SFF", "3040 SFF", "3050 SFF", "7040 Micro", "7050 Micro", "3050 Micro"],
            "Precision Tower": ["Tower"],
            "Vostro Desktop": ["Tower"],
            "Inspiron Desktop": ["Tower"]
        }
    },
    "HP": {
        models: ["EliteDesk", "ProDesk", "Workstation", "Pavilion Desktop"],
        series: {
            "EliteDesk": ["800 G2 SFF", "800 G3 SFF", "800 G4 SFF", "800 G2 Mini", "800 G3 Mini"],
            "ProDesk": ["600 G2 SFF", "600 G3 SFF", "400 G3 SFF"],
            "Workstation": ["Z240 Tower", "Z440 Tower"],
            "Pavilion Desktop": ["Tower"]
        }
    },
    "Lenovo": {
        models: ["ThinkCentre", "ThinkStation", "IdeaCentre"],
        series: {
            "ThinkCentre": ["M900 SFF", "M700 SFF", "M910s SFF", "M920s SFF", "M710q Tiny", "M910q Tiny", "M920q Tiny"],
            "ThinkStation": ["P310 Tower", "P320 Tower"],
            "IdeaCentre": ["Tower"]
        }
    },
    "Apple": {
        models: ["Mac Mini", "iMac", "Mac Pro", "Mac Studio"],
        series: {
            "Mac Mini": ["M1", "M2", "Intel i5", "Intel i7"],
            "iMac": ["27-inch 5K", "21.5-inch"],
            "Mac Pro": ["Tower"],
            "Mac Studio": ["M1", "M2"]
        }
    }
};

var IQA_GamingInventory = {
    "Sony": {
        models: ["Ps3", "Ps2", "Ps1", "PlayStation 5", "PlayStation 4", "PS VR2"],
        series: {
            "Ps3": ["Slim", "Fat", "Super Slim"],
            "Ps2": ["Slim", "Fat"],
            "Ps1": ["Classic"],
            "PlayStation 5": ["Disc Edition", "Digital Edition"],
            "PlayStation 4": ["Pro", "Slim", "Fat"],
            "PS VR2": []
        }
    },
    "Nintendo": {
        models: ["Switch", "Switch OLED", "Switch Lite"],
        series: {
            "Switch": ["Neon Blue/Red"],
            "Switch OLED": ["White", "Animal Crossing Edition"],
            "Switch Lite": []
        }
    },
    "Microsoft Gaming": {
        models: ["Xbox Series X", "Xbox Series S", "Xbox One X", "Xbox 360", "Xbox One"],
        series: {
            "Xbox Series X": ["1TB Black", "Halo Infinite Edition"],
            "Xbox Series S": ["512GB White"],
            "Xbox One X": [],
            "Xbox 360": [],
            "Xbox One": []
        }
    }
};

var gamingCategories = {
    "Consoles": ["PS5", "PS4", "Xbox Series X", "Xbox Series S", "Switch OLED", "Switch", "Retro Console"],
    "Controllers": ["DualSense", "DualShock 4", "Xbox Wireless Controller", "Elite Series 2", "Joy-Con", "Pro Controller"],
    "Games": ["Physical Disc", "Digital Code", "Cartridge"]
};

var cpuGenerations = [
    "i7-12th Gen", "i7-11th Gen", "i5-11th Gen", "i7-10th Gen", "i5-10th Gen",
    "i7-9th Gen", "i5-9th Gen", "i7-8th Gen", "i5-8th Gen", "6th - 7th Gen",
    "4th Gen & 5th", "2nd & 3rd Gen", "Ryzen 3", "Ryzen 5", "Ryzen 7", "AMD"
];

// Global support for all scripts
var IQA_Inventory = {};
(function () {
    const allBrands = new Set([
        ...Object.keys(IQA_LaptopInventory),
        ...Object.keys(IQA_DesktopInventory),
        ...Object.keys(IQA_GamingInventory)
    ]);

    allBrands.forEach(brand => {
        IQA_Inventory[brand] = {
            models: [],
            series: [],
            modelSeries: {}
        };

        const mergeSource = (source) => {
            if (source[brand]) {
                const bData = source[brand];
                if (bData.models) {
                    bData.models.forEach(model => {
                        if (!IQA_Inventory[brand].models.includes(model)) {
                            IQA_Inventory[brand].models.push(model);
                        }

                        let sList = [];
                        if (bData.series && Array.isArray(bData.series[model])) {
                            sList = bData.series[model];
                        }

                        if (!IQA_Inventory[brand].modelSeries[model]) {
                            IQA_Inventory[brand].modelSeries[model] = [];
                        }
                        IQA_Inventory[brand].modelSeries[model].push(...sList);
                        IQA_Inventory[brand].series.push(...sList);
                    });
                }
            }
        };

        mergeSource(IQA_LaptopInventory);
        mergeSource(IQA_DesktopInventory);
        mergeSource(IQA_GamingInventory);

        // Deduplicate elements while maintaining order
        IQA_Inventory[brand].models = Array.from(new Set(IQA_Inventory[brand].models));
        IQA_Inventory[brand].series = Array.from(new Set(IQA_Inventory[brand].series));
        Object.keys(IQA_Inventory[brand].modelSeries).forEach(model => {
            IQA_Inventory[brand].modelSeries[model] = Array.from(new Set(IQA_Inventory[brand].modelSeries[model]));
        });
    });
})();

// Safely provide legacy name support without polluting with 'var' or 'const' that could cause redeclaration errors
if (!window.inventoryData) {
    window.inventoryData = IQA_Inventory;
}
