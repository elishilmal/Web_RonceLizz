document.addEventListener("DOMContentLoaded", function () {
  initApp();
});

function initApp() {
  initTooltips();
  initFormValidation();
  initModals();
  initMobileMenu();
  autoDismissAlerts();
  addSparkleEffects();
  initConfirmDialogs();
}

// Tooltips
function initTooltips() {
  const tooltips = document.querySelectorAll("[data-tooltip]");
  tooltips.forEach((el) => {
    el.addEventListener("mouseenter", showTooltip);
    el.addEventListener("mouseleave", hideTooltip);
  });
}

function showTooltip(e) {
  const tooltip = document.createElement("div");
  tooltip.className = "tooltip";
  tooltip.textContent = e.target.dataset.tooltip;
  tooltip.style.position = "absolute";
  tooltip.style.background =
    "linear-gradient(45deg, var(--pink-accent), var(--purple-dark))";
  tooltip.style.color = "white";
  tooltip.style.padding = "8px 12px";
  tooltip.style.borderRadius = "8px";
  tooltip.style.fontSize = "0.9rem";
  tooltip.style.zIndex = "1000";
  tooltip.style.boxShadow = "0 4px 15px rgba(0,0,0,0.2)";

  const rect = e.target.getBoundingClientRect();
  tooltip.style.top = rect.top - 40 + "px";
  tooltip.style.left = rect.left + rect.width / 2 - 50 + "px";

  document.body.appendChild(tooltip);
  e.target._tooltip = tooltip;
}

function hideTooltip(e) {
  if (e.target._tooltip) {
    e.target._tooltip.remove();
    e.target._tooltip = null;
  }
}

// Form Validation
function initFormValidation() {
  const forms = document.querySelectorAll("form[data-validate]");
  forms.forEach((form) => {
    form.addEventListener("submit", validateForm);
  });
}

function validateForm(e) {
  const form = e.target;
  const inputs = form.querySelectorAll(
    "input[required], select[required], textarea[required]",
  );
  let isValid = true;

  inputs.forEach((input) => {
    if (!input.value.trim()) {
      markInvalid(input);
      isValid = false;
    } else {
      markValid(input);
    }
  });

  if (!isValid) {
    e.preventDefault();
    showAlert("Harap isi semua field yang diperlukan!", "warning");
  }
}

function markInvalid(input) {
  input.style.borderColor = "var(--peach-dark)";
  input.style.background = "var(--peach-light)";

  let errorMsg = input.nextElementSibling;
  if (!errorMsg || !errorMsg.classList.contains("error-msg")) {
    errorMsg = document.createElement("div");
    errorMsg.className = "error-msg";
    errorMsg.textContent = "Field ini wajib diisi";
    errorMsg.style.color = "var(--peach-dark)";
    errorMsg.style.fontSize = "0.85rem";
    errorMsg.style.marginTop = "5px";
    input.parentNode.appendChild(errorMsg);
  }
}

function markValid(input) {
  input.style.borderColor = "var(--mint-dark)";
  input.style.background = "var(--white)";

  const errorMsg = input.nextElementSibling;
  if (errorMsg && errorMsg.classList.contains("error-msg")) {
    errorMsg.remove();
  }
}

// Modals
function initModals() {
  const modalTriggers = document.querySelectorAll("[data-modal]");
  modalTriggers.forEach((trigger) => {
    trigger.addEventListener("click", openModal);
  });

  const modalCloses = document.querySelectorAll(".modal-close, .modal-overlay");
  modalCloses.forEach((close) => {
    close.addEventListener("click", closeModal);
  });

  // Close modal with Escape key
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      closeModal();
    }
  });
}

function openModal(e) {
  const modalId = e.target.dataset.modal;
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add("active");
    document.body.style.overflow = "hidden";
  }
}

function closeModal(e) {
  if (
    (e && e.target.classList.contains("modal-close")) ||
    (e && e.target.classList.contains("modal-overlay"))
  ) {
    const modal = e.target.closest(".modal-overlay");
    modal.classList.remove("active");
  } else {
    const modals = document.querySelectorAll(".modal-overlay.active");
    modals.forEach((modal) => {
      modal.classList.remove("active");
    });
  }
  document.body.style.overflow = "auto";
}

// Mobile Menu
function initMobileMenu() {
  const toggleBtn = document.querySelector(".mobile-menu-toggle");
  const sidebar = document.querySelector(".sidebar");

  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener("click", () => {
      sidebar.classList.toggle("active");
    });

    document.addEventListener("click", (e) => {
      if (window.innerWidth <= 1024) {
        if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
          sidebar.classList.remove("active");
        }
      }
    });
  }
}

function autoDismissAlerts() {
  const alerts = document.querySelectorAll(".alert:not(.alert-permanent)");
  alerts.forEach((alert) => {
    setTimeout(() => {
      alert.style.opacity = "0";
      alert.style.transform = "translateY(-10px)";
      setTimeout(() => {
        if (alert.parentNode) {
          alert.remove();
        }
      }, 300);
    }, 5000);
  });
}

// Sparkle Effects
function addSparkleEffects() {
  const headers = document.querySelectorAll("h1, h2, h3");
  headers.forEach((header) => {
    if (Math.random() > 0.5) {
      header.classList.add("sparkle");
    }
  });
}

// Confirm Dialogs
function initConfirmDialogs() {
  const confirmButtons = document.querySelectorAll("[data-confirm]");
  confirmButtons.forEach((button) => {
    button.addEventListener("click", function (e) {
      const message = this.dataset.confirm || "Apakah Anda yakin?";
      if (!confirmCustom(message)) {
        e.preventDefault();
        e.stopPropagation();
      }
    });
  });
}

function confirmCustom(message) {
  return new Promise((resolve) => {
    const modal = document.createElement("div");
    modal.className = "modal-overlay active";
    modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Konfirmasi</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <p>${message}</p>
                </div>
                <div class="modal-footer" style="display: flex; gap: 10px; margin-top: 20px;">
                    <button class="btn btn-secondary" id="confirm-cancel">Batal</button>
                    <button class="btn btn-danger" id="confirm-ok">Ya, Lanjutkan</button>
                </div>
            </div>
        `;

    document.body.appendChild(modal);

    modal
      .querySelector(".modal-close, #confirm-cancel")
      .addEventListener("click", () => {
        modal.remove();
        resolve(false);
      });

    modal.querySelector("#confirm-ok").addEventListener("click", () => {
      modal.remove();
      resolve(true);
    });

    modal.addEventListener("click", (e) => {
      if (e.target === modal) {
        modal.remove();
        resolve(false);
      }
    });
  });
}

// Show Alert Function
window.showAlert = function (message, type = "info") {
  const alert = document.createElement("div");
  alert.className = `alert alert-${type}`;
  alert.innerHTML = `
        <span class="alert-icon">
            ${
              type === "success"
                ? "✓"
                : type === "warning"
                  ? "⚠"
                  : type === "danger"
                    ? "✗"
                    : "ℹ"
            }
        </span>
        <span>${message}</span>
    `;

  const container =
    document.querySelector(".alert-container") || createAlertContainer();
  container.appendChild(alert);

  setTimeout(() => {
    alert.style.opacity = "0";
    alert.style.transform = "translateY(-10px)";
    setTimeout(() => {
      if (alert.parentNode) {
        alert.remove();
      }
    }, 300);
  }, 5000);
};

function createAlertContainer() {
  const container = document.createElement("div");
  container.className = "alert-container";
  container.style.position = "fixed";
  container.style.top = "20px";
  container.style.right = "20px";
  container.style.zIndex = "1000";
  container.style.maxWidth = "400px";
  document.body.appendChild(container);
  return container;
}

// Format Currency
window.formatCurrency = function (amount) {
  return "Rp " + parseInt(amount).toLocaleString("id-ID");
};

// Toggle Password Visibility
window.togglePassword = function (inputId) {
  const input = document.getElementById(inputId);
  const toggleBtn = input.nextElementSibling;

  if (input.type === "password") {
    input.type = "text";
    toggleBtn.textContent = "👁️";
  } else {
    input.type = "password";
    toggleBtn.textContent = "👁️‍🗨️";
  }
};

// Add loading state to buttons
document.addEventListener("submit", function (e) {
  const form = e.target;
  const submitBtn = form.querySelector('button[type="submit"]');

  if (submitBtn) {
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML =
      '<span class="loading-spinner" style="margin-right: 8px;"></span> Memproses...';
    submitBtn.disabled = true;

    setTimeout(() => {
      submitBtn.innerHTML = originalText;
      submitBtn.disabled = false;
    }, 3000);
  }
});

// Add cute hover effects to cards
const cards = document.querySelectorAll(".card");
cards.forEach((card) => {
  card.addEventListener("mouseenter", () => {
    card.style.transform = "translateY(-5px) scale(1.02)";
  });

  card.addEventListener("mouseleave", () => {
    card.style.transform = "translateY(0) scale(1)";
  });
});

// Add ripple effect to buttons
document.addEventListener("click", function (e) {
  if (e.target.classList.contains("btn")) {
    const btn = e.target;
    const ripple = document.createElement("span");
    const rect = btn.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = e.clientX - rect.left - size / 2;
    const y = e.clientY - rect.top - size / 2;

    ripple.style.position = "absolute";
    ripple.style.borderRadius = "50%";
    ripple.style.background = "rgba(255, 255, 255, 0.5)";
    ripple.style.width = ripple.style.height = size + "px";
    ripple.style.left = x + "px";
    ripple.style.top = y + "px";
    ripple.style.transform = "scale(0)";
    ripple.style.animation = "ripple 0.6s linear";

    btn.style.position = "relative";
    btn.style.overflow = "hidden";
    btn.appendChild(ripple);

    setTimeout(() => {
      ripple.remove();
    }, 600);
  }
});

// Add CSS for ripple animation
const style = document.createElement("style");
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

function submitOrderForm() {
  const productId = document.getElementById("product_id_select").value;
  const quantity = parseInt(document.getElementById("quantity").value);
  const maxStock = parseInt(document.getElementById("quantity").max);
  const notes = document.querySelector('textarea[name="notes"]').value;
  const totalPrice = document.getElementById("total_price").textContent;
  const productName =
    document.getElementById("product_id_select").options[
      document.getElementById("product_id_select").selectedIndex
    ].text;

  if (!productId) {
    Swal.fire({
      title: "Peringatan!",
      text: "Silakan pilih produk terlebih dahulu!",
      icon: "warning",
      confirmButtonColor: "#ff6b93",
    });
    return;
  }

  if (quantity > maxStock) {
    Swal.fire({
      title: "Peringatan!",
      text: "Jumlah pesanan melebihi stok yang tersedia!",
      icon: "warning",
      confirmButtonColor: "#ff6b93",
    });
    return;
  }

  if (quantity < 1) {
    Swal.fire({
      title: "Peringatan!",
      text: "Jumlah pesanan minimal 1!",
      icon: "warning",
      confirmButtonColor: "#ff6b93",
    });
    return;
  }

  if (!notes.trim()) {
    Swal.fire({
      title: "Peringatan!",
      text: "Silakan isi catatan termasuk alamat pengiriman!",
      icon: "warning",
      confirmButtonColor: "#ff6b93",
    });
    return;
  }
}
