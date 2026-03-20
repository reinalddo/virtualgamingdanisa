<?php
require_once __DIR__ . '/store_config.php';

$facebookUrl = store_config_normalize_social_url(store_config_get('facebook', ''));
$instagramUrl = store_config_normalize_social_url(store_config_get('instagram', ''));
$whatsappValue = store_config_get('whatsapp', '');
$whatsappMessage = store_config_get('mensaje_whatsapp', '');
$whatsappUrl = store_config_whatsapp_link_with_message($whatsappValue, $whatsappMessage);
$whatsappChannelUrl = store_config_normalize_social_url(store_config_get('whatsapp_channel', ''));

$hasFacebook = store_config_is_valid_social_url($facebookUrl);
$hasInstagram = store_config_is_valid_social_url($instagramUrl);
$hasWhatsapp = $whatsappUrl !== '';
$hasWhatsappChannel = store_config_is_valid_social_url($whatsappChannelUrl);

$menuScript = <<<'SCRIPT'
<script>
  const menuToggle = document.getElementById("menu-toggle");
  const menuOverlay = document.getElementById("menu-overlay");
  const menuPanel = document.getElementById("menu-panel");
  const menuClose = document.getElementById("menu-close");
  const authContainer = document.getElementById("auth-container");
  const authTrigger = document.getElementById("auth-trigger");
  const authMenu = document.getElementById("auth-menu");
  const userTrigger = document.getElementById("user-trigger");
  const userMenu = document.getElementById("user-menu");
  const authModal = document.getElementById("auth-modal");
  const authLogin = document.getElementById("auth-login");
  const authRegister = document.getElementById("auth-register");
  const passwordToggles = document.querySelectorAll("[data-password-toggle]");
  const userOrdersModal = document.getElementById("user-orders-modal");
  const userProfileModal = document.getElementById("user-profile-modal");
  const userOrdersList = document.getElementById("user-orders-list");
  const userOrdersTableBody = document.getElementById("user-orders-table-body");
  const userOrdersCards = document.getElementById("user-orders-cards");
  const userOrdersLoading = document.getElementById("user-orders-loading");
  const userOrdersEmpty = document.getElementById("user-orders-empty");
  const userOrdersFeedback = document.getElementById("user-orders-feedback");
  const userProfileForm = document.getElementById("user-profile-form");
  const userProfileFeedback = document.getElementById("user-profile-feedback");
  const userTriggerName = document.getElementById("user-trigger-name");
  const userTriggerInitials = document.getElementById("user-trigger-initials");
  const userMenuName = document.getElementById("user-menu-name");
  const userMenuEmail = document.getElementById("user-menu-email");

  const showElement = (element, displayClass) => {
    if (!element) {
      return;
    }
    element.classList.remove("d-none");
    if (displayClass) {
      element.classList.add(displayClass);
    }
  };

  const hideElement = (element, displayClass) => {
    if (!element) {
      return;
    }
    element.classList.add("d-none");
    if (displayClass) {
      element.classList.remove(displayClass);
    }
  };

  const openMenu = () => {
    showElement(menuOverlay);
    showElement(menuPanel);
  };

  const closeMenu = () => {
    hideElement(menuOverlay);
    hideElement(menuPanel);
  };

  if (menuToggle) {
    menuToggle.addEventListener("click", openMenu);
  }
  if (menuClose) {
    menuClose.addEventListener("click", closeMenu);
  }
  if (menuOverlay) {
    menuOverlay.addEventListener("click", closeMenu);
  }

  const showAuthMenu = () => {
    showElement(authMenu);
  };

  const hideAuthMenu = () => {
    hideElement(authMenu);
  };

  const showUserMenu = () => {
    showElement(userMenu);
  };

  const hideUserMenu = () => {
    hideElement(userMenu);
  };

  const openUserModal = (modal) => {
    showElement(modal, "d-flex");
  };

  const closeUserModal = (modal) => {
    hideElement(modal, "d-flex");
  };

  const closeAllUserModals = () => {
    closeUserModal(userOrdersModal);
    closeUserModal(userProfileModal);
  };

  const showFeedback = (element, message, variant) => {
    if (!element) {
      return;
    }
    element.textContent = message;
    element.className = `alert mb-3 py-2 alert-${variant}`;
    element.classList.remove("d-none");
  };

  const hideFeedback = (element) => {
    if (!element) {
      return;
    }
    element.classList.add("d-none");
    element.textContent = "";
  };

  const escapeHtml = (value) => {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  };

  const statusLabel = (status) => {
    const labels = {
      pendiente: "Pendiente",
      pagado: "Pagado",
      enviado: "Enviado",
      cancelado: "Cancelado",
    };
    return labels[status] || status || "Pendiente";
  };

  const getInitials = (name, email) => {
    const source = (name || email || "US").trim();
    if (!source) {
      return "US";
    }
    const parts = source.split(/\s+/).filter(Boolean);
    const initials = parts.slice(0, 2).map((part) => part.charAt(0)).join("");
    return (initials || source.slice(0, 2) || "US").toUpperCase();
  };

  const renderOrderCard = (order) => {
    const amount = order.paquete_cantidad ? ` <span class="text-secondary">(${escapeHtml(order.paquete_cantidad)})</span>` : "";
    return `
      <article class="rounded-4 border border-info p-3" style="background:rgba(8,15,24,0.78);box-shadow:0 0 16px rgba(34,211,238,0.08);">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
          <div>
            <div class="small text-uppercase text-info" style="letter-spacing:0.14em;">Pedido #${escapeHtml(order.id)}</div>
            <h4 class="h6 mb-0 text-white">${escapeHtml(order.juego_nombre)}</h4>
          </div>
          <span class="badge rounded-pill text-bg-dark border border-info-subtle text-info">${escapeHtml(statusLabel(order.estado))}</span>
        </div>
        <div class="small text-secondary mb-2">${escapeHtml(order.creado_en)}</div>
        <div class="mb-2 text-light"><strong>Paquete:</strong> ${escapeHtml(order.paquete_nombre)}${amount}</div>
        <div class="mb-2 text-light"><strong>Correo:</strong> ${escapeHtml(order.email)}</div>
        <div class="fw-bold text-info fs-5">${escapeHtml(order.moneda)} ${escapeHtml(order.precio)}</div>
      </article>`;
  };

  const renderOrderRow = (order) => {
    const amount = order.paquete_cantidad ? ` (${escapeHtml(order.paquete_cantidad)})` : "";
    return `
      <tr>
        <td class="bg-transparent border-bottom border-info-subtle text-info fw-semibold">#${escapeHtml(order.id)}<div class="small text-secondary fw-normal">${escapeHtml(order.creado_en)}</div></td>
        <td class="bg-transparent border-bottom border-info-subtle text-light fw-semibold">${escapeHtml(order.juego_nombre)}</td>
        <td class="bg-transparent border-bottom border-info-subtle text-light">${escapeHtml(order.paquete_nombre)}<span class="text-secondary">${amount}</span></td>
        <td class="bg-transparent border-bottom border-info-subtle text-light">${escapeHtml(order.email)}</td>
        <td class="bg-transparent border-bottom border-info-subtle"><span class="badge rounded-pill text-bg-dark border border-info-subtle text-info">${escapeHtml(statusLabel(order.estado))}</span></td>
        <td class="bg-transparent border-bottom border-info-subtle text-info fw-bold text-end">${escapeHtml(order.moneda)} ${escapeHtml(order.precio)}</td>
      </tr>`;
  };

  const loadUserOrders = async () => {
    if (!userOrdersList || !userOrdersLoading || !userOrdersEmpty || !userOrdersTableBody || !userOrdersCards) {
      return;
    }
    hideFeedback(userOrdersFeedback);
    userOrdersList.innerHTML = "";
    userOrdersList.innerHTML = `
                <div class="table-responsive d-none d-md-block rounded-4 border border-info-subtle overflow-hidden" style="background:rgba(8,15,24,0.82);">
                  <table class="table align-middle mb-0" style="--bs-table-bg:transparent;--bs-table-color:#e5f6ff;">
                    <thead>
                      <tr>
                        <th class="text-info text-uppercase small fw-bold border-bottom border-info-subtle bg-transparent">Pedido</th>
                        <th class="text-info text-uppercase small fw-bold border-bottom border-info-subtle bg-transparent">Juego</th>
                        <th class="text-info text-uppercase small fw-bold border-bottom border-info-subtle bg-transparent">Paquete</th>
                        <th class="text-info text-uppercase small fw-bold border-bottom border-info-subtle bg-transparent">Correo</th>
                        <th class="text-info text-uppercase small fw-bold border-bottom border-info-subtle bg-transparent">Estado</th>
                        <th class="text-info text-uppercase small fw-bold border-bottom border-info-subtle bg-transparent text-end">Total</th>
                      </tr>
                    </thead>
                    <tbody id="user-orders-table-body"></tbody>
                  </table>
                </div>
                <div id="user-orders-cards" class="d-grid d-md-none gap-3"></div>`;
    const tableBody = document.getElementById("user-orders-table-body");
    const cardsContainer = document.getElementById("user-orders-cards");
    hideElement(userOrdersList);
    hideElement(userOrdersEmpty);
    showElement(userOrdersLoading);
    userOrdersLoading.textContent = "Cargando pedidos...";

    try {
      const response = await fetch("/api/account.php?action=orders", {
        credentials: "same-origin",
        headers: { "Accept": "application/json" },
      });
      const data = await response.json();
      if (!response.ok || !data.ok) {
        throw new Error(data.message || "No se pudo cargar el historial.");
      }

      hideElement(userOrdersLoading);
      if (!Array.isArray(data.orders) || data.orders.length === 0) {
        showElement(userOrdersEmpty);
        return;
      }

      tableBody.innerHTML = data.orders.map(renderOrderRow).join("");
      cardsContainer.innerHTML = data.orders.map(renderOrderCard).join("");
      showElement(userOrdersList);
    } catch (error) {
      hideElement(userOrdersLoading);
      showFeedback(userOrdersFeedback, error.message || "No se pudo cargar el historial.", "danger");
    }
  };

  const updateUserPresentation = (user) => {
    if (!user) {
      return;
    }
    if (userTriggerName) {
      userTriggerName.textContent = user.full_name || user.email || "Usuario";
    }
    if (userTriggerInitials) {
      userTriggerInitials.textContent = getInitials(user.full_name || "", user.email || "");
    }
    if (userMenuName) {
      userMenuName.textContent = user.full_name || user.email || "Usuario";
    }
    if (userMenuEmail) {
      userMenuEmail.textContent = user.email || "";
    }
    const orderEmailField = document.querySelector('#order-form input[name="email"]');
    if (orderEmailField) {
      orderEmailField.value = user.email || "";
    }
  };

  const openAuthModal = (mode) => {
    if (!authModal || !authLogin || !authRegister) return;
    showElement(authModal, "d-flex");
    if (mode === "register") {
      hideElement(authLogin, "d-grid");
      showElement(authRegister, "d-grid");
    } else {
      hideElement(authRegister, "d-grid");
      showElement(authLogin, "d-grid");
    }
  };

  const closeAuthModal = () => {
    if (!authModal) return;
    hideElement(authModal, "d-flex");
  };

  const togglePassword = (inputId, button) => {
    const input = document.getElementById(inputId);
    if (!input) {
      return;
    }
    const showPassword = input.type === "password";
    input.type = showPassword ? "text" : "password";
    if (button) {
      const hiddenIcon = button.querySelector('[data-password-icon="hidden"]');
      const visibleIcon = button.querySelector('[data-password-icon="visible"]');
      if (hiddenIcon) {
        hiddenIcon.classList.toggle("d-none", showPassword);
      }
      if (visibleIcon) {
        visibleIcon.classList.toggle("d-none", !showPassword);
      }
      button.setAttribute("aria-pressed", showPassword ? "true" : "false");
      button.setAttribute("aria-label", showPassword ? (button.dataset.passwordLabelHide || "Ocultar contraseña") : (button.dataset.passwordLabelShow || "Mostrar contraseña"));
    }
  };

  window.openAuthModal = openAuthModal;
  window.togglePassword = togglePassword;

  if (authTrigger && authMenu && authContainer) {
    authTrigger.addEventListener("click", (event) => {
      event.stopPropagation();
      hideUserMenu();
      if (authMenu.classList.contains("d-none")) {
        showAuthMenu();
      } else {
        hideAuthMenu();
      }
    });

    document.addEventListener("click", (event) => {
      if (!authContainer.contains(event.target)) {
        hideAuthMenu();
      }
    });
  }

  if (userTrigger && userMenu && authContainer) {
    userTrigger.addEventListener("click", (event) => {
      event.stopPropagation();
      hideAuthMenu();
      if (userMenu.classList.contains("d-none")) {
        showUserMenu();
      } else {
        hideUserMenu();
      }
    });

    document.addEventListener("click", (event) => {
      if (!authContainer.contains(event.target)) {
        hideUserMenu();
      }
    });
  }

  document.querySelectorAll("[data-auth-open]").forEach((button) => {
    button.addEventListener("click", (event) => {
      event.preventDefault();
      const mode = button.dataset.authOpen;
      hideAuthMenu();
      openAuthModal(mode);
    });
  });

  document.querySelectorAll("[data-auth-close]").forEach((button) => {
    button.addEventListener("click", (event) => {
      event.preventDefault();
      closeAuthModal();
    });
  });

  document.querySelectorAll("[data-auth-switch]").forEach((button) => {
    button.addEventListener("click", (event) => {
      event.preventDefault();
      openAuthModal(button.dataset.authSwitch);
    });
  });

  document.querySelectorAll("[data-user-open]").forEach((button) => {
    button.addEventListener("click", async (event) => {
      event.preventDefault();
      hideUserMenu();
      const target = button.dataset.userOpen;
      if (target === "orders") {
        openUserModal(userOrdersModal);
        await loadUserOrders();
        return;
      }
      if (target === "profile") {
        hideFeedback(userProfileFeedback);
        openUserModal(userProfileModal);
      }
    });
  });

  document.querySelectorAll("[data-user-close]").forEach((button) => {
    button.addEventListener("click", (event) => {
      event.preventDefault();
      closeAllUserModals();
    });
  });

  passwordToggles.forEach((button) => {
    button.addEventListener("click", (event) => {
      event.preventDefault();
      togglePassword(button.dataset.passwordToggle, button);
    });
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      hideAuthMenu();
      hideUserMenu();
      closeAuthModal();
      closeAllUserModals();
      closeMenu();
    }
  });

  if (userProfileForm) {
    userProfileForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      hideFeedback(userProfileFeedback);
      const submitButton = userProfileForm.querySelector('button[type="submit"]');
      const formData = new FormData(userProfileForm);
      formData.append("action", "update_profile");

      if (submitButton) {
        submitButton.disabled = true;
      }

      try {
        const response = await fetch("/api/account.php", {
          method: "POST",
          body: formData,
          credentials: "same-origin",
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
          throw new Error(data.message || "No se pudieron guardar los cambios.");
        }
        updateUserPresentation(data.user || null);
        userProfileForm.reset();
        userProfileForm.elements.name.value = (data.user && data.user.full_name) || "";
        userProfileForm.elements.email.value = (data.user && data.user.email) || "";
        showFeedback(userProfileFeedback, data.message || "Datos guardados.", "success");
      } catch (error) {
        showFeedback(userProfileFeedback, error.message || "No se pudieron guardar los cambios.", "danger");
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
        }
      }
    });
  }
</script>
SCRIPT;
?>
    </div>
  </div>
  <?php if ($hasFacebook || $hasInstagram): ?>
    <footer class="social-footer-shell mt-5">
      <div class="social-footer-card">
        <p class="social-footer-kicker mb-2">Redes oficiales</p>
        <div class="social-footer-links">
          <?php if ($hasFacebook): ?>
            <a href="<?php echo htmlspecialchars($facebookUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="social-footer-link social-footer-link-facebook" aria-label="Facebook">
              <span class="social-footer-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="currentColor" role="img"><path d="M13.5 22v-8h2.7l.5-3h-3.2V9.1c0-.9.3-1.6 1.7-1.6H17V4.8c-.3 0-1.3-.1-2.4-.1-2.4 0-4.1 1.5-4.1 4.3V11H8v3h2.5v8h3Z"/></svg>
              </span>
              <span>Facebook</span>
            </a>
          <?php endif; ?>
          <?php if ($hasInstagram): ?>
            <a href="<?php echo htmlspecialchars($instagramUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="social-footer-link social-footer-link-instagram" aria-label="Instagram">
              <span class="social-footer-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" role="img"><rect x="3.5" y="3.5" width="17" height="17" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.3" cy="6.7" r="1"></circle></svg>
              </span>
              <span>Instagram</span>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </footer>
  <?php endif; ?>
  <?php if ($hasWhatsapp || $hasWhatsappChannel): ?>
    <div class="floating-social-stack" aria-label="Accesos rápidos de contacto">
      <?php if ($hasWhatsappChannel): ?>
        <a href="<?php echo htmlspecialchars($whatsappChannelUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="floating-social-button floating-social-button-channel" aria-label="Canal de difusión">
          <span class="floating-social-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="currentColor" role="img"><path d="M12 2a10 10 0 0 0-8.7 14.95L2 22l5.22-1.3A10 10 0 1 0 12 2Zm4.74 13.34c-.2.56-1.16 1.04-1.62 1.11-.42.06-.95.09-1.53-.1-.35-.11-.81-.26-1.39-.51-2.45-1.06-4.05-3.67-4.17-3.84-.12-.16-1-1.34-1-2.55s.63-1.79.86-2.03c.22-.24.48-.3.64-.3h.46c.14 0 .33-.05.52.39.2.47.67 1.62.73 1.74.06.12.1.27.02.43-.07.16-.11.26-.22.4-.11.13-.22.29-.31.39-.1.11-.2.22-.08.43.12.2.53.88 1.14 1.42.78.69 1.44.9 1.64 1 .2.1.31.08.43-.05.12-.13.49-.57.62-.76.13-.2.27-.16.45-.1.19.07 1.17.55 1.38.65.2.1.34.15.39.24.05.09.05.53-.15 1.09Z"/></svg>
          </span>
          <span class="floating-social-label" style="display:inline-block;white-space:nowrap;line-height:1.2;">Canal de difusión</span>
        </a>
      <?php endif; ?>
      <?php if ($hasWhatsapp): ?>
        <a href="<?php echo htmlspecialchars($whatsappUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="floating-social-button floating-social-button-whatsapp" aria-label="WhatsApp">
          <span class="floating-social-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="currentColor" role="img"><path d="M20.52 3.48A11.8 11.8 0 0 0 12.08 0C5.54 0 .22 5.32.22 11.86c0 2.09.55 4.13 1.58 5.93L0 24l6.39-1.67a11.8 11.8 0 0 0 5.69 1.45h.01c6.54 0 11.86-5.32 11.86-11.86 0-3.17-1.23-6.16-3.43-8.44ZM12.09 21.76h-.01a9.87 9.87 0 0 1-5.03-1.38l-.36-.21-3.79.99 1.01-3.69-.23-.38A9.87 9.87 0 0 1 2.2 11.86C2.2 6.4 6.63 1.98 12.08 1.98c2.64 0 5.12 1.03 6.98 2.91a9.8 9.8 0 0 1 2.88 6.98c0 5.45-4.43 9.89-9.85 9.89Zm5.42-7.41c-.3-.15-1.76-.87-2.03-.97-.27-.1-.46-.15-.66.15-.2.3-.76.97-.93 1.17-.17.2-.34.22-.64.07-.3-.15-1.27-.47-2.41-1.49-.89-.8-1.49-1.79-1.67-2.09-.17-.3-.02-.47.13-.62.13-.13.3-.34.44-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.66-1.59-.9-2.17-.24-.58-.48-.5-.66-.5h-.56c-.2 0-.52.08-.79.37-.27.3-1.05 1.03-1.05 2.52 0 1.49 1.08 2.92 1.23 3.12.15.2 2.11 3.23 5.12 4.52.72.31 1.29.49 1.73.63.73.23 1.39.2 1.91.12.58-.09 1.76-.72 2.01-1.42.25-.69.25-1.29.17-1.42-.07-.12-.27-.2-.57-.35Z"/></svg>
          </span>
          <span class="floating-social-label" style="display:inline-block;white-space:nowrap;line-height:1.2;">Soporte</span>
        </a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php
  $menuScriptVersioned = str_replace('<script', '<script', $menuScript);
  $menuScriptVersioned = str_replace('</script>', '</script>', $menuScriptVersioned);
  // Si hay scripts externos, agregar ?v=fecha
  echo $menuScriptVersioned;
  ?>
  <?php
  if (!empty($pageScripts) && is_array($pageScripts)) {
    foreach ($pageScripts as $script) {
      echo $script;
    }
  }
  ?>
</body>
</html>
