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

$q = isset($_GET["study_id"]) ? trim($_GET["study_id"]) : "";
$studyId = ($q !== "" && ctype_digit($q)) ? (int)$q : null;

$pageTitle = $studyId ? "Study #{$studyId}" : "Study browser";
require __DIR__ . "/partials/header.php";

// If a study_id is provided: show summary + variants list
if ($studyId !== null) {

  // Summary row
  $stmt = $pdo->prepare("
    SELECT study_id, study_name, n_unique_variants, n_evidence_links, n_genes, n_diseases
    FROM v_literature_summary_by_study
    WHERE study_id = ?
  ");
  $stmt->execute([$studyId]);
  $summary = $stmt->fetch();

  // Variant rows for this study
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
      disease_name,
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
    WHERE study_id = ?
    ORDER BY gene_symbol, literature_variant_id
  ");
  $stmt2->execute([$studyId]);
  $variants = $stmt2->fetchAll();
  ?>

  <div class="card">
    <h2>Study lookup</h2>

    <form method="get" action="/study.php" class="card">
      <label><b>Study ID</b></label><br><br>
      <input type="text" name="study_id" value="<?= h($studyId) ?>" placeholder="e.g. 13" />
      <br><br>
      <button type="submit">Search</button>
      <span class="small" style="margin-left:10px;">
        or <a href="/study.php">browse all studies</a>
      </span>
    </form>

    <?php if (!$summary): ?>
      <div class="card">
        <h3>No results</h3>
        <p class="small">No study found for <b><?= h($studyId) ?></b> in <code>v_literature_summary_by_study</code>.</p>
      </div>
    <?php else: ?>
      <div class="card">
        <h3>Summary</h3>
        <table>
          <tr><th>Study ID</th><td><?= h($summary["study_id"]) ?></td></tr>
          <tr><th>Study name</th><td><?= h($summary["study_name"]) ?></td></tr>
          <tr><th>Unique variants</th><td><?= h($summary["n_unique_variants"]) ?></td></tr>
          <tr><th>Evidence links</th><td><?= h($summary["n_evidence_links"]) ?></td></tr>
          <tr><th>Genes</th><td><?= h($summary["n_genes"]) ?></td></tr>
          <tr><th>Diseases</th><td><?= h($summary["n_diseases"]) ?></td></tr>
        </table>
      </div>

      <div class="card">
        <h3>Variants (<?= count($variants) ?> rows)</h3>

        <table>
          <tr>
            <th>ID</th>
            <th>Gene</th>
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
                <a href="/variant.php?literature_variant_id=<?= h($v["literature_variant_id"]) ?>">
                  <?= h($v["literature_variant_id"]) ?>
                </a>
              </td>
              <td><a href="/gene.php?q=<?= h($v["gene_symbol"]) ?>"><?= h($v["gene_symbol"]) ?></a></td>
              <td><a href="/disease.php?q=<?= h($v["disease_name"]) ?>"><?= h($v["disease_name"]) ?></a></td>
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

// Otherwise: browse all studies
$rows = $pdo->query("
  SELECT study_id, study_name, n_unique_variants, n_evidence_links, n_genes, n_diseases
  FROM v_literature_summary_by_study
  ORDER BY study_id
")->fetchAll();
?>

<div class="card">
  <h2>Study browser</h2>

  <form method="get" action="/study.php" class="card">
    <label><b>Study ID</b></label><br><br>
    <input type="text" name="study_id" placeholder="e.g. 13" />
    <br><br>
    <button type="submit">Search</button>
  </form>

  <h3>All studies (<?= count($rows) ?>)</h3>
  <table>
    <tr>
      <th>Study ID</th>
      <th>Study</th>
      <th>Unique variants</th>
      <th>Evidence links</th>
      <th>Genes</th>
      <th>Diseases</th>
    </tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="/study.php?study_id=<?= h($r["study_id"]) ?>"><?= h($r["study_id"]) ?></a></td>
        <td><?= h($r["study_name"]) ?></td>
        <td><?= h($r["n_unique_variants"]) ?></td>
        <td><?= h($r["n_evidence_links"]) ?></td>
        <td><?= h($r["n_genes"]) ?></td>
        <td><?= h($r["n_diseases"]) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</div>

<?php require __DIR__ . "/partials/footer.php"; ?>
