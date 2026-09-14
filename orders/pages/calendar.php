<?php
/**
 * Calendar Module - Integrated Version
 * Pulls from calendar.db and integrates with main app shell.
 */

$user_role = $_SESSION['role'] ?? 'Operator';
if ($user_role !== 'Admin' && $user_role !== 'Front Desk') {
    echo "<div class='glass-card' style='padding: 2rem; text-align: center;'><h2>Unauthorized</h2><p>You do not have permission to access the Calendar.</p></div>";
    return;
}

// Date Handling
date_default_timezone_set('America/Los_Angeles');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$current_view = isset($_GET['view_type']) ? $_GET['view_type'] : 'week';
$week_offset = isset($_GET['week_offset']) ? (int)$_GET['week_offset'] : 0;

// Create a date object for the first of the month
$first_day_of_month = mktime(0, 0, 0, $month, 1, $year);
$number_of_days = date('t', $first_day_of_month);
$date_components = getdate($first_day_of_month);
$month_name = $date_components['month'];
$day_of_week = $date_components['wday']; // 0 (Sun) to 6 (Sat)

// Weekly View Date Logic
$today = time();
$day_of_week_now = date('w', $today);
$offset_to_monday = ($day_of_week_now == 0) ? 6 : $day_of_week_now - 1;
$monday_ts = $today - ($offset_to_monday * 86400) + ($week_offset * 7 * 86400);
$friday_ts = $monday_ts + (4 * 86400);

// Fetch Events from Calendar DB
$events = [];
try {
    $pdo_cal = Database::calendar();

    // Ensure table exists
    $pdo_cal->exec("CREATE TABLE IF NOT EXISTS events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        event_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        color TEXT DEFAULT '#38bdf8',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // SCHEMA MIGRATION: Check if customer_id column exists, add if missing
    $cols = $pdo_cal->query("PRAGMA table_info(events)")->fetchAll(PDO::FETCH_ASSOC);
    $has_customer_id = false;
    foreach ($cols as $col) {
        if ($col['name'] === 'customer_id') {
            $has_customer_id = true;
            break;
        }
    }
    if (!$has_customer_id) {
        $pdo_cal->exec("ALTER TABLE events ADD COLUMN customer_id TEXT");
    }

    // Load all customers for the modal
    $pdo_cust_list = Database::customers();
    $all_customers = $pdo_cust_list->query("SELECT customer_id, company_name FROM customers ORDER BY company_name ASC")->fetchAll();

    $stmt = $pdo_cal->query("SELECT * FROM events");
    while ($row = $stmt->fetch()) {
        $event_id = $row['id'];
        $event_date = $row['event_date'];
        $cust_id = $row['customer_id'];

        $conversion_status = null;
        if ($cust_id) {
            // SMART CONVERSION: Check if this customer placed an order +/- 3 days from this visit
            $pdo_ord_check = Database::orders();
            $stmt_conv = $pdo_ord_check->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND created_at BETWEEN date(?, '-1 day') AND date(?, '+3 days')");
            $stmt_conv->execute([$cust_id, $event_date, $event_date]);
            $order_count = $stmt_conv->fetchColumn();

            $conversion_status = ($order_count > 0) ? 'converted' : 'window_shop';
        }

        $row['conversion_status'] = $conversion_status;
        $events[$event_date][] = $row;
    }
} catch (Exception $e) {
    // Fail silently
}

// Dynamic Title
if ($current_view === 'week') {
    $page_title_display = date('M d', $monday_ts) . " - " . date('M d, Y', $friday_ts);
} else {
    $page_title_display = "$month_name $year";
}
?>

<div class="calendar-module-container <?php echo 'view-' . $current_view; ?>">
    <header class="calendar-header-main">
        <div class="header-left">
            <h1 style="color: var(--text-main); font-weight: 800;"><?php echo $page_title_display; ?></h1>
            <p style="color: var(--text-dim); font-size: 0.85rem; margin-top: 5px;">Admin Scheduling & Visit Logs</p>
        </div>
        <nav class="calendar-nav-controls">
            <div class="view-controls">
                <button class="<?php echo $current_view === 'month' ? 'active' : ''; ?>" onclick="location.href='index.php?view=calendar&view_type=month&month=<?php echo $month; ?>&year=<?php echo $year; ?>'">Month</button>
                <button class="<?php echo $current_view === 'week' ? 'active' : ''; ?>" onclick="location.href='index.php?view=calendar&view_type=week&week_offset=0'">Week</button>

                <?php if ($week_offset != 0 || $current_view === 'month'): ?>
                    <button onclick="location.href='index.php?view=calendar&view_type=week&week_offset=0'" style="background: rgba(255,255,255,0.1); color: var(--accent); margin-left: 0.5rem;">
                        Today
                    </button>
                <?php endif; ?>
            </div>
            <div class="month-controls">
                <?php if ($current_view === 'week'): ?>
                    <button onclick="location.href='index.php?view=calendar&view_type=week&week_offset=<?php echo $week_offset - 1; ?>'"><</button>
                    <button onclick="location.href='index.php?view=calendar&view_type=week&week_offset=<?php echo $week_offset + 1; ?>'">></button>
                <?php else: ?>
                    <button onclick="location.href='index.php?view=calendar&view_type=month&view_type=month&month=<?php echo $month-1 == 0 ? 12 : $month-1; ?>&year=<?php echo $month-1 == 0 ? $year-1 : $year; ?>'"><</button>
                    <button onclick="location.href='index.php?view=calendar&view_type=month&view_type=month&month=<?php echo $month+1 == 13 ? 1 : $month+1; ?>&year=<?php echo $month+1 == 13 ? $year+1 : $year; ?>'">></button>
                <?php endif; ?>
            </div>
            <button class="btn-create-event" onclick="openEventModal('<?php echo date('Y-m-d'); ?>')" style="margin-left: 1rem; box-shadow: 0 0 20px rgba(56, 189, 248, 0.4);">+ Create Event</button>
        </nav>
    </header>

    <main class="glass-card calendar-body">
        <?php if ($current_view === 'week'): ?>
            <!-- Weekly View (Mon-Fri) -->
            <div class="calendar-grid-header" style="grid-template-columns: 100px repeat(5, 1fr);">
                <div class="header-cell">Time</div>
                <?php
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                for ($i = 0; $i < 5; $i++):
                    $current_day_ts = $monday_ts + ($i * 86400);
                    $is_today_col = (date('Y-m-d', $current_day_ts) == date('Y-m-d'));
                ?>
                    <div class="header-cell <?php echo $is_today_col ? 'is-today-col' : ''; ?>">
                        <?php echo $days[$i]; ?><br>
                        <small style="color: var(--text-dim); font-weight: 400;"><?php echo date('M d', $current_day_ts); ?></small>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="weekly-scroll-area">
                <?php
                $start_hour = 8;
                $end_hour = 17;
                for ($h = $start_hour; $h <= $end_hour; $h++):
                    $time_label = ($h <= 12) ? "$h:00 AM" : ($h-12).":00 PM";
                    if ($h == 12) $time_label = "12:00 PM";
                    $is_lunch = ($h == 12);
                    $current_time_str = sprintf('%02d:00', $h);
                ?>
                    <div class="weekly-row <?php echo $is_lunch ? 'lunch-break' : ''; ?>">
                        <div class="time-slot-label"><?php echo $time_label; ?></div>
                        <?php for($d=0; $d<5; $d++):
                            $current_date_str = date('Y-m-d', $monday_ts + ($d * 86400));
                            $slot_events = [];
                            if (isset($events[$current_date_str])) {
                                foreach ($events[$current_date_str] as $ev) {
                                    $ev_start = (int)substr($ev['start_time'], 0, 2);
                                    $ev_end = (int)substr($ev['end_time'], 0, 2);
                                    if ($h >= $ev_start && $h < $ev_end) $slot_events[] = $ev;
                                }
                            }
                            $has_overlap = count($slot_events) > 1;
                        ?>
                            <div class="grid-cell <?php echo $has_overlap ? 'has-overlap' : ''; ?>"
                                 onclick="openEventModal('<?php echo $current_date_str; ?>', '<?php echo $current_time_str; ?>')">
                                <?php
                                foreach ($slot_events as $ev) {
                                    $ev_start = (int)substr($ev['start_time'], 0, 2);
                                    $is_start = ($h == $ev_start);
                                    $style = "background: " . htmlspecialchars($ev['color']) . ";";
                                    $style .= "box-shadow: 0 4px 10px -2px " . htmlspecialchars($ev['color']) . "88;";
                                    $tooltip = htmlspecialchars($ev['title'] . ($ev['description'] ? ": " . $ev['description'] : ""));
                                    echo '<div class="event-block ' . ($is_start ? 'is-start' : 'is-cont') . '"
                                                style="' . $style . '"
                                                title="' . $tooltip . '"
                                                onclick="event.stopPropagation(); openEditModal(' . htmlspecialchars(json_encode($ev)) . ')">';
                                     if ($is_start) {
                                         echo '<span class="event-title-text">' . htmlspecialchars($ev['title']) . '</span>';
                                     }
                                     echo '</div>';
                                }
                                ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                <?php endfor; ?>
            </div>

        <?php else: ?>
            <!-- Monthly View -->
            <div class="calendar-grid-header">
                <div class="header-cell">Mon</div><div class="header-cell">Tue</div><div class="header-cell">Wed</div><div class="header-cell">Thu</div><div class="header-cell">Fri</div>
            </div>
            <div class="calendar-month-grid">
                <?php
                $first_day_dw = (int)date('N', $first_day_of_month); // 1 (Mon) to 7 (Sun)
                $offset = ($first_day_dw <= 5) ? ($first_day_dw - 1) : 0;
                $rendered_days_count = 0;

                for ($x = 0; $x < $offset; $x++) {
                    echo '<div class="grid-cell muted"></div>';
                }

                for ($i = 1; $i <= $number_of_days; $i++):
                    $current_date_ts = mktime(0, 0, 0, $month, $i, $year);
                    $dw = (int)date('N', $current_date_ts);
                    if ($dw > 5) {
                        continue; // Skip Saturday and Sunday
                    }
                    $rendered_days_count++;
                    $current_date = sprintf('%04d-%02d-%02d', $year, $month, $i);
                    $is_today = ($current_date == date('Y-m-d')) ? 'is-today' : '';
                ?>
                    <div class="grid-cell <?php echo $is_today; ?>" onclick="openEventModal('<?php echo $current_date; ?>')">
                        <div class="day-number"><?php echo $i; ?></div>
                        <div class="event-list-container">
                            <?php
                            if (isset($events[$current_date])) {
                                foreach ($events[$current_date] as $ev) {
                                    $conv = $ev['conversion_status'];
                                    $conv_icon = '';
                                    if ($conv === 'converted') $conv_icon = ' ✅ Sale';
                                    if ($conv === 'window_shop') $conv_icon = ' 👀 Window Shop';

                                    echo '<div class="event-pill ' . ($conv ? 'has-conversion ' . $conv : '') . '"
                                               title="' . htmlspecialchars($ev['start_time']) . '"
                                               style="background: ' . htmlspecialchars($ev['color']) . '; box-shadow: 0 4px 10px -2px ' . htmlspecialchars($ev['color']) . '88;"
                                               onclick="event.stopPropagation(); openEditModal(' . htmlspecialchars(json_encode($ev)) . ')">'
                                          . htmlspecialchars($ev['title']) . $conv_icon .
                                          '</div>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                <?php endfor;

                // Fill remaining cells for a clean grid layout (multiples of 5)
                $total_cells = $offset + $rendered_days_count;
                $remaining_cells = (5 - ($total_cells % 5)) % 5;
                for ($x = 0; $x < $remaining_cells; $x++) {
                    echo '<div class="grid-cell muted"></div>';
                }
                ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Event Modal -->
<div id="calendarEventModal" class="calendar-modal">
    <div class="modal-content-glass">
        <div class="modal-header">
            <h2 id="modalTitle">New Entry</h2>
            <button type="button" class="close-btn" onclick="closeCalendarModal()">&times;</button>
        </div>
        <form id="calendarEventForm" action="api/calendar/save.php" method="POST">
            <input type="hidden" id="eventId" name="event_id" value="">
            <input type="hidden" name="week_offset" value="<?php echo $week_offset; ?>">
            <input type="hidden" name="view_type" value="<?php echo $current_view; ?>">
            <input type="hidden" id="eventColor" name="color" value="#38bdf8">

            <div class="form-group-cal">
                <label for="eventTitle">Title</label>
                <input type="text" id="eventTitle" name="title" required placeholder="Project Name, Meeting, etc.">
            </div>

            <div class="form-group-cal">
                <label for="eventCustomer">Link to Customer / Visit</label>
                <select id="eventCustomer" name="customer_id" class="form-control-cal">
                    <option value="">-- No Customer (Internal Event) --</option>
                    <?php foreach ($all_customers as $cust): ?>
                        <option value="<?php echo htmlspecialchars($cust['customer_id']); ?>">
                            <?php echo htmlspecialchars($cust['company_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group-cal">
                <label for="eventDesc">Description / Notes</label>
                <textarea id="eventDesc" name="description" placeholder="Add links, notes, or details..."></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group-cal">
                    <label for="eventDate">Date</label>
                    <input type="date" id="eventDate" name="event_date" required>
                </div>

                <div class="form-group-cal">
                    <label for="eventColor">Category Color</label>
                    <div class="color-options-cal">
                        <div class="color-dot-cal active" data-color="#38bdf8" style="background: #38bdf8" onclick="selectColor(this, '#38bdf8')"></div>
                        <div class="color-dot-cal" data-color="#10b981" style="background: #10b981" onclick="selectColor(this, '#10b981')"></div>
                        <div class="color-dot-cal" data-color="#f59e0b" style="background: #f59e0b" onclick="selectColor(this, '#f59e0b')"></div>
                        <div class="color-dot-cal" data-color="#ef4444" style="background: #ef4444" onclick="selectColor(this, '#ef4444')"></div>
                        <div class="color-dot-cal" data-color="#8b5cf6" style="background: #8b5cf6" onclick="selectColor(this, '#8b5cf6')"></div>
                    </div>
                </div>
            </div>

            <div class="form-group-cal" style="text-align: center;">
                <label for="startTime" style="display: block; text-align: left;">Schedule (8 AM - 5 PM)</label>
                <div id="timeTimeline" class="timeline-picker"></div>
                <input type="hidden" id="startTime" name="start_time" value="08:00">
                <input type="hidden" id="endTime" name="end_time" value="09:00">
                <div id="durationLabel" class="duration-pill">Duration: 1 hour</div>
            </div>

            <div class="modal-footer-cal">
                <button type="button" id="deleteBtn" onclick="deleteCalendarEvent()" class="btn-delete-cal">Delete Entry</button>
                <button type="button" onclick="closeCalendarModal()" class="btn-cancel-cal">Discard</button>
                <button type="submit" id="submitBtn" class="btn-submit-cal">Save Event</button>
            </div>
        </form>
    </div>
</div>

<script>
    let selectedStart = null;
    let selectedEnd = null;

    function initTimeline() {
        const container = document.getElementById('timeTimeline');
        if (!container) return;
        container.innerHTML = '';
        for (let h = 8; h <= 17; h++) {
            const chip = document.createElement('div');
            chip.className = 'timeline-chip';

            const displayHour = (h > 12 ? h-12 : h);
            const ampm = (h >= 12 ? 'pm' : 'am');

            chip.innerHTML = `
                <span class="h-val">${displayHour}</span>
                <span class="a-val">${ampm}</span>
            `;

            chip.dataset.hour = h;
            chip.onclick = () => selectHour(h);
            container.appendChild(chip);
        }
    }

    function selectColor(el, color) {
        document.querySelectorAll('.color-dot-cal').forEach(d => d.classList.remove('active'));
        el.classList.add('active');
        document.getElementById('eventColor').value = color;
    }

    function selectHour(h) {
        if (!selectedStart || (selectedStart && selectedEnd)) {
            selectedStart = h;
            selectedEnd = null;
        } else if (h > selectedStart) {
            selectedEnd = h;
        } else {
            selectedStart = h;
            selectedEnd = null;
        }
        updateTimelineUI();
    }

    // Smart Keyword Detection
    document.getElementById('eventTitle').addEventListener('input', function(e) {
        const val = e.target.value.toLowerCase();
        if (val.includes('meeting') || val.includes('lunch')) {
            if (selectedStart && !selectedEnd) {
                selectedEnd = Math.min(selectedStart + 1, 17);
                updateTimelineUI();
            }
        } else if (val.includes('workshop') || val.includes('training')) {
            if (selectedStart) {
                selectedEnd = Math.min(selectedStart + 2, 17);
                updateTimelineUI();
            }
        }
    });

    function updateTimelineUI() {
        const chips = document.querySelectorAll('.timeline-chip');
        chips.forEach(chip => {
            const h = parseInt(chip.dataset.hour);
            chip.classList.remove('selected', 'in-range');

            if (h === selectedStart || h === selectedEnd) chip.classList.add('selected');
            if (selectedStart && selectedEnd && h > selectedStart && h < selectedEnd) {
                chip.classList.add('in-range');
            }
        });

        if (selectedStart) {
            document.getElementById('startTime').value = selectedStart.toString().padStart(2, '0') + ':00';
            if (!selectedEnd) {
                let end = Math.min(selectedStart + 1, 17);
                document.getElementById('endTime').value = end.toString().padStart(2, '0') + ':00';
                document.getElementById('durationLabel').innerText = `Duration: 1 hour`;
            } else {
                document.getElementById('endTime').value = selectedEnd.toString().padStart(2, '0') + ':00';
                document.getElementById('durationLabel').innerText = `Duration: ${selectedEnd - selectedStart} hours`;
            }
        }
    }

    function openEventModal(date, time = '08:00') {
        document.getElementById('modalTitle').innerText = 'New Entry';
        document.getElementById('eventId').value = '';
        document.getElementById('eventTitle').value = '';
        document.getElementById('eventDesc').value = '';
        document.getElementById('eventCustomer').value = '';
        document.getElementById('eventDate').value = date;
        document.getElementById('deleteBtn').style.display = 'none';
        document.getElementById('submitBtn').innerText = 'Create Event';

        selectedStart = parseInt(time.split(':')[0]);
        selectedEnd = Math.min(selectedStart + 1, 17);

        initTimeline();
        updateTimelineUI();
        document.getElementById('calendarEventModal').classList.add('active');
    }

    function openEditModal(event) {
        document.getElementById('modalTitle').innerText = 'Edit Entry';
        document.getElementById('eventId').value = event.id;
        document.getElementById('eventTitle').value = event.title;
        document.getElementById('eventDesc').value = event.description || '';
        document.getElementById('eventCustomer').value = event.customer_id || '';
        document.getElementById('eventDate').value = event.event_date;
        document.getElementById('deleteBtn').style.display = 'block';
        document.getElementById('submitBtn').innerText = 'Save Changes';

        selectedStart = parseInt(event.start_time.split(':')[0]);
        selectedEnd = parseInt(event.end_time.split(':')[0]);

        document.getElementById('eventColor').value = event.color;
        document.querySelectorAll('.color-dot-cal').forEach(d => {
            d.classList.toggle('active', d.dataset.color === event.color);
        });

        initTimeline();
        updateTimelineUI();
        document.getElementById('calendarEventModal').classList.add('active');
    }

    function deleteCalendarEvent() {
        const id = document.getElementById('eventId').value;
        if (id && confirm('Are you sure you want to delete this entry?')) {
            const offset = <?php echo $week_offset; ?>;
            const viewType = '<?php echo $current_view; ?>';
            const csrfToken = '<?= Security::getToken() ?>';
            location.href = `api/calendar/delete.php?id=${id}&week_offset=${offset}&view_type=${viewType}&csrf_token=${csrfToken}`;
        }
    }

    function closeCalendarModal() {
        document.getElementById('calendarEventModal').classList.remove('active');
    }

    document.addEventListener('DOMContentLoaded', initTimeline);
</script>
