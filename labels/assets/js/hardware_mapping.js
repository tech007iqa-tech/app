/**
 * assets/js/hardware_mapping.js
 *
 * Global JavaScript object mirror of includes/hardware_mapping.php.
 * Use these constants to reference hardware fields in the frontend.
 */

window.HW_FIELDS = {
    // Core Logic
    BRAND: 'brand',
    MODEL: 'model',
    SERIES: 'series',
    SERIAL_NUMBER: 'serial_number',
    ITEM_TYPE: 'type',

    // Processing
    CPU_GEN: 'cpu_gen',
    CPU_SPECS: 'cpu_specs',
    CPU_CORES: 'cpu_cores',
    CPU_SPEED: 'cpu_speed',
    CPU_DETAILS: 'cpu_details',

    // Internals
    RAM: 'ram',
    STORAGE: 'storage',
    GPU: 'gpu',
    SCREEN_RES: 'screen_res',

    // Technical Details
    BATTERY: 'battery',
    BATTERY_SPECS: 'battery_specs',
    WEBCAM: 'webcam',
    BACKLIT_KB: 'backlit_kb',
    OS_VERSION: 'os_version',
    COSMETIC_GRADE: 'cosmetic_grade',
    WORK_NOTES: 'work_notes',

    // Status/Location
    BIOS_STATE: 'bios_state',
    DESCRIPTION: 'description',
    STATUS: 'status',
    LOCATION: 'warehouse_location'
};

// Freeze the object to prevent accidental changes
Object.freeze(window.HW_FIELDS);
