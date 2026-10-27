<?php
$page_title = 'Gallery – Our Work Portfolio & Product Photos';
$page_description = 'View our portfolio of safety net installations, invisible grills, bird nets, sports nets and real product photos from our Hyderabad showroom. NettingTeam — Authorized Russea™ Dealer.';
$page_keywords = 'safety nets gallery hyderabad, invisible grills photos hyderabad, bird nets installation photos, gcm enterprises gallery, russea™ nets hyderabad photos, safety net work hyderabad';
$page_canonical = 'https://nettingteam.com/gallery';
$breadcrumbs = [['name' => 'Gallery']];
include 'includes/header.php';
?>

<!-- Page Header -->
<section class="hero" style="padding: 8rem 0 3rem;">
    <div class="container">
        <div class="hero-content">
            <h1 class="hero-title">Our Gallery</h1>
            <p class="hero-subtitle">
                Explore our portfolio of professional installations across Hyderabad
            </p>
        </div>
    </div>
</section>

<!-- Gallery Section -->
<section class="section">
    <div class="container">
        <h2 class="section-title">Our Work Portfolio</h2>
        <p class="section-subtitle">
            Browse through our completed installations and projects
        </p>
        
        <!-- Filter Buttons -->
        <div class="gallery-filters text-center" style="margin-bottom: 2rem;">
            <button class="btn btn-secondary gallery-filter active" data-filter="all">All</button>
            <button class="btn btn-secondary gallery-filter" data-filter="safety">Safety Nets</button>
            <button class="btn btn-secondary gallery-filter" data-filter="bird">Bird Nets</button>
            <button class="btn btn-secondary gallery-filter" data-filter="sports">Sports Nets</button>
            <button class="btn btn-secondary gallery-filter" data-filter="turf">Football Turf &amp; Cricket</button>
            <button class="btn btn-secondary gallery-filter" data-filter="grills">Invisible Grills</button>
            <button class="btn btn-secondary gallery-filter" data-filter="cloth">Cloth Hangers</button>
            <button class="btn btn-secondary gallery-filter" data-filter="stock">Showroom &amp; Stock</button>
        </div>
        
        <!-- Gallery Grid -->
        <div class="gallery-grid">
            <!-- Row 1 -->
            <div class="gallery-item" data-category="safety">
                <img src="images/hero/balcony-safety-nets.jpg" alt="Balcony Safety Net Installation" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Balcony Safety Net</h4>
                    <p>High-rise apartment installation</p>
                </div>
            </div>
            
            <div class="gallery-item" data-category="bird">
                <img src="images/hero/hdpe-bird-nets.jpg" alt="HDPE Bird Net Installation" loading="lazy">
                <div class="gallery-overlay">
                    <h4>HDPE Bird Net</h4>
                    <p>UV stabilized netting</p>
                </div>
            </div>
            
            <div class="gallery-item" data-category="bird">
                <img src="images/hero/invisible-pigeon-mesh.jpg" alt="Invisible Pigeon Mesh" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Invisible Pigeon Mesh</h4>
                    <p>Transparent bird protection</p>
                </div>
            </div>
            
            <div class="gallery-item" data-category="grills">
                <img src="images/hero/invisible-grills.jpg" alt="Invisible Grill Installation" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Invisible Grills</h4>
                    <p>Stainless steel 316 grade</p>
                </div>
            </div>
            
            <!-- Row 2 -->
            <div class="gallery-item" data-category="sports">
                <img src="images/hero/sports-nets.jpg" alt="Sports Net Installation" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Sports Nets</h4>
                    <p>Cricket practice nets</p>
                </div>
            </div>
            
            <div class="gallery-item" data-category="safety">
                <img src="images/hero/balconies.jpg" alt="Balcony Protection" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Balcony Protection</h4>
                    <p>Child & pet safety</p>
                </div>
            </div>
            
            <div class="gallery-item" data-category="cloth">
                <img src="images/hero/cloth-hangers.jpg" alt="Cloth Hangers" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Ceiling Cloth Hangers</h4>
                    <p>Pulley-based system</p>
                </div>
            </div>
            
            <div class="gallery-item" data-category="safety">
                <img src="images/hero/gallery1.jpg" alt="Safety Net Project" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Invisible Grill Project</h4>
                    <p>Residential installation</p>
                </div>
            </div>
            
            <!-- Row 3 -->
            <div class="gallery-item" data-category="bird">
                <img src="images/hero/hero-pigeon.jpg" alt="Pigeon Net Installation" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Pigeon Net</h4>
                    <p>Anti-bird protection</p>
                </div>
            </div>
            
            <div class="gallery-item" data-category="sports">
                <img src="images/hero/hero-sports.jpg" alt="Sports Netting" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Sports Netting</h4>
                    <p>Ball stop netting</p>
                </div>
            </div>
            
            <div class="gallery-item" data-category="grills">
                <img src="images/hero/hero-grill.jpg" alt="Invisible Grill" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Invisible Grill</h4>
                    <p>Modern safety solution</p>
                </div>
            </div>
            
            <div class="gallery-item" data-category="safety">
                <img src="images/hero/gallery4.jpg" alt="Safety Net Work" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Cloth Hangers Work</h4>
                    <p>Professional installation</p>
                </div>
            </div>
            
            <!-- Row 4 -->
            <div class="gallery-item" data-category="safety">
                <img src="images/hero/slider1.jpg" alt="Safety Net Installation" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Balcony Safety</h4>
                    <p>Complete protection</p>
                </div>
            </div>
            
            <div class="gallery-item" data-category="bird">
                <img src="images/hero/slider2.jpg" alt="Bird Net Project" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Invisible Grill Project</h4>
                    <p>Terrace protection</p>
                </div>
            </div>
            
            <div class="gallery-item" data-category="cloth">
                <img src="images/hero/cloth2.jpg" alt="Cloth Drying System" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Cloth Drying System</h4>
                    <p>Space-saving design</p>
                </div>
            </div>
            
            <div class="gallery-item" data-category="safety">
                <img src="images/hero/slider4.jpg" alt="Net Installation" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Dry Cloth Hangers</h4>
                    <p>Quality workmanship</p>
                </div>
            </div>

            <!-- Showroom & Real Product Photos -->
            <div class="gallery-item" data-category="stock">
                <img src="images/storefront.jpeg" alt="NettingTeam Showroom Karmanghat Hyderabad" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Our Showroom</h4>
                    <p>Walk-in welcome — Karmanghat</p>
                </div>
            </div>

            <div class="gallery-item" data-category="stock">
                <img src="images/stock-warehouse-1.jpeg" alt="Russea™ HDPE Net Stock — 100+ Variants" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Ready Stock</h4>
                    <p>100+ net variants always available</p>
                </div>
            </div>

            <div class="gallery-item" data-category="stock">
                <img src="images/stock-warehouse-2.jpeg" alt="Safety Net Warehouse Stock Hyderabad" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Warehouse Stock</h4>
                    <p>Same-day dispatch available</p>
                </div>
            </div>

            <div class="gallery-item" data-category="stock">
                <img src="images/stock-warehouse-3.jpeg" alt="Russea™ Net Stock — Walk In Today" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Walk-In Stock</h4>
                    <p>Inspect before you buy</p>
                </div>
            </div>

            <div class="gallery-item" data-category="stock">
                <img src="images/product-green-net.jpeg" alt="Russea™ HDPE Green Sports Net Quality" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Russea™ HDPE Net</h4>
                    <p>Check quality in-store</p>
                </div>
            </div>

            <div class="gallery-item" data-category="stock">
                <img src="images/product-quality-check.jpeg" alt="Russea™ HDPE Net Quality Check" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Quality Check</h4>
                    <p>ISO 9001:2015 certified material</p>
                </div>
            </div>

            <div class="gallery-item" data-category="stock">
                <img src="images/product-twine-spool.jpeg" alt="Russea™ HDPE Twine Spool" loading="lazy">
                <div class="gallery-overlay">
                    <h4>HDPE Twine Spool</h4>
                    <p>Premium Russea™ fibre</p>
                </div>
            </div>

            <div class="gallery-item" data-category="stock">
                <img src="images/stock-russea™-label.jpeg" alt="Russea™ Authorized Dealer Label" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Authorized Dealer</h4>
                    <p>Genuine Russea™ branded stock</p>
                </div>
            </div>
            <!-- Football Turf & Box Cricket Ground Setup Images -->
            <div class="gallery-item" data-category="turf sports">
                <img src="images/hero/sports-complex-overview.jpg" alt="Football Turf and Box Cricket Sports Complex Hyderabad" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Sports Complex — Football + Cricket</h4>
                    <p>Complete turnkey sports facility Hyderabad</p>
                </div>
            </div>
            <div class="gallery-item" data-category="turf">
                <img src="images/hero/turf-7aside-aerial.jpg" alt="7-a-Side Football Turf Aerial View Hyderabad" loading="lazy">
                <div class="gallery-overlay">
                    <h4>7-a-Side Football Turf — Aerial</h4>
                    <p>50mm PE turf, MS cage, LED floodlights</p>
                </div>
            </div>
            <div class="gallery-item" data-category="turf">
                <img src="images/hero/turf-7aside-night.jpg" alt="Football Turf Night LED Floodlights Hyderabad" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Football Turf at Night</h4>
                    <p>200+ lux professional LED lighting</p>
                </div>
            </div>
            <div class="gallery-item" data-category="turf">
                <img src="images/hero/turf-construction.jpg" alt="Football Turf Construction Ground Preparation Hyderabad" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Turf Ground Construction</h4>
                    <p>Sub-base layers and artificial turf installation</p>
                </div>
            </div>
            <div class="gallery-item" data-category="turf sports">
                <img src="images/hero/box-cricket-aerial.jpg" alt="Box Cricket Court Setup Aerial View Hyderabad" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Box Cricket Court — Aerial View</h4>
                    <p>MS cage, HDPE nets, full roof enclosure</p>
                </div>
            </div>
            <div class="gallery-item" data-category="turf sports">
                <img src="images/hero/box-cricket-inside.jpg" alt="Box Cricket Court Inside View Pitch and Nets" loading="lazy">
                <div class="gallery-overlay">
                    <h4>Box Cricket — Inside View</h4>
                    <p>Cricket pitch mat, outfield turf &amp; LED lit cage</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="section" style="background: rgba(220, 38, 38, 0.05);">
    <div class="container">
        <div class="grid grid-4">
            <div class="card text-center">
                <div style="font-size: 2.5rem; color: var(--color-primary); font-weight: 700;">5000+</div>
                <p style="margin: 0; color: var(--color-gray-600);">Installations Completed</p>
            </div>
            <div class="card text-center">
                <div style="font-size: 2.5rem; color: var(--color-primary); font-weight: 700;">10+</div>
                <p style="margin: 0; color: var(--color-gray-600);">Years Experience</p>
            </div>
            <div class="card text-center">
                <div style="font-size: 2.5rem; color: var(--color-primary); font-weight: 700;">100%</div>
                <p style="margin: 0; color: var(--color-gray-600);">Customer Satisfaction</p>
            </div>
            <div class="card text-center">
                <div style="font-size: 2.5rem; color: var(--color-primary); font-weight: 700;">5 Year</div>
                <p style="margin: 0; color: var(--color-gray-600);">Warranty</p>
            </div>
        </div>
    </div>
</section>

<!-- Contact CTA -->
<section class="section">
    <div class="container text-center">
        <h2 class="section-title">Ready to Get Started?</h2>
        <p class="section-subtitle">
            Contact us today for a free consultation and quote
        </p>
        <div class="hero-buttons">
            <a href="contact.php" class="btn btn-primary btn-lg">
                <i class="fas fa-comment-dots"></i> Get Free Quote
            </a>
            <a href="tel:+919912399334" class="btn btn-secondary btn-lg">
                <i class="fas fa-phone"></i> Call Now
            </a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
