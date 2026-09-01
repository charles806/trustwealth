document.addEventListener("DOMContentLoaded", () => {
    /* Payout countdown — the active plan's clock to maturity. */
    const countdown = document.getElementById("payout-countdown");

    if (countdown) {
        const target = parseInt(countdown.dataset.target, 10);
        const pad = (n) => String(n).padStart(2, "0");

        const tick = () => {
            let diff = target - Math.floor(Date.now() / 1000);
            if (diff < 0) diff = 0;

            const h = Math.floor(diff / 3600);
            const m = Math.floor((diff % 3600) / 60);
            const s = diff % 60;

            countdown.textContent = pad(h) + ":" + pad(m) + ":" + pad(s);
        };

        tick();
        setInterval(tick, 1000);
    }

    /* Copy deposit addresses. */
    document.querySelectorAll("[data-copy]").forEach((btn) => {
        btn.addEventListener("click", () => {
            const source = document.getElementById(btn.dataset.copy);
            if (!source || !navigator.clipboard) return;

            navigator.clipboard.writeText(source.textContent.trim()).then(() => {
                const original = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i>Copied';
                setTimeout(() => {
                    btn.innerHTML = original;
                }, 1600);
            });
        });
    });

    /* Copy a deposit reference code (admin operator console). */
    document.querySelectorAll("[data-copy-ref]").forEach((btn) => {
        btn.addEventListener("click", () => {
            const source = document.getElementById(btn.dataset.copyRef);
            if (!source || !navigator.clipboard) return;

            navigator.clipboard.writeText(source.textContent.trim()).then(() => {
                const original = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i>Copied';
                setTimeout(() => {
                    btn.innerHTML = original;
                }, 1600);
            });
        });
    });

    /* Coin toggle — swap the highlighted address card. */
    const coinRadios = document.querySelectorAll('.coin-toggle input[name="coin"]');
    coinRadios.forEach((radio) => {
        radio.addEventListener("change", () => {
            const coin = radio.value;
            coinRadios.forEach((r) => {
                r.closest("label").querySelector(".coin-opt").classList.toggle("active", r.value === coin);
            });
            document.querySelectorAll("[data-coin]").forEach((card) => {
                card.style.display = card.dataset.coin === coin ? "block" : "none";
            });
        });
    });
});