<?php
// fencer_details.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Validate inputs
if (!isset($_GET['fencer_id']) || !ctype_digit($_GET['fencer_id'])) {
    http_response_code(400);
    echo "Missing or invalid 'fencer_id'.";
    exit;
}

$fencerId     = (int) $_GET['fencer_id'];
$tournamentId = isset($_GET['tournament_id']) && ctype_digit($_GET['tournament_id']) ? (int)$_GET['tournament_id'] : null;
$seasonId     = isset($_GET['season_id']) && ctype_digit($_GET['season_id']) ? (int)$_GET['season_id'] : null;

$tournamentColors = [
    'longsword' => '#1f77b4', // blue
    'saber'     => '#d62728', // red
    'rapier'    => '#2ca02c', // green
];

try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Get fencer
    $stmt = $pdo->prepare("SELECT ID, FullName, Name FROM Fencer WHERE ID = :fid");
    $stmt->execute([':fid' => $fencerId]);
    $fencer = $stmt->fetch();
    if (!$fencer) {
        http_response_code(404);
        echo "Fencer not found.";
        exit;
    }

    // If no season_id, use current season
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

    // Match history (unchanged)
    $matches = [];
    $tournamentName = null;
    $backLink = null;

    if ($tournamentId !== null) {
        $stmt = $pdo->prepare("SELECT Name FROM Tournament WHERE ID = :tid");
        $stmt->execute([':tid' => $tournamentId]);
        $tournament = $stmt->fetch();
        $tournamentName = $tournament['Name'] ?? ('Tournament ID ' . $tournamentId);
        $backLink = 'tournament_overview.php?tournament_id=' . $tournamentId;

        $sql = "
            SELECT tm.ID AS MatchID,
                   tm.FightDate,
                   tm.ChallengerID,
                   tm.ChallengedID,
                   c.Name AS ChallengerName,
                   d.Name AS ChallengedName,
                   j.Name AS JudgeName,
                   r1.Name AS Ref1Name,
                   r2.Name AS Ref2Name,
                   ta.Name AS TableName,
                   tm.ChallengerScore,
                   tm.ChallangedScore,
                   tm.ChallengerWarnings,
                   tm.ChallengedWarnings,
                   tm.Doubles,
                   t.Name AS TournamentName,
                   CASE WHEN tm.ChallengerID = :fid THEN 'Challenger' ELSE 'Challenged' END AS MyRole,
                   CASE WHEN tm.ChallengerID = :fid THEN tm.ChallengerScore ELSE tm.ChallangedScore END AS MyScore,
                   CASE WHEN tm.ChallengerID = :fid THEN tm.ChallangedScore ELSE tm.ChallengerScore END AS OppScore,
                   CASE WHEN tm.ChallengerID = :fid THEN d.Name ELSE c.Name END AS OpponentName,
                   CASE WHEN tm.ChallengerID = :fid THEN d.ID ELSE c.ID END AS OpponentID,
                   CASE
                       WHEN (CASE WHEN tm.ChallengerID = :fid THEN tm.ChallengerScore ELSE tm.ChallangedScore END) >
                            (CASE WHEN tm.ChallengerID = :fid THEN tm.ChallangedScore ELSE tm.ChallengerScore END) THEN 'Win'
                       WHEN (CASE WHEN tm.ChallengerID = :fid THEN tm.ChallengerScore ELSE tm.ChallangedScore END) <
                            (CASE WHEN tm.ChallengerID = :fid THEN tm.ChallangedScore ELSE tm.ChallengerScore END) THEN 'Loss'
                       ELSE 'Draw'
                   END AS Result,
                   CASE WHEN tm.ChallengerID = :fid THEN tm.ChallengerWarnings ELSE tm.ChallengedWarnings END AS MyWarnings,
                   CASE WHEN tm.ChallengerID = :fid THEN tm.ChallengedWarnings ELSE tm.ChallengerWarnings END AS OppWarnings
            FROM TournamentMatch tm
            JOIN Tournament t ON t.ID = tm.TournamentID
            JOIN Fencer c  ON c.ID  = tm.ChallengerID
            JOIN Fencer d  ON d.ID  = tm.ChallengedID
            LEFT JOIN Fencer j  ON j.ID  = tm.Judge
            LEFT JOIN Fencer r1 ON r1.ID = tm.Referee1
            LEFT JOIN Fencer r2 ON r2.ID = tm.Referee2
            LEFT JOIN Fencer ta ON ta.ID = tm.MatchTable
            WHERE tm.TournamentID = :tid
              AND (:fid IN (tm.ChallengerID, tm.ChallengedID))
            ORDER BY tm.FightDate DESC, tm.ID DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':fid' => $fencerId, ':tid' => $tournamentId]);
        $matches = $stmt->fetchAll();
    } else {
        $sql = "
            SELECT tm.ID AS MatchID,
                   tm.FightDate,
                   tm.TournamentID,
                   t.Name AS TournamentName,
                   tm.ChallengerID,
                   tm.ChallengedID,
                   c.Name AS ChallengerName,
                   d.Name AS ChallengedName,
                   j.Name AS JudgeName,
                   r1.Name AS Ref1Name,
                   r2.Name AS Ref2Name,
                   ta.Name AS TableName,
                   tm.ChallengerScore,
                   tm.ChallangedScore,
                   tm.ChallengerWarnings,
                   tm.ChallengedWarnings,
                   tm.Doubles,
                   CASE WHEN tm.ChallengerID = :fid THEN 'Challenger' ELSE 'Challenged' END AS MyRole,
                   CASE WHEN tm.ChallengerID = :fid THEN tm.ChallengerScore ELSE tm.ChallangedScore END AS MyScore,
                   CASE WHEN tm.ChallengerID = :fid THEN tm.ChallangedScore ELSE tm.ChallengerScore END AS OppScore,
                   CASE WHEN tm.ChallengerID = :fid THEN d.Name ELSE c.Name END AS OpponentName,
                   CASE WHEN tm.ChallengerID = :fid THEN d.ID ELSE c.ID END AS OpponentID,
                   CASE
                       WHEN (CASE WHEN tm.ChallengerID = :fid THEN tm.ChallengerScore ELSE tm.ChallangedScore END) >
                            (CASE WHEN tm.ChallengerID = :fid THEN tm.ChallangedScore ELSE tm.ChallengerScore END) THEN 'Win'
                       WHEN (CASE WHEN tm.ChallengerID = :fid THEN tm.ChallengerScore ELSE tm.ChallangedScore END) <
                            (CASE WHEN tm.ChallengerID = :fid THEN tm.ChallangedScore ELSE tm.ChallengerScore END) THEN 'Loss'
                       ELSE 'Draw'
                   END AS Result,
                   CASE WHEN tm.ChallengerID = :fid THEN tm.ChallengerWarnings ELSE tm.ChallengedWarnings END AS MyWarnings,
                   CASE WHEN tm.ChallengerID = :fid THEN tm.ChallengedWarnings ELSE tm.ChallengerWarnings END AS OppWarnings
            FROM TournamentMatch tm
            JOIN Tournament t ON t.ID = tm.TournamentID
            JOIN Fencer c  ON c.ID  = tm.ChallengerID
            JOIN Fencer d  ON d.ID  = tm.ChallengedID
            LEFT JOIN Fencer j  ON j.ID  = tm.Judge
            LEFT JOIN Fencer r1 ON r1.ID = tm.Referee1
            LEFT JOIN Fencer r2 ON r2.ID = tm.Referee2
            LEFT JOIN Fencer ta ON ta.ID = tm.MatchTable
            WHERE t.SeasonID = :sid
              AND (:fid IN (tm.ChallengerID, tm.ChallengedID))
            ORDER BY tm.FightDate DESC, tm.ID DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':fid' => $fencerId, ':sid' => $seasonId]);
        $matches = $stmt->fetchAll();

        $tournamentName = "All tournaments in season $seasonId";
        $backLink = "season_overview.php?season_id=" . $seasonId;
    }

    // Build rank progression data
    $rankHistory = [];

    if ($tournamentId !== null) {
        // Initial rank for this tournament
        $initStmt = $pdo->prepare("
            SELECT CASE
                        WHEN OldRank IS NULL THEN tf.Rank
                        ELSE OldRank
                   END AS Rank
                 , DATE(s.StartDate) AS StartDate
                 , t.Name AS TournamentName
              FROM TournamentFencer tf
              JOIN Tournament t ON t.ID = tf.TournamentID
              JOIN Season s ON s.ID = t.SeasonID
         LEFT JOIN (
                    SELECT rc.TournamentID
                         , rc.FencerID
                         , rc.OldRank
                      FROM RankChangeLog rc
                      JOIN (
                                SELECT FencerID
                                     , TournamentID
                                     , MIN(ChangeDateTime) AS ChangeDateTime
                                  FROM RankChangeLog
                              GROUP BY FencerID
                                     , TournamentID
                           ) fi
                        ON rc.FencerID = fi.FencerID
                       AND rc.TournamentID = fi.TournamentID
                       AND rc.ChangeDateTime = fi.ChangeDateTime
                   ) cr
                ON cr.TournamentID = tf.TournamentID
               AND cr.FencerID = tf.FencerID
             WHERE tf.TournamentID = :tid 
               AND tf.FencerID = :fid
        ");
        $initStmt->execute([':tid' => $tournamentId, ':fid' => $fencerId]);
        $initialRanks = $initStmt->fetchAll();

        $rankStmt = $pdo->prepare("
            SELECT st.NewRank
                 , st.ChangeDate
                 , st.Comment
                 , st.TournamentName
              FROM (
                    SELECT MAX(r.OldRank) AS NewRank
                         , DATE(DATE_SUB(r.ChangeDateTime, INTERVAL 1 DAY)) AS ChangeDate
                         , r.Comment
                         , t.Name AS TournamentName
                         , t.ID
                      FROM RankChangeLog r
                      JOIN Tournament t
                        ON t.ID = r.TournamentID
                     WHERE r.TournamentID = :tid
                       AND r.FencerID = :fid
                  GROUP BY DATE(DATE_SUB(r.ChangeDateTime, INTERVAL 1 DAY))
                         , r.Comment
                         , t.Name
                         , t.ID

                     UNION

                    SELECT MIN(r.NewRank) AS NewRank
                         , DATE(r.ChangeDateTime) AS ChangeDate
                         , r.Comment
                         , t.Name AS TournamentName
                         , t.ID
                      FROM RankChangeLog r
                      JOIN Tournament t
                        ON t.ID = r.TournamentID
                     WHERE r.TournamentID = :tid
                       AND r.FencerID = :fid
                  GROUP BY DATE(r.ChangeDateTime)
                         , r.Comment
                         , t.Name
                         , t.ID

                     UNION

                    SELECT Rank
                         , DATE(NOW()) AS ChangeDate
                         , 'Current rank' AS Comment
                         , t.Name AS TournamentName
                         , t.ID
                      FROM TournamentFencer r
                      JOIN Tournament t
                        ON t.ID = r.TournamentID
                     WHERE r.TournamentID = :tid
                       AND r.FencerID = :fid
                       AND r.FencerID NOT IN (SELECT FencerID FROM RankChangeLog WHERE ChangeDateTime >= CURDATE() AND TournamentID = :tid)
                   ) st
            ORDER BY st.ID
                   , st.ChangeDate
        ");
        $rankStmt->execute([':tid' => $tournamentId, ':fid' => $fencerId]);
        $rankChanges = $rankStmt->fetchAll();


        foreach ($initialRanks as $row) {
            $colorKey = strtolower(strtok($row['TournamentName'], " "));
            $rankHistory[$row['TournamentName']] = [
                'color' => $tournamentColors[$colorKey] ?? '#000000',
                'data' => [[
                    'x' => $row['StartDate'],
                    'y' => $row['Rank'],
                    'comment' => 'Starting rank'
                ]]
            ];
        }
        foreach ($rankChanges as $row) {
            $colorKey = strtolower(strtok($row['TournamentName'], " "));
            $rankHistory[$row['TournamentName']]['data'][] = [
                'x' => $row['ChangeDate'],
                'y' => $row['NewRank'],
                'comment' => $row['Comment'] ?? ''
            ];
            if (!isset($rankHistory[$row['TournamentName']]['color'])) {
                $rankHistory[$row['TournamentName']]['color'] = $tournamentColors[$colorKey] ?? '#000000';
            }
        }
    } else {
        $initStmt = $pdo->prepare("
            SELECT CASE
                        WHEN OldRank IS NULL THEN tf.Rank
                        ELSE OldRank
                   END AS Rank
                 , s.StartDate
                 , t.ID AS TournamentID
                 , t.Name AS TournamentName
              FROM TournamentFencer tf
              JOIN Tournament t ON t.ID = tf.TournamentID
              JOIN Season s ON s.ID = t.SeasonID
         LEFT JOIN (
                    SELECT rc.TournamentID
                         , rc.FencerID
                         , rc.OldRank
                      FROM RankChangeLog rc
                      JOIN (
                                SELECT FencerID
                                     , TournamentID
                                     , MIN(ChangeDateTime) AS ChangeDateTime
                                  FROM RankChangeLog
                              GROUP BY FencerID
                                     , TournamentID
                           ) fi
                        ON rc.FencerID = fi.FencerID
                       AND rc.TournamentID = fi.TournamentID
                       AND rc.ChangeDateTime = fi.ChangeDateTime
                   ) cr
                ON cr.TournamentID = tf.TournamentID
               AND cr.FencerID = tf.FencerID
             WHERE t.SeasonID = :sid
               AND tf.FencerID = :fid
        ");
        $initStmt->execute([':sid' => $seasonId, ':fid' => $fencerId]);
        $initialRanks = $initStmt->fetchAll();

        $rankStmt = $pdo->prepare("
            SELECT st.NewRank
                 , st.ChangeDate
                 , st.Comment
                 , st.TournamentName
              FROM (
                -- Creating last day of old rank
                    SELECT MAX(r.OldRank) AS NewRank
                         , DATE(DATE_SUB(r.ChangeDateTime, INTERVAL 1 DAY)) AS ChangeDate
                         , r.Comment
                         , t.Name AS TournamentName
                         , t.ID
                          FROM RankChangeLog r
                      JOIN Tournament t
                        ON t.ID = r.TournamentID
                     WHERE t.SeasonID = :sid
                       AND r.FencerID = :fid
                  GROUP BY DATE(DATE_SUB(r.ChangeDateTime, INTERVAL 1 DAY))
                         , r.Comment
                         , t.Name
                         , t.ID

                     UNION

                -- Getting list of rank changes
                    SELECT MIN(r.NewRank) AS NewRank
                         , DATE(r.ChangeDateTime) AS ChangeDate
                         , r.Comment
                         , t.Name AS TournamentName
                         , t.ID
                      FROM RankChangeLog r
                      JOIN Tournament t
                        ON t.ID = r.TournamentID
                     WHERE t.SeasonID = :sid
                       AND r.FencerID = :fid
                  GROUP BY DATE(r.ChangeDateTime)
                         , r.Comment
                         , t.Name
                         , t.ID

                     UNION

                -- Creating today datapoint
                    SELECT Rank
                         , DATE(NOW()) AS ChangeDate
                         , 'Current rank' AS Comment
                         , t.Name AS TournamentName
                         , t.ID
                      FROM TournamentFencer r
                      JOIN Tournament t
                        ON t.ID = r.TournamentID
                 LEFT JOIN (
                                SELECT FencerID
                                     , TournamentID
                                  FROM RankChangeLog 
                                 WHERE ChangeDateTime >= CURDATE()
                              GROUP BY FencerID
                                     , TournamentID
                           ) fi
                        ON r.FencerID = fi.FencerID
                       AND r.TournamentID = fi.TournamentID
                     WHERE t.SeasonID = :sid
                       AND r.FencerID = :fid
                       AND fi.TournamentID IS NULL
                   ) st
            ORDER BY st.ID
                   , st.ChangeDate
        ");
        $rankStmt->execute([':sid' => $seasonId, ':fid' => $fencerId]);
        $rankChanges = $rankStmt->fetchAll();

        foreach ($initialRanks as $row) {
            $colorKey = strtolower(strtok($row['TournamentName'], " "));
            $rankHistory[$row['TournamentName']] = [
                'color' => $tournamentColors[$colorKey] ?? '#000000',
                'data' => [[
                    'x' => $row['StartDate'],
                    'y' => $row['Rank'],
                    'comment' => 'Starting rank'
                ]]
            ];
        }
        foreach ($rankChanges as $row) {
            $colorKey = strtolower(strtok($row['TournamentName'], " "));
            $rankHistory[$row['TournamentName']]['data'][] = [
                'x' => $row['ChangeDate'],
                'y' => $row['NewRank'],
                'comment' => $row['Comment'] ?? ''
            ];
            if (!isset($rankHistory[$row['TournamentName']]['color'])) {
                $rankHistory[$row['TournamentName']]['color'] = $tournamentColors[$colorKey] ?? '#000000';
            }
        }
    }

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
    <title>Fencer Details — <?= h($tournamentName ?? '') ?></title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container">
        <h1><?= h($fencer['Name']) ?> <small class="meta">(#<?= h((string)$fencer['ID']) ?>)</small></h1>
        <p class="meta">Scope: <span class="badge"><?= h($tournamentName) ?></span></p>

        <h2>Rank Progression</h2>
        <canvas id="rankChart" class="chart-canvas"></canvas>

        <?php if (!$matches): ?>
            <div class="empty">No matches found for this fencer in the selected scope.</div>
        <?php else: ?>
            
        <h2>Match History</h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th class="mono">Date</th>
                        <th>Tournament</th>
                        <th>Role</th>
                        <th>Opponent</th>
                        <th class="mono">Score</th>
                        <th>Result</th>
                        <th class="mono">Warnings</th>
                        <th>Doubles</th>
                        <th>Officials</th>
                        <th class="mono">Match&nbsp;ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matches as $m): ?>
                        <tr>
                            <td class="mono"><?= h($m['FightDate'] ?? '') ?></td>
                            <td><?= h($m['TournamentName'] ?? '') ?></td>
                            <td><?= h($m['MyRole']) ?></td>
                            <td>
                                <a href="fencer_details.php?fencer_id=<?= h((string)$m['OpponentID']) ?>&tournament_id=<?= $tournamentId !== null ? h((string)$tournamentId) : '' ?>&season_id=<?= h((string)$seasonId) ?>">
                                    <?= h($m['OpponentName']) ?>
                                </a>
                            </td>
                            <td class="mono"><?= h((string)$m['MyScore']) ?>–<?= h((string)$m['OppScore']) ?></td>
                            <td><?= h($m['Result']) ?></td>
                            <td class="mono"><?= h((string)($m['MyWarnings'] ?? 0)) ?> / <?= h((string)($m['OppWarnings'] ?? 0)) ?></td>
                            <td><?= h((string)($m['Doubles'] ?? 0)) ?></td>
                            <td>
                                <?php
                                    $officials = array_filter([
                                        $m['JudgeName']   ? ('Ref: '     . $m['JudgeName'])  : null,
                                        $m['Ref1Name']    ? ('Judge1: '  . $m['Ref1Name'])   : null,
                                        $m['Ref2Name']    ? ('Judge2: '  . $m['Ref2Name'])   : null,
                                        $m['TableName']   ? ('Table: '   . $m['TableName'])  : null,
                                    ]);
                                    echo $officials ? h(implode('; ', $officials)) : '—';
                                ?>
                            </td>
                            <td class="mono"><?= h((string)$m['MatchID']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <?php if ($backLink): ?>
        <p style="margin-top:20px;">
            <a href="<?= h($backLink) ?>">← Back</a>
        </p>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3"></script>
    <script>
        const ctx = document.getElementById('rankChart').getContext('2d');

        const datasets = <?php
        echo json_encode(array_values(array_map(function($name, $info) {
            return [
                'label' => $name,
                'data' => $info['data'],
                'fill' => false,
                'borderColor' => $info['color'],
                'backgroundColor' => $info['color'],
                'tension' => 0
            ];
        }, array_keys($rankHistory), $rankHistory)), JSON_UNESCAPED_SLASHES);
        ?>;

        // pick aspect ratio by breakpoint
        const mqTi = window.matchMedia('(max-width: 320px)');
        const mqSm = window.matchMedia('(max-width: 480px)');
        const mqMd = window.matchMedia('(max-width: 768px)');
        function getAR() {
            if (mqTi.matches) return 0.7;   // small phones: nearly square
            if (mqSm.matches) return 1.1;   // small phones: nearly square
            if (mqMd.matches) return 1.4;   // tablets/large phones
            return 2.0;                     // desktop wide
        }

        // Pull CSS variable for text color
        //const textColor = getComputedStyle(document.body).getPropertyValue('--text-color').trim() || '#000';
        const theme = document.documentElement.getAttribute('data-theme')
        if (theme === 'light')
            textColor = '#222';
        else
            textColor = '#eee';

        new Chart(ctx, {
          type: 'line',
          data: { datasets },
          options: {
            responsive: true,
            maintainAspectRatio: true,
            aspectRatio: getAR(),
            interaction: { mode: 'nearest', axis: 'x', intersect: false },
            plugins: {
              legend: {
                labels: { 
                    color: textColor, 
                    usePointStyle: true,
                    pointStyle: 'rect' 
                 }
              },
              tooltip: {
                callbacks: {
                  label: (ctx) => {
                    const p = ctx.raw;
                    let label = `${ctx.dataset.label}: Rank ${p.y}`;
                    if (p.comment) label += ` (${p.comment})`;
                    return label;
                  }
                }
              }
            },
            scales: {
              x: { type: 'time', time: { unit: 'day' }, title: { display: true, text: 'Date', color: textColor }, ticks: { color: textColor } },
              y: { reverse: true, title: { display: true, text: 'Rank', color: textColor }, ticks: { precision: 0, color: textColor } }
            }
          }
        });
    </script>
</body>
</html>