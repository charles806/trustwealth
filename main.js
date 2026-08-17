document.addEventListener("DOMContentLoaded", () => {
    /* Responsive mobile nav */
    const menuBtn = document.getElementById("menu-btn");
    const navLinks = document.getElementById("nav-links");

    if (menuBtn && navLinks) {
        const closeMenu = () => {
            navLinks.classList.remove("active");
            menuBtn.setAttribute("aria-expanded", "false");
            menuBtn.innerHTML = "&#9776;";
        };

        menuBtn.addEventListener("click", () => {
            const isOpen = navLinks.classList.toggle("active");
            menuBtn.setAttribute("aria-expanded", String(isOpen));
            menuBtn.innerHTML = isOpen ? "&#10006;" : "&#9776;";
        });

        navLinks.addEventListener("click", (e) => {
            if (e.target.closest("a")) closeMenu();
        });
    }

    /* Currency converter + live clock */
    const btcInput = document.getElementById("btcAmount");
    const usdInput = document.getElementById("usdAmount");
    const priceValue = document.getElementById("price-value");
    const clock = document.getElementById("clock");
    const BTC_TO_USD_RATE = 94500;

    const formatUsd = (n) =>
        n.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    if (btcInput && usdInput) {
        usdInput.placeholder = "$ 0.00";

        btcInput.addEventListener("input", (e) => {
            const btc = parseFloat(e.target.value);

            if (!isNaN(btc) && btc > 0) {
                const usd = btc * BTC_TO_USD_RATE;
                usdInput.value = "$ " + formatUsd(usd);
            } else {
                usdInput.value = "";
            }
        });
    }

    if (priceValue) {
        priceValue.textContent = "$" + formatUsd(BTC_TO_USD_RATE);
    }

    if (clock) {
        const tick = () => {
            const now = new Date();
            const hh = String(now.getUTCHours()).padStart(2, "0");
            const mm = String(now.getUTCMinutes()).padStart(2, "0");
            const ss = String(now.getUTCSeconds()).padStart(2, "0");
            clock.textContent = hh + ":" + mm + ":" + ss + " UTC";
        };
        tick();
        setInterval(tick, 1000);
    }

    /* Signup validation */
    const form = document.getElementById("signup-form");

    if (form) {
        const email = document.getElementById("email");
        const confirmEmail = document.getElementById("confirm_email");
        const password = document.getElementById("password");
        const confirmPassword = document.getElementById("confirm_password");
        const emailError = document.getElementById("email-error");
        const passwordError = document.getElementById("password-error");

        const setInvalid = (field, error, invalid) => {
            field.classList.toggle("invalid", invalid);
            error.classList.toggle("show", invalid);
        };

        const validateEmail = () => {
            const match = email.value.trim() === confirmEmail.value.trim();
            setInvalid(confirmEmail, emailError, confirmEmail.value.length > 0 && !match);
            return match || confirmEmail.value.trim() === "";
        };

        const validatePassword = () => {
            const match = password.value === confirmPassword.value;
            setInvalid(confirmPassword, passwordError, confirmPassword.value.length > 0 && !match);
            return match || confirmPassword.value === "";
        };

        confirmEmail.addEventListener("input", validateEmail);
        confirmPassword.addEventListener("input", validatePassword);
        email.addEventListener("input", () => setInvalid(confirmEmail, emailError, false));

        form.addEventListener("submit", (e) => {
            const emailsOk = validateEmail();
            const passwordsOk = validatePassword();

            if (!emailsOk || !passwordsOk) {
                e.preventDefault();
                (emailsOk ? confirmPassword : confirmEmail)?.focus();
                return;
            }

            e.preventDefault();
            alert("Account creation is not wired up yet — this demo would submit to the server.");
        });
    }
});