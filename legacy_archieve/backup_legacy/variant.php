<?php
// public/variant.php
// Purpose: Variant detail page for researchers.
// - Primary access: click a variant from gene.php / disease.php / study.php (passes ?id=...)
// - Optional "lookup" by biology (gene + protein OR gene + cDNA), then redirects to ?id=...

$config = require __DIR__ . "/../config/db.php";

$dsn = sprintf(
  "mysql:host=%s;port=%d;dbname=%s;charset=%s",
  $config["db_host"],
  $config["db_port"],
  $config["db_name"],
  $config["charset"]
);

try {
  $pdo = new PDO($dsn, $config["db_user"], $config["db_pass"], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo "<h2>DB connection failed</h2>";
  echo "<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8") . "</pre>";
  exit;
}

// ---- helpers ----
function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}

function redirect_to($url) {
  header("Location: " . $url);
  exit;
}

$errors = [];
$info = null;

// ---- Handle POST actions ----
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "";

  if ($action === "lookup_by_id") {
    $id = trim($_POST["literature_variant_id"] ?? "");
    if ($id === "" || !ctype_digit($id)) {
      $errors[] = "Please enter a valid numeric internal ID.";
    } else {
      redirect_to("variant.php?id=" . urlencode($id));
    }
  }

  if ($action === "lookup_by_biology") {
    $gene    = strtoupper(trim($_POST["gene_symbol"] ?? ""));
    $protein = trim($_POST["protein_change"] ?? "");
    $cdna    = trim($_POST["cDNA_HGVS"] ?? "");

    if ($gene === "") {
      $errors[] = "Gene symbol is required for biological lookup.";
    }

    if ($protein === "" && $cdna === "") {
      $errors[] = "Provide at least one: protein change or cDNA HGVS.";
    }

    if (!$errors) {
      // Find best matching variant_id from v_literature_variants_flat
      // Prefer protein match if provided; else use cDNA match.
      if ($protein !== "") {
        $stmt = $pdo->prepare("
          SELECT literature_variant_id
          FROM v_literature_variants_flat
          WHERE gene_symbol = :gene
            AND protein_change = :protein
          ORDER BY literature_variant_id
          LIMIT 1
        ");
        $stmt->execute([
          ":gene" => $gene,
          ":protein" => $protein,
        ]);
      } else {
        $stmt = $pdo->prepare("
          SELECT literature_variant_id
          FROM v_literature_variants_flat
          WHERE gene_symbol = :gene
            AND cDNA_HGVS = :cdna
          ORDER BY literature_variant_id
          LIMIT 1
        ");
        $stmt->execute([
          ":gene" => $gene,
          ":cdna" => $cdna,
        ]);
      }

      $row = $stmt->fetch();
      if (!$row) {
        $errors[] = "No matching variant found. Try a different protein/cDNA string (must match exactly what is stored).";
      } else {
        redirect_to("variant.php?id=" . urlencode($row["literature_variant_id"]));
      }
    }
  }
}

// ---- Fetch variant by GET id ----
$id = isset($_GET["id"]) ? trim($_GET["id"]) : "";
$variant = null;

if ($id !== "") {
  if (!ctype_digit($id)) {
    $errors[] = "Invalid id parameter. It must be numeric.";
  } else {
    // Detail from your flat view (includes study + disease + coords)
    $stmt = $pdo->prepare("
      SELECT *
      FROM v_literature_variants_flat
      WHERE literature_variant_id = :id
      LIMIT 1
    ");
    $stmt->execute([":id" => (int)$id]);
    $variant = $stmt->fetch();

    if (!$variant) {
      $errors[] = "Variant not found for literature_variant_id=" . h($id);
    }
  }
}

// Optional: evidence links (if you created v_literature_variant_evidence)
$evidence_rows = [];
if ($variant) {
  try {
    $stmt = $pdo->prepare("
      SELECT *
      FROM v_literature_variant_evidence
      WHERE literature_variant_id = :id
      ORDER BY study_id
    ");
    $stmt->execute([":id" => (int)$variant["literature_variant_id"]]);
    $evidence_rows = $stmt->fetchAll();
  } catch (Throwable $e) {
    // If view doesn't exist, silently ignore (keeps page robust)
    $evidence_rows = [];
  }
}

// ---- Page ----
$title = "Variant details";
$active = "variant";
include __DIR__ . "/partials/header.php";
?>

<h1>Variant details</h1>

<p class="muted">
  <b>Note:</b> <code>literature_variant_id</code> is an internal database identifier.
  Researchers normally arrive here by clicking a variant from the Gene / Disease / Study pages.
</p>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul>
      <?php foreach ($errors as $e): ?>
        <li><?= h($e) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="grid-2">

  <div class="card">
    <h2>Find a variant (recommended)</h2>
    <form method="post" class="form">
      <input type="hidden" name="action" value="lookup_by_biology">

      <label>Gene symbol <span class="req">*</span></label>
      <input name="gene_symbol" placeholder="e.g. DNMT3A" value="<?= h($_POST["gene_symbol"] ?? "") ?>">

      <label>Protein change (optional)</label>
      <input name="protein_change" placeholder="e.g. p.R882H" value="<?= h($_POST["protein_change"] ?? "") ?>">

      <div class="muted" style="margin: 6px 0;">— or —</div>

      <label>cDNA HGVS (optional)</label>
      <input name="cDNA_HGVS" placeholder="e.g. c.2645G>A" value="<?= h($_POST["cDNA_HGVS"] ?? "") ?>">

      <button type="submit">Search</button>
    </form>

    <p class="muted small">
      Tip: values must match what is stored in your database (case/spacing matters for protein/cDNA).
      If you want, we can later add “contains” searches and normalization.
    </p>
  </div>

  <div class="card">
    <h2>Find by internal ID (advanced)</h2>
    <form method="post" class="form">
      <input type="hidden" name="action" value="lookup_by_id">

      <label>Variant internal ID</label>
      <input name="literature_variant_id" placeholder="e.g. 123" value="<?= h($_POST["literature_variant_id"] ?? $id) ?>">

      <button type="submit">Go</button>
    </form>
  </div>

</div>

<?php if ($variant): ?>
  <div class="card" style="margin-top: 16px;">
    <h2>Summary</h2>

    <table class="table">
      <tr><th>literature_variant_id</th><td><?= h($variant["literature_variant_id"]) ?></td></tr>
      <tr><th>gene_symbol</th><td><?= h($variant["gene_symbol"]) ?></td></tr>
      <tr><th>protein_change</th><td><?= h($variant["protein_change"]) ?></td></tr>
      <tr><th>cDNA_HGVS</th><td><?= h($variant["cDNA_HGVS"]) ?></td></tr>
      <tr><th>variant_type</th><td><?= h($variant["variant_type"]) ?></td></tr>
      <tr><th>is_driver</th><td><?= h($variant["is_driver"]) ?></td></tr>
      <tr><th>disease</th><td><?= h($variant["disease_name"]) ?> (ID <?= h($variant["disease_id"]) ?>)</td></tr>
      <tr><th>cell_type_name</th><td><?= h($variant["cell_type_name"]) ?></td></tr>
      <tr><th>evidence_type</th><td><?= h($variant["evidence_type"]) ?></td></tr>
      <tr><th>study</th><td><?= h($variant["study_name"]) ?> (study_id <?= h($variant["study_id"]) ?>)</td></tr>
    </table>
  </div>

  <div class="card" style="margin-top: 16px;">
    <h2>Coordinates</h2>

    <table class="table">
      <tr>
        <th>Paper reference genome</th>
        <td><?= h($variant["paper_ref_genome"]) ?></td>
      </tr>
      <tr>
        <th>Paper coordinates</th>
        <td>
          <?= h($variant["paper_chrom"]) ?> : <?= h($variant["paper_pos"]) ?>
          <?= h($variant["paper_ref"]) ?> &gt; <?= h($variant["paper_alt"]) ?>
        </td>
      </tr>

      <tr>
        <th>Lifted reference genome</th>
        <td><?= h($variant["lifted_ref_genome"]) ?></td>
      </tr>
      <tr>
        <th>Lifted coordinates</th>
        <td>
          <?= h($variant["lifted_chrom"]) ?> : <?= h($variant["lifted_pos"]) ?>
          <?= h($variant["lifted_ref"]) ?> &gt; <?= h($variant["lifted_alt"]) ?>
        </td>
      </tr>
    </table>
  </div>

  <div class="card" style="margin-top: 16px;">
    <h2>Notes</h2>
    <p><b>notes</b></p>
    <pre class="pre"><?= h($variant["notes"] ?? "") ?></pre>

    <p><b>Remarks</b></p>
    <pre class="pre"><?= h($variant["Remarks"] ?? "") ?></pre>
  </div>

  <?php if ($evidence_rows): ?>
    <div class="card" style="margin-top: 16px;">
      <h2>Evidence links</h2>
      <table class="table">
        <tr>
          <th>study_id</th>
          <th>evidence_type</th>
          <th>notes</th>
        </tr>
        <?php foreach ($evidence_rows as $er): ?>
          <tr>
            <td><?= h($er["study_id"] ?? "") ?></td>
            <td><?= h($er["evidence_type"] ?? "") ?></td>
            <td><?= h($er["notes"] ?? "") ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . "/partials/footer.php"; ?>
