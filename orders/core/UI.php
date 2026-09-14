<?php
/**
 * IQA System UI Helper
 * Renders HTML components for the interface.
 */

class UI {
    /**
     * Initializes theme script based on localStorage
     */
    public static function theme_init_script() {
        return '
        <script>
        (function() {
            const theme = localStorage.getItem("theme") || "light";
            document.documentElement.setAttribute("data-theme", theme);
            document.body.className = theme + "-theme";
        })();
        </script>
        ';
    }

    /**
     * Toggles between dark and light modes
     */
    public static function theme_toggle() {
        return '
        <button id="theme-toggle-btn" class="btn-theme-toggle" onclick="toggleTheme()" aria-label="Toggle dark mode" style="width: 44px; height: 40px; border-radius: 12px; background: white; border: 1px solid var(--border-color); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; box-shadow: var(--shadow-sm); transition: all 0.2s;">
            🌓
        </button>
        <script>
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute("data-theme") || "light";
            const newTheme = currentTheme === "dark" ? "light" : "dark";
            document.documentElement.setAttribute("data-theme", newTheme);
            document.body.className = newTheme + "-theme";
            localStorage.setItem("theme", newTheme);
        }
        </script>
        ';
    }

    /**
     * Outputs a hidden CSRF token input field
     */
    public static function csrf_field() {
        $token = Security::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Checks if the current request is an AJAX request.
     */
    public static function is_ajax() {
        return isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
    }

    /**
     * Renders UI notification banners from the session or URL parameter
     */
    public static function render_notifications() {
        $html = '';
        if (isset($_SESSION['msg'])) {
            $html .= '<div class="alert success"><span>' . htmlspecialchars($_SESSION['msg']) . '</span><button onclick="this.parentElement.remove()" style="background:none; border:none; color:inherit; cursor:pointer; font-weight:900;">✕</button></div>';
            unset($_SESSION['msg']);
        }
        if (isset($_SESSION['error'])) {
            $html .= '<div class="alert error"><span>' . htmlspecialchars($_SESSION['error']) . '</span><button onclick="this.parentElement.remove()" style="background:none; border:none; color:inherit; cursor:pointer; font-weight:900;">✕</button></div>';
            unset($_SESSION['error']);
        }
        return $html;
    }

    /**
     * Renders a dashboard statistic card
     */
    public static function stat_card($title, $value, $class = '', $id = '') {
        $class_attr = $class ? ' ' . $class : '';
        $id_attr = $id ? ' id="' . htmlspecialchars($id) . '"' : '';
        return '
        <div class="dashboard-card' . $class_attr . '"' . $id_attr . ' style="background: white; padding: 20px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); display: flex; flex-direction: column; justify-content: space-between; height: 110px;">
            <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-secondary); text-transform: uppercase;">' . htmlspecialchars($title) . '</div>
            <div style="font-size: 1.8rem; font-weight: 900; color: var(--text-main); line-height: 1.1;">' . htmlspecialchars($value) . '</div>
        </div>
        ';
    }
}
