document.addEventListener('DOMContentLoaded', () => {
  const menu = document.querySelector('[data-menu]');
  const sidebar = document.querySelector('.sidebar');
  const adminNavigation = document.querySelector('.admin-topnav');
  const overlay = document.querySelector('.sidebar-overlay');
  menu?.addEventListener('click', () => (adminNavigation || sidebar)?.classList.toggle('is-open'));
  overlay?.addEventListener('click', () => sidebar?.classList.remove('is-open'));

  document.querySelectorAll('[data-nav-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const group = button.closest('.nav-group');
      const isOpen = group?.classList.toggle('is-open') ?? false;
      button.setAttribute('aria-expanded', String(isOpen));
      if (isOpen) window.requestAnimationFrame(() => group?.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
    });
  });

  document.querySelectorAll('.admin-nav-trigger').forEach((button) => {
    button.addEventListener('click', (event) => {
      event.stopPropagation();
      const group = button.closest('.admin-nav-group');
      const wasOpen = group?.classList.contains('is-open') ?? false;
      document.querySelectorAll('.admin-nav-group.is-open').forEach((openGroup) => {
        openGroup.classList.remove('is-open');
        openGroup.querySelector('.admin-nav-trigger')?.setAttribute('aria-expanded', 'false');
      });
      if (!wasOpen && group) {
        group.classList.add('is-open');
        button.setAttribute('aria-expanded', 'true');
      }
    });
  });

  document.addEventListener('click', () => {
    document.querySelectorAll('.admin-nav-group.is-open').forEach((group) => {
      group.classList.remove('is-open');
      group.querySelector('.admin-nav-trigger')?.setAttribute('aria-expanded', 'false');
    });
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') document.querySelectorAll('.admin-nav-group.is-open').forEach((group) => {
      group.classList.remove('is-open');
      group.querySelector('.admin-nav-trigger')?.setAttribute('aria-expanded', 'false');
    });
  });

  document.querySelectorAll('.nav-group').forEach((group) => {
    group.addEventListener('mouseenter', () => {
      window.requestAnimationFrame(() => group.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
    });
  });

  document.querySelectorAll('[data-confirm]').forEach((element) => {
    element.addEventListener('click', (event) => {
      if (!window.confirm(element.dataset.confirm)) event.preventDefault();
    });
  });

  document.querySelectorAll('[data-select-all]').forEach((selectAll) => {
    selectAll.addEventListener('change', () => {
      document.querySelectorAll(`[data-select-item="${selectAll.dataset.selectAll}"]`).forEach((item) => {
        item.checked = selectAll.checked;
      });
    });
  });

  document.querySelectorAll('[data-modal-open]').forEach((button) => {
    button.addEventListener('click', () => document.getElementById(button.dataset.modalOpen)?.showModal());
  });
  document.querySelectorAll('dialog [data-modal-close]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
  });
});
