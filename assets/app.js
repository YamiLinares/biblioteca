(() => {
    'use strict';

    const sidebar = document.querySelector('#sidebar');
    const menuButton = document.querySelector('[data-menu-toggle]');
    const setMenuOpen = (open) => {
        sidebar?.classList.toggle('is-open', open);
        document.body.classList.toggle('menu-open', open);
        menuButton?.setAttribute('aria-expanded', String(open));
    };

    menuButton?.addEventListener('click', () => {
        setMenuOpen(!sidebar?.classList.contains('is-open'));
    });
    document.querySelector('.main-area')?.addEventListener('click', () => {
        if (sidebar?.classList.contains('is-open')) setMenuOpen(false);
    });

    document.querySelectorAll('[data-open-dialog]').forEach((button) => {
        button.addEventListener('click', () => {
            document.getElementById(button.dataset.openDialog)?.showModal();
        });
    });
    document.querySelectorAll('dialog.modal').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    });

    document.querySelectorAll('dialog.modal[open]').forEach((dialog) => {
        dialog.removeAttribute('open');
        dialog.showModal();
    });

    document.querySelectorAll('[data-close-dialog]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });

    document.querySelectorAll('[data-dismiss]').forEach((button) => {
        button.addEventListener('click', () => button.closest('.toast')?.remove());
    });

    const confirmation = document.querySelector('#confirmDialog');
    const acceptConfirmation = confirmation?.querySelector('[data-accept-confirm]');
    let pendingForm = null;

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) return;
        if (form.dataset.confirmed === 'true') {
            delete form.dataset.confirmed;
            return;
        }
        event.preventDefault();
        pendingForm = form;
        const title = confirmation?.querySelector('#confirmTitle');
        const detail = confirmation?.querySelector('#confirmDetail');
        if (title) title.textContent = form.dataset.confirm;
        if (detail) detail.textContent = form.dataset.confirmDetail || 'Esta acción se aplicará al registro seleccionado.';
        confirmation?.showModal();
    });

    acceptConfirmation?.addEventListener('click', () => {
        if (!pendingForm) return;
        confirmation.close();
        pendingForm.dataset.confirmed = 'true';
        pendingForm.requestSubmit();
        pendingForm = null;
    });

    confirmation?.querySelector('[data-cancel-confirm]')?.addEventListener('click', () => {
        confirmation.close();
        pendingForm = null;
    });

    document.querySelectorAll('[data-table-search]').forEach((input) => {
        const updateResults = () => {
            const target = document.getElementById(input.dataset.tableSearch);
            if (!target) return;
            const query = input.value.trim().toLocaleLowerCase('es');
            const rows = target.querySelectorAll('[data-searchable]');
            let visible = 0;
            rows.forEach((row) => {
                const matches = row.dataset.searchable.toLocaleLowerCase('es').includes(query);
                row.hidden = !matches;
                if (matches) visible += 1;
            });
            const empty = document.querySelector('[data-empty-search="' + CSS.escape(input.dataset.tableSearch) + '"]');
            if (empty) empty.hidden = visible !== 0 || query === '';
        };
        input.addEventListener('input', updateResults);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar?.classList.contains('is-open')) setMenuOpen(false);
    });
})();
