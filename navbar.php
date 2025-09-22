<?php
require_once __DIR__ . '/auth.php';
$loggedIn = is_logged_in();

$current = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar">
    <a href="/"><img src="favicon.png" alt="Home" style="width:18px;height:18px;"></a>
    <a href="season_overview.php" class="<?= $current === 'season_overview.php' ? 'selected' : '' ?>">Season</a>
    <a href="tournament_overview.php?tournament_id=1" class="<?= $current === 'tournament_overview.php' && ($_GET['tournament_id'] ?? '') === '1' ? 'selected' : '' ?>">Longsword</a>
    <a href="tournament_overview.php?tournament_id=2" class="<?= $current === 'tournament_overview.php' && ($_GET['tournament_id'] ?? '') === '2' ? 'selected' : '' ?>">Saber</a>
    <a href="tournament_overview.php?tournament_id=3" class="<?= $current === 'tournament_overview.php' && ($_GET['tournament_id'] ?? '') === '3' ? 'selected' : '' ?>">Rapier</a>

    <?php if ($loggedIn): ?>
        <div class="dropdown">
            <button class="dropbtn">Administration ▾</button>
            <div class="dropdown-content">
                <a href="register_match.php">Register match</a>
                <a href="fencers.php">Fencers</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    <?php else: ?>
        <a href="login.php" class="dropbtn">Administration</a>
    <?php endif; ?>

    <a href="https://2-71828.com/hema/timer/">Match clock</a>

    <!-- Dark mode toggle -->
    <button id="darkModeToggle" class="dark-toggle" title="Toggle dark mode">🌙</button>
</nav>

<script>
    // Apply saved theme or system preference
    (function() {
        let savedTheme = localStorage.getItem('theme');
        if (!savedTheme) {
            // Detect system preference
            savedTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', savedTheme);
        updateToggleIcon(savedTheme);
    })();

    function updateToggleIcon(theme) {
        const btn = document.getElementById('darkModeToggle');
        if (!btn) return;
        btn.textContent = theme === 'dark' ? '☀️' : '🌙';
    }

    document.getElementById('darkModeToggle').addEventListener('click', () => {
        let currentTheme = document.documentElement.getAttribute('data-theme');
        let newTheme = (currentTheme === 'dark') ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateToggleIcon(newTheme);
    });
</script>