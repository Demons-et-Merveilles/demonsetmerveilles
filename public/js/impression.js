/**
 * Page d'impression d'une fiche JDR.
 * Les fiches sont déjà dans la page (masquées) : on affiche uniquement celle choisie.
 */
$(function () {
    const $select = $('#selectToPrint');
    const $fiches = $('#cardToPrint .carte-impression');
    const $messageVide = $('#aucune-carte');

    // Affiche la fiche correspondant au JDR sélectionné dans la liste
    $select.on('change', function () {
        const idChoisi = String($(this).val());

        // On masque tout, puis on réaffiche ce qui doit l'être
        $fiches.prop('hidden', true);

        if (idChoisi === '-1') {
            $messageVide.prop('hidden', false);
            return;
        }

        $messageVide.prop('hidden', true);

        const $fiche = $fiches.filter(function () {
            return String($(this).data('id')) === idChoisi;
        });

        $fiche.prop('hidden', false);

        // Le titre de la page sert de nom de fichier à l'impression PDF
        document.title = $fiche.data('titre');
    });

    // Lance l'impression du navigateur
    $('#boutonImprimer').on('click', function () {
        window.print();
    });
});
