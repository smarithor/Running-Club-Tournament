<?php
// tournament_overview.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (!isset($_GET['tournament_id']) || !ctype_digit($_GET['tournament_id'])) {
    http_response_code(400);
    echo "Missing or invalid 'tournament_id'.";
    exit;
}

$tournamentId = (int) $_GET['tournament_id'];

try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Fencers ordered by rank
    $stmt = $pdo->prepare("
        SELECT TournamentID, TournamentName, FencerID, FencerName, Rank, RankChange, LastFightID
        FROM vwTournamentFencer
        WHERE TournamentID = :tid
        ORDER BY Rank ASC
    ");
    $stmt->execute([':tid' => $tournamentId]);
    $rows = $stmt->fetchAll();

    // Matches
    $matchStmt = $pdo->prepare("
        SELECT m.FightDate,
               fc.Name AS ChallengerName,
               fd.Name AS ChallengedName,
               m.ChallengerScore,
               m.ChallangedScore,
               m.ChallengerWarnings,
               m.ChallengedWarnings,
               m.Doubles,
               j.Name AS JudgeName,
               r1.Name AS Ref1Name,
               r2.Name AS Ref2Name,
               ta.Name AS TableName
        FROM TournamentMatch m
        JOIN Fencer fc ON fc.ID = m.ChallengerID
        JOIN Fencer fd ON fd.ID = m.ChallengedID
        LEFT JOIN Fencer j ON j.ID = m.Judge
        LEFT JOIN Fencer r1 ON r1.ID = m.Referee1
        LEFT JOIN Fencer r2 ON r2.ID = m.Referee2
        LEFT JOIN Fencer ta ON ta.ID = m.MatchTable
        WHERE m.TournamentID = :tid
        ORDER BY m.FightDate DESC, m.ID DESC
    ");
    $matchStmt->execute([':tid' => $tournamentId]);
    $matches = $matchStmt->fetchAll();

    // Initial ranks
    $initStmt = $pdo->prepare("
        SELECT tf.FencerID
             , f.Color
             , f.Name
             , CASE
                    WHEN OldRank IS NULL THEN tf.Rank
                    ELSE OldRank
               END AS Rank
             , DATE(s.StartDate) AS StartDate
          FROM TournamentFencer tf
          JOIN Fencer f
            ON f.ID = tf.FencerID
          JOIN Tournament t
            ON t.ID = tf.TournamentID
          JOIN Season s
            ON s.ID = t.SeasonID
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
    ");
    $initStmt->execute([':tid' => $tournamentId]);
    $initialRanks = $initStmt->fetchAll();

    // Rank changes
    $rankStmt = $pdo->prepare("
        SELECT st.FencerID
             , st.Color
             , st.Name
             , st.NewRank
             , st.ChangeDate
             , st.Comment
             , st.TournamentName
          FROM (
                -- Creating last day of old rank
                SELECT r.FencerID
                     , f.Color
                     , f.Name
                     , MAX(r.OldRank) AS NewRank
                     , DATE(DATE_SUB(r.ChangeDateTime, INTERVAL 1 DAY)) AS ChangeDate
                     , r.Comment
                     , t.Name AS TournamentName
                     , t.ID
                  FROM RankChangeLog r
                  JOIN Tournament t
                    ON t.ID = r.TournamentID
                  JOIN Fencer f
                    ON f.ID = r.FencerID
                 WHERE r.TournamentID = :tid
              GROUP BY r.FencerID
                     , f.Color
                     , f.Name
                     , DATE(DATE_SUB(r.ChangeDateTime, INTERVAL 1 DAY))
                     , r.Comment
                     , t.Name
                     , t.ID

                 UNION

                -- Getting list of rank changes
                SELECT r.FencerID
                     , f.Color
                     , f.Name
                     , MIN(r.NewRank) AS NewRank
                     , DATE(r.ChangeDateTime) AS ChangeDate
                     , r.Comment
                     , t.Name AS TournamentName
                     , t.ID
                  FROM RankChangeLog r
                  JOIN Tournament t
                    ON t.ID = r.TournamentID
                  JOIN Fencer f
                    ON f.ID = r.FencerID
                 WHERE r.TournamentID = :tid
              GROUP BY r.FencerID
                     , f.Color
                     , f.Name
                     , DATE(r.ChangeDateTime)
                     , r.Comment
                     , t.Name
                     , t.ID

                 UNION

                -- Creating today datapoint
                SELECT r.FencerID
                     , f.Color
                     , f.Name
                     , r.Rank
                     , DATE(NOW()) AS ChangeDate
                     , 'Current rank' AS Comment
                     , t.Name AS TournamentName
                     , t.ID
                  FROM TournamentFencer r
                  JOIN Tournament t
                    ON t.ID = r.TournamentID
                  JOIN Fencer f
                    ON f.ID = r.FencerID
                 WHERE r.TournamentID = :tid
                   AND r.FencerID NOT IN (SELECT FencerID FROM RankChangeLog WHERE ChangeDateTime >= CURDATE() AND TournamentID = :tid)
               ) st
        ORDER BY st.ID
               , st.ChangeDate;
    ");
    $rankStmt->execute([':tid' => $tournamentId]);
    $rankChanges = $rankStmt->fetchAll();

    // Build rank history
    $rankHistory = [];

    foreach ($initialRanks as $row) {
        $fid = $row['FencerID'];
        $rankHistory[$fid] = [
            'color' => $row['Color'],
            'name' => $row['Name'],
            'data' => [[
                'x' => $row['StartDate'],
                'y' => $row['Rank'],
                'comment' => 'Starting rank'
            ]]
        ];
    }

    foreach ($rankChanges as $row) {
        $fid = $row['FencerID'];
        if (!isset($rankHistory[$fid])) {
            $rankHistory[$fid] = [
                'name' => $row['Name'],
                'color' => $row['Color'],
                'data' => []
            ];
        }
        $rankHistory[$fid]['data'][] = [
            'x' => $row['ChangeDate'],
            'y' => $row['NewRank'],
            'comment' => $row['Comment'] ?? ''
        ];
    }

    // Staffing summary
    $staffStmt = $pdo->prepare("
        SELECT f.ID,
               f.Name,
               SUM(CASE WHEN m.Judge     = f.ID THEN 1 ELSE 0 END) AS JudgeCount,
               SUM(CASE WHEN m.Referee1  = f.ID THEN 1 ELSE 0 END) + SUM(CASE WHEN m.Referee2  = f.ID THEN 1 ELSE 0 END) AS RefCount,
               SUM(CASE WHEN m.MatchTable= f.ID THEN 1 ELSE 0 END) AS TableCount,
               SUM(
                   (CASE WHEN m.Judge     = f.ID THEN 1 ELSE 0 END) +
                   (CASE WHEN m.Referee1  = f.ID THEN 1 ELSE 0 END) +
                   (CASE WHEN m.Referee2  = f.ID THEN 1 ELSE 0 END) +
                   (CASE WHEN m.MatchTable= f.ID THEN 1 ELSE 0 END)
               ) AS TotalCount
          FROM Fencer f
          LEFT JOIN TournamentMatch m
            ON m.TournamentID = :tid
           AND (f.ID IN (m.Judge, m.Referee1, m.Referee2, m.MatchTable))
         GROUP BY f.ID, f.Name
         ORDER BY TotalCount DESC, f.Name
    ");
    $staffStmt->execute([':tid' => $tournamentId]);
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
    <title>Tournament Overview</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
    .tournament-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        align-items: start;
    }
    @media (max-width: 900px) {
        .tournament-grid {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>
<body>
    <?php include __DIR__ . '/navbar.php'; ?>
    <div class="container">
        <?php $tournamentName = $rows[0]['TournamentName'] ?? ''; ?>
        <h1>Fencers — <?= $tournamentName ? h($tournamentName) : "Tournament ID " . h((string)$tournamentId) ?></h1>

        <div class="tournament-grid">
            <div>
                <h2>Fencers</h2>
                <?php if (!$rows): ?>
                    <div class="empty">No fencers found for this tournament.</div>
                <?php else: ?>
                <table class="tourtable">
                    <thead>
                        <tr>
                            <th class="rank">Rank</th>
                            <th>Fencer</th>
                            <th class="hidden">Fencer ID</th>
                            <th class="rank">Δ</th>
                            <th class="hidden">Last Fight</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="mono"><?= $r['Rank'] !== null ? h((string)$r['Rank']) : '—' ?></td>
                            <td><a href="fencer_details.php?fencer_id=<?= h((string)$r['FencerID']) ?>&tournament_id=<?= h((string)$tournamentId) ?>"><?= h($r['FencerName']) ?></a></td>
                            <td class="hidden"><?= h($r['FencerID']) ?></td>
                            <td><?= h((string)($r['RankChange'] ?? '')) ?></td>
                            <td class="hidden"><?= $r['LastFightID'] !== null ? h((string)$r['LastFightID']) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <div>
                <h2>Staffing Summary</h2>
                <?php if ($staffing): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Fencer</th>
                            <th>Big stick</th>
                            <th>Little stick</th>
                            <th>Table</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($staffing as $s): ?>
                        <tr>
                            <td><?= h($s['Name']) ?></td>
                            <td class="mono"><?= h((string)$s['JudgeCount']) ?></td>
                            <td class="mono"><?= h((string)$s['RefCount']) ?></td>
                            <td class="mono"><?= h((string)$s['TableCount']) ?></td>
                            <td class="mono"><strong><?= h((string)$s['TotalCount']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p>No staffing data available yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <h2>Rank Progression</h2>
        <canvas id="rankChart" class="chart-canvas"></canvas>

        <h2>Match Results</h2>
        
        <div class="table-wrapper">
            <?php if ($matches): ?>
            <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Challenger</th>
                <th>Score</th>
                <th>Score</th>
                <th>Challenged</th>
                <th>Warnings (C / D)</th>
                <th>Doubles</th>
                <th>Big stick</th>
                <th>Little stick 1</th>
                <th>Little stick 2</th>
                <th>Table</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($matches as $m): ?>
            <tr>
                <td><?= h($m['FightDate']) ?></td>
                <td><?= h($m['ChallengerName']) ?></td>
                <td class="mono"><?= h((string)$m['ChallengerScore']) ?></td>
                <td class="mono"><?= h((string)$m['ChallangedScore']) ?></td>
                <td><?= h($m['ChallengedName']) ?></td>
                <td class="mono"><?= h((string)$m['ChallengerWarnings']) ?> / <?= h((string)$m['ChallengedWarnings']) ?></td>
                <td class="mono"><?= h((string)$m['Doubles']) ?></td>
                <td><?= h($m['JudgeName']) ?></td>
                <td><?= h($m['Ref1Name']) ?></td>
                <td><?= h($m['Ref2Name']) ?></td>
                <td><?= h($m['TableName']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            </table>
            <?php else: ?>
            <p>No matches recorded for this tournament yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3"></script>

    <script>
        const ctx = document.getElementById('rankChart').getContext('2d');

        const datasets = <?php
        echo json_encode(array_values(array_map(function($f) {
            return [
                'label' => $f['name'],
                'data' => $f['data'],
                'fill' => false,
                'borderColor' => $f['color'] ?: sprintf('hsl(%d, 70%%, 50%%)', rand(0,360)),
                'backgroundColor' => $f['color'] ?: sprintf('hsl(%d, 70%%, 50%%)', rand(0,360)),
                'tension' => 0
            ];
        }, $rankHistory)), JSON_UNESCAPED_SLASHES);
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

        const chart = new Chart(ctx, {
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

        const controls = document.createElement('div');
        controls.style.margin = '10px 0';
        controls.style.display = 'flex';
        controls.style.gap = '10px';

        const showBtn = document.createElement('button');
        showBtn.textContent = 'Show All';
        showBtn .style.margin = '10px 0';
        showBtn.onclick = () => {
            chart.data.datasets.forEach((ds, i) => {
                chart.setDatasetVisibility(i, true);
            });
            chart.update();
        };

        const clearBtn  = document.createElement('button');
        clearBtn .textContent = 'Clear All';
        clearBtn .style.margin = '10px 0';
        clearBtn .onclick = () => {
            chart.data.datasets.forEach((ds, i) => {
                chart.setDatasetVisibility(i, false);
            });
            chart.update();
        };
        controls.appendChild(showBtn);
        controls.appendChild(clearBtn);

        document.getElementById('rankChart').parentNode.insertBefore(controls, document.getElementById('rankChart'));
    </script>

</body>
</html>
