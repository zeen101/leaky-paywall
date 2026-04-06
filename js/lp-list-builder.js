(function() {

    function qs(root, sel) {
      return root.querySelector(sel);
    }

    async function postJson(url, payload) {

      const res = await fetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": LP_LIST_BUILDER.nonce
        },
        body: JSON.stringify(payload),
        credentials: "same-origin"
      });

      const data = await res.json().catch(() => ({}));

      if (!res.ok) {
        const msg = data?.message || "Something went wrong.";
        throw new Error(msg);
      }

      return data;
    }

    function clearRestrictionData() {
      try { localStorage.removeItem('lp_viewed_content'); } catch (e) {}

      // Clear any LP restriction cookies.
      document.cookie.split(';').forEach(function(c) {
        var name = c.split('=')[0].trim();
        if (name.indexOf('issuem_lp') === 0) {
          document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
        }
      });
    }

    function showSuccessAndReload(form, message = "You're signed in!") {
      form.outerHTML = `
        <div class="lp-inline-auth__success">
          <p><strong>Thanks!</strong></p>
          <p>${message}</p>
        </div>
      `;

      setTimeout(() => {
        window.location.reload();
      }, 2000);
    }

    function applyResponse(container, data) {

        const headingEl = document.querySelector('.Slider__ExpandedHeader');
        const subheadingEl = document.querySelector('.Slider__ExpandedSubHeader');

        const msgEl = container.querySelector(".lp-list-builder__msg");

        console.log(headingEl);

        if (headingEl && typeof data.heading === "string") headingEl.textContent = data.heading;
        if (subheadingEl && typeof data.subheading === "string") subheadingEl.textContent = data.subheading;

        const currentForm = container.querySelector("form.lp-list-builder-auth__form");
        if (currentForm && typeof data.form_html === "string") {
          currentForm.outerHTML = data.form_html;
        } else if (typeof data.form_html === "string") {
          container.insertAdjacentHTML("beforeend", data.form_html);
        }

        if (msgEl) msgEl.textContent = "";
    }

    function mount(container) {

      const msgEl = qs(container, '.lp-list-builder__msg');

      container.addEventListener("click", async (e) => {
        const forgot = e.target.closest('[data-action="forgot-password"]');

        if (!forgot) return;

        e.preventDefault();

        const form = e.target.closest("form");
        const email = form?.querySelector('input[name="email"]')?.value || "";

        try {
          const data = await postJson(LP_LIST_BUILDER.pwResetRequestUrl, { email } );
          applyResponse(container, data);

          container.querySelector('input[name="code"]')?.focus();


        } catch (err ) {
            container.querySelector(".lp-list-builder__msg").textContent = err.message || "Something went wrong.";
        }

      });

      container.addEventListener("submit", async (e) => {
        const form = e.target.closest("form");
        if (!form ) return;

        e.preventDefault();

        msgEl.textContent = "";

        const step = form.getAttribute("data-step");
        const fd = new FormData(form);

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.dataset.originalText = submitBtn.textContent;
          submitBtn.innerHTML = `Processing… <span class="lp-list-builder__spinner--inline"></span>`;
        }

        try {
          console.log('trying form');

          if (step == "email") {
            console.log('step is email');
            const email = (fd.get("email") || "").toString().trim();
            const heading = document.querySelector('.Slider__ExpandedHeader');
            const subheading = document.querySelector('.Slider__ExpandedSubHeader');

            const data = await postJson(LP_LIST_BUILDER.flowUrl, { email });

            form.outerHTML = data.form_html;
            heading.innerHTML = data.heading;
            subheading.innerHTML = data.subheading;

            const newForm = qs(container, `form[data-step="${data.step}"]`);
            const pw = newForm && qs(newForm, 'input[name="password"]');
            if (pw) pw.focus();

            if (data.step === 'signup') {
              document.dispatchEvent(new CustomEvent('lp_list_builder_signup_form_rendered', { detail: { form: newForm } }));
            }

            return;
          }

          if (step == "signup") {
            const payload = Object.fromEntries(fd.entries());
            payload.current_url = window.location.href.split("#")[0];

            if (typeof window.lpListBuilderPreSignup === 'function') {
              await window.lpListBuilderPreSignup(payload);
            }

            await postJson(LP_LIST_BUILDER.signupUrl, payload);

            clearRestrictionData();
            showSuccessAndReload(form, "Your account has been created. Unlocking content...");

            return;
          }

          if (step == "password") {
            const email = (fd.get("email") || "").toString().trim();
            const password = (fd.get("password") || "").toString();

            await postJson(LP_LIST_BUILDER.loginUrl, {
              email,
              password,
              current_url: window.location.href.split("#")[0],
            });

            showSuccessAndReload(form, "Signing you in…");
            return;
          }

          if (step === "reset-code") {
            const email = (fd.get("email") || "").toString().trim();
            const code = (fd.get("code") || "").toString().trim();

            const data = await postJson(LP_LIST_BUILDER.pwResetVerifyUrl, { email, code });
            applyResponse(container, data);

            container.querySelector('input[name="password"]')?.focus();
            return;
          }

          if (step === "reset-new-password") {
            const email = (fd.get("email") || "").toString().trim();
            const token = (fd.get("token") || "").toString();
            const password = (fd.get("password") || "").toString();

            await postJson(LP_LIST_BUILDER.pwResetConfirmUrl, { email, token, password });

            showSuccessAndReload(form, "Signing you in…");
            return;
          }

        } catch (err) {
          msgEl.textContent = err.message || "Something went wrong.";

          var btn = form.querySelector('button[type="submit"]');
          if (btn) {
            btn.disabled = false;
            btn.textContent = btn.dataset.originalText || btn.textContent;
          }
        }
      });

    }

    document.addEventListener("DOMContentLoaded", () => {
        document.querySelectorAll(".Form__Container").forEach(mount);
    });


      document.addEventListener('leaky_paywall_shown', function(e) {
          var nagType = e.detail.response.nag_type;
          var isUpgrade = nagType === 'upgrade' || (nagType && nagType.indexOf('targeted') === 0);

          if (nagType !== 'subscribe' && !isUpgrade) {
              return;
          }

          var subscribePanel = document.getElementById('lplb-subscribe-panel');
          var upgradePanel   = document.getElementById('lplb-upgrade-panel');

          if (isUpgrade) {
              if (!LP_LIST_BUILDER.upgradeEnabled) { return; }
              if (subscribePanel) subscribePanel.style.display = 'none';
              if (upgradePanel)   upgradePanel.style.display   = '';
          } else {
              if (subscribePanel) subscribePanel.style.display = '';
              if (upgradePanel)   upgradePanel.style.display   = 'none';
          }

          document.documentElement.style.overflow = "hidden";
          document.body.style.overflow = "hidden";

          var mask = document.getElementById("lplb-mask");
          var portal = document.getElementById("lplb-portal");

          if (mask) mask.classList.add("is-visible");
          if (portal) portal.classList.add("is-visible");

          // Trigger transitions on next frame so the browser registers the initial state first.
          requestAnimationFrame(function() {
              if (mask) mask.classList.add("is-active");
              if (portal) portal.classList.add("is-active");
          });
      });

})();