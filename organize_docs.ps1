# Script d'organisation des fichiers .md
# v1.9.31 - Organisation automatique de la documentation

Write-Host "📂 Organisation de la documentation..." -ForegroundColor Cyan

# Déplacer les audits
$audits = @(
    "AUDIT_*.md", 
    "BILAN_*.md", 
    "RESUME_AUDIT*.md", 
    "RAPPORT_FINAL*.md",
    "SYNTHESE_*.md",
    "LISEZ_MOI_DABORD_AUDIT.md",
    "GUIDE_LECTURE_AUDIT.md",
    "INDEX_DOCUMENTATION_AUDIT.md",
    "README_AUDIT.md",
    "CLOTURE_AUDIT_UTILISATEUR.md",
    "audit-complet-plugin.plan.md"
)

foreach ($pattern in $audits) {
    Get-ChildItem -Filter $pattern -ErrorAction SilentlyContinue | Move-Item -Destination "docs/audits" -Force -ErrorAction SilentlyContinue
}

# Déplacer les bugfixes
$bugfixes = @(
    "BUGFIX_*.md",
    "BUGS_ET_*.md",
    "FIX_*.md",
    "SECURITY_FIX*.md",
    "REFONTE_LOGIQUE*.md",
    "RESOLUTION_PROBLEME*.md",
    "REVIEW_CORRECTIONS.md"
)

foreach ($pattern in $bugfixes) {
    Get-ChildItem -Filter $pattern -ErrorAction SilentlyContinue | Move-Item -Destination "docs/bugfixes" -Force -ErrorAction SilentlyContinue
}

# Déplacer les features
$features = @("FEATURE_*.md")
foreach ($pattern in $features) {
    Get-ChildItem -Filter $pattern -ErrorAction SilentlyContinue | Move-Item -Destination "docs/features" -Force -ErrorAction SilentlyContinue
}

# Déplacer les guides
$guides = @(
    "GUIDE_*.md",
    "QUICKSTART*.md",
    "TESTING_GUIDE.md",
    "USER_CONSENT_PATTERNS.md",
    "INSTRUCTIONS_PURGE_CACHE.md",
    "TEST_BULK_OPERATIONS.md"
)

foreach ($pattern in $guides) {
    Get-ChildItem -Filter $pattern -ErrorAction SilentlyContinue | Move-Item -Destination "docs/guides" -Force -ErrorAction SilentlyContinue
}

# Déplacer installation/deployment
$installation = @(
    "INSTALLATION.md",
    "DEPLOYMENT_READY.md",
    "PRE_DEPLOYMENT_CHECKLIST.md",
    "FINAL_DEPLOYMENT_REPORT.md",
    "GIT_PUSH_CONFIRMATION.md"
)

foreach ($file in $installation) {
    if (Test-Path $file) {
        Move-Item $file -Destination "docs/installation" -Force -ErrorAction SilentlyContinue
    }
}

# Déplacer documentation technique
$technical = @(
    "MOODLE_4.5_*.md",
    "DATABASE_IMPACT.md",
    "CATEGORIES_DEFINITION.md",
    "CATEGORY_PROTECTION.md",
    "CURSOR_CONFIGURATION_SUMMARY.md",
    "DEBUG_TEST_*.md",
    "DIAGNOSTIC_*.md"
)

foreach ($pattern in $technical) {
    Get-ChildItem -Filter $pattern -ErrorAction SilentlyContinue | Move-Item -Destination "docs/technical" -Force -ErrorAction SilentlyContinue
}

# Déplacer performance/optimisations
$performance = @(
    "PERFORMANCE_OPTIMIZATION.md",
    "LARGE_DATABASE_FIX.md",
    "RESOLUTION_29K_QUESTIONS.md",
    "GROS_SITES_OPTIMISATIONS*.md",
    "BROKEN_LINKS_FIX*.md",
    "TODOS_RESTANTS*.md",
    "RECOMMANDATIONS_STRATEGIQUES*.md"
)

foreach ($pattern in $performance) {
    Get-ChildItem -Filter $pattern -ErrorAction SilentlyContinue | Move-Item -Destination "docs/performance" -Force -ErrorAction SilentlyContinue
}

# Déplacer releases/versions
$releases = @(
    "RELEASE_NOTES*.md",
    "VERSION_*.md",
    "UPGRADE*.md",
    "IMPLEMENTATION_*.md"
)

foreach ($pattern in $releases) {
    Get-ChildItem -Filter $pattern -ErrorAction SilentlyContinue | Move-Item -Destination "docs/releases" -Force -ErrorAction SilentlyContinue
}

# Déplacer archives (résumés de sessions)
$archives = @(
    "RESUME_SESSION*.md",
    "RESUME_BULK*.md",
    "RESUME_CONTEXT*.md",
    "RESUME_CORRECTION*.md",
    "RESUME_FINAL*.md",
    "STATUS_PROJET*.md",
    "TRAVAIL_REALISE*.md",
    "FICHE_RESUME*.md"
)

foreach ($pattern in $archives) {
    Get-ChildItem -Filter $pattern -ErrorAction SilentlyContinue | Move-Item -Destination "docs/archives" -Force -ErrorAction SilentlyContinue
}

# Fichiers spéciaux à déplacer individuellement
if (Test-Path "README_v1.2.2.md") { Move-Item "README_v1.2.2.md" -Destination "docs/releases" -Force -ErrorAction SilentlyContinue }
if (Test-Path "PROJECT_OVERVIEW.md") { Move-Item "PROJECT_OVERVIEW.md" -Destination "docs" -Force -ErrorAction SilentlyContinue }

Write-Host "✅ Organisation terminée !" -ForegroundColor Green
Write-Host ""
Write-Host "📊 Résumé :" -ForegroundColor Yellow
Write-Host "  - Audits :        $(( Get-ChildItem docs/audits -File -ErrorAction SilentlyContinue).Count) fichiers"
Write-Host "  - Bugfixes :      $(( Get-ChildItem docs/bugfixes -File -ErrorAction SilentlyContinue).Count) fichiers"
Write-Host "  - Features :      $(( Get-ChildItem docs/features -File -ErrorAction SilentlyContinue).Count) fichiers"
Write-Host "  - Guides :        $(( Get-ChildItem docs/guides -File -ErrorAction SilentlyContinue).Count) fichiers"
Write-Host "  - Installation :  $(( Get-ChildItem docs/installation -File -ErrorAction SilentlyContinue).Count) fichiers"
Write-Host "  - Technical :     $(( Get-ChildItem docs/technical -File -ErrorAction SilentlyContinue).Count) fichiers"
Write-Host "  - Performance :   $(( Get-ChildItem docs/performance -File -ErrorAction SilentlyContinue).Count) fichiers"
Write-Host "  - Releases :      $(( Get-ChildItem docs/releases -File -ErrorAction SilentlyContinue).Count) fichiers"
Write-Host "  - Archives :      $(( Get-ChildItem docs/archives -File -ErrorAction SilentlyContinue).Count) fichiers"
Write-Host ""
Write-Host "📁 Fichiers restants à la racine : $(( Get-ChildItem -Filter "*.md" -File -ErrorAction SilentlyContinue).Count) fichiers"

