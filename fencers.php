<?php
// fencers.php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_auth();

$errors = [];
$notice = null;

try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo "DB error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    exit;
}

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function post_str(string $k, string $def=''): string {
    return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $def;
}
function post_int(?string $k, ?int $def=null): ?int {
    if ($k === null || !isset($_POST[$k])) return $def;
    $v = trim((string)$_POST[$k]);
    return ctype_digit($v) ? (int)$v : $def;
}

/* ---------- Handle actions ---------- */
$action = $_POST['__action'] ?? '';

if ($action === 'add_fencer') {
    $name = post_str('Name');
    $full = post_str('FullName');
    $col = post_str('Color');




    if ($name === '') $errors[] = "Short name is required.";
    if ($col === '') $errors[] = "Color is required.";
    if (!$errors) {
        $stmt = $pdo->prepare("INSERT INTO Fencer (FullName, Name, Color) VALUES (:full, :name, :col)");
        $stmt->execute([':full' => $full === '' ? null : $full, ':name' => $name, ':col' => $col]);
        $notice = "Fencer “" . h($name) . "” added.";
    }
}

if ($action === 'add_to_tournament') {
    $tid = post_int('TournamentID');
    $fid = post_int('FencerID');

    if (!$tid) $errors[] = "Tournament is required.";
    if (!$fid) $errors[] = "Fencer is required.";
    if (!$errors) {
        // Prevent duplicates
        $chk = $pdo->prepare("SELECT 1 FROM TournamentFencer WHERE TournamentID = :tid AND FencerID = :fid");
        $chk->execute([':tid' => $tid, ':fid' => $fid]);
        if ($chk->fetch()) {
            $errors[] = "That fencer is already in the selected tournament.";
        } else {
            // Insert at bottom rank.
            // If ranks exist, bottom = MAX(Rank)+1. If all ranks are NULL, bottom = COUNT(*)+1.
            $ins = $pdo->prepare("
                INSERT INTO TournamentFencer (TournamentID, FencerID, Rank)
                VALUES (
                    :tid,
                    :fid,
                    (
                        SELECT CASE
                                 WHEN MAX(Rank) IS NULL THEN COUNT(*) + 1
                                 ELSE MAX(Rank) + 1
                               END
                          FROM TournamentFencer tf
                         WHERE tf.TournamentID = :tid
                    )
                )
            ");
            $ins->execute([':tid' => $tid, ':fid' => $fid]);
            $notice = "Fencer added to tournament at bottom rank.";
        }
    }
}

if ($action === 'promote_demote') {
    $tid = post_int('TournamentID');
    $fid = post_int('FencerID');
    $comment = post_str('Comment');

    if (!$tid) $errors[] = "Tournament is required.";
    if (!$fid) $errors[] = "Fencer is required.";

    if (!$errors) {
        try {
            if (isset($_POST['promote'])) {
                $stmt = $pdo->prepare("CALL udpPromoteFencerInTournament(:tid, :fid, :cmt)");
                $stmt->execute([':tid' => $tid, ':fid' => $fid, ':cmt' => $comment]);
                $notice = "Fencer promoted successfully.";
            } elseif (isset($_POST['demote'])) {
                $stmt = $pdo->prepare("CALL udpDemoteFencerInTournament(:tid, :fid, :cmt)");
                $stmt->execute([':tid' => $tid, ':fid' => $fid, ':cmt' => $comment]);
                $notice = "Fencer demoted successfully.";
            }
        } catch (PDOException $e) {
            $errors[] = "Action failed: " . $e->getMessage();
        }
    }
}

if ($action === 'update_color') {
    $fid = post_int('FencerID');
    $color = post_str('Color');
    if (!$fid) $errors[] = "Invalid fencer ID.";
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) $errors[] = "Invalid color format.";

    if (!$errors) {
        $stmt = $pdo->prepare("UPDATE Fencer SET Color = :col WHERE ID = :fid");
        $stmt->execute([':col' => $color, ':fid' => $fid]);
        $notice = "Color updated for fencer ID $fid.";
    }
}

/* ---------- Data for UI ---------- */

// All fencers (with how many tournaments they’re in)
$fencers = $pdo->query("
    SELECT f.ID, f.Name, f.FullName, f.Color,
           COALESCE((
               SELECT COUNT(*)
                 FROM TournamentFencer tf
                WHERE tf.FencerID = f.ID
           ),0) AS InTournaments
    FROM Fencer f
    ORDER BY f.Name
")->fetchAll();

// Tournaments for dropdowns
$tournaments = $pdo->query("SELECT ID, Name FROM Tournament ORDER BY ID")->fetchAll();

// Selected tournament for the “Add to tournament” block (sticky)
$selTournamentId = post_int('TournamentID', (int)($tournaments[0]['ID'] ?? 1));

// Fencers NOT already in selected tournament (choices for adding)
$availStmt = $pdo->prepare("
    SELECT f.ID, f.Name
      FROM Fencer f
     WHERE NOT EXISTS (
               SELECT 1
                 FROM TournamentFencer tf
                WHERE tf.TournamentID = :tid
                  AND tf.FencerID = f.ID
           )
     ORDER BY f.Name
");
$availStmt->execute([':tid' => $selTournamentId]);
$availableForTournament = $availStmt->fetchAll();

// Fencers already in selected tournament (for promote/demote)
$inTournStmt = $pdo->prepare("
    SELECT f.ID, f.Name
      FROM TournamentFencer tf
      JOIN Fencer f ON f.ID = tf.FencerID
     WHERE tf.TournamentID = :tid
     ORDER BY tf.Rank ASC, f.Name
");
$inTournStmt->execute([':tid' => $selTournamentId]);
$fencersInTournament = $inTournStmt->fetchAll();

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Fencers — RC Tournament</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script>
    function changeTournament(sel){
        const form = document.getElementById('addToTournamentForm');
        form.__action.value = 'change_tournament';
        form.submit();
    }
    function changeTournament2(sel){
        const form = document.getElementById('proDemoteFencerForm');
        form.__action.value = 'change_tournament';
        form.submit();
    }
    </script>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<div class="container">

	<h1>Fencers</h1>

	<?php if ($notice): ?>
	<div class="msg ok"><?= $notice ?></div>
	<?php endif; ?>
	<?php if ($errors): ?>
	<div class="msg err">
	    <strong>Please fix the following:</strong>
	    <ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
	</div>
	<?php endif; ?>

	<div class="grid">
	    <div class="card">
	        <h2>All Fencers</h2>
	        <?php if ($fencers): ?>
	        <table>
	            <thead>
	                <tr>
	                    <th style="width:70px;">ID</th>
	                    <th>Name</th>
	                    <th>Full name</th>
	                    <th style="width:120px;">In tournaments</th>
                        <th>Color</th>
	                </tr>
	            </thead>
	            <tbody>
	            <?php foreach ($fencers as $f): ?>
	                <tr>
	                    <td><small class="mono"><?= h((string)$f['ID']) ?></small></td>
	                    <td><?= h($f['Name'] ?? '') ?></td>
	                    <td><?= h($f['FullName'] ?? '') ?></td>
	                    <td><small class="mono"><?= h((string)$f['InTournaments']) ?></small></td>
                        <td>
                            <form method="post" action="" style="margin:0;">
                                <input type="hidden" name="__action" value="update_color">
                                <input type="hidden" name="FencerID" value="<?= h((string)$f['ID']) ?>">
                                <input type="color" name="Color" value="<?= h($f['Color'] ?? '#000000') ?>" 
                                       onchange="this.form.submit()">
                            </form>
                        </td>
	                </tr>
	            <?php endforeach; ?>
	            </tbody>
	        </table>
	        <?php else: ?>
	            <p>No fencers yet.</p>
	        <?php endif; ?>
	    </div>

        <div class="card">
            <h2>Promote/Demote Fencer in Tournament</h2>
            <form id="proDemoteFencerForm" method="post" action="">
                <input type="hidden" name="__action" value="promote_demote">

                <label for="promoteTournament">Tournament</label>
                <select id="promoteTournament" name="TournamentID" onchange="changeTournament2(this)" required>
                    <?php foreach ($tournaments as $t): ?>
                        <option value="<?= h((string)$t['ID']) ?>" <?= (string)$selTournamentId === (string)$t['ID'] ? 'selected' : '' ?>>
                            <?= h($t['Name'] ?? ('Tournament '.$t['ID'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="promoteFencer" style="margin-top:10px;">Fencer</label>
                <select id="promoteFencer" name="FencerID" required>
                    <option value="" disabled selected>(select)</option>
                    <?php foreach ($fencersInTournament as $ft): ?>
                        <option value="<?= h((string)$ft['ID']) ?>"><?= h($ft['Name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="comment" style="margin-top:10px;">Comment</label>
                <input id="comment" name="Comment" type="text" placeholder="Reason for promotion/demotion">

                <div class="actions" style="margin-top:10px;">
                    <button class="primary" type="submit" name="promote">Promote</button>
                    <button class="primary" type="submit" name="demote">Demote</button>
                </div>
            </form>

	        <h2>Add New Fencer</h2>
	        <form id="addToFencerForm" method="post" action="">
	            <input type="hidden" name="__action" value="add_fencer">
	            <label for="name">Short name (display)</label>
	            <input id="name" name="Name" type="text" required placeholder="e.g., Rúnar">
	            <label for="full">Full name (optional)</label>
	            <input id="full" name="FullName" type="text" placeholder="e.g., Rúnar Jónsson">
                <label for="color">Color</label>
                <input type="color" id="color" name="Color" value="#ff0000">
	            <div class="actions">
	                <button class="primary" type="submit">Add Fencer</button>
	                <button type="reset">Reset</button>
	            </div>
	        </form>

	        <h2 style="margin-top:24px;">Add Fencer to Tournament</h2>
	        <form id="addToTournamentForm" method="post" action="">
	            <input type="hidden" name="__action" value="add_to_tournament">
	            <label for="tourn">Tournament</label>
	            <select id="tourn" name="TournamentID" onchange="changeTournament(this)" required>
	                <?php foreach ($tournaments as $t): ?>
	                    <option value="<?= h((string)$t['ID']) ?>" <?= (string)$selTournamentId === (string)$t['ID'] ? 'selected' : '' ?>>
	                        <?= h($t['Name'] ?? ('Tournament '.$t['ID'])) ?>
	                    </option>
	                <?php endforeach; ?>
	            </select>

	            <label for="fencer" style="margin-top:10px;">Fencer (not already in tournament)</label>
	            <select id="fencer" name="FencerID" required>
	                <option value="" disabled selected>(select)</option>
	                <?php foreach ($availableForTournament as $af): ?>
	                    <option value="<?= h((string)$af['ID']) ?>"><?= h($af['Name']) ?></option>
	                <?php endforeach; ?>
	            </select>

	            <div class="actions">
	                <button class="primary" type="submit">Add to Tournament</button>
	            </div>
	            <p><small class="mono">New entrant gets bottom rank automatically.</small></p>
	        </form>
	    </div>
	</div>
</div>

</body>
</html>