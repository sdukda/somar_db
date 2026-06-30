## 07_system_architecture.md

## 1 System Architecture

## 1.1 Overview

SOMAR is implemented as a layered research software architecture that separates data acquisition, curation, storage, and presentation components. Somatic variant information is first derived from peer-reviewed publications and associated supplementary data, including reported genomic coordinates, reference genome builds, and driver interpretations. These data undergo manual curation and normalization, including HGVS validation, reference genome verification, disease and cell ontology mapping (DOID and CL), driver classification, and coordinate liftover between GRCh37 and GRCh38 where required.

Following curation, a validation and quality control stage ensures deduplication, foreign key resolution, referential integrity checks, and schema consistency prior to structured ingestion. Validated data are stored as curated staging files (CSV) and subsequently imported into the core relational database (autoimmune_db, MySQL). The database comprises normalized base tables and analytical SQL views. Importantly, the PHP web application queries only analytical views rather than base tables, ensuring logical abstraction and stable public-facing access.

Versioning and reproducibility are maintained through Git-based source control, SQL migration files, curated seed data, and tagged releases, allowing the database to be reconstructed from scratch. External knowledge integration is achieved via linkage to the UCSC Genome Browser, PubMed, and ontology resources (DOID and Cell Ontology), supporting interoperability and contextual validation.

## 1.2 Layered Architectural Model

![Architectural Model](./images/Autoimmune_Portal_Architecture_v0.3.png)

The system follows a five-layer architecture model:

**a. Data Source Layer**
This layer comprises primary scientific inputs:

-	Peer-reviewed publications
-	Supplementary variant tables
-	Reported genomic coordinates
-	Declared reference genome builds
-	Author-reported driver interpretations

This layer represents the authoritative source of curated knowledge.

**b. Curation & Processing Layer**
This layer transforms raw literature data into structured, validated datasets. It includes:

-	HGVS normalization and validation
-	Reference genome verification (GRCh37 / GRCh38)
-	Coordinate liftover between builds
-	Driver classification (CH driver vs disease driver)
-	Disease ontology mapping (DOID)
-	Cell ontology mapping (CL)

A dedicated validation and quality control stage enforces:

-	Deduplication
-	Referential integrity checks
-	Foreign key resolution
-	Natural key hashing
-	Schema compliance

This layer ensures scientific and structural correctness prior to database ingestion.

**c. Persistence Layer (Relational Database Layer)**
The core relational database (autoimmune_db, MySQL) contains:

-	Normalized base tables (genes, variants, disease, study, reference_genome, cell_type, literature_driver_variants)
-	Analytical SQL views (e.g., v_literature_variants_flat, v_literature_summary_by_gene)

The database design follows normalization principles to avoid redundancy while supporting analytical querying.

**d. Application Layer**
The PHP-based web application provides:

-	Gene-, disease-, study-, and coordinate-centric views
-	Sorting and filtering functionality
-	CSV export capabilities
-	UCSC Genome Browser integration

Importantly, the application queries analytical SQL views rather than base tables, ensuring logical decoupling between schema design and presentation logic.

**e. Reproducibility & Versioning Layer**
Reproducibility is enforced through:

-	Git-based source control
-	SQL migration files
-	Curated seed data
-	Tagged releases (e.g., v0.3-summary-enhancement)
-	Schema evolution tracking

This layer ensures the database can be reconstructed deterministically from source.

