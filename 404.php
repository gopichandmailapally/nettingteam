<?php
http_response_code(404);
$page_title = 'Page Not Found';
$page_description = 'The page you are looking for could not be found. Return to NettingTeam homepage.';
$page_robots = 'noindex, nofollow';
include 'includes/header.php';
?>

<!-- 404 Error Section -->
<section class="hero" style="padding: 10rem 0 6rem; min-height: 70vh;">
    <div class="container text-center">
        <div class="hero-content">
            <div style="font-size: 8rem; color: var(--color-primary); margin-bottom: 1rem;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1 class="hero-title" style="font-size: 6rem; color: var(--color-primary);">404</h1>
            <h2 style="margin-bottom: 1.5rem;">Page Not Found</h2>
            <p class="hero-subtitle" style="max-width: 500px;">
                Oops! The page you're looking for doesn't exist or has been moved. 
                Let's get you back on track.
            </p>
            <div class="hero-buttons" style="margin-top: 2rem;">
                <a href="index.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-home"></i> Go to Homepage
                </a>
                <a href="contact.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-phone"></i> Contact Us
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Quick Links Section -->
<section class="section" style="background: rgba(102, 126, 234, 0.05);">
    <div class="container">
        <h2 class="section-title">Looking for Something?</h2>
        <p class="section-subtitle">
            Here are some helpful links to get you started
        </p>
        
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
                <h4>Bird & Pigeon Nets</h4>
                <p>Keep your property clean and bird-free.</p>
                <a href="service-bird-nets.php" class="btn btn-secondary" style="margin-top: 1rem;">
                    Learn More <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
