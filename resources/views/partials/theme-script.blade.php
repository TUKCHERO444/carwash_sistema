<script>
    // On page load or when changing themes, best to add inline in `head` to avoid FOUC
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }

    document.addEventListener('DOMContentLoaded', function() {
        var isDark = document.documentElement.classList.contains('dark');

        // Sync initial icon state for ALL toggle buttons
        document.querySelectorAll('[data-theme-toggle]').forEach(function(btn) {
            var darkIcon  = btn.querySelector('[data-theme-icon="dark"]');
            var lightIcon = btn.querySelector('[data-theme-icon="light"]');
            if (isDark) {
                if (lightIcon) lightIcon.classList.remove('hidden');
                if (darkIcon)  darkIcon.classList.add('hidden');
            } else {
                if (darkIcon)  darkIcon.classList.remove('hidden');
                if (lightIcon) lightIcon.classList.add('hidden');
            }
        });

        function applyTheme(dark) {
            if (dark) {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            }
            // Update icons on ALL toggle buttons
            document.querySelectorAll('[data-theme-toggle]').forEach(function(btn) {
                var darkIcon  = btn.querySelector('[data-theme-icon="dark"]');
                var lightIcon = btn.querySelector('[data-theme-icon="light"]');
                if (dark) {
                    if (lightIcon) lightIcon.classList.remove('hidden');
                    if (darkIcon)  darkIcon.classList.add('hidden');
                } else {
                    if (darkIcon)  darkIcon.classList.remove('hidden');
                    if (lightIcon) lightIcon.classList.add('hidden');
                }
            });
        }

        document.querySelectorAll('[data-theme-toggle]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                applyTheme(!document.documentElement.classList.contains('dark'));
            });
        });
    });
</script>