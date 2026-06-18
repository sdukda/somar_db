# SOMAR
## Somatic Mutation in Autoimmune Disease Repository
### Overview
SOMAR (Somatic Mutation in Autoimmune Disease Repository) is a curated relational database and web platform developed to collect, standardise and explore literature-derived somatic mutations reported in autoimmune and inflammatory diseases.

The repository integrates published evidence and provides a searchable interface for investigating disease-associated somatic variants across studies, genes, diseases and cell types.


The project combines:
-	Published literature evidence
-	Manually curated somatic variants
-	Disease, gene, cell-type, and study context
-	Harmonised variant consequence categories
-	Reproducible schema migrations and seed data

The database is intended to support research into disease-associated somatic variants in autoimmune conditions, inflammatory disorders, and related immune-mediated diseases.By linking variants with genes, cell types, diseases, studies and supporting evidence, SOMAR provides a structured resource for investigating the contribution of somatic variation to immune dysregulation and disease pathogenesis.

### Project Goals
The primary objectives of SOMAR are:
-	To provide a** structured, queryable schema** for somatic variants reported in published autoimmune disease literature
-	To ensure each variant is traceable to its study, disease, cell type and supporting evidence.
-	To standardize variant descriptions reported across publications
-	To facilitate exploration by gene, disease, variant and study via a lightweight web interface.

### Repository Structure
```
autoimmune_db/
├── sql/
│   ├── migrations/        # Versioned schema and view definitions
│   ├── seeds/             # Seed data and curated CSV imports
│   └── migrations_archive/# Historical / deprecated migrations
│
├── ui/
│   ├── config/            # DB bootstrap and config templates
│   ├── public/            # PHP web interface (browse/search)
│   └── download_helpers.php
│
├── docs/
│   ├── erd/               # Entity-relationship diagrams (PDF)
│   └── schema/            # Schema documentation
│
├── scripts/               # Utility scripts (e.g. variant parsing)
├── .gitignore
└── README.md
```
**Database Design** 
**Core entities**
The schema centres on somatic variants, linked to:
-	Genes
-	Diseases (with Disease Ontology identifiers where available)
-	Cell types (with ontology identifiers where available)
-	Studies (PMID, DOI, year)
-	Variant consequences / impact categories

The design highlights:
-	Consistent links between related records
-	Transparent tracking of data sources
-	Avoidance of duplicate data
-	Clear distinction between reported and derived values

Entity-relationship diagrams are provided in:
docs/erd/

**Schema Migrations**
All schema changes are tracked via **ordered SQL migrations** in:
sql/migrations/
Each migration is:
-	Idempotent where possible
-	Executed in numeric order
-	Documented through commit history
This allows the database to be rebuilt from scratch in a reproducible manner.

**Seed Data and Curation**
Curated seed data is stored in:
sql/seeds/
This includes:
-	Core lookup tables (genes, diseases, cell types)
-	Study metadata
-	Curated somatic variant tables
-	CSV files derived from manual literature extraction

Example curated file:
literature_driver_variants_v1.csv

These files represent **manual curation from published studies**, with explicit annotation of:
-	Reference genome used in the paper
-	Lifted coordinates where applicable
-	Variant type and consequence
-	Disease and cell-type context
-	Evidence notes

**Web Interface**
A lightweight PHP interface is provided to browse and query the database:
-	Genes – variant, disease, and study counts per gene
-	Diseases – disease-centric summaries and variant context
-	Variants – sortable, filterable variant listings
-	Studies – publication-level summaries

The UI is intentionally simple and read-only, designed for:
-	Exploration
-	Verification

**Configuration and Security**
Database credentials are never committed.
-	ui/config/db.php
    → Safe template committed to GitHub
-	ui/config/db.local.php
    → Local file containing real credentials (ignored by Git)

This ensures:
-	Reproducibility
-	Security
-	Safe sharing with collaborators

**Reproducibility**
To recreate the database locally:
-	Create an empty MySQL database
-	Apply migrations in order from sql/migrations/
-	Load seed data from sql/seeds/
-	Configure db.local.php
-	Run the web UI locally (e.g. via Apache or PHP built-in server)
Exact deployment is intentionally left flexible, as this repository focuses on schema and data provenance, not production hosting.

**Intended Use**
This repository is intended for:
-	Academic research
-	Supervisor review
-	Schema inspection
-	Reproducibility assessment
-	Future extension into larger variant knowledgebases
It is not intended as a production clinical system.

### Citation
If you use SOMAR in your research, please cite:

Dukda S., Kumar M., Calcino A., Schmitz U., Field M.A.

SOMAR: Somatic Mutation in Autoimmune Disease Repository.

James Cook University, Australia.

**Author**
Sonam Dukda
James Cook University


