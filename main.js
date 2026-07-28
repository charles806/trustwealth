document.addEventListener("DOMContentLoaded", () => {
    // Responsive Mobile Navbar Controls
    const menuBtn = document.getElementById("menu-btn");
    const navLinks = document.getElementById("nav-links");

    if (menuBtn && navLinks) {
        menuBtn.addEventListener("click", () => {
            navLinks.classList.toggle("active");
            menuBtn.innerHTML = navLinks.classList.contains("active") ? "&#10006;" : "&#9776;";
        });
    }

    // Dynamic Exchange Currency Calculator System
    const btcInput = document.getElementById("btcAmount");
    const usdInput = document.getElementById("usdAmount");
    const BTC_TO_USD_RATE = 94500;

    if (btcInput && usdInput) {
        usdInput.placeholder = "$ 0.00";

        btcInput.addEventListener("input", (e) => {
            const btcValue = parseFloat(e.target.value);

            if (!isNaN(btcValue) && btcValue > 0) {
                const calculatedUsd = btcValue * BTC_TO_USD_RATE;
                usdInput.value = "$ " + calculatedUsd.toLocaleString('en-US', { 
                    minimumFractionDigits: 2, 
                    maximumFractionDigits: 2 
                });
            } else {
                usdInput.value = "";
            }
        });
    }

    // Purchase Actions Access Hook
    const buyBtn = document.getElementById("buyBtn");
    if (buyBtn) {
        buyBtn.addEventListener("click", () => {
            alert("Redirecting to secure purchase portal...");
        });
    }
});