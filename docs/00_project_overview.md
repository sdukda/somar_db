# somar_db — project overview

This repository contains:
- A MySQL schema for a curated autoimmune somatic driver variant knowledgebase
- SQL migrations (schema + views)
- Seed + staging loaders used to populate the database
- A PHP web UI for browsing Genes / Diseases / Studies / Variants
- Documentation and ERD PDFs

## Key curated dataset
The primary curated input table is:
- `sql/seeds/literature_driver_variants_v1.csv`

It stores (per literature report row):
- paper-reported coordinates (chrom/pos/ref/alt + reference genome)
- lifted-over coordinates (chrom/pos/ref/alt + reference genome)
- gene, cDNA/protein changes
- disease + cell type context
- driver label + evidence/notes

## Where to look
- Schema/migrations: `sql/migrations/`
- Seeds/loaders: `sql/seeds/`
- UI: `ui/public/`
- ERDs: `docs/erd/`
