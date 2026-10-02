# Completed Updates — 9/5/2026 10:52 PM

- [x] **/orders/index.php?view=leads**: In the timeline log, added past orders list view stacked directly on top of the interaction logs (two dedicated boxes with live order fetching from `orders.db`, order manifest links, unit counts, totals, and communication notes).
- [x] **/orders/index.php?view=trends**: Server-side active tab synchronization (`$active_tab`) eliminating initial tab flash on load.
- [x] **/orders/index.php?view=trends&tab=tab-tested**: Read-only rendering for non-Admin users.
- [x] **/orders/index.php?view=trends&tab=tab-matrix**: B2B Untested role-based protection for regular users and enhanced Admin editing with Reference vs Bulk Edit modes.