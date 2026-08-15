        </div> <!-- close .main-content -->
    </div> <!-- close .dashboard-layout -->

    <div id="toast-container"></div>

    <footer class="app-footer" style="text-align: center; padding: 20px; color: var(--text-muted); font-size: 0.85rem; margin-top: auto;">
        <p>&copy; <?= date("Y") ?> Public Utility Management System &bull; Enterprise Operations Suite</p>
    </footer>

    <script>
        // Global Sidebar Toggle for Responsive Mobile Navigation
        function toggleSidebar() {
            const sidebar = document.getElementById("appSidebar");
            const backdrop = document.getElementById("sidebarBackdrop");
            if (sidebar) sidebar.classList.toggle("open");
            if (backdrop) backdrop.classList.toggle("active");
        }

        // Global Theme Toggle with LocalStorage
        function toggleTheme() {
            document.body.classList.toggle("dark-mode");
            const isDark = document.body.classList.contains("dark-mode");
            localStorage.setItem("theme", isDark ? "dark" : "light");
            updateThemeIcons(isDark);
        }

        function updateThemeIcons(isDark) {
            const themeIcons = document.querySelectorAll(".theme-icon, #toggle-theme i");
            themeIcons.forEach(icon => {
                icon.className = isDark ? "fas fa-sun" : "fas fa-moon";
            });
            const themeLabels = document.querySelectorAll("#toggle-theme span");
            themeLabels.forEach(label => {
                label.textContent = isDark ? "Light Mode" : "Dark Mode";
            });
        }

        // Global Toast Notification Helper
        function showToast(message, type = "success", duration = 4000) {
            const container = document.getElementById("toast-container");
            if (!container) return;

            const toast = document.createElement("div");
            toast.className = `toast toast-${type}`;

            let iconClass = "fa-check-circle";
            if (type === "error" || type === "danger") iconClass = "fa-exclamation-circle";
            else if (type === "warning") iconClass = "fa-triangle-exclamation";
            else if (type === "info") iconClass = "fa-info-circle";

            toast.innerHTML = `<i class="fas ${iconClass}"></i> <span>${message}</span>`;
            container.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = "0";
                toast.style.transform = "translateX(100%)";
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }

        document.addEventListener("DOMContentLoaded", () => {
            // Restore theme preference
            const savedTheme = localStorage.getItem("theme");
            if (savedTheme === "dark") {
                document.body.classList.add("dark-mode");
                updateThemeIcons(true);
            }

            const headerThemeBtn = document.getElementById("toggle-theme");
            if (headerThemeBtn) {
                headerThemeBtn.addEventListener("click", toggleTheme);
            }

            // Shrink Header on Scroll
            const header = document.getElementById("header");
            if (header) {
                window.addEventListener("scroll", () => {
                    if (window.scrollY > 25) {
                        header.classList.add("shrink");
                    } else {
                        header.classList.remove("shrink");
                    }
                });
            }

            // Auto-trigger Toast for page alert banners if present
            const pageAlert = document.querySelector(".alert");
            if (pageAlert) {
                const msg = pageAlert.querySelector("span") ? pageAlert.querySelector("span").textContent : pageAlert.textContent;
                let type = "info";
                if (pageAlert.classList.contains("alert-success")) type = "success";
                else if (pageAlert.classList.contains("alert-danger")) type = "error";
                else if (pageAlert.classList.contains("alert-warning")) type = "warning";
                if (msg && msg.trim()) {
                    showToast(msg.trim(), type);
                }
            }
        });
    </script>
</body>
</html>
