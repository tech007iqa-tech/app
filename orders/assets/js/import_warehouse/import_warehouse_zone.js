/**
 * Warehouse Import Working Zone & Location Cascade Sub-Module
 * Manages target zone selection, cascade filtering of shelves/locations, and custom location creation.
 */

let zoneLocationsMap = {};

function initZoneState() {
    const stateEl = document.getElementById('import-warehouse-state');
    if (stateEl) {
        try {
            const data = JSON.parse(stateEl.textContent);
            zoneLocationsMap = data.zone_locations_map || {};
        } catch (e) {
            console.error('Failed to parse import warehouse state JSON:', e);
        }
    }
}

function onZoneChange() {
    const zoneSelect = document.getElementById('override_zone_select');
    const customZoneInput = document.getElementById('override_zone_custom');
    const locSelect = document.getElementById('override_location_select');
    const customLocInput = document.getElementById('override_location_custom');

    if (!zoneSelect || !locSelect) return;

    if (customZoneInput) {
        customZoneInput.style.display = 'none';
        customZoneInput.required = false;
        customZoneInput.value = '';
    }

    if (customLocInput) {
        customLocInput.style.display = 'none';
        customLocInput.required = false;
        customLocInput.value = '';
    }

    locSelect.innerHTML = '<option value="">📄 Keep Row-Level Locations (Default)</option>';

    if (zoneSelect.value === '__NEW_ZONE__') {
        if (customZoneInput) {
            customZoneInput.style.display = 'inline-block';
            customZoneInput.required = true;
            customZoneInput.focus();
        }

        const optNew = document.createElement('option');
        optNew.value = '__NEW_LOC__';
        optNew.textContent = '+ Create New Location...';
        locSelect.appendChild(optNew);
        locSelect.value = '__NEW_LOC__';
        toggleCustomLocationInput();
    } else if (zoneSelect.value !== '') {
        const selectedZone = zoneSelect.value;
        const locs = zoneLocationsMap[selectedZone] || [];

        locs.forEach(loc => {
            const opt = document.createElement('option');
            opt.value = loc;
            opt.textContent = loc;
            locSelect.appendChild(opt);
        });

        const optNew = document.createElement('option');
        optNew.value = '__NEW_LOC__';
        optNew.textContent = '+ Create New Location...';
        locSelect.appendChild(optNew);
    }
}

function toggleCustomLocationInput() {
    const select = document.getElementById('override_location_select');
    const customInput = document.getElementById('override_location_custom');
    if (select && customInput) {
        if (select.value === '__NEW_LOC__') {
            customInput.style.display = 'inline-block';
            customInput.required = true;
            customInput.focus();
        } else {
            customInput.style.display = 'none';
            customInput.required = false;
            customInput.value = '';
        }
    }
}
