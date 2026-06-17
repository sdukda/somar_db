<?php
// ui/public/study_v2.php

require __DIR__ . "/../config/bootstrap.php";
require_once __DIR__ . "/../download_helpers.php"; // wants_csv_download(), download_csv_and_exit()

// --------------------
// Small helper URLs
// --------------------
function pubmed_url($pmid): string {
  $pmid = trim((string)$pmid);
  if ($pmid === "") return "";
  if (preg_match('/^\d+$/', $pmid)) {
    return "https://pubmed.ncbi.nlm.nih.gov/" . $pmid . "/";
  }
  return "";
}

function doi_url($doi): string {
  $doi = trim((string)$doi);
  if ($doi === "") return "";
  $doi = preg_replace('/^(doi:\s*)/i', '', $doi);
  return "https://doi.org/" . $doi;
}

// --------------------
// Inputs
// --------------------
$q  = trim((string)($_GET["q"] ?? ""));

// ----------------------------
// Sorting (browse/search table)
// ----------------------------
$allowedSorts = [
  'study_name' => 'study_name',
  'year'       => 'year',
  'pmid'       => 'pmid',
  'doi'        => 'doi',
];

$sort = (string)($_GET['sort'] ?? 'study_name');
$dir  = strtolower((string)($_GET['dir'] ?? 'asc'));

if (!isset($allowedSorts[$sort])) {
  $sort = 'study_name';
}
$dirSql = ($dir === 'desc') ? 'DESC' : 'ASC';

// final safe ORDER BY (never put raw user input here)
$orderBy = $allowedSorts[$sort] . " " . $dirSql . ", study_id ASC";

// Helper to build clickable header links (keeps q, toggles dir)
function studies_sort_link($label, $column, $q, $sort, $dir) {
  $isActive = ($sort === $column);

  // Next direction
  $nextDir = ($isActive && $dir === 'asc') ? 'desc' : 'asc';

  // Always show both arrows
  if ($isActive) {
    $arrows = ($dir === 'asc')
      ? '<span class="sort-active">▲</span><span class="sort-inactive">▼</span>'
      : '<span class="sort-inactive">▲</span><span class="sort-active">▼</span>';
  } else {
    $arrows = '<span class="sort-inactive">▲</span><span class="sort-inactive">▼</span>';
  }

  $url = '?q='.urlencode($q).'&sort='.$column.'&dir='.$nextDir;

  return '<a class="sort-link" href="'.$url.'">'.$label.' '.$arrows.'</a>';
}
$id = (int)($_GET["id"] ?? 0);

// Always initialise (prevents undefined variable warnings)
$studies        = [];
$study          = null;
$variants       = [];
$diseaseSummary = [];
$geneSummary    = [];

// --------------------
// Load one study (optional detail panel)
// --------------------
if ($id > 0) {
  $stmt = $pdo->prepare("
    SELECT study_id, study_name, year, pmid, doi, notes
    FROM study
    WHERE study_id = ?
  ");
  $stmt->execute([$id]);
  $study = $stmt->fetch();
  if (!$study) {
    $id = 0;
  }
}

// --------------------
// List studies (browse/search)
// --------------------
if ($q !== "") {
  $like = "%" . $q . "%";
  $stmt = $pdo->prepare("
    SELECT study_id, study_name, year, pmid, doi
    FROM study
    WHERE study_name LIKE ?
       OR CAST(year AS CHAR) LIKE ?
       OR pmid LIKE ?
       OR doi LIKE ?
    ORDER BY $orderBy
    LIMIT 500
  ");
  $stmt->execute([$like, $like, $like, $like]);
  $studies = $stmt->fetchAll();
} else {
  $stmt = $pdo->prepare("
    SELECT study_id, study_name, year, pmid, doi
    FROM study
    ORDER BY $orderBy
    LIMIT 500
  ");
  $stmt->execute();
  $studies = $stmt->fetchAll();
}
// --------------------
// Detail: variants + summaries for selected study
// --------------------
if ($id > 0) {

  $stmt = $pdo->prepare("
    SELECT
      literature_variant_id,
      gene_symbol,
      cDNA_HGVS,
      protein_change,
      variant_type,
      consequence,
      consequence_detail,

      CONCAT_WS('',
        COALESCE(lifted_chrom, paper_chrom), ':',
        COALESCE(lifted_pos,   paper_pos),  ' ',
        COALESCE(lifted_ref,   paper_ref),  '>',
        COALESCE(lifted_alt,   paper_alt)
      ) AS genomic_variant,

      is_driver,
      disease_id,
      disease_name,
      disease_category,
      disease_ontology_id,
      cell_type_name,
      cell_type_ontology_id,
      evidence_type,

      paper_ref_genome, paper_chrom, paper_pos, paper_ref, paper_alt,
      lifted_ref_genome, lifted_chrom, lifted_pos, lifted_ref, lifted_alt

    FROM v_literature_variants_flat
    WHERE study_id = ?
    ORDER BY literature_variant_id DESC
    LIMIT 500
  ");
  $stmt->execute([$id]);
  $variants = $stmt->fetchAll();

  $stmt = $pdo->prepare("
    SELECT
      disease_id,
      disease_name,
      disease_category,
      disease_ontology_id,
      COUNT(DISTINCT literature_variant_id) AS n_variants
    FROM v_literature_variants_flat
    WHERE study_id = ?
    GROUP BY disease_id, disease_name, disease_category, disease_ontology_id
    ORDER BY n_variants DESC, disease_name ASC
    LIMIT 100
  ");
  $stmt->execute([$id]);
  $diseaseSummary = $stmt->fetchAll();

  $stmt = $pdo->prepare("
    SELECT
      gene_symbol,
      COUNT(DISTINCT literature_variant_id) AS n_variants
    FROM v_literature_variants_flat
    WHERE study_id = ?
    GROUP BY gene_symbol
    ORDER BY n_variants DESC, gene_symbol ASC
    LIMIT 100
  ");
  $stmt->execute([$id]);
  $geneSummary = $stmt->fetchAll();
}

// --------------------
// Download (CSV) - MUST happen before header.php outputs HTML
// --------------------
if (wants_csv_download()) {

  // A) If a study is selected: download its variants
  if ($id > 0) {
    $cols = [
      "literature_variant_id",
      "gene_symbol",
      "cDNA_HGVS",
      "protein_change",
      "variant_type",
      "consequence",
      "consequence_detail",
      "genomic_variant",
      "is_driver",
      "disease_name",
      "disease_category",
      "disease_ontology_id",
      "cell_type_name",
      "cell_type_ontology_id",
      "evidence_type",
      "paper_ref_genome", "paper_chrom", "paper_pos", "paper_ref", "paper_alt",
      "lifted_ref_genome", "lifted_chrom", "lifted_pos", "lifted_ref", "lifted_alt"
    ];

    download_csv_and_exit($variants, $cols, "study_" . $id . "_" . date("Ymd_His"));
  }

  // B) Otherwise: download the study list (current browse/search)
  $cols = ["study_id", "study_name", "year", "pmid", "doi"];
  download_csv_and_exit($studies, $cols, "studies_" . date("Ymd_His"));
}

// --------------------
// Now safe to render HTML
// --------------------
$pageTitle = "Study";
require __DIR__ . "/partials/header.php";
?>
<!-- Study wrap start -->
<div class="study-page-wrap">

  <h2>Studies</h2>
  <div class="section-divider"></div>

  <form method="get" action="/study_v2.php" class="study-page-form">
    <input id="q" name="q" type="text" value="<?= h($q) ?>"
           placeholder="Search studies (title, PMID, year, DOI)..." />

    <div class="form-actions">
      <button type="submit" class="btn">Search</button>
      <button type="submit" name="download" value="csv" class="btn">Download</button>
      <a class="btn" href="/study_v2.php">Clear</a>
    </div>
  </form>

  <p class="small">
    Tip: browse and search published <b>studies</b>, <b>PMID</b> and <b>DOI</b> reporting somatic variants in autoimmune disease.
  </p>

  <?php if ($study): ?>
    <div class="study-page-section" style="margin-top:16px;">
      <h3><?= h($study["study_name"]) ?></h3>

      <?php
        $pmidLink = pubmed_url($study["pmid"] ?? "");
        $doiLink  = doi_url($study["doi"] ?? "");
      ?>

      <table class="browse-studies-table">
        <tr><th style="width:180px;">Year</th><td><?= h($study["year"] ?? "") ?></td></tr>
        <tr>
          <th>PMID</th>
          <td>
            <?php if ($pmidLink): ?>
              <a target="_blank" rel="noopener" href="<?= h($pmidLink) ?>"><?= h($study["pmid"]) ?></a>
            <?php else: ?>
              <?= h($study["pmid"] ?? "") ?>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <th>DOI</th>
          <td>
            <?php if (!empty($study["doi"])): ?>
              <a target="_blank" rel="noopener" href="<?= h($doiLink) ?>"><?= h($study["doi"]) ?></a>
            <?php else: ?>
              <?= h($study["doi"] ?? "") ?>
            <?php endif; ?>
          </td>
        </tr>
      </table>

      <div class="grid" style="margin-top:14px;">

        <div class="panel">
          <div class="card">
            <h4>Diseases in this study</h4>
            <?php if (!$diseaseSummary): ?>
              <div class="small">No linked variants found for this study.</div>
            <?php else: ?>
<div class="table-wrap">
<table class="study-mini-table study-disease-table">
                <tr>
                  <th>Disease</th>
                  <th>Category</th>
                  <th class="small">DOID</th>
                  <th>Variants</th>
                </tr>
                <?php foreach ($diseaseSummary as $d): ?>
                  <tr>
                    <td title="<?= h($d["disease_name"]) ?>">
  <a href="/disease_v2.php?id=<?= (int)$d["disease_id"] ?>">
    <?= h($d["disease_name"]) ?>
  </a>
</td>
                    <td><?= h($d["disease_category"] ?? "") ?></td>
                    <td class="small"><?= h($d["disease_ontology_id"] ?? "") ?></td>
                    <td><?= (int)$d["n_variants"] ?></td>
                  </tr>
                <?php endforeach; ?>
              </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

          </div>
        </div>

      </div>

      <div class="panel" style="margin-top:14px;">
        <h4>Variants in this study</h4>
        <?php if (!$variants): ?>
          <div class="small">No variants found.</div>
        <?php else: ?>
          <div class="table-wrap">
            <div class="table-wrap">
            <table class="browse-variants-table study-variants-table">
              <tr>
                <th>Variant ID</th>
                <th>Gene</th>
                <th>cDNA</th>
                <th>Protein</th>
                <th>Type</th>
                <th>Consequence</th>
                <th>Driver</th>
                <th>Disease</th>
                <th>Cell type</th>
              </tr>
              <?php foreach ($variants as $v): ?>
                <tr>
                  <td><a href="/variant_v2.php?id=<?= (int)$v["literature_variant_id"] ?>"><?= (int)$v["literature_variant_id"] ?></a></td>
                  <td><a href="/gene_v2.php?q=<?= urlencode($v["gene_symbol"]) ?>"><?= h($v["gene_symbol"]) ?></a></td>
                  <td class="small"><?= h($v["cDNA_HGVS"] ?? "") ?></td>
                  <td class="small"><?= h($v["protein_change"] ?? "") ?></td>
                  <td><?= h($v["variant_type"] ?? "") ?></td>
                  <td><?= h($v["consequence"] ?? "") ?></td>
                  <td><?= h($v["is_driver"] ?? "") ?></td>
                  <td title="<?= h($v["disease_name"] ?? "") ?>">
  <a href="/disease_v2.php?id=<?= (int)$v["disease_id"] ?>">
    <?= h($v["disease_name"] ?? "") ?>
  </a>
</td>
                  <td><?= h($v["cell_type_name"] ?? "") ?></td>
                </tr>
              <?php endforeach; ?>
            </table>
          </div>
        <?php endif; ?>
      </div>

    </div>
  <?php endif; ?>

  <div style="margin-top:24px;">
    <h3>Browse all studies</h3>
    <div class="table-wrap">
    <table class="browse-studies-table">
      <tr>
        <th><?= studies_sort_link('Study', 'study_name', $q, $sort, $dir) ?></th>
        <th class="col-year"><?= studies_sort_link('Year', 'year', $q, $sort, $dir) ?></th>
        <th class="col-pmid"><?= studies_sort_link('PMID', 'pmid', $q, $sort, $dir) ?></th>
        <th><?= studies_sort_link('DOI', 'doi', $q, $sort, $dir) ?></th>
      </tr>

      <?php foreach ($studies as $s): ?>
        <?php
          $pmidLink = pubmed_url($s["pmid"] ?? "");
          $doiLink  = doi_url($s["doi"] ?? "");
        ?>
        <tr>
          <td>
            <a href="/study_v2.php?id=<?= (int)$s["study_id"] ?>">
              <?= h($s["study_name"]) ?>
            </a>
          </td>

          <td class="col-year"><?= h($s["year"] ?? "") ?></td>

          <td class="col-pmid">
            <?php if ($pmidLink): ?>
              <a target="_blank" rel="noopener" href="<?= h($pmidLink) ?>"><?= h($s["pmid"]) ?></a>
            <?php else: ?>
              <?= h($s["pmid"] ?? "") ?>
            <?php endif; ?>
          </td>

          <td class="small">
            <?php if (!empty($s["doi"])): ?>
              <a target="_blank" rel="noopener" href="<?= h($doiLink) ?>"><?= h($s["doi"]) ?></a>
            <?php else: ?>
              <?= h($s["doi"] ?? "") ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

</div>
<!-- Study wrap end -->
<?php require __DIR__ . "/partials/footer.php"; ?>
