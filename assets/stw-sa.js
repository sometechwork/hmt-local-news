jQuery(function ($) {
  console.log("STW_SA script loaded", window.STW_SA);

  (function () {
    function qs(root, sel) {
      return root.querySelector(sel);
    }

    function openModal() {
      const modal = document.querySelector(".stw-sa-modal");
      if (!modal) return;
      modal.setAttribute("aria-hidden", "false");
      modal.classList.add("is-open");
      document.body.classList.add("stw-sa-no-scroll");
    }

    function closeModal() {
      const modal = document.querySelector(".stw-sa-modal");
      if (!modal) return;
      modal.setAttribute("aria-hidden", "true");
      modal.classList.remove("is-open");
      document.body.classList.remove("stw-sa-no-scroll");
    }

    async function loadAnnouncement(postId) {
      const loading = document.querySelector(".stw-sa-modal__loading");
      const titleEl = document.querySelector(".stw-sa-modal__title");
      const metaEl = document.querySelector(".stw-sa-modal__meta");
      const thumbEl = document.querySelector(".stw-sa-modal__thumb");
      const contentEl = document.querySelector(".stw-sa-modal__content");

      if (loading) loading.style.display = "block";
      if (titleEl) titleEl.textContent = "";
      if (metaEl) metaEl.textContent = "";
      if (thumbEl) thumbEl.innerHTML = "";
      if (contentEl) contentEl.innerHTML = "";

      const res = await fetch(STW_SA.restUrl + postId, { method: "GET" });
      if (!res.ok) {
        if (loading) loading.style.display = "none";
        if (titleEl) titleEl.textContent = "Could not load announcement";
        return;
      }

      const data = await res.json();
      if (loading) loading.style.display = "none";
      if (titleEl) titleEl.textContent = data.title || "";
      if (metaEl) metaEl.textContent = data.date || "";
      if (thumbEl) thumbEl.innerHTML = data.thumbnail || "";
      if (contentEl) contentEl.innerHTML = data.content || "";
    }

    document.addEventListener("click", async function (e) {
      const btn = e.target.closest(".stw-sa-readmore");
      if (btn) {
        e.preventDefault();
        const postId = btn.getAttribute("data-post-id");
        openModal();
        await loadAnnouncement(postId);
        return;
      }

      const close = e.target.closest("[data-close='1']");
      if (close) {
        closeModal();
        return;
      }
    });

    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      const modal = document.querySelector(".stw-sa-modal.is-open");
      if (modal) {
        closeModal();
      }
    });
  })();
});
