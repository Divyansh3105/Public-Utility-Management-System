    <footer class="app-footer" style="text-align: center; padding: 20px; color: #94a3b8; font-size: 0.9rem; margin-top: 40px;">
        <p>&copy; <?= date("Y") ?> Public Utility Management System. All rights reserved.</p>
    </footer>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
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
        });
    </script>
</body>
</html>
