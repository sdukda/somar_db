# SOMAR
## Somatic Mutation in Autoimmune Disease Repository
### Overview
SOMAR (Somatic Mutation in Autoimmune Disease Repository) is a curated relational database and web platform developed to collect, standardise and explore literature-derived somatic mutations reported in autoimmune and inflammatory diseases.

The repository integrates published evidence and provides a searchable interface for investigating disease-associated somatic variants across studies, genes, diseases and cell types.It also incorporates harmonised variants consequence categories, reproducible schema migrations, and seed data to support consistent database construction, curation and future expansion.

The database is intended to support research into disease-associated somatic variants in autoimmune conditions, inflammatory disorders, and related immune-mediated diseases. By linking variants with genes, cell types, diseases, studies and supporting evidence, SOMAR provides a structured resource for investigating the contribution of somatic variation to immune dysregulation and disease pathogenesis.

### Project Goals
SOMAR aims to provide a structured, queryable, and traceable framework for cataloguing somatic variants reported in published autoimmune disease literature. Each curated variant is linked to its corresponding study, disease context, cell type, and supporting evidence, enabling transparent interpretation of the source data. The project also seeks to standardise variant descriptions across publications and support user-friendly exploration by gene, disease, variant, and study through a lightweight web interface.

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
The SOMAR schema centres on somatic variants and links each curated record to relevant genes, diseases, cell types, studies, and variant consequence or impact categories. Where available, external identifiers such as Disease Ontology and cell ontology terms are included to improve consistency and interoperability.

The database design emphasises consistent relationships between records, transparent tracking of data sources, avoidance of duplicate information, and a clear distinction between values reported directly in publications and values derived during curation.

Entity-relationship diagrams are provided in:
`docs/erd/`.

**Schema Migrations**
All schema changes are tracked through ordered **ordered SQL migrations** in `sql/migrations/`.
These migrations are designed to be executed in numeric order and are written to be idempotent where possible. Changes are also documented through the repository commit history, allowing the database structure to be rebuilt from scratch in a reproducible manner.

**Seed Data and Curation**
Curated seed data is stored in `sql/seeds/' and include core lookup tables, study metadata, curated somatic variant tables, and CSV files derived from manual literature extraction.

Example curated file:
literature_driver_variants_v1.csv

These files represent manual curation from published studies and include explicit annotation of the reference genome used in the original paper, lifted coordinates where applicable, variant type and consequence, disease and cell-type context, and supporting evidence notes.

**Web Interface**
SOMAR includes a lightweight PHP interface for browsing and querying the database. The interface supports gene-level summaries, disease-centric views, sortable variant listings, and publication-level summaries. It is intentionally simple and read-only, with the primary purpose of supporting exploration, verification, and review of curated records.

**Configuration and Security**
Database credentials are not committed to the repository. A safe configuration template is provided at `ui/config/db.php`, while local credentials should be stored in `ui/config/db.local.php`, which is ignored by Git. This structure supports reproducibility while protecting sensitive local configuration details and enabling safe sharing with collaborators.

**Reproducibility**
The database can be recreated locally by creating an empty MySQL database, applying the migration files in order from `sql/migrations/`, loading seed data from `sql/seeds/`, configuring db.local.php, and running the web interface locally using Apache or the PHP built-in server. Exact deployment is intentionally left flexible because this repository focuses on schema design, curated data provenance, and reproducible database construction rather than production hosting.

**Intended Use**
This repository is intended to support academic research, supervisor review, schema inspection, reproducibility assessment, and future extension into larger somatic variant knowledgebases. It is not intended for clinical decision-making or use as a production clinical system.

### Citation
If you use SOMAR in your research, please cite:

Dukda S., Kumar M., Calcino A., Schmitz U., Field M.A.

SOMAR: Somatic Mutation in Autoimmune Disease Repository.

James Cook University, Australia.

**Author**
Sonam Dukda
James Cook University


