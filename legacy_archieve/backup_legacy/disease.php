<?php
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
  echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
  exit;
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

$q = isset($_GET["q"]) ? trim($_GET["q"]) : "";
$disease = $q; // keep case as typed

$pageTitle = $disease ? "Disease: {$disease}" : "Disease browser";
require __DIR__ . "/partials/header.php";

// If disease selected: show summary + variants list
if ($disease !== "") {

  // Summary row from v_literature_summary_by_disease
  $stmt = $pdo->prepare("
    SELECT disease_name, n_unique_variants, n_studies, n_genes
    FROM v_literature_summary_by_disease
    WHERE disease_name = ?
  ");
  $stmt->execute([$disease]);
  $summary = $stmt->fetch();

  // Variants for the disease from v_literature_variants_flat
  $stmt2 = $pdo->prepare("
    SELECT
      literature_variant_id,
      study_id,
      study_name_short,
      gene_symbol,
      cDNA_HGVS,
      protein_change,
      variant_type,
      is_driver,
      cell_type_name,
      evidence_type,
      paper_ref_genome,
      paper_chrom,
      paper_pos,
      paper_ref,
      paper_alt,
      lifted_ref_genome,
      lifted_chrom,
      lifted_pos,
      lifted_ref,
      lifted_alt
    FROM v_literature_variants_flat
    WHERE disease_name = ?
    ORDER BY gene_symbol, study_id, literature_variant_id
  ");
  $stmt2->execute([$disease]);
  $variants = $stmt2->fetchAll();
  ?>

  <div class="card">
    <h2>Disease search</h2>

    <form method="get" action="/disease.php" class="card">
      <label><b>Disease name</b></label><br><br>
      <input type="text" name="q" value="<?= h($disease) ?>" placeholder="e.g. Ulcerative colitis" />
      <br><br>
      <button type="submit">Search</button>
      <span class="small" style="margin-left:10px;">
        or <a href="/disease.php">browse all diseases</a>
      </span>
    </form>

    <?php if (!$summary): ?>
      <div class="card">
        <h3>No results</h3>
        <p class="small">No disease found for <b><?= h($disease) ?></b> in <code>v_literature_summary_by_disease</code>.</p>
        <p class="small">Tip: copy/paste the disease name from the browse list (exact match).</p>
      </div>
    <?php else: ?>
      <div class="card">
        <h3>Summary</h3>
        <table>
          <tr><th>Disease</th><td><?= h($summary["disease_name"]) ?></td></tr>
          <tr><th>Unique variants</th><td><?= h($summary["n_unique_variants"]) ?></td></tr>
          <tr><th>Studies</th><td><?= h($summary["n_studies"]) ?></td></tr>
          <tr><th>Genes</th><td><?= h($summary["n_genes"]) ?></td></tr>
        </table>
      </div>

      <div class="card">
        <h3>Variants (<?= count($variants) ?> rows)</h3>

        <table>
          <tr>
            <th>ID</th>
            <th>Gene</th>
            <th>Study</th>
            <th>cDNA</th>
            <th>Protein</th>
            <th>Variant type</th>
            <th>Driver label</th>
            <th>Cell type</th>
            <th>Evidence</th>
            <th>Paper coord</th>
            <th>Lifted coord</th>
          </tr>

          <?php foreach ($variants as $v): ?>
            <tr>
              <td>
                <a href="/variant.php?literature_variant_id=<?= h($v["literature_variant_id"]) ?>">
                  <?= h($v["literature_variant_id"]) ?>
                </a>
              </td>
              <td><a href="/gene.php?q=<?= h($v["gene_symbol"]) ?>"><?= h($v["gene_symbol"]) ?></a></td>
              <td><?= h($v["study_name_short"]) ?></td>
              <td><?= h($v["cDNA_HGVS"]) ?></td>
              <td><?= h($v["protein_change"]) ?></td>
              <td><?= h($v["variant_type"]) ?></td>
              <td><?= h($v["is_driver"]) ?></td>
              <td><?= h($v["cell_type_name"]) ?></td>
              <td><?= h($v["evidence_type"]) ?></td>
              <td class="small">
                <?= h($v["paper_ref_genome"]) ?><br>
                <?= h($v["paper_chrom"]) ?>:<?= h($v["paper_pos"]) ?>
                <?= h($v["paper_ref"]) ?>&gt;<?= h($v["paper_alt"]) ?>
              </td>
              <td class="small">
                <?= h($v["lifted_ref_genome"]) ?><br>
                <?= h($v["lifted_chrom"]) ?>:<?= h($v["lifted_pos"]) ?>
                <?= h($v["lifted_ref"]) ?>&gt;<?= h($v["lifted_alt"]) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <?php
  require __DIR__ . "/partials/footer.php";
  exit;
}

// Otherwise: browse all diseases
$rows = $pdo->query("
  SELECT disease_name, n_unique_variants, n_studies, n_genes
  FROM v_literature_summary_by_disease
  ORDER BY n_unique_variants DESC, disease_name ASC
")->fetchAll();
?>

<div class="card">
  <h2>Disease browser</h2>

  <form method="get" action="/disease.php" class="card">
    <label><b>Search disease</b></label><br><br>
    <input type="text" name="q" placeholder="e.g. Ulcerative colitis" />
    <br><br>
    <button type="submit">Search</button>
  </form>

  <h3>All diseases (<?= count($rows) ?>)</h3>
  <table>
    <tr>
      <th>Disease</th>
      <th>Unique variants</th>
      <th>Studies</th>
      <th>Genes</th>
    </tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="/disease.php?q=<?= h($r["disease_name"]) ?>"><?= h($r["disease_name"]) ?></a></td>
        <td><?= h($r["n_unique_variants"]) ?></td>
        <td><?= h($r["n_studies"]) ?></td>
        <td><?= h($r["n_genes"]) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php require __DIR__ . "/partials/footer.php"; ?>
