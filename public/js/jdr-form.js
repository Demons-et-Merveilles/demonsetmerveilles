/**
 * Formulaire de JDR : aperçu de l'image choisie avant l'envoi (jQuery).
 */
$(function () {
    const $champFichier = $('.jdr-form input[type="file"]');
    const $apercu = $('#apercu-image');
    const $vide = $('#apercu-vide');

    // Aperçu affiché au chargement de la page (image actuelle en modification)
    const srcInitiale = $apercu.attr('src') || '';
    let urlTemporaire = null;

    // Affiche ou masque l'aperçu selon qu'il y a une image à montrer
    function afficherApercu(src) {
        if (src) {
            $apercu.attr('src', src).prop('hidden', false);
            $vide.prop('hidden', true);
        } else {
            $apercu.removeAttr('src').prop('hidden', true);
            $vide.prop('hidden', false);
        }
    }

    $champFichier.on('change', function () {
        // Libère l'aperçu précédent pour ne pas garder de mémoire inutilement
        if (urlTemporaire) {
            URL.revokeObjectURL(urlTemporaire);
            urlTemporaire = null;
        }

        const fichier = this.files[0];

        if (fichier && fichier.type.startsWith('image/')) {
            urlTemporaire = URL.createObjectURL(fichier);
            afficherApercu(urlTemporaire);
        } else {
            // Aucun fichier (ou fichier non image) : on revient à l'image actuelle
            afficherApercu(srcInitiale);
        }
    });
});
