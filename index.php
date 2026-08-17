<?php
declare(strict_types=1);

require __DIR__ . '/app/config.php';
require __DIR__ . '/app/auth.php';
require __DIR__ . '/app/helpers.php';

$isAuth = current_user() !== null;
$pageTitle = 'Bitcoin &amp; USDT Investing';
$pageDescription = 'Trust Wealth Ltd manages pooled capital with a long-term, transparent philosophy. Trade Bitcoin and USDT on plans that pay out daily.';

include __DIR__ . '/partials/header.php';
?>

<main id="main">
    <section class="hero" aria-label="Introduction">
        <div class="hero-grid">
            <div>
                <p class="eyebrow">BTC · USDT trading platform</p>
                <h1>Let your capital <span class="accent">work the market</span> for you.</h1>
                <p class="hero-sub">Trust Wealth Ltd pools client capital and trades it with a long-term, transparent philosophy — paying out daily.</p>
                <div class="hero-actions">
                    <?php if ($isAuth) : ?>
                        <a href="dashboard.php" class="btn btn-gold btn-lg">Open dashboard</a>
                        <a href="#plans" class="btn btn-link btn-lg">View plans &#8595;</a>
                    <?php else : ?>
                        <a href="signup.php" class="btn btn-gold btn-lg">Get started</a>
                        <a href="login.php" class="btn btn-link btn-lg">Log in &#8594;</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ledger" id="rates" aria-label="Bitcoin to US dollar converter">
                <div class="ledger-head">
                    <span class="pair"><span class="live-dot"></span>BTC / USD · live</span>
                    <span class="ledger-time" id="clock">&#8212;</span>
                </div>
                <p class="price-line">1 BTC = <span class="gold" id="price-value">$94,500.00</span></p>

                <div class="converter">
                    <div class="field">
                        <input type="number" id="btcAmount" inputmode="decimal" placeholder="0.00" aria-label="Bitcoin amount" min="0" step="any">
                        <span class="unit">BTC</span>
                    </div>
                    <div class="field">
                        <input type="text" id="usdAmount" readonly aria-label="US dollar value">
                        <span class="unit muted">USD</span>
                    </div>
                </div>

                <a href="signup.php" class="btn btn-gold btn-block">Buy now</a>
                <p class="ledger-note">Rates settle at market close. <a href="#plans">See the plans &#8594;</a></p>
            </div>
        </div>
    </section>

    <section class="stat-band" aria-label="Trust Wealth in numbers">
        <div class="stat-grid">
            <div class="stat">
                <span class="num">5<span class="gold">+</span></span>
                <span class="lbl">Years trading</span>
            </div>
            <div class="stat">
                <span class="num">40<span class="gold">+</span></span>
                <span class="lbl">Countries covered</span>
            </div>
            <div class="stat">
                <span class="num">100<span class="gold">%</span></span>
                <span class="lbl">Capital protection</span>
            </div>
            <div class="stat">
                <span class="num">50<span class="gold">%</span></span>
                <span class="lbl">Top plan return</span>
            </div>
        </div>
    </section>

    <section class="section" id="about">
        <div class="about-grid">
            <figure class="figure">
                <img src="Img/office.jpeg" alt="The Trust Wealth team at work in the Regensburg office">
                <figcaption>Team &#8212; Regensburg office</figcaption>
            </figure>
            <div class="about-copy">
                <p class="eyebrow">About the firm</p>
                <h2>We act for our clients, not against them.</h2>
                <p>Trust Wealth Ltd approaches investment differently than many firms or banks. We serve our clients as a fiduciary &#8212; their interests first, full transparency, and a long-term philosophy that market fads and media hype can&#8217;t touch.</p>
                <ul class="about-list">
                    <li>Enhanced knowledge</li>
                    <li>Shared passion</li>
                    <li>Acts of integrity</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="section-head">
            <p class="eyebrow">Why Trust Wealth</p>
            <h2>Licensed, transparent, immediate</h2>
            <p>About five years of steady engagement in wealth management &#8212; enough to build an investment model that most partners are only now discovering.</p>
        </div>
        <div class="card-grid">
            <div class="card">
                <div class="card-icon"><i class="fa-solid fa-chart-line"></i></div>
                <h3>Licensed investments</h3>
                <p>All the licenses needed to trade with our clients&#8217; funds.</p>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                <h3>No hidden fees</h3>
                <p>Every fee, profit and charge is plainly on the table.</p>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fa-solid fa-money-bill-transfer"></i></div>
                <h3>Instant trading</h3>
                <p>No hassle or delay &#8212; start trading immediately.</p>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <h3>Secure &amp; trusted</h3>
                <p>Security and customer satisfaction are our watchwords.</p>
            </div>
        </div>
    </section>

    <section class="section" id="plans">
        <div class="section-head">
            <p class="eyebrow">Plans</p>
            <h2>Pick a plan, watch it work</h2>
            <p>Every plan includes automated payouts, AI-integrated trading and a 10% referral bonus.</p>
        </div>

        <div class="plans-grid">
            <div class="plan">
                <p class="plan-name">Basic</p>
                <p class="plan-range">$50 &#8211; $3,000</p>
                <ul>
                    <li>100% capital protection</li>
                    <li>Automated payout</li>
                    <li>AI-integrated trading</li>
                    <li>Renewable anytime</li>
                </ul>
                <p class="plan-yield">10% after 24 hours</p>
                <a href="signup.php" class="btn btn-ghost">Get started</a>
            </div>

            <div class="plan">
                <p class="plan-name">Business</p>
                <p class="plan-range">$3,500 &#8211; $9,999</p>
                <ul>
                    <li>100% capital protection</li>
                    <li>Automated payout</li>
                    <li>AI-integrated trading</li>
                    <li>Renewable anytime</li>
                </ul>
                <p class="plan-yield">15% after 3 days</p>
                <a href="signup.php" class="btn btn-ghost">Get started</a>
            </div>

            <div class="plan plan-gold">
                <span class="plan-badge">Featured</span>
                <p class="plan-name">Gold</p>
                <p class="plan-range">$19,000 &#8211; Unlimited</p>
                <ul>
                    <li>100% capital protection</li>
                    <li>Automated payout</li>
                    <li>AI-integrated trading</li>
                    <li>Renewable anytime</li>
                </ul>
                <p class="plan-yield">30% after 7 days</p>
                <a href="signup.php" class="btn btn-gold">Get started</a>
            </div>

            <div class="plan">
                <p class="plan-name">Advanced</p>
                <p class="plan-range">$100,000+</p>
                <ul>
                    <li>100% capital protection</li>
                    <li>Automated payout</li>
                    <li>AI-integrated trading</li>
                    <li>Renewable anytime</li>
                </ul>
                <p class="plan-yield">50% after 1 month</p>
                <a href="signup.php" class="btn btn-ghost">Get started</a>
            </div>
        </div>
    </section>

    <section class="section payment" id="payment">
        <div class="section-head">
            <p class="eyebrow">Payment options</p>
            <h2>We keep your assets safe</h2>
            <p>Your security and trust come first. Deposits and returns settle in the two currencies below.</p>
        </div>
        <div class="payment-strip">
            <span class="pill"><i class="fa-brands fa-bitcoin ico"></i> Bitcoin <span class="sub">BTC</span></span>
            <span class="pill"><i class="fa-solid fa-coins ico"></i> Tether <span class="sub">USDT</span></span>
        </div>
    </section>

    <section class="section" id="how">
        <div class="section-head">
            <p class="eyebrow">How it works</p>
            <h2>Three steps to your first payout</h2>
        </div>
        <div class="steps">
            <div class="step">
                <span class="step-num">01</span>
                <div>
                    <h3>Sign up</h3>
                    <p>Create your account &#8212; it takes a few minutes, no paper.</p>
                </div>
            </div>
            <div class="step">
                <span class="step-num">02</span>
                <div>
                    <h3>Make a deposit</h3>
                    <p>Fund your wallet with BTC or USDT, then buy, invest and reinvest.</p>
                </div>
            </div>
            <div class="step">
                <span class="step-num">03</span>
                <div>
                    <h3>Invest, then collect</h3>
                    <p>Choose a plan and start receiving payouts every day.</p>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
include __DIR__ . '/partials/footer.php';