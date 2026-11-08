// Menu page: loads items from the database and handles search + category filter.

let menuItems = [];
let activeCategory = 'All';

function renderCategories() {
  const categories = ['All', ...new Set(menuItems.map(item => item.category))];
  const container = document.getElementById('category-filter');
  container.innerHTML = categories
    .map(cat => `<button class="category-btn${cat === activeCategory ? ' active' : ''}" data-category="${escapeHtml(cat)}">${escapeHtml(cat)}</button>`)
    .join('');
}

function renderMenu() {
  const query = document.getElementById('menu-search').value.trim().toLowerCase();
  const visible = menuItems.filter(item =>
    (activeCategory === 'All' || item.category === activeCategory) &&
    (item.name.toLowerCase().includes(query) || item.description.toLowerCase().includes(query))
  );

  document.getElementById('menu-result-count').textContent =
    `${visible.length} item${visible.length === 1 ? '' : 's'} found`;

  const grid = document.getElementById('menu-grid');
  if (visible.length === 0) {
    grid.innerHTML = '<div class="menu-empty"><p>No items match your search.</p></div>';
    return;
  }

  grid.innerHTML = visible.map(item => `
    <div class="menu-item">
      <div class="menu-item-image-wrapper">
        <img class="menu-item-image" src="${escapeHtml(imageUrl(item.image))}" alt="${escapeHtml(item.name)}" loading="lazy">
      </div>
      <div class="menu-item-content">
        <div class="menu-item-name">${escapeHtml(item.name)}</div>
        <div class="menu-item-description">${escapeHtml(item.description)}</div>
        <div class="menu-item-footer">
          <span class="menu-item-price">${item.price.toFixed(2)}</span>
          <button class="btn btn-primary btn-add" data-id="${escapeHtml(item.id)}">Add</button>
        </div>
      </div>
    </div>`).join('');
}

document.addEventListener('DOMContentLoaded', async () => {
  const grid = document.getElementById('menu-grid');
  const data = await api('menu_items.php');
  if (!data.success) {
    grid.innerHTML = `<div class="menu-empty"><p>${escapeHtml(data.error || 'Could not load the menu.')}</p></div>`;
    document.getElementById('menu-result-count').textContent = '';
    return;
  }

  menuItems = data.items;
  renderCategories();
  renderMenu();

  await sessionReady;
  syncCartWithMenu(menuItems);

  grid.addEventListener('click', e => {
    const btn = e.target.closest('.btn-add');
    if (!btn) return;
    const item = menuItems.find(i => i.id === btn.dataset.id);
    if (!item) return;
    if (!addToCart(item)) {
      showToast(`You can order at most ${MAX_QUANTITY} of ${item.name}`);
      return;
    }
    showToast(`${item.name} added to cart`);
    btn.classList.add('btn-added');
    btn.textContent = 'Added';
    setTimeout(() => {
      btn.classList.remove('btn-added');
      btn.textContent = 'Add';
    }, 1000);
  });

  document.getElementById('menu-search').addEventListener('input', renderMenu);
  document.getElementById('category-filter').addEventListener('click', e => {
    const btn = e.target.closest('.category-btn');
    if (!btn) return;
    activeCategory = btn.dataset.category;
    renderCategories();
    renderMenu();
  });
});
