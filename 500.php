<?php
http_response_code(500);
$page_title = 'Server Error';
$page_description = 'An unexpected error occurred on our server. Please try again or contact NettingTeam directly.';
$page_robots = 'noindex, nofollow';
include 'includes/header.php';
?>

<!-- 500 Error Section -->
<section class="hero" style="padding: 10rem 0 6rem; min-height: 70vh;">
    <div class="container text-center">
        <div class="hero-content">
            <div style="font-size: 8rem; color: var(--color-primary); margin-bottom: 1rem;">
                <i class="fas fa-tools"></i>
            </div>
            <h1 class="hero-title" style="font-size: 6rem; color: var(--color-primary);">500</h1>
            <h2 style="margin-bottom: 1.5rem;">Internal Server Error</h2>
            <p class="hero-subtitle" style="max-width: 520px;">
                Something went wrong on our end. Our team has been notified. 
                Please try again shortly or reach us directly — we're always available!
            </p>
            <div class="hero-buttons" style="margin-top: 2rem;">
                <a href="index.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-home"></i> Go to Homepage
                </a>
                <a href="tel:+919912399334" class="btn btn-secondary btn-lg">
                    <i class="fas fa-phone"></i> Call: 9912399334
                </a>
            </div>
            <p style="margin-top: 1.5rem; color: var(--color-gray-500); font-size: 0.9rem;">
                Or WhatsApp us at 
                <a href="https://wa.me/919912399334" target="_blank" style="color: var(--color-primary); font-weight: 600;">+919912399334</a>
            </p>
        </div>
    </div>
</section>

<!-- Quick Links Section -->
<section class="section" style="background: rgba(220, 38, 38, 0.05);">
    <div class="container">
        <h2 class="section-title">While We Fix This, Explore Our Services</h2>
        <p class="section-subtitle">Here are some helpful links to get you started</p>

        <div class="grid grid-3">
            <div class="card">
                <div class="feature-icon">
                    <i class="fas fa-home"></i>
                </div>
                <h4>Balcony Safety Nets</h4>
                <p>Protect your family with premium balcony safety nets.</p>
                <a href="service-balcony-safety-nets.php" class="btn btn-secondary" style="margin-top: 1rem;">
                    Learn More <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <div class="card">
                <div class="feature-icon">
                    <i class="fas fa-border-all"></i>
                </div>
                <h4>Invisible Grills</h4>
                <p>Modern safety with unobstructed views.</p>
                <a href="service-invisible-grills.php" class="btn btn-secondary" style="margin-top: 1rem;">
                    Learn More <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <div class="card">
                <div class="feature-icon">
                    <i class="fas fa-dove"></i>
                </div>
                <h4>Bird &amp; Pigeon Nets</h4>
                <p>Keep your property clean and bird-free.</p>
                <a href="service-bird-nets.php" class="btn btn-secondary" style="margin-top: 1rem;">
                    Learn More <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
