(function () {
  function qs(root, sel) { return root.querySelector(sel); }

  function openModal(wrapper) {
    const modal = qs(wrapper, ".stw-sa-modal");
    modal.setAttribute("aria-hidden", "false");
    modal.classList.add("is-open");
    document.body.classList.add("stw-sa-no-scroll");
  }

  function closeModal(wrapper) {
    const modal = qs(wrapper, ".stw-sa-modal");
    modal.setAttribute("aria-hidden", "true");
    modal.classList.remove("is-open");
    document.body.classList.remove("stw-sa-no-scroll");
  }

  async function loadAnnouncement(wrapper, postId) {
    const loading = qs(wrapper, ".stw-sa-modal__loading");
    const titleEl = qs(wrapper, ".stw-sa-modal__title");
    const metaEl = qs(wrapper, ".stw-sa-modal__meta");
    const contentEl = qs(wrapper, ".stw-sa-modal__content");

    loading.style.display = "block";
    titleEl.textContent = "";
    metaEl.textContent = "";
    contentEl.innerHTML = "";

    const res = await fetch(STW_SA.restUrl + postId, { method: "GET" });
    if (!res.ok) {
      loading.style.display = "none";
      titleEl.textContent = "Could not load announcement";
      return;
    }

    const data = await res.json();
    loading.style.display = "none";
    titleEl.textContent = data.title || "";
    metaEl.textContent = data.date || "";
    contentEl.innerHTML = data.content || "";
  }

  document.addEventListener("click", async function (e) {
    const btn = e.target.closest(".stw-sa-readmore");
    if (btn) {
      e.preventDefault();
      const wrapper = btn.closest(".stw-sa");
      const postId = btn.getAttribute("data-post-id");
      openModal(wrapper);
      await loadAnnouncement(wrapper, postId);
      return;
    }

    const close = e.target.closest("[data-close='1']");
    if (close) {
      const wrapper = e.target.closest(".stw-sa");
      if (wrapper) closeModal(wrapper);
      return;
    }
  });

  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    document.querySelectorAll(".stw-sa-modal.is-open").forEach(function (modal) {
      const wrapper = modal.closest(".stw-sa");
      if (wrapper) closeModal(wrapper);
    });
  });
})();