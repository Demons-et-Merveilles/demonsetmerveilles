/**
 * Tables de JDR – Démons & Merveilles (front-end jQuery).
 *
 * Glisser-déposer des MJ et des joueurs sur des tables.
 *
 * Le serveur (Symfony / Doctrine) est la source de vérité : chaque action est envoyée en AJAX,
 * le serveur répond avec l'état complet du plateau, et l'interface est redessinée à partir de cet état.
 * (L'ancienne version enregistrait tout dans IndexedDB, sur l'appareil.)
 */
$(function () {
    'use strict';

    // ──────────────────────────────────────────────
    // CONFIG & STATE
    // ──────────────────────────────────────────────
    const $board = $('#board');

    // URLs de l'API, jeton CSRF et état initial fournis par Twig (attributs data-*)
    const urls = JSON.parse($board.attr('data-urls'));
    const csrfToken = $board.attr('data-csrf-token');

    // state = {
    //   maxPlayers,
    //   gms:     [{ id, name }],
    //   players: [{ id, name }],
    //   tables:  [{ id, name, gm: id|null, players: [id] }]
    // }
    let state = JSON.parse($board.attr('data-state'));

    let drag = null;        // glisser-déposer en cours
    let toastTimer = null;

    // ──────────────────────────────────────────────
    // API (toutes les actions passent par sendAction)
    // ──────────────────────────────────────────────

    /**
     * Envoie une action au serveur, puis redessine le plateau avec l'état renvoyé.
     * Retourne la promesse jQuery pour permettre d'enchaîner un .done() si besoin.
     */
    function sendAction(url, data) {
        return $.ajax({
            url: url,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data || {}),
            headers: { 'X-CSRF-Token': csrfToken },
            dataType: 'json',
        })
            .done(function (response) {
                state = response.state;
                render();
            })
            .fail(function (xhr) {
                const response = xhr.responseJSON || {};

                showToast(response.error || 'Erreur réseau : action non enregistrée.');

                // On se recale sur le serveur pour ne pas afficher un état faux
                if (response.state) {
                    state = response.state;
                    render();
                } else {
                    reloadState();
                }
            });
    }

    function reloadState() {
        $.getJSON(urls.state).done(function (response) {
            state = response.state;
            render();
        });
    }

    // ──────────────────────────────────────────────
    // RECHERCHES DANS L'ÉTAT
    // ──────────────────────────────────────────────
    function getList(kind) {
        if (kind === 'gm') return state.gms;
        if (kind === 'player') return state.players;
        return state.tables;
    }

    function getItem(kind, id) {
        return getList(kind).find(function (item) { return item.id === id; });
    }

    /** Un MJ / un joueur est « placé » s'il est sur une table (sinon il est dans sa liste). */
    function isAssigned(kind, id) {
        return state.tables.some(function (table) {
            return kind === 'gm' ? table.gm === id : table.players.includes(id);
        });
    }

    // ──────────────────────────────────────────────
    // CONSTRUCTION DE L'INTERFACE
    // ──────────────────────────────────────────────

    /** Pastille (MJ ou joueur) avec ses boutons ✎ et ×. */
    function buildChip(kind, item) {
        return $('<div class="chip">').addClass(kind).attr({ 'data-kind': kind, 'data-id': item.id })
            .append($('<span class="name">').text(item.name))
            .append($('<button type="button" class="tool edit" title="Renommer">').text('✎'))
            .append($('<button type="button" class="tool del" title="Supprimer">').text('×'));
    }

    function renderPool($pool, kind, emptyText) {
        $pool.empty();

        getList(kind).forEach(function (item) {
            if (!isAssigned(kind, item.id)) {
                $pool.append(buildChip(kind, item));
            }
        });

        if (!$pool.children().length) {
            $pool.append($('<div class="empty-msg">').text(emptyText));
        }
    }

    function buildTable(table) {
        const maxPlayers = state.maxPlayers;
        const $card = $('<div class="table-card">');

        // En-tête : nom de la table, renommer, supprimer
        $card.append(
            $('<div class="table-head" data-kind="table">').attr('data-id', table.id)
                .append($('<span class="name">').text(table.name))
                .append($('<button type="button" class="tool edit" title="Renommer la table">').text('✎'))
                .append($('<button type="button" class="tool del" title="Supprimer la table">').text('×'))
        );

        // Emplacement du MJ (un seul)
        const $gmZone = $('<div class="zone gm-zone" data-zone="table-gm" data-accepts="gm">').attr('data-table', table.id);
        const gm = table.gm && getItem('gm', table.gm);
        $gmZone.append(gm ? buildChip('gm', gm) : $('<div class="empty-msg">').text('Dépose un MJ ici'));
        $card.append($('<div class="label">').text('Maître de jeu'), $gmZone);

        // Emplacements des joueurs (maxPlayers)
        const $playersZone = $('<div class="zone players-zone" data-zone="table-players" data-accepts="player">').attr('data-table', table.id);
        table.players.forEach(function (playerId) {
            const player = getItem('player', playerId);
            if (player) {
                $playersZone.append(buildChip('player', player));
            }
        });
        for (let i = table.players.length; i < maxPlayers; i++) {
            $playersZone.append($('<div class="slot-empty">').text('place libre'));
        }

        const $count = $('<span class="count">')
            .text(table.players.length + ' / ' + maxPlayers)
            .toggleClass('full', table.players.length >= maxPlayers);
        $card.append($('<div class="label">').append($('<span>').text('Joueurs'), $count), $playersZone);

        return $card;
    }

    /** Redessine tout à partir de l'état (l'état est la seule source de vérité). */
    function render() {
        renderPool($('#poolGms'), 'gm', 'Tous les MJ sont placés');
        renderPool($('#poolPlayers'), 'player', 'Ajoute des joueurs ci-dessus');

        const $tables = $('#tables').empty();
        state.tables.forEach(function (table) {
            $tables.append(buildTable(table));
        });
    }

    // ──────────────────────────────────────────────
    // GLISSER-DÉPOSER (souris ET tactile)
    // ──────────────────────────────────────────────

    /** Zone de dépôt sous le pointeur, si elle accepte ce type de pastille. */
    function zoneUnder(x, y, kind) {
        const $zone = $(document.elementFromPoint(x, y)).closest('.zone');

        return ($zone.length && $zone.data('accepts') === kind) ? $zone : $();
    }

    $(document).on('pointerdown', '.chip', function (e) {
        if (e.button > 0 || $(e.target).closest('button, input').length) {
            return;
        }

        const ev = e.originalEvent;
        drag = { $chip: $(this), x: ev.clientX, y: ev.clientY, active: false, $ghost: null, $over: $() };
    });

    $(document).on('pointermove', function (e) {
        if (!drag) {
            return;
        }
        const ev = e.originalEvent;

        // On attend un petit mouvement avant de démarrer (pour ne pas bloquer les clics)
        if (!drag.active) {
            if (Math.hypot(ev.clientX - drag.x, ev.clientY - drag.y) < 6) {
                return;
            }
            drag.active = true;
            drag.offsetX = drag.$chip.outerWidth() / 2;
            drag.offsetY = drag.$chip.outerHeight() / 2;
            drag.$ghost = drag.$chip.clone().addClass('ghost').width(drag.$chip.outerWidth()).appendTo('body');
            drag.$chip.addClass('is-source');
            $('body').addClass('dragging');
        }

        // La copie suit le doigt / la souris
        drag.$ghost.css({ left: ev.clientX - drag.offsetX, top: ev.clientY - drag.offsetY });

        // Surbrillance de la zone visée
        const $zone = zoneUnder(ev.clientX, ev.clientY, drag.$chip.data('kind'));
        if (!$zone.is(drag.$over)) {
            drag.$over.removeClass('hover');
            drag.$over = $zone.addClass('hover');
        }
    });

    function endDrag(e) {
        if (!drag) {
            return;
        }

        const current = drag;
        drag = null;
        if (!current.active) {
            return;
        }

        current.$ghost.remove();
        $('body').removeClass('dragging');
        current.$over.removeClass('hover');
        current.$chip.removeClass('is-source');

        const ev = e.originalEvent;
        const $zone = zoneUnder(ev.clientX, ev.clientY, current.$chip.data('kind'));
        if (!$zone.length) {
            return; // lâché dans le vide : rien ne bouge
        }

        const tableId = $zone.data('table') || null; // undefined pour les listes MJ / joueurs

        // Le serveur applique les règles (table pleine, etc.) et renvoie le nouvel état
        sendAction(urls.move, {
            kind: current.$chip.data('kind'),
            id: current.$chip.data('id'),
            zone: $zone.data('zone'),
            table: tableId,
        }).done(function (response) {
            if (response.ok === false && tableId) {
                shakeTable(tableId); // table pleine : petite secousse pour prévenir
            }
        });
    }
    $(document).on('pointerup pointercancel', endDrag);

    function shakeTable(tableId) {
        const $full = $('.players-zone[data-table="' + tableId + '"]').addClass('shake');

        setTimeout(function () { $full.removeClass('shake'); }, 450);
    }

    // ──────────────────────────────────────────────
    // RENOMMER (clic sur ✎ ou double-clic sur le nom)
    // ──────────────────────────────────────────────
    function startRename($host) {
        const kind = $host.data('kind');
        const id = $host.data('id');
        const item = getItem(kind, id);
        const $name = $host.find('.name').first();
        const $input = $('<input type="text" class="rename-input" maxlength="30">').val(item.name);

        $name.replaceWith($input);
        $input.trigger('focus').trigger('select');

        let done = false;
        function finish(save) {
            if (done) {
                return;
            }
            done = true;

            const value = $input.val().trim();
            if (save && value && value !== item.name) {
                sendAction(urls.rename, { kind: kind, id: id, name: value });
            } else {
                render(); // rien à enregistrer : on remet simplement le nom d'origine
            }
        }

        $input.on('keydown', function (e) {
            if (e.key === 'Enter') finish(true);
            if (e.key === 'Escape') finish(false);
        }).on('blur', function () {
            finish(true);
        });
    }

    $(document).on('click', '.edit', function () {
        startRename($(this).closest('[data-id]'));
    });
    $(document).on('dblclick', '.name', function () {
        startRename($(this).closest('[data-id]'));
    });

    // ──────────────────────────────────────────────
    // SUPPRESSIONS
    // ──────────────────────────────────────────────
    $(document).on('click', '.del', function () {
        const $host = $(this).closest('[data-id]');

        sendAction(urls.delete, { kind: $host.data('kind'), id: $host.data('id') });
    });

    // ──────────────────────────────────────────────
    // AJOUTS
    // ──────────────────────────────────────────────
    function addPlayers() {
        const $input = $('#newPlayer');
        const names = $input.val();

        if (!names.trim()) {
            return;
        }

        // Le serveur sépare les noms (virgule, point-virgule, retour à la ligne)
        sendAction(urls.addPlayers, { names: names }).done(function () {
            $input.val('').trigger('focus');
        });
    }

    $('#addPlayer').on('click', addPlayers);
    $('#newPlayer').on('keydown', function (e) {
        if (e.key === 'Enter') addPlayers();
    });

    $('#addGm').on('click', function () {
        sendAction(urls.addGameMaster);
    });
    $('#addTable').on('click', function () {
        sendAction(urls.addTable);
    });

    // Remet tout le monde dans les listes (sans rien supprimer)
    $('#clearTables').on('click', function () {
        sendAction(urls.clear);
    });

    // ──────────────────────────────────────────────
    // MESSAGES
    // ──────────────────────────────────────────────
    function showToast(message) {
        const $toast = $('#toast').text(message).addClass('show');

        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            $toast.removeClass('show');
        }, 3000);
    }

    // ──────────────────────────────────────────────
    // DÉMARRAGE
    // ──────────────────────────────────────────────
    render();
});
