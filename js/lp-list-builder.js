(function() {

    // Bail cleanly if the config global isn't defined. This can happen when
    // a consent-management platform (Cookiebot, OneTrust, Iubenda, etc.)
    // blocks the inline <script> that defines window.LP_LIST_BUILDER but
    // allows this external file to load — leaving the file with a missing
    // dependency. Without this guard the file throws a ReferenceError
    // during init, which prevents the modal from being closed and locks
    // paid subscribers out of content behind a stuck popup.
    //
    // The right fix is publisher-side (mark LP scripts as Necessary in the
    // consent tool), but this guard ensures paid subscribers keep working
    // via leaky-paywall-check.js even when the config isn't available.
    if (typeof window.LP_LIST_BUILDER === 'undefined') {
      return;
    }

    function qs(root, sel) {
      return root.querySelector(sel);
    }

    // Resolve the current post/page ID from the body classes (postid-NN / page-id-NN).
    // Used for conversion attribution so it doesn't rely on parsing the URL.
    function getCurrentPostId() {
      var classes = (document.body.className || '').split(/\s+/);
      for (var i = 0; i < classes.length; i++) {
        if (classes[i].indexOf('postid-') === 0) {
          return parseInt(classes[i].substring(7), 10) || 0;
        }
        if (classes[i].indexOf('page-id-') === 0) {
          return parseInt(classes[i].substring(8), 10) || 0;
        }
      }
      return 0;
    }

    async function postJson(url, payload) {

      const res = await fetch(url, {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
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

    function showSuccessAndReload(form, message = "You're signed in!", heading = 'Welcome!') {
      const headingEl = document.querySelector('.Slider__ExpandedHeader');
      const subheadingEl = document.querySelector('.Slider__ExpandedSubHeader');
      if (headingEl) headingEl.innerHTML = heading;
      if (subheadingEl) subheadingEl.innerHTML = '';

      form.outerHTML = `
        <div class="lp-inline-auth__success">
          <p>${message}</p>
        </div>
      `;

      setTimeout(() => {
        window.location.reload();
      }, 2000);
    }

    function escapeHtml(str) {
      return String(str).replace(/[&<>"']/g, function(ch) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
      });
    }

    function renderOtpForm(email) {
      const safeEmail = escapeHtml(email);
      return ''
        + '<form class="lp-list-builder-auth__form" data-step="otp">'
        +   '<input type="hidden" name="email" value="' + safeEmail + '" />'
        +   '<div class="Slider__InputGroup">'
        +     '<div class="Slider__InputRow">'
        +       '<label>Verification code</label>'
        +       '<div class="TextField Slider__EmailAddressField">'
        +         '<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus placeholder="000000" />'
        +       '</div>'
        +     '</div>'
        +     '<button type="submit" class="Slider__ExpandedButton" tabindex="0">Verify code</button>'
        +     '<p class="lp-list-builder__subtle">'
        +       'Didn\'t get the code? '
        +       '<a href="#" data-action="resend-otp">Send it again</a>'
        +     '</p>'
        +     '<p class="lp-list-builder__msg" aria-live="polite"></p>'
        +   '</div>'
        + '</form>';
    }

    function applyResponse(container, data) {

        const headingEl = document.querySelector('.Slider__ExpandedHeader');
        const subheadingEl = document.querySelector('.Slider__ExpandedSubHeader');

        const msgEl = container.querySelector(".lp-list-builder__msg");

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
        const resendOtp = e.target.closest('[data-action="resend-otp"]');

        if (resendOtp) {
          e.preventDefault();
          const form = e.target.closest('form');
          const email = form?.querySelector('input[name="email"]')?.value || '';
          const msg = form?.querySelector('.lp-list-builder__msg');

          try {
            const data = await postJson(window.LP_LIST_BUILDER.requestOtpUrl, { email });
            if (msg) msg.textContent = data.message || 'A new code was sent.';
          } catch (err) {
            if (msg) msg.textContent = err.message || 'Could not send a new code.';
          }
          return;
        }

        const forgot = e.target.closest('[data-action="forgot-password"]');

        if (!forgot) return;

        e.preventDefault();

        const form = e.target.closest("form");
        const email = form?.querySelector('input[name="email"]')?.value || "";

        try {
          const data = await postJson(window.LP_LIST_BUILDER.pwResetRequestUrl, { email } );
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
          if (step == "email") {
            const email = (fd.get("email") || "").toString().trim();
            const heading = document.querySelector('.Slider__ExpandedHeader');
            const subheading = document.querySelector('.Slider__ExpandedSubHeader');

            const data = await postJson(window.LP_LIST_BUILDER.flowUrl, { email });

            // If OTP mode is enabled and this is a new signup, insert the
            // OTP verification step between email and signup.
            if (data.step === 'signup' && window.LP_LIST_BUILDER.otpEnabled) {
              // Stash the signup form HTML for after verification.
              container.dataset.lpStashedSignupHtml = data.form_html;
              container.dataset.lpStashedSignupHeading = data.heading || '';
              container.dataset.lpStashedSignupSubheading = data.subheading || '';

              try {
                await postJson(window.LP_LIST_BUILDER.requestOtpUrl, { email });
              } catch (err) {
                msgEl.textContent = err.message || 'Could not send verification code.';
                if (submitBtn) {
                  submitBtn.disabled = false;
                  submitBtn.textContent = submitBtn.dataset.originalText || 'Continue';
                }
                return;
              }

              heading.innerHTML = 'Check your email';
              subheading.innerHTML = 'We sent a 6-digit code to ' + email + '.';

              form.outerHTML = renderOtpForm(email);

              const otpForm = qs(container, 'form[data-step="otp"]');
              const codeInput = otpForm && qs(otpForm, 'input[name="code"]');
              if (codeInput) codeInput.focus();

              return;
            }

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

          if (step === "otp") {
            const email = (fd.get("email") || "").toString().trim();
            const code = (fd.get("code") || "").toString().trim();

            await postJson(window.LP_LIST_BUILDER.verifyOtpUrl, { email, code });

            // Swap in the stashed signup form.
            const heading = document.querySelector('.Slider__ExpandedHeader');
            const subheading = document.querySelector('.Slider__ExpandedSubHeader');
            const stashedHtml = container.dataset.lpStashedSignupHtml || '';
            const stashedHeading = container.dataset.lpStashedSignupHeading || '';
            const stashedSubheading = container.dataset.lpStashedSignupSubheading || '';

            form.outerHTML = stashedHtml;
            // Always apply the stashed heading/subheading (empty string is valid —
            // it clears the OTP step's "We sent a code" message).
            if (heading && 'lpStashedSignupHeading' in container.dataset) {
              heading.innerHTML = stashedHeading;
            }
            if (subheading && 'lpStashedSignupSubheading' in container.dataset) {
              subheading.innerHTML = stashedSubheading;
            }

            delete container.dataset.lpStashedSignupHtml;
            delete container.dataset.lpStashedSignupHeading;
            delete container.dataset.lpStashedSignupSubheading;

            const signupForm = qs(container, 'form[data-step="signup"]');
            const pw = signupForm && qs(signupForm, 'input[name="password"]');
            if (pw) pw.focus();

            if (signupForm) {
              document.dispatchEvent(new CustomEvent('lp_list_builder_signup_form_rendered', { detail: { form: signupForm } }));
            }

            return;
          }

          if (step == "signup") {
            const payload = Object.fromEntries(fd.entries());
            payload.current_url = window.location.href.split("#")[0];
            // Send the post ID straight from the page so attribution doesn't
            // depend on url_to_postid(), which fails on tagged URLs (?utm_source=…).
            payload.nag_loc = getCurrentPostId();

            if (typeof window.lpListBuilderPreSignup === 'function') {
              await window.lpListBuilderPreSignup(payload);
            }

            await postJson(window.LP_LIST_BUILDER.signupUrl, payload);

            clearRestrictionData();
            showSuccessAndReload(form, "Your account has been created. Unlocking content...");

            return;
          }

          if (step == "password") {
            const email = (fd.get("email") || "").toString().trim();
            const password = (fd.get("password") || "").toString();

            await postJson(window.LP_LIST_BUILDER.loginUrl, {
              email,
              password,
              current_url: window.location.href.split("#")[0],
            });

            showSuccessAndReload(form, "Signing you in…", "Welcome back!");
            return;
          }

          if (step === "reset-code") {
            const email = (fd.get("email") || "").toString().trim();
            const code = (fd.get("code") || "").toString().trim();

            const data = await postJson(window.LP_LIST_BUILDER.pwResetVerifyUrl, { email, code });
            applyResponse(container, data);

            container.querySelector('input[name="password"]')?.focus();
            return;
          }

          if (step === "reset-new-password") {
            const email = (fd.get("email") || "").toString().trim();
            const token = (fd.get("token") || "").toString();
            const password = (fd.get("password") || "").toString();

            await postJson(window.LP_LIST_BUILDER.pwResetConfirmUrl, { email, token, password });

            showSuccessAndReload(form, "Signing you in…", "Welcome back!");
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
              if (!window.LP_LIST_BUILDER.upgradeEnabled) { return; }
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