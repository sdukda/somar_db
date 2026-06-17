<?php
// ui/public/partials/header.php
// Expects: $pageTitle (string)
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($pageTitle ?? "Autoimmune Somatic Variants Portal") ?></title>
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="site-header">
  <div class="container header-row">
    <div class="brand">
      <img src="/assets/images/ctbmb_logo_col.jpg" alt="" class="brand-logo">
      <div>
        <div class="brand-title">SOMAR</div>
        <div class="brand-subtile">Somatic Mutation in Autoimmune Disease Repository</div>
        <div class="brand-subtitle">(Literature-derived driver variants &amp; evidence)</div>
      </div>
    </div>

  <nav class="nav">
  <a href="/">Home</a>
  <a href="/gene_v2.php">Gene</a>
  <a href="/disease_v2.php">Disease</a>
  <a href="/study_v2.php">Study</a>
  <a href="/variants_v2.php">Variants</a>
</nav>

</div>

<script>
(function () {
  const form = document.getElementById('headerSearchForm');
  const typeEl = document.getElementById('headerSearchType');
  const inputEl = document.getElementById('headerSearchInput');

  if (!form) return;

  const routes = {
    gene: "/gene_v2.php",
    disease: "/disease_v2.php",
    study: "/study_v2.php",
    variant: "/variants_v2.php"
  };

  const placeholders = {
    gene: "Search gene (e.g. DNMT3A, TET2, STAT3)",
    disease: "Search disease (e.g. Ulcerative colitis)",
    study: "Search study (e.g. PMID 23197547)",
    variant: "Search variant (e.g. chr17:7675236 C>G)"
  };

  function updateUI() {
    inputEl.placeholder = placeholders[typeEl.value];
  }

  form.addEventListener("submit", function () {
    form.action = routes[typeEl.value];
  });

  typeEl.addEventListener("change", updateUI);
  updateUI();
})();
</script>
</div>
</header>
  <main class="container">
