<?php
// register_match.php
declare(strict_types=1);


$errors = [];
$notice = null;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_auth();

// Connect
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

// Helpers
function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function post_int(string $key, ?int $default=null): ?int {
    if (!isset($_POST[$key])) return $default;
    $v = trim((string)$_POST[$key]);
    return ctype_digit($v) ? (int)$v : $default;
}

// Fetch tournaments (for the dropdown)
$tournaments = $pdo->query("SELECT ID, Name FROM Tournament ORDER BY ID")->fetchAll();
$defaultTid = $tournaments[0]['ID'] ?? 1;

// Determine selected tournament (persist on change)
$selectedTournamentId = post_int('parTournamentID', (int)($_POST['parTournamentID'] ?? $_GET['t'] ?? $defaultTid));

// Fetch fencers for: all (for officials) and only those in selected tournament (for competitors)
$allFencers   = $pdo->query("SELECT ID, Name FROM Fencer ORDER BY Name")->fetchAll();

$fencersStmt = $pdo->prepare("
    SELECT f.ID, f.Name
    FROM TournamentFencer tf
    JOIN Fencer f ON f.ID = tf.FencerID
    WHERE tf.TournamentID = :tid
    ORDER BY f.Name
");
$fencersStmt->execute([':tid' => $selectedTournamentId]);
$tournamentFencers = $fencersStmt->fetchAll();

// Defaults for form fields
$today = (new DateTime('now', new DateTimeZone('Atlantic/Reykjavik')))->format('Y-m-d');

$values = [
    'parTournamentID'        => $selectedTournamentId,
    'parFightDate'           => $_POST['parFightDate']           ?? $today, // DATE (YYYY-MM-DD)
    'parChallengerID'        => post_int('parChallengerID')        ?? '',
    'parChallengedID'        => post_int('parChallengedID')        ?? '',
    'parChallengerScore'     => post_int('parChallengerScore')     ?? '0',
    'parChallangedScore'     => post_int('parChallangedScore')     ?? '0',
    'parChallengerWarnings'  => post_int('parChallengerWarnings')  ?? '0',
    'parChallengedWarnings'  => post_int('parChallengedWarnings')  ?? '0',
    'parDoubles'             => post_int('parDoubles')             ?? '0',
    'parJudge'               => post_int('parJudge')               ?? '',
    'parReferee1'            => post_int('parReferee1')            ?? '',
    'parReferee2'            => post_int('parReferee2')            ?? '',
    'parTable'               => post_int('parTable')               ?? '',
];

// Handle submit (only when pressing Save)
if (($_POST['__action'] ?? '') === 'save') {
    // Basic server-side validation
    if (!$values['parTournamentID'])         $errors[] = "Tournament is required.";
    if (!$values['parChallengerID'])         $errors[] = "Challenger is required.";
    if (!$values['parChallengedID'])         $errors[] = "Challenged is required.";
    if ($values['parChallengerID'] && $values['parChallengerID'] === $values['parChallengedID']) {
        $errors[] = "Challenger and Challenged must be different fencers.";
    }
    if (!$values['parJudge'])                $errors[] = "Judge is required.";
    if (!$values['parReferee1'])             $errors[] = "Referee 1 is required.";
    if (!$values['parReferee2'])             $errors[] = "Referee 2 is required.";
    if (!$values['parTable'])                $errors[] = "Table is required.";

    // Validate date format (YYYY-MM-DD) and sanity-check it
    $dateOK = false;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$values['parFightDate'])) {
        $dt = DateTime::createFromFormat('Y-m-d', (string)$values['parFightDate']);
        $dateOK = $dt && $dt->format('Y-m-d') === $values['parFightDate'];
    }
    if (!$dateOK) $errors[] = "Fight date must be a valid date (YYYY-MM-DD).";

    if (!$errors) {
        try {
            // CALL udpRegisterMatchResults with DATE parameter for parFightDate
            $call = $pdo->prepare("CALL udpRegisterMatchResults(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $call->execute([
                $values['parTournamentID'],
                $values['parChallengerID'],
                $values['parChallengedID'],
                $values['parFightDate'],            // <-- pass as DATE string 'YYYY-MM-DD'
                $values['parChallengerScore'],
                $values['parChallangedScore'],
                $values['parChallengerWarnings'],
                $values['parChallengedWarnings'],
                $values['parDoubles'],
                $values['parJudge'],
                $values['parReferee1'],
                $values['parReferee2'],
                $values['parTable'],
            ]);

            // Clear residual result sets from CALL (PDO MySQL quirk)
            while ($call->nextRowset()) { /* flush */ }

            $notice = "Match saved successfully.";
            // Optionally reset score fields
            $values['parChallengerScore']    = '0';
            $values['parChallangedScore']    = '0';
            $values['parChallengerWarnings'] = '0';
            $values['parChallengedWarnings'] = '0';
            $values['parDoubles']            = '0';
        } catch (PDOException $e) {
            $errors[] = "Save failed: " . $e->getMessage();
        }
    }
}

// Utility to render <option>
function options(array $rows, $selectedId): string {
    $out = '';
    foreach ($rows as $r) {
        $sel = ((string)$selectedId === (string)$r['ID']) ? ' selected' : '';
        $label = $r['Name'] ?? ('ID '.$r['ID']);
        $out .= '<option value="' . h((string)$r['ID']) . '"' . $sel . '>' . h($label) . '</option>';
    }
    return $out;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Register Match Results</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="style.css">
    <script>
    function onTournamentChange() {
        // Submit the form to refresh challenger/challenged lists for the selected tournament.
        const f = document.getElementById('matchForm');
        f.__action.value = 'change-tournament';
        f.submit();
    }
    </script>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<div class="container">
    
    <h1>Register Match Results</h1>

    <?php if ($notice): ?>
        <div class="msg ok"><?= h($notice) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="msg err">
            <strong>Please fix the following:</strong>
            <ul>
                <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form id="matchForm" method="post" action="">
        <input type="hidden" name="__action" value="save">

        <fieldset>
            <legend>Event</legend>
            <div class="row">
                <div class="col-6">
                    <label for="parTournamentID">Tournament</label>
                    <select name="parTournamentID" id="parTournamentID" onchange="onTournamentChange()" required>
                        <?php foreach ($tournaments as $t): ?>
                            <option value="<?= h((string)$t['ID']) ?>"
                                <?= (string)$values['parTournamentID'] === (string)$t['ID'] ? 'selected' : '' ?>>
                                <?= h($t['Name'] ?? ('Tournament '.$t['ID'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="help">Changing the tournament refreshes the fencer lists below.</small>
                </div>
                <div class="col-6">
                    <label for="parFightDate">Fight Date</label>
                    <input type="date" id="parFightDate" name="parFightDate" value="<?= h((string)$values['parFightDate']) ?>" required>
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Competitors</legend>
            <div class="row">
                <div class="col-6">
                    <label for="parChallengerID">Challenger</label>
                    <select name="parChallengerID" id="parChallengerID" required>
                        <option value="" disabled <?= $values['parChallengerID']===''?'selected':'';?>>(select)</option>
                        <?= options($tournamentFencers, $values['parChallengerID']) ?>
                    </select>
                </div>
                <div class="col-6">
                    <label for="parChallengedID">Challenged</label>
                    <select name="parChallengedID" id="parChallengedID" required>
                        <option value="" disabled <?= $values['parChallengedID']===''?'selected':'';?>>(select)</option>
                        <?= options($tournamentFencers, $values['parChallengedID']) ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-3">
                    <label for="parChallengerScore">Challenger Score</label>
                    <input type="number" id="parChallengerScore" name="parChallengerScore" inputmode="numeric" step="1" min="0"
                           value="<?= h((string)$values['parChallengerScore']) ?>" required>
                </div>
                <div class="col-3">
                    <label for="parChallangedScore">Challenged Score</label>
                    <input type="number" id="parChallangedScore" name="parChallangedScore" inputmode="numeric" step="1" min="0"
                           value="<?= h((string)$values['parChallangedScore']) ?>" required>
                </div>
                <div class="col-3">
                    <label for="parChallengerWarnings">Challenger Warnings</label>
                    <input type="number" id="parChallengerWarnings" name="parChallengerWarnings" inputmode="numeric" step="1" min="0"
                           value="<?= h((string)$values['parChallengerWarnings']) ?>" required>
                </div>
                <div class="col-3">
                    <label for="parChallengedWarnings">Challenged Warnings</label>
                    <input type="number" id="parChallengedWarnings" name="parChallengedWarnings" inputmode="numeric" step="1" min="0"
                           value="<?= h((string)$values['parChallengedWarnings']) ?>" required>
                </div>
            </div>
            <div class="row">
                <div class="col-3">
                    <label for="parDoubles">Doubles</label>
                    <input type="number" id="parDoubles" name="parDoubles" inputmode="numeric" step="1" min="0"
                           value="<?= h((string)$values['parDoubles']) ?>" required>
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Officials</legend>
            <div class="row">
                <div class="col-4">
                    <label for="parJudge">Judge</label>
                    <select name="parJudge" id="parJudge" required>
                        <option value="" disabled <?= $values['parJudge']===''?'selected':'';?>>(select)</option>
                        <?= options($allFencers, $values['parJudge']) ?>
                    </select>
                </div>
                <div class="col-4">
                    <label for="parReferee1">Referee 1</label>
                    <select name="parReferee1" id="parReferee1" required>
                        <option value="" disabled <?= $values['parReferee1']===''?'selected':'';?>>(select)</option>
                        <?= options($allFencers, $values['parReferee1']) ?>
                    </select>
                </div>
                <div class="col-4">
                    <label for="parReferee2">Referee 2</label>
                    <select name="parReferee2" id="parReferee2" required>
                        <option value="" disabled <?= $values['parReferee2']===''?'selected':'';?>>(select)</option>
                        <?= options($allFencers, $values['parReferee2']) ?>
                    </select>
                </div>
                <div class="col-4">
                    <label for="parTable">Table</label>
                    <select name="parTable" id="parTable" required>
                        <option value="" disabled <?= $values['parTable']===''?'selected':'';?>>(select)</option>
                        <?= options($allFencers, $values['parTable']) ?>
                    </select>
                </div>
            </div>
        </fieldset>

        <div class="actions">
            <button type="submit" class="primary">Save Match</button>
            <button type="reset">Reset</button>
        </div>
    </form>

    <script>
    function keepSaveByDefault() {
        const f = document.getElementById('matchForm');
        f.__action.value = 'save';
    }
    function onTournamentChange() {
        const f = document.getElementById('matchForm');
        f.__action.value = 'change-tournament';
        f.submit();
    }
    // Ensure we go back to 'save' after any refresh from tournament change
    document.getElementById('matchForm').addEventListener('change', keepSaveByDefault);
    </script>
</div>
</body>
</html>
