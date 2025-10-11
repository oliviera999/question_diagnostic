/**
 * 📊 Barres de progression pour opérations longues
 * 
 * 🆕 v1.9.41 : TODO BASSE #2 - Progress bars AJAX
 * 
 * Affiche des barres de progression visuelles pour les opérations
 * longues (suppressions en masse, exports, scans).
 * 
 * @package    local_question_diagnostic
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function() {
    'use strict';

    /**
     * Crée et affiche une modal avec barre de progression
     * 
     * @param {string} title Titre de l'opération
     * @param {string} message Message initial
     * @returns {object} Objet avec méthodes de contrôle
     */
    function createProgressModal(title, message) {
        // Créer la modal
        const modal = document.createElement('div');
        modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; justify-content: center; align-items: center;';
        modal.id = 'qd-progress-modal';
        
        const content = document.createElement('div');
        content.style.cssText = 'background: white; padding: 30px; border-radius: 10px; min-width: 500px; max-width: 600px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);';
        
        // Titre
        const titleEl = document.createElement('h3');
        titleEl.textContent = title;
        titleEl.style.cssText = 'margin: 0 0 20px 0; color: #333;';
        content.appendChild(titleEl);
        
        // Message
        const messageEl = document.createElement('div');
        messageEl.id = 'qd-progress-message';
        messageEl.textContent = message;
        messageEl.style.cssText = 'margin-bottom: 20px; color: #666;';
        content.appendChild(messageEl);
        
        // Barre de progression container
        const progressContainer = document.createElement('div');
        progressContainer.style.cssText = 'background: #f0f0f0; border-radius: 10px; overflow: hidden; height: 30px; position: relative; margin-bottom: 10px;';
        
        // Barre de progression
        const progressBar = document.createElement('div');
        progressBar.id = 'qd-progress-bar';
        progressBar.style.cssText = 'background: linear-gradient(90deg, #4CAF50, #45a049); height: 100%; width: 0%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;';
        progressContainer.appendChild(progressBar);
        content.appendChild(progressContainer);
        
        // Texte de pourcentage
        const percentText = document.createElement('div');
        percentText.id = 'qd-progress-percent';
        percentText.textContent = '0%';
        percentText.style.cssText = 'text-align: center; color: #666; font-size: 14px;';
        content.appendChild(percentText);
        
        // Détails (items traités)
        const detailsEl = document.createElement('div');
        detailsEl.id = 'qd-progress-details';
        detailsEl.style.cssText = 'margin-top: 15px; font-size: 12px; color: #999; text-align: center;';
        content.appendChild(detailsEl);
        
        modal.appendChild(content);
        document.body.appendChild(modal);
        
        // API de contrôle
        return {
            /**
             * Met à jour la progression
             * @param {number} current Nombre d'items traités
             * @param {number} total Nombre total d'items
             */
            update: function(current, total) {
                const percent = Math.round((current / total) * 100);
                progressBar.style.width = percent + '%';
                progressBar.textContent = percent + '%';
                percentText.textContent = percent + '%';
                detailsEl.textContent = current + ' / ' + total + ' items traités';
            },
            
            /**
             * Met à jour le message
             * @param {string} msg Nouveau message
             */
            setMessage: function(msg) {
                messageEl.textContent = msg;
            },
            
            /**
             * Ferme la modal
             */
            close: function() {
                setTimeout(function() {
                    modal.remove();
                }, 500);
            },
            
            /**
             * Affiche un état de succès
             * @param {string} msg Message de succès
             */
            success: function(msg) {
                progressBar.style.background = 'linear-gradient(90deg, #4CAF50, #45a049)';
                progressBar.style.width = '100%';
                progressBar.textContent = '✓ Terminé';
                messageEl.textContent = msg;
                messageEl.style.color = '#4CAF50';
                messageEl.style.fontWeight = 'bold';
            },
            
            /**
             * Affiche un état d'erreur
             * @param {string} msg Message d'erreur
             */
            error: function(msg) {
                progressBar.style.background = 'linear-gradient(90deg, #f44336, #d32f2f)';
                messageEl.textContent = '❌ ' + msg;
                messageEl.style.color = '#f44336';
                messageEl.style.fontWeight = 'bold';
            }
        };
    }

    /**
     * Simule une progression par lots
     * Utile pour les opérations synchrones longues
     * 
     * @param {Array} items Items à traiter
     * @param {Function} callback Fonction à appeler pour chaque item
     * @param {Object} options Options (title, message, onComplete, batchSize)
     */
    function processBatchWithProgress(items, callback, options) {
        options = options || {};
        const title = options.title || 'Opération en cours...';
        const message = options.message || 'Traitement des items...';
        const batchSize = options.batchSize || 10;
        const onComplete = options.onComplete || function() {};
        
        const progress = createProgressModal(title, message);
        
        let currentIndex = 0;
        const total = items.length;
        
        function processBatch() {
            const end = Math.min(currentIndex + batchSize, total);
            
            for (let i = currentIndex; i < end; i++) {
                try {
                    callback(items[i], i);
                } catch (e) {
                    console.error('Erreur traitement item', i, e);
                }
            }
            
            currentIndex = end;
            progress.update(currentIndex, total);
            
            if (currentIndex < total) {
                // Continuer avec le prochain lot
                setTimeout(processBatch, 10);
            } else {
                // Terminé
                progress.success('Opération terminée avec succès !');
                setTimeout(function() {
                    progress.close();
                    onComplete();
                }, 1500);
            }
        }
        
        // Démarrer le traitement
        processBatch();
    }

    /**
     * Exemple : Suppression en masse avec barre de progression
     * 
     * @param {Array} categoryIds IDs des catégories à supprimer
     * @param {Function} onComplete Callback après suppression
     */
    function deleteCategoriesWithProgress(categoryIds, onComplete) {
        processBatchWithProgress(
            categoryIds,
            function(categoryId) {
                // Ici, on simule juste la suppression
                // Dans une vraie implémentation, ce serait un appel AJAX
                console.log('Suppression catégorie', categoryId);
            },
            {
                title: '🗑️ Suppression en masse',
                message: 'Suppression des catégories sélectionnées...',
                batchSize: 5,
                onComplete: onComplete
            }
        );
    }

    // Export global
    window.QDProgress = {
        createModal: createProgressModal,
        processBatch: processBatchWithProgress,
        deleteCategories: deleteCategoriesWithProgress
    };
})();

