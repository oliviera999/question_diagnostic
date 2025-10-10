/**
 * Moodle Question Bank Diagnostic Tool - JavaScript pour page Questions
 * v1.7.2
 */

(function() {
    'use strict';

    // État global
    const state = {
        currentSort: { column: null, direction: 'asc' }
    };

    // Initialisation au chargement du DOM
    document.addEventListener('DOMContentLoaded', function() {
        initializeFilters();
        initializeSorting();
    });

    /**
     * Initialise les filtres
     */
    function initializeFilters() {
        const searchInput = document.getElementById('filter-search-questions');
        const typeFilter = document.getElementById('filter-type-questions');
        const usageFilter = document.getElementById('filter-usage-questions');
        const duplicatesFilter = document.getElementById('filter-duplicates-questions');

        if (searchInput) {
            searchInput.addEventListener('input', debounce(applyFilters, 300));
        }

        if (typeFilter) {
            typeFilter.addEventListener('change', applyFilters);
        }

        if (usageFilter) {
            usageFilter.addEventListener('change', applyFilters);
        }

        if (duplicatesFilter) {
            duplicatesFilter.addEventListener('change', applyFilters);
        }
    }

    /**
     * Applique les filtres sur le tableau
     */
    function applyFilters() {
        const searchTerm = document.getElementById('filter-search-questions')?.value.toLowerCase() || '';
        const typeFilter = document.getElementById('filter-type-questions')?.value || 'all';
        const usageFilter = document.getElementById('filter-usage-questions')?.value || 'all';
        const duplicatesFilter = document.getElementById('filter-duplicates-questions')?.value || 'all';

        const rows = document.querySelectorAll('#questions-table tbody tr');

        rows.forEach(row => {
            let visible = true;

            // Filtre de recherche
            if (searchTerm) {
                const name = row.getAttribute('data-name')?.toLowerCase() || '';
                const id = row.getAttribute('data-id')?.toLowerCase() || '';
                const course = row.getAttribute('data-course')?.toLowerCase() || '';
                const module = row.getAttribute('data-module')?.toLowerCase() || '';
                const excerpt = row.getAttribute('data-excerpt')?.toLowerCase() || '';
                
                if (!name.includes(searchTerm) && 
                    !id.includes(searchTerm) && 
                    !course.includes(searchTerm) &&
                    !module.includes(searchTerm) &&
                    !excerpt.includes(searchTerm)) {
                    visible = false;
                }
            }

            // Filtre par type
            if (typeFilter !== 'all' && visible) {
                const qtype = row.getAttribute('data-type') || '';
                if (qtype !== typeFilter) {
                    visible = false;
                }
            }

            // Filtre par usage
            if (usageFilter !== 'all' && visible) {
                const isUsed = row.getAttribute('data-used') === '1';
                if (usageFilter === 'used' && !isUsed) {
                    visible = false;
                } else if (usageFilter === 'unused' && isUsed) {
                    visible = false;
                }
            }

            // Filtre par doublons
            if (duplicatesFilter !== 'all' && visible) {
                const isDuplicate = row.getAttribute('data-is-duplicate') === '1';
                if (duplicatesFilter === 'yes' && !isDuplicate) {
                    visible = false;
                } else if (duplicatesFilter === 'no' && isDuplicate) {
                    visible = false;
                }
            }

            row.style.display = visible ? '' : 'none';
        });

        updateFilterStats();
    }

    /**
     * Met à jour les statistiques de filtrage
     */
    function updateFilterStats() {
        const rows = document.querySelectorAll('#questions-table tbody tr');
        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
        
        const statsDiv = document.getElementById('filter-stats-questions');
        if (statsDiv) {
            statsDiv.textContent = visibleRows.length + ' question(s) affichée(s) sur ' + rows.length;
        }
    }

    /**
     * Initialise le tri des colonnes
     */
    function initializeSorting() {
        const headers = document.querySelectorAll('#questions-table th.sortable');
        
        headers.forEach(header => {
            header.addEventListener('click', function() {
                const column = this.dataset.column;
                sortTable(column);
            });
        });
    }

    /**
     * Trie le tableau par colonne
     */
    function sortTable(column) {
        const tbody = document.querySelector('#questions-table tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const header = document.querySelector(`#questions-table th[data-column="${column}"]`);
        
        // Déterminer la direction du tri
        let direction = 'asc';
        if (state.currentSort.column === column) {
            direction = state.currentSort.direction === 'asc' ? 'desc' : 'asc';
        }
        
        state.currentSort = { column, direction };

        // Mettre à jour les classes CSS des en-têtes
        document.querySelectorAll('#questions-table th.sortable').forEach(h => {
            h.classList.remove('sorted-asc', 'sorted-desc');
        });
        header.classList.add(direction === 'asc' ? 'sorted-asc' : 'sorted-desc');

        // Trier les lignes
        rows.sort((a, b) => {
            let aVal = a.getAttribute('data-' + column) || '';
            let bVal = b.getAttribute('data-' + column) || '';

            // Convertir en nombre si possible
            if (!isNaN(aVal) && !isNaN(bVal) && aVal !== '' && bVal !== '') {
                aVal = parseFloat(aVal);
                bVal = parseFloat(bVal);
            } else {
                aVal = aVal.toString().toLowerCase();
                bVal = bVal.toString().toLowerCase();
            }

            if (aVal < bVal) return direction === 'asc' ? -1 : 1;
            if (aVal > bVal) return direction === 'asc' ? 1 : -1;
            return 0;
        });

        // Réorganiser le DOM
        rows.forEach(row => tbody.appendChild(row));
    }

    /**
     * Fonction de debounce pour optimiser les performances
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
})();


