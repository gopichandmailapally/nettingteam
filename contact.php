<?php
$page_title = 'Contact NettingTeam — Visit Our Office & Warehouse at Karmanghat, Hyderabad';
$page_description = 'Visit NettingTeam office & warehouse at Karmanghat, Hyderabad. Authorized Russea™ dealer for safety nets, bird nets, invisible grills. Walk in to inspect stock. Call 9912399334.';
$page_keywords = 'contact gcm enterprises hyderabad, gcm enterprises karmanghat address, safety nets office hyderabad, bird nets showroom karmanghat, gcm enterprises phone number, safety net dealers karmanghat hyderabad, russea™ nets contact hyderabad';
$page_canonical = 'https://nettingteam.com/contact';
$breadcrumbs = [['name' => 'Contact Us']];
include 'includes/header.php';
require_once 'includes/company.php';
?>

<!-- Page Header -->
<section class="hero" style="padding: 6rem 0 3rem;">
    <div class="container">
        <div class="hero-content">
            <span style="display:inline-block;background:var(--color-primary);color:white;padding:0.35rem 1rem;border-radius:50px;font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:1rem"><i class="fas fa-store"></i> Walk-In Showroom &amp; Warehouse — Karmanghat</span>
            <h1 class="hero-title">Visit Our Office &amp; Warehouse</h1>
            <p class="hero-subtitle">
                <?= htmlspecialchars(gcm_address(), ENT_QUOTES) ?><br>
                Inspect Russea™ stock, get an exact quote &amp; place your order &mdash; walk-in welcome
            </p>
            <div style="margin-top:1.5rem;display:flex;flex-wrap:wrap;gap:.75rem;justify-content:center">
                <a href="<?= htmlspecialchars(gcm_maps_url(), ENT_QUOTES) ?>" target="_blank" rel="noopener"
                   style="display:inline-flex;align-items:center;gap:.5rem;background:#1e3a8a;color:white;padding:.75rem 1.5rem;border-radius:8px;font-weight:700;text-decoration:none;font-size:1rem">
                    <i class="fas fa-directions"></i> Get Directions — Open in Google Maps
                </a>
                <a href="tel:+91<?= gcm_phone() ?>"
                   style="display:inline-flex;align-items:center;gap:.5rem;background:#dc2626;color:white;padding:.75rem 1.5rem;border-radius:8px;font-weight:700;text-decoration:none;font-size:1rem">
                    <i class="fas fa-phone-alt"></i> Call <?= gcm_phone() ?>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section class="section">
    <div class="container">
        <div class="grid grid-2" style="gap: 3rem; align-items: start;">
            
            <!-- Contact Form -->
            <div>
                <h2 class="mb-3">Send Us a Message</h2>
                <p style="margin-bottom: 2rem; color: var(--color-gray-400);">
                    Fill out the form below and we'll get back to you within 24 hours.
                </p>
                
                <form id="contact-form" class="contact-form" action="contact-handler.php" method="POST" style="max-width: 100%;">
                    <div class="form-group">
                        <label for="name" class="form-label">Full Name *</label>
                        <input type="text" id="name" name="name" class="form-input" required placeholder="Enter your full name">
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" id="email" name="email" class="form-input" required placeholder="your.email@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone" class="form-label">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" class="form-input" required placeholder="+91 9876543210">
                    </div>
                    
                    <div class="form-group">
                        <label for="service" class="form-label">Service Interested In</label>
                        <select id="service" name="service" class="form-select">
                            <option value="">Select a service</option>
                            <option value="balcony-safety-nets">Balcony Safety Nets</option>
                            <option value="bird-nets">Bird Nets</option>
                            <option value="pigeon-nets">Pigeon Nets</option>
                            <option value="sports-nets">Sports Nets</option>
                            <option value="invisible-grills">Invisible Grills</option>
                            <option value="cloth-hangers">Cloth Hangers</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" id="location" name="location" class="form-input" placeholder="Your area in Hyderabad">
                    </div>
                    
                    <div class="form-group">
                        <label for="message" class="form-label">Message *</label>
                        <textarea id="message" name="message" class="form-textarea" required placeholder="Tell us about your requirements..."></textarea>
                    </div>

                    <div style="display:flex;align-items:center;gap:0.6rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:0.85rem 1rem;margin-bottom:1rem">
                        <input type="checkbox" name="has_whatsapp" id="cwa" value="1" style="width:18px;height:18px;accent-color:#25d366;cursor:pointer;flex-shrink:0">
                        <label for="cwa" style="cursor:pointer;font-size:0.9rem;color:#374151;font-weight:600;display:flex;align-items:center;gap:0.4rem;margin:0">
                            <i class="fab fa-whatsapp" style="color:#25d366;font-size:1.2rem"></i>
                            WhatsApp is active on my mobile number — send me a WhatsApp reply
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
            </div>
            
            <!-- Contact Information -->
            <div>
                <h2 class="mb-3">Get In Touch</h2>
                <p style="margin-bottom: 2rem; color: var(--color-gray-400);">
                    Reach out to us directly through any of the following channels:
                </p>
                
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div style="display: flex; align-items: start; gap: 1rem;">
                        <div class="feature-icon" style="margin: 0; width: 50px; height: 50px; font-size: 1.25rem;">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div>
                            <h4 style="margin-bottom: 0.5rem;">Phone</h4>
                            <p style="margin: 0;">
                                <a href="tel:+919912399334" style="color: var(--color-primary); font-size: 1.25rem; font-weight: 600;">
                                    +919912399334
                                </a>
                            </p>
                            <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem; color: var(--color-gray-500);">
                                Available 24/7 for your convenience
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div style="display: flex; align-items: start; gap: 1rem;">
                        <div class="feature-icon" style="margin: 0; width: 50px; height: 50px; font-size: 1.25rem;">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div>
                            <h4 style="margin-bottom: 0.5rem;">Official Emails</h4>
                            <div style="display: flex; flex-direction: column; gap: 0.4rem; margin-top: 0.4rem;">
                                <div>
                                    <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; display: block;">General Inquiries</span>
                                    <a href="mailto:contact@nettingteam.com" style="color: var(--color-primary); font-weight: 600; text-decoration: none;">
                                        contact@nettingteam.com
                                    </a>
                                </div>
                                <div>
                                    <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; display: block;">Customer Support</span>
                                    <a href="mailto:support@nettingteam.com" style="color: var(--color-primary); font-weight: 600; text-decoration: none;">
                                        support@nettingteam.com
                                    </a>
                                </div>
                                <div>
                                    <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; display: block;">Sales & Bulk Quotes</span>
                                    <a href="mailto:sales@nettingteam.com" style="color: var(--color-primary); font-weight: 600; text-decoration: none;">
                                        sales@nettingteam.com
                                    </a>
                                </div>
                            </div>
                            <p style="margin: 0.75rem 0 0 0; font-size: 0.85rem; color: var(--color-gray-500);">
                                Quick response within 2 hours
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div style="display: flex; align-items: start; gap: 1rem;">
                        <div class="feature-icon" style="margin: 0; width: 50px; height: 50px; font-size: 1.25rem;">
                            <i class="fab fa-whatsapp"></i>
                        </div>
                        <div>
                            <h4 style="margin-bottom: 0.5rem;">WhatsApp</h4>
                            <p style="margin: 0;">
                                <a href="https://wa.me/919912399334?text=Hi,%20I%20would%20like%20to%20inquire%20about%20your%20safety%20net%20services." 
                                   target="_blank" 
                                   style="color: var(--color-primary); font-size: 1.25rem; font-weight: 600;">
                                    Chat with us
                                </a>
                            </p>
                            <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem; color: var(--color-gray-500);">
                                Quick responses and instant quotes
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div style="display: flex; align-items: start; gap: 1rem;">
                        <div class="feature-icon" style="margin: 0; width: 50px; height: 50px; font-size: 1.25rem;">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <h4 style="margin-bottom: 0.5rem;">Office, Showroom &amp; Warehouse</h4>
                            <p style="margin: 0; color: var(--color-gray-300); line-height: 1.7;">
                                <strong>NettingTeam</strong><br>
                                <?= nl2br(htmlspecialchars(gcm_address(), ENT_QUOTES)) ?>
                            </p>
                            <a href="<?= htmlspecialchars(gcm_maps_url(), ENT_QUOTES) ?>" target="_blank" rel="noopener"
                               style="display:inline-flex;align-items:center;gap:.4rem;margin-top:.75rem;background:var(--color-primary);color:white;padding:.45rem 1rem;border-radius:6px;font-size:.875rem;font-weight:600;text-decoration:none">
                                <i class="fas fa-directions"></i> Get Directions on Google Maps
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card" style="margin-top: 2rem; background: rgba(102, 126, 234, 0.1);">
                    <h4 style="margin-bottom: 1rem;"><i class="fas fa-clock"></i> Business Hours</h4>
                    <p style="margin: 0; color: var(--color-gray-300);">
                        <strong>Monday - Saturday:</strong> 8:00 AM - 8:00 PM<br>
                        <strong>Sunday:</strong> 9:00 AM - 6:00 PM
                    </p>
                    <p style="margin: 1rem 0 0 0; font-size: 0.875rem; color: var(--color-gray-400);">
                        Emergency services available 24/7
                    </p>
                </div>
            </div>
            
        </div>
    </div>
</section>

<!-- Google Maps Section -->
<section class="section" style="background:#f8fafc;padding:3rem 0">
    <div class="container">
        <h2 class="section-title">Find Our Office &amp; Showroom</h2>
        <p class="section-subtitle">Walk in to inspect Russea™ certified stock, get an exact quote and place your order.</p>
        <div style="display:grid;grid-template-columns:1fr 340px;gap:2rem;align-items:start" class="map-grid">
            <!-- Embedded Map -->
            <div style="border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1);">
                <iframe
                    src="https://maps.google.com/maps?q=GCM+Enterprises+Karmanghat+Hyderabad&output=embed&z=16"
                    width="100%" height="420" style="border:0;display:block"
                    allowfullscreen loading="lazy"
                    title="NettingTeam Office &amp; Warehouse — Karmanghat, Hyderabad"></iframe>
            </div>
            <!-- Address + Info Card -->
            <div>
                <div style="background:white;border:2px solid #e5e7eb;border-radius:14px;padding:1.5rem;margin-bottom:1.25rem">
                    <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:1rem">
                        <div style="background:#1e3a8a;color:white;border-radius:8px;padding:.55rem .65rem">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <strong style="color:#1e3a8a;font-size:1.05rem">Office, Showroom &amp; Warehouse</strong>
                    </div>
                    <address style="font-style:normal;color:#374151;line-height:1.8;font-size:.95rem">
                        <strong>NettingTeam</strong><br>
                        <?= nl2br(htmlspecialchars(gcm_address(), ENT_QUOTES)) ?>
                    </address>
                    <!-- Warehouse Visit Note -->
                    <div style="margin-top:.75rem;background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:.65rem .85rem;font-size:.85rem;color:#92400e;">
                        <i class="fas fa-warehouse" style="margin-right:.4rem"></i>
                        <strong>Warehouse visits welcome!</strong> Walk in to inspect Russea™ stock, check quality &amp; pick your product directly.
                    </div>
                    <a href="<?= htmlspecialchars(gcm_maps_url(), ENT_QUOTES) ?>" target="_blank" rel="noopener"
                       style="display:flex;align-items:center;justify-content:center;gap:.5rem;margin-top:1rem;background:#1e3a8a;color:white;padding:.7rem 1rem;border-radius:8px;font-weight:700;text-decoration:none;font-size:.95rem">
                        <i class="fas fa-directions"></i> Get Directions on Google Maps
                    </a>
                </div>
                <div style="background:white;border:2px solid #e5e7eb;border-radius:14px;padding:1.25rem;margin-bottom:1.25rem">
                    <div style="font-weight:700;color:#1e3a8a;margin-bottom:.75rem"><i class="fas fa-clock"></i> Business Hours</div>
                    <div style="color:#374151;font-size:.9rem;line-height:1.8">
                        <strong>Mon &ndash; Sat:</strong> 8:00 AM &ndash; 8:00 PM<br>
                        <strong>Sunday:</strong> 9:00 AM &ndash; 6:00 PM
                    </div>
                </div>
                <div style="background:#1e3a8a;border-radius:14px;padding:1.25rem;text-align:center;color:white">
                    <div style="font-size:.85rem;opacity:.85;margin-bottom:.35rem">Call or WhatsApp</div>
                    <a href="tel:+919912399334" style="color:white;font-size:1.4rem;font-weight:800;text-decoration:none">
                        <i class="fas fa-phone-alt"></i> 9912399334
                    </a>
                    <div style="margin-top:.75rem">
                        <a href="https://wa.me/919912399334" target="_blank"
                           style="display:inline-flex;align-items:center;gap:.4rem;background:#25d366;color:white;padding:.5rem 1.2rem;border-radius:8px;font-weight:700;text-decoration:none;font-size:.9rem">
                            <i class="fab fa-whatsapp"></i> WhatsApp Us
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<style>
@media(max-width:768px){.map-grid{grid-template-columns:1fr !important}}
</style>

<?php include 'includes/footer.php'; ?>
