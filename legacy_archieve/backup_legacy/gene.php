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

$q = isset($_GET["q"]) ? trim($_GET["q"]) : "";
$gene = strtoupper($q);

$pageTitle = $gene ? "Gene: {$gene}" : "Gene browser";
require __DIR__ . "/partials/header.php";

// Helpers
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

// If user searched a gene: show gene summary + variants list
if ($gene !== "") {

  // 1) Summary (from v_literature_summary_by_gene)
  $stmt = $pdo->prepare("
    SELECT gene_symbol, n_unique_variants, n_studies, n_diseases
    FROM v_literature_summary_by_gene
    WHERE gene_symbol = ?
  ");
  $stmt->execute([$gene]);
  $summary = $stmt->fetch();

  // 2) Variants for that gene (from v_literature_variants_flat)
  $stmt2 = $pdo->prepare("
    SELECT
      literature_variant_id,
      study_id,
      study_name_short,
      disease_name,
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
    WHERE gene_symbol = ?
    ORDER BY study_id, literature_variant_id
  ");
  $stmt2->execute([$gene]);
  $variants = $stmt2->fetchAll();
  ?>

  <div class="card">
    <h2>Gene search</h2>
    <form method="get" action="/gene.php" class="card">
      <label><b>Gene symbol</b></label><br><br>
      <input type="text" name="q" value="<?= h($gene) ?>" placeholder="e.g. DNMT3A" />
      <br><br>
      <button type="submit">Search</button>
      <span class="small" style="margin-left:10px;">
        or <a href="/gene.php">browse all genes</a>
      </span>
    </form>

    <?php if (!$summary): ?>
      <div class="card">
        <h3>No results</h3>
        <p class="small">No gene found for <b><?= h($gene) ?></b> in <code>v_literature_summary_by_gene</code>.</p>
        <p class="small">Try another gene symbol (e.g. <b>DNMT3A</b>, <b>TET2</b>, <b>STAT3</b>).</p>
      </div>
    <?php else: ?>
      <div class="card">
        <h3>Summary</h3>
        <table>
          <tr><th>Gene</th><td><?= h($summary["gene_symbol"]) ?></td></tr>
          <tr><th>Unique variants</th><td><?= h($summary["n_unique_variants"]) ?></td></tr>
          <tr><th>Studies</th><td><?= h($summary["n_studies"]) ?></td></tr>
          <tr><th>Diseases</th><td><?= h($summary["n_diseases"]) ?></td></tr>
        </table>
      </div>

      <div class="card">
        <h3>Variants (<?= count($variants) ?> rows)</h3>
        <p class="small">
          Each row is a literature variant record (click ID for detail page later).
        </p>

        <table>
          <tr>
            <th>ID</th>
            <th>Study</th>
            <th>Disease</th>
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
                <!-- variant.php will be built after disease/study pages; link already prepared -->
                <a href="/variant.php?literature_variant_id=<?= h($v["literature_variant_id"]) ?>">
                  <?= h($v["literature_variant_id"]) ?>
                </a>
              </td>
              <td><?= h($v["study_name_short"]) ?></td>
              <td><?= h($v["disease_name"]) ?></td>
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

// Otherwise: browse all genes
$rows = $pdo->query("
  SELECT gene_symbol, n_unique_variants, n_studies, n_diseases
  FROM v_literature_summary_by_gene
  ORDER BY n_unique_variants DESC, gene_symbol ASC
")->fetchAll();
?>

<div class="card">
  <h2>Gene browser</h2>

  <form method="get" action="/gene.php" class="card">
    <label><b>Search gene</b></label><br><br>
    <input type="text" name="q" placeholder="e.g. DNMT3A" />
    <br><br>
    <button type="submit">Search</button>
  </form>

  <h3>All genes (<?= count($rows) ?>)</h3>
  <table>
    <tr>
      <th>Gene</th>
      <th>Unique variants</th>
      <th>Studies</th>
      <th>Diseases</th>
    </tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="/gene.php?q=<?= h($r["gene_symbol"]) ?>"><?= h($r["gene_symbol"]) ?></a></td>
        <td><?= h($r["n_unique_variants"]) ?></td>
        <td><?= h($r["n_studies"]) ?></td>
        <td><?= h($r["n_diseases"]) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <p class="small">
    Note: If you see “messy” output in terminal, this UI page is the clean way to browse.
  </p>
</div>

<?php require __DIR__ . "/partials/footer.php"; ?>
