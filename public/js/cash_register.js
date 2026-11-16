/**
 * Caisse : front-end jQuery.
 *
 * Le catalogue est rendu côté serveur par Twig (cartes .product-card avec des attributs data-*).
 * Ce script gère uniquement : le panier, les filtres, les modales, l'encaissement (AJAX)
 * et l'import / export JSON.
 */
(function ($) {
    'use strict';

    // ──────────────────────────────────────────────
    // CONFIG & STATE
    // ──────────────────────────────────────────────
    const $app = $('#app');

    // URLs, jeton CSRF et nom de l'évènement fournis par Twig (attributs data-*)
    const urls = {
        sale: $app.attr('data-sale-url'),
        history: $app.attr('data-history-url'),
        historyImport: $app.attr('data-history-import-url'),
        catalogImport: $app.attr('data-catalog-import-url'),
    };
    const csrfToken = $app.attr('data-csrf-token');
    const eventName = $app.attr('data-event-name');

    // Articles indexés par id, lus une seule fois depuis les cartes de la grille
    const products = {};

    // Panier : { productId: quantité }
    let cart = {};

    // Catégorie filtrée ('all' = toutes les catégories)
    let currentCategory = 'all';

    let toastTimer = null;

    // ──────────────────────────────────────────────
    // INIT
    // ──────────────────────────────────────────────
    $(function () {
        readProductsFromGrid();
        bindEvents();
        updateCart();
        updateClock();
        setInterval(updateClock, 1000);
    });

    function readProductsFromGrid() {
        $('.product-card').each(function () {
            const $card = $(this);
            const id = $card.attr('data-id');

            products[id] = {
                id: parseInt(id, 10),
                name: $card.attr('data-name'),
                emoji: $card.attr('data-emoji'),
                price: parseFloat($card.attr('data-price')),
            };
        });
    }

    function bindEvents() {
        // Grille et filtres
        $('#products-grid').on('click', '.product-card', function () {
            addToCart($(this).attr('data-id'));
        });
        $('#search').on('input', applyFilters);
        $('#category-filters').on('click', '.filter-btn', function () {
            currentCategory = $(this).attr('data-category');
            $('#category-filters .filter-btn').removeClass('active');
            $(this).addClass('active');
            applyFilters();
        });

        // Panier (boutons + et − générés dynamiquement : délégation d'évènement)
        $('#cart-items').on('click', '.qty-btn', function () {
            const id = $(this).closest('.cart-item').attr('data-id');

            if ($(this).attr('data-action') === 'add') {
                addToCart(id);
            } else {
                removeOneFromCart(id);
            }
        });

        // Actions de la barre latérale
        $('#btn-pay').on('click', openReceipt);
        $('#btn-clear').on('click', clearCart);
        $('#btn-history').on('click', openHistory);
        $('#btn-confirm-pay').on('click', confirmPayment);

        // Modales : bouton ✕ ou clic sur le fond sombre
        $('[data-close-modal]').on('click', function () {
            closeModal($(this).attr('data-close-modal'));
        });
        $('.modal-overlay').on('click', function (event) {
            if (event.target === this) {
                closeModal(this.id);
            }
        });

        // Import de fichiers JSON
        $('#btn-import-catalog').on('click', function () {
            $('#file-catalog-input').trigger('click');
        });
        $('#file-catalog-input').on('change', handleCatalogImport);
        $('#btn-import-history').on('click', function () {
            $('#file-history-input').trigger('click');
        });
        $('#file-history-input').on('change', handleHistoryImport);
    }

    // ──────────────────────────────────────────────
    // CLOCK
    // ──────────────────────────────────────────────
    function updateClock() {
        const now = new Date();

        $('#clock').text(
            now.toLocaleDateString('fr-FR') + ' · ' +
            now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
        );
    }

    // ──────────────────────────────────────────────
    // FILTERS (catégorie + recherche, tout se fait sur les cartes déjà présentes dans la page)
    // ──────────────────────────────────────────────
    function applyFilters() {
        const query = $('#search').val().trim().toLowerCase();
        let visibleCount = 0;

        $('.product-card').each(function () {
            const $card = $(this);
            const matchesCategory = currentCategory === 'all' || $card.attr('data-category') === currentCategory;
            const matchesQuery = $card.attr('data-search').includes(query);
            const isVisible = matchesCategory && matchesQuery;

            $card.toggle(isVisible);
            if (isVisible) {
                visibleCount++;
            }
        });

        $('#no-results').prop('hidden', visibleCount > 0);
    }

    // ──────────────────────────────────────────────
    // CART
    // ──────────────────────────────────────────────
    function addToCart(productId) {
        cart[productId] = (cart[productId] || 0) + 1;
        updateCart();
        bumpTotal();
    }

    function removeOneFromCart(productId) {
        if (!cart[productId]) {
            return;
        }

        cart[productId]--;
        if (cart[productId] <= 0) {
            delete cart[productId];
        }
        updateCart();
    }

    function clearCart() {
        cart = {};
        updateCart();
    }

    /** Petite animation du total à chaque ajout. */
    function bumpTotal() {
        const $total = $('#total-display');

        $total.removeClass('bump');
        void $total[0].offsetWidth; // force le navigateur à recalculer pour relancer l'animation
        $total.addClass('bump');
        setTimeout(function () {
            $total.removeClass('bump');
        }, 300);
    }

    /** @returns {{product: Object, quantity: number}[]} */
    function getCartLines() {
        return Object.keys(cart).map(function (id) {
            return { product: products[id], quantity: cart[id] };
        });
    }

    function getCartTotals(lines) {
        let count = 0;
        let price = 0;

        lines.forEach(function (line) {
            count += line.quantity;
            price += line.product.price * line.quantity;
        });

        return { count: count, price: price };
    }

    /** Redessine tout ce qui dépend du panier : total, liste, boutons, badges de la grille. */
    function updateCart() {
        const lines = getCartLines();
        const totals = getCartTotals(lines);
        const hasItems = lines.length > 0;

        // Total
        $('#total-display').text(formatPrice(totals.price));
        $('#item-count').text(totals.count + ' article' + (totals.count > 1 ? 's' : ''));

        // Boutons
        $('#btn-pay, #btn-clear').prop('disabled', !hasItems);

        // Liste du panier
        $('#cart-empty').css('display', hasItems ? 'none' : 'flex');
        const $list = $('#cart-items').empty();

        lines.forEach(function (line) {
            const $row = $(cloneTemplate('cart-item-template'));

            $row.attr('data-id', line.product.id);
            $row.find('.cart-item-emoji').text(line.product.emoji);
            $row.find('.cart-item-name').text(line.product.name);
            $row.find('.cart-item-price').text(formatPrice(line.product.price) + ' / u.');
            $row.find('.cart-qty').text(line.quantity);
            $row.find('.cart-item-total').text(formatPrice(line.product.price * line.quantity));

            $list.append($row);
        });

        // Badges de quantité sur les cartes de la grille
        $('.product-card').each(function () {
            const quantity = cart[$(this).attr('data-id')] || 0;

            $(this).toggleClass('in-cart', quantity > 0);
            $(this).find('.card-badge').text(quantity > 0 ? quantity : '');
        });
    }

    // ──────────────────────────────────────────────
    // RECEIPT MODAL + PAYMENT
    // ──────────────────────────────────────────────
    function openReceipt() {
        const lines = getCartLines();
        const $items = $('#receipt-items').empty();

        lines.forEach(function (line) {
            const $row = $(cloneTemplate('receipt-row-template'));

            $row.find('.rname').text(line.product.emoji + ' ' + line.product.name);
            $row.find('.rqty').text('×' + line.quantity);
            $row.find('.rprice').text(formatPrice(line.product.price * line.quantity));

            $items.append($row);
        });

        $('#receipt-total').text(formatPrice(getCartTotals(lines).price));
        $('#receipt-meta').text(eventName + ' · ' + new Date().toLocaleString('fr-FR'));

        openModal('modal-receipt');
    }

    /**
     * Envoie le panier au serveur.
     * Seuls les ids et les quantités partent : les prix sont recalculés côté serveur.
     */
    function confirmPayment() {
        const items = Object.keys(cart).map(function (id) {
            return { productId: parseInt(id, 10), quantity: cart[id] };
        });
        const $button = $('#btn-confirm-pay').prop('disabled', true); // évite le double clic

        $.ajax({
            url: urls.sale,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ items: items }),
            headers: { 'X-CSRF-Token': csrfToken },
            dataType: 'json',
        })
            .done(function () {
                closeModal('modal-receipt');
                showPaymentSuccess();
            })
            .fail(function (xhr) {
                showToast(getErrorMessage(xhr, 'Erreur : vente non enregistrée ✗'));
            })
            .always(function () {
                $button.prop('disabled', false);
            });
    }

    function showPaymentSuccess() {
        const $success = $('#pay-success').addClass('show');

        setTimeout(function () {
            $success.removeClass('show');
            clearCart();
            showToast('Vente enregistrée ✓');
        }, 1400);
    }

    // ──────────────────────────────────────────────
    // HISTORY MODAL
    // ──────────────────────────────────────────────
    /** Le tableau est généré par Twig (_history.html.twig) : on charge juste le fragment HTML. */
    function loadHistory() {
        $('#history-table-wrap').load(urls.history, function (response, status) {
            if (status === 'error') {
                showToast("Erreur : impossible de charger l'historique ✗");
            }
        });
    }

    function openHistory() {
        loadHistory();
        openModal('modal-history');
    }

    // ──────────────────────────────────────────────
    // IMPORT JSON
    // ──────────────────────────────────────────────
    function handleCatalogImport() {
        const file = this.files[0];
        if (!file) {
            return;
        }

        uploadJsonFile(file, urls.catalogImport, function (result) {
            showToast(result.imported + ' articles chargés ✓');
            // La grille est rendue par Twig : on recharge la page pour la mettre à jour
            setTimeout(function () {
                window.location.reload();
            }, 800);
        });

        this.value = ''; // permet de re-sélectionner le même fichier plus tard
    }

    function handleHistoryImport() {
        const file = this.files[0];
        if (!file) {
            return;
        }

        uploadJsonFile(file, urls.historyImport, function (result) {
            showToast(result.imported + ' paniers importés ✓');
            loadHistory();
        });

        this.value = '';
    }

    function uploadJsonFile(file, url, onSuccess) {
        const formData = new FormData();
        formData.append('file', file);

        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            processData: false, // FormData ne doit pas être transformé par jQuery
            contentType: false, // le navigateur ajoute lui-même le bon Content-Type
            headers: { 'X-CSRF-Token': csrfToken },
            dataType: 'json',
        })
            .done(onSuccess)
            .fail(function (xhr) {
                showToast(getErrorMessage(xhr, 'Erreur : JSON invalide ✗'));
            });
    }

    // ──────────────────────────────────────────────
    // MODALS
    // ──────────────────────────────────────────────
    function openModal(id) {
        $('#' + id).addClass('open');
    }

    function closeModal(id) {
        $('#' + id).removeClass('open');
    }

    // ──────────────────────────────────────────────
    // UTILS
    // ──────────────────────────────────────────────
    function formatPrice(amount) {
        return amount.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    }

    /** Retourne le HTML d'un <template> (nettoyé des espaces autour). */
    function cloneTemplate(templateId) {
        return $('#' + templateId).html().trim();
    }

    /** Message d'erreur renvoyé par le serveur ({"error": "..."}), sinon message par défaut. */
    function getErrorMessage(xhr, fallback) {
        return (xhr.responseJSON && xhr.responseJSON.error) || fallback;
    }

    function showToast(message) {
        const $toast = $('#toast').text(message).addClass('show');

        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            $toast.removeClass('show');
        }, 2500);
    }
})(jQuery);
