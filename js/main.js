// NettingTeam - Main JavaScript

// Mobile Menu Toggle
document.addEventListener('DOMContentLoaded', function () {
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const mobileNav = document.querySelector('.mobile-nav');
    const header = document.querySelector('.header');
    const scrollTopBtn = document.querySelector('.scroll-top');

    // Mobile menu toggle
    if (mobileMenuBtn && mobileNav) {
        mobileMenuBtn.addEventListener('click', function () {
            mobileNav.classList.toggle('active');
            const icon = this.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            }
        });

        // Close mobile menu when clicking on a link (but NOT the submenu toggle)
        const mobileLinks = mobileNav.querySelectorAll('a');
        mobileLinks.forEach(link => {
            link.addEventListener('click', function () {
                // Don't close menu if clicking the Services toggle
                if (this.classList.contains('mobile-submenu-toggle')) {
                    return;
                }
                // Don't close menu if clicking a category title
                if (this.classList.contains('mobile-category-title')) {
                    return;
                }
                // Close menu for actual navigation links
                mobileNav.classList.remove('active');
                const icon = mobileMenuBtn.querySelector('i');
                if (icon) {
                    icon.classList.add('fa-bars');
                    icon.classList.remove('fa-times');
                }
            });
        });
    }

    // Mobile submenu toggle
    const mobileSubmenuToggle = document.querySelector('.mobile-submenu-toggle');
    const mobileSubmenuItems = document.querySelector('.mobile-submenu-items');

    if (mobileSubmenuToggle && mobileSubmenuItems) {
        mobileSubmenuToggle.addEventListener('click', function (e) {
            e.preventDefault();
            mobileSubmenuItems.classList.toggle('active');
            const chevron = this.querySelector('.fa-chevron-down');
            if (chevron) {
                chevron.style.transform = mobileSubmenuItems.classList.contains('active')
                    ? 'rotate(180deg)'
                    : 'rotate(0deg)';
            }
        });
    }

    // Mobile category toggle (second level accordion)
    const categoryToggles = document.querySelectorAll('.mobile-category-toggle');
    categoryToggles.forEach(toggle => {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const category = this.closest('.mobile-category');
            const items = category.querySelector('.mobile-category-items');
            const icon = this.querySelector('i');

            // Toggle this category
            if (items) {
                items.classList.toggle('active');
            }
            this.classList.toggle('active');

            // Change icon from plus to minus
            if (icon) {
                if (icon.classList.contains('fa-plus')) {
                    icon.classList.remove('fa-plus');
                    icon.classList.add('fa-minus');
                } else {
                    icon.classList.remove('fa-minus');
                    icon.classList.add('fa-plus');
                }
            }
        });
    });

    // Gallery Filter Functionality
    const galleryFilters = document.querySelectorAll('.gallery-filter');
    const galleryItems = document.querySelectorAll('.gallery-item');

    if (galleryFilters.length > 0 && galleryItems.length > 0) {
        galleryFilters.forEach(filter => {
            filter.addEventListener('click', function () {
                // Remove active class from all filters
                galleryFilters.forEach(f => f.classList.remove('active'));
                // Add active class to clicked filter
                this.classList.add('active');

                const filterValue = this.getAttribute('data-filter');

                // Show/hide gallery items based on filter
                galleryItems.forEach(item => {
                    const itemCategory = item.getAttribute('data-category') || '';
                    const categories = itemCategory.split(/\s+/);

                    if (filterValue === 'all' || categories.includes(filterValue)) {
                        item.style.display = 'block';
                        item.style.animation = 'fadeIn 0.5s ease';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });
    }

    // Header scroll effect
    if (header) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    }

    // Scroll to top button
    if (scrollTopBtn) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 300) {
                scrollTopBtn.style.display = 'flex';
            } else {
                scrollTopBtn.style.display = 'none';
            }
        });

        scrollTopBtn.addEventListener('click', function (e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    // ── Desktop Services dropdown: click-to-toggle ───────────
    const navDropdown = document.querySelector('.nav-dropdown');
    const dropdownMenu = document.querySelector('.dropdown-menu.dropdown-mega');
    const servicesLink = navDropdown ? navDropdown.querySelector('.nav-link') : null;

    if (navDropdown && servicesLink) {
        // Toggle on click
        servicesLink.addEventListener('click', function (e) {
            e.preventDefault();
            navDropdown.classList.toggle('open');
        });

        // Close when clicking a dropdown link
        if (dropdownMenu) {
            dropdownMenu.querySelectorAll('a').forEach(function (link) {
                link.addEventListener('click', function () {
                    navDropdown.classList.remove('open');
                });
            });
        }

        // Close on outside click
        document.addEventListener('click', function (e) {
            if (!navDropdown.contains(e.target)) {
                navDropdown.classList.remove('open');
            }
        });

        // Close on Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                navDropdown.classList.remove('open');
            }
        });
    }

    // Active navigation link
    const currentPage = window.location.pathname.split('/').pop() || 'index.php';
    const navLinks = document.querySelectorAll('.nav-link, .mobile-nav-link');

    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage || (currentPage === 'index.php' && href === 'index.php')) {
            link.classList.add('active');
        }
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Form validation (without AJAX - let form submit normally for popup)
    const contactForm = document.querySelector('#contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', function (e) {
            // Basic validation only
            let isValid = true;
            const requiredFields = this.querySelectorAll('[required]');

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#dc2626';
                    field.style.boxShadow = '0 0 0 3px rgba(220, 38, 38, 0.2)';
                } else {
                    field.style.borderColor = '';
                    field.style.boxShadow = '';
                }
            });

            if (!isValid) {
                e.preventDefault();
                // Show custom error using popup if available
                if (typeof showPopup === 'function') {
                    showPopup('error', '❌ Missing Information', 'Please fill in all required fields.', null);
                } else {
                    alert('Please fill in all required fields.');
                }
                return;
            }

            // Form will submit normally to contact-handler.php
            // The handler will redirect with status=success/error
            // The popup modal in footer will display the message
        });
    }

    // Animation on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observe cards and sections
    const animatedElements = document.querySelectorAll('.card, .service-card, .feature-card');
    animatedElements.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });
});

// Smart Dynamic WhatsApp Click Handler
function openWhatsApp(customService, customLocation) {
    const phoneNumber = '919912399334';
    
    // Dynamically detect page context from current URL and document title
    let service = customService || '';
    if (!service) {
        const title = document.title || '';
        const path = window.location.pathname.toLowerCase();
        
        if (path.includes('box-cricket') || title.toLowerCase().includes('box cricket')) {
            service = 'Box Cricket Ground Setup & Netting';
        } else if (path.includes('football-turf') || title.toLowerCase().includes('football turf') || path.includes('5-a-side') || path.includes('7-a-side') || path.includes('8-a-side')) {
            service = 'Football Turf Setup & Perimeter Netting';
        } else if (path.includes('cricket') || title.toLowerCase().includes('cricket')) {
            service = 'Cricket Practice Pitch Nets Installation';
        } else if (path.includes('invisible-grill') || title.toLowerCase().includes('invisible grill')) {
            service = 'Stainless Steel Invisible Grills';
        } else if (path.includes('balcony') || title.toLowerCase().includes('balcony')) {
            service = 'Balcony Safety Nets Installation';
        } else if (path.includes('bird') || title.toLowerCase().includes('bird')) {
            service = 'Anti-Bird & Pigeon Protection Nets';
        } else if (path.includes('pigeon') || title.toLowerCase().includes('pigeon')) {
            service = 'Pigeon Safety Nets & Spikes';
        } else if (path.includes('sports') || title.toLowerCase().includes('sports')) {
            service = 'Sports Netting & Court Enclosures';
        } else if (path.includes('grass') || title.toLowerCase().includes('artificial grass')) {
            service = 'Artificial Grass Turf & Flooring';
        } else if (path.includes('cloth-hanger') || path.includes('drying') || path.includes('pull-and-dry')) {
            service = 'Balcony & Ceiling Cloth Drying Hangers';
        } else {
            service = title.split(/[–—|-]/)[0].trim() || 'Safety Nets & Invisible Grills';
        }
    }
    
    let location = customLocation || 'Hyderabad, Telangana';
    const currentUrl = window.location.href;
    
    let pricingPrompt = 'Please share pricing per sq.ft, technical specifications, and installation availability.';
    const lowerSvc = service.toLowerCase();
    if (lowerSvc.includes('hanger') || lowerSvc.includes('stand') || lowerSvc.includes('rack')) {
        pricingPrompt = 'Please share pricing per piece / set, model options, technical specifications, and installation availability.';
    } else if (lowerSvc.includes('cricket') || lowerSvc.includes('turf') || lowerSvc.includes('court')) {
        pricingPrompt = 'Please share turnkey court setup cost / pricing per sq.ft, technical specifications, and installation availability.';
    }

    const msg = `Hi NettingTeam, I would like to inquire about *${service}* in *${location}*.\n\n` +
                `• Service: ${service}\n` +
                `• Location: ${location}\n` +
                `• Page: ${currentUrl}\n` +
                `• Website: www.nettingteam.com\n\n` +
                pricingPrompt;
                
    window.open(`https://wa.me/${phoneNumber}?text=${encodeURIComponent(msg)}`, '_blank');
}

// Phone Click Handler
function callPhone() {
    window.location.href = 'tel:+919912399334';
}

// Email Click Handler
function sendEmail() {
    window.location.href = 'mailto:nettingteam.com@gmail.com?subject=Service Inquiry';
}

// Hero Slider Functionality
let currentSlide = 0;
let slideInterval;

function showSlide(index) {
    const slides = document.querySelectorAll('.hero-slide');
    const indicators = document.querySelectorAll('.indicator');

    if (!slides.length) return;

    // Wrap around
    if (index >= slides.length) currentSlide = 0;
    else if (index < 0) currentSlide = slides.length - 1;
    else currentSlide = index;

    // Update slides
    slides.forEach((slide, i) => {
        slide.classList.remove('active');
        if (i === currentSlide) {
            slide.classList.add('active');
        }
    });

    // Update indicators
    indicators.forEach((indicator, i) => {
        indicator.classList.remove('active');
        if (i === currentSlide) {
            indicator.classList.add('active');
        }
    });
}

function changeSlide(direction) {
    showSlide(currentSlide + direction);
    resetSlideInterval();
}

function goToSlide(index) {
    showSlide(index);
    resetSlideInterval();
}

function resetSlideInterval() {
    clearInterval(slideInterval);
    slideInterval = setInterval(() => {
        showSlide(currentSlide + 1);
    }, 3500); // Change slide every 3.5 seconds
}

// Initialize slider on page load
document.addEventListener('DOMContentLoaded', function () {
    const slides = document.querySelectorAll('.hero-slide');
    if (slides.length > 0) {
        showSlide(0);
        resetSlideInterval();

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') changeSlide(-1);
            if (e.key === 'ArrowRight') changeSlide(1);
        });
    }
});


// =========================================================================
// NETTINGTEAM RESILIENCE SHIELD: Global Smart WhatsApp Link Decorator
// =========================================================================
(function() {
    const tgDistricts = {
        'adilabad': 'Adilabad, Telangana', 'bhadradri-kothagudem': 'Bhadradri Kothagudem, Telangana',
        'kothagudem': 'Bhadradri Kothagudem, Telangana', 'hanamkonda': 'Hanamkonda, Telangana',
        'hanmakonda': 'Hanamkonda, Telangana', 'hyderabad': 'Hyderabad, Telangana',
        'jagtial': 'Jagtial, Telangana', 'jangaon': 'Jangaon, Telangana',
        'jayashankar-bhupalpally': 'Jayashankar Bhupalpally, Telangana', 'bhupalpally': 'Jayashankar Bhupalpally, Telangana',
        'jogulamba-gadwal': 'Jogulamba Gadwal, Telangana', 'gadwal': 'Jogulamba Gadwal, Telangana',
        'kamareddy': 'Kamareddy, Telangana', 'karimnagar': 'Karimnagar, Telangana',
        'khammam': 'Khammam, Telangana', 'kumuram-bheem-asifabad': 'Kumuram Bheem Asifabad, Telangana',
        'kumuram-bheem': 'Kumuram Bheem Asifabad, Telangana', 'asifabad': 'Kumuram Bheem Asifabad, Telangana',
        'mahabubabad': 'Mahabubabad, Telangana', 'mahabubnagar': 'Mahabubnagar, Telangana',
        'mahbubnagar': 'Mahabubnagar, Telangana', 'mancherial': 'Mancherial, Telangana',
        'medak': 'Medak, Telangana', 'medchal-malkajgiri': 'Medchal-Malkajgiri, Telangana',
        'medchal': 'Medchal-Malkajgiri, Telangana', 'mulugu': 'Mulugu, Telangana',
        'nagarkurnool': 'Nagarkurnool, Telangana', 'nalgonda': 'Nalgonda, Telangana',
        'narayanpet': 'Narayanpet, Telangana', 'nirmal': 'Nirmal, Telangana',
        'nizamabad': 'Nizamabad, Telangana', 'peddapalli': 'Peddapalli, Telangana',
        'rajanna-sircilla': 'Rajanna Sircilla, Telangana', 'sircilla': 'Rajanna Sircilla, Telangana',
        'rangareddy': 'Rangareddy, Telangana', 'ranga-reddy': 'Rangareddy, Telangana',
        'sangareddy': 'Sangareddy, Telangana', 'siddipet': 'Siddipet, Telangana',
        'suryapet': 'Suryapet, Telangana', 'vikarabad': 'Vikarabad, Telangana',
        'wanaparthy': 'Wanaparthy, Telangana', 'warangal': 'Warangal, Telangana',
        'warangal-urban': 'Warangal Urban, Telangana', 'warangal-rural': 'Warangal Rural, Telangana',
        'yadadri-bhuvanagiri': 'Yadadri Bhuvanagiri, Telangana', 'yadadri': 'Yadadri Bhuvanagiri, Telangana',
        'bhuvanagiri': 'Yadadri Bhuvanagiri, Telangana'
    };

    const hydAreas = {
        'charminar': 'Charminar', 'gachibowli': 'Gachibowli', 'madhapur': 'Madhapur',
        'kondapur': 'Kondapur', 'hitech-city': 'HiTech City', 'hitec-city': 'HiTech City',
        'banjara-hills': 'Banjara Hills', 'jubilee-hills': 'Jubilee Hills', 'kukatpally': 'Kukatpally',
        'secunderabad': 'Secunderabad', 'ameerpet': 'Ameerpet', 'begumpet': 'Begumpet',
        'miyapur': 'Miyapur', 'manikonda': 'Manikonda', 'financial-district': 'Financial District',
        'uppal': 'Uppal', 'lb-nagar': 'LB Nagar', 'dilsukhnagar': 'Dilsukhnagar',
        'kompally': 'Kompally', 'bachupally': 'Bachupally', 'mehdipatnam': 'Mehdipatnam',
        'tolichowki': 'Tolichowki', 'attapur': 'Attapur', 'nacharam': 'Nacharam',
        'ecil': 'ECIL', 'malkajgiri': 'Malkajgiri', 'alwal': 'Alwal', 'bowenpally': 'Bowenpally',
        'boduppal': 'Boduppal', 'hayathnagar': 'Hayathnagar', 'vanasthalipuram': 'Vanasthalipuram',
        'saroornagar': 'Saroornagar', 'kothapet': 'Kothapet', 'malakpet': 'Malakpet',
        'amberpet': 'Amberpet', 'ramanthapur': 'Ramanthapur', 'trimulgherry': 'Trimulgherry',
        'marredpally': 'Marredpally', 'panjagutta': 'Panjagutta', 'somajiguda': 'Somajiguda',
        'yousufguda': 'Yousufguda', 'erragadda': 'Erragadda', 'sr-nagar': 'SR Nagar',
        'hafeezpet': 'Hafeezpet', 'nizampet': 'Nizampet', 'kphb': 'KPHB Colony',
        'kphb-colony': 'KPHB Colony', 'chandanagar': 'Chandanagar', 'lingampally': 'Lingampally',
        'serilingampally': 'Serilingampally', 'nanakramguda': 'Nanakramguda', 'narsingi': 'Narsingi',
        'puppalaguda': 'Puppalaguda', 'rajendra-nagar': 'Rajendra Nagar', 'rajendranagar': 'Rajendranagar',
        'shamshabad': 'Shamshabad', 'tarnaka': 'Tarnaka', 'habsiguda': 'Habsiguda',
        'moula-ali': 'Moula Ali', 'kapra': 'Kapra', 'sainikpuri': 'Sainikpuri',
        'yapral': 'Yapral', 'nagaram': 'Nagaram', 'dammaiguda': 'Dammaiguda',
        'cherlapally': 'Cherlapally', 'mallapur': 'Mallapur', 'peerzadiguda': 'Peerzadiguda',
        'medipally': 'Medipally', 'chengicherla': 'Chengicherla', 'ghatkesar': 'Ghatkesar',
        'pocharam': 'Pocharam', 'bandlaguda-jagir': 'Bandlaguda Jagir', 'bandlaguda': 'Bandlaguda',
        'sun-city': 'Sun City', 'langar-houz': 'Langar Houz', 'golkonda': 'Golkonda',
        'golconda': 'Golconda', 'asif-nagar': 'Asif Nagar', 'nampally': 'Nampally',
        'abids': 'Abids', 'koti': 'Koti', 'himayatnagar': 'Himayatnagar',
        'narayanaguda': 'Narayanaguda', 'hyderguda': 'Hyderguda', 'barkatpura': 'Barkatpura',
        'kachiguda': 'Kachiguda', 'chikkadpally': 'Chikkadpally', 'musheerabad': 'Musheerabad',
        'rtc-cross-roads': 'RTC Cross Roads', 'vidyanagar': 'Vidyanagar', 'nallakunta': 'Nallakunta',
        'padmarao-nagar': 'Padmarao Nagar', 'ranigunj': 'Ranigunj', 'karmanghat': 'Karmanghat',
        'badangpet': 'Badangpet', 'meerpet': 'Meerpet', 'balapur': 'Balapur',
        'chandrayangutta': 'Chandrayangutta', 'falaknuma': 'Falaknuma', 'santosh-nagar': 'Santosh Nagar',
        'saidabad': 'Saidabad', 'bahadurpura': 'Bahadurpura', 'jeedimetla': 'Jeedimetla',
        'quthbullapur': 'Quthbullapur', 'chintal': 'Chintal', 'shapur-nagar': 'Shapur Nagar',
        'suraram': 'Suraram', 'bahadurpally': 'Bahadurpally', 'dundigal': 'Dundigal',
        'shamirpet': 'Shamirpet', 'keesara': 'Keesara', 'gundlapochampally': 'Gundlapochampally',
        'bowrampet': 'Bowrampet', 'mallampet': 'Mallampet', 'pragathi-nagar': 'Pragathi Nagar',
        'hydernagar': 'Hydernagar', 'moosapet': 'Moosapet', 'bharat-nagar': 'Bharat Nagar',
        'motinagar': 'Motinagar', 'borabanda': 'Borabanda', 'allwyn-colony': 'Allwyn Colony',
        'jagathgiri-gutta': 'Jagathgiri Gutta', 'sanathnagar': 'Sanathnagar', 'balkampet': 'Balkampet',
        'fatehnagar': 'Fatehnagar', 'khairatabad': 'Khairatabad', 'lakdikapul': 'Lakdikapul',
        'masab-tank': 'Masab Tank', 'shaikpet': 'Shaikpet', 'seven-tombs': 'Seven Tombs',
        'filmnagar': 'Film Nagar', 'film-nagar': 'Film Nagar', 'raidurgam': 'Raidurgam',
        'raidurg': 'Raidurg', 'knowledge-city': 'Knowledge City', 'gowlidoddy': 'Gowlidoddy',
        'kokapet': 'Kokapet', 'gandipet': 'Gandipet', 'tellapur': 'Tellapur',
        'osman-nagar': 'Osman Nagar', 'kollur': 'Kollur', 'mokila': 'Mokila',
        'shankarapalli': 'Shankarapalli', 'patancheru': 'Patancheru', 'beeramguda': 'Beeramguda',
        'rc-puram': 'RC Puram', 'ramachandrapuram': 'Ramachandrapuram', 'ameenpur': 'Ameenpur',
        'bollaram': 'Bollaram', 'adibatla': 'Adibatla', 'turkayamjal': 'Turkayamjal',
        'gurramguda': 'Gurramguda', 'old-city': 'Old City'
    };

    const tgMandals = {
        'chandampet': ['Chandampet', 'Nalgonda'], 'devarakonda': ['Devarakonda', 'Nalgonda'],
        'miryalaguda': ['Miryalaguda', 'Nalgonda'], 'nakrekal': ['Nakrekal', 'Nalgonda'],
        'ramannapet': ['Ramannapet', 'Nalgonda'], 'marriguda': ['Marriguda', 'Nalgonda'],
        'thipparthi': ['Thipparthi', 'Nalgonda'], 'pedda-adiserla': ['Pedda Adiserla', 'Nalgonda'],
        'pedda-adiserlapally': ['Pedda Adiserla', 'Nalgonda'], 'munugode': ['Munugode', 'Nalgonda'],
        'haliya': ['Haliya', 'Nalgonda'], 'narketpally': ['Narketpally', 'Nalgonda'],
        'kangal': ['Kangal', 'Nalgonda'], 'gundlapally': ['Gundlapally', 'Nalgonda'],
        'nidamanoor': ['Nidamanoor', 'Nalgonda'], 'tripuraram': ['Tripuraram', 'Nalgonda'],
        'anumula': ['Anumula', 'Nalgonda'], 'gurrampode': ['Gurrampode', 'Nalgonda'],
        'kattangur': ['Kattangur', 'Nalgonda'], 'madugulapally': ['Madugulapally', 'Nalgonda'],
        'damaracherla': ['Damaracherla', 'Nalgonda'], 'adavidevulapally': ['Adavidevulapally', 'Nalgonda'],
        'shali-gouraram': ['Shali Gouraram', 'Nalgonda'], 'kethepally': ['Kethepally', 'Nalgonda'],
        'utnoor': ['Utnoor', 'Adilabad'], 'boath': ['Boath', 'Adilabad'],
        'ichoda': ['Ichoda', 'Adilabad'], 'jainad': ['Jainad', 'Adilabad'],
        'talamadugu': ['Talamadugu', 'Adilabad'], 'tamsi': ['Tamsi', 'Adilabad'],
        'sirikonda-adilabad': ['Sirikonda', 'Adilabad'], 'mavala': ['Mavala', 'Adilabad'],
        'narnoor': ['Narnoor', 'Adilabad'], 'bazarhathnoor': ['Bazarhathnoor', 'Adilabad'],
        'indervelly': ['Indervelly', 'Adilabad'], 'gudihatnoor': ['Gudihatnoor', 'Adilabad'],
        'bela': ['Bela', 'Adilabad'], 'gadiguda': ['Gadiguda', 'Adilabad'],
        'kodad': ['Kodad', 'Suryapet'], 'huzurnagar': ['Huzurnagar', 'Suryapet'],
        'mothkur': ['Mothkur', 'Suryapet'], 'tirumalgiri': ['Tirumalgiri', 'Suryapet'],
        'mattampally': ['Mattampally', 'Suryapet'], 'garidepally': ['Garidepally', 'Suryapet'],
        'neredcherla': ['Neredcherla', 'Suryapet'], 'mellachervu': ['Mellachervu', 'Suryapet'],
        'chivvemla': ['Chivvemla', 'Suryapet'], 'penpahad': ['Penpahad', 'Suryapet'],
        'atmakur-suryapet': ['Atmakur', 'Suryapet'], 'thungathurthy': ['Thungathurthy', 'Suryapet'],
        'neredugommu': ['Neredugommu', 'Suryapet'], 'munagala': ['Munagala', 'Suryapet'],
        'zaheerabad': ['Zaheerabad', 'Sangareddy'], 'sadasivapet': ['Sadasivapet', 'Sangareddy'],
        'narayankhed': ['Narayankhed', 'Sangareddy'], 'andole': ['Andole', 'Sangareddy'],
        'jogipet': ['Jogipet', 'Sangareddy'], 'jharasangam': ['Jharasangam', 'Sangareddy'],
        'kohir': ['Kohir', 'Sangareddy'], 'kangti': ['Kangti', 'Sangareddy'],
        'nyalkal': ['Nyalkal', 'Sangareddy'], 'kandi': ['Kandi', 'Sangareddy'],
        'gajwel': ['Gajwel', 'Siddipet'], 'dubbak': ['Dubbak', 'Siddipet'],
        'thoguta': ['Thoguta', 'Siddipet'], 'chinna-kodur': ['Chinna Kodur', 'Siddipet'],
        'husnabad': ['Husnabad', 'Siddipet'], 'kondapak': ['Kondapak', 'Siddipet'],
        'nangnur': ['Nangnur', 'Siddipet'], 'wargal': ['Wargal', 'Siddipet'],
        'cheriyal': ['Cheriyal', 'Siddipet'], 'komuravelli': ['Komuravelli', 'Siddipet'],
        'toopran': ['Toopran', 'Medak'], 'narsapur': ['Narsapur', 'Medak'],
        'ramayampet': ['Ramayampet', 'Medak'], 'chegunta': ['Chegunta', 'Medak'],
        'shankarampet': ['Shankarampet', 'Medak'], 'papannapet': ['Papannapet', 'Medak'],
        'regode': ['Regode', 'Medak'], 'alladurg': ['Alladurg', 'Medak'],
        'armoor': ['Armoor', 'Nizamabad'], 'bodhan': ['Bodhan', 'Nizamabad'],
        'dichpally': ['Dichpally', 'Nizamabad'], 'navipet': ['Navipet', 'Nizamabad'],
        'varni': ['Varni', 'Nizamabad'], 'bheemgal': ['Bheemgal', 'Nizamabad'],
        'balkonda': ['Balkonda', 'Nizamabad'], 'banswada': ['Banswada', 'Kamareddy'],
        'yellareddy': ['Yellareddy', 'Kamareddy'], 'pitlam': ['Pitlam', 'Kamareddy'],
        'bhiknoor': ['Bhiknoor', 'Kamareddy'], 'machareddy': ['Machareddy', 'Kamareddy'],
        'jadcherla': ['Jadcherla', 'Mahabubnagar'], 'bhoothpur': ['Bhoothpur', 'Mahabubnagar'],
        'devarkadra': ['Devarkadra', 'Mahabubnagar'], 'achampet': ['Achampet', 'Nagarkurnool'],
        'kalwakurthy': ['Kalwakurthy', 'Nagarkurnool'], 'kollapur': ['Kollapur', 'Nagarkurnool'],
        'pebbair': ['Pebbair', 'Wanaparthy'], 'kothakota': ['Kothakota', 'Wanaparthy'],
        'alampur': ['Alampur', 'Jogulamba Gadwal'], 'makthal': ['Makthal', 'Narayanpet'],
        'kosgi': ['Kosgi', 'Narayanpet'], 'tandur': ['Tandur', 'Vikarabad'],
        'pargi': ['Pargi', 'Vikarabad'], 'kodangal': ['Kodangal', 'Vikarabad'],
        'bhongir': ['Bhongir', 'Yadadri Bhuvanagiri'], 'yadagirigutta': ['Yadagirigutta', 'Yadadri Bhuvanagiri'],
        'choutuppal': ['Choutuppal', 'Yadadri Bhuvanagiri'], 'palakurthi': ['Palakurthi', 'Jangaon'],
        'station-ghanpur': ['Station Ghanpur', 'Jangaon'], 'kazipet': ['Kazipet', 'Warangal'],
        'narsampet': ['Narsampet', 'Warangal'], 'parkal': ['Parkal', 'Warangal'],
        'hasanparthy': ['Hasanparthy', 'Hanamkonda'], 'thorrur': ['Thorrur', 'Mahabubabad'],
        'maripeda': ['Maripeda', 'Mahabubabad'], 'dornakal': ['Dornakal', 'Mahabubabad'],
        'mahadevpur': ['Mahadevpur', 'Jayashankar Bhupalpally'], 'eturnagaram': ['Eturnagaram', 'Mulugu'],
        'bhadrachalam': ['Bhadrachalam', 'Bhadradri Kothagudem'], 'palvancha': ['Palvancha', 'Bhadradri Kothagudem'],
        'yellandu': ['Yellandu', 'Bhadradri Kothagudem'], 'sattupalli': ['Sattupalli', 'Khammam'],
        'madhira': ['Madhira', 'Khammam'], 'wyra': ['Wyra', 'Khammam'],
        'vemsoor': ['Vemsoor', 'Khammam'], 'huzurabad': ['Huzurabad', 'Karimnagar'],
        'jammikunta': ['Jammikunta', 'Karimnagar'], 'vemulawada': ['Vemulawada', 'Rajanna Sircilla'],
        'koratla': ['Koratla', 'Jagtial'], 'metpally': ['Metpally', 'Jagtial'],
        'godavarikhani': ['Godavarikhani', 'Peddapalli'], 'ramagundam': ['Ramagundam', 'Peddapalli'],
        'manthani': ['Manthani', 'Peddapalli'], 'bellampalli': ['Bellampalli', 'Mancherial'],
        'mandamarri': ['Mandamarri', 'Mancherial'], 'bhainsa': ['Bhainsa', 'Nirmal'],
        'mudhole': ['Mudhole', 'Nirmal'], 'basar': ['Basar', 'Nirmal'],
        'kagaznagar': ['Kagaznagar', 'Kumuram Bheem Asifabad']
    };

    const apDistricts = {
        'visakhapatnam': 'Visakhapatnam, Andhra Pradesh', 'vizag': 'Visakhapatnam, Andhra Pradesh',
        'vijayawada': 'Vijayawada, Andhra Pradesh', 'guntur': 'Guntur, Andhra Pradesh',
        'tirupati': 'Tirupati, Andhra Pradesh', 'nellore': 'Nellore, Andhra Pradesh',
        'kurnool': 'Kurnool, Andhra Pradesh', 'kadapa': 'Kadapa, Andhra Pradesh',
        'anantapur': 'Anantapur, Andhra Pradesh', 'rajahmundry': 'Rajahmundry, Andhra Pradesh',
        'kakinada': 'Kakinada, Andhra Pradesh', 'eluru': 'Eluru, Andhra Pradesh',
        'ongole': 'Ongole, Andhra Pradesh', 'srikakulam': 'Srikakulam, Andhra Pradesh',
        'vizianagaram': 'Vizianagaram, Andhra Pradesh', 'chittoor': 'Chittoor, Andhra Pradesh',
        'nandyal': 'Nandyal, Andhra Pradesh', 'machilipatnam': 'Machilipatnam, Andhra Pradesh',
        'bhimavaram': 'Bhimavaram, Andhra Pradesh', 'tenali': 'Tenali, Andhra Pradesh',
        'proddatur': 'Proddatur, Andhra Pradesh', 'hindupur': 'Hindupur, Andhra Pradesh',
        'mangalagiri': 'Mangalagiri, Andhra Pradesh'
    };

    const apAreas = {
        'gajuwaka': ['Gajuwaka', 'Visakhapatnam'], 'madhurawada': ['Madhurawada', 'Visakhapatnam'],
        'mvp-colony': ['MVP Colony', 'Visakhapatnam'], 'seethammadhara': ['Seethammadhara', 'Visakhapatnam'],
        'benz-circle': ['Benz Circle', 'Vijayawada'], 'governorpet': ['Governorpet', 'Vijayawada'],
        'patamata': ['Patamata', 'Vijayawada'], 'gunadala': ['Gunadala', 'Vijayawada'],
        'brodipet': ['Brodipet', 'Guntur'], 'arundelpet': ['Arundelpet', 'Guntur'],
        'renigunta': ['Renigunta', 'Tirupati']
    };

    const bangaloreAreas = [
        'whitefield', 'indiranagar', 'koramangala', 'hsr-layout', 'electronic-city',
        'hebbal', 'jayanagar', 'jp-nagar', 'marathahalli', 'btm-layout', 'yelahanka',
        'banashankari', 'rajajinagar', 'malleshwaram', 'bellandur', 'sarjapur-road',
        'kanakapura-road', 'bannerghatta-road', 'thanisandra', 'hennur', 'kr-puram',
        'mahadevapura', 'varthur', 'domlur', 'frazer-town', 'basavanagudi', 'yeshwanthpur',
        'peenya', 'kengeri', 'rajarajeshwari-nagar', 'rt-nagar', 'kalyan-nagar', 'hoodi',
        'kadugodi', 'kundalahalli', 'brookefield', 'kammanahalli', 'hbr-layout', 'banaswadi'
    ];

    const chennaiAreas = [
        'velachery', 'anna-nagar', 't-nagar', 'adyar', 'tambaram', 'porur', 'mylapore',
        'nungambakkam', 'guindy', 'alwarpet', 'besant-nagar', 'thiruvanmiyur', 'sholinganallur',
        'perungudi', 'thoraipakkam', 'navalur', 'siruseri', 'medavakkam', 'pallikaranai',
        'madipakkam', 'chromepet', 'pallavaram', 'vadapalani', 'koyambedu', 'ashok-nagar',
        'kk-nagar', 'valasaravakkam', 'ambattur', 'avadi', 'poonamallee', 'omr', 'ecr'
    ];

    const mumbaiAreas = [
        'andheri', 'bandra', 'borivali', 'juhu', 'goregaon', 'malad', 'kandivali',
        'santacruz', 'vile-parle', 'kurla', 'ghatkopar', 'powai', 'mulund', 'bhandup',
        'chembur', 'dadar', 'worli', 'thane', 'kalyan', 'dombivli', 'mira-road', 'navi-mumbai'
    ];

    const puneAreas = [
        'kothrud', 'baner', 'aundh', 'wakad', 'hinjewadi', 'hinjawadi', 'viman-nagar',
        'kalyani-nagar', 'koregaon-park', 'magarpatta', 'hadapsar', 'kharadi', 'pimpri',
        'chinchwad', 'bhosari', 'ravet', 'nigdi', 'pimple-saudagar', 'bavdhan'
    ];

    const otherCities = {
        'bangalore': 'Bangalore, Karnataka', 'bengaluru': 'Bangalore, Karnataka',
        'mysore': 'Mysore, Karnataka', 'mangalore': 'Mangalore, Karnataka',
        'hubli': 'Hubli, Karnataka', 'chennai': 'Chennai, Tamil Nadu',
        'coimbatore': 'Coimbatore, Tamil Nadu', 'madurai': 'Madurai, Tamil Nadu',
        'trichy': 'Trichy, Tamil Nadu', 'salem': 'Salem, Tamil Nadu',
        'mumbai': 'Mumbai, Maharashtra', 'pune': 'Pune, Maharashtra',
        'nagpur': 'Nagpur, Maharashtra', 'nashik': 'Nashik, Maharashtra',
        'delhi': 'Delhi, NCR', 'gurugram': 'Gurugram, Haryana', 'noida': 'Noida, Uttar Pradesh',
        'kolkata': 'Kolkata, West Bengal', 'ahmedabad': 'Ahmedabad, Gujarat',
        'surat': 'Surat, Gujarat', 'jaipur': 'Jaipur, Rajasthan',
        'lucknow': 'Lucknow, Uttar Pradesh', 'patna': 'Patna, Bihar',
        'bhopal': 'Bhopal, Madhya Pradesh', 'indore': 'Indore, Madhya Pradesh',
        'raipur': 'Raipur, Chhattisgarh', 'bhubaneswar': 'Bhubaneswar, Odisha',
        'kochi': 'Kochi, Kerala', 'thiruvananthapuram': 'Thiruvananthapuram, Kerala',
        'chandigarh': 'Chandigarh, Punjab', 'goa': 'Goa, India'
    };

    function titleCase(str) {
        return (str || '').replace(/-/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });
    }

    function resolveSmartGeoLocation(inputSlugOrText, defaultLoc) {
        defaultLoc = defaultLoc || 'Hyderabad, Telangana';
        if (!inputSlugOrText) return defaultLoc;

        let clean = (inputSlugOrText + '').toLowerCase().trim();
        clean = clean.replace(/^.*?-in-/i, '');
        clean = clean.replace(/\b(safety-nets|pigeon-nets|invisible-grill|invisible-grills|cloth-hanger|cloth-hangers|bird-spikes|spikes|sports-nets|cricket-nets|football-turf|box-cricket|turf|dealers|suppliers|price|price-per-sqft|near-me|installation|installation-near-me|wholesalers|manufacturers|rate|cost|services|service)\b/ig, '');
        clean = clean.replace(/[^a-z0-9\s-]/g, ' ').trim().replace(/[\s-]+/g, '-');
        if (!clean) return defaultLoc;

        const cleanBase = clean.replace(/-(dt|district|dist|area|mandal|town|city|tehsil|taluk|village|hub|zone|colony|layout|road|street|junction|x-roads|cross-roads|crossroads)$/i, '');

        // 1. Telangana Districts
        if (tgDistricts[clean]) return tgDistricts[clean];
        if (tgDistricts[cleanBase]) return tgDistricts[cleanBase];

        // 2. Hyderabad Localities
        if (hydAreas[clean]) return hydAreas[clean] + ', Hyderabad, Telangana';
        if (hydAreas[cleanBase]) return hydAreas[cleanBase] + ', Hyderabad, Telangana';

        // 3. Telangana Mandals
        if (tgMandals[clean]) return tgMandals[clean][0] + ', ' + tgMandals[clean][1] + ', Telangana';
        if (tgMandals[cleanBase]) return tgMandals[cleanBase][0] + ', ' + tgMandals[cleanBase][1] + ', Telangana';

        // 4. AP Districts & Areas
        if (apDistricts[clean]) return apDistricts[clean];
        if (apDistricts[cleanBase]) return apDistricts[cleanBase];
        if (apAreas[clean]) return apAreas[clean][0] + ', ' + apAreas[clean][1] + ', Andhra Pradesh';
        if (apAreas[cleanBase]) return apAreas[cleanBase][0] + ', ' + apAreas[cleanBase][1] + ', Andhra Pradesh';

        // 5. Bangalore / Chennai / Mumbai / Pune
        if (bangaloreAreas.includes(clean) || bangaloreAreas.includes(cleanBase)) return titleCase(clean) + ', Bangalore, Karnataka';
        if (chennaiAreas.includes(clean) || chennaiAreas.includes(cleanBase)) return titleCase(clean) + ', Chennai, Tamil Nadu';
        if (mumbaiAreas.includes(clean) || mumbaiAreas.includes(cleanBase)) return titleCase(clean) + ', Mumbai, Maharashtra';
        if (puneAreas.includes(clean) || puneAreas.includes(cleanBase)) return titleCase(clean) + ', Pune, Maharashtra';

        // 6. Other cities
        if (otherCities[clean]) return otherCities[clean];
        if (otherCities[cleanBase]) return otherCities[cleanBase];

        // 7. Regex sub-district match
        const m = clean.match(/^([a-z0-9-]+)-(?:in-|dt-|near-)?(nalgonda|adilabad|suryapet|sangareddy|siddipet|medak|nizamabad|kamareddy|mahabubnagar|nagarkurnool|wanaparthy|gadwal|narayanpet|vikarabad|rangareddy|medchal|yadadri|jangaon|warangal|hanamkonda|mahabubabad|bhupalpally|mulugu|kothagudem|khammam|karimnagar|sircilla|jagtial|peddapalli|mancherial|nirmal|asifabad)$/i);
        if (m) {
            const aName = titleCase(m[1]);
            const dKey = m[2].toLowerCase();
            const dName = tgDistricts[dKey] ? tgDistricts[dKey].split(',')[0] : titleCase(dKey);
            return aName + ', ' + dName + ', Telangana';
        }

        return titleCase(clean) + ', Hyderabad, Telangana';
    }

    function buildSmartMessage() {
        const title = document.title || '';
        const path = window.location.pathname.toLowerCase();
        let service = '';

        if (path.includes('box-cricket') || title.toLowerCase().includes('box cricket')) {
            service = 'Box Cricket Ground Setup & Netting';
        } else if (path.includes('football-turf') || title.toLowerCase().includes('football turf') || path.includes('5-a-side') || path.includes('7-a-side') || path.includes('8-a-side')) {
            service = 'Football Turf Setup & Perimeter Netting';
        } else if (path.includes('cricket') || title.toLowerCase().includes('cricket')) {
            service = 'Cricket Practice Pitch Nets Installation';
        } else if (path.includes('invisible-grill') || title.toLowerCase().includes('invisible grill')) {
            service = 'Stainless Steel Invisible Grills';
        } else if (path.includes('balcony') || title.toLowerCase().includes('balcony')) {
            service = 'Balcony Safety Nets Installation';
        } else if (path.includes('bird') || title.toLowerCase().includes('bird')) {
            service = 'Anti-Bird & Pigeon Protection Nets';
        } else if (path.includes('pigeon') || title.toLowerCase().includes('pigeon')) {
            service = 'Pigeon Safety Nets & Spikes';
        } else if (path.includes('sports') || title.toLowerCase().includes('sports')) {
            service = 'Sports Netting & Court Enclosures';
        } else if (path.includes('grass') || title.toLowerCase().includes('artificial grass')) {
            service = 'Artificial Grass Turf & Flooring';
        } else if (path.includes('cloth-hanger') || path.includes('drying') || path.includes('pull-and-dry')) {
            service = 'Balcony & Ceiling Cloth Drying Hangers';
        } else {
            service = title.split(/[–—|-]/)[0].trim() || 'Safety Nets & Invisible Grills';
        }

        let locInput = path;
        const m = path.match(/-in-([a-z0-9-]+)/);
        if (m) {
            locInput = m[1];
        }
        const location = resolveSmartGeoLocation(locInput, 'Hyderabad, Telangana (All Areas & Pan-India Supply)');

        let pricingPrompt = 'Please share pricing per sq.ft, technical specifications, and installation availability.';
        const lowerSvc = service.toLowerCase();
        if (lowerSvc.includes('hanger') || lowerSvc.includes('stand') || lowerSvc.includes('rack')) {
            pricingPrompt = 'Please share pricing per piece / set, model options, technical specifications, and installation availability.';
        } else if (lowerSvc.includes('cricket') || lowerSvc.includes('turf') || lowerSvc.includes('court')) {
            pricingPrompt = 'Please share turnkey court setup cost / pricing per sq.ft, technical specifications, and installation availability.';
        }

        return `Hi NettingTeam, I would like to inquire about *${service}* in *${location}*.\n\n` +
               `• Service: ${service}\n` +
               `• Location: ${location}\n` +
               `• Page: ${window.location.href}\n` +
               `• Website: www.nettingteam.com\n\n` +
               pricingPrompt;
    }

    function decorateAllWhatsAppLinks() {
        const smartMsg = buildSmartMessage();
        const smartUrl = `https://wa.me/919912399334?text=${encodeURIComponent(smartMsg)}`;
        
        const links = document.querySelectorAll('a[href*="wa.me"], a[href*="whatsapp.com"], .whatsapp-link, .contact-btn.whatsapp');
        links.forEach(link => {
            const currentHref = link.getAttribute('href') || '';
            if (!currentHref.includes('text=') || currentHref.includes('safety%20net%20services') || currentHref === 'https://wa.me/919912399334') {
                link.setAttribute('href', smartUrl);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', decorateAllWhatsAppLinks);
    } else {
        decorateAllWhatsAppLinks();
    }

    document.addEventListener('click', function(e) {
        const target = e.target.closest('a[href*="wa.me"], a[href*="whatsapp.com"], .whatsapp-link, .contact-btn.whatsapp');
        if (target) {
            const currentHref = target.getAttribute('href') || '';
            if (!currentHref.includes('text=') || currentHref.includes('safety%20net%20services') || currentHref === 'https://wa.me/919912399334') {
                const smartMsg = buildSmartMessage();
                target.setAttribute('href', `https://wa.me/919912399334?text=${encodeURIComponent(smartMsg)}`);
            }
        }
    }, true);
})();

