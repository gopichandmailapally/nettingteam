/**
 * ═══════════════════════════════════════════════════════════════════════════
 * GCM XTREME — NEXT-GEN BIM & PARAMETRIC SPORTS ARCHITECTURE ENGINE
 * Complete AI Prompt Parser, Multi-Court Campus, Dual-Sport Overlays,
 * Triple-Click Surface Inspector, and Interactive Drag & Snap System
 * ═══════════════════════════════════════════════════════════════════════════
 */

(function(window) {
  'use strict';

  var GcmXtreme = {
    version: '2.0.0-PRO',
    name: 'GCM XTREME',
    
    // Core State
    state: {
      campusName: 'GCM Xtreme Sports Arena',
      length: 160,
      width: 60,
      height: 30,
      pitch_length: 66,
      sport_type: 'multisport_arena_complex_185x60',
      roof_type: 'curved_truss',
      style: 'full_steel',
      currentVisTab: '3d',
      activeModalCompId: null,
      
      // Multi-Court Campus Structure
      courts: [
        {
          id: 'court_1',
          name: 'Main Sports Arena',
          sport: 'cricket',
          secondarySport: null,
          length: 100,
          width: 60,
          offset_x: 0,
          offset_y: 0,
          attachment: 'primary', // 'primary', 'attached', 'offset'
          gap_distance: 0, // distance in FT (e.g. 2 FT, 1 M = 3.28 FT)
          flooring: 'turf_50mm_12000dtex',
          has_pitch: true,
          pitch_length: 66
        },
        {
          id: 'court_2',
          name: 'Indoor Badminton Arena (2 Courts)',
          sport: 'badminton',
          secondarySport: null,
          length: 60,
          width: 60,
          offset_x: 100,
          offset_y: 0,
          attachment: 'attached',
          gap_distance: 0,
          flooring: 'vinyl_mat_badminton',
          has_pitch: false,
          courts_count: 2
        }
      ],
      
      // Interactive Gates with continuous drag coordinates
      gates: [
        {
          id: 'gate_1',
          name: 'Sideline Player Door',
          wall: 'left', // 'front', 'back', 'left', 'right'
          position_ft: 25.0, // Distance along wall in FT
          width_ft: 4.0,
          height_ft: 7.0,
          type: 'single_hinged'
        },
        {
          id: 'gate_2',
          name: 'Equipment Access Gate',
          wall: 'back',
          position_ft: 30.0,
          width_ft: 8.0,
          height_ft: 8.0,
          type: 'double_sliding'
        }
      ],
      
      // Master Engineering Components (Linked to MasterProductCatalog)
      components: {
        civil_base: {
          id: 'civil_base',
          category: 'Civil Works & Ground Sub-Base',
          name: '4" M20 Laser-Levelled Concrete Bed with 8mm TMT Reinforcement',
          brand: 'Ultratech RMC / Fe550D TMT',
          spec: 'High-strength M20 grade laser levelled PCC/RCC bedding with dual layer 8mm steel mesh.',
          gauge: '4" Depth, 8mm TMT Mesh @ 150mm c/c',
          unit: 'SQFT',
          rate: 75,
          included: true,
          icon: 'fa-cubes-stacked text-warning',
          calcQty: function(s) { return s.length * s.width; }
        },
        structural_framework: {
          id: 'structural_framework',
          category: 'Structural Steel & Framework',
          name: 'Heavy-Duty Galvanized Tubular Steel Column Framework with Y-Arms',
          brand: 'Apollo Steel / Jindal',
          spec: 'Hot-dip galvanized hollow structural steel columns with intermediate wind ties.',
          gauge: '80×80mm × 3.2mm Main Columns',
          unit: 'SQFT',
          rate: 45,
          included: true,
          icon: 'fa-landmark text-info',
          calcQty: function(s) { return s.length * s.width; }
        },
        roof_canopy: {
          id: 'roof_canopy',
          category: 'Overhead Roof & Weather Protection',
          name: 'Curved Arch Tubular Steel Roof Truss with 0.50mm PPGL Roofing Sheets',
          brand: 'Tata Bluescope Colorbond',
          spec: 'Curved arch lattice roof truss with pre-painted galvalume weatherproofing sheets.',
          gauge: '0.50mm PPGL Profile (AZ150, 550 MPa)',
          unit: 'SQFT',
          rate: 58,
          included: true,
          icon: 'fa-shield-halved text-primary',
          calcQty: function(s) { return s.length * s.width; }
        },
        wall_enclosures: {
          id: 'wall_enclosures',
          category: 'Perimeter Walls, Enclosures & Netting',
          name: '0.50mm PPGL Metal Profile Cladding with 4.0mm High-Impact Braided HDPE Netting',
          brand: 'Garware Technical Fibres & AMNS',
          spec: 'Lower wall weather sheet protection with upper UV-stabilized boundary ball-stop netting.',
          gauge: '0.50mm Lower Sheets + 4.0mm 40mm Sq Mesh',
          unit: 'SQFT',
          rate: 42,
          included: true,
          icon: 'fa-border-all text-secondary',
          calcQty: function(s) { return 2 * (s.length + s.width) * s.height; }
        },
        sports_flooring: {
          id: 'sports_flooring',
          category: 'Sports Flooring & Synthetic Surfaces',
          name: '50mm FIFA Quality Pro Monofilament Artificial Turf with SBR/Sand Infill',
          brand: 'CCGrass Pro Turf',
          spec: '12,000 DTEX bi-color diamond spine synthetic grass, silicon sand & cryogenic SBR granules.',
          gauge: '50mm Pile Height, 12,000 DTEX',
          unit: 'SQFT',
          rate: 58,
          included: true,
          icon: 'fa-seedling text-success',
          calcQty: function(s) { 
            var turfCourt = s.courts.find(function(c) { return c.sport === 'cricket' || c.sport === 'football'; });
            return turfCourt ? (turfCourt.length * turfCourt.width) : (s.length * s.width);
          }
        },
        cricket_pitch: {
          id: 'cricket_pitch',
          category: 'Sports Flooring & Synthetic Surfaces',
          name: 'Pro Cricket Pitch Surface: 15mm High-Density Multi-Sport Turf Pad (83 FT)',
          brand: 'Russea Cricket Deck',
          spec: 'Dual-tone non-directional texturized high-density turf pad for true ball bounce and spin.',
          gauge: '15mm Dense Curled Pile, Dual Backing',
          unit: 'SQFT',
          rate: 78,
          included: true,
          icon: 'fa-cricket-bat-ball text-success',
          calcQty: function(s) { return (s.pitch_length || 66) * 6.5; }
        },
        badminton_arena: {
          id: 'badminton_arena',
          category: 'Sports Flooring & Synthetic Surfaces',
          name: 'Indoor Badminton Arena System: 2 BWF Regulation Courts with 4.5mm Vinyl Sports Mats',
          brand: 'Gerflor Taraflex / Enlio BWF',
          spec: 'Competition court setup with shock-absorption backing, precision boundary court lines.',
          gauge: '4.5mm BWF Approved Multi-Layer Mat',
          unit: 'SQFT',
          rate: 95,
          included: true,
          icon: 'fa-feather text-warning',
          calcQty: function(s) { 
            var badm = s.courts.find(function(c) { return c.sport === 'badminton'; });
            return badm ? (badm.length * badm.width) : 0;
          }
        },
        sports_lighting: {
          id: 'sports_lighting',
          category: 'Sports Lighting & Electrical Systems',
          name: 'Pro Stadium LED Floodlight Array (300W IP66 High-Mast Fixtures with Armatures)',
          brand: 'Philips Sports Lighting / Osram',
          spec: 'High-efficacy 150 lm/W glare-free stadium floodlights with dedicated cable harness.',
          gauge: '300W IP66 / IK08 Optical Lens',
          unit: 'NOS',
          rate: 14500,
          included: true,
          icon: 'fa-lightbulb text-warning',
          calcQty: function(s) { return Math.max(6, Math.round((s.length * s.width) / 1000)); }
        },
        player_gates: {
          id: 'player_gates',
          category: 'Gates & Access Hardware',
          name: 'Heavy-Duty Structural Steel Hinged Access Gates (4 FT × 7 FT)',
          brand: 'GCM Custom Fabrication',
          spec: 'Frame with ball-bearing hinges, lock latch, and security mesh cover.',
          gauge: '40×40mm Outer Frame + Mesh',
          unit: 'NOS',
          rate: 9500,
          included: true,
          icon: 'fa-door-open text-info',
          calcQty: function(s) { return s.gates.length || 2; }
        },
        amenities_lounge: {
          id: 'amenities_lounge',
          category: 'Turnkey Amenities & Operations',
          name: 'Turnkey Reception, Player Changing Lounge & Equipment Room Setup',
          brand: 'GCM Modular Infrastructure',
          spec: 'Prefab partition walls, bench seating, equipment storage racks, and interior lighting.',
          gauge: 'Modular 25 FT × 60 FT Utility Wing',
          unit: 'SQFT',
          rate: 180,
          included: false,
          icon: 'fa-couch text-primary',
          calcQty: function(s) { return 25 * s.width; }
        }
      }
    },

    // ─────────────────────────────────────────────────────────────────────────
    // 1. NATURAL LANGUAGE / AI PROMPT-TO-ARENA GENERATOR
    // ─────────────────────────────────────────────────────────────────────────
    parseArenaPrompt: function(promptText) {
      if (!promptText || typeof promptText !== 'string') return null;
      var text = promptText.toLowerCase().trim();
      var s = this.state;
      var newCourts = [];
      var detectedSports = [];

      // Detect sports keywords
      var hasCricket = text.indexOf('cricket') !== -1;
      var hasBadminton = text.indexOf('badminton') !== -1;
      var hasBasketball = text.indexOf('basket') !== -1 || text.indexOf('hoop') !== -1;
      var hasFootball = text.indexOf('foot') !== -1 || text.indexOf('futsal') !== -1 || text.indexOf('turf') !== -1;
      var hasPickleball = text.indexOf('pickle') !== -1;
      var hasTennis = text.indexOf('tennis') !== -1 && text.indexOf('table') === -1;
      var hasVolleyball = text.indexOf('volley') !== -1;

      // Detect dual game / shared single court
      var isSharedOneCourt = text.indexOf('same court') !== -1 || 
                             text.indexOf('in one court') !== -1 || 
                             text.indexOf('one court') !== -1 || 
                             text.indexOf('dual') !== -1 || 
                             text.indexOf('included in same') !== -1;

      // Extract quantities
      var badmCountMatch = text.match(/(\d+)\s*(?:court)?\s*badminton|badminton\s*(?:for)?\s*(\d+)\s*court/);
      var badmCount = badmCountMatch ? parseInt(badmCountMatch[1] || badmCountMatch[2], 10) : (hasBadminton ? 2 : 0);

      var pickleCountMatch = text.match(/(\d+)\s*(?:court)?\s*pickle|pickle\s*(?:ball)?\s*(\d+)/);
      var pickleCount = pickleCountMatch ? parseInt(pickleCountMatch[1] || pickleCountMatch[2], 10) : (hasPickleball ? 2 : 0);

      // Detect distance / gap
      var gapMatch = text.match(/(\d+(?:\.\d+)?)\s*(?:ft|foot|feet|meter|m)\s*(?:distance|gap|walkway|apart)?/);
      var gapFt = 0;
      if (gapMatch) {
        var num = parseFloat(gapMatch[1]);
        if (text.indexOf('meter') !== -1 || text.indexOf(' m ') !== -1) {
          gapFt = Math.round(num * 3.28084);
        } else {
          gapFt = num;
        }
      }

      // Detect roof / enclosure style
      if (text.indexOf('indoor') !== -1 || text.indexOf('roof') !== -1 || text.indexOf('stadium') !== -1 || text.indexOf('shed') !== -1) {
        s.style = 'full_steel';
        s.roof_type = 'curved_truss';
        s.components.roof_canopy.included = true;
        s.components.wall_enclosures.included = true;
      } else if (text.indexOf('cable net') !== -1 || text.indexOf('top net') !== -1) {
        s.style = 'cable_net';
        s.roof_type = 'flat_net';
        s.components.roof_canopy.included = true;
        s.components.roof_canopy.name = 'Wire-Rope Cable Suspended Overhead Ball-Stop Netting';
      } else if (text.indexOf('open') !== -1 || text.indexOf('open to sky') !== -1) {
        s.style = 'open_sky';
        s.roof_type = 'open_top';
        s.components.roof_canopy.included = false;
      }

      // Build courts matrix
      var currentOffsetX = 0;
      var totalL = 0;
      var maxW = 60;

      if (isSharedOneCourt) {
        // Dual-game in one single court
        var prim = 'cricket';
        var sec = null;
        var courtW = 60;
        var courtL = 100;

        if (hasBadminton && hasBasketball) {
          prim = 'basketball';
          sec = 'badminton';
          courtL = 90;
          courtW = 55;
        } else if (hasFootball && hasBasketball) {
          prim = 'football';
          sec = 'basketball';
          courtL = 110;
          courtW = 60;
        } else if (hasBadminton && hasPickleball) {
          prim = 'badminton';
          sec = 'pickleball';
          courtL = 88;
          courtW = 44;
        } else if (hasCricket && (hasPickleball || hasBadminton)) {
          prim = 'cricket';
          sec = hasPickleball ? 'pickleball' : 'badminton';
          courtL = 100;
          courtW = 60;
        }

        newCourts.push({
          id: 'court_shared_1',
          name: prim.toUpperCase() + ' & ' + (sec ? sec.toUpperCase() : '') + ' Dual-Game Hybrid Court',
          sport: prim,
          secondarySport: sec,
          length: courtL,
          width: courtW,
          offset_x: 0,
          offset_y: 0,
          attachment: 'primary',
          gap_distance: 0,
          flooring: (prim === 'cricket' || prim === 'football') ? 'turf_50mm_12000dtex' : 'vinyl_mat_badminton',
          has_pitch: (prim === 'cricket'),
          pitch_length: (prim === 'cricket' ? 66 : 0)
        });

        totalL = courtL;
        maxW = courtW;
      } else {
        // Multi-court layout
        if (hasCricket) {
          newCourts.push({
            id: 'court_cricket',
            name: 'Box Cricket Ground',
            sport: 'cricket',
            secondarySport: null,
            length: 100,
            width: 60,
            offset_x: currentOffsetX,
            offset_y: 0,
            attachment: 'primary',
            gap_distance: 0,
            flooring: 'turf_50mm_12000dtex',
            has_pitch: true,
            pitch_length: 66
          });
          currentOffsetX += 100 + gapFt;
          totalL += 100 + gapFt;
          maxW = Math.max(maxW, 60);
        }

        if (hasFootball) {
          var fL = text.indexOf('7a') !== -1 || text.indexOf('7-a') !== -1 ? 140 : 100;
          var fW = text.indexOf('7a') !== -1 || text.indexOf('7-a') !== -1 ? 80 : 60;
          newCourts.push({
            id: 'court_football',
            name: (text.indexOf('7a') !== -1 ? '7-A-Side' : '5-A-Side') + ' Football Turf',
            sport: 'football',
            secondarySport: hasBasketball ? 'basketball' : null,
            length: fL,
            width: fW,
            offset_x: currentOffsetX,
            offset_y: 0,
            attachment: currentOffsetX > 0 ? (gapFt > 0 ? 'offset' : 'attached') : 'primary',
            gap_distance: gapFt,
            flooring: 'turf_50mm_12000dtex',
            has_pitch: false
          });
          currentOffsetX += fL + gapFt;
          totalL += fL + gapFt;
          maxW = Math.max(maxW, fW);
        }

        if (hasBadminton) {
          var bL = badmCount > 2 ? (badmCount * 22 + 20) : 60;
          var bW = 60;
          newCourts.push({
            id: 'court_badminton',
            name: 'Indoor Badminton Arena (' + badmCount + ' Courts)',
            sport: 'badminton',
            secondarySport: hasBasketball ? 'basketball' : null,
            length: bL,
            width: bW,
            offset_x: currentOffsetX,
            offset_y: 0,
            attachment: currentOffsetX > 0 ? (gapFt > 0 ? 'offset' : 'attached') : 'primary',
            gap_distance: gapFt,
            flooring: 'vinyl_mat_badminton',
            has_pitch: false,
            courts_count: badmCount
          });
          currentOffsetX += bL + gapFt;
          totalL += bL + gapFt;
          maxW = Math.max(maxW, bW);
        }

        if (hasPickleball) {
          var pL = 50;
          var pW = 60;
          newCourts.push({
            id: 'court_pickleball',
            name: 'Pro Pickleball Arena (' + pickleCount + ' Courts)',
            sport: 'pickleball',
            secondarySport: null,
            length: pL,
            width: pW,
            offset_x: currentOffsetX,
            offset_y: 0,
            attachment: currentOffsetX > 0 ? (gapFt > 0 ? 'offset' : 'attached') : 'primary',
            gap_distance: gapFt,
            flooring: 'synthetic_acrylic',
            has_pitch: false,
            courts_count: pickleCount
          });
          currentOffsetX += pL + gapFt;
          totalL += pL + gapFt;
          maxW = Math.max(maxW, pW);
        }
      }

      if (newCourts.length === 0) {
        // Fallback default
        newCourts.push({
          id: 'court_custom',
          name: 'Custom Sports Arena',
          sport: 'cricket',
          secondarySport: null,
          length: 100,
          width: 60,
          offset_x: 0,
          offset_y: 0,
          attachment: 'primary',
          gap_distance: 0,
          flooring: 'turf_50mm_12000dtex',
          has_pitch: true,
          pitch_length: 66
        });
        totalL = 100;
        maxW = 60;
      }

      s.courts = newCourts;
      s.length = totalL;
      s.width = maxW;
      s.height = (hasBadminton || hasBasketball) ? 30 : 25;
      s.pitch_length = hasCricket ? 66 : 0;
      s.components.cricket_pitch.included = hasCricket;
      s.components.badminton_arena.included = hasBadminton;
      s.components.sports_flooring.included = hasCricket || hasFootball;

      return {
        prompt: promptText,
        courtsCount: newCourts.length,
        totalLength: totalL,
        width: maxW,
        height: s.height,
        courts: newCourts
      };
    },

    // ─────────────────────────────────────────────────────────────────────────
    // 2. TRIPLE-CLICK SURFACE INSPECTOR & RADIAL HUD
    // ─────────────────────────────────────────────────────────────────────────
    initTripleClickInspector: function(canvasOrSvgId) {
      var self = this;
      var el = document.getElementById(canvasOrSvgId);
      if (!el) return;

      var clickCount = 0;
      var clickTimer = null;

      el.addEventListener('click', function(e) {
        clickCount++;
        clearTimeout(clickTimer);

        if (clickCount >= 3) {
          // Triple click detected!
          clickCount = 0;
          var rect = el.getBoundingClientRect();
          var clickX = e.clientX - rect.left;
          var clickY = e.clientY - rect.top;

          var targetSurface = self.detectSurfaceAt(clickX, clickY, el);
          self.openTripleClickHud(targetSurface, e.clientX, e.clientY);
        } else {
          clickTimer = setTimeout(function() {
            clickCount = 0;
          }, 450);
        }
      });
    },

    detectSurfaceAt: function(x, y, container) {
      // Heuristic detection based on viewport coordinates and current mode
      var s = this.state;
      var w = container.clientWidth || 800;
      var h = container.clientHeight || 450;

      var relX = x / w;
      var relY = y / h;

      if (relY < 0.25) {
        return { id: 'roof_canopy', name: 'Overhead Roof Truss & PPGL Canopy', category: 'Roofing & Canopy' };
      } else if (relY > 0.75) {
        if (relX > 0.3 && relX < 0.7 && s.components.cricket_pitch.included) {
          return { id: 'cricket_pitch', name: 'Center Cricket Pitch Deck', category: 'Sports Flooring & Synthetic Surfaces' };
        }
        return { id: 'sports_flooring', name: 'Ground Synthetic Turf / Court Flooring', category: 'Sports Flooring & Synthetic Surfaces' };
      } else if (relX < 0.25) {
        return { id: 'wall_enclosures', name: 'Left Sideline Enclosure & Netting', category: 'Perimeter Walls, Enclosures & Netting' };
      } else if (relX > 0.75) {
        return { id: 'wall_enclosures', name: 'Right Sideline Enclosure & Netting', category: 'Perimeter Walls, Enclosures & Netting' };
      } else {
        return { id: 'structural_framework', name: 'Structural Column Bay & Wind Ties', category: 'Structural Steel & Framework' };
      }
    },

    openTripleClickHud: function(surface, screenX, screenY) {
      var self = this;
      var existing = document.getElementById('gcmTripleClickHud');
      if (existing) existing.remove();

      var hud = document.createElement('div');
      hud.id = 'gcmTripleClickHud';
      hud.style.position = 'fixed';
      hud.style.left = Math.min(window.innerWidth - 320, Math.max(10, screenX - 150)) + 'px';
      hud.style.top = Math.min(window.innerHeight - 300, Math.max(10, screenY - 50)) + 'px';
      hud.style.width = '300px';
      hud.style.background = 'rgba(15, 23, 42, 0.95)';
      hud.style.backdropFilter = 'blur(12px)';
      hud.style.border = '2px solid #38bdf8';
      hud.style.borderRadius = '12px';
      hud.style.boxShadow = '0 12px 36px rgba(0, 0, 0, 0.6)';
      hud.style.color = '#fff';
      hud.style.zIndex = '99999';
      hud.style.padding = '12px';
      hud.style.fontSize = '12px';

      var comp = self.state.components[surface.id] || { name: surface.name, included: true };

      hud.innerHTML = 
        '<div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-secondary">' +
          '<div class="fw-bold text-info text-truncate">' +
            '<i class="fa fa-crosshairs me-1"></i>' + surface.name +
          '</div>' +
          '<button type="button" class="btn-close btn-close-white btn-sm" onclick="this.parentElement.parentElement.remove()"></button>' +
        '</div>' +
        '<div class="text-light text-opacity-75 small mb-3">' +
          'Status: <strong class="' + (comp.included ? 'text-success' : 'text-danger') + '">' + (comp.included ? 'Active Installed' : 'Excluded') + '</strong>' +
        '</div>' +
        '<div class="d-grid gap-2">' +
          '<button type="button" class="btn btn-sm btn-success fw-bold text-start" id="btnHudAdd">' +
            '<i class="fa fa-plus-circle me-2"></i><strong>+ ADD Product</strong> to this Surface' +
          '</button>' +
          '<button type="button" class="btn btn-sm btn-outline-danger fw-bold text-start" id="btnHudDelete">' +
            '<i class="fa fa-trash me-2"></i><strong>🗑️ DELETE / REMOVE</strong> Items at this spot' +
          '</button>' +
          '<button type="button" class="btn btn-sm btn-outline-info fw-bold text-start" id="btnHudModify">' +
            '<i class="fa fa-sliders me-2"></i><strong>✏️ MODIFY / UPDATE</strong> Specs &amp; Rates' +
          '</button>' +
        '</div>';

      document.body.appendChild(hud);

      // Bind button events
      document.getElementById('btnHudAdd').onclick = function() {
        hud.remove();
        self.openProductSearchModalForSurface(surface);
      };

      document.getElementById('btnHudDelete').onclick = function() {
        hud.remove();
        if (confirm('Remove ' + surface.name + ' from the arena and quotation?')) {
          if (self.state.components[surface.id]) {
            self.state.components[surface.id].included = false;
          }
          if (typeof window.renderStudioComponentCards === 'function') window.renderStudioComponentCards();
          if (typeof window.calcStudioBoq === 'function') window.calcStudioBoq();
          if (typeof window.updateStudioVisualizer === 'function') window.updateStudioVisualizer();
        }
      };

      document.getElementById('btnHudModify').onclick = function() {
        hud.remove();
        if (typeof window.openStudioCompCustomizer === 'function') {
          window.openStudioCompCustomizer(surface.id);
        }
      };
    },

    openProductSearchModalForSurface: function(surface) {
      var self = this;
      var catalog = window.MasterProductCatalog || [];
      var modalEl = document.getElementById('studioCompCustomizerModal');
      
      // Filter catalog for this category
      var matches = catalog.filter(function(p) {
        if (!p.category) return false;
        var pCat = p.category.toLowerCase();
        var sCat = (surface.category || surface.name).toLowerCase();
        return pCat.indexOf(sCat.split(' ')[0]) !== -1 || sCat.indexOf(pCat.split(' ')[0]) !== -1;
      });

      if (matches.length > 0 && self.state.components[surface.id]) {
        self.state.components[surface.id].included = true;
        if (typeof window.openStudioCompCustomizer === 'function') {
          window.openStudioCompCustomizer(surface.id);
        }
      } else {
        if (window.GcmNotify) { GcmNotify.info('Master Products Catalog', 'Browsing master architectural database for ' + surface.name); } else { alert('Browsing 281 Master Products for ' + surface.name); }
      }
    },

    // ─────────────────────────────────────────────────────────────────────────
    // 3. INTERACTIVE GATES DRAG-AND-DROP SYSTEM
    // ─────────────────────────────────────────────────────────────────────────
    initGateDragGizmo: function(svgContainerId) {
      var self = this;
      var container = document.getElementById(svgContainerId);
      if (!container) return;

      var isDraggingGate = false;
      var activeGateId = null;

      container.addEventListener('mousedown', function(e) {
        var gateEl = e.target.closest('.interactive-gate-handle');
        if (gateEl) {
          isDraggingGate = true;
          activeGateId = gateEl.getAttribute('data-gate-id');
          e.preventDefault();
        }
      });

      window.addEventListener('mousemove', function(e) {
        if (!isDraggingGate || !activeGateId) return;
        var gate = self.state.gates.find(function(g) { return g.id === activeGateId; });
        if (!gate) return;

        var rect = container.getBoundingClientRect();
        var relX = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
        
        // Convert to FT along wall
        var wallLength = (gate.wall === 'front' || gate.wall === 'back') ? self.state.width : self.state.length;
        gate.position_ft = Math.round(relX * wallLength * 10) / 10;

        self.updateGateTooltip(gate, e.clientX, e.clientY);
        if (typeof window.updateStudioVisualizer === 'function') {
          window.updateStudioVisualizer();
        }
      });

      window.addEventListener('mouseup', function() {
        if (isDraggingGate) {
          isDraggingGate = false;
          activeGateId = null;
          var tip = document.getElementById('gcmGateDragTooltip');
          if (tip) tip.remove();
        }
      });
    },

    updateGateTooltip: function(gate, clientX, clientY) {
      var tip = document.getElementById('gcmGateDragTooltip');
      if (!tip) {
        tip = document.createElement('div');
        tip.id = 'gcmGateDragTooltip';
        tip.style.position = 'fixed';
        tip.style.background = '#0284c7';
        tip.style.color = '#fff';
        tip.style.padding = '4px 8px';
        tip.style.borderRadius = '4px';
        tip.style.fontSize = '11px';
        tip.style.fontWeight = 'bold';
        tip.style.pointerEvents = 'none';
        tip.style.zIndex = '99999';
        tip.style.boxShadow = '0 4px 12px rgba(0,0,0,0.4)';
        document.body.appendChild(tip);
      }

      var meters = (gate.position_ft * 0.3048).toFixed(2);
      tip.style.left = (clientX + 15) + 'px';
      tip.style.top = (clientY - 25) + 'px';
      tip.textContent = gate.name + ': ' + gate.position_ft + ' FT [' + meters + ' M] from corner';
    }
  };

  window.GcmXtreme = GcmXtreme;

})(window);
