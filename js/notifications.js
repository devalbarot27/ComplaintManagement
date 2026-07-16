(function () {
  'use strict';

  function initNotifications() {
    var POLL_INTERVAL_MS = 45000;
    var DROPDOWN_LIMIT = 5;
    var PAGE_LIMIT = 20;

    var bellBtn = document.getElementById('notificationBellBtn');
    var menu = document.getElementById('notificationMenu');
    var badge = document.getElementById('notificationBadge');
    var listEl = document.getElementById('notificationDropdownList');
    var markAllBtn = document.getElementById('notificationMarkAllBtn');
    var pageListEl = document.getElementById('notificationsPageList');
    var pageLoadMoreBtn = document.getElementById('notificationsLoadMoreBtn');
    var pageMarkAllBtn = document.getElementById('notificationsPageMarkAllBtn');

    if (!bellBtn && !pageListEl) {
      return;
    }

    var pageOffset = 0;
    var pageHasMore = true;
    var pageLoading = false;

    function escapeHtml(value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function updateBadge(count) {
      if (!badge) {
        return;
      }
      var n = parseInt(count, 10) || 0;
      if (n > 0) {
        badge.textContent = n > 99 ? '99+' : String(n);
        badge.classList.add('is-visible');
        badge.setAttribute('aria-hidden', 'false');
      } else {
        badge.textContent = '';
        badge.classList.remove('is-visible');
        badge.setAttribute('aria-hidden', 'true');
      }

      if (markAllBtn) {
        markAllBtn.disabled = n <= 0;
      }
      if (pageMarkAllBtn) {
        pageMarkAllBtn.disabled = n <= 0;
      }
    }

    function renderItem(item) {
      var isUnread = !item.is_read;
      var href = item.redirect_url ? item.redirect_url : '#';
      var classes = 'notification-item' + (isUnread ? ' is-unread' : '');
      var actionLabel = item.redirect_url ? 'Action' : '';

      return (
        '<a href="' + escapeHtml(href) + '" class="' + classes + '" data-notification-id="' + escapeHtml(item.id) + '"' +
        (item.redirect_url ? '' : ' data-no-redirect="1"') + '>' +
        '<div class="notification-item-title">' +
        '<strong>' + escapeHtml(item.title) + '</strong>' +
        '<span class="notification-dot" aria-hidden="true"></span>' +
        '</div>' +
        '<p class="notification-item-message">' + escapeHtml(item.message) + '</p>' +
        '<div class="notification-item-meta">' +
        '<span class="notification-item-time">' + escapeHtml(item.relative_time || '') + '</span>' +
        (actionLabel
          ? '<span class="notification-action">' + escapeHtml(actionLabel) + ' <i class="bi bi-arrow-right"></i></span>'
          : '') +
        '</div>' +
        '</a>'
      );
    }

    function renderList(container, items, emptyText) {
      if (!container) {
        return;
      }
      if (!items || !items.length) {
        container.innerHTML = '<div class="notification-empty">' + escapeHtml(emptyText || 'No notifications') + '</div>';
        return;
      }
      container.innerHTML = items.map(renderItem).join('');
    }

    function fetchJson(url, options) {
      return fetch(url, options || {}).then(function (response) {
        return response.json().then(function (data) {
          if (!response.ok) {
            throw new Error((data && data.error) || 'Request failed');
          }
          return data;
        });
      });
    }

    function refreshUnreadCount() {
      return fetchJson('api/notifications_unread_count.php').then(function (data) {
        updateBadge(data.unread_count || 0);
        return data;
      }).catch(function () {
        /* silent poll failure */
      });
    }

    function loadDropdown() {
      if (!listEl) {
        return Promise.resolve();
      }
      listEl.innerHTML = '<div class="notification-loading">Loading...</div>';
      return fetchJson('api/notifications_list.php?limit=' + DROPDOWN_LIMIT + '&offset=0')
        .then(function (data) {
          updateBadge(data.unread_count || 0);
          renderList(listEl, data.items || [], 'No notifications yet');
        })
        .catch(function () {
          listEl.innerHTML = '<div class="notification-empty">Unable to load notifications</div>';
        });
    }

    function loadPage(reset) {
      if (!pageListEl || pageLoading) {
        return;
      }
      if (reset) {
        pageOffset = 0;
        pageHasMore = true;
        pageListEl.innerHTML = '<div class="notification-loading">Loading...</div>';
      }
      if (!pageHasMore && !reset) {
        return;
      }

      pageLoading = true;
      if (pageLoadMoreBtn) {
        pageLoadMoreBtn.disabled = true;
        pageLoadMoreBtn.textContent = 'Loading...';
      }

      fetchJson('api/notifications_list.php?limit=' + PAGE_LIMIT + '&offset=' + pageOffset)
        .then(function (data) {
          updateBadge(data.unread_count || 0);
          var items = data.items || [];
          if (reset) {
            renderList(pageListEl, items, 'No notifications yet');
          } else if (items.length) {
            pageListEl.insertAdjacentHTML('beforeend', items.map(renderItem).join(''));
          }
          pageOffset += items.length;
          pageHasMore = !!data.has_more;
          if (pageLoadMoreBtn) {
            pageLoadMoreBtn.style.display = pageHasMore ? '' : 'none';
            pageLoadMoreBtn.disabled = !pageHasMore;
            pageLoadMoreBtn.textContent = 'Load More';
          }
        })
        .catch(function () {
          if (reset) {
            pageListEl.innerHTML = '<div class="notification-empty">Unable to load notifications</div>';
          }
          if (pageLoadMoreBtn) {
            pageLoadMoreBtn.disabled = false;
            pageLoadMoreBtn.textContent = 'Load More';
          }
        })
        .finally(function () {
          pageLoading = false;
        });
    }

    function markRead(id) {
      return fetchJson('api/notifications_mark_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
      }).then(function (data) {
        updateBadge(data.unread_count || 0);
        return data;
      });
    }

    function markAllRead() {
      return fetchJson('api/notifications_mark_all_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: '{}'
      }).then(function (data) {
        updateBadge(0);
        document.querySelectorAll('.notification-item.is-unread').forEach(function (el) {
          el.classList.remove('is-unread');
        });
        return data;
      });
    }

    function onNotificationClick(event) {
      var item = event.target.closest('.notification-item');
      if (!item) {
        return;
      }

      var id = parseInt(item.getAttribute('data-notification-id'), 10) || 0;
      var noRedirect = item.getAttribute('data-no-redirect') === '1';

      if (id <= 0) {
        return;
      }

      if (noRedirect) {
        event.preventDefault();
      }

      markRead(id).then(function () {
        item.classList.remove('is-unread');
      }).catch(function () {
        /* allow navigation even if mark-read fails */
      });
    }

    if (bellBtn && menu) {
      bellBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        var profileMenu = document.getElementById('profileMenu');
        if (profileMenu) {
          profileMenu.classList.remove('show');
        }
        var willOpen = !menu.classList.contains('show');
        menu.classList.toggle('show');
        bellBtn.classList.toggle('is-open', willOpen);
        if (willOpen) {
          loadDropdown();
        }
      });

      menu.addEventListener('click', function (e) {
        e.stopPropagation();
      });

      document.addEventListener('click', function () {
        menu.classList.remove('show');
        bellBtn.classList.remove('is-open');
      });
    }

    if (listEl) {
      listEl.addEventListener('click', onNotificationClick);
    }
    if (pageListEl) {
      pageListEl.addEventListener('click', onNotificationClick);
    }

    if (markAllBtn) {
      markAllBtn.addEventListener('click', function (e) {
        e.preventDefault();
        markAllRead().then(function () {
          loadDropdown();
        });
      });
    }

    if (pageMarkAllBtn) {
      pageMarkAllBtn.addEventListener('click', function (e) {
        e.preventDefault();
        markAllRead().then(function () {
          loadPage(true);
        });
      });
    }

    if (pageLoadMoreBtn) {
      pageLoadMoreBtn.addEventListener('click', function () {
        loadPage(false);
      });
    }

    refreshUnreadCount();
    if (pageListEl) {
      loadPage(true);
    }

    setInterval(function () {
      refreshUnreadCount();
      if (menu && menu.classList.contains('show')) {
        loadDropdown();
      }
    }, POLL_INTERVAL_MS);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNotifications);
  } else {
    initNotifications();
  }
})();