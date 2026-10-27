<?php require_once __DIR__ . '/init.php'; ?>
    <!-- Footer -->
    <?php require_once __DIR__ . '/company.php'; ?>
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <!-- Company Info -->
                <div class="footer-section">
                    <div style="margin-bottom: 1rem;">
                        <img src="/images/logos/logo-footer.png" alt="NettingTeam — Safety Nets Hyderabad" style="height: 70px; width: auto;">
                    </div>
                    <p style="color: #94a3b8; font-size: 0.9rem; line-height: 1.6; margin-bottom: 1rem;">
                        India’s premier safety infrastructure provider with 10+ years of engineering excellence, 50,000+ completed installations, and official 5-year replacement warranty.
                    </p>
                    
                    <div style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 12px; margin-bottom: 1.2rem; font-size: 0.82rem; color: #cbd5e1; display: flex; flex-direction: column; gap: 6px;">
                        <div>🏢 <strong>Company:</strong> NettingTeam</div>
                        <div>📋 <strong>GSTIN:</strong> <span style="color:#34d399; font-weight:800; font-family:monospace; background:rgba(5,150,105,0.18); padding:2px 6px; border-radius:4px;">36EBFPM5722R2ZY</span></div>
                        <div>🛡️ <strong>Partner:</strong> 100% Genuine Russea™</div>
                    </div>
                    <p style="color: var(--color-gray-400); margin-bottom: 1rem;">
                        Your trusted partner for premium safety solutions in Hyderabad. 
                        Quality products, professional installation, and excellent service.
                    </p>
                    <div class="social-links">
                        <a href="https://www.youtube.com/@gcmsafetynets" target="_blank" rel="noopener noreferrer" class="social-icon" aria-label="NettingTeam on YouTube" style="background: #dc2626;">
                            <i class="fab fa-youtube"></i>
                        </a>
                        <a href="<?= getSmartWhatsAppUrl() ?>" target="_blank" rel="noopener noreferrer" class="social-icon" aria-label="Chat with NettingTeam on WhatsApp" style="background: #25d366;">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <a href="https://g.page/r/CYsaqtWlebHrEBM/review" target="_blank" rel="noopener noreferrer" class="social-icon" aria-label="Review NettingTeam on Google" style="background: #ea4335;">
                            <i class="fab fa-google"></i>
                        </a>
                        <a href="https://www.justdial.com/Hyderabad/Gcm-Enterprises-Near-Karmanghat/040PXX40-XX40-221019145242-W4L4_BZDET" target="_blank" rel="noopener noreferrer" class="social-icon" aria-label="NettingTeam on JustDial" style="background: #ff6600;">
                            <i class="fas fa-map-marker-alt"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="index.php" class="footer-link">Home</a></li>
                        <li><a href="about.php" class="footer-link">About Us</a></li>
                        <li><a href="services.php" class="footer-link">Our Services</a></li>
                        <li><a href="contact.php" class="footer-link">Contact Us</a></li>
                        <li><a href="privacy-policy.php" class="footer-link">Privacy Policy</a></li>
                        <li><a href="terms-of-service.php" class="footer-link">Terms of Service</a></li>
                    </ul>
                </div>
                
                <!-- Services -->
                <div class="footer-section">
                    <h4>Our Services</h4>
                    <ul class="footer-links">
                        <li><a href="service-balcony-safety-nets.php" class="footer-link">Balcony Safety Nets</a></li>
                        <li><a href="service-bird-nets.php" class="footer-link">Bird Nets</a></li>
                        <li><a href="service-pigeon-nets.php" class="footer-link">Pigeon Nets</a></li>
                        <li><a href="service-invisible-grills.php" class="footer-link">Invisible Grills</a></li>
                        <li><a href="service-sports-nets.php" class="footer-link">Sports Nets</a></li>
                        <li><a href="service-cloth-hangers.php" class="footer-link">Cloth Hangers</a></li>
                    </ul>
                </div>
                
                <!-- Contact & Registered Office -->
                <div class="footer-section">
                    <h4>Registered Head Office</h4>
                    <ul class="footer-links">
                        <li style="color: #cbd5e1; margin-bottom: 0.6rem; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-phone" style="color: #34d399;"></i> 
                            <a href="tel:+919912399334" class="footer-link" style="font-weight: 700;">+919912399334</a>
                        </li>
                        <li style="color: #cbd5e1; margin-bottom: 0.6rem; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-envelope" style="color: #38bdf8;"></i> 
                            <a href="mailto:contact@nettingteam.com" class="footer-link">contact@nettingteam.com</a>
                        </li>
                        <li style="color: #cbd5e1; line-height:1.6; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); padding: 10px 12px; border-radius: 8px; margin-top: 6px;">
                            <i class="fas fa-map-marker-alt" style="color:#f59e0b"></i> 
                            <strong>Plot No.91, Road No.2,</strong><br>
                            Sri Ram Nagar Colony, Karmanghat,<br>
                            Saroornagar - 500035, Hyderabad, Telangana<br>
                            <a href="<?= htmlspecialchars(gcm_maps_url(), ENT_QUOTES) ?>" target="_blank" rel="noopener noreferrer" style="color:#38bdf8;font-weight:600;display:inline-flex;align-items:center;gap:5px;margin-top:6px;font-size:0.82rem;">
                                <i class="fas fa-directions"></i> Get Directions &amp; Visit Showroom
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
        <!-- SEO Area Coverage Grid -->
        <div style="border-top:1px solid #2d2d2d;padding:2rem 0 1rem">
            <h3 style="color:#9ca3af;font-size:0.85rem;font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-bottom:1.5rem">Safety Nets &amp; Services Across Greater Hyderabad &amp; Secunderabad</h3>
            <?php
            $footer_states = [
                'West Hyderabad (IT Corridor)' => [
                    ['Gachibowli','safety-nets'],['Madhapur','bird-nets'],['HiTech City','invisible-grills'],
                    ['Kondapur','pigeon-nets'],['Financial District','safety-nets'],['Manikonda','balcony-safety-nets'],
                    ['Kokapet','invisible-grills'],['Tellapur','safety-nets'],['Miyapur','sports-nets'],
                    ['Kukatpally','pigeon-nets'],['KPHB','safety-nets'],['Chandanagar','bird-nets'],
                    ['Bachupally','safety-nets'],['Nizampet','invisible-grills'],
                ],
                'Central Hyderabad' => [
                    ['Banjara Hills','safety-nets'],['Jubilee Hills','invisible-grills'],['Somajiguda','bird-nets'],
                    ['Panjagutta','pigeon-nets'],['Begumpet','safety-nets'],['Ameerpet','balcony-safety-nets'],
                    ['SR Nagar','safety-nets'],['Himayatnagar','pigeon-nets'],['Abids','bird-nets'],
                    ['Khairatabad','invisible-grills'],
                ],
                'North Hyderabad & Secunderabad' => [
                    ['Secunderabad','safety-nets'],['Bowenpally','bird-nets'],['Marredpally','invisible-grills'],
                    ['Alwal','pigeon-nets'],['Kompally','safety-nets'],['Medchal','sports-nets'],
                    ['Malkajgiri','balcony-safety-nets'],['ECIL','pigeon-nets'],['Sainikpuri','safety-nets'],
                    ['Jeedimetla','bird-nets'],['Dundigal','safety-nets'],
                ],
                'East Hyderabad' => [
                    ['LB Nagar','safety-nets'],['Uppal','bird-nets'],['Dilsukhnagar','pigeon-nets'],
                    ['Kothapet','safety-nets'],['Karmanghat','balcony-safety-nets'],['Vanasthalipuram','safety-nets'],
                    ['Hayathnagar','sports-nets'],['Nagole','invisible-grills'],['Boduppal','safety-nets'],
                    ['Nacharam','pigeon-nets'],['Ghatkesar','safety-nets'],
                ],
                'South Hyderabad' => [
                    ['Mehdipatnam','safety-nets'],['Tolichowki','bird-nets'],['Attapur','pigeon-nets'],
                    ['Rajendra Nagar','safety-nets'],['Shaikpet','invisible-grills'],['Raidurg','safety-nets'],
                    ['Shamshabad','balcony-safety-nets'],['Aramghar','pigeon-nets'],
                ],
            ];
            foreach ($footer_states as $state => $items) {
                echo '<div style="margin-bottom:1.5rem">';
                echo '<div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1.2px;color:#dc2626;margin-bottom:0.6rem">'.$state.'</div>';
                echo '<div style="display:flex;flex-wrap:wrap;gap:0.35rem 0.75rem">';
                foreach ($items as $item) {
                    $city = $item[0];
                    $svc  = $item[1];
                    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/','-',trim($city)));
                    $label = ucwords(str_replace('-',' ',$svc)).' in '.$city;
                    echo '<a href="/'.$svc.'-in-'.$slug.'" style="color:#6b7280;font-size:0.75rem;text-decoration:none;white-space:nowrap" onmouseover="this.style.color=\'#dc2626\'" onmouseout="this.style.color=\'#6b7280\'">'.$label.'</a>';
                }
                echo '</div></div>';
            }
            ?>
        </div>

            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> NettingTeam. All rights reserved. | Designed and Developed by <a href="https://www.ushaleads.in" target="_blank" rel="noopener" style="color: var(--color-primary); font-weight: 600;">Usha Leads</a></p>
            </div>
        </div>
    </footer>
    
    <!-- Floating Contact Bar -->
    <div class="floating-contact">
        <a href="<?= getSmartWhatsAppUrl() ?>" target="_blank" rel="noopener noreferrer" class="contact-btn whatsapp" 
           aria-label="WhatsApp"
           title="Chat on WhatsApp">
            <i class="fab fa-whatsapp"></i>
        </a>
        <a href="tel:+919912399334" 
           class="contact-btn phone" 
           aria-label="Call Us"
           title="Call Us">
            <i class="fas fa-phone"></i>
        </a>
        <a href="mailto:contact@nettingteam.com" 
           class="contact-btn email" 
           aria-label="Email Us"
           title="Email Us">
            <i class="fas fa-envelope"></i>
        </a>
        <a href="https://g.page/r/CYsaqtWlebHrEBM/review" 
           target="_blank"
           rel="noopener"
           class="contact-btn"
           aria-label="Leave a Google Review"
           title="Leave Us a Google Review"
           style="background:#f59e0b;color:#fff">
            <i class="fas fa-star"></i>
        </a>
        <a href="#" 
           class="contact-btn scroll-top" 
           aria-label="Scroll to top"
           title="Back to Top"
           style="display: none;">
            <i class="fas fa-arrow-up"></i>
        </a>
    </div>
    
    <!-- Beautiful Popup Modal -->
    <div id="popup-modal" class="popup-overlay" style="display: none;">
        <div class="popup-container">
            <div class="popup-icon" id="popup-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 class="popup-title" id="popup-title">Success!</h3>
            <p class="popup-message" id="popup-message">Your message has been sent.</p>
            <div class="popup-id" id="popup-id" style="display: none;"></div>
            <button class="popup-btn" onclick="closePopup()">Got it!</button>
        </div>
    </div>
    
    <style>
    .popup-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(5px);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease;
    }
    .popup-container {
        background: white;
        border-radius: 20px;
        padding: 2.5rem;
        text-align: center;
        max-width: 400px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .popup-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2.5rem;
    }
    .popup-icon.success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }
    .popup-icon.error {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
    }
    .popup-title {
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
        color: #1e293b;
    }
    .popup-title.success { color: #059669; }
    .popup-title.error { color: #dc2626; }
    .popup-message {
        font-size: 1rem;
        color: #64748b;
        margin-bottom: 1.5rem;
        line-height: 1.6;
    }
    .popup-id {
        background: #f1f5f9;
        border-radius: 10px;
        padding: 0.75rem 1rem;
        font-size: 0.85rem;
        color: #475569;
        margin-bottom: 1.5rem;
        font-family: monospace;
    }
    .popup-btn {
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        color: white;
        border: none;
        padding: 1rem 3rem;
        font-size: 1rem;
        font-weight: 600;
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .popup-btn:hover {
        transform: scale(1.05);
        box-shadow: 0 10px 30px rgba(220, 38, 38, 0.4);
    }
    @keyframes popIn {
        0% { opacity: 0; transform: scale(0.8) translateY(-20px); }
        100% { opacity: 1; transform: scale(1) translateY(0); }
    }
    </style>
    
    <script>
    function showPopup(type, title, message, enquiryId) {
        var modal = document.getElementById('popup-modal');
        var icon = document.getElementById('popup-icon');
        var titleEl = document.getElementById('popup-title');
        var messageEl = document.getElementById('popup-message');
        var idEl = document.getElementById('popup-id');
        if (type === 'success') {
            icon.innerHTML = '<i class="fas fa-check-circle"></i>';
            icon.className = 'popup-icon success';
            titleEl.className = 'popup-title success';
        } else {
            icon.innerHTML = '<i class="fas fa-times-circle"></i>';
            icon.className = 'popup-icon error';
            titleEl.className = 'popup-title error';
        }
        titleEl.textContent = title;
        messageEl.textContent = message;
        if (enquiryId) {
            idEl.style.display = 'block';
            idEl.innerHTML = '📋 Enquiry ID: <strong>' + enquiryId + '</strong>';
        } else {
            idEl.style.display = 'none';
        }
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    function closePopup() {
        var modal = document.getElementById('popup-modal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
        if (window.history.replaceState) {
            var url = window.location.protocol + '//' + window.location.host + window.location.pathname;
            window.history.replaceState({}, document.title, url);
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
        var urlParams = new URLSearchParams(window.location.search);
        var status = urlParams.get('status');
        var msg = urlParams.get('msg');
        var type = urlParams.get('type');
        var id = urlParams.get('id');
        if (status === 'success') {
            var title = type === 'quick' ? '🎉 Request Received!' : '✅ Message Sent!';
            showPopup('success', title, msg || 'Thank you! We will contact you soon.', id);
        } else if (status === 'error') {
            showPopup('error', '❌ Oops!', msg || 'Something went wrong. Please try again.', null);
        }
    });
    document.getElementById('popup-modal').onclick = function(e) {
        if (e.target === this) closePopup();
    };
    document.onkeydown = function(e) {
        if (e.key === 'Escape') closePopup();
    };
    </script>
    
    <!-- Main JavaScript -->
    <script src="/js/main.js"></script>

<script>
// Universal Smart Dynamic WhatsApp Click Handler for NettingTeam
document.addEventListener("click", function(e) {
    var waLink = e.target.closest('a[href*="wa.me"], a[href*="whatsapp.com"], .whatsapp-btn, .btn-whatsapp, .contact-btn.whatsapp, .whatsapp-link, .fab-whatsapp');
    if (!waLink) return;
    
    e.preventDefault();
    
    var currentUrl = window.location.href.split("#")[0];
    var path = window.location.pathname;
    var title = document.title || "";
    var h1Elem = document.querySelector("h1");
    var h1 = h1Elem ? h1Elem.innerText.trim() : "";
    var checkStr = (h1 + " " + title + " " + path).toLowerCase();
    
    // 1. Detect Service
    var service = "";
    if (checkStr.includes("cloth-hanger") || checkStr.includes("cloth hanger") || checkStr.includes("drying-hanger") || checkStr.includes("drying stand") || checkStr.includes("ceiling cloth")) {
        service = "Balcony & Ceiling Cloth Drying Hanger";
    } else if (checkStr.includes("invisible-grill") || checkStr.includes("invisible grill")) {
        service = "Stainless Steel Invisible Grills";
    } else if (checkStr.includes("box-cricket") || checkStr.includes("box cricket")) {
        service = "Box Cricket Ground Setup & Netting";
    } else if (checkStr.includes("5-a-side") || checkStr.includes("7-a-side") || checkStr.includes("8-a-side") || checkStr.includes("football-turf") || checkStr.includes("football turf")) {
        service = "Football Turf Setup & Perimeter Netting";
    } else if (checkStr.includes("cricket")) {
        service = "Cricket Practice Pitch Nets Installation";
    } else if (checkStr.includes("spike")) {
        service = "Bird & Pigeon Protection Spikes";
    } else if (checkStr.includes("pigeon")) {
        service = "Pigeon Safety Nets Installation";
    } else if (checkStr.includes("child")) {
        service = "Children & Pet Safety Nets";
    } else if (checkStr.includes("balcony")) {
        service = "Balcony Safety Nets Installation";
    } else if (checkStr.includes("sports")) {
        service = "Sports Netting & Ground Enclosures";
    } else if (checkStr.includes("grass") || checkStr.includes("turf")) {
        service = "Artificial Grass Turf & Sports Flooring";
    } else if (h1) {
        service = h1.replace(/[–—|\-].*$/, "").trim();
    } else {
        service = "Safety Nets & Invisible Grills";
    }
    
    // 2. Detect Location (using comprehensive resolver or fallback)
    var location = "";
    var match = path.match(/-in-([a-z0-9-]+)/i);
    if (match && match[1]) {
        var slug = match[1];
        if (typeof resolveSmartGeoLocation === "function") {
            location = resolveSmartGeoLocation(slug);
        } else {
            var formatted = slug.split("-").map(function(w) {{ return w.charAt(0).toUpperCase() + w.slice(1); }}).join(" ");
            location = (typeof resolveSmartGeoLocation === "function") ? resolveSmartGeoLocation(slug) : formatted;
        }
    } else {
        location = "Hyderabad, Telangana (All Areas & Pan-India Supply)";
    }
    
    // 3. Pricing Line
    var pricingText = "Please share pricing per sq.ft, technical specifications, and installation availability.";
    if (checkStr.includes("hanger") || checkStr.includes("drying")) {
        pricingText = "Please share pricing per piece / set, model options, technical specifications, and installation availability.";
    } else if (checkStr.includes("spike")) {
        pricingText = "Please share pricing per piece / per strip, technical specifications, and installation availability.";
    } else if (checkStr.includes("cricket") || checkStr.includes("turf")) {
        pricingText = "Please share turnkey court setup cost / pricing per sq.ft, technical specifications, and installation availability.";
    }
    
    var msg = "Hi NettingTeam, I would like to inquire about *" + service + "* in *" + location + "*.

" +
              "• Service: " + service + "
" +
              "• Location: " + location + "
" +
              "• Page: " + currentUrl + "
" +
              "• Website: www.nettingteam.com

" +
              pricingText;
              
    window.open("https://wa.me/919912399334?text=" + encodeURIComponent(msg), "_blank");
});
</script>

<script src="/js/geo_resolver.js"></script>
    <script src="/js/tracker.js" defer></script>
</body>
</html>

