## 08_methods.md
## Somatic Mutation in Autoimmune Disease Repository (SOMAR) - Methods

## 8. Methods
## 8.1 Study Design and System Overview

Somatic Mutation in Autoimmune Disease Repository (SOMAR) was developed as a literature-curated relational database designed to catalogue and organise somatic driver variants reported in the published papers on autoimmune and inflammatory diseases. The system integrates manually curated variant data from peer-reviewed publications with structured metadata, genomic coordinate normalisation, and a web-based query interface.

The portal was implemented using:
-	MySQL (relational database backend)
-	PHP (PDO-based) web application layer
-	Structured SQL views for data presentation
-	Git-based version control and migration management

The system was designed to:
-	Preserves the original publication-reported genomic coordinates
-	Supports dual reference genome builds (GRCh37 and GRCh38)
-	Enable reproducible schema deployment
-	Provides genomic browsing functionality

## 8.2 Literature Selection and Data Curation Strategy
## 8.2.1 Study Inclusion Criteria

Publications includes:
-	Reported somatic mutations in autoimmune or inflammatory diseases
-	Provided gene-level or coordinate-level mutation information
-	Identified potential driver variants or recurrent mutations
-	Sufficient methodological detail to extract variant coordinates

## 8.2.2 Variant Extraction

From each study/published paper, the following were manually extracted:
-	Gene symbol
-	Protein change (HGVSp)
-	cDNA change (HGVSc)
-	Chromosome
-	Genomic position
-	Reference allele
-	Alternate allele
-	Reference genome build reported in the paper
-	Disease context
-	Cell type or tissue compartment
-	Evidence type (e.g., targeted sequencing, WES, WGS)
-	Driver classification (e.g., CH driver, somatic disease driver)

## 8.3 Variant Normalisation and Reference Genome Handling

A core architectural decision was to preserve:
-	Original genomic coordinates as reported in the publication, and
-	A lifted-over coordinate when applicable.

## 8.3.1 Dual Genome Build Support

Studies reported coordinates in either:
-	GRCh37 (hg19)
-	GRCh38 (hg38)

To ensure interoperability:
-	Paper-reported coordinates were stored in paper_* columns
-	Lifted-over coordinates were stored in lifted_* columns
-	Reference genome identity was preserved explicitly

A genome build toggle was implemented in the web interface to allow researchers the choice to dynamically select:
-	GRCh37 view
-	GRCh38 view

The toggle dynamically determines which coordinate representation to display and which UCSC genome database (hg19 or hg38) to link to.

## 8.3.2 Variant Representation Standardisation
Variants were standardised to:

chr:position REF>ALT

Where applicable:
-	Insertions
-	Deletions
-	Indels
-	SNVs
Variant type classification was derived from REF/ALT allele length comparisons.
HGVS protein and cDNA representations were retained if reported.

## 8.4 Database Schema Design

## 8.4.1 ERD-First Modelling Approach
The relational schema was designed using an Entity–Relationship Diagram (ERD) prior to implementation. Core entities include:
-	gene
-	variant
-	study
-	disease
-	reference_genome
-	cell_type
-	variant_annotation
-	sample_variant_call

The schema design emphasised:
-	Third normal form (3NF)
-	Elimination of redundancy
-	Explicit foreign key constraints
-	Unique indexing on biologically meaningful identifiers

## 8.4.2 Controlled Vocabulary Tables
Lookup tables were implemented for:
-	variant_type
-	reference_genome
-	disease ontology identifiers
-	evidence categories

This ensures data consistency across curated studies.

## 8.4.3 View-Based Abstraction Layer
Rather than querying raw relational tables directly in the UI, SQL views were created, including:
-	v_literature_variants_flat
-	v_literature_summary_by_variant_coords

These views:
-	Join multiple relational tables
-	Simplify query logic
-	Provide a stable interface for the PHP layer
-	Support future schema evolution without UI breakage

## 8.5 Search Logic and Query Strategy
Two distinct search modes were implemented:

## 8.5.1 Exact Gene Matching
When a query matches gene symbol formatting:
-	Exact gene symbol matching is prioritised
-	Strict filtering ensures no cross-gene contamination
-	Multi-gene string artifacts are tokenised and filtered

This prevents unrelated genes (e.g., CARD11 when searching KMT2D) from appearing.

## 8.5.2 Broad Search Mode

If no strict gene match is found, the system performs broader matching across:
-	Disease name
-	Study title
-	HGVS fields
-	Genomic coordinate string

## 8.6 Web Application Implementation
The web interface was implemented in PHP using PDO (PHP data objects) prepared statements to:
-	Prevent SQL injection
-	Ensure parameterised queries
-	Maintain security

The interface includes:
-	Gene-centric view
-	Disease-centric view
-	Study-centric view
-	Variant-level detail pages
-	Genome build toggle
-	UCSC Genome Browser linking

Dynamic UCSC links are generated based on selected genome build.

## 8.7 UCSC Genome Browser Integration

For each genomic coordinate:
-	The appropriate UCSC database (hg19 or hg38) is selected
-	Coordinate strings are URL-encoded
-	Links open in a new secure tab

## 8.8 Reproducibility and Version Control
The project is maintained under Git version control:
-	Structured SQL migrations
-	Seed files
-	Tagged releases
-	View definitions

DocumentationDatabase reconstruction can be performed by:
-	Running migrations sequentially
-	Loading seed files
-	Creating views
-	Launching PHP local development server

This ensures reproducibility across environments.

## 8.9 Ethical Considerations
All data were derived from published studies. Only aggregate variant-level information reported in peer-reviewed literature was curated.


