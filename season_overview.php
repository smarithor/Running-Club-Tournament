<?php
// season_overview.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Validate inputs
$seasonId = isset($_GET['season_id']) && ctype_digit($_GET['season_id']) ? (int)$_GET['season_id'] : null;

try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // If no season_id given, default to current season
    if ($seasonId === null) {
        $stmt = $pdo->query("SELECT ID FROM Season WHERE StartDate <= CURDATE() AND EndDate >= CURDATE() LIMIT 1");
        $row = $stmt->fetch();
        if ($row) {
            $seasonId = (int)$row['ID'];
        } else {
            http_response_code(400);
            echo "No current season found.";
            exit;
        }
    }

    // Get season details
    $stmt = $pdo->prepare("SELECT ID, Name, StartDate, EndDate FROM Season WHERE ID = :sid");
    $stmt->execute([':sid' => $seasonId]);
    $season = $stmt->fetch();
    if (!$season) {
        http_response_code(404);
        echo "Season not found.";
        exit;
    }

    // Tournaments in season
    $tStmt = $pdo->prepare("SELECT ID, Name FROM Tournament WHERE SeasonID = :sid ORDER BY ID");
    $tStmt->execute([':sid' => $seasonId]);
    $tournaments = $tStmt->fetchAll();

    // Fencers in season
    $fStmt = $pdo->prepare("
        SELECT DISTINCT f.ID, f.Name, f.FullName
          FROM Fencer f
          JOIN TournamentFencer tf ON tf.FencerID = f.ID
          JOIN Tournament t ON t.ID = tf.TournamentID
         WHERE t.SeasonID = :sid
         ORDER BY f.Name
    ");
    $fStmt->execute([':sid' => $seasonId]);
    $fencers = $fStmt->fetchAll();

    // Staffing summary across season
    $staffStmt = $pdo->prepare("
        SELECT f.ID,
               f.Name,
               SUM(CASE WHEN m.Judge     = f.ID THEN 1 ELSE 0 END) AS JudgeCount,
               SUM(CASE WHEN m.Referee1  = f.ID THEN 1 ELSE 0 END) AS Ref1Count,
               SUM(CASE WHEN m.Referee2  = f.ID THEN 1 ELSE 0 END) AS Ref2Count,
               SUM(CASE WHEN m.MatchTable= f.ID THEN 1 ELSE 0 END) AS TableCount,
               SUM(
                   (CASE WHEN m.Judge     = f.ID THEN 1 ELSE 0 END) +
                   (CASE WHEN m.Referee1  = f.ID THEN 1 ELSE 0 END) +
                   (CASE WHEN m.Referee2  = f.ID THEN 1 ELSE 0 END) +
                   (CASE WHEN m.MatchTable= f.ID THEN 1 ELSE 0 END)
               ) AS TotalCount
          FROM Fencer f
          LEFT JOIN TournamentMatch m
            ON f.ID IN (m.Judge, m.Referee1, m.Referee2, m.MatchTable)
          LEFT JOIN Tournament t
            ON t.ID = m.TournamentID
           AND t.SeasonID = :sid
         GROUP BY f.ID, f.Name
         ORDER BY TotalCount DESC, f.Name
    ");
    $staffStmt->execute([':sid' => $seasonId]);
    $staffing = $staffStmt->fetchAll();

} catch (PDOException $e) {
    http_response_code(500);
    echo "Database error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    exit;
}

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Season Overview — <?= h($season['Name']) ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
    .season-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        align-items: start;
    }
    @media (max-width: 900px) {
        .season-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<div class="container">
    <h1>Season Overview — <?= h($season['Name']) ?></h1>
    <p class="meta">From <?= h($season['StartDate']) ?> to <?= h($season['EndDate']) ?></p>

    <h2>Tournaments</h2>
    <?php if ($tournaments): ?>
    <ul>
        <?php foreach ($tournaments as $t): ?>
            <li><a href="tournament_overview.php?tournament_id=<?= h((string)$t['ID']) ?>"><?= h($t['Name']) ?></a></li>
        <?php endforeach; ?>
    </ul>
    <?php else: ?>
        <p>No tournaments found for this season.</p>
    <?php endif; ?>

    <div class="season-grid">
        <div>
            <h2>Fencers</h2>
            <?php if ($fencers): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Full Name</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($fencers as $f): ?>
                    <tr>
                        <td class="mono"><?= h((string)$f['ID']) ?></td>
                        <td><a href="fencer_details.php?fencer_id=<?= h((string)$f['ID']) ?>&season_id=<?= h((string)$seasonId) ?>"><?= h($f['Name']) ?></a></td>
                        <td><?= h($f['FullName'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>No fencers found for this season.</p>
            <?php endif; ?>
        </div>

        <div>
            <h2>Staffing Summary (Season)</h2>
            <?php if ($staffing): ?>
            <table>
                <thead>
                    <tr>
                        <th>Fencer</th>
                        <th>Judging</th>
                        <th>Referee 1</th>
                        <th>Referee 2</th>
                        <th>Table</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($staffing as $s): ?>
                    <tr>
                        <td><?= h($s['Name']) ?></td>
                        <td class="mono"><?= h((string)$s['JudgeCount']) ?></td>
                        <td class="mono"><?= h((string)$s['Ref1Count']) ?></td>
                        <td class="mono"><?= h((string)$s['Ref2Count']) ?></td>
                        <td class="mono"><?= h((string)$s['TableCount']) ?></td>
                        <td class="mono"><strong><?= h((string)$s['TotalCount']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>No staffing data available for this season yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
