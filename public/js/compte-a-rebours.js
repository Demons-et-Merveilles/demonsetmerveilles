/**
 * Compte à rebours des portes ouvertes.
 * La date cible est portée par l'élément #countdown (attribut data-cible),
 * elle est définie dans config/services.yaml (app.date_portes_ouvertes).
 */
$(function () {
    const $compteur = $('#countdown');

    // Rien à faire si la page n'a pas de compte à rebours
    if ($compteur.length === 0) {
        return;
    }

    const dateCible = new Date($compteur.data('cible'));
    let minuteur = null;

    // Ajoute un zéro devant les nombres < 10 (ex : 5 -> "05")
    function surDeuxChiffres(nombre) {
        return nombre < 10 ? '0' + nombre : String(nombre);
    }

    // Formate un nombre de secondes en "X JOURS / XX HEURES / XX MINUTES / XX SECONDES"
    function formaterDuree(secondesTotales) {
        let secondes = secondesTotales;

        const jours = Math.floor(secondes / (24 * 3600));
        secondes %= 24 * 3600;

        const heures = Math.floor(secondes / 3600);
        secondes %= 3600;

        const minutes = Math.floor(secondes / 60);
        secondes %= 60;

        return `${jours} JOUR${jours > 1 ? 'S' : ''} \n` +
            `${surDeuxChiffres(heures)} HEURE${heures > 1 ? 'S' : ''} \n` +
            `${surDeuxChiffres(minutes)} MINUTE${minutes > 1 ? 'S' : ''} \n` +
            `${surDeuxChiffres(secondes)} SECONDE${secondes > 1 ? 'S' : ''} `;
    }

    // Met à jour l'affichage ; arrête le minuteur une fois la date atteinte
    function mettreAJour() {
        const secondesRestantes = Math.floor((dateCible - new Date()) / 1000);

        if (secondesRestantes > 0) {
            $compteur.text(formaterDuree(secondesRestantes));
        } else {
            $compteur.text("C'est maintenant !");
            clearInterval(minuteur);
        }
    }

    mettreAJour(); // affichage immédiat, sans attendre la première seconde
    minuteur = setInterval(mettreAJour, 1000);
});
