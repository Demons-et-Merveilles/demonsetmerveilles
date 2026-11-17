/**
 * Caisse Démons et Merveilles — interface de vente (jQuery).
 *
 * Les produits et l'historique sont enregistrés en base par l'API Symfony
 * (voir src/Controller/Admin/CaisseController.php). Plus de localStorage.
 * Les adresses de l'API et le jeton CSRF viennent des attributs data- de #app.
 */
$(function () {

    // ──────────────────────────────────────────────
    // CONFIGURATION (injectée par Twig)
    // ──────────────────────────────────────────────
    const $app = $('#app');
    const config = {
        urlProduits: $app.data('urlProduits'),
        urlProduitsExport: $app.data('urlProduitsExport'),
        urlProduitsImport: $app.data('urlProduitsImport'),
        urlVente: $app.data('urlVente'),
        urlHistorique: $app.data('urlHistorique'),
        urlHistoriqueExport: $app.data('urlHistoriqueExport'),
        urlHistoriqueImport: $app.data('urlHistoriqueImport'),
    };

    // Le jeton CSRF accompagne tous les appels AJAX
    $.ajaxSetup({headers: {'X-CSRF-Token': $app.data('csrf')}});

    // ──────────────────────────────────────────────
    // ÉTAT
    // ──────────────────────────────────────────────
    let produits = [];                 // catalogue chargé depuis la base
    let panier = {};                   // { référenceProduit: quantité }
    let categorieCourante = 'Tous';
    let idPanier = genererIdPanier();
    let paiementEnCours = false;       // évite le double clic sur "Valider le paiement"
    let minuteurToast = null;

    // ──────────────────────────────────────────────
    // INITIALISATION
    // ──────────────────────────────────────────────
    $('#session-id').text(idPanier);
    chargerProduits();
    mettreAJourHorloge();
    setInterval(mettreAJourHorloge, 1000);

    // ──────────────────────────────────────────────
    // CATALOGUE
    // ──────────────────────────────────────────────

    // Charge les produits actifs depuis la base
    function chargerProduits() {
        $.getJSON(config.urlProduits)
            .done(function (donnees) {
                panier = {};
                produits = donnees;
                afficherCategories();
                afficherGrille();
                mettreAJourPanier();
                afficherToast(`${donnees.length} articles chargés ✓`);
            })
            .fail(function (jqXHR) {
                afficherToast('Erreur : ' + messageErreur(jqXHR) + ' ✗');
            });
    }

    // Retrouve un produit à partir de sa référence
    function trouverProduit(reference) {
        return produits.find(p => p.id === reference);
    }

    // ──────────────────────────────────────────────
    // HORLOGE
    // ──────────────────────────────────────────────
    function mettreAJourHorloge() {
        const maintenant = new Date();
        $('#clock').text(
            maintenant.toLocaleDateString('fr-FR') + ' · ' +
            maintenant.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit', second: '2-digit'})
        );
    }

    // ──────────────────────────────────────────────
    // CATÉGORIES
    // ──────────────────────────────────────────────
    function afficherCategories() {
        const categories = ['Tous', ...new Set(produits.map(p => p.categorie))];
        const $conteneur = $('#category-filters').empty();

        categories.forEach(function (categorie) {
            $('<button>', {
                class: 'filter-btn' + (categorie === categorieCourante ? ' active' : ''),
                text: categorie,
            }).attr('data-categorie', categorie).appendTo($conteneur);
        });
    }

    // Clic sur un filtre de catégorie
    $('#category-filters').on('click', '.filter-btn', function () {
        categorieCourante = $(this).attr('data-categorie');
        afficherCategories();
        afficherGrille();
    });

    // ──────────────────────────────────────────────
    // GRILLE DES ARTICLES
    // ──────────────────────────────────────────────
    $('#search').on('input', afficherGrille);

    function afficherGrille() {
        const recherche = $('#search').val().toLowerCase();
        const $grille = $('#products-grid').empty();

        const filtres = produits.filter(function (p) {
            const bonneCategorie = categorieCourante === 'Tous' || p.categorie === categorieCourante;
            const bonneRecherche = p.nom.toLowerCase().includes(recherche) || p.id.toLowerCase().includes(recherche);
            return bonneCategorie && bonneRecherche;
        });

        if (filtres.length === 0) {
            $('<div>', {class: 'no-results', text: 'Aucun article trouvé'}).appendTo($grille);
            return;
        }

        filtres.forEach(function (p) {
            const quantite = panier[p.id] || 0;

            // .text() échappe le contenu : un nom de produit ne peut pas injecter de HTML
            const $carte = $('<div>', {class: 'product-card' + (quantite > 0 ? ' in-cart' : '')}).attr('data-id', p.id);
            $('<div>', {class: 'card-badge', text: quantite > 0 ? quantite : ''}).appendTo($carte);
            $('<span>', {class: 'card-emoji', text: p.emoji || '📦'}).appendTo($carte);
            $('<div>', {class: 'card-name', text: p.nom}).appendTo($carte);
            $('<div>', {class: 'card-cat', text: p.categorie}).appendTo($carte);
            $('<div>').append($('<span>', {class: 'card-price', text: formaterPrix(p.prix)})).appendTo($carte);

            $grille.append($carte);
        });
    }

    // Clic sur une carte article : ajoute au panier
    $('#products-grid').on('click', '.product-card', function () {
        ajouterAuPanier($(this).attr('data-id'));
    });

    // ──────────────────────────────────────────────
    // PANIER
    // ──────────────────────────────────────────────
    function ajouterAuPanier(reference) {
        panier[reference] = (panier[reference] || 0) + 1;
        mettreAJourPanier();

        // Petite animation du total
        const $total = $('#total-display').removeClass('bump');
        void $total[0].offsetWidth; // force le navigateur à relancer l'animation
        $total.addClass('bump');
        setTimeout(() => $total.removeClass('bump'), 300);
    }

    function retirerUnDuPanier(reference) {
        if (!panier[reference]) {
            return;
        }
        panier[reference]--;
        if (panier[reference] <= 0) {
            delete panier[reference];
        }
        mettreAJourPanier();
    }

    function viderPanier() {
        panier = {};
        mettreAJourPanier();
    }

    // Calcule le contenu détaillé du panier (utilisé par le panier et le reçu)
    function calculerPanier() {
        const lignes = [];
        let total = 0;
        let nbArticles = 0;

        Object.entries(panier).forEach(function ([reference, quantite]) {
            const produit = trouverProduit(reference);
            if (!produit) {
                return;
            }
            lignes.push({produit: produit, quantite: quantite});
            total += produit.prix * quantite;
            nbArticles += quantite;
        });

        return {lignes: lignes, total: total, nbArticles: nbArticles};
    }

    function mettreAJourPanier() {
        const contenu = calculerPanier();
        const aDesArticles = contenu.lignes.length > 0;

        // Total et compteur
        $('#total-display').text(formaterPrix(contenu.total));
        $('#item-count').text(contenu.nbArticles === 0
            ? '0 article'
            : `${contenu.nbArticles} article${contenu.nbArticles > 1 ? 's' : ''}`);

        // Boutons
        $('#btn-pay, #btn-clear').prop('disabled', !aDesArticles);

        // Liste des articles du panier
        $('#cart-empty').css('display', aDesArticles ? 'none' : 'flex');
        const $liste = $('#cart-items').empty();

        contenu.lignes.forEach(function (ligne) {
            const p = ligne.produit;
            const $ligne = $('<div>', {class: 'cart-item'}).attr('data-id', p.id);

            $('<div>', {class: 'cart-item-emoji', text: p.emoji || '📦'}).appendTo($ligne);

            $('<div>', {class: 'cart-item-info'})
                .append($('<div>', {class: 'cart-item-name', text: p.nom}))
                .append($('<div>', {class: 'cart-item-price', text: formaterPrix(p.prix) + ' / u.'}))
                .appendTo($ligne);

            $('<div>', {class: 'cart-item-controls'})
                .append($('<button>', {class: 'qty-btn remove', title: 'Retirer', text: '−'}).attr('data-action', 'moins'))
                .append($('<span>', {class: 'cart-qty', text: ligne.quantite}))
                .append($('<button>', {class: 'qty-btn', title: 'Ajouter', text: '+'}).attr('data-action', 'plus'))
                .appendTo($ligne);

            $('<div>', {class: 'cart-item-total', text: formaterPrix(p.prix * ligne.quantite)}).appendTo($ligne);

            $liste.append($ligne);
        });

        // Pastilles de quantité sur la grille
        $('.product-card').each(function () {
            const $carte = $(this);
            const quantite = panier[$carte.attr('data-id')] || 0;
            $carte.toggleClass('in-cart', quantite > 0);
            $carte.find('.card-badge').text(quantite > 0 ? quantite : '');
        });
    }

    // Boutons − et + de chaque ligne du panier
    $('#cart-items').on('click', '.qty-btn', function () {
        const reference = $(this).closest('.cart-item').attr('data-id');
        if ($(this).attr('data-action') === 'plus') {
            ajouterAuPanier(reference);
        } else {
            retirerUnDuPanier(reference);
        }
    });

    $('#btn-clear').on('click', viderPanier);

    // ──────────────────────────────────────────────
    // REÇU / ENCAISSEMENT
    // ──────────────────────────────────────────────
    $('#btn-pay').on('click', ouvrirRecu);

    function ouvrirRecu() {
        const contenu = calculerPanier();
        const $lignes = $('#receipt-items').empty();

        contenu.lignes.forEach(function (ligne) {
            $('<div>', {class: 'receipt-row'})
                .append($('<span>', {class: 'rname', text: (ligne.produit.emoji || '') + ' ' + ligne.produit.nom}))
                .append($('<span>', {class: 'rqty', text: '×' + ligne.quantite}))
                .append($('<span>', {class: 'rprice', text: formaterPrix(ligne.produit.prix * ligne.quantite)}))
                .appendTo($lignes);
        });

        $('#receipt-total').text(formaterPrix(contenu.total));
        $('#receipt-meta').text(`Panier #${idPanier} · ${new Date().toLocaleString('fr-FR')}`);

        ouvrirModal('modal-receipt');
    }

    // Valide le paiement : le panier est enregistré en base par le serveur
    $('#btn-confirm-pay').on('click', function () {
        if (paiementEnCours) {
            return;
        }
        paiementEnCours = true;

        envoyerJson(config.urlVente, {idPanier: idPanier, panier: panier})
            .done(function () {
                fermerModal('modal-receipt');

                // Message de succès, puis nouveau panier
                const $succes = $('#pay-success').addClass('show');
                setTimeout(function () {
                    $succes.removeClass('show');
                    viderPanier();
                    idPanier = genererIdPanier();
                    $('#session-id').text(idPanier);
                    afficherToast('Vente enregistrée ✓');
                }, 1400);
            })
            .fail(function (jqXHR) {
                afficherToast('Erreur : ' + messageErreur(jqXHR) + ' ✗');
            })
            .always(function () {
                paiementEnCours = false;
            });
    });

    // ──────────────────────────────────────────────
    // HISTORIQUE
    // ──────────────────────────────────────────────
    $('#btn-history').on('click', ouvrirHistorique);

    // Charge l'historique de la session depuis la base et l'affiche (le plus récent en premier)
    function ouvrirHistorique() {
        $.getJSON(config.urlHistorique)
            .done(function (historique) {
                const $corps = $('#history-tbody').empty();

                if (historique.length === 0) {
                    $('<tr>').append($('<td>', {colspan: 7, class: 'history-empty', text: 'Aucune vente enregistrée'})).appendTo($corps);
                } else {
                    historique.slice().reverse().forEach(function (e) {
                        $('<tr>')
                            .append($('<td>', {text: e.id_panier}).css({color: 'var(--accent)', fontSize: '10px'}))
                            .append($('<td>', {text: e.id_article}))
                            .append($('<td>', {text: e.nom}))
                            .append($('<td>', {text: e.quantite}).css('textAlign', 'center'))
                            .append($('<td>', {text: formaterPrix(e.prix_unitaire)}))
                            .append($('<td>', {text: formaterPrix(e.sous_total)}).css('color', 'var(--accent)'))
                            .append($('<td>', {text: new Date(e.date).toLocaleString('fr-FR')}).css('fontSize', '10px'))
                            .appendTo($corps);
                    });
                }
                ouvrirModal('modal-history');
            })
            .fail(function (jqXHR) {
                afficherToast('Erreur : ' + messageErreur(jqXHR) + ' ✗');
            });
    }

    // ──────────────────────────────────────────────
    // FENÊTRES MODALES
    // ──────────────────────────────────────────────
    function ouvrirModal(id) {
        $('#' + id).addClass('open');
    }

    function fermerModal(id) {
        $('#' + id).removeClass('open');
    }

    // Boutons ✕
    $(document).on('click', '[data-fermer]', function () {
        fermerModal($(this).attr('data-fermer'));
    });

    // Clic sur le fond sombre : ferme la fenêtre
    $('.modal-overlay').on('click', function (e) {
        if (e.target === this) {
            $(this).removeClass('open');
        }
    });

    // ──────────────────────────────────────────────
    // IMPORT / EXPORT
    // ──────────────────────────────────────────────

    // "Charger JSON" : importe un catalogue (products.json) dans la base
    $('#btn-charger-produits').on('click', function () {
        $('#fichier-produits').trigger('click');
    });

    $('#fichier-produits').on('change', function () {
        const fichier = this.files[0];
        if (!fichier) {
            return;
        }

        lireFichierJson(fichier, function (donnees) {
            envoyerJson(config.urlProduitsImport, donnees)
                .done(chargerProduits) // recharge le catalogue depuis la base
                .fail(function (jqXHR) {
                    afficherToast('Erreur : ' + messageErreur(jqXHR) + ' ✗');
                });
        });
        $(this).val('');
    });

    // Importer un ancien historique JSON dans la session en cours
    $('#btn-import-historique').on('click', function () {
        $('#fichier-historique').trigger('click');
    });

    $('#fichier-historique').on('change', function () {
        const fichier = this.files[0];
        if (!fichier) {
            return;
        }

        lireFichierJson(fichier, function (donnees) {
            envoyerJson(config.urlHistoriqueImport, donnees)
                .done(function (reponse) {
                    ouvrirHistorique();
                    afficherToast(`${reponse.importes} paniers importés ✓`);
                })
                .fail(function (jqXHR) {
                    afficherToast('Erreur : ' + messageErreur(jqXHR) + ' ✗');
                });
        });
        $(this).val('');
    });

    // Les exports sont des téléchargements générés par le serveur
    $('#btn-export-historique').on('click', function () {
        window.location.href = config.urlHistoriqueExport;
        afficherToast('Historique exporté ✓');
    });

    $('#btn-export-catalogue').on('click', function () {
        window.location.href = config.urlProduitsExport;
        afficherToast('Catalogue exporté ✓');
    });

    // Lit un fichier JSON choisi par l'utilisateur et passe son contenu au callback
    function lireFichierJson(fichier, callback) {
        const lecteur = new FileReader();
        lecteur.onload = function (e) {
            let donnees;
            try {
                donnees = JSON.parse(e.target.result);
            } catch (erreur) {
                afficherToast('Erreur : JSON invalide ✗');
                return;
            }
            callback(donnees);
        };
        lecteur.readAsText(fichier);
    }

    // ──────────────────────────────────────────────
    // OUTILS
    // ──────────────────────────────────────────────

    // Envoie des données en JSON (POST) ; le jeton CSRF part grâce à $.ajaxSetup
    function envoyerJson(url, donnees) {
        return $.ajax({
            url: url,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(donnees),
            dataType: 'json',
        });
    }

    // Message d'erreur renvoyé par le serveur, ou message générique
    function messageErreur(jqXHR) {
        if (jqXHR.responseJSON && jqXHR.responseJSON.erreur) {
            return jqXHR.responseJSON.erreur;
        }
        return jqXHR.status === 403 ? 'accès refusé (reconnectez-vous)' : 'erreur serveur (' + jqXHR.status + ')';
    }

    function formaterPrix(nombre) {
        return nombre.toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' €';
    }

    // Identifiant unique d'un panier (lettres majuscules et chiffres, ex : CMH3K9XYZ)
    function genererIdPanier() {
        return 'C' + Date.now().toString(36).toUpperCase() + Math.random().toString(36).slice(2, 5).toUpperCase();
    }

    function afficherToast(message) {
        const $toast = $('#toast').text(message).addClass('show');
        clearTimeout(minuteurToast);
        minuteurToast = setTimeout(() => $toast.removeClass('show'), 2500);
    }
});
