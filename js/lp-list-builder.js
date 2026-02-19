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

      console.log('mount init');
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
            return;
          }

          if (step == "signup") {
            const email = (fd.get("email") || "").toString().trim();
            const password = (fd.get("password") || "").toString();

            const data = await postJson(LP_LIST_BUILDER.signupUrl, {
              email,
              password,
              current_url: window.location.href.split("#")[0],
            });

            showSuccessAndReload(form, "Your account has been created.");

            return;
          }

          if (step == "password") {
            const email = (fd.get("email") || "").toString().trim();
            const password = (fd.get("password") || "").toString();

            const data = await postJson(LP_LIST_BUILDER.loginUrl, {
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

            const data = await postJson(LP_LIST_BUILDER.pwResetConfirmUrl, { email, token, password });

            showSuccessAndReload(form, "Signing you in…");
            return;
          }

        } catch (err) {
          msgEl.textContent = err.message || "Something went wrong.";
        }
      });

    }

    document.addEventListener("DOMContentLoaded", () => {
        console.log('list builder init');
        document.querySelectorAll(".Form__Container").forEach(mount);

        // const isLoggedIn = document.body.classList.contains("logged-in");
        // const isSingle = document.body.classList.contains("single-post");

        // if (!isLoggedIn) {

        //   if (isSingle) {
        //     document.documentElement.style.overflow = "hidden"; // <html>
        //     document.body.style.overflow = "hidden";            // <body>
        //     document.getElementById("lplb-mask")?.classList.add("is-visible");
        //     document.getElementById("lplb-portal")?.classList.add("is-visible");
        //   }

        // }



    });


      document.addEventListener('leaky_paywall_shown', function(e) {
          console.log('Paywall displayed for post:', e.detail.postId);

          // Only show the slider for the subscribe nag.
          if (e.detail.response.nag_type !== 'subscribe') {
              return;
          }

          document.documentElement.style.overflow = "hidden"; // <html>
          document.body.style.overflow = "hidden";            // <body>
          document.getElementById("lplb-mask")?.classList.add("is-visible");
          document.getElementById("lplb-portal")?.classList.add("is-visible");
      });

})();