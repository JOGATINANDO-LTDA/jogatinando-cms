/**
 * Jogatinando CMS — Admin JavaScript
 */
document.addEventListener('DOMContentLoaded', function() {
  var sidebar = document.getElementById('adminSidebar');
  var overlay = document.getElementById('sidebarOverlay');
  var toggle = document.getElementById('sidebarToggle');

  function openSidebar() {
    if (!sidebar || !overlay) return;
    sidebar.classList.add('open');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    if (!sidebar || !overlay) return;
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  if (toggle) toggle.addEventListener('click', openSidebar);
  if (overlay) overlay.addEventListener('click', closeSidebar);

  // Close sidebar on Escape
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
      closeSidebar();
    }
  });

  // Confirm delete actions
  document.querySelectorAll('[data-confirm]').forEach(function(el) {
    el.addEventListener('click', function(e) {
      if (!confirm(this.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });

  // Auto-hide flash messages after 5s
  var flashes = document.querySelectorAll('.flash');
  flashes.forEach(function(flash) {
    setTimeout(function() {
      flash.style.transition = 'opacity 0.5s';
      flash.style.opacity = '0';
      setTimeout(function() { flash.remove(); }, 500);
    }, 5000);
  });

  // File upload preview
  document.querySelectorAll('.file-upload input[type="file"]').forEach(function(input) {
    input.addEventListener('change', function() {
      var container = this.closest('.file-upload');
      var preview = container.querySelector('.preview-img');
      if (this.files && this.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
          if (preview) {
            preview.src = e.target.result;
            preview.style.display = 'block';
          } else {
            var img = document.createElement('img');
            img.className = 'preview-img';
            img.src = e.target.result;
            container.appendChild(img);
          }
        };
        reader.readAsDataURL(this.files[0]);
        var textEl = container.querySelector('.upload-text');
        if (textEl) textEl.textContent = this.files[0].name;
      }
    });
  });

  // Slug generator from title
  var titleInput = document.getElementById('title');
  var slugInput = document.getElementById('slug');
  if (titleInput && slugInput) {
    titleInput.addEventListener('input', function() {
      if (!slugInput.dataset.manual) {
        slugInput.value = this.value
          .toLowerCase()
          .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
          .replace(/[^a-z0-9]+/g, '-')
          .replace(/^-+|-+$/g, '');
      }
    });
    slugInput.addEventListener('input', function() {
      this.dataset.manual = '1';
    });
  }

  // Sortable table rows (drag handle)
  var dragItem = null;
  document.querySelectorAll('[data-sortable]').forEach(function(list) {
    list.querySelectorAll('.drag-handle').forEach(function(handle) {
      handle.addEventListener('mousedown', function(e) {
        dragItem = this.closest('tr, .sortable-item');
        if (dragItem) dragItem.style.opacity = '0.5';
      });
    });
  });

  // Emoji Picker
  var EMOJIS = {
    'Games': ['🎮','🕹️','🎲','🎯','🏆','⚔️','🛡️','🔫','💣','🗡️','🏹','🔮','👾','🤖','🧩','🎰'],
    'Tech': ['💻','🖥️','⌨️','🖱️','📡','🔌','💾','📀','📱','📟','📠','🔋','🛰️','🚀','🛸','💿'],
    'Web': ['🌐','🔗','📦','🔥','✨','💎','🎨','🎬','🎵','🎶','🔊','📸','🎥','💡','⚙️','🔧'],
    'Store': ['🛒','💰','💳','🏷️','🎁','🏪','🏬','📮','📬','📫','📰','📚','📖','🔖','📑','🎫'],
    'People': ['👤','👥','🧑‍💻','👨‍💻','👩‍💻','🧑‍🎨','👨‍🎨','👩‍🎨','🎩','👑','🎖️','🏅','🥇','🥈','🥉','🎯'],
    'Symbols': ['✅','❌','⚠️','❗','❓','💯','🔴','🟢','🔵','🟡','🟣','⚫','⚪','➕','➖','💲']
  };

  function createEmojiPicker(inputId) {
    var input = document.getElementById(inputId);
    if (!input) return;

    var currentValue = input.value || '🎮';

    // Hide original input
    input.type = 'hidden';

    // Create wrapper
    var wrap = document.createElement('div');
    wrap.className = 'emoji-picker-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    // Create visible button
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'emoji-picker-btn';
    btn.textContent = currentValue;
    btn.title = 'Clique para selecionar emoji';
    wrap.appendChild(btn);

    // Create popup
    var popup = document.createElement('div');
    popup.className = 'emoji-picker-popup';
    wrap.appendChild(popup);

    // Search
    var searchWrap = document.createElement('div');
    searchWrap.className = 'emoji-picker-search';
    var searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = 'Buscar emoji...';
    searchWrap.appendChild(searchInput);
    popup.appendChild(searchWrap);

    // Grid
    var grid = document.createElement('div');
    grid.className = 'emoji-picker-grid';
    popup.appendChild(grid);

    function renderEmojis(filter) {
      grid.innerHTML = '';
      var lower = (filter || '').toLowerCase();
      Object.keys(EMOJIS).forEach(function(cat) {
        EMOJIS[cat].forEach(function(em) {
          if (lower && cat.toLowerCase().indexOf(lower) === -1 && em.indexOf(lower) === -1) return;
          var b = document.createElement('button');
          b.type = 'button';
          b.textContent = em;
          b.title = em;
          if (input.value === em) b.className = 'selected';
          b.addEventListener('click', function(e) {
            e.preventDefault();
            input.value = em;
            btn.textContent = em;
            grid.querySelectorAll('button').forEach(function(x) { x.className = ''; });
            b.className = 'selected';
            popup.classList.remove('open');
          });
          grid.appendChild(b);
        });
      });
    }

    renderEmojis('');
    searchInput.addEventListener('input', function() {
      renderEmojis(this.value);
    });

    btn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      var isOpen = popup.classList.contains('open');
      document.querySelectorAll('.emoji-picker-popup.open').forEach(function(p) { p.classList.remove('open'); });
      if (!isOpen) {
        popup.classList.add('open');
        searchInput.value = '';
        renderEmojis('');
        searchInput.focus();
      }
    });
  }

  // Initialize emoji pickers for icon fields
  ['icon'].forEach(function(id) {
    createEmojiPicker(id);
  });

  // Close emoji pickers on outside click
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.emoji-picker-wrap')) {
      document.querySelectorAll('.emoji-picker-popup.open').forEach(function(p) {
        p.classList.remove('open');
      });
    }
  });
});
