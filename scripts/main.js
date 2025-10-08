/**
 * Moodle Question Bank Diagnostic Tool - JavaScript
 */

(function() {
    'use strict';

    // État global
    const state = {
        selectedCategories: new Set(),
        allCategories: [],
        filteredCategories: [],
        currentSort: { column: null, direction: 'asc' },
        currentPage: 1,
        itemsPerPage: 50
    };

    // Initialisation au chargement du DOM
    document.addEventListener('DOMContentLoaded', function() {
        initializeTable();
        initializeFilters();
        initializeBulkActions();
        initializeModals();
        initializeSorting();
    });

    /**
     * Initialise la table et les cases à cocher
     */
    function initializeTable() {
        const checkboxAll = document.getElementById('select-all');
        const checkboxes = document.querySelectorAll('.category-checkbox');

        // Récupérer toutes les catégories
        checkboxes.forEach(cb => {
            const row = cb.closest('tr');
            const categoryData = {
                id: parseInt(cb.value),
                name: row.dataset.name,
                empty: row.dataset.empty === '1',
                orphan: row.dataset.orphan === '1'
            };
            state.allCategories.push(categoryData);
        });

        state.filteredCategories = [...state.allCategories];

        // Sélectionner/désélectionner tout
        if (checkboxAll) {
            checkboxAll.addEventListener('change', function() {
                const checked = this.checked;
                checkboxes.forEach(cb => {
                    const row = cb.closest('tr');
                    // Ne sélectionner que les lignes visibles (non filtrées)
                    if (row.style.display !== 'none') {
                        cb.checked = checked;
                        if (checked) {
                            state.selectedCategories.add(parseInt(cb.value));
                        } else {
                            state.selectedCategories.delete(parseInt(cb.value));
                        }
                        updateRowSelection(cb);
                    }
                });
                updateBulkActionsBar();
            });
        }

        // Gestion des cases individuelles
        checkboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                const id = parseInt(this.value);
                if (this.checked) {
                    state.selectedCategories.add(id);
                } else {
                    state.selectedCategories.delete(id);
                }
                updateRowSelection(this);
                updateBulkActionsBar();
            });
        });
    }

    /**
     * Met à jour la classe de la ligne sélectionnée
     */
    function updateRowSelection(checkbox) {
        const row = checkbox.closest('tr');
        if (checkbox.checked) {
            row.classList.add('selected');
        } else {
            row.classList.remove('selected');
        }
    }

    /**
     * Met à jour la barre d'actions groupées
     */
    function updateBulkActionsBar() {
        const bulkBar = document.getElementById('bulk-actions-bar');
        const countSpan = document.getElementById('selected-count');
        
        if (!bulkBar || !countSpan) return;

        const count = state.selectedCategories.size;
        
        if (count > 0) {
            bulkBar.classList.add('visible');
            countSpan.textContent = count;
        } else {
            bulkBar.classList.remove('visible');
        }
    }

    /**
     * Initialise les filtres
     */
    function initializeFilters() {
        const searchInput = document.getElementById('filter-search');
        const statusFilter = document.getElementById('filter-status');
        const contextFilter = document.getElementById('filter-context');

        if (searchInput) {
            searchInput.addEventListener('input', debounce(applyFilters, 300));
        }

        if (statusFilter) {
            statusFilter.addEventListener('change', applyFilters);
        }

        if (contextFilter) {
            contextFilter.addEventListener('change', applyFilters);
        }
    }

    /**
     * Applique les filtres sur le tableau
     */
    function applyFilters() {
        const searchTerm = document.getElementById('filter-search')?.value.toLowerCase() || '';
        const status = document.getElementById('filter-status')?.value || 'all';
        const context = document.getElementById('filter-context')?.value || 'all';

        const rows = document.querySelectorAll('.qd-table tbody tr');

        rows.forEach(row => {
            let visible = true;

            // Filtre de recherche
            if (searchTerm) {
                const name = row.dataset.name?.toLowerCase() || '';
                const id = row.dataset.id?.toLowerCase() || '';
                if (!name.includes(searchTerm) && !id.includes(searchTerm)) {
                    visible = false;
                }
            }

            // Filtre de statut
            if (status !== 'all' && visible) {
                const isEmpty = row.getAttribute('data-empty') === '1';
                const isOrphan = row.getAttribute('data-orphan') === '1';
                const isDuplicate = row.getAttribute('data-duplicate') === '1';
                const isProtected = row.getAttribute('data-protected') === '1';
                const questionCount = parseInt(row.getAttribute('data-questions') || '0');
                const subcatCount = parseInt(row.getAttribute('data-subcategories') || '0');
                
                // ⚠️ SÉCURITÉ CRITIQUE : Ne JAMAIS afficher comme supprimable si :
                // - La catégorie est protégée
                // - La catégorie contient des questions (même 1 seule)
                // - La catégorie contient des sous-catégories
                if (status === 'deletable') {
                    if (isProtected || questionCount > 0 || subcatCount > 0) {
                        visible = false;
                    }
                } else if (status === 'empty' && !isEmpty) {
                    visible = false;
                } else if (status === 'duplicate' && !isDuplicate) {
                    visible = false;
                } else if (status === 'orphan' && !isOrphan) {
                    visible = false;
                } else if (status === 'ok' && (isEmpty || isOrphan || isDuplicate)) {
                    visible = false;
                }
            }

            // Filtre de contexte
            if (context !== 'all' && visible) {
                if (row.dataset.context !== context) {
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
        const rows = document.querySelectorAll('.qd-table tbody tr');
        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
        
        const statsDiv = document.getElementById('filter-stats');
        if (statsDiv) {
            statsDiv.textContent = `Affichage de ${visibleRows.length} sur ${rows.length} catégories`;
        }
    }

    /**
     * Initialise les actions groupées
     */
    function initializeBulkActions() {
        const deleteBtn = document.getElementById('bulk-delete-btn');
        const exportBtn = document.getElementById('bulk-export-btn');
        const cancelBtn = document.getElementById('bulk-cancel-btn');
        
        // Bouton de suppression
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                if (state.selectedCategories.size === 0) {
                    alert('Veuillez sélectionner au moins une catégorie.');
                    return;
                }

                // ⚠️ FIX: Utiliser POST au lieu de GET pour éviter "Request-URI Too Long"
                const ids = Array.from(state.selectedCategories).join(',');
                submitPostForm(M.cfg.wwwroot + '/local/question_diagnostic/actions/delete.php', {
                    ids: ids,
                    sesskey: M.cfg.sesskey
                });
            });
        }

        // Bouton d'export
        if (exportBtn) {
            exportBtn.addEventListener('click', function() {
                if (state.selectedCategories.size === 0) {
                    alert('Veuillez sélectionner au moins une catégorie.');
                    return;
                }

                // ⚠️ FIX: Utiliser POST au lieu de GET pour éviter "Request-URI Too Long"
                const ids = Array.from(state.selectedCategories).join(',');
                submitPostForm(M.cfg.wwwroot + '/local/question_diagnostic/actions/export.php', {
                    type: 'csv',
                    ids: ids,
                    sesskey: M.cfg.sesskey
                });
            });
        }

        // Bouton d'annulation
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                // Désélectionner toutes les cases
                const checkboxes = document.querySelectorAll('.category-checkbox');
                const checkboxAll = document.getElementById('select-all');
                
                checkboxes.forEach(cb => {
                    cb.checked = false;
                    updateRowSelection(cb);
                });
                
                if (checkboxAll) {
                    checkboxAll.checked = false;
                }
                
                state.selectedCategories.clear();
                updateBulkActionsBar();
            });
        }
    }

    /**
     * Initialise les modals
     */
    function initializeModals() {
        // Modal de fusion
        const mergeButtons = document.querySelectorAll('.merge-btn');
        mergeButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const categoryId = this.dataset.id;
                const categoryName = this.dataset.name;
                showMergeModal(categoryId, categoryName);
            });
        });

        // Fermeture des modals
        document.querySelectorAll('.qd-modal-close').forEach(btn => {
            btn.addEventListener('click', function() {
                this.closest('.qd-modal').classList.remove('visible');
            });
        });

        // Fermeture en cliquant sur le fond
        document.querySelectorAll('.qd-modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('visible');
                }
            });
        });
    }

    /**
     * Affiche le modal de fusion
     */
    function showMergeModal(sourceId, sourceName) {
        const modal = document.getElementById('merge-modal');
        if (!modal) return;

        const modalBody = modal.querySelector('.qd-modal-body');
        const select = document.createElement('select');
        select.id = 'merge-dest-select';
        select.className = 'form-control';

        // Créer la liste des catégories destination possibles
        const categories = state.allCategories.filter(cat => cat.id !== parseInt(sourceId));
        
        let html = '<option value="">-- Sélectionner une catégorie destination --</option>';
        categories.forEach(cat => {
            html += `<option value="${cat.id}">${cat.name} (ID: ${cat.id})</option>`;
        });
        select.innerHTML = html;

        modalBody.innerHTML = `
            <p>Fusionner la catégorie : <strong>${sourceName}</strong> (ID: ${sourceId})</p>
            <p>Vers la catégorie :</p>
        `;
        modalBody.appendChild(select);

        // Bouton de confirmation
        const footer = modal.querySelector('.qd-modal-footer');
        footer.innerHTML = `
            <button class="btn btn-secondary qd-modal-close">Annuler</button>
            <button id="confirm-merge-btn" class="btn btn-primary">Fusionner</button>
        `;

        const confirmBtn = document.getElementById('confirm-merge-btn');
        confirmBtn.addEventListener('click', function() {
            const destId = select.value;
            if (!destId) {
                alert('Veuillez sélectionner une catégorie destination.');
                return;
            }

            const url = M.cfg.wwwroot + '/local/question_diagnostic/actions/merge.php?source=' + sourceId + '&dest=' + destId + '&sesskey=' + M.cfg.sesskey;
            window.location.href = url;
        });

        modal.classList.add('visible');
    }

    /**
     * Initialise le tri des colonnes
     */
    function initializeSorting() {
        const headers = document.querySelectorAll('.qd-table th.sortable');
        
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
        const tbody = document.querySelector('.qd-table tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const header = document.querySelector(`th[data-column="${column}"]`);
        
        // Déterminer la direction du tri
        let direction = 'asc';
        if (state.currentSort.column === column) {
            direction = state.currentSort.direction === 'asc' ? 'desc' : 'asc';
        }
        
        state.currentSort = { column, direction };

        // Mettre à jour les classes CSS des en-têtes
        document.querySelectorAll('.qd-table th.sortable').forEach(h => {
            h.classList.remove('sorted-asc', 'sorted-desc');
        });
        header.classList.add(direction === 'asc' ? 'sorted-asc' : 'sorted-desc');

        // Trier les lignes
        rows.sort((a, b) => {
            let aVal = a.dataset[column] || '';
            let bVal = b.dataset[column] || '';

            // Convertir en nombre si possible
            if (!isNaN(aVal) && !isNaN(bVal)) {
                aVal = parseFloat(aVal);
                bVal = parseFloat(bVal);
            } else {
                aVal = aVal.toLowerCase();
                bVal = bVal.toLowerCase();
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

    /**
     * Soumet un formulaire en POST (pour éviter les URLs trop longues)
     * @param {string} url - URL de destination
     * @param {object} params - Paramètres à envoyer
     */
    function submitPostForm(url, params) {
        // Créer un formulaire invisible
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        form.style.display = 'none';

        // Ajouter les paramètres comme champs cachés
        for (const key in params) {
            if (params.hasOwnProperty(key)) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = params[key];
                form.appendChild(input);
            }
        }

        // Ajouter le formulaire au DOM, le soumettre, puis le supprimer
        document.body.appendChild(form);
        form.submit();
    }

    /**
     * Fonction utilitaire pour afficher les messages
     */
    function showMessage(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `qd-alert qd-alert-${type}`;
        alertDiv.textContent = message;
        alertDiv.style.position = 'fixed';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.minWidth = '300px';
        alertDiv.style.animation = 'slideIn 0.3s ease-out';

        document.body.appendChild(alertDiv);

        setTimeout(() => {
            alertDiv.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => alertDiv.remove(), 300);
        }, 5000);
    }

    // Exposer certaines fonctions globalement pour les boutons inline
    window.QDTool = {
        deleteCategory: function(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ?')) {
                window.location.href = M.cfg.wwwroot + '/local/question_diagnostic/actions/delete.php?id=' + id + '&sesskey=' + M.cfg.sesskey;
            }
        },
        
        showMergeModal: showMergeModal,
        
        showMessage: showMessage
    };
})();

