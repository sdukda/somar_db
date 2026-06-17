<?php
require __DIR__ . "/../config/bootstrap.php";
require_once __DIR__ . "/../download_helpers.php";

// --------------------
// Helpers
// --------------------
function sql_in_placeholders(array $arr): string {
  return implode(",", array_fill(0, count($arr), "?"));
}

// Helper to build clickable header links (with optional tooltip)
function diseases_sort_link(
  string $label,
  string $col,
  string $q,
  int $id,
  string $sort,
  string $dir,
  string $title = ''
): string {
  $isActive = ($sort === $col);
  $isAsc    = (strtolower($dir) === 'asc');
  $nextDir  = ($isActive && $isAsc) ? 'desc' : 'asc';

  // arrows (safe HTML) - always show ▲▼, highlight active direction
  if ($isActive) {
    $arrow = $isAsc
      ? ' <span class="sort-arrow active">&#9650;</span><span class="sort-arrow inactive">&#9660;</span>' // ▲▼
      : ' <span class="sort-arrow inactive">&#9650;</span><span class="sort-arrow active">&#9660;</span>'; // ▲▼
  } else {
    $arrow = ' <span class="sort-arrow inactive">&#9650;</span><span class="sort-arrow inactive">&#9660;</span>'; // ▲▼
  }
  $params = [
    'q'    => $q,
    'id'   => $id > 0 ? $id : null,
    'sort' => $col,
    'dir'  => $nextDir,
  ];

  $href = '/disease_v2.php?' . http_build_query(array_filter(
    $params,
    fn($v) => $v !== '' && $v !== null
  ));

  $titleAttr = ($title !== '') ? ' title="' . h($title) . '"' : '';

  // Escape label + href; keep arrow span as HTML
  
  return '<a href="' . h($href) . '"' . $titleAttr . '>' . h($label) . $arrow . '</a>';
}

// --------------------
// Inputs
// --------------------
$q  = trim((string)($_GET["q"] ?? ""));
$id = (int)($_GET["id"] ?? 0);

// ----------------------------
// Sorting (Diseases list table)
// ----------------------------
$allowedSorts = [
  'disease_name'        => 'disease_name',
  'category'            => 'category',
  'disease_ontology_id' => 'disease_ontology_id',
];

$sort = (string)($_GET['sort'] ?? 'disease_name');
$dir  = strtolower((string)($_GET['dir'] ?? 'asc'));

if (!array_key_exists($sort, $allowedSorts)) {
  $sort = 'disease_name';
}
$dirSql  = ($dir === 'desc') ? 'DESC' : 'ASC';
$orderBy = $allowedSorts[$sort] . " " . $dirSql;

// --------------------
// Load selected disease (optional)
// --------------------
$disease    = null;
$diseaseIds = [];

if ($id > 0) {
  $stmt = $pdo->prepare("
    SELECT disease_id, disease_name, category, disease_ontology_id
    FROM disease
    WHERE disease_id = ?
  ");
  $stmt->execute([$id]);
  $disease = $stmt->fetch();

  if (!$disease) {
    $id = 0;
    $disease = null;
  }
}

// --------------------
// List diseases (browse/search)
// --------------------
if ($q !== "") {
  $stmt = $pdo->prepare("
    SELECT disease_id, disease_name, category, disease_ontology_id
    FROM disease
    WHERE (disease_name LIKE ?
           OR disease_ontology_id LIKE ?)
      AND disease_ontology_id IS NOT NULL
      AND TRIM(disease_ontology_id) <> ''
    ORDER BY $orderBy
    LIMIT 500
  ");
  $like = "%" . $q . "%";
  $stmt->execute([$like, $like]);
  $diseases = $stmt->fetchAll();
} else {
  $diseases = $pdo->query("
    SELECT disease_id, disease_name, category, disease_ontology_id
    FROM disease
    WHERE disease_ontology_id IS NOT NULL
      AND TRIM(disease_ontology_id) <> ''
    ORDER BY $orderBy
    LIMIT 500
  ")->fetchAll();
}

// --------------------
// Download CSV (must happen before header)
// --------------------
if (wants_csv_download()) {
  $cols = ["disease_id", "disease_name", "category", "disease_ontology_id"];
  download_csv_and_exit($diseases, $cols, "diseases_" . date("Ymd_His"));
}

// --------------------
// Detail panel queries (only when $id > 0)
// --------------------
$cellTypeBreakdown = [];
$topGenes          = [];
$variants          = [];

if ($id > 0) {
  // Build rolled-up disease IDs: selected disease + child diseases
  $diseaseIds = [$id];

  try {
    $stmt = $pdo->prepare("
      SELECT child_disease_id
      FROM disease_rollup
      WHERE parent_disease_id = ?
    ");
    $stmt->execute([$id]);
    $childIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($childIds as $cid) {
      $diseaseIds[] = (int)$cid;
    }

    $diseaseIds = array_values(array_unique($diseaseIds));
  } catch (Throwable $e) {
    $diseaseIds = [$id];
  }

  $in = sql_in_placeholders($diseaseIds);

  // Cell-type breakdown from variants view (rolled-up)
  $stmt = $pdo->prepare("
    SELECT
      cell_type_name,
      cell_type_ontology_id,
      COUNT(DISTINCT literature_variant_id) AS n_variants,
      COUNT(DISTINCT study_id) AS n_studies
    FROM v_literature_variants_flat
    WHERE disease_id IN ($in)
    GROUP BY cell_type_name, cell_type_ontology_id
    ORDER BY n_variants DESC, cell_type_name ASC
    LIMIT 200
  ");
  $stmt->execute($diseaseIds);
  $cellTypeBreakdown = $stmt->fetchAll();

  // Fallback: curated cell types if no variants exist
  if (empty($cellTypeBreakdown)) {
    $stmt = $pdo->prepare("
      SELECT
        cell_type_name,
        cell_type_ontology_id,
        cell_type_notes
      FROM disease_celltype_map
      WHERE disease_id = ?
      ORDER BY cell_type_name
    ");
    $stmt->execute([$id]);
    $cellTypeBreakdown = $stmt->fetchAll();
  }

  // Top genes (rolled-up)
  $stmt = $pdo->prepare("
    SELECT
      gene_symbol,
      COUNT(DISTINCT literature_variant_id) AS n_variants
    FROM v_literature_variants_flat
    WHERE disease_id IN ($in)
    GROUP BY gene_symbol
    ORDER BY n_variants DESC, gene_symbol ASC
    LIMIT 50
  ");
  $stmt->execute($diseaseIds);
  $topGenes = $stmt->fetchAll();

  // Recent variants preview (rolled-up)
  $stmt = $pdo->prepare("
    SELECT
      literature_variant_id,
      study_id,
      study_name,
      gene_symbol,
      cDNA_HGVS,
      protein_change,
      variant_type,
      is_driver,
      cell_type_name,
      cell_type_ontology_id
    FROM v_literature_variants_flat
    WHERE disease_id IN ($in)
    ORDER BY literature_variant_id DESC
    LIMIT 50
  ");
  $stmt->execute($diseaseIds);
  $variants = $stmt->fetchAll();
}

// --------------------
// Render
// --------------------
$pageTitle = "Diseases";
require __DIR__ . "/partials/header.php";
?>
<div class="disease-page-wrap">
  <h2>Diseases</h2>
  <div class="section-divider"></div>

  <form class="disease-page-form" method="get" action="/disease_v2.php">
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search diseases (name or DOID)..." />
    
    <div class="form-actions">
  <button type="submit" class="btn">Search</button>
  <button type="submit" name="download" value="csv" class="btn">Download</button>
  <a class="btn" href="/disease_v2.php">Clear</a>
</div>
    
    <p class="small">
      Tip: search by <b>disease name</b> or <b>DOID</b>. Click a disease to view its variants and cell types.
    </p>

  </form>
 
  <?php if ($disease): ?>
    <div style="margin-top:16px;">
      <h3><?= h($disease["disease_name"]) ?></h3>

      <table class="disease-summary-table">
        <tr>
          <th style="width:180px;">Category</th>
          <td><?= h($disease["category"] ?? "") ?></td>
        </tr>
        <tr>
          <th>DOID</th>
          <td class="small">
            <?php if (!empty($disease["disease_ontology_id"])): ?>
              <a
                href="https://disease-ontology.org/?id=<?= urlencode($disease["disease_ontology_id"]) ?>"
                target="_blank"
                rel="noopener noreferrer"
              >
                <?= h($disease["disease_ontology_id"]) ?>
              </a>
            <?php else: ?>
              N/A
            <?php endif; ?>
          </td>
        </tr>
      </table>

      <div class="grid" style="margin-top:14px;">
        <div class="col-12">
          <div class="card">
            <h4>Cell types in this disease</h4>
<?php if (!$cellTypeBreakdown): ?>
  <div class="small">No rows yet.</div>
<?php else: ?>
  <div class="table-wrap">
    <table class="disease-celltype-table">
      <tr>
        <th>Cell type</th>
        <th>CL ID</th>
        <th>Variants</th>
        <th>Studies</th>
        <th class="small">Notes</th>
      </tr>

                  <?php foreach ($cellTypeBreakdown as $r): ?>
                    <tr>
                      <td><?= h($r["cell_type_name"] ?? "") ?></td>
                      <td class="small"><?= h($r["cell_type_ontology_id"] ?? "") ?></td>
                      <td><?= array_key_exists("n_variants", $r) ? (int)$r["n_variants"] : "—" ?></td>
                      <td><?= array_key_exists("n_studies",  $r) ? (int)$r["n_studies"]  : "—" ?></td>
                      <td class="small"><?= h($r["cell_type_notes"] ?? "") ?></td>
                    </tr>
                  <?php endforeach; ?>
                </table>
              </div>
            <?php endif; ?>

          </div>
        </div>

      </div>

<div class="disease-variants-block">
  <h4>Recent variants (preview)</h4>

  <?php if (!$variants): ?>
    <div class="small">No variants found for this disease.</div>
  <?php else: ?>

    <div class="table-wrap">
      <table class="browse-variants-table disease-variants-preview">
        <tr>
          <th>Variant ID</th>
          <th class="study-col">Study</th>
          <th>Gene</th>
          <th>cDNA</th>
          <th>Protein</th>
          <th>Type</th>
          <th>Driver</th>
          <th>Cell type</th>
          <th>CL ID</th>
        </tr>
        <?php foreach ($variants as $v): ?>
          <tr>
            <td>
              <a href="/variant_v2.php?id=<?= (int)$v["literature_variant_id"] ?>">
                <?= (int)$v["literature_variant_id"] ?>
              </a>
            </td>
            <td class="study-col" title="<?= h($v["study_name"] ?? "") ?>">
              <a href="/study_v2.php?id=<?= (int)$v["study_id"] ?>">
                <?= h($v["study_name"] ?? "") ?>
              </a>
            </td>
            <td>
              <a href="/gene_v2.php?q=<?= urlencode($v["gene_symbol"]) ?>">
                <?= h($v["gene_symbol"]) ?>
              </a>
            </td>
            <td class="small"><?= h($v["cDNA_HGVS"] ?? "") ?></td>
            <td class="small"><?= h($v["protein_change"] ?? "") ?></td>
            <td><?= h($v["variant_type"] ?? "") ?></td>
            <td><?= h($v["is_driver"] ?? "") ?></td>
            <td><?= h($v["cell_type_name"] ?? "") ?></td>
            <td class="small"><?= h($v["cell_type_ontology_id"] ?? "") ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>

  <?php endif; ?>
</div>
  
    </div> <!-- closes .disease-page-section -->
    </div> <!-- closes the disease detail .card -->
  <?php endif; ?>

<div style="margin-top:24px;">
    <h3>Browse all diseases</h3>
<div class="table-wrap">
    <table class="browse-disease-table">
      <tr>
        <th><?= diseases_sort_link('Disease', 'disease_name', $q, $id, $sort, $dir) ?></th>
        <th><?= diseases_sort_link('Category', 'category', $q, $id, $sort, $dir) ?></th>
        <th><?= diseases_sort_link('DOID', 'disease_ontology_id', $q, $id, $sort, $dir, 'Disease Ontology ID (DOID)') ?></th>
      </tr>

      <?php foreach ($diseases as $d): ?>
        <tr>
          <td>
            <a href="/disease_v2.php?id=<?= (int)$d["disease_id"] ?>">
              <?= h($d["disease_name"]) ?>
            </a>
          </td>
          <td><?= h($d["category"] ?? "") ?></td>
          <td class="small">
            <?php if (!empty($d["disease_ontology_id"])): ?>
              <a
                href="https://disease-ontology.org/?id=<?= urlencode($d["disease_ontology_id"]) ?>"
                target="_blank"
                rel="noopener noreferrer"
              >
                <?= h($d["disease_ontology_id"]) ?>
              </a>
            <?php else: ?>
              N/A
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  
</div> <!-- closes .disease-page-wrap -->
<?php require __DIR__ . "/partials/footer.php"; ?>
