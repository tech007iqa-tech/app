/**
 * labels/assets/js/battery_catalog.js
 * Comprehensive Client-Side Enterprise Laptop Battery Cross-Match Catalog
 * Provides zero-latency client-side search, autocompletion, and smart cross-matching.
 */
'use strict';

const BATTERY_CATALOG = [
    // ── DELL ─────────────────────────────────────────────────────────────
    {
        brand: 'Dell',
        part_number: 'WDX0R',
        model_name: 'Dell Type WDX0R 42Wh 3-Cell Battery',
        aliases: '3CRH3, T2JX4, FC92N, CYMGM, FW8KR, 0WDX0R, Y3F7Y, P69G001',
        voltage: '11.4V',
        capacity_wh: '42Wh',
        capacity_mah: '3500mAh',
        cell_count: '3-Cell',
        chemistry: 'Li-ion',
        compatible_models: [
            'Dell Inspiron 13 5368', 'Dell Inspiron 13 5378', 'Dell Inspiron 13 7368', 'Dell Inspiron 13 7378',
            'Dell Inspiron 14 5468', 'Dell Inspiron 15 5567', 'Dell Inspiron 15 5568', 'Dell Inspiron 15 5570',
            'Dell Inspiron 15 5578', 'Dell Inspiron 15 7560', 'Dell Inspiron 15 7569', 'Dell Inspiron 15 7570',
            'Dell Inspiron 15 7579', 'Dell Inspiron 17 5767', 'Dell Inspiron 17 5770', 'Dell Latitude 13 3379',
            'Dell Latitude 3180', 'Dell Latitude 3189', 'Dell Vostro 14 5468', 'Dell Vostro 15 5568'
        ],
        connector_type: 'Internal Ribbon Cable',
        notes: 'Fits Dell Inspiron 5000/7000 & Latitude 3000 series.',
        warehouse_location: 'Bin BAT-D01'
    },
    {
        brand: 'Dell',
        part_number: '3HWPP',
        model_name: 'Dell Type 3HWPP 68Wh 4-Cell Battery',
        aliases: '4GVGH, 1WND8, 01WND8, JY8D6, 0JY8D6, 03HWPP',
        voltage: '15.2V',
        capacity_wh: '68Wh',
        capacity_mah: '4250mAh',
        cell_count: '4-Cell',
        chemistry: 'Li-Polymer',
        compatible_models: [
            'Dell Latitude 5400', 'Dell Latitude 5410', 'Dell Latitude 5500', 'Dell Latitude 5510',
            'Dell Inspiron 7590 2-in-1', 'Dell Inspiron 7791 2-in-1'
        ],
        connector_type: 'Internal Ribbon Cable',
        notes: 'Extended capacity pack. Occupies 2.5" drive bay area in Latitude 5400/5500.',
        warehouse_location: 'Bin BAT-D02'
    },
    {
        brand: 'Dell',
        part_number: '1VX1I',
        model_name: 'Dell Type 1VX1I 42Wh 3-Cell Battery',
        aliases: 'DJ1J0, F3YGT, 01VX1I, 0DJ1J0, PGFX4, ONFOH',
        voltage: '11.4V',
        capacity_wh: '42Wh',
        capacity_mah: '3500mAh',
        cell_count: '3-Cell',
        chemistry: 'Li-ion',
        compatible_models: [
            'Dell Latitude 5280', 'Dell Latitude 5290', 'Dell Latitude 5480', 'Dell Latitude 5490',
            'Dell Latitude 5580', 'Dell Latitude 5590'
        ],
        connector_type: 'Internal Ribbon Cable',
        notes: 'Standard 3-cell pack allowing 2.5" SATA HDD/SSD installation.',
        warehouse_location: 'Bin BAT-D03'
    },
    {
        brand: 'Dell',
        part_number: '93FTF',
        model_name: 'Dell Type 93FTF 51Wh 3-Cell Battery',
        aliases: 'GD1JP, D4CMT, 093FTF, 0GD1JP, 83XPC',
        voltage: '11.4V',
        capacity_wh: '51Wh',
        capacity_mah: '4250mAh',
        cell_count: '3-Cell',
        chemistry: 'Li-Polymer',
        compatible_models: [
            'Dell Latitude 5280', 'Dell Latitude 5290', 'Dell Latitude 5480', 'Dell Latitude 5490',
            'Dell Latitude 5580', 'Dell Latitude 5590', 'Dell Precision 3520', 'Dell Precision 3530'
        ],
        connector_type: 'Internal Ribbon Cable',
        notes: 'Fits Latitude 5480/5490 and Precision 3520/3530.',
        warehouse_location: 'Bin BAT-D04'
    },
    {
        brand: 'Dell',
        part_number: '6GTPY',
        model_name: 'Dell Type 6GTPY 97Wh 6-Cell Extended Battery',
        aliases: '5XJ28, 5046J, 06GTPY, 05XJ28, H5H20',
        voltage: '11.4V',
        capacity_wh: '97Wh',
        capacity_mah: '8333mAh',
        cell_count: '6-Cell',
        chemistry: 'Li-Polymer',
        compatible_models: [
            'Dell XPS 15 9560', 'Dell XPS 15 9570', 'Dell XPS 15 7590', 'Dell Precision 5510',
            'Dell Precision 5520', 'Dell Precision 5530', 'Dell Precision 5540', 'Dell Vostro 7590'
        ],
        connector_type: 'Internal Ribbon Cable',
        notes: 'Extended capacity. Replaces 2.5" drive caddy in XPS 15 and Precision 5520/5530/5540.',
        warehouse_location: 'Bin BAT-D05'
    },
    {
        brand: 'Dell',
        part_number: 'J60J5',
        model_name: 'Dell Type J60J5 55Wh 4-Cell Battery',
        aliases: '0J60J5, R1V85, 0R1V85, 242WD, GG4FM, MC34Y',
        voltage: '7.6V',
        capacity_wh: '55Wh',
        capacity_mah: '7000mAh',
        cell_count: '4-Cell',
        chemistry: 'Li-ion',
        compatible_models: [
            'Dell Latitude E7270', 'Dell Latitude E7470'
        ],
        connector_type: 'Internal Flat Cable',
        notes: 'Specifically designed for 6th Gen Dell Latitude E7270 and E7470 ultrabooks.',
        warehouse_location: 'Bin BAT-D06'
    },
    {
        brand: 'Dell',
        part_number: 'F3YGT',
        model_name: 'Dell Type F3YGT 60Wh 4-Cell Battery',
        aliases: '2X39G, 02X39G, DM3WV, 0DM3WV, 451-BBYE',
        voltage: '7.6V',
        capacity_wh: '60Wh',
        capacity_mah: '7500mAh',
        cell_count: '4-Cell',
        chemistry: 'Li-Polymer',
        compatible_models: [
            'Dell Latitude 7280', 'Dell Latitude 7290', 'Dell Latitude 7380', 'Dell Latitude 7390',
            'Dell Latitude 7480', 'Dell Latitude 7490'
        ],
        connector_type: 'Internal Ribbon Cable',
        notes: 'Primary battery for 7th & 8th Gen Dell Latitude 7000 series ultrabooks.',
        warehouse_location: 'Bin BAT-D07'
    },
    {
        brand: 'Dell',
        part_number: '6MT4T',
        model_name: 'Dell Type 6MT4T 62Wh 4-Cell Battery',
        aliases: '7V69Y, TXF9M, 79VRK, 06MT4T, 07V69Y',
        voltage: '7.6V',
        capacity_wh: '62Wh',
        capacity_mah: '8160mAh',
        cell_count: '4-Cell',
        chemistry: 'Li-ion',
        compatible_models: [
            'Dell Latitude E5250', 'Dell Latitude E5270', 'Dell Latitude E5450', 'Dell Latitude E5470',
            'Dell Latitude E5550', 'Dell Latitude E5570', 'Dell Precision 3510'
        ],
        connector_type: 'Internal Flat Ribbon Cable',
        notes: 'Fits Latitude E5450, E5470, E5550, E5570.',
        warehouse_location: 'Bin BAT-D08'
    },
    {
        brand: 'Dell',
        part_number: 'MXV9V',
        model_name: 'Dell Type MXV9V 52Wh 4-Cell Battery',
        aliases: '0MXV9V, 5VC2M, 05VC2M, 829MX',
        voltage: '7.6V',
        capacity_wh: '52Wh',
        capacity_mah: '6500mAh',
        cell_count: '4-Cell',
        chemistry: 'Li-Polymer',
        compatible_models: [
            'Dell Latitude 5300', 'Dell Latitude 5300 2-in-1', 'Dell Latitude 5310', 'Dell Latitude 5310 2-in-1',
            'Dell Latitude 7300', 'Dell Latitude 7400', 'Dell Inspiron 7391 2-in-1'
        ],
        connector_type: 'Internal Ribbon Cable',
        notes: 'Compact pack for Latitude 5300/7300/7400.',
        warehouse_location: 'Bin BAT-D09'
    },

    // ── HP ───────────────────────────────────────────────────────────────
    {
        brand: 'HP',
        part_number: 'CS03XL',
        model_name: 'HP CS03XL Long Life Notebook Battery',
        aliases: 'HSTNN-DB6U, HSTNN-UB6S, HSTNN-I33C-4, 800231-141, 800513-001, T7B32AA',
        voltage: '11.4V',
        capacity_wh: '46.5Wh',
        capacity_mah: '3910mAh',
        cell_count: '3-Cell',
        chemistry: 'Li-ion',
        compatible_models: [
            'HP EliteBook 745 G3', 'HP EliteBook 745 G4', 'HP EliteBook 755 G3', 'HP EliteBook 755 G4',
            'HP EliteBook 840 G3', 'HP EliteBook 840 G4', 'HP EliteBook 850 G3', 'HP EliteBook 850 G4',
            'HP ZBook 15u G3', 'HP ZBook 15u G4', 'HP mt42 Mobile Thin Client', 'HP mt43 Mobile Thin Client'
        ],
        connector_type: 'Internal Drop-in Connector',
        notes: 'High-volume warehouse battery. Fits HP EliteBook 840 G3 and G4.',
        warehouse_location: 'Bin BAT-H01'
    },
    {
        brand: 'HP',
        part_number: 'SS03XL',
        model_name: 'HP SS03XL Long Life Rechargeable Battery',
        aliases: 'HSTNN-IB8C, HSTNN-LB8G, HSTNN-DB8J, 932823-1C1, 933321-855, 933321-852',
        voltage: '11.55V',
        capacity_wh: '50Wh',
        capacity_mah: '4110mAh',
        cell_count: '3-Cell',
        chemistry: 'Li-ion',
        compatible_models: [
            'HP EliteBook 735 G5', 'HP EliteBook 735 G6', 'HP EliteBook 745 G5', 'HP EliteBook 745 G6',
            'HP EliteBook 830 G5', 'HP EliteBook 830 G6', 'HP EliteBook 840 G5', 'HP EliteBook 840 G6',
            'HP EliteBook 846 G5', 'HP EliteBook 846 G6', 'HP ZBook 14u G5', 'HP ZBook 14u G6',
            'HP mt44 Mobile Thin Client', 'HP mt45 Mobile Thin Client'
        ],
        connector_type: 'Internal Drop-in Connector',
        notes: 'Primary battery for 8th Gen HP EliteBook 840 G5 and G6.',
        warehouse_location: 'Bin BAT-H02'
    },
    {
        brand: 'HP',
        part_number: 'CC03XL',
        model_name: 'HP CC03XL 53Wh Notebook Battery',
        aliases: 'HSTNN-IB9F, HSTNN-DB9Q, L77608-1C1, L77608-2C1, L78553-005',
        voltage: '11.55V',
        capacity_wh: '53Wh',
        capacity_mah: '4330mAh',
        cell_count: '3-Cell',
        chemistry: 'Li-Polymer',
        compatible_models: [
            'HP EliteBook 830 G7', 'HP EliteBook 830 G8', 'HP EliteBook 835 G7', 'HP EliteBook 835 G8',
            'HP EliteBook 840 G7', 'HP EliteBook 840 G8', 'HP EliteBook 845 G7', 'HP EliteBook 845 G8',
            'HP ZBook Firefly 14 G7', 'HP ZBook Firefly 14 G8'
        ],
        connector_type: 'Internal Connector Cable',
        notes: 'Standard battery for 10th & 11th Gen HP EliteBook 840 G7 and G8.',
        warehouse_location: 'Bin BAT-H03'
    },
    {
        brand: 'HP',
        part_number: 'TT03XL',
        model_name: 'HP TT03XL 56Wh Notebook Battery',
        aliases: 'HSTNN-DB8K, HSTNN-LB8H, HSTNN-UB7A, 932824-1C1, 933322-855',
        voltage: '11.55V',
        capacity_wh: '56Wh',
        capacity_mah: '4550mAh',
        cell_count: '3-Cell',
        chemistry: 'Li-Polymer',
        compatible_models: [
            'HP EliteBook 850 G5', 'HP EliteBook 850 G6', 'HP EliteBook 755 G5', 'HP ZBook 15u G5', 'HP ZBook 15u G6'
        ],
        connector_type: 'Internal Drop-in Connector',
        notes: 'Fits 15.6" EliteBook 850 G5/G6 and ZBook 15u G5/G6.',
        warehouse_location: 'Bin BAT-H04'
    },
    {
        brand: 'HP',
        part_number: 'RI04',
        model_name: 'HP RI04 Notebook Battery',
        aliases: 'HSTNN-DB7B, HSTNN-PB6Q, 805047-851, 805294-001',
        voltage: '14.8V',
        capacity_wh: '44Wh',
        capacity_mah: '2850mAh',
        cell_count: '4-Cell',
        chemistry: 'Li-ion',
        compatible_models: [
            'HP ProBook 450 G3', 'HP ProBook 455 G3', 'HP ProBook 470 G3'
        ],
        connector_type: 'External Snap-in Slot',
        notes: 'External clip-in battery for ProBook 450 G3.',
        warehouse_location: 'Bin BAT-H05'
    },
    {
        brand: 'HP',
        part_number: 'RR03XL',
        model_name: 'HP RR03XL 48Wh Battery',
        aliases: 'HSTNN-PB6W, HSTNN-LB7I, HSTNN-UB7C, 851477-421, 851610-850',
        voltage: '11.4V',
        capacity_wh: '48Wh',
        capacity_mah: '3890mAh',
        cell_count: '3-Cell',
        chemistry: 'Li-ion',
        compatible_models: [
            'HP ProBook 430 G4', 'HP ProBook 440 G4', 'HP ProBook 450 G4', 'HP ProBook 455 G4', 'HP ProBook 470 G4'
        ],
        connector_type: 'Internal Ribbon Cable',
        notes: 'Internal battery for HP ProBook 400 G4 generation.',
        warehouse_location: 'Bin BAT-H06'
    },
    {
        brand: 'HP',
        part_number: 'JC04',
        model_name: 'HP JC04 4-Cell External Battery',
        aliases: 'JC03, HSTNN-DB8E, HSTNN-PB6Y, HSTNN-LB7V, 919700-850, 919701-850',
        voltage: '14.6V',
        capacity_wh: '41.6Wh',
        capacity_mah: '2850mAh',
        cell_count: '4-Cell',
        chemistry: 'Li-ion',
        compatible_models: [
            'HP 240 G6', 'HP 245 G6', 'HP 250 G6', 'HP 255 G6', 'HP 15-bs000', 'HP 15-bw000', 'HP 17-bs000'
        ],
        connector_type: 'External Snap-in Slot',
        notes: 'Common external battery for entry-level HP 250 G6 laptops.',
        warehouse_location: 'Bin BAT-H07'
    },
    {
        brand: 'HP',
        part_number: 'HT03XL',
        model_name: 'HP HT03XL Long Life Battery',
        aliases: 'HSTNN-UB7J, HSTNN-DB8R, HSTNN-LB8M, L11119-855, L11421-2C2',
        voltage: '11.55V',
        capacity_wh: '41.04Wh',
        capacity_mah: '3470mAh',
        cell_count: '3-Cell',
        chemistry: 'Li-ion',
        compatible_models: [
            'HP 240 G7', 'HP 245 G7', 'HP 250 G7', 'HP 255 G7', 'HP Pavilion 14-ce', 'HP Pavilion 14-cf',
            'HP Pavilion 15-cs', 'HP Pavilion 15-cw', 'HP Pavilion 15-da', 'HP Pavilion 15-db'
        ],
        connector_type: 'Internal Drop-in Connector',
        notes: 'High-frequency replacement pack for HP 250 G7 and Pavilion 15.',
        warehouse_location: 'Bin BAT-H08'
    },

    // ── LENOVO ───────────────────────────────────────────────────────────
    {
        brand: 'Lenovo',
        part_number: '01AV421',
        model_name: 'Lenovo ThinkPad Internal Battery 01AV421',
        aliases: '01AV419, 01AV420, 01AV489, SB10K97576, SB10K97577, SB10K97578',
        voltage: '11.4V',
        capacity_wh: '24Wh',
        capacity_mah: '2090mAh',
        cell_count: '3-Cell',
        chemistry: 'Li-Polymer',
        compatible_models: [
            'Lenovo ThinkPad T470', 'Lenovo ThinkPad T480', 'Lenovo ThinkPad A475', 'Lenovo ThinkPad A485'
        ],
        connector_type: 'Internal Flat Ribbon Cable',
        notes: 'Internal front battery in PowerBridge dual-battery system.',
        warehouse_location: 'Bin BAT-L01'
    },
    {
        brand: 'Lenovo',
        part_number: '01AV423',
        model_name: 'Lenovo ThinkPad Battery 61+ (Rear External)',
        aliases: '61, 61+, 61++, 01AV422, 01AV424, 01AV425, 01AV426, 01AV427, SB10K97580',
        voltage: '10.8V',
        capacity_wh: '48Wh',
        capacity_mah: '4400mAh',
        cell_count: '6-Cell',
        chemistry: 'Li-ion',
        compatible_models: [
            'Lenovo ThinkPad T470', 'Lenovo ThinkPad T480', 'Lenovo ThinkPad T570', 'Lenovo ThinkPad T580',
            'Lenovo ThinkPad P51s', 'Lenovo ThinkPad P52s', 'Lenovo ThinkPad A475', 'Lenovo ThinkPad A485'
        ],
        connector_type: 'External Snap-in Slot',
        notes: 'Rear clip-in cylindrical pack for ThinkPad T470 / T480. High warehouse demand.',
        warehouse_location: 'Bin BAT-L02'
    },
    {
        brand: 'Lenovo',
        part_number: '45N1127',
        model_name: 'Lenovo ThinkPad Battery 68+ (Extended Rear)',
        aliases: '68, 68+, 45N1124, 45N1125, 45N1126, 45N1128, 45N1738, 45N1775, 0C52862',
        voltage: '10.8V',
        capacity_wh: '72Wh',
        capacity_mah: '6600mAh',
        cell_count: '6-Cell',
        chemistry: 'Li-ion',
        compatible_models: [
            'Lenovo ThinkPad T440', 'Lenovo ThinkPad T440s', 'Lenovo ThinkPad T450', 'Lenovo ThinkPad T450s',
            'Lenovo ThinkPad T460', 'Lenovo ThinkPad T460p', 'Lenovo ThinkPad X240', 'Lenovo ThinkPad X250',
            'Lenovo ThinkPad X260', 'Lenovo ThinkPad L450', 'Lenovo ThinkPad L460', 'Lenovo ThinkPad W550s'
        ],
        connector_type: 'External Snap-in Slot',
        notes: 'Classic ThinkPad 6-cell external extended battery. Fits T440/T450/T460 & X240/X250/X260.',
        warehouse_location: 'Bin BAT-L03'
    },
    {
        brand: 'Lenovo',
        part_number: 'L17M3P51',
        model_name: 'Lenovo ThinkPad L17M3P51 57Wh Battery',
        aliases: '01AV463, 01AV464, 01AV465, 01AV466, SB10K97610, SB10K97611, L17C3P51',
        voltage: '11.52V',
        capacity_wh: '57Wh',
        capacity_mah: '4950mAh',
        cell_count: '3-Cell',
        chemistry: 'Li-Polymer',
        compatible_models: [
            'Lenovo ThinkPad T490', 'Lenovo ThinkPad T590', 'Lenovo ThinkPad P43s', 'Lenovo ThinkPad P53s'
        ],
        connector_type: 'Internal Ribbon Cable',
        notes: 'Single internal battery in ThinkPad T490. Replaced dual-battery PowerBridge.',
        warehouse_location: 'Bin BAT-L04'
    },
    {
        brand: 'Lenovo',
        part_number: 'L18M3P73',
        model_name: 'Lenovo ThinkPad L18M3P73 50Wh Battery',
        aliases: '02DL007, 02DL008, 02DL009, 02DL010, SB10K97652, SB10K97653, L18C3P73',
        voltage: '11.52V',
        capacity_wh: '50Wh',
        capacity_mah: '4345mAh',
        cell_count: '3-Cell',
        chemistry: 'Li-Polymer',
        compatible_models: [
            'Lenovo ThinkPad T14 Gen 1', 'Lenovo ThinkPad T14 Gen 2', 'Lenovo ThinkPad P14s Gen 1', 'Lenovo ThinkPad P14s Gen 2'
        ],
        connector_type: 'Internal Ribbon Cable',
        notes: 'High-demand modern fleet pack for ThinkPad T14 Gen 1 and Gen 2.',
        warehouse_location: 'Bin BAT-L05'
    },
    {
        brand: 'Lenovo',
        part_number: '00HW022',
        model_name: 'Lenovo ThinkPad 00HW022 Front Internal Battery',
        aliases: '00HW023, 00HW024, 00HW025, SB10F46460, SB10F46461, SB10F46462',
        voltage: '11.25V',
        capacity_wh: '24Wh',
        capacity_mah: '2100mAh',
        cell_count: '3-Cell',
        chemistry: 'Li-ion',
        compatible_models: [
            'Lenovo ThinkPad T460s', 'Lenovo ThinkPad T470s'
        ],
        connector_type: 'Internal Flat Ribbon Cable',
        notes: 'Dual internal battery architecture for T460s/T470s (Battery 1 Front).',
        warehouse_location: 'Bin BAT-L06'
    },
    {
        brand: 'Lenovo',
        part_number: '01AV477',
        model_name: 'Lenovo ThinkPad Battery 77+ (P50 / P51 / P52)',
        aliases: '77, 77+, 01AV477, SB10H45007, SB10H45008, SB10K97634, 00NY493, 00NY492, 00NY491, 00NY490, SB10H45071, SB10H45073',
        voltage: '11.25V',
        capacity_wh: '90Wh',
        capacity_mah: '8000mAh',
        cell_count: '6-Cell',
        chemistry: 'Li-ion',
        compatible_models: [
            'Lenovo ThinkPad P50', 'Lenovo ThinkPad P51', 'Lenovo ThinkPad P52'
        ],
        connector_type: 'External Snap-in Slot',
        notes: 'High-capacity 90Wh external pack for ThinkPad P50/P51/P52 workstations (ASM P/N: SB10H45007 / FRU: 01AV477).',
        warehouse_location: 'Bin BAT-L07'
    },

    // ── APPLE ────────────────────────────────────────────────────────────
    {
        brand: 'Apple',
        part_number: 'A1819',
        model_name: 'Apple MacBook Pro 13" Touch Bar Battery A1819',
        aliases: '020-01705, 080-333-4000, A1706',
        voltage: '11.41V',
        capacity_wh: '49.2Wh',
        capacity_mah: '4314mAh',
        cell_count: '3-Cell',
        chemistry: 'Li-Polymer',
        compatible_models: [
            'Apple MacBook Pro 13" Touch Bar A1706 (Late 2016 MLH12LL/A)',
            'Apple MacBook Pro 13" Touch Bar A1706 (Mid 2017 MPXV2LL/A)'
        ],
        connector_type: 'Internal Ribbon Connector Board',
        notes: 'Adhesive-backed 3-cell pouch pack for MacBook Pro 13 Touch Bar A1706.',
        warehouse_location: 'Bin BAT-A01'
    },
    {
        brand: 'Apple',
        part_number: 'A1713',
        model_name: 'Apple MacBook Pro 13" Non-Touch Bar Battery A1713',
        aliases: '020-00814, 020-00816, A1708',
        voltage: '11.4V',
        capacity_wh: '54.5Wh',
        capacity_mah: '4781mAh',
        cell_count: '3-Cell',
        chemistry: 'Li-Polymer',
        compatible_models: [
            'Apple MacBook Pro 13" Function Keys A1708 (Late 2016 MLL42LL/A)',
            'Apple MacBook Pro 13" Function Keys A1708 (Mid 2017 MPXQ2LL/A)'
        ],
        connector_type: 'Internal Ribbon Connector Board',
        notes: 'For non-Touch Bar A1708 MacBook Pro. Different form factor from A1819.',
        warehouse_location: 'Bin BAT-A02'
    },
    {
        brand: 'Apple',
        part_number: 'A1496',
        model_name: 'Apple MacBook Air 13" Battery A1496',
        aliases: 'A1405, A1377, 020-8143-A, 020-8145-A',
        voltage: '7.6V',
        capacity_wh: '54.4Wh',
        capacity_mah: '7150mAh',
        cell_count: '4-Cell',
        chemistry: 'Li-Polymer',
        compatible_models: [
            'Apple MacBook Air 13" A1466 (Mid 2013 to 2017 MD760LL/A, MQD32LL/A)',
            'Apple MacBook Air 13" A1369 (Late 2010, Mid 2011 MC503LL/A)'
        ],
        connector_type: 'Internal Drop-in Connector',
        notes: 'Classic wedge MacBook Air 13" screw-in battery pack.',
        warehouse_location: 'Bin BAT-A03'
    },

    // ── MICROSOFT ────────────────────────────────────────────────────────
    {
        brand: 'Microsoft',
        part_number: 'G3HTA027H',
        model_name: 'Microsoft Surface Pro Battery G3HTA027H / DYNM02',
        aliases: 'DYNM02, G3HTA036H, G3HTA044H',
        voltage: '7.57V',
        capacity_wh: '45Wh',
        capacity_mah: '5940mAh',
        cell_count: '2-Cell',
        chemistry: 'Li-ion',
        compatible_models: [
            'Microsoft Surface Pro 4 1724', 'Microsoft Surface Pro 5 (2017) 1796', 'Microsoft Surface Pro 6 1796'
        ],
        connector_type: 'Internal Multi-Pin Ribbon',
        notes: 'For Surface Pro 4 / 5 / 6 tablet enclosures.',
        warehouse_location: 'Bin BAT-M01'
    },
    {
        brand: 'Microsoft',
        part_number: 'G3HTA038H',
        model_name: 'Microsoft Surface Laptop Battery G3HTA038H',
        aliases: 'DYNM03, 1769-BAT',
        voltage: '7.57V',
        capacity_wh: '45.2Wh',
        capacity_mah: '5970mAh',
        cell_count: '2-Cell',
        chemistry: 'Li-Polymer',
        compatible_models: [
            'Microsoft Surface Laptop 1 1769', 'Microsoft Surface Laptop 2 1769', 'Microsoft Surface Laptop 3 13.5" 1867 1868'
        ],
        connector_type: 'Internal Flat Flex',
        notes: 'Fits Surface Laptop 1 / 2 / 3 with Alcantara / Metal palmrest.',
        warehouse_location: 'Bin BAT-M02'
    }
];

/**
 * Normalizes strings for robust matching.
 */
function normalizeSearchText(str) {
    if (!str) return '';
    return str.toLowerCase().replace(/[^a-z0-9]/g, '');
}

/**
 * Searches the catalog by laptop model.
 */
function searchBatteriesForLaptop(laptopQuery) {
    if (!laptopQuery || !laptopQuery.trim()) return [];
    const qClean = laptopQuery.trim().toLowerCase();
    const qTokens = qClean.split(/\s+/).filter(t => t.length > 0);

    return BATTERY_CATALOG.filter(bat => {
        const modelsList = Array.isArray(bat.compatible_models) ? bat.compatible_models.join(' ') : (bat.compatible_models || '');
        const combined = (bat.brand + ' ' + modelsList).toLowerCase();
        return qTokens.every(token => combined.includes(token));
    });
}

/**
 * Searches the catalog by battery part number / aliases.
 */
function searchLaptopsForBattery(batteryQuery) {
    if (!batteryQuery || !batteryQuery.trim()) return [];
    const qClean = batteryQuery.trim().toLowerCase();
    const qTokens = qClean.split(/\s+/).filter(t => t.length > 0);

    return BATTERY_CATALOG.filter(bat => {
        const combined = (bat.brand + ' ' + bat.part_number + ' ' + (bat.aliases || '') + ' ' + (bat.model_name || '')).toLowerCase();
        return qTokens.every(token => combined.includes(token));
    });
}

/**
 * Smart bidirectional search: looks across both laptop models and battery part numbers.
 */
function smartBatteryCrossSearch(query) {
    if (!query || !query.trim()) return BATTERY_CATALOG;
    const qClean = query.trim().toLowerCase();
    const qTokens = qClean.split(/\s+/).filter(t => t.length > 0);

    return BATTERY_CATALOG.filter(bat => {
        const modelsList = Array.isArray(bat.compatible_models) ? bat.compatible_models.join(' ') : (bat.compatible_models || '');
        const combined = (
            bat.brand + ' ' +
            bat.part_number + ' ' +
            (bat.model_name || '') + ' ' +
            (bat.aliases || '') + ' ' +
            (bat.voltage || '') + ' ' +
            (bat.capacity_wh || '') + ' ' +
            (bat.warehouse_location || '') + ' ' +
            modelsList
        ).toLowerCase();

        return qTokens.every(token => combined.includes(token));
    });
}

if (typeof window !== 'undefined') {
    window.BATTERY_CATALOG = BATTERY_CATALOG;
    window.searchBatteriesForLaptop = searchBatteriesForLaptop;
    window.searchLaptopsForBattery = searchLaptopsForBattery;
    window.smartBatteryCrossSearch = smartBatteryCrossSearch;
}
