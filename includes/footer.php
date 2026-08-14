    <div id="toast-container"></div>

    <footer class="app-footer" style="text-align: center; padding: 20px; color: #94a3b8; font-size: 0.9rem; margin-top: 40px;">
        <p>&copy; <?= date("Y") ?> Public Utility Management System. All rights reserved.</p>
    </footer>

    <script>
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
            // Theme Toggle Logic with LocalStorage Persistence
            const toggleBtn = document.getElementById("toggle-theme");
            if (toggleBtn) {
                const isDark = localStorage.getItem("theme") === "dark";
                if (isDark) {
                    document.body.classList.add("dark-mode");
                    const icon = toggleBtn.querySelector("i");
                    const label = toggleBtn.querySelector("span");
                    if (icon) icon.className = "fas fa-sun";
                    if (label) label.textContent = "Light Mode";
                }

                toggleBtn.addEventListener("click", () => {
                    document.body.classList.toggle("dark-mode");
                    const darkActive = document.body.classList.contains("dark-mode");
                    localStorage.setItem("theme", darkActive ? "dark" : "light");
                    const icon = toggleBtn.querySelector("i");
                    const label = toggleBtn.querySelector("span");
                    if (icon) icon.className = darkActive ? "fas fa-sun" : "fas fa-moon";
                    if (label) label.textContent = darkActive ? "Light Mode" : "Dark Mode";
                });
            }

            // Shrink Header on Scroll
            const header = document.getElementById("header");
            if (header) {
                window.addEventListener("scroll", () => {
                    if (window.scrollY > 30) {
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
