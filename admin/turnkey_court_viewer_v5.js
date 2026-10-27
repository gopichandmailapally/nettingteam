/**
 * Turnkey Sports Arena & Box Cricket Visualizer
 * High-Precision 3D Interactive WebGL + 2D Architectural CAD Plan / Elevation Suite
 * Features:
 * - Authentic 45mm Square HDPE Ball-Stop Netting with Mipmapped Anti-Aliased Mesh
 * - Seamless Rich Emerald Sports Turf Ground
 * - Regulation Center Cricket Pitch Strip (60 FT / 66 FT × 6.5 FT) with white creases
 * - Real 3D Cricket Equipment: Wooden Stumps & Bails, Cricket Bat, Red Leather Cricket Ball
 * - Perimeter Net Tension Border Ropes & Dynamic Gate Frame Openings
 */
(function(window) {
  'use strict';

  function getTurnkeyTierHeights(H_ft, config) {
    H_ft = parseFloat(H_ft) || 25.0;
    // 1. If config has tiers explicitly defined (from estimation / turnkey config)
    if (config && Array.isArray(config.tiers) && config.tiers.length > 0) {
      var cfHeights = [];
      config.tiers.forEach(function(t) {
        var h = parseFloat(t.height_ft || t.height || t);
        if (!isNaN(h) && h > 0 && h <= H_ft + 0.1) {
          cfHeights.push(parseFloat(Math.min(h, H_ft).toFixed(2)));
        }
      });
      if (cfHeights.length > 0) {
        var uniqueTiers = Array.from(new Set(cfHeights)).sort(function(a, b) { return a - b; });
        return uniqueTiers;
      }
    }

    // 2. Default standard engineering tier heights:
    // Base tie runner is always at 0.5 FT
    // Top eave tie pipe is ALWAYS exactly at H_ft (capping the columns, never floating!)
    // Intermediate runners spaced evenly (<= 6.5 FT c/c)
    if (H_ft <= 9.0) {
      return [0.5, H_ft];
    } else if (H_ft <= 13.5) {
      // 10 to 13.5 FT courts (e.g. 12 FT training nets, 10 FT cricket lanes: 2 tiers)
      return [0.5, H_ft];
    } else if (H_ft <= 17.0) {
      // 14 to 17 FT courts (3 tiers: base, mid, top)
      return [0.5, parseFloat((H_ft * 0.5).toFixed(1)), H_ft];
    } else if (H_ft <= 21.0) {
      // 18 to 21 FT courts (4 tiers)
      return [0.5, parseFloat((H_ft * 0.36).toFixed(1)), parseFloat((H_ft * 0.68).toFixed(1)), H_ft];
    } else if (H_ft <= 25.5) {
      // 24 to 25.5 FT regulation box cricket (5 tiers)
      return [0.5, 6.5, 12.5, 18.5, H_ft];
    } else if (H_ft <= 30.5) {
      // 30 FT indoor stadiums (6 tiers)
      return [0.5, 6.5, 12.5, 18.5, 24.5, H_ft];
    } else {
      var count = Math.max(3, Math.round(H_ft / 6.0));
      var spacing = (H_ft - 0.5) / (count - 1);
      var tiers = [0.5];
      for (var i = 1; i < count - 1; i++) {
        tiers.push(parseFloat((0.5 + i * spacing).toFixed(1)));
      }
      tiers.push(H_ft);
      return tiers;
    }
  }

  function checkIs160Builtup(config) {
    if (!config) return false;
    if (config.is160Builtup) return true;
    if (config.id && String(config.id).indexOf('078') !== -1) return true;
    if (config.doc_id && String(config.doc_id).indexOf('078') !== -1) return true;
    if (config.title && String(config.title).indexOf('078') !== -1) return true;
    if (config.zones && config.zones.badminton && parseFloat(config.zones.badminton.length) === 60) return true;
    if (parseFloat(config.badminton_length) === 60) return true;
    if (typeof window !== 'undefined') {
      if (window.location && window.location.href.indexOf('078') !== -1) return true;
      if (typeof document !== 'undefined' && document.body && document.body.innerText.indexOf('EST-202609-078') !== -1) return true;
    }
    return false;
  }

  function checkIsCricket(sport, config) {
    if (!sport) return true;
    sport = String(sport).toLowerCase();
    if (sport === 'multisport_4in1_100x60' || (config && (config.id === 'EST-202610-026' || config.doc_id === 'EST-202610-026'))) {
      return true;
    }
    if (
      sport === 'outdoor_multi_sports_120x60' ||
      sport.indexOf('badminton') !== -1 ||
      sport.indexOf('basketball') !== -1 ||
      sport.indexOf('tennis') !== -1 ||
      sport.indexOf('pickleball') !== -1
    ) {
      return false;
    }
    var turf = String((config && config.turf_type) || '').toLowerCase();
    var pitchL = parseFloat((config && config.pitch_length) || 0);
    if (
      sport.indexOf('cricket') !== -1 ||
      sport.indexOf('training_net') !== -1 ||
      sport === 'box_cricket' ||
      sport === 'hybrid_cricket_football' ||
      turf.indexOf('cricket') !== -1 ||
      turf === 'cricket_dual_turf' ||
      (pitchL > 0 && sport.indexOf('cricket') !== -1)
    ) {
      return true;
    }
    return false;
  }

  function getIntermediateSpans(span, target, minGap) {
    target = target || 15;
    minGap = minGap || 12;
    if (span <= minGap) {
      return { bays: 1, bayLength: span, intermediateCount: 0, positions: [] };
    }
    var bays = Math.max(1, Math.round(span / target));
    var bayLength = span / bays;
    var intermediateCount = bays - 1;
    var positions = [];
    for (var i = 1; i <= intermediateCount; i++) {
      positions.push(parseFloat((i * bayLength).toFixed(2)));
    }
    return {
      bays: bays,
      bayLength: parseFloat(bayLength.toFixed(2)),
      intermediateCount: intermediateCount,
      positions: positions
    };
  }

  function getResolvedGates(config, L_ft, W_ft, H_ft) {
    var rawList = config.gates;
    if (typeof rawList === 'string') {
      try { rawList = JSON.parse(rawList); } catch(e) { rawList = []; }
    }
    var isMultiZone = (config.is185x60 || (L_ft === 185 && W_ft === 60) || (config.zones && config.zones.length > 1));
    var isCricket = (config.sport_type === 'box_cricket' || (config.sport && config.sport.indexOf('cricket') !== -1) || config.is_cricket);

    // If not a multi-zone complex, filter out non-perimeter gates (like inter-zone partition gates from duplicated templates)
    if (!isMultiZone && Array.isArray(rawList)) {
      rawList = rawList.filter(function(g) {
        return g && (g.wall === 'left_length' || g.wall === 'right_length' || g.wall === 'front_width' || g.wall === 'back_width');
      });
    }

    var count = parseInt(config.gate_count || 1);
    if (!Array.isArray(rawList) || rawList.length === 0) {
      if (count > 0) {
        rawList = [{
          id: "gate_1",
          name: "Main Player Door",
          wall: "left_length",
          width_ft: 4.0,
          height_ft: 7.0,
          color: "#dc2626"
        }];
      } else {
        return [];
      }
    }

    // Limit to actual gate count
    if (count > 0 && rawList.length > count) {
      rawList = rawList.slice(0, count);
    }

    return rawList.map(function(g, idx) {
      var wall = g.wall || (idx % 2 === 0 ? "left_length" : "right_length");
      var width_ft = parseFloat(g.width_ft) || (g.type === "double" ? 8.0 : 4.0);
      var height_ft = parseFloat(g.height_ft) || 7.0;
      var isWidthWall = (wall === "front_width" || wall === "back_width");
      var span_ft = isWidthWall ? W_ft : L_ft;

      // Calculate vertical column positions along this specific wall
      var spansInfo = getIntermediateSpans(span_ft, 15, 12);
      var colPositions = [0].concat(spansInfo.positions).concat([span_ft]);

      // Calculate clear intermediate bays (between adjacent columns)
      var bayCenters = [];
      for (var b = 0; b < colPositions.length - 1; b++) {
        var bStart = colPositions[b];
        var bEnd = colPositions[b + 1];
        bayCenters.push({
          bayIdx: b,
          start: bStart,
          end: bEnd,
          center: (bStart + bEnd) / 2
        });
      }

      var pos_ft = parseFloat(g.position_ft);
      if (isNaN(pos_ft) || pos_ft <= 0) {
        if (typeof g.x_ft === 'number' && (wall === 'left_length' || wall === 'right_length')) {
          pos_ft = g.x_ft + L_ft / 2;
        } else if (typeof g.z_ft === 'number' && (wall === 'front_width' || wall === 'back_width')) {
          pos_ft = g.z_ft + W_ft / 2;
        }
      }

      // CRITICAL FOR CRICKET COURTS:
      // Player access door must NEVER be in the center (where pitch/batting/bowling action is!)
      // Default to the first clear corner bay near the batting end / entrance (Bay 0)
      if (isCricket && (isNaN(pos_ft) || pos_ft <= 0 || (pos_ft > span_ft * 0.35 && pos_ft < span_ft * 0.65))) {
        pos_ft = bayCenters[0] ? bayCenters[0].center : (span_ft * 0.1);
      } else if (isNaN(pos_ft) || pos_ft <= 0) {
        var chosenBay = bayCenters[Math.min(idx, bayCenters.length - 1)] || bayCenters[0];
        pos_ft = chosenBay ? chosenBay.center : span_ft * 0.1;
      } else {
        // If position is provided, verify it does NOT collide with any vertical pole
        for (var c = 0; c < colPositions.length; c++) {
          var cp = colPositions[c];
          if (Math.abs(pos_ft - cp) < (width_ft / 2 + 1.2)) {
            var matchedBay = null;
            for (var bi = 0; bi < bayCenters.length; bi++) {
              if (pos_ft >= bayCenters[bi].start && pos_ft <= bayCenters[bi].end) {
                matchedBay = bayCenters[bi];
                break;
              }
            }
            if (matchedBay) {
              pos_ft = matchedBay.center;
            } else {
              pos_ft = (pos_ft < cp) ? Math.max(width_ft / 2 + 1.5, cp - width_ft / 2 - 2.0) : Math.min(span_ft - width_ft / 2 - 1.5, cp + width_ft / 2 + 2.0);
            }
            break;
          }
        }
      }

      // Final safety clamp: keep gate inside span with safe margin from corner poles
      pos_ft = Math.max(width_ft / 2 + 1.5, Math.min(span_ft - width_ft / 2 - 1.5, pos_ft));

      var x_ft = 0, z_ft = 0;
      if (wall === "right_length") {
        x_ft = pos_ft - L_ft / 2;
        z_ft = W_ft / 2;
      } else if (wall === "left_length") {
        x_ft = pos_ft - L_ft / 2;
        z_ft = -W_ft / 2;
      } else if (wall === "front_width") {
        x_ft = -L_ft / 2;
        z_ft = pos_ft - W_ft / 2;
      } else if (wall === "back_width") {
        x_ft = L_ft / 2;
        z_ft = pos_ft - W_ft / 2;
      }

      return {
        id: g.id || "gate_" + (idx + 1),
        name: g.name || "Gate #" + (idx + 1),
        wall: wall,
        pos_ft: parseFloat(pos_ft.toFixed(2)),
        width_ft: width_ft,
        height_ft: height_ft,
        x_ft: parseFloat(x_ft.toFixed(2)),
        z_ft: parseFloat(z_ft.toFixed(2)),
        color: g.color || "#dc2626"
      };
    });
  }

  // Authentic HDPE Sports Green Netting Texture Generator (45mm Proportional Square Mesh with Knots & Anti-Aliased Mipmapping)
  
  function createNetTexture(repeatX, repeatY, sqSize, wireThick, borderThick, color, opacity) {
    if (typeof createAuthenticSportsNetTexture === 'function') {
      return createAuthenticSportsNetTexture(true);
    }
    var canvas = document.createElement('canvas');
    canvas.width = 64; canvas.height = 64;
    var ctx = canvas.getContext('2d');
    ctx.strokeStyle = color || '#15803d';
    ctx.lineWidth = 2.0;
    ctx.beginPath();
    ctx.moveTo(32, 0); ctx.lineTo(32, 64);
    ctx.moveTo(0, 32); ctx.lineTo(64, 32);
    ctx.stroke();
    var tex = new THREE.CanvasTexture(canvas);
    tex.wrapS = THREE.RepeatWrapping;
    tex.wrapT = THREE.RepeatWrapping;
    return tex;
  }

  function createAuthenticSportsNetTexture(isOverhead) {
    var canvas = document.createElement('canvas');
    var size = 64;
    canvas.width = size;
    canvas.height = size;
    var ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, size, size);

    var cells = 2; // 2 mesh squares per tile
    var step = size / cells;

    // Authentic HDPE Green sports twine (Solid sports green mesh without any white/grey alpha haze)
    ctx.strokeStyle = '#15803d';
    ctx.lineWidth = 2.0;

    for (var i = 0; i <= size; i += step) {
      // Vertical twines
      ctx.beginPath();
      ctx.moveTo(i, 0);
      ctx.lineTo(i, size);
      ctx.stroke();

      // Horizontal twines
      ctx.beginPath();
      ctx.moveTo(0, i);
      ctx.lineTo(size, i);
      ctx.stroke();
    }

    // Knotted mesh junctions (Dark pine green knots)
    ctx.fillStyle = '#14532d';
    for (var x = 0; x <= size; x += step) {
      for (var y = 0; y <= size; y += step) {
        ctx.beginPath();
        ctx.arc(x, y, 2.2, 0, Math.PI * 2);
        ctx.fill();
      }
    }

    var tex = new THREE.CanvasTexture(canvas);
    tex.wrapS = THREE.RepeatWrapping;
    tex.wrapT = THREE.RepeatWrapping;
    tex.generateMipmaps = true;
    tex.minFilter = THREE.LinearMipmapLinearFilter;
    tex.magFilter = THREE.LinearFilter;
    return tex;
  }

    // =========================================================================
  // Authentic Cryonics 200W Premium 5050 LED Floodlight Texture & 3D Model
  // Features:
  // - Textured silver optical reflector recess
  // - 5050 SMD multi-lens optical clusters with golden phosphor diodes
  // - Cast-aluminium dark anthracite bezel & heat-sink finned rear casing
  // - Heavy-duty U-shaped trunnion swivel mounting bracket with side locking bolts
  // =========================================================================
  function createCryonics5050LensTexture() {
    var canvas = document.createElement("canvas");
    var w = 256;
    var h = 160;
    canvas.width = w;
    canvas.height = h;
    var ctx = canvas.getContext("2d");

    // 1. Outer stepped textured aluminum reflector frame
    var grad = ctx.createLinearGradient(0, 0, w, h);
    grad.addColorStop(0, "#e2e8f0");
    grad.addColorStop(0.5, "#cbd5e1");
    grad.addColorStop(1, "#94a3b8");
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, w, h);

    // Textured reflector micro-grooves
    ctx.strokeStyle = "rgba(255,255,255,0.4)";
    ctx.lineWidth = 1;
    for (var y = 0; y < h; y += 4) {
      ctx.beginPath();
      ctx.moveTo(0, y);
      ctx.lineTo(w, y);
      ctx.stroke();
    }

    // 2. Black interior PCB panel
    var margin = 14;
    ctx.fillStyle = "#090d16";
    ctx.fillRect(margin, margin, w - margin * 2, h - margin * 2);

    // 3. Grid of 5050 SMD Optical Lens Clusters
    var cols = 6;
    var rows = 3;
    var blockW = (w - margin * 2 - 16) / cols;
    var blockH = (h - margin * 2 - 12) / rows;

    for (var r = 0; r < rows; r++) {
      for (var c = 0; c < cols; c++) {
        var bx = margin + 8 + c * blockW;
        var by = margin + 6 + r * blockH;

        // Clear optical lens housing box
        ctx.fillStyle = "rgba(241, 245, 249, 0.15)";
        ctx.strokeStyle = "rgba(255, 255, 255, 0.4)";
        ctx.lineWidth = 1;
        ctx.fillRect(bx + 1, by + 1, blockW - 3, blockH - 3);
        ctx.strokeRect(bx + 1, by + 1, blockW - 3, blockH - 3);

        // 4 individual 5050 LED lenses per cluster
        var subW = (blockW - 4) / 2;
        var subH = (blockH - 4) / 2;
        for (var sr = 0; sr < 2; sr++) {
          for (var sc = 0; sc < 2; sc++) {
            var lx = bx + 2 + sc * subW + subW / 2;
            var ly = by + 2 + sr * subH + subH / 2;

            // Optical focal convex lens bubble
            ctx.beginPath();
            ctx.arc(lx, ly, Math.min(subW, subH) * 0.42, 0, Math.PI * 2);
            ctx.fillStyle = "rgba(255, 255, 255, 0.6)";
            ctx.fill();

            // Golden-Amber high-lux LED phosphor core
            ctx.beginPath();
            ctx.arc(lx, ly, Math.min(subW, subH) * 0.22, 0, Math.PI * 2);
            ctx.fillStyle = "#f59e0b";
            ctx.fill();

            // Bright center glow emitter point
            ctx.beginPath();
            ctx.arc(lx, ly, Math.min(subW, subH) * 0.10, 0, Math.PI * 2);
            ctx.fillStyle = "#fffbeb";
            ctx.fill();
          }
        }
      }
    }

    // Top branding: Cryonics 200W
    ctx.fillStyle = "rgba(255, 255, 255, 0.75)";
    ctx.font = "bold 8px sans-serif";
    ctx.textAlign = "center";
    ctx.fillText("Cryonics 200W - 5050 LED", w / 2, margin + 7);

    var tex = new THREE.CanvasTexture(canvas);
    tex.generateMipmaps = true;
    tex.minFilter = THREE.LinearMipmapLinearFilter;
    return tex;
  }

  function init3DCourt(containerId, config) {
    var container = document.getElementById(containerId);
    if (!container || typeof THREE === 'undefined') return;
    var megaRoofGroup = null;

    container.innerHTML = '';
    var width = container.clientWidth || 440;
    var height = container.clientHeight || 247;

    var L_ft = parseFloat(config.length) || 90;
    var W_ft = parseFloat(config.width) || 60;
    var H_ft = parseFloat(config.height) || 25;
    var sport = config.sport_type || 'box_cricket';
    var roofType = config.roof_type || 'light_cable_top';
    var hasRoof = (roofType !== 'open_top');
    var isCircular = (sport === 'cricket_circular');
    var isOval = (sport === 'cricket_oval');
    var isCricket = checkIsCricket(sport, config);
    var isTrainingNet = (sport === 'training_net' || sport.indexOf('cricket_training') !== -1 || sport.indexOf('training_net') !== -1 || (config.is_training_net === true) || (config.shape === 'training_net') || (config.is_front_open === true) || (config.open_front === true) || (config.open_sides && config.open_sides.front === true) || roofType === 'training_net_open_front');
    var is2Lane = (sport === 'training_net_2lane');
    var is3Lane = (sport === 'training_net_3lane');

    var openSides = config.open_sides || {};
    if (typeof openSides === 'string') {
      try { openSides = JSON.parse(openSides); } catch(e) { openSides = {}; }
    }
    var isFrontOpen = (openSides.front === true || config.open_front === true || (isTrainingNet && openSides.front !== false));
    var isBackOpen  = (openSides.back === true || config.open_back === true);
    var isLeftOpen  = (openSides.left === true || config.open_left === true);
    var isRightOpen = (openSides.right === true || config.open_right === true);
    var isTopOpen   = (openSides.top === true || config.open_top === true || roofType === 'open_top');

    var layers = config.layers || {};
    if (typeof layers === 'string') {
      try { layers = JSON.parse(layers); } catch(e) { layers = {}; }
    }
    var showStructure = (layers.structure !== false);
    var showNetting   = (layers.netting !== false);
    var showCivil     = (layers.civil !== false && config.civil_bed_type !== 'client_scope' && config.civil_bed_type !== 'none');
    var showTurf      = (layers.turf !== false && config.turf_type !== 'no_turf');
    var showPitch     = (layers.pitch !== false && (config.pitch_length > 0 || isCricket));
    if (config && config.equipment && config.equipment.cricket_pitch !== undefined) {
      showPitch = !!config.equipment.cricket_pitch;
    }
    var showLighting  = (layers.lighting !== false);
    var showGates     = (layers.gates !== false && parseInt(config.gate_count || 1) > 0);

    var L = L_ft * 0.1;
    var W = W_ft * 0.1;
    var H = H_ft * 0.1;
    var halfL = L / 2;
    var halfW = W / 2;

    var is185x60 = (sport === 'multisport_arena_complex_185x60' || (L_ft === 185 && W_ft === 60));
    var is105x170 = (sport === 'multisport_complex_105x170' || (L_ft === 170 && W_ft === 105));
    var is160Builtup = Boolean(is185x60 && checkIs160Builtup(config));
    var arenaStartRelX = -halfL;       // -9.25 units (0 FT)
    var z1EndX         = arenaStartRelX + 10.0; // +0.75 units (100 FT, Cricket partition)
    var sportsEndRelX  = arenaStartRelX + (is160Builtup ? 16.0 : 14.5); // 160 FT (+6.75 units) for 078, 145 FT (+5.25 units) for 074
    var arenaEndRelX   = halfL;        // +9.25 units (185 FT, outer plot boundary)
    var arenaMidX      = 0;
    var structStartX = is105x170 ? (-halfL + (18 * 0.1)) : (is185x60 ? arenaStartRelX : -halfL); // -6.7 units (X = 18 FT)
    var structEndX   = is105x170 ? halfL : (is185x60 ? sportsEndRelX : halfL); // 160 FT for 078, 145 FT for 074
    var structLen3D  = structEndX - structStartX;
    var indoorStartRelX = -halfL + (68 * 0.1); // -1.7 units (X = 68 FT)
    var indoorLen3D  = structEndX - indoorStartRelX;
    var arenaLen3D = is185x60 ? L : L;

    var scene = new THREE.Scene();
    scene.background = new THREE.Color(0xf8fafc);

    // Intelligent Proportional Camera Positioning & Aspect Ratio
    var courtDiag = Math.sqrt(L * L + W * W + H * H);
    var maxDim = Math.max(L, W);
    var targetY = (!showStructure && !showNetting) ? 0 : H * 0.40;
    var targetX = is185x60 ? arenaMidX : 0;

    // Camera Distance & Viewing Angle tailored to maintain true proportions for all aspect ratios
    var camDist = Math.max(courtDiag * 1.15, maxDim * 1.10, H * 2.2);
    var camElev = Math.max(H * 1.45, camDist * 0.38);
    // True 3/4 axonometric perspective: balanced X and Z angles so length & width are in true proportion
    var camX = -Math.max(L * 0.72, camDist * 0.62);
    var camZ = Math.max(W * 1.45, camDist * 0.52);

    var camera = new THREE.PerspectiveCamera(32, width / height, 0.5, 350);
    if (!showStructure && !showNetting) {
      camera.position.set(L * 0.85, Math.max(L, W) * 0.75, W * 1.05);
    } else if (is185x60) {
      camera.position.set(L * 0.85, H * 1.35, W * 1.45);
    } else if (is105x170) {
      camera.position.set(L * 0.90, H * 1.40, W * 1.40);
    } else {
      camera.position.set(camX, camElev, camZ);
    }

    var renderer = new THREE.WebGLRenderer({ antialias: true, preserveDrawingBuffer: true, alpha: true, logarithmicDepthBuffer: true });
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    container.appendChild(renderer.domElement);

    var maxAniso = renderer.capabilities.getMaxAnisotropy ? renderer.capabilities.getMaxAnisotropy() : 4;

    var controls = null;
    if (typeof THREE.OrbitControls !== 'undefined') {
      controls = new THREE.OrbitControls(camera, renderer.domElement);
      controls.enableDamping = true;
      controls.dampingFactor = 0.08;
      controls.target.set(targetX, targetY, 0);
      controls.maxPolarAngle = Math.PI / 2 + 0.05;
      controls.minDistance = 2;
      controls.maxDistance = 200;
    }

    // Professional Stadium Lighting
    var ambLight = new THREE.AmbientLight(0xffffff, 0.9);
    scene.add(ambLight);

    var dirLight = new THREE.DirectionalLight(0xfffbeb, 0.6);
    dirLight.position.set(L * 0.8, H * 2.5, W * 0.9);
    scene.add(dirLight);

    var fillLight = new THREE.DirectionalLight(0xf0fdf4, 0.4);
    fillLight.position.set(-L * 0.8, H * 1.5, -W * 0.9);
    scene.add(fillLight);

    // Standard Materials
    var steelMat = new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.35, metalness: 0.6 });
    var civilMat = new THREE.MeshStandardMaterial({ color: 0x94a3b8, roughness: 0.9, polygonOffset: true, polygonOffsetFactor: 2.0, polygonOffsetUnits: 2.0 });
    var whiteMat = new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.3 });

    // Clean, Seamless Rich Sports Turf Ground
    var turfColorHex = 0x15803d;
    if (sport === 'tennis') turfColorHex = 0x2563eb;
    else if (sport === 'badminton' || sport === 'badminton_multi') turfColorHex = 0x059669;

    var turfMat = new THREE.MeshBasicMaterial({
      color: turfColorHex,
      polygonOffset: true,
      polygonOffsetFactor: -1.0,
      polygonOffsetUnits: -1.0
    });

    // Base Civil Bed & Turf Ground (Definitively separated in Y to eliminate all Z-fighting)
    if (showCivil) {
      var bedL = is185x60 ? arenaLen3D : L;
      var bedPosX = is185x60 ? arenaMidX : 0;
      var bedGeo = new THREE.BoxGeometry(bedL + 0.4, 0.08, W + 0.4);
      var bed = new THREE.Mesh(bedGeo, civilMat);
      // Height is 0.08: at y = -0.05, top of bed is at y = -0.010 (12mm strictly below turf ground)
      bed.position.set(bedPosX, -0.05, 0);
      scene.add(bed);
    }

    if (showTurf) {
      var turfL = is185x60 ? arenaLen3D : L;
      var turfPosX = is185x60 ? arenaMidX : 0;
      var groundGeo = new THREE.PlaneGeometry(turfL, W);
      var ground = new THREE.Mesh(groundGeo, turfMat);
      ground.rotation.x = -Math.PI / 2;
      ground.position.set(turfPosX, 0.002, 0);
      scene.add(ground);

      // Boundary Line
      var bLines = new THREE.LineSegments(
        new THREE.EdgesGeometry(groundGeo),
        new THREE.LineBasicMaterial({ color: 0xffffff, linewidth: 2 })
      );
      bLines.rotation.x = -Math.PI / 2;
      bLines.position.set(turfPosX, 0.003, 0);
      scene.add(bLines);

      // Custom Outdoor Multi-Sports Court: Dual Game Markings (Basketball + Badminton)
      if (sport === 'outdoor_multi_sports_120x60') {
        var cvs = document.createElement('canvas');
        cvs.width = 2048;
        cvs.height = 1024;
        var ctx = cvs.getContext('2d');

        var cw = cvs.width;
        var ch = cvs.height;
        var scX = cw / 120.0;
        var scY = ch / 60.0;

        // 1. Perimeter Run-off Apron (Forest Green)
        ctx.fillStyle = '#15803d';
        ctx.fillRect(0, 0, cw, ch);

        // 2. Inner Basketball Playing Court Boundary (104 FT x 50 FT)
        var courtL_ft = 104;
        var courtW_ft = 50;
        var cX = (120 - courtL_ft) / 2 * scX;
        var cY = (60 - courtW_ft) / 2 * scY;
        var cW = courtL_ft * scX;
        var cH = courtW_ft * scY;
        var midX = cw / 2;
        var midY = ch / 2;

        // Fill Main Court: Royal Blue
        ctx.fillStyle = '#1d4ed8';
        ctx.fillRect(cX, cY, cW, cH);

        // Center division line & Center Jump Circle (Terracotta Red)
        var ccRadius = 6.0 * scX; // 6 FT radius (12 FT diameter)
        ctx.fillStyle = '#b91c1c';
        ctx.beginPath();
        ctx.arc(midX, midY, ccRadius, 0, Math.PI * 2);
        ctx.fill();
        ctx.lineWidth = 4.5;
        ctx.strokeStyle = '#ffffff';
        ctx.stroke();

        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 4;
        ctx.beginPath();
        ctx.moveTo(midX, cY);
        ctx.lineTo(midX, cY + cH);
        ctx.stroke();

        // 3. Basketball Keys (Terracotta Red, 16 FT wide x 19 FT long)
        var keyL = 19 * scX;
        var keyW = 16 * scY;
        var keyY = midY - keyW / 2;

        // Left Key
        ctx.fillStyle = '#b91c1c';
        ctx.fillRect(cX, keyY, keyL, keyW);
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 4;
        ctx.strokeRect(cX, keyY, keyL, keyW);

        // Left Free-throw Circle (Radius 6 FT)
        var ftCircleX = cX + keyL;
        ctx.beginPath();
        ctx.arc(ftCircleX, midY, ccRadius, -Math.PI / 2, Math.PI / 2, false);
        ctx.stroke();
        ctx.save();
        ctx.setLineDash([10, 8]);
        ctx.beginPath();
        ctx.arc(ftCircleX, midY, ccRadius, Math.PI / 2, -Math.PI / 2, false);
        ctx.stroke();
        ctx.restore();

        // Left 3-Point Arc (22.15 FT radius)
        var leftBasketX = cX + (4 * scX);
        var arcR = 22.15 * scX;
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 4;
        ctx.beginPath();
        ctx.arc(leftBasketX, midY, arcR, -0.92, 0.92, false);
        ctx.stroke();
        var straightYTop = midY - arcR * Math.sin(0.92);
        var straightYBot = midY + arcR * Math.sin(0.92);
        ctx.beginPath();
        ctx.moveTo(cX, straightYTop);
        ctx.lineTo(leftBasketX + arcR * Math.cos(0.92), straightYTop);
        ctx.moveTo(cX, straightYBot);
        ctx.lineTo(leftBasketX + arcR * Math.cos(0.92), straightYBot);
        ctx.stroke();

        // Left Key Hash Marks
        for (var km = 1; km <= 4; km++) {
          var kmX = cX + (km * 3.5 * scX);
          ctx.beginPath();
          ctx.moveTo(kmX, keyY); ctx.lineTo(kmX, keyY - 12);
          ctx.moveTo(kmX, keyY + keyW); ctx.lineTo(kmX, keyY + keyW + 12);
          ctx.stroke();
        }

        // Right Key
        var rightKeyX = cX + cW - keyL;
        ctx.fillStyle = '#b91c1c';
        ctx.fillRect(rightKeyX, keyY, keyL, keyW);
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 4;
        ctx.strokeRect(rightKeyX, keyY, keyL, keyW);

        // Right Free-throw Circle
        var ftCircleX_R = rightKeyX;
        ctx.beginPath();
        ctx.arc(ftCircleX_R, midY, ccRadius, Math.PI / 2, -Math.PI / 2, false);
        ctx.stroke();
        ctx.save();
        ctx.setLineDash([10, 8]);
        ctx.beginPath();
        ctx.arc(ftCircleX_R, midY, ccRadius, -Math.PI / 2, Math.PI / 2, false);
        ctx.stroke();
        ctx.restore();

        // Right 3-Point Arc
        var rightBasketX = cX + cW - (4 * scX);
        ctx.beginPath();
        ctx.arc(rightBasketX, midY, arcR, Math.PI - 0.92, Math.PI + 0.92, false);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(cX + cW, straightYTop);
        ctx.lineTo(rightBasketX - arcR * Math.cos(0.92), straightYTop);
        ctx.moveTo(cX + cW, straightYBot);
        ctx.lineTo(rightBasketX - arcR * Math.cos(0.92), straightYBot);
        ctx.stroke();

        // Right Key Hash Marks
        for (var km = 1; km <= 4; km++) {
          var kmX = rightKeyX + keyL - (km * 3.5 * scX);
          ctx.beginPath();
          ctx.moveTo(kmX, keyY); ctx.lineTo(kmX, keyY - 12);
          ctx.moveTo(kmX, keyY + keyW); ctx.lineTo(kmX, keyY + keyW + 12);
          ctx.stroke();
        }

        // Basketball Boundary White Stroke
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 5.5;
        ctx.strokeRect(cX, cY, cW, cH);

        // 4. Regulation Badminton Court Markings (Contrasting Yellow Lines)
        // BWF Dimensions: 44 FT Length (22 FT each side of net), 20 FT Doubles Width, 17 FT Singles Width
        var badX1 = midX - (22 * scX);
        var badX2 = midX + (22 * scX);
        var badY1 = midY - (10 * scY);
        var badY2 = midY + (10 * scY);
        var badSingleY1 = midY - (8.5 * scY);
        var badSingleY2 = midY + (8.5 * scY);

        ctx.strokeStyle = '#fef08a';
        ctx.lineWidth = 3.5;

        // Doubles Boundary (44 FT x 20 FT)
        ctx.strokeRect(badX1, badY1, badX2 - badX1, badY2 - badY1);

        // Singles Sidelines (17 FT Width)
        ctx.beginPath();
        ctx.moveTo(badX1, badSingleY1); ctx.lineTo(badX2, badSingleY1);
        ctx.moveTo(badX1, badSingleY2); ctx.lineTo(badX2, badSingleY2);
        ctx.stroke();

        // Short Service Lines (6.5 FT from center net on each side)
        var sslLeft = midX - (6.5 * scX);
        var sslRight = midX + (6.5 * scX);
        ctx.beginPath();
        ctx.moveTo(sslLeft, badY1); ctx.lineTo(sslLeft, badY2);
        ctx.moveTo(sslRight, badY1); ctx.lineTo(sslRight, badY2);
        ctx.stroke();

        // Doubles Long Service Lines (2.5 FT inside baseline)
        var lslLeft = badX1 + (2.5 * scX);
        var lslRight = badX2 - (2.5 * scX);
        ctx.beginPath();
        ctx.moveTo(lslLeft, badY1); ctx.lineTo(lslLeft, badY2);
        ctx.moveTo(lslRight, badY1); ctx.lineTo(lslRight, badY2);
        ctx.stroke();

        // Center Service Line (from short service lines to back baseline)
        ctx.beginPath();
        ctx.moveTo(badX1, midY); ctx.lineTo(sslLeft, midY);
        ctx.moveTo(sslRight, midY); ctx.lineTo(badX2, midY);
        ctx.stroke();

        // Bind Canvas to Three.js Texture
        var courtTexture = new THREE.CanvasTexture(cvs);
        courtTexture.anisotropy = 16;
        var courtMat = new THREE.MeshStandardMaterial({
          map: courtTexture,
          roughness: 0.35,
          metalness: 0.05
        });
        var courtGround = new THREE.Mesh(new THREE.PlaneGeometry(L, W), courtMat);
        courtGround.rotation.x = -Math.PI / 2;
        courtGround.position.set(0, 0.003, 0);
        scene.add(courtGround);
      }

      // =========================================================================
      // 16,000 SFT MEGA SPORTS ARENA (105 FT × 170 FT): DEDICATED 4K CANVAS COURT MARKINGS
      // Full regulation markings for 4 BWF Badminton Courts + 2 Pickleball Courts +
      // 66×10 Cricket Match Pitch + 15' Walkway + Front Parking & Dual Gates
      // =========================================================================
      // =========================================================================
      // 185 FT × 60 FT MULTI-SPORT ARENA: FULL 185 FT TURNKEY COMPLEX GROUND CANVAS
      // Zone 1: Cricket (100 FT) • Zone 2: Badminton (45 FT) • Zone 3: Amenities Suite (40 FT)
      // =========================================================================
      if (is185x60) {
        var cvs = document.createElement('canvas');
        cvs.width = 3700;  // 20 px per foot (185 FT Full Complex)
        cvs.height = 1200; // 20 px per foot (60 FT Width)
        var ctx = cvs.getContext('2d');
        var scX = cvs.width / 185.0; // 20 px/ft
        var scY = cvs.height / 60.0; // 20 px/ft
        var cw = cvs.width;
        var ch = cvs.height;

        // ZONE 1: CRICKET GROUND (0 to 100 FT = 2000 px)
        var z1W = 100 * scX;
        ctx.fillStyle = '#15803d';
        ctx.fillRect(0, 0, z1W, ch);

        // Alternating mowing strips
        for (var tx = 0; tx < z1W; tx += 80) {
          if (Math.floor(tx / 80) % 2 === 1) {
            ctx.fillStyle = '#16a34a';
            ctx.fillRect(tx, 0, Math.min(80, z1W - tx), ch);
          }
        }

        // White perimeter boundary
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 6;
        ctx.setLineDash([20, 15]);
        ctx.strokeRect(30, 30, z1W - 60, ch - 60);
        ctx.setLineDash([]);

        // 83 FT × 6.5 FT Curled Match Pitch Turf
        var pL = 83 * scX;
        var pW = 6.5 * scY;
        var pX = (z1W - pL) / 2;
        var pY = (ch - pW) / 2;

        ctx.fillStyle = '#deb887';
        ctx.fillRect(pX, pY, pL, pW);
        ctx.strokeStyle = '#854d0e';
        ctx.lineWidth = 4;
        ctx.strokeRect(pX, pY, pL, pW);

        // White creases & stumps
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 5;
        ctx.beginPath();
        ctx.moveTo(pX + 35, pY); ctx.lineTo(pX + 35, pY + pW);
        ctx.moveTo(pX + pL - 35, pY); ctx.lineTo(pX + pL - 35, pY + pW);
        ctx.stroke();

        ctx.fillStyle = '#713f12';
        ctx.font = 'bold 28px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('83 FT × 6.5 FT CURLED MATCH PITCH TURF', z1W / 2, ch / 2 + 10);

        if (is160Builtup) {
          // ZONE 2: 60 FT × 60 FT BADMINTON & AMENITIES COMPLEX (100 to 160 FT = 1200 px)
          var z2X = z1W;
          var z2W = 60 * scX; // 1200 px
          var midY = ch / 2;  // 600 px

          // Base Blue Flooring
          ctx.fillStyle = '#0284c7';
          ctx.fillRect(z2X, 0, z2W, ch);

          // ── NORTH BAY (Y: 0 to 600 px, 30 FT wide) ──
          // 1. [LT] Ladies Toilet at X: 100 to 115 FT (300 px), Y: 0 to 240 px (12 FT) at OUTER NORTH WALL
          ctx.fillStyle = '#fdf2f8';
          ctx.fillRect(z2X, 0, 300, 240);
          ctx.strokeStyle = '#be185d';
          ctx.lineWidth = 8;
          ctx.strokeRect(z2X, 0, 300, 240);

          // Exterior ventilation indicator along North wall
          ctx.fillStyle = '#be185d';
          ctx.fillRect(z2X + 60, 0, 180, 10);

          ctx.fillStyle = '#fbcfe8';
          ctx.fillRect(z2X + 40, 30, 60, 90);
          ctx.fillStyle = '#ffffff';
          ctx.strokeStyle = '#db2777';
          ctx.lineWidth = 4;
          ctx.beginPath(); ctx.arc(z2X + 180, 70, 20, 0, Math.PI * 2); ctx.fill(); ctx.stroke();
          ctx.beginPath(); ctx.arc(z2X + 180, 150, 20, 0, Math.PI * 2); ctx.fill(); ctx.stroke();

          ctx.fillStyle = '#9d174d';
          ctx.font = 'bold 22px Inter, sans-serif';
          ctx.textAlign = 'center';
          ctx.fillText('[LT] LADIES TOILET', z2X + 150, 120);
          ctx.font = 'bold 16px Inter, sans-serif';
          ctx.fillStyle = '#db2777';
          ctx.fillText('15 FT × 12 FT (180 SFT)', z2X + 150, 146);
          ctx.font = 'bold 13px Inter, sans-serif';
          ctx.fillStyle = '#be185d';
          ctx.fillText('💨 OUTER WALL VENTILATION', z2X + 150, 170);

          // 2. [O] Office Room at X: 100 to 115 FT (300 px), Y: 240 to 600 px (18 FT = 360 px) on INNER SIDE
          ctx.fillStyle = '#f8fafc';
          ctx.fillRect(z2X, 240, 300, 360);
          ctx.strokeStyle = '#1e293b';
          ctx.lineWidth = 8;
          ctx.strokeRect(z2X, 240, 300, 360);

          ctx.fillStyle = '#94a3b8';
          ctx.fillRect(z2X + 60, 300, 160, 70);
          ctx.fillStyle = '#3b82f6';
          ctx.beginPath(); ctx.arc(z2X + 140, 280, 20, 0, Math.PI * 2); ctx.fill();
          ctx.fillStyle = '#64748b';
          ctx.beginPath(); ctx.arc(z2X + 100, 400, 16, 0, Math.PI * 2); ctx.fill();
          ctx.beginPath(); ctx.arc(z2X + 180, 400, 16, 0, Math.PI * 2); ctx.fill();

          ctx.fillStyle = '#0f172a';
          ctx.font = 'bold 24px Inter, sans-serif';
          ctx.fillText('[O] OFFICE ROOM', z2X + 150, 500);
          ctx.font = 'bold 18px Inter, sans-serif';
          ctx.fillStyle = '#475569';
          ctx.fillText('15 FT × 18 FT (270 SFT)', z2X + 150, 535);

          // 3. Badminton Court 1 (①) at X: 115 to 160 FT (900 px), Y: 0 to 600 px (30 FT)
          var c1X = z2X + 300 + (900 - 44 * scX) / 2;
          var c1Y = (600 - 20 * scY) / 2;
          var cL = 44 * scX;
          var cW = 20 * scY;

          ctx.fillStyle = '#047857';
          ctx.fillRect(c1X, c1Y, cL, cW);
          ctx.strokeStyle = '#ffffff';
          ctx.lineWidth = 6;
          ctx.strokeRect(c1X + 4, c1Y + 4, cL - 8, cW - 8);

          // Red Center Net
          ctx.strokeStyle = '#dc2626';
          ctx.lineWidth = 8;
          ctx.beginPath();
          ctx.moveTo(c1X + cL / 2, c1Y);
          ctx.lineTo(c1X + cL / 2, c1Y + cW);
          ctx.stroke();

          ctx.fillStyle = '#ffffff';
          ctx.font = 'bold 24px Inter, sans-serif';
          ctx.textAlign = 'center';
          ctx.fillText('🏸 BADMINTON COURT ① (44×20 FT BWF)', c1X + cL / 2, c1Y + cW / 2 + 8);

          // ── SOUTH BAY (Y: 600 to 1200 px, 30 FT wide) ──
          // 4. Badminton Court 2 (②) at X: 100 to 145 FT (900 px), Y: 600 to 1200 px
          var c2X = z2X + (900 - 44 * scX) / 2;
          var c2Y = 600 + (600 - 20 * scY) / 2;

          ctx.fillStyle = '#047857';
          ctx.fillRect(c2X, c2Y, cL, cW);
          ctx.strokeStyle = '#ffffff';
          ctx.lineWidth = 6;
          ctx.strokeRect(c2X + 4, c2Y + 4, cL - 8, cW - 8);

          // Red Center Net
          ctx.strokeStyle = '#dc2626';
          ctx.lineWidth = 8;
          ctx.beginPath();
          ctx.moveTo(c2X + cL / 2, c2Y);
          ctx.lineTo(c2X + cL / 2, c2Y + cW);
          ctx.stroke();

          ctx.fillStyle = '#ffffff';
          ctx.font = 'bold 24px Inter, sans-serif';
          ctx.fillText('🏸 BADMINTON COURT ② (44×20 FT BWF)', c2X + cL / 2, c2Y + cW / 2 + 8);

          // 5. [L] Reception Lobby at X: 145 to 160 FT (300 px), Y: 600 to 960 px (18 FT = 360 px) at INNER / CENTER SIDE
          var gtX = z2X + 900;
          ctx.fillStyle = '#fefce8';
          ctx.fillRect(gtX, 600, 300, 360);
          ctx.strokeStyle = '#ca8a04';
          ctx.lineWidth = 8;
          ctx.strokeRect(gtX, 600, 300, 360);

          ctx.fillStyle = '#fde047';
          ctx.fillRect(gtX + 40, 630, 120, 50);
          ctx.fillStyle = '#cbd5e1';
          ctx.fillRect(gtX + 40, 720, 160, 45);

          ctx.fillStyle = '#854d0e';
          ctx.font = 'bold 24px Inter, sans-serif';
          ctx.fillText('[L] RECEPTION LOBBY', gtX + 150, 840);
          ctx.font = 'bold 18px Inter, sans-serif';
          ctx.fillStyle = '#b45309';
          ctx.fillText('15 FT × 18 FT (270 SFT)', gtX + 150, 875);
          ctx.font = 'bold 14px Inter, sans-serif';
          ctx.fillStyle = '#a16207';
          ctx.fillText('★ DIRECT ENTRY RECEPTION ★', gtX + 150, 902);

          // 6. [GT] Gents Toilet at X: 145 to 160 FT (300 px), Y: 960 to 1200 px (12 FT = 240 px) at OUTER SOUTH WALL
          ctx.fillStyle = '#f0f9ff';
          ctx.fillRect(gtX, 960, 300, 240);
          ctx.strokeStyle = '#0284c7';
          ctx.lineWidth = 8;
          ctx.strokeRect(gtX, 960, 300, 240);

          // Exterior ventilation indicator along South wall
          ctx.fillStyle = '#0284c7';
          ctx.fillRect(gtX + 60, 1190, 180, 10);

          ctx.fillStyle = '#38bdf8';
          ctx.fillRect(gtX + 30, 980, 25, 40);
          ctx.fillRect(gtX + 70, 980, 25, 40);
          ctx.fillStyle = '#ffffff';
          ctx.strokeStyle = '#0284c7';
          ctx.lineWidth = 4;
          ctx.beginPath(); ctx.arc(gtX + 180, 1070, 20, 0, Math.PI * 2); ctx.fill(); ctx.stroke();

          ctx.fillStyle = '#0369a1';
          ctx.font = 'bold 22px Inter, sans-serif';
          ctx.fillText('[GT] GENTS TOILET', gtX + 150, 1070);
          ctx.font = 'bold 16px Inter, sans-serif';
          ctx.fillStyle = '#0284c7';
          ctx.fillText('15 FT × 12 FT (180 SFT)', gtX + 150, 1098);
          ctx.font = 'bold 13px Inter, sans-serif';
          ctx.fillStyle = '#0369a1';
          ctx.fillText('💨 OUTER WALL VENTILATION', gtX + 150, 1122);

          // West partition line at 100 FT
          ctx.strokeStyle = '#064e3b';
          ctx.lineWidth = 14;
          ctx.beginPath(); ctx.moveTo(z2X, 0); ctx.lineTo(z2X, ch); ctx.stroke();

          // East partition wall at 160 FT
          var z3X = z2X + z2W; // 160 FT
          var z3W = 25 * scX;  // 500 px
          ctx.strokeStyle = '#0f172a';
          ctx.lineWidth = 14;
          ctx.beginPath(); ctx.moveTo(z3X, 0); ctx.lineTo(z3X, ch); ctx.stroke();

          // ZONE 3: 25 FT OPEN SPACE / PARKING (160 to 185 FT)
          ctx.fillStyle = '#e2e8f0';
          ctx.fillRect(z3X, 0, z3W, ch);
          ctx.strokeStyle = '#cbd5e1';
          ctx.lineWidth = 2;
          for (var px_i = z3X; px_i < cw; px_i += 30) {
            ctx.beginPath(); ctx.moveTo(px_i, 0); ctx.lineTo(px_i, ch); ctx.stroke();
          }
          for (var py_i = 0; py_i < ch; py_i += 30) {
            ctx.beginPath(); ctx.moveTo(z3X, py_i); ctx.lineTo(cw, py_i); ctx.stroke();
          }
          ctx.fillStyle = '#475569';
          ctx.font = 'bold 22px Inter, sans-serif';
          ctx.textAlign = 'center';
          ctx.fillText('ZONE 3: 25 FT × 60 FT FRONT PARKING & OPEN SPACE (1,500 SFT)', z3X + z3W / 2, ch / 2 - 15);
          ctx.font = 'bold 18px Inter, sans-serif';
          ctx.fillStyle = '#64748b';
          ctx.fillText('ZERO METAL STRUCTURE • CLIENT SCOPE', z3X + z3W / 2, ch / 2 + 20);

        } else {
          var z2X = z1W;
          var z2W = 45 * scX;
          ctx.fillStyle = '#0284c7';
          ctx.fillRect(z2X, 0, z2W, ch);

          // 2 Side-by-Side Courts
          var cBayH = ch / 2; // 600 px each
          for (var bi = 0; bi < 2; bi++) {
            var by = bi * cBayH;
            var badL = 44 * scX;
            var badW = 20 * scY;
            var badX = z2X + (z2W - badL) / 2;
            var badY = by + (cBayH - badW) / 2;

            ctx.fillStyle = '#047857';
            ctx.fillRect(badX, badY, badL, badW);
            ctx.strokeStyle = '#ffffff';
            ctx.lineWidth = 5;
            ctx.strokeRect(badX + 4, badY + 4, badL - 8, badW - 8);

            // Center Red Net Line
            ctx.strokeStyle = '#dc2626';
            ctx.lineWidth = 8;
            ctx.beginPath();
            ctx.moveTo(badX + badL / 2, badY);
            ctx.lineTo(badX + badL / 2, badY + badW);
            ctx.stroke();

            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 26px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('BADMINTON COURT #' + (bi + 1) + ' (45×30 FT)', badX + badL / 2, badY + badW / 2 + 10);
          }

          // West partition line at 100 FT
          ctx.strokeStyle = '#064e3b';
          ctx.lineWidth = 14;
          ctx.beginPath(); ctx.moveTo(z2X, 0); ctx.lineTo(z2X, ch); ctx.stroke();

          // East partition wall at 145 FT
          var z3X = z2X + z2W;
          var z3W = 40 * scX; // 800 px
          ctx.strokeStyle = '#0f172a';
          ctx.lineWidth = 14;
          ctx.beginPath(); ctx.moveTo(z3X, 0); ctx.lineTo(z3X, ch); ctx.stroke();

          // ZONE 3: 40 FT OPEN SPACE (145 to 185 FT)
          ctx.fillStyle = '#e2e8f0';
          ctx.fillRect(z3X, 0, z3W, ch);
          ctx.strokeStyle = '#cbd5e1';
          ctx.lineWidth = 2;
          for (var px_i = z3X; px_i < cw; px_i += 30) {
            ctx.beginPath(); ctx.moveTo(px_i, 0); ctx.lineTo(px_i, ch); ctx.stroke();
          }
          for (var py_i = 0; py_i < ch; py_i += 30) {
            ctx.beginPath(); ctx.moveTo(z3X, py_i); ctx.lineTo(cw, py_i); ctx.stroke();
          }
          ctx.fillStyle = '#475569';
          ctx.font = 'bold 22px Inter, sans-serif';
          ctx.textAlign = 'center';
          ctx.fillText('ZONE C: 40 FT × 60 FT FRONT PARKING & OPEN SPACE (2,400 SFT)', z3X + z3W / 2, ch / 2 - 15);
          ctx.font = 'bold 18px Inter, sans-serif';
          ctx.fillStyle = '#64748b';
          ctx.fillText('ZERO METAL STRUCTURE • CLIENT SCOPE', z3X + z3W / 2, ch / 2 + 20);
        }

        // Bind Canvas to Three.js Texture (Full 185 FT complex)
        var tex185 = new THREE.CanvasTexture(cvs);
        tex185.anisotropy = 16;
        var mat185 = new THREE.MeshStandardMaterial({ map: tex185, roughness: 0.4, metalness: 0.05 });
        var ground185 = new THREE.Mesh(new THREE.PlaneGeometry(L, W), mat185);
        ground185.rotation.x = -Math.PI / 2;
        ground185.position.set(0, 0.003, 0);
        scene.add(ground185);
      }

      if (sport === 'multisport_complex_105x170' || (L_ft === 170 && W_ft === 105)) {
        var cvs = document.createElement('canvas');
        cvs.width = 3400;  // 20 px per foot (170 FT Length)
        cvs.height = 2100; // 20 px per foot (105 FT Width)
        var ctx = cvs.getContext('2d');

        var cw = cvs.width;
        var ch = cvs.height;
        var scX = cw / 170.0; // 20.0 px/ft
        var scY = ch / 105.0; // 20.0 px/ft

        // Base Campus Concrete Foundation
        ctx.fillStyle = '#0f172a';
        ctx.fillRect(0, 0, cw, ch);

        // ───────────────────────────────────────────────────────────────────────
        // ZONE 1: FRONT VEHICLE PARKING (105 FT × 18 FT = 1,890 SFT)
        // ───────────────────────────────────────────────────────────────────────
        var parkW = 18 * scX; // 360 px
        ctx.fillStyle = '#1e293b';
        ctx.fillRect(0, 0, parkW, ch);

        // Paver Block Grid Pattern
        ctx.strokeStyle = '#334155';
        ctx.lineWidth = 1.5;
        for (var px = 0; px < parkW; px += 24) {
          ctx.beginPath(); ctx.moveTo(px, 0); ctx.lineTo(px, ch); ctx.stroke();
        }
        for (var py = 0; py < ch; py += 24) {
          ctx.beginPath(); ctx.moveTo(0, py); ctx.lineTo(parkW, py); ctx.stroke();
        }

        // Individual Parking Bays (10+ vehicle bays)
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 4;
        for (var b = 80; b < ch - 100; b += 160) {
          ctx.strokeRect(40, b, parkW - 80, 140);
        }

        // Single Main Campus Entrance Gate on Left at Front of Metal Structure (X = 18 FT mark)
        ctx.fillStyle = '#f97316';
        ctx.fillRect(parkW - 14, 30, 24, 240); // At 18 FT boundary entering 15 FT pathway
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 20px Inter, sans-serif';
        ctx.save();
        ctx.translate(parkW - 2, 150); ctx.rotate(-Math.PI / 2);
        ctx.fillText('MAIN ENTRANCE GATE (12×8 FT)', 0, 0);
        ctx.restore();

        // Parking Title
        ctx.save();
        ctx.translate(parkW / 2 + 10, ch / 2);
        ctx.rotate(-Math.PI / 2);
        ctx.font = '900 32px Inter, sans-serif';
        ctx.fillStyle = '#94a3b8';
        ctx.textAlign = 'center';
        ctx.fillText('OUTSIDE VEHICLE PARKING (105×18 FT) • OUTSIDE METAL STRUCTURE', 0, 0);
        ctx.restore();

        // ───────────────────────────────────────────────────────────────────────
        // ZONE 2A: 15 FT CONNECTING PATHWAY (50 FT × 15 FT = 750 SFT)
        // ───────────────────────────────────────────────────────────────────────
        var cricX = parkW;
        var cricW = 50 * scX; // 1000 px
        var pathH = 15 * scY; // 300 px
        ctx.fillStyle = '#fef3c7';
        ctx.fillRect(cricX, 0, cricW, pathH);
        ctx.strokeStyle = '#d97706';
        ctx.lineWidth = 3;
        ctx.strokeRect(cricX, 0, cricW, pathH);

        ctx.fillStyle = '#b45309';
        ctx.font = 'bold 26px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('➔ 15 FT CONNECTING PATHWAY (ACCESS TO INDOOR ARENA) ➔ ➔', cricX + cricW / 2, pathH / 2 + 9);

        // ───────────────────────────────────────────────────────────────────────
        // ZONE 2B: OUTDOOR BOX CRICKET GROUND (90 FT × 50 FT = 4,500 SFT)
        // ───────────────────────────────────────────────────────────────────────
        var cricY = pathH;
        var cricH = ch - pathH; // 1800 px (90 FT)

        // Turf base with alternating lawn stripes
        for (var s = 0; s < cricW; s += 50) {
          ctx.fillStyle = (Math.floor(s / 50) % 2 === 0) ? '#15803d' : '#16a34a';
          ctx.fillRect(cricX + s, cricY, 50, cricH);
        }

        // Boundary line
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 5;
        ctx.setLineDash([16, 12]);
        ctx.strokeRect(cricX + 16, cricY + 16, cricW - 32, cricH - 32);
        ctx.setLineDash([]);

        // Cricket Open-Top Label
        ctx.fillStyle = 'rgba(255, 255, 255, 0.94)';
        ctx.fillRect(cricX + 30, cricY + 30, cricW - 60, 60);
        ctx.strokeStyle = '#15803d';
        ctx.lineWidth = 3;
        ctx.strokeRect(cricX + 30, cricY + 30, cricW - 60, 60);
        ctx.fillStyle = '#15803d';
        ctx.font = '900 24px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('🏏 OUTDOOR BOX CRICKET (90×50 FT) — OPEN TOP / NO GI ROOF', cricX + cricW / 2, cricY + 68);

        // 66 FT × 10 FT Centered Cricket Match Pitch
        var pLen = 66 * scY; // 1320 px along Y
        var pWid = 10 * scX; // 200 px along X
        var pX = cricX + (cricW - pWid) / 2;
        var pY = cricY + (cricH - pLen) / 2;

        ctx.fillStyle = '#deb887'; // Natural clay / curled turf
        ctx.fillRect(pX, pY, pWid, pLen);
        ctx.strokeStyle = '#854d0e';
        ctx.lineWidth = 4;
        ctx.strokeRect(pX, pY, pWid, pLen);

        // Regulation Creases
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 5;
        // Bowling crease ends
        ctx.beginPath(); ctx.moveTo(pX, pY + 24); ctx.lineTo(pX + pWid, pY + 24); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(pX, pY + pLen - 24); ctx.lineTo(pX + pWid, pY + pLen - 24); ctx.stroke();
        // Popping crease (4 FT inside)
        ctx.beginPath(); ctx.moveTo(pX, pY + 80); ctx.lineTo(pX + pWid, pY + 80); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(pX, pY + pLen - 80); ctx.lineTo(pX + pWid, pY + pLen - 80); ctx.stroke();

        ctx.save();
        ctx.translate(pX + pWid / 2, pY + pLen / 2);
        ctx.rotate(-Math.PI / 2);
        ctx.fillStyle = '#713f12';
        ctx.font = 'bold 22px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('MATCH PITCH (66 FT × 10 FT)', 0, 0);
        ctx.restore();

        // ───────────────────────────────────────────────────────────────────────
        // ZONE 3: INDOOR SPORTS HALL (105 FT × 102 FT = 10,710 SFT ~ 11,000 SFT)
        // ───────────────────────────────────────────────────────────────────────
        var indX = cricX + cricW; // 1360 px
        var indW = 102 * scX;     // 2040 px

        // Civil base floor bed
        ctx.fillStyle = '#f8fafc';
        ctx.fillRect(indX, 0, indW, ch);

        // Heavy Thick Perimeter Foundation Walls (10 FT Brickwork Boundary)
        ctx.strokeStyle = '#0f172a';
        ctx.lineWidth = 14;
        ctx.strokeRect(indX, 0, indW, ch);

        // Header Banner: INDOOR SPORTS HALL UNDER 0.50mm PPGI ROOF (35 FT HEIGHT)
        ctx.fillStyle = '#1e3a8a';
        ctx.fillRect(indX + 30, 20, indW - 60, 70);
        ctx.strokeStyle = '#38bdf8';
        ctx.lineWidth = 3;
        ctx.strokeRect(indX + 30, 20, indW - 60, 70);
        ctx.fillStyle = '#ffffff';
        ctx.font = '900 30px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('🏠 INDOOR SPORTS HALL (11,000 SFT) — UNDER 0.50mm PPGI GI SHEET ROOF (35 FT HEIGHT)', indX + indW / 2, 65);

        var badX = indX + 50;
        var badW = 44 * scX; // 880 px
        var badH = 20 * scY; // 400 px
        var badGap = 70;
        var badStartY = 110;
        var pklX = badX + badW + 90;
        var pklW = 44 * scX; // 880 px
        var pklH = 20 * scY; // 400 px

        // ── [SWAPPED] 1. AMENITIES & RECEPTION BLOCK ON TOP-LEFT (Adjacent to 15 FT Connecting Pathway)
        var amenX = badX;
        var amenY = badStartY;
        var amenW = badW;
        var amenH = (2 * badH) + badGap;

        ctx.fillStyle = '#f1f5f9';
        ctx.fillRect(amenX, amenY, amenW, amenH);
        ctx.strokeStyle = '#94a3b8';
        ctx.lineWidth = 4;
        ctx.strokeRect(amenX, amenY, amenW, amenH);

        // Entry header badge (Pathway side)
        ctx.fillStyle = '#0284c7';
        ctx.fillRect(amenX, amenY - 26, amenW, 26);
        ctx.fillStyle = '#ffffff';
        ctx.font = '900 15px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('➔ GUEST / VISITOR ENTRY FROM 15 FT CONNECTING PATHWAY ➔', amenX + amenW / 2, amenY - 8);

        var frontH = amenH * 0.46; // Front zone depth (~20 FT)
        var innerH = amenH - frontH; // Inner zone depth (~23.5 FT)
        var innerY = amenY + frontH;

        // Front-West: Main Lobby & Reception Foyer
        var lobbyW = amenW * 0.56;
        ctx.fillStyle = '#e0f2fe';
        ctx.fillRect(amenX, amenY, lobbyW, frontH);
        ctx.strokeStyle = '#38bdf8'; ctx.lineWidth = 2; ctx.strokeRect(amenX, amenY, lobbyW, frontH);
        ctx.fillStyle = '#0369a1';
        ctx.font = 'bold 20px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('🚪 MAIN LOBBY', amenX + lobbyW / 2, amenY + frontH / 2 - 10);
        ctx.font = '14px Inter, sans-serif';
        ctx.fillText('ENTRANCE FOYER & RECEPTION', amenX + lobbyW / 2, amenY + frontH / 2 + 10);

        // Front-East: Office Room & Billing Desk
        var offW = amenW - lobbyW;
        ctx.fillStyle = '#f8fafc';
        ctx.fillRect(amenX + lobbyW, amenY, offW, frontH);
        ctx.strokeStyle = '#cbd5e1'; ctx.lineWidth = 2; ctx.strokeRect(amenX + lobbyW, amenY, offW, frontH);
        ctx.fillStyle = '#1e293b';
        ctx.font = 'bold 20px Inter, sans-serif';
        ctx.fillText('🏢 OFFICE ROOM', amenX + lobbyW + offW / 2, amenY + frontH / 2 - 10);
        ctx.font = '14px Inter, sans-serif';
        ctx.fillText('& BILLING DESK', amenX + lobbyW + offW / 2, amenY + frontH / 2 + 10);

        // Inner-West: Gents Washrooms & Changing Suite (Directly facing play areas)
        var washW = amenW / 2;
        ctx.fillStyle = '#f1f5f9';
        ctx.fillRect(amenX, innerY, washW, innerH);
        ctx.strokeStyle = '#94a3b8'; ctx.lineWidth = 2; ctx.strokeRect(amenX, innerY, washW, innerH);
        ctx.fillStyle = '#1e293b';
        ctx.font = 'bold 20px Inter, sans-serif';
        ctx.fillText('🚹 GENTS CHANGING', amenX + washW / 2, innerY + innerH / 2 - 10);
        ctx.font = '14px Inter, sans-serif';
        ctx.fillText('& WASHROOMS', amenX + washW / 2, innerY + innerH / 2 + 10);

        // Inner-East: Ladies Washrooms & Changing Suite (Directly facing play areas)
        ctx.fillStyle = '#fdf2f8';
        ctx.fillRect(amenX + washW, innerY, washW, innerH);
        ctx.strokeStyle = '#f472b6'; ctx.lineWidth = 2; ctx.strokeRect(amenX + washW, innerY, washW, innerH);
        ctx.fillStyle = '#9d174d';
        ctx.font = 'bold 20px Inter, sans-serif';
        ctx.fillText('🚺 LADIES CHANGING', amenX + washW * 1.5, innerY + innerH / 2 - 10);
        ctx.font = '14px Inter, sans-serif';
        ctx.fillText('& WASHROOMS', amenX + washW * 1.5, innerY + innerH / 2 + 10);

        // South Court Access Banner (Facing Courts)
        ctx.fillStyle = '#059669';
        ctx.fillRect(amenX, amenY + amenH, amenW, 26);
        ctx.fillStyle = '#ffffff';
        ctx.font = '900 14px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('➔ DIRECT PLAYER ACCESS TO BADMINTON & PICKLEBALL COURTS ➔', amenX + amenW / 2, amenY + amenH + 18);

        // ── 2. 2 REGULATION PICKLEBALL COURTS (TOP-RIGHT)
        for (var pi = 0; pi < 2; pi++) {
          var py = badStartY + (pi * (pklH + badGap * 1.5));

          ctx.fillStyle = '#1e40af';
          ctx.fillRect(pklX, py, pklW, pklH);
          ctx.strokeStyle = '#1e3a8a';
          ctx.lineWidth = 3;
          ctx.strokeRect(pklX, py, pklW, pklH);

          var pNetX = pklX + pklW / 2;
          ctx.fillStyle = '#0284c7';
          ctx.fillRect(pNetX - 140, py + 6, 280, pklH - 12);

          ctx.strokeStyle = '#ffffff';
          ctx.lineWidth = 4;
          ctx.strokeRect(pklX + 6, py + 6, pklW - 12, pklH - 12);

          ctx.strokeStyle = '#dc2626';
          ctx.lineWidth = 8;
          ctx.beginPath(); ctx.moveTo(pNetX, py); ctx.lineTo(pNetX, py + pklH); ctx.stroke();

          ctx.strokeStyle = '#ffffff';
          ctx.lineWidth = 4;
          ctx.beginPath(); ctx.moveTo(pNetX - 140, py + 6); ctx.lineTo(pNetX - 140, py + pklH - 6); ctx.stroke();
          ctx.beginPath(); ctx.moveTo(pNetX + 140, py + 6); ctx.lineTo(pNetX + 140, py + pklH - 6); ctx.stroke();

          ctx.beginPath(); ctx.moveTo(pklX + 6, py + pklH / 2); ctx.lineTo(pNetX - 140, py + pklH / 2); ctx.stroke();
          ctx.beginPath(); ctx.moveTo(pNetX + 140, py + pklH / 2); ctx.lineTo(pklX + pklW - 6, py + pklH / 2); ctx.stroke();

          ctx.fillStyle = '#ffffff';
          ctx.font = '900 22px Inter, sans-serif';
          ctx.textAlign = 'left';
          ctx.fillText('PICKLEBALL CT ' + (pi + 1) + ' (44×20 FT)', pklX + 24, py + pklH / 2 + 8);
          ctx.textAlign = 'right';
          ctx.fillStyle = '#bae6fd';
          ctx.font = 'bold 20px Inter, sans-serif';
          ctx.fillText('8-LAYER ACRYLIC CUSHION', pklX + pklW - 24, py + pklH / 2 + 8);
        }

        // Helper to draw Badminton Court on Ground Canvas
        var drawBadmintonCvs = function(bx, by, ctLabel) {
          ctx.fillStyle = '#047857';
          ctx.fillRect(bx, by, badW, badH);
          ctx.strokeStyle = '#065f46';
          ctx.lineWidth = 3;
          ctx.strokeRect(bx, by, badW, badH);

          ctx.strokeStyle = '#ffffff';
          ctx.lineWidth = 4;
          ctx.strokeRect(bx + 6, by + 6, badW - 12, badH - 12);

          ctx.beginPath();
          ctx.moveTo(bx + 6, by + 36); ctx.lineTo(bx + badW - 6, by + 36);
          ctx.moveTo(bx + 6, by + badH - 36); ctx.lineTo(bx + badW - 6, by + badH - 36);
          ctx.stroke();

          var bNetX = bx + badW / 2;
          ctx.strokeStyle = '#dc2626';
          ctx.lineWidth = 8;
          ctx.beginPath(); ctx.moveTo(bNetX, by); ctx.lineTo(bNetX, by + badH); ctx.stroke();

          ctx.strokeStyle = '#ffffff';
          ctx.lineWidth = 3.5;
          ctx.beginPath(); ctx.moveTo(bNetX - 130, by + 6); ctx.lineTo(bNetX - 130, by + badH - 6); ctx.stroke();
          ctx.beginPath(); ctx.moveTo(bNetX + 130, by + 6); ctx.lineTo(bNetX + 130, by + badH - 6); ctx.stroke();

          ctx.beginPath(); ctx.moveTo(bx + 56, by + 6); ctx.lineTo(bx + 56, by + badH - 6); ctx.stroke();
          ctx.beginPath(); ctx.moveTo(bx + badW - 56, by + 6); ctx.lineTo(bx + badW - 56, by + badH - 6); ctx.stroke();

          ctx.beginPath(); ctx.moveTo(bx + 56, by + badH / 2); ctx.lineTo(bNetX - 130, by + badH / 2); ctx.stroke();
          ctx.beginPath(); ctx.moveTo(bNetX + 130, by + badH / 2); ctx.lineTo(bx + badW - 56, by + badH / 2); ctx.stroke();

          ctx.fillStyle = '#ffffff';
          ctx.font = '900 22px Inter, sans-serif';
          ctx.textAlign = 'left';
          ctx.fillText(ctLabel + ' (44×20 FT)', bx + 24, by + badH / 2 + 8);
          ctx.textAlign = 'right';
          ctx.fillStyle = '#a7f3d0';
          ctx.font = 'bold 20px Inter, sans-serif';
          ctx.fillText('4.5mm BWF VINYL MAT', bx + badW - 24, by + badH / 2 + 8);
        };

        // ── 3. BADMINTON COURTS 1 & 2 (BOTTOM-LEFT)
        drawBadmintonCvs(badX, badStartY + (2 * (badH + badGap)), 'BADMINTON CT 1');
        drawBadmintonCvs(badX, badStartY + (3 * (badH + badGap)), 'BADMINTON CT 2');

        // ── [SWAPPED] 4. BADMINTON COURTS 3 & 4 (BOTTOM-RIGHT, where amenities used to be)
        drawBadmintonCvs(pklX, badStartY + (2 * (badH + badGap)), 'BADMINTON CT 3');
        drawBadmintonCvs(pklX, badStartY + (3 * (badH + badGap)), 'BADMINTON CT 4');

        // Bind Canvas to Three.js Texture with high anisotropy
        var megaCourtTexture = new THREE.CanvasTexture(cvs);
        megaCourtTexture.anisotropy = 16;
        var megaCourtMat = new THREE.MeshStandardMaterial({
          map: megaCourtTexture,
          roughness: 0.4,
          metalness: 0.05
        });
        var megaCourtGround = new THREE.Mesh(new THREE.PlaneGeometry(L, W), megaCourtMat);
        megaCourtGround.rotation.x = -Math.PI / 2;
        megaCourtGround.position.set(0, 0.004, 0);
        scene.add(megaCourtGround);
      }

    }

    function createPipe(p1, p2, radius, mat) {
      var v1 = new THREE.Vector3(p1[0], p1[1], p1[2]);
      var v2 = new THREE.Vector3(p2[0], p2[1], p2[2]);
      var dist = v1.distanceTo(v2);
      if (dist < 0.001) return new THREE.Group();
      var cylGeo = new THREE.CylinderGeometry(radius, radius, dist, 8);
      var mesh = new THREE.Mesh(cylGeo, mat);
      var mid = v1.clone().add(v2).multiplyScalar(0.5);
      mesh.position.copy(mid);
      mesh.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), v2.clone().sub(v1).normalize());
      return mesh;
    }

    function createSquarePipe(p1, p2, size, color) {
      var v1 = new THREE.Vector3(p1[0], p1[1], p1[2]);
      var v2 = new THREE.Vector3(p2[0], p2[1], p2[2]);
      var dist = v1.distanceTo(v2);
      if (dist < 0.001) return new THREE.Group();
      var boxGeo = new THREE.BoxGeometry(size, dist, size);
      var colMat = new THREE.MeshStandardMaterial({ color: color || 0x1e293b, roughness: 0.35, metalness: 0.6 });
      var mesh = new THREE.Mesh(boxGeo, colMat);
      var mid = v1.clone().add(v2).multiplyScalar(0.5);
      mesh.position.copy(mid);
      mesh.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), v2.clone().sub(v1).normalize());
      return mesh;
    }

    // High-Precision 3D MS Tubular Entry Gate Builder
    function render3DTubularGate(x, z, widthUnits, heightUnits, isFacingZ, labelText, colorHex) {
      var gateGroup = new THREE.Group();
      var gateMat = new THREE.MeshStandardMaterial({
        color: colorHex || 0xdc2626,
        roughness: 0.35,
        metalness: 0.65
      });
      var pipeRadius = 0.025;
      var halfD = widthUnits / 2;

      if (isFacingZ) {
        // Gate in the YZ plane (across width, normal along X)
        gateGroup.add(createPipe([x, 0, z - halfD], [x, heightUnits, z - halfD], pipeRadius, gateMat));
        gateGroup.add(createPipe([x, 0, z + halfD], [x, heightUnits, z + halfD], pipeRadius, gateMat));
        gateGroup.add(createPipe([x, heightUnits, z - halfD], [x, heightUnits, z + halfD], pipeRadius, gateMat));
        gateGroup.add(createPipe([x, heightUnits * 0.5, z - halfD], [x, heightUnits * 0.5, z + halfD], pipeRadius * 0.75, gateMat));

        // Semi-translucent red door leaf
        var doorGeo = new THREE.PlaneGeometry(widthUnits * 0.95, heightUnits * 0.95);
        var doorMat = new THREE.MeshStandardMaterial({
          color: colorHex || 0xef4444,
          roughness: 0.4,
          metalness: 0.25,
          transparent: true,
          opacity: 0.55,
          side: THREE.DoubleSide
        });
        var doorMesh = new THREE.Mesh(doorGeo, doorMat);
        doorMesh.rotation.y = Math.PI / 2;
        doorMesh.position.set(x, heightUnits / 2, z);
        gateGroup.add(doorMesh);

        // Chrome slide-latch handle
        var handleGeo = new THREE.BoxGeometry(0.03, 0.10, 0.02);
        var handleMat = new THREE.MeshStandardMaterial({ color: 0xf8fafc, metalness: 0.9, roughness: 0.1 });
        var handle = new THREE.Mesh(handleGeo, handleMat);
        handle.position.set(x + 0.02, heightUnits * 0.5, z + halfD * 0.5);
        gateGroup.add(handle);
      } else {
        // Gate in the XY plane (along length, normal along Z)
        gateGroup.add(createPipe([x - halfD, 0, z], [x - halfD, heightUnits, z], pipeRadius, gateMat));
        gateGroup.add(createPipe([x + halfD, 0, z], [x + halfD, heightUnits, z], pipeRadius, gateMat));
        gateGroup.add(createPipe([x - halfD, heightUnits, z], [x + halfD, heightUnits, z], pipeRadius, gateMat));
        gateGroup.add(createPipe([x - halfD, heightUnits * 0.5, z], [x + halfD, heightUnits * 0.5, z], pipeRadius * 0.75, gateMat));

        var doorGeo2 = new THREE.PlaneGeometry(widthUnits * 0.95, heightUnits * 0.95);
        var doorMat2 = new THREE.MeshStandardMaterial({
          color: colorHex || 0xef4444,
          roughness: 0.4,
          metalness: 0.25,
          transparent: true,
          opacity: 0.55,
          side: THREE.DoubleSide
        });
        var doorMesh2 = new THREE.Mesh(doorGeo2, doorMat2);
        doorMesh2.position.set(x, heightUnits / 2, z);
        gateGroup.add(doorMesh2);

        var handle2 = new THREE.Mesh(new THREE.BoxGeometry(0.02, 0.10, 0.03), new THREE.MeshStandardMaterial({ color: 0xf8fafc, metalness: 0.9, roughness: 0.1 }));
        handle2.position.set(x + halfD * 0.5, heightUnits * 0.5, z + 0.02);
        gateGroup.add(handle2);
      }

      return gateGroup;
    }

    var resolvedGates = (showGates) ? getResolvedGates(config, L_ft, W_ft, H_ft) : [];

    // Structural Columns
    var lenSpans = getIntermediateSpans(L_ft, 15, 12);
    var widSpans = getIntermediateSpans(W_ft, 15, 12);
    var poleStyle = config.pole_style || (config.pole_profile && (config.pole_profile.type || config.pole_profile.pole_type)) || 'ladder_2line';
    var isLadder = (poleStyle === 'ladder_2line' || poleStyle === 's3_ladder_truss' || (typeof poleStyle === 'string' && poleStyle.indexOf('ladder') !== -1));
    var is4Line = (poleStyle === 'ladder_4line' || poleStyle === 's4_box_truss');

    function draw3DColumn(x, z, colH, wallOrientation) {
      var colMat = new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.35, metalness: 0.65 });
      if (isLadder) {
        // Two-lines structural ladder column: dual vertical uprights with welded ladder rungs @ 1.5ft c/c
        var ladderW = 0.12;    // 1.2 FT between uprights
        var upThick = 0.035;   // 40mm square pipe
        var rungThick = 0.022; // 25mm square pipe rungs
        var rungStep = 0.15;   // 1.5 FT rung spacing

        if (wallOrientation === 'length') {
          // Sideline columns: 2 uprights along X with welded rungs
          var x1 = x - ladderW / 2;
          var x2 = x + ladderW / 2;
          scene.add(createSquarePipe([x1, 0, z], [x1, colH, z], upThick, colMat));
          scene.add(createSquarePipe([x2, 0, z], [x2, colH, z], upThick, colMat));
          for (var ry = rungStep; ry < colH - 0.05; ry += rungStep) {
            scene.add(createSquarePipe([x1, ry, z], [x2, ry, z], rungThick, colMat));
          }
        } else if (wallOrientation === 'width') {
          // End wall columns: 2 uprights along Z with welded rungs
          var z1 = z - ladderW / 2;
          var z2 = z + ladderW / 2;
          scene.add(createSquarePipe([x, 0, z1], [x, colH, z1], upThick, colMat));
          scene.add(createSquarePipe([x, 0, z2], [x, colH, z2], upThick, colMat));
          for (var ry = rungStep; ry < colH - 0.05; ry += rungStep) {
            scene.add(createSquarePipe([x, ry, z1], [x, ry, z2], rungThick, colMat));
          }
        } else {
          // Corner column: heavy dual uprights with rungs facing inward along length
          var offX = (x <= 0) ? ladderW : -ladderW;
          scene.add(createSquarePipe([x, 0, z], [x, colH, z], upThick * 1.25, colMat));
          scene.add(createSquarePipe([x + offX, 0, z], [x + offX, colH, z], upThick, colMat));
          for (var ry = rungStep; ry < colH - 0.05; ry += rungStep) {
            scene.add(createSquarePipe([x, ry, z], [x + offX, ry, z], rungThick, colMat));
          }
        }
      } else if (is4Line) {
        // 4-line box lattice column
        var boxW = 0.12;
        var pThick = 0.032;
        var rThick = 0.020;
        var rStep = 0.15;
        var hW = boxW / 2;
        var pts = [[x - hW, z - hW], [x + hW, z - hW], [x + hW, z + hW], [x - hW, z + hW]];
        pts.forEach(function(pt) {
          scene.add(createSquarePipe([pt[0], 0, pt[1]], [pt[0], colH, pt[1]], pThick, colMat));
        });
        for (var ry = rStep; ry < colH - 0.05; ry += rStep) {
          scene.add(createSquarePipe([x - hW, ry, z - hW], [x + hW, ry, z - hW], rThick, colMat));
          scene.add(createSquarePipe([x + hW, ry, z - hW], [x + hW, ry, z + hW], rThick, colMat));
          scene.add(createSquarePipe([x + hW, ry, z + hW], [x - hW, ry, z + hW], rThick, colMat));
          scene.add(createSquarePipe([x - hW, ry, z + hW], [x - hW, ry, z - hW], rThick, colMat));
        }
      } else {
        // Single square column
        scene.add(createSquarePipe([x, 0, z], [x, colH, z], 0.075, colMat));
      }
    }

    if (showStructure) {
      if (is185x60) {
        // Portal Frame Coordinates spanning strictly 0 to 145 FT (ZONE C 145-185 FT has ZERO METAL STRUCTURE!):
        // Zone 1 (Cricket, 0–100 FT): 6 equal bays of 16.67 FT (7 frames: 0, 16.67, 33.33, 50, 66.67, 83.33, 100 FT)
        // Zone 2 (Badminton, 100–145 FT): 3 equal bays of 15.00 FT (3 frames: 115, 130, 145 FT)
        // Total = 10 Frame Lines spanning 145 FT metal structure!
        var arenaFrameXs = is160Builtup ? [
          arenaStartRelX,                          // 0 FT (West end wall)
          arenaStartRelX + (10.0 / 6) * 1,         // 16.67 FT
          arenaStartRelX + (10.0 / 6) * 2,         // 33.33 FT
          arenaStartRelX + (10.0 / 6) * 3,         // 50.00 FT
          arenaStartRelX + (10.0 / 6) * 4,         // 66.67 FT
          arenaStartRelX + (10.0 / 6) * 5,         // 83.33 FT
          arenaStartRelX + 10.0,                   // 100.00 FT (Partition Wall: Cricket ↔ Badminton)
          arenaStartRelX + 10.0 + 1.5,             // 115.00 FT
          arenaStartRelX + 10.0 + 3.0,             // 130.00 FT
          arenaStartRelX + 10.0 + 4.5,             // 145.00 FT
          sportsEndRelX                            // 160.00 FT (End of 160 FT Metal Structure)
        ] : [
          arenaStartRelX,                          // 0 FT (West end wall)
          arenaStartRelX + (10.0 / 6) * 1,         // 16.67 FT
          arenaStartRelX + (10.0 / 6) * 2,         // 33.33 FT
          arenaStartRelX + (10.0 / 6) * 3,         // 50.00 FT
          arenaStartRelX + (10.0 / 6) * 4,         // 66.67 FT
          arenaStartRelX + (10.0 / 6) * 5,         // 83.33 FT
          arenaStartRelX + 10.0,                   // 100.00 FT (Partition Wall: Cricket ↔ Badminton)
          arenaStartRelX + 10.0 + (4.5 / 3) * 1,   // 115.00 FT
          arenaStartRelX + 10.0 + (4.5 / 3) * 2,   // 130.00 FT
          sportsEndRelX                            // 145.00 FT (Badminton Front Wall / End of Metal Structure)
        ];

        // 1. EXACTLY 20 Perimeter Sideline Columns (10 North + 10 South = 20 Columns)
        for (var fi = 0; fi < arenaFrameXs.length; fi++) {
          var fx = arenaFrameXs[fi];
          scene.add(createSquarePipe([fx, 0, -halfW], [fx, H, -halfW], 0.075));
          scene.add(createSquarePipe([fx, 0, halfW], [fx, H, halfW], 0.075));
        }

        // 2. Transverse End Wall Columns:
        // A. West End Wall (0 FT = arenaStartRelX): 4 inner columns
        [-1.8, -0.6, 0.6, 1.8].forEach(function(pz) {
          scene.add(createSquarePipe([arenaStartRelX, 0, pz], [arenaStartRelX, H, pz], 0.065));
        });

        // B. East Front Entrance Wall of Badminton Complex (160 FT = sportsEndRelX for 078, 145 FT for 074):
        // Inner columns: center poles are shifted 3.5 FT away from center (z = ±0.35) to frame the 6 FT center gate!
        var frontColsZ = [-1.8, -0.35, 0.35, 1.8];
        frontColsZ.forEach(function(pz) {
          scene.add(createSquarePipe([sportsEndRelX, 0, pz], [sportsEndRelX, H, pz], 0.065));
        });
      } else if (is105x170) {
        // Enclose ONLY the 152 FT x 105 FT metal structure (Parking 18 FT is completely OUTSIDE!)
        // Zone 2 (Outdoor Cricket + Pathway, 50 FT, x: -6.7 to -1.7): 30 FT column height (colH = 3.0)
        var cricColsX = [structStartX, -5.033, -3.366, indoorStartRelX];
        cricColsX.forEach(function(cx) {
          draw3DColumn(cx, -halfW, 3.0, (cx === structStartX) ? 'corner' : 'length');
          draw3DColumn(cx, halfW, 3.0, (cx === structStartX) ? 'corner' : 'length');
        });

        // Intermediate columns separating 15 FT pathway from cricket cage at z = -3.75 (-halfW + 1.5):
        [-5.033, -3.366].forEach(function(cx) {
          draw3DColumn(cx, -3.75, 3.0, 'length');
        });

        // Front Transverse Wall at structStartX (-6.7, 18 FT):
        // Pathway divider column at z = -3.75
        draw3DColumn(structStartX, -3.75, 3.0, 'width');
        // Across the 90 FT cricket front wall (from z = -3.75 to +5.25):
        [-2.25, -0.75, 0.75, 2.25, 3.75].forEach(function(cz) {
          draw3DColumn(structStartX, cz, 3.0, 'width');
        });

        // Rear wall columns at structEndX (+8.5, 170 FT):
        draw3DColumn(structEndX, -halfW, 3.5, 'corner');
        draw3DColumn(structEndX, halfW, 3.5, 'corner');
        [-3.5, -1.75, 1.75, 3.5].forEach(function(cz) {
          draw3DColumn(structEndX, cz, 3.5, 'width');
        });
      } else {
        // Corner Columns
        draw3DColumn(-halfL, -halfW, H, 'corner');
        draw3DColumn(halfL, -halfW, H, 'corner');
        draw3DColumn(-halfL, halfW, H, 'corner');
        draw3DColumn(halfL, halfW, H, 'corner');

        // Intermediate Columns along Length
        for (var i = 0; i < lenSpans.positions.length; i++) {
          var x = -halfL + lenSpans.positions[i] * 0.1;
          draw3DColumn(x, -halfW, H, 'length');
          draw3DColumn(x, halfW, H, 'length');
        }

        // Intermediate Columns along Width
        if (!isTrainingNet && !isFrontOpen) {
          for (var j = 0; j < widSpans.positions.length; j++) {
            var z = -halfW + widSpans.positions[j] * 0.1;
            draw3DColumn(-halfL, z, H, 'width');
          }
        }
        if (!isBackOpen) {
          for (var k = 0; k < widSpans.positions.length; k++) {
            var z2 = -halfW + widSpans.positions[k] * 0.1;
            draw3DColumn(halfL, z2, H, 'width');
          }
        }
      }
    }

    // Horizontal Tiers & Purlins (Segmented around door openings)
    var tiersFt = getTurnkeyTierHeights(H_ft, config);
    var tiers = tiersFt.map(function(t) { return t * 0.1; });

    function drawSegmentedPurlinX(zPos, ty, tierFt, wallKey, mat) {
      // In 105x170, Outdoor Cricket (x: structStartX to indoorStartRelX) is 30 FT high;
      // tiers above 30 FT only belong to the 35 FT Indoor PEB Building (x: indoorStartRelX to structEndX).
      var purlinStart = is105x170 ? (tierFt > 30.5 ? indoorStartRelX : structStartX) : (is185x60 ? arenaStartRelX : -halfL);
      var purlinEnd   = is105x170 ? structEndX : (is185x60 ? sportsEndRelX : halfL);

      var wallGates = resolvedGates.filter(function(g) {
        var gx = is185x60 ? (arenaStartRelX + g.x_ft * 0.1) : (g.x_ft * 0.1);
        if (gx < purlinStart - 0.5 || gx > purlinEnd + 0.5) return false;
        return g.wall === wallKey && tierFt <= g.height_ft;
      });

      if (wallGates.length === 0) {
        scene.add(createPipe([purlinStart, ty, zPos], [purlinEnd, ty, zPos], 0.02, mat));
        return;
      }

      var intervals = [];
      wallGates.forEach(function(g) {
        var halfDoor = (g.width_ft * 0.1) / 2;
        var gx = is185x60 ? (arenaStartRelX + g.x_ft * 0.1) : (g.x_ft * 0.1);
        var gStart = Math.max(purlinStart, gx - halfDoor);
        var gEnd   = Math.min(purlinEnd, gx + halfDoor);
        if (gStart < gEnd && gStart < purlinEnd && gEnd > purlinStart) {
          intervals.push({ start: gStart, end: gEnd });
        }
      });
      intervals.sort(function(a, b) { return a.start - b.start; });

      var curX = purlinStart;
      intervals.forEach(function(iv) {
        var segEnd = Math.min(purlinEnd, iv.start);
        if (segEnd > curX + 0.02) {
          scene.add(createPipe([curX, ty, zPos], [segEnd, ty, zPos], 0.02, mat));
        }
        curX = Math.max(curX, iv.end);
      });
      if (curX < purlinEnd - 0.02) {
        scene.add(createPipe([curX, ty, zPos], [purlinEnd, ty, zPos], 0.02, mat));
      }
    }

    function drawSegmentedPurlinZ(xPos, ty, tierFt, wallKey, mat) {
      var wallGates = resolvedGates.filter(function(g) {
        return g.wall === wallKey && tierFt <= g.height_ft;
      });

      // For 185x60 front wall (at xPos = sportsEndRelX), add gate intervals if tierFt <= 7 FT
      if (is185x60 && Math.abs(xPos - sportsEndRelX) < 0.05 && tierFt <= 7.0) {
        wallGates = [
          { z_ft: 0.0, width_ft: 6.0, height_ft: 7.0 } // Main Entrance Door [E] at center entrance way (z = 0.0)
        ];
      }
      // For 185x60 inside partition wall at 100 FT (z1EndX)
      if (is185x60 && Math.abs(xPos - z1EndX) < 0.05 && tierFt <= 7.0) {
        wallGates = [
          { z_ft: 0.0, width_ft: 4.0, height_ft: 7.0 }     // Inside Gate at z = 0.0
        ];
      }

      // For 105x170 front wall at X = 18 FT (xPos = structStartX), add 12 FT Main Gate interval if tierFt <= 8.0 FT
      if (is105x170 && Math.abs(xPos - structStartX) < 0.05 && tierFt <= 8.0) {
        wallGates = [
          { z_ft: -45.0, width_ft: 12.0, height_ft: 8.0 } // 12 FT Main Gate centered on pathway at z = -4.5
        ];
      }

      if (wallGates.length === 0) {
        scene.add(createPipe([xPos, ty, -halfW], [xPos, ty, halfW], 0.02, mat));
        return;
      }

      var intervals = [];
      wallGates.forEach(function(g) {
        var halfDoor = (g.width_ft * 0.1) / 2;
        var gz = g.z_ft * 0.1;
        intervals.push({ start: Math.max(-halfW, gz - halfDoor), end: Math.min(halfW, gz + halfDoor) });
      });
      intervals.sort(function(a, b) { return a.start - b.start; });

      var curZ = -halfW;
      intervals.forEach(function(iv) {
        if (iv.start > curZ + 0.02) {
          scene.add(createPipe([xPos, ty, curZ], [xPos, ty, iv.start], 0.02, mat));
        }
        curZ = Math.max(curZ, iv.end);
      });
      if (curZ < halfW - 0.02) {
        scene.add(createPipe([xPos, ty, curZ], [xPos, ty, halfW], 0.02, mat));
      }
    }

    if (showStructure) {
      for (var t = 0; t < tiers.length; t++) {
        var ty = tiers[t];
        var tierFt = tiersFt[t];

        if (!isLeftOpen) drawSegmentedPurlinX(-halfW, ty, tierFt, 'left_length', steelMat);
        if (!isRightOpen) drawSegmentedPurlinX(halfW, ty, tierFt, 'right_length', steelMat);

        // Front wall purlins: in 105x170, front wall is at structStartX (-6.7 = 18 FT mark) and height is 30 FT!
        // ZERO horizontal lines at 0 FT (arenaStartRelX)!
        var frontPurlinX = is105x170 ? structStartX : (is185x60 ? arenaStartRelX : -halfL);
        if (!isFrontOpen && (!is105x170 || tierFt <= 30.5)) {
          drawSegmentedPurlinZ(frontPurlinX, ty, tierFt, 'front_width', steelMat);
        }

        // Pathway divider horizontal tie pipe at z = -3.75 (from 18 FT to 68 FT)
        if (is105x170 && tierFt <= 30.5) {
          scene.add(createPipe([structStartX, ty, -3.75], [indoorStartRelX, ty, -3.75], 0.02, steelMat));
        }

        var backPurlinX = is185x60 ? sportsEndRelX : halfL;
        if (!isBackOpen) drawSegmentedPurlinZ(backPurlinX, ty, tierFt, 'back_width', steelMat);
        if (is185x60) {
          drawSegmentedPurlinZ(z1EndX, ty, tierFt, 'inside_partition', steelMat);
        }
      }

      // High-Tension Galvanized Wire Rope Grid across Top (for light_cable_top / training nets)
      if (hasRoof && (roofType === 'light_cable_top' || roofType === 'wire_rope_grid') && !is185x60 && !is105x170) {
        var topCableMat = new THREE.MeshStandardMaterial({ color: 0x64748b, roughness: 0.4, metalness: 0.8 });
        for (var ci = 0; ci < lenSpans.positions.length; ci++) {
          var cxPos = -halfL + lenSpans.positions[ci] * 0.1;
          scene.add(createPipe([cxPos, H, -halfW], [cxPos, H, halfW], 0.009, topCableMat));
        }
      }
    }

        // Render 3D Gate Frames and Doors
    if (showGates && is185x60) {
      if (is160Builtup) {
        // 1. FRONT MAIN ENTRANCE DOOR (6 FT × 7 FT) at 160 FT (sportsEndRelX) at CENTER ENTRANCE WAY (z = 0.0)
        var frontMainEntry = render3DTubularGate(
          sportsEndRelX, // x = +6.75 (160 FT front boundary)
          0.0,          // z = 0.0 (EXACT CENTER ENTRANCE WAY OF 60 FT WIDTH!)
          0.6,          // 6 FT width
          0.7,          // 7 FT height
          true,         // facing along X (in YZ plane)
          "🚪 [E] MAIN ENTRANCE (CENTER)",
          0xdc2626
        );
        scene.add(frontMainEntry);

        // 2. INSIDE INTER-ZONE GATE: CRICKET ↔ BADMINTON (4 FT × 7 FT)
        var insideGate = render3DTubularGate(
          arenaStartRelX + 10.0, // x = +0.75 (100 FT partition wall)
          0.0,                   // z = 0.0 (central walkway)
          0.4,                   // 4 FT width
          0.7,                   // 7 FT height
          true,                  // facing along X
          "🔄 INSIDE GATE (CRICKET ↔ BADMINTON)",
          0x0284c7
        );
        scene.add(insideGate);

        // 3. CRICKET SIDELINE PLAYER DOOR (4 FT × 7 FT)
        var sidelineGate = render3DTubularGate(
          -5.08,  // x = -5.08 (Zone 1)
          -halfW, // z = -3.0 (north sideline)
          0.4,    // 4 FT width
          0.7,    // 7 FT height
          false,  // facing along Z
          null,
          0xeab308
        );
        scene.add(sidelineGate);
      } else {
        // 1. FRONT MAIN ENTRANCE DOOR (6 FT × 7 FT) at 145 FT (sportsEndRelX) at CENTER ENTRANCE WAY (z = 0.0)
        var frontMainEntry = render3DTubularGate(
          sportsEndRelX, // x = +5.25 (145 FT front boundary)
          0.0,          // z = 0.0 (EXACT CENTER ENTRANCE WAY OF 60 FT WIDTH!)
          0.6,          // 6 FT width
          0.7,          // 7 FT height
          true,         // facing along X (in YZ plane)
          "🚪 [E] MAIN ENTRANCE",
          0xdc2626
        );
        scene.add(frontMainEntry);

        // 2. INSIDE INTER-ZONE GATE: CRICKET ↔ BADMINTON (4 FT × 7 FT)
        var insideGate = render3DTubularGate(
          arenaStartRelX + 10.0, // x = +0.75 (100 FT partition wall)
          0.0,                   // z = 0.0 (central walkway)
          0.4,                   // 4 FT width
          0.7,                   // 7 FT height
          true,                  // facing along X
          "🔄 INSIDE GATE (CRICKET ↔ BADMINTON)",
          0x0284c7
        );
        scene.add(insideGate);

        var sidelineGate = render3DTubularGate(
          -5.08,  // x = -5.08 (Zone 1, centered between frame 2 and frame 3)
          -halfW, // z = -3.0 (north sideline)
          0.4,    // 4 FT width
          0.7,    // 7 FT height
          false,  // facing along Z
          null,
          0xeab308
        );
        scene.add(sidelineGate);
      }
    } else if (sport === 'multisport_complex_105x170' || (L_ft === 170 && W_ft === 105)) {
      if (showGates) {
        // 1. MAIN ENTRANCE GATE (12 FT x 8 FT) on Front Wall of Metal Structure at X = structStartX (-6.7, 18 FT mark)
        // Direct grand entry from the outside open-air vehicle parking into the 15 FT connecting pathway
        var gateMain = render3DTubularGate(
          structStartX, // -6.7 (18 FT mark)
          -4.5,         // centered on 15 FT pathway
          1.2,          // 12 FT width
          0.8,          // 8 FT height
          true,         // isFacingZ = true (across width in YZ plane)
          null,
          0xf97316      // high-visibility industrial orange
        );
        scene.add(gateMain);

        // 2. INDOOR ARENA MAIN DOUBLE DOOR (6 FT x 8 FT) at X = indoorStartRelX (-1.7, 68 FT mark)
        // Located on the front transverse wall of the PEB building along the 15 FT pathway (centered at z = -4.5)
        // THIS IS THE EXACT RED RECTANGLE BOX HIGHLIGHTED BY THE USER!
        var gateIndoor = render3DTubularGate(
          indoorStartRelX, // -1.7 (68 FT)
          -4.5,            // centered on 15 FT pathway
          0.6,             // 6 FT width
          0.8,             // 8 FT height
          true,            // isFacingZ = true (transverse wall in YZ plane)
          null,
          0x0284c7         // modern commercial cyan/blue
        );
        scene.add(gateIndoor);

        // Overhead entrance canopy & header trim over the Indoor Arena Door
        var canopyMat = new THREE.MeshStandardMaterial({ color: 0x0284c7, roughness: 0.3, metalness: 0.7 });
        var canopyMesh = new THREE.Mesh(new THREE.BoxGeometry(0.22, 0.04, 0.75), canopyMat);
        canopyMesh.position.set(indoorStartRelX - 0.11, 0.84, -4.5);
        scene.add(canopyMesh);

        // NOTE: The green door at (x = -4.2, z = -3.75) inside the red circle has been REMOVED as requested by user!

        // 3. EMERGENCY EXIT DOOR (4 FT x 7 FT) on Rear Wall (X = structEndX = +8.5)
        var gateExit = render3DTubularGate(
          structEndX, // +8.5 (170 FT)
          0.0,        // centered at Z = 0
          0.4,        // 4 FT width
          0.7,        // 7 FT height
          true,       // isFacingZ = true
          null,
          0xeab308    // safety yellow
        );
        scene.add(gateExit);
      }
    } else if (showGates && resolvedGates.length > 0) {
      resolvedGates.forEach(function(g) {
        var gx = g.x_ft * 0.1;
        var gz = g.z_ft * 0.1;
        var gw = (g.width_ft || 4.0) * 0.1;
        var gh = (g.height_ft || 7.0) * 0.1;
        var isSide = (g.wall === 'right_length' || g.wall === 'left_length');
        var colorHex = g.color ? parseInt(g.color.replace('#', '0x')) : 0xdc2626;

        var gateMesh = render3DTubularGate(
          gx,
          gz,
          gw,
          gh,
          !isSide, // isFacingZ: false for length walls (XY plane), true for width walls (YZ plane)
          null,    // No floating signboard badge!
          colorHex
        );
        scene.add(gateMesh);
      });
    }

    // ==========================================
    // Real HDPE Ball-Stop Sports Netting
    // (Authentic Square Mesh with Knotted Intersections)
    // ==========================================
    if (showNetting) {
      var sideTex = createAuthenticSportsNetTexture(false);
      sideTex.anisotropy = maxAniso;
      sideTex.repeat.set(Math.max(12, Math.round(L_ft * 0.85)), Math.max(6, Math.round(H_ft * 0.85)));

      var sideTexZ = createAuthenticSportsNetTexture(false);
      sideTexZ.anisotropy = maxAniso;
      sideTexZ.repeat.set(Math.max(12, Math.round(W_ft * 0.85)), Math.max(6, Math.round(H_ft * 0.85)));

      var netMatX = new THREE.MeshBasicMaterial({
        map: sideTex,
        transparent: true,
        alphaTest: 0.15,
        opacity: 0.95,
        depthWrite: false,
        side: THREE.FrontSide
      });

      var netMatZ = new THREE.MeshBasicMaterial({
        map: sideTexZ,
        transparent: true,
        alphaTest: 0.15,
        opacity: 0.95,
        depthWrite: false,
        side: THREE.FrontSide
      });

      // Reinforced 8mm border rope material
      var ropeMat = new THREE.MeshStandardMaterial({ color: 0x0f172a, roughness: 0.5 });

      var netL = is185x60 ? arenaLen3D : L;
      var netMidX = is185x60 ? arenaMidX : 0;
      var netEndX = is185x60 ? arenaEndRelX : halfL;

      if (sport === 'multisport_complex_105x170' || (L_ft === 170 && W_ft === 105)) {
        // Enclose ONLY the 50 FT × 90 FT Outdoor Box Cricket Arena up to 30 FT (3.0 units) Height
        // The Indoor Badminton & Pickleball complex has full 35 FT GI sheet wall cladding!
        var cricStartX = -halfL + (18 * 0.1); // -6.7 (starts after 18 FT parking)
        var cricEndX   = -halfL + (68 * 0.1); // -1.7 (ends at indoor hall front partition)
        var cricLen3D  = 5.0; // 50 FT
        var cricMidX   = (cricStartX + cricEndX) / 2;
        var cricStartZ = -halfW + (15 * 0.1); // -3.75 (along 15 FT pathway boundary)
        var cricEndZ   = halfW;               // +5.25 (outer perimeter boundary)
        var cricWid3D  = cricEndZ - cricStartZ; // 9.0 (90 FT)
        var cricMidZ   = (cricStartZ + cricEndZ) / 2;
        var cricH      = 3.0; // 30 FT clear netting height

        var cricNetMatX = new THREE.MeshBasicMaterial({ map: sideTex, transparent: true, alphaTest: 0.15, opacity: 0.95, side: THREE.DoubleSide });
        var cricNetMatZ = new THREE.MeshBasicMaterial({ map: sideTexZ, transparent: true, alphaTest: 0.15, opacity: 0.95, side: THREE.DoubleSide });

        // Left boundary net (facing 15 FT connecting pathway)
        var nCricPath = new THREE.Mesh(new THREE.PlaneGeometry(cricLen3D, cricH), cricNetMatX);
        nCricPath.position.set(cricMidX, cricH / 2, cricStartZ);
        scene.add(nCricPath);

        // Right boundary net
        var nCricRight = new THREE.Mesh(new THREE.PlaneGeometry(cricLen3D, cricH), cricNetMatX);
        nCricRight.position.set(cricMidX, cricH / 2, cricEndZ);
        scene.add(nCricRight);

        // Front boundary net (facing parking)
        var nCricFront = new THREE.Mesh(new THREE.PlaneGeometry(cricWid3D, cricH), cricNetMatZ);
        nCricFront.rotation.y = Math.PI / 2;
        nCricFront.position.set(cricStartX, cricH / 2, cricMidZ);
        scene.add(nCricFront);

        // Back boundary net (separating cricket from indoor PEB hall)
        var nCricBack = new THREE.Mesh(new THREE.PlaneGeometry(cricWid3D, cricH), cricNetMatZ);
        nCricBack.rotation.y = -Math.PI / 2;
        nCricBack.position.set(cricEndX, cricH / 2, cricMidZ);
        scene.add(nCricBack);
      } else {
        if (is185x60) {
          // Enclose strictly Zone A Cricket Ground (0 to 100 FT, x: arenaStartRelX to z1EndX)
          // Zone B (100 to 145 FT) has full 0.50mm PPGL GI wall cladding on all 4 sides; Zone C has NO structure.
          var z1Len = 10.0; // 100 FT
          var z1MidX = arenaStartRelX + z1Len / 2; // -4.25
          var z1NetMatX = new THREE.MeshBasicMaterial({ map: sideTex, transparent: true, alphaTest: 0.15, opacity: 0.95, side: THREE.DoubleSide });
          var z1NetMatZ = new THREE.MeshBasicMaterial({ map: sideTexZ, transparent: true, alphaTest: 0.15, opacity: 0.95, side: THREE.DoubleSide });

          // Left Sideline Net (North, z = -halfW)
          var nLeft = new THREE.Mesh(new THREE.PlaneGeometry(z1Len, H), z1NetMatX);
          nLeft.position.set(z1MidX, H / 2, -halfW);
          scene.add(nLeft);
          scene.add(createPipe([arenaStartRelX, 0, -halfW], [z1EndX, 0, -halfW], 0.01, ropeMat));
          scene.add(createPipe([arenaStartRelX, H, -halfW], [z1EndX, H, -halfW], 0.01, ropeMat));

          // Right Sideline Net (South, z = halfW)
          var nRight = new THREE.Mesh(new THREE.PlaneGeometry(z1Len, H), z1NetMatX);
          nRight.position.set(z1MidX, H / 2, halfW);
          scene.add(nRight);
          scene.add(createPipe([arenaStartRelX, 0, halfW], [z1EndX, 0, halfW], 0.01, ropeMat));
          scene.add(createPipe([arenaStartRelX, H, halfW], [z1EndX, H, halfW], 0.01, ropeMat));

          // West Back Wall Net (at 0 FT = arenaStartRelX)
          var nWest = new THREE.Mesh(new THREE.PlaneGeometry(W, H), z1NetMatZ);
          nWest.rotation.y = Math.PI / 2;
          nWest.position.set(arenaStartRelX, H / 2, 0);
          scene.add(nWest);
        } else {
          // Wall planes oriented inward towards court center (FrontSide culls near walls for unobstructed turf visibility)
          if (!isLeftOpen) {
            var nLeft = new THREE.Mesh(new THREE.PlaneGeometry(netL, H), netMatX);
            nLeft.position.set(netMidX, H / 2, -halfW);
            scene.add(nLeft);
            scene.add(createPipe([-halfL, 0, -halfW], [netEndX, 0, -halfW], 0.01, ropeMat));
            scene.add(createPipe([-halfL, H, -halfW], [netEndX, H, -halfW], 0.01, ropeMat));
          }
          if (!isRightOpen) {
            var nRight = new THREE.Mesh(new THREE.PlaneGeometry(netL, H), netMatX);
            nRight.rotation.y = Math.PI;
            nRight.position.set(netMidX, H / 2, halfW);
            scene.add(nRight);
            scene.add(createPipe([-halfL, 0, halfW], [netEndX, 0, halfW], 0.01, ropeMat));
            scene.add(createPipe([-halfL, H, halfW], [netEndX, H, halfW], 0.01, ropeMat));
          }
          if (!isFrontOpen) {
            var nFront = new THREE.Mesh(new THREE.PlaneGeometry(W, H), netMatZ);
            nFront.rotation.y = Math.PI / 2;
            nFront.position.set(-halfL, H / 2, 0);
            scene.add(nFront);
          }
          if (!isBackOpen) {
            var nBack = new THREE.Mesh(new THREE.PlaneGeometry(W, H), netMatZ);
            nBack.rotation.y = -Math.PI / 2;
            nBack.position.set(netEndX, H / 2, 0);
            scene.add(nBack);
          }
        }
      }

      
    // ==========================================
    // 16,000 SFT MEGA SPORTS ARENA (105×170): 3D GI METAL SHEET ROOF OVER INDOOR HALL
    // Exact-Geometry Pitched Roof: Eave H = 35 FT (3.5 units), Apex Ridge H = 38.5 FT (3.85 units)
    // ==========================================
        // ==========================================
    // ==========================================
    // 185 FT × 60 FT MULTI-SPORT ARENA:
    // ZONE A (0 to 100 FT): OPEN-TOP 1.5mm 40mm MESH (WIRE ROPE GRID — ZERO TOP STEEL / ZERO GI SHEETS)
    // ZONE B (100 to 145 FT): 0.50mm PPGL METAL ROOF + 0.50mm GI WALL CLADDING (ALL 4 SIDES — NO SHADE NET)
    // ZONE C (145 to 185 FT): ZERO STEEL STRUCTURE! OPEN PARKING & APPROACH OUTSIDE STRUCTURE
    // ==========================================
    if (sport === 'multisport_arena_complex_185x60' || (L_ft === 185 && W_ft === 60)) {
      var eaveH = 3.0; // 30 FT eave clear height
      var apexH = 3.4; // 34 FT peak apex height (1:7 pitch)
      var z2StartX = arenaStartRelX + 10.0; // +0.75 units (100 FT mark)
      var z2EndX   = sportsEndRelX;         // +5.25 units (145 FT mark)
      var z2Len    = z2EndX - z2StartX;     // 4.5 units (45 FT)

      megaRoofGroup = new THREE.Group();
      megaRoofGroup.name = 'megaRoofGroup185';

      var columnMat = new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.3, metalness: 0.7 });

      // ───────────────────────────────────────────────────────────────────────
      // PART A: ZONE A CRICKET TOP (0 to 100 FT) — 1.5mm 40mm OVERHEAD MESH
      // Wire rope tension cable grid (NO steel rafters, NO purlins, NO GI sheets)
      // ───────────────────────────────────────────────────────────────────────
      var cableMat = new THREE.MeshStandardMaterial({ color: 0x64748b, roughness: 0.4, metalness: 0.8 });
      // Transverse tension wire ropes at every frame line (0 to 100 FT)
      var z1Frames = [
        arenaStartRelX,
        arenaStartRelX + (10.0 / 6) * 1,
        arenaStartRelX + (10.0 / 6) * 2,
        arenaStartRelX + (10.0 / 6) * 3,
        arenaStartRelX + (10.0 / 6) * 4,
        arenaStartRelX + (10.0 / 6) * 5,
        z1EndX
      ];
      for (var zi = 0; zi < z1Frames.length; zi++) {
        var zfx = z1Frames[zi];
        megaRoofGroup.add(createPipe([zfx, eaveH, -halfW], [zfx, eaveH, halfW], 0.012, cableMat));
      }
      // Longitudinal tension wire ropes across width (every 10 FT)
      for (var cwz = -halfW + 1.0; cwz < halfW; cwz += 1.0) {
        megaRoofGroup.add(createPipe([arenaStartRelX, eaveH, cwz], [z1EndX, eaveH, cwz], 0.012, cableMat));
      }

      // Overhead 1.5mm 40mm mesh top netting plane over Zone A (100 FT x 60 FT)
      var topMeshTex = createAuthenticSportsNetTexture(true);
      topMeshTex.wrapS = THREE.RepeatWrapping;
      topMeshTex.wrapT = THREE.RepeatWrapping;
      topMeshTex.repeat.set(25, 15);
      var topMeshMat = new THREE.MeshBasicMaterial({
        map: topMeshTex,
        transparent: true,
        alphaTest: 0.1,
        opacity: 0.85,
        side: THREE.DoubleSide,
        depthWrite: false
      });
      var topNetPlane = new THREE.Mesh(new THREE.PlaneGeometry(10.0, W), topMeshMat);
      topNetPlane.rotation.x = Math.PI / 2;
      topNetPlane.position.set(arenaStartRelX + 5.0, eaveH, 0);
      megaRoofGroup.add(topNetPlane);

      // ───────────────────────────────────────────────────────────────────────
      // PART B: ZONE B BADMINTON ROOF (100 to 145 FT = 45 FT SPAN ONLY)
      // Steel gable roof trusses, secondary purlins, ridge cap & 0.50mm PPGL sheets
      // ───────────────────────────────────────────────────────────────────────
      // Gable Roof Trusses (5 for 160 FT, 4 for 145 FT)
      var z2TrussXs = is160Builtup ? [
        z2StartX,
        z2StartX + 1.5,
        z2StartX + 3.0,
        z2StartX + 4.5,
        z2EndX
      ] : [
        z2StartX,
        z2StartX + 1.5,
        z2StartX + 3.0,
        z2EndX
      ];
      for (var ti = 0; ti < z2TrussXs.length; ti++) {
        var tx = z2TrussXs[ti];
        megaRoofGroup.add(createPipe([tx, eaveH, -halfW], [tx, apexH, 0], 0.045, columnMat));
        megaRoofGroup.add(createPipe([tx, apexH, 0], [tx, eaveH, halfW], 0.045, columnMat));
        megaRoofGroup.add(createPipe([tx, eaveH, -halfW], [tx, eaveH, halfW], 0.035, columnMat));
      }

      // 0.50mm PPGL Corrugated Metal Roof Sheets (STRICTLY over 45 FT Badminton Zone!)
      var leftSlopeGeo = new THREE.BufferGeometry();
      var leftVertices = new Float32Array([
        z2StartX, eaveH, -halfW,
        z2EndX, apexH, 0,
        z2EndX, eaveH, -halfW,

        z2StartX, eaveH, -halfW,
        z2StartX, apexH, 0,
        z2EndX, apexH, 0
      ]);
      leftSlopeGeo.setAttribute('position', new THREE.BufferAttribute(leftVertices, 3));
      leftSlopeGeo.computeVertexNormals();

      var rightSlopeGeo = new THREE.BufferGeometry();
      var rightVertices = new Float32Array([
        z2StartX, apexH, 0,
        z2EndX, eaveH, halfW,
        z2EndX, apexH, 0,

        z2StartX, apexH, 0,
        z2StartX, eaveH, halfW,
        z2EndX, eaveH, halfW
      ]);
      rightSlopeGeo.setAttribute('position', new THREE.BufferAttribute(rightVertices, 3));
      rightSlopeGeo.computeVertexNormals();

      var roofSheetMat = new THREE.MeshStandardMaterial({
        color: 0x1e3a8a, // PPGL Royal Blue Galvalume Profile Sheets
        roughness: 0.35,
        metalness: 0.65,
        side: THREE.DoubleSide,
        transparent: true,
        opacity: 0.70
      });
      megaRoofGroup.add(new THREE.Mesh(leftSlopeGeo, roofSheetMat));
      megaRoofGroup.add(new THREE.Mesh(rightSlopeGeo, roofSheetMat));

      // Central Steel Ridge Cap along Apex of 45 FT Badminton Roof
      var ridgeMat = new THREE.MeshStandardMaterial({ color: 0x0f172a, roughness: 0.3, metalness: 0.8 });
      megaRoofGroup.add(createPipe([z2StartX, apexH + 0.01, 0], [z2EndX, apexH + 0.01, 0], 0.05, ridgeMat));

      // Roof Purlins on Badminton 45 FT Roof
      var purlinMat = new THREE.LineBasicMaterial({ color: 0x475569, linewidth: 2 });
      for (var pz = -halfW + 0.8; pz < 0; pz += 0.8) {
        var py = eaveH + ((apexH - eaveH) * (1 - Math.abs(pz) / halfW));
        var pGeoL = new THREE.BufferGeometry().setFromPoints([
          new THREE.Vector3(z2StartX, py + 0.005, pz),
          new THREE.Vector3(z2EndX, py + 0.005, pz)
        ]);
        megaRoofGroup.add(new THREE.Line(pGeoL, purlinMat));

        var pGeoR = new THREE.BufferGeometry().setFromPoints([
          new THREE.Vector3(z2StartX, py + 0.005, -pz),
          new THREE.Vector3(z2EndX, py + 0.005, -pz)
        ]);
        megaRoofGroup.add(new THREE.Line(pGeoR, purlinMat));
      }

      // Stainless Steel Turbo Roof Ventilators (3 Nos along 45 FT Badminton Ridge)
      for (var vi = 1; vi <= 3; vi++) {
        var ventX = z2StartX + (vi * (z2Len / 4));
        var ventBase = new THREE.CylinderGeometry(0.08, 0.11, 0.10, 16);
        var ventMat = new THREE.MeshStandardMaterial({ color: 0xe2e8f0, metalness: 0.9, roughness: 0.15 });
        var ventMesh = new THREE.Mesh(ventBase, ventMat);
        ventMesh.position.set(ventX, apexH + 0.06, 0);
        megaRoofGroup.add(ventMesh);
      }

      // Eaves Gutters & Rainwater Downpipes (Both 45 FT eaves lengths)
      var gutterMat = new THREE.MeshStandardMaterial({ color: 0x475569, metalness: 0.7, roughness: 0.3 });
      megaRoofGroup.add(createPipe([z2StartX - 0.05, eaveH - 0.02, -halfW], [z2EndX + 0.05, eaveH - 0.02, -halfW], 0.035, gutterMat));
      megaRoofGroup.add(createPipe([z2StartX - 0.05, eaveH - 0.02, halfW], [z2EndX + 0.05, eaveH - 0.02, halfW], 0.035, gutterMat));
      // Downpipes at 4 corners of badminton hall
      megaRoofGroup.add(createPipe([z2StartX, eaveH - 0.02, -halfW], [z2StartX, 0, -halfW], 0.025, gutterMat));
      megaRoofGroup.add(createPipe([z2EndX, eaveH - 0.02, -halfW], [z2EndX, 0, -halfW], 0.025, gutterMat));
      megaRoofGroup.add(createPipe([z2StartX, eaveH - 0.02, halfW], [z2StartX, 0, halfW], 0.025, gutterMat));
      megaRoofGroup.add(createPipe([z2EndX, eaveH - 0.02, halfW], [z2EndX, 0, halfW], 0.025, gutterMat));

      // ───────────────────────────────────────────────────────────────────────
      // PART C: ZONE B WALLS — 0.50mm PPGL GI CORRUGATED METAL WALL CLADDING (ALL 4 SIDES)
      // REPLACING SHADE NET ENTIRELY WITH 100% WINDPROOF METAL PROFILE SHEETS!
      // ───────────────────────────────────────────────────────────────────────
      var wallH = 0.45; // 4.5 FT solid base wall height
      var netH  = eaveH - wallH; // 2.55 units (25.5 FT of wall cladding)
      var netCenterY = wallH + netH / 2;

      // Base wall material (slate dark grey masonry plinth)
      var baseWallMat = new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.85, metalness: 0.15 });
      var wallCopingMat = new THREE.MeshStandardMaterial({ color: 0x475569, roughness: 0.7, metalness: 0.2 });

      // Corrugated Metal Wall Cladding Material (0.50mm PPGL Royal Blue / Industrial Profile)
      var wallCladdingMat = new THREE.MeshStandardMaterial({
        color: 0x1e3a8a, // PPGL Royal Blue Corrugated Profile Wall Cladding
        roughness: 0.4,
        metalness: 0.6,
        side: THREE.DoubleSide
      });

      // 1. 4.5 FT Solid Base Wall on all 4 sides
      // North Wall Base
      var wallN = new THREE.Mesh(new THREE.BoxGeometry(z2Len, wallH, 0.08), baseWallMat);
      wallN.position.set(z2StartX + z2Len / 2, wallH / 2, -halfW);
      scene.add(wallN);
      var capN = new THREE.Mesh(new THREE.BoxGeometry(z2Len + 0.02, 0.02, 0.10), wallCopingMat);
      capN.position.set(z2StartX + z2Len / 2, wallH + 0.01, -halfW);
      scene.add(capN);

      // South Wall Base
      var wallS = new THREE.Mesh(new THREE.BoxGeometry(z2Len, wallH, 0.08), baseWallMat);
      wallS.position.set(z2StartX + z2Len / 2, wallH / 2, halfW);
      scene.add(wallS);
      var capS = new THREE.Mesh(new THREE.BoxGeometry(z2Len + 0.02, 0.02, 0.10), wallCopingMat);
      capS.position.set(z2StartX + z2Len / 2, wallH + 0.01, halfW);
      scene.add(capS);

      // West Wall Base (at 100 FT partition, leaving central doorway at z = 0.0)
      var wallW_N = new THREE.Mesh(new THREE.BoxGeometry(0.08, wallH, 2.8), baseWallMat);
      wallW_N.position.set(z2StartX, wallH / 2, -1.6);
      scene.add(wallW_N);
      var capW_N = new THREE.Mesh(new THREE.BoxGeometry(0.10, 0.02, 2.82), wallCopingMat);
      capW_N.position.set(z2StartX, wallH + 0.01, -1.6);
      scene.add(capW_N);
      var wallW_S = new THREE.Mesh(new THREE.BoxGeometry(0.08, wallH, 2.8), baseWallMat);
      wallW_S.position.set(z2StartX, wallH / 2, 1.6);
      scene.add(wallW_S);
      var capW_S = new THREE.Mesh(new THREE.BoxGeometry(0.10, 0.02, 2.82), wallCopingMat);
      capW_S.position.set(z2StartX, wallH + 0.01, 1.6);
      scene.add(capW_S);

      // East Front Wall Base (at z2EndX)
      // North base wall from z = -3.0 to -0.3 (2.7 units)
      var wallE_N = new THREE.Mesh(new THREE.BoxGeometry(0.08, wallH, 2.7), baseWallMat);
      wallE_N.position.set(z2EndX, wallH / 2, -1.65);
      scene.add(wallE_N);
      var capE_N = new THREE.Mesh(new THREE.BoxGeometry(0.10, 0.02, 2.72), wallCopingMat);
      capE_N.position.set(z2EndX, wallH + 0.01, -1.65);
      scene.add(capE_N);

      // South base wall from z = +0.3 to +3.0 (2.7 units)
      var wallE_S = new THREE.Mesh(new THREE.BoxGeometry(0.08, wallH, 2.7), baseWallMat);
      wallE_S.position.set(z2EndX, wallH / 2, 1.65);
      scene.add(wallE_S);
      var capE_S = new THREE.Mesh(new THREE.BoxGeometry(0.10, 0.02, 2.72), wallCopingMat);
      capE_S.position.set(z2EndX, wallH + 0.01, 1.65);
      scene.add(capE_S);

      // 2. 0.50mm PPGL GI Metal Wall Cladding Panels (All 4 Sides — replacing shade net!)
      // Side 1: West Partition Wall at 100 FT (from 4.5 FT to 30 FT)
      var cladW_A = new THREE.Mesh(new THREE.PlaneGeometry(2.8, netH), wallCladdingMat);
      cladW_A.rotation.y = Math.PI / 2;
      cladW_A.position.set(z2StartX, netCenterY, -1.6);
      scene.add(cladW_A);

      var cladW_B = new THREE.Mesh(new THREE.PlaneGeometry(2.8, netH), wallCladdingMat);
      cladW_B.rotation.y = Math.PI / 2;
      cladW_B.position.set(z2StartX, netCenterY, 1.6);
      scene.add(cladW_B);

      // Transom cladding above Inside Gate (7 FT to 30 FT)
      var cladW_Top = new THREE.Mesh(new THREE.PlaneGeometry(0.4, eaveH - 0.7), wallCladdingMat);
      cladW_Top.rotation.y = Math.PI / 2;
      cladW_Top.position.set(z2StartX, 0.7 + (eaveH - 0.7) / 2, 0.0);
      scene.add(cladW_Top);

      // Gable triangle cladding on West partition (30 FT to 34 FT apex)
      var gableW_Geo = new THREE.BufferGeometry();
      var gableW_Verts = new Float32Array([
        z2StartX, eaveH, -halfW,
        z2StartX, apexH, 0,
        z2StartX, eaveH, halfW
      ]);
      gableW_Geo.setAttribute('position', new THREE.BufferAttribute(gableW_Verts, 3));
      gableW_Geo.computeVertexNormals();
      scene.add(new THREE.Mesh(gableW_Geo, wallCladdingMat));

      // Side 2: East Front Wall at z2EndX (from 4.5 FT to 30 FT)
      // North Cladding from z = -3.0 to -0.3 (2.7 units)
      var cladE_N = new THREE.Mesh(new THREE.PlaneGeometry(2.7, netH), wallCladdingMat);
      cladE_N.rotation.y = Math.PI / 2;
      cladE_N.position.set(z2EndX, netCenterY, -1.65);
      scene.add(cladE_N);

      // South Cladding from z = +0.3 to +3.0 (2.7 units)
      var cladE_S = new THREE.Mesh(new THREE.PlaneGeometry(2.7, netH), wallCladdingMat);
      cladE_S.rotation.y = Math.PI / 2;
      cladE_S.position.set(z2EndX, netCenterY, 1.65);
      scene.add(cladE_S);

      // Transom Cladding directly above the center entrance gate (z = 0.0) from 7 FT to 30 FT
      var cladE_Top = new THREE.Mesh(new THREE.PlaneGeometry(0.6, eaveH - 0.7), wallCladdingMat);
      cladE_Top.rotation.y = Math.PI / 2;
      cladE_Top.position.set(z2EndX, 0.7 + (eaveH - 0.7) / 2, 0.0);
      scene.add(cladE_Top);

      // Gable triangle cladding on East front wall (30 FT to 34 FT apex)
      var gableE_Geo = new THREE.BufferGeometry();
      var gableE_Verts = new Float32Array([
        z2EndX, eaveH, halfW,
        z2EndX, apexH, 0,
        z2EndX, eaveH, -halfW
      ]);
      gableE_Geo.setAttribute('position', new THREE.BufferAttribute(gableE_Verts, 3));
      gableE_Geo.computeVertexNormals();
      scene.add(new THREE.Mesh(gableE_Geo, wallCladdingMat));

      // Side 3: North Outer Sideline Wall (full 45 FT length from 4.5 FT to 30 FT)
      var cladN = new THREE.Mesh(new THREE.PlaneGeometry(z2Len, netH), wallCladdingMat);
      cladN.position.set(z2StartX + z2Len / 2, netCenterY, -halfW);
      scene.add(cladN);

      // Side 4: South Outer Sideline Wall (full 45 FT length from 4.5 FT to 30 FT)
      var cladS = new THREE.Mesh(new THREE.PlaneGeometry(z2Len, netH), wallCladdingMat);
      cladS.position.set(z2StartX + z2Len / 2, netCenterY, halfW);
      scene.add(cladS);

      // Horizontal Wall Girt / Runner lines across all 4 walls (@ 5 FT c/c = 5 tiers)
      for (var gy = wallH + 0.5; gy < eaveH; gy += 0.5) {
        // North wall girt
        scene.add(createPipe([z2StartX, gy, -halfW], [z2EndX, gy, -halfW], 0.015, steelMat));
        // South wall girt
        scene.add(createPipe([z2StartX, gy, halfW], [z2EndX, gy, halfW], 0.015, steelMat));
        // West wall girt
        scene.add(createPipe([z2StartX, gy, -halfW], [z2StartX, gy, halfW], 0.015, steelMat));
        // East wall girt
        scene.add(createPipe([z2EndX, gy, -halfW], [z2EndX, gy, halfW], 0.015, steelMat));
      }

      // ───────────────────────────────────────────────────────────────────────
      // PART D: ZONE C — REMAINING OPEN SPACE (145 to 185 FT)
      // Outside metal structure — zero construction / zero steel structure!
      // ───────────────────────────────────────────────────────────────────────
      var z3Len = arenaEndRelX - sportsEndRelX; // 4.0 units (40 FT)
      var z3MidX = sportsEndRelX + z3Len / 2; // +7.25 units

      // Clean flat ground level open space (NO gates, NO posts, NO lines, NO structure)
      var openGroundGeo = new THREE.PlaneGeometry(z3Len, W);
      var openGroundMat = new THREE.MeshStandardMaterial({
        color: 0xe2e8f0,
        roughness: 0.95,
        metalness: 0.05
      });
      var openGround = new THREE.Mesh(openGroundGeo, openGroundMat);
      openGround.rotation.x = -Math.PI / 2;
      openGround.position.set(z3MidX, 0.002, 0);
      scene.add(openGround);

      if (typeof config.show_roof_sheets !== 'undefined') {
        megaRoofGroup.visible = (config.show_roof_sheets !== false);
      }

      scene.add(megaRoofGroup);
    }

    if (sport === 'multisport_complex_105x170' || (L_ft === 170 && W_ft === 105)) {
      var indoorStartRelX = -halfL + (68 * 0.1); // Starts at 68 FT (-1.7 units)
      var indoorLen3D = halfL - indoorStartRelX;  // 102 FT (10.2 units)
      var eaveH = 3.5;  // 35 FT eave clear height
      var apexH = 3.85; // 38.5 FT apex ridge height

      megaRoofGroup = new THREE.Group();
      megaRoofGroup.name = 'megaRoofGroup';

      // 1. Solid Base Perimeter Brickwork Walls (10 FT Height = 1.0 unit)
      var brickMat = new THREE.MeshStandardMaterial({ color: 0x475569, roughness: 0.9, metalness: 0.1 });
      scene.add(createPipe([indoorStartRelX, 0.5, -halfW], [halfL, 0.5, -halfW], 0.08, brickMat));
      scene.add(createPipe([indoorStartRelX, 0.5, halfW], [halfL, 0.5, halfW], 0.08, brickMat));
      scene.add(createPipe([halfL, 0.5, -halfW], [halfL, 0.5, halfW], 0.08, brickMat));
      scene.add(createPipe([indoorStartRelX, 0.5, -halfW], [indoorStartRelX, 0.5, halfW], 0.08, brickMat));

      // 2. PEB Portal Frame Columns (35 FT Height) along Perimeter
      var columnMat = new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.3, metalness: 0.7 });
      for (var cx = indoorStartRelX; cx <= halfL + 0.01; cx += (indoorLen3D / 6)) {
        scene.add(createPipe([cx, 0, -halfW], [cx, eaveH, -halfW], 0.06, columnMat));
        scene.add(createPipe([cx, 0, halfW], [cx, eaveH, halfW], 0.06, columnMat));
        megaRoofGroup.add(createPipe([cx, eaveH, -halfW], [cx, apexH, 0], 0.045, columnMat));
        megaRoofGroup.add(createPipe([cx, apexH, 0], [cx, eaveH, halfW], 0.045, columnMat));
        megaRoofGroup.add(createPipe([cx, eaveH, -halfW], [cx, eaveH, halfW], 0.035, columnMat));
      }

      // 3. Exact 3D Pitched GI Profile Roof Geometry
      // Left Slope (Z: -halfW to 0, rising from eaveH to apexH)
      var leftSlopeGeo = new THREE.BufferGeometry();
      var leftVertices = new Float32Array([
        indoorStartRelX, eaveH, -halfW,
        halfL, eaveH, -halfW,
        halfL, apexH, 0,
        indoorStartRelX, eaveH, -halfW,
        halfL, apexH, 0,
        indoorStartRelX, apexH, 0
      ]);
      leftSlopeGeo.setAttribute('position', new THREE.BufferAttribute(leftVertices, 3));
      leftSlopeGeo.computeVertexNormals();

      // Right Slope (Z: 0 to +halfW, falling from apexH to eaveH)
      var rightSlopeGeo = new THREE.BufferGeometry();
      var rightVertices = new Float32Array([
        indoorStartRelX, apexH, 0,
        halfL, apexH, 0,
        halfL, eaveH, halfW,
        indoorStartRelX, apexH, 0,
        halfL, eaveH, halfW,
        indoorStartRelX, eaveH, halfW
      ]);
      rightSlopeGeo.setAttribute('position', new THREE.BufferAttribute(rightVertices, 3));
      rightSlopeGeo.computeVertexNormals();

      var roofSheetMat = new THREE.MeshStandardMaterial({
        color: 0x334155,
        roughness: 0.35,
        metalness: 0.65,
        side: THREE.DoubleSide,
        transparent: true,
        opacity: 0.68
      });

      var leftRoofMesh = new THREE.Mesh(leftSlopeGeo, roofSheetMat);
      var rightRoofMesh = new THREE.Mesh(rightSlopeGeo, roofSheetMat);
      megaRoofGroup.add(leftRoofMesh);
      megaRoofGroup.add(rightRoofMesh);

      // 4. Longitudinal Purlins & Corrugated Profile Ribs on Roof
      var ribMat = new THREE.LineBasicMaterial({ color: 0x94a3b8, linewidth: 2 });
      var purlinMat = new THREE.LineBasicMaterial({ color: 0x475569, linewidth: 2 });

      for (var pz = -4.2; pz < 0; pz += 1.05) {
        var py = eaveH + ((apexH - eaveH) * (1 - Math.abs(pz) / halfW));
        var purlinGeoL = new THREE.BufferGeometry().setFromPoints([
          new THREE.Vector3(indoorStartRelX, py + 0.005, pz),
          new THREE.Vector3(halfL, py + 0.005, pz)
        ]);
        megaRoofGroup.add(new THREE.Line(purlinGeoL, purlinMat));

        var purlinGeoR = new THREE.BufferGeometry().setFromPoints([
          new THREE.Vector3(indoorStartRelX, py + 0.005, -pz),
          new THREE.Vector3(halfL, py + 0.005, -pz)
        ]);
        megaRoofGroup.add(new THREE.Line(purlinGeoR, purlinMat));
      }

      for (var rx = indoorStartRelX; rx <= halfL; rx += 0.4) {
        var ribGeo = new THREE.BufferGeometry().setFromPoints([
          new THREE.Vector3(rx, eaveH + 0.008, -halfW),
          new THREE.Vector3(rx, apexH + 0.008, 0),
          new THREE.Vector3(rx, eaveH + 0.008, halfW)
        ]);
        megaRoofGroup.add(new THREE.Line(ribGeo, ribMat));
      }

      // 5. Central Steel Ridge Cap along Apex
      var ridgeMat = new THREE.MeshStandardMaterial({ color: 0x0f172a, roughness: 0.3, metalness: 0.8 });
      megaRoofGroup.add(createPipe([indoorStartRelX, apexH + 0.01, 0], [halfL, apexH + 0.01, 0], 0.05, ridgeMat));

      // 6. 8 Stainless Steel Rotating Turbo Ventilators along Ridge
      for (var vi = 1; vi <= 8; vi++) {
        var ventX = indoorStartRelX + (vi * (indoorLen3D / 9));
        var ventBase = new THREE.CylinderGeometry(0.09, 0.12, 0.12, 16);
        var ventMat = new THREE.MeshStandardMaterial({ color: 0xe2e8f0, metalness: 0.9, roughness: 0.15 });
        var ventMesh = new THREE.Mesh(ventBase, ventMat);
        ventMesh.position.set(ventX, apexH + 0.07, 0);
        megaRoofGroup.add(ventMesh);

        var cowlGeo = new THREE.SphereGeometry(0.11, 16, 8);
        var cowlMesh = new THREE.Mesh(cowlGeo, ventMat);
        cowlMesh.position.set(ventX, apexH + 0.14, 0);
        megaRoofGroup.add(cowlMesh);
      }

      // Add roof group to scene
      scene.add(megaRoofGroup);
      // 7. 3D AMENITIES SUITE PARTITION WALLS (Toilets, Office, Lobby — 44 FT x 47 FT)
      var amenWallMat = new THREE.MeshStandardMaterial({ color: 0xcfd8dc, roughness: 0.7, metalness: 0.1 });
      var amenH3D = 1.0; // 10 FT wall height
      // East Demising Wall separating Amenities Suite from Courts (along z = -0.55, from x = indoorStartRelX to 2.7)
      scene.add(createPipe([indoorStartRelX, amenH3D / 2, -0.55], [2.7, amenH3D / 2, -0.55], 0.04, amenWallMat));
      // South Demising Wall separating Amenities Suite from rear courts (along x = 2.7, from z = -halfW to -0.55)
      scene.add(createPipe([2.7, amenH3D / 2, -halfW], [2.7, amenH3D / 2, -0.55], 0.04, amenWallMat));
      // Internal Dividing Wall separating Front Zone (Lobby & Office) from Inner Zone (Washrooms) along z = -2.9
      scene.add(createPipe([indoorStartRelX, amenH3D / 2, -2.9], [2.7, amenH3D / 2, -2.9], 0.03, amenWallMat));
      // Wall separating Main Lobby from Admin Office (along x = 0.5, from z = -halfW to -2.9)
      scene.add(createPipe([0.5, amenH3D / 2, -halfW], [0.5, amenH3D / 2, -2.9], 0.03, amenWallMat));
      // Wall separating Gents Washrooms from Ladies Washrooms (along x = 0.5, from z = -2.9 to -0.55)
      scene.add(createPipe([0.5, amenH3D / 2, -2.9], [0.5, amenH3D / 2, -0.55], 0.03, amenWallMat));

      // 5B. 4.5 FT Solid Brick Base Wall + 0.50mm PPGL Profile Metal Wall Cladding Sheets up to 35 FT Height
      var brickH = 0.45; // 4.5 FT solid brickwork plinth base
      var sheetH = eaveH - brickH; // 3.05 units (30.5 FT height)
      var sheetMidY = brickH + sheetH / 2; // 1.975 units

      var brickWallMat = new THREE.MeshStandardMaterial({
        color: 0x94a3b8, // Light Slate Masonry Brick Plinth
        roughness: 0.85,
        metalness: 0.15,
        side: THREE.DoubleSide
      });

      var wallSheetMat = new THREE.MeshStandardMaterial({
        color: 0x334155, // Architectural Slate Charcoal GI Profile Cladding
        roughness: 0.45,
        metalness: 0.55,
        side: THREE.DoubleSide,
        transparent: true,
        opacity: 0.70
      });

      // ── 4.5 FT SOLID BRICK BASE WALLS (Added to scene for permanent ground perimeter) ──
      // Left Base Wall (Z = -halfW)
      var leftBrickGeo = new THREE.PlaneGeometry(indoorLen3D, brickH);
      var leftBrickMesh = new THREE.Mesh(leftBrickGeo, brickWallMat);
      leftBrickMesh.position.set((indoorStartRelX + halfL) / 2, brickH / 2, -halfW);
      scene.add(leftBrickMesh);

      // Right Base Wall (Z = +halfW)
      var rightBrickGeo = new THREE.PlaneGeometry(indoorLen3D, brickH);
      var rightBrickMesh = new THREE.Mesh(rightBrickGeo, brickWallMat);
      rightBrickMesh.position.set((indoorStartRelX + halfL) / 2, brickH / 2, halfW);
      scene.add(rightBrickMesh);

      // Rear Base Wall (X = halfL)
      var rearBrickGeo = new THREE.PlaneGeometry(W, brickH);
      var rearBrickMesh = new THREE.Mesh(rearBrickGeo, brickWallMat);
      rearBrickMesh.rotation.y = -Math.PI / 2;
      rearBrickMesh.position.set(halfL, brickH / 2, 0);
      scene.add(rearBrickMesh);

      // Front Partition Base Wall (X = indoorStartRelX)
      // Open 6 FT doorway cutout at z = -4.5 (from z = -4.8 to -4.2) for the Indoor Arena Entrance Door!
      // Left base wall section: -halfW (-5.25) to -4.8 (width = 0.45)
      var frontBrickGeoL = new THREE.PlaneGeometry(0.45, brickH);
      var frontBrickMeshL = new THREE.Mesh(frontBrickGeoL, brickWallMat);
      frontBrickMeshL.rotation.y = Math.PI / 2;
      frontBrickMeshL.position.set(indoorStartRelX, brickH / 2, -5.025);
      scene.add(frontBrickMeshL);

      // Right base wall section: -4.2 to +halfW (+5.25) (width = 9.45)
      var frontBrickGeoR = new THREE.PlaneGeometry(9.45, brickH);
      var frontBrickMeshR = new THREE.Mesh(frontBrickGeoR, brickWallMat);
      frontBrickMeshR.rotation.y = Math.PI / 2;
      frontBrickMeshR.position.set(indoorStartRelX, brickH / 2, 0.525);
      scene.add(frontBrickMeshR);

      // ── 0.50mm PPGL GI WALL CLADDING SHEETS (FROM 4.5 FT TO 35 FT EAVE) (Added to megaRoofGroup) ──
      // Left Longitudinal Wall Cladding (Z = -halfW)
      var leftWallGeo = new THREE.PlaneGeometry(indoorLen3D, sheetH);
      var leftWallMesh = new THREE.Mesh(leftWallGeo, wallSheetMat);
      leftWallMesh.position.set((indoorStartRelX + halfL) / 2, sheetMidY, -halfW);
      megaRoofGroup.add(leftWallMesh);

      // Right Longitudinal Wall Cladding (Z = +halfW)
      var rightWallGeo = new THREE.PlaneGeometry(indoorLen3D, sheetH);
      var rightWallMesh = new THREE.Mesh(rightWallGeo, wallSheetMat);
      rightWallMesh.position.set((indoorStartRelX + halfL) / 2, sheetMidY, halfW);
      megaRoofGroup.add(rightWallMesh);

      // Rear End Wall Cladding (X = halfL)
      var rearWallGeo = new THREE.PlaneGeometry(W, sheetH);
      var rearWallMesh = new THREE.Mesh(rearWallGeo, wallSheetMat);
      rearWallMesh.rotation.y = -Math.PI / 2;
      rearWallMesh.position.set(halfL, sheetMidY, 0);
      megaRoofGroup.add(rearWallMesh);

      // Rear End Gable Triangle (from eaveH to apexH)
      var rearGableGeo = new THREE.BufferGeometry();
      rearGableGeo.setAttribute('position', new THREE.BufferAttribute(new Float32Array([
        halfL, eaveH, -halfW,
        halfL, apexH, 0,
        halfL, eaveH, halfW
      ]), 3));
      rearGableGeo.computeVertexNormals();
      megaRoofGroup.add(new THREE.Mesh(rearGableGeo, wallSheetMat));

      // Front Partition Wall Cladding (X = indoorStartRelX) with framed door opening at z = -4.5 (red rectangle)
      var frontWallGeoL = new THREE.PlaneGeometry(0.45, sheetH);
      var frontWallMeshL = new THREE.Mesh(frontWallGeoL, wallSheetMat);
      frontWallMeshL.rotation.y = Math.PI / 2;
      frontWallMeshL.position.set(indoorStartRelX, sheetMidY, -5.025);
      megaRoofGroup.add(frontWallMeshL);

      var overDoorH = eaveH - 0.8;
      var frontWallGeoOver = new THREE.PlaneGeometry(0.6, overDoorH);
      var frontWallMeshOver = new THREE.Mesh(frontWallGeoOver, wallSheetMat);
      frontWallMeshOver.rotation.y = Math.PI / 2;
      frontWallMeshOver.position.set(indoorStartRelX, 0.8 + overDoorH / 2, -4.5);
      megaRoofGroup.add(frontWallMeshOver);

      var frontWallGeoR = new THREE.PlaneGeometry(9.45, sheetH);
      var frontWallMeshR = new THREE.Mesh(frontWallGeoR, wallSheetMat);
      frontWallMeshR.rotation.y = Math.PI / 2;
      frontWallMeshR.position.set(indoorStartRelX, sheetMidY, 0.525);
      megaRoofGroup.add(frontWallMeshR);

      // Front Partition Gable Triangle
      var frontGableGeo = new THREE.BufferGeometry();
      frontGableGeo.setAttribute('position', new THREE.BufferAttribute(new Float32Array([
        indoorStartRelX, eaveH, -halfW,
        indoorStartRelX, eaveH, halfW,
        indoorStartRelX, apexH, 0
      ]), 3));
      frontGableGeo.computeVertexNormals();
      megaRoofGroup.add(new THREE.Mesh(frontGableGeo, wallSheetMat));

      // Horizontal Wall Girts & Vertical Corrugated Flutes (From 4.5 FT Wall Top to 35 FT Eave)
      var wallTrimMat = new THREE.LineBasicMaterial({ color: 0x64748b, linewidth: 1.5 });
      for (var gy = brickH + 0.6; gy < eaveH; gy += 0.7) {
        var girtL = new THREE.BufferGeometry().setFromPoints([
          new THREE.Vector3(indoorStartRelX, gy, -halfW - 0.005),
          new THREE.Vector3(halfL, gy, -halfW - 0.005)
        ]);
        megaRoofGroup.add(new THREE.Line(girtL, wallTrimMat));
        var girtR = new THREE.BufferGeometry().setFromPoints([
          new THREE.Vector3(indoorStartRelX, gy, halfW + 0.005),
          new THREE.Vector3(halfL, gy, halfW + 0.005)
        ]);
        megaRoofGroup.add(new THREE.Line(girtR, wallTrimMat));
      }
      for (var wx = indoorStartRelX; wx <= halfL + 0.01; wx += 0.4) {
        var fluteL = new THREE.BufferGeometry().setFromPoints([
          new THREE.Vector3(wx, brickH, -halfW - 0.005),
          new THREE.Vector3(wx, eaveH, -halfW - 0.005)
        ]);
        megaRoofGroup.add(new THREE.Line(fluteL, wallTrimMat));
        var fluteR = new THREE.BufferGeometry().setFromPoints([
          new THREE.Vector3(wx, brickH, halfW + 0.005),
          new THREE.Vector3(wx, eaveH, halfW + 0.005)
        ]);
        megaRoofGroup.add(new THREE.Line(fluteR, wallTrimMat));
      }

      // 7. 3D Physical Nets & Posts for Badminton & Pickleball Courts
      var netMat = new THREE.MeshBasicMaterial({ color: 0xdc2626 });
      var postMat = new THREE.MeshStandardMaterial({ color: 0x0f172a, metalness: 0.8 });

      // Top-Right: 2 Regulation Pickleball Nets (across X = indoorStartRelX + 7.4)
      var pklNetRelX = indoorStartRelX + 7.4;
      var pklZOffsets = [-2.8, -0.6];
      for (var pi = 0; pi < pklZOffsets.length; pi++) {
        var pz = pklZOffsets[pi];
        var pNetGeo = new THREE.BoxGeometry(0.04, 0.10, 2.0);
        var pNet = new THREE.Mesh(pNetGeo, netMat);
        pNet.position.set(pklNetRelX, 0.35, pz);
        scene.add(pNet);
        scene.add(createPipe([pklNetRelX, 0, pz - 1.0], [pklNetRelX, 0.38, pz - 1.0], 0.03, postMat));
        scene.add(createPipe([pklNetRelX, 0, pz + 1.0], [pklNetRelX, 0.38, pz + 1.0], 0.03, postMat));
      }

      // Bottom-Left: Badminton Courts 1 & 2 Nets (across X = indoorStartRelX + 2.8)
      var badNet12RelX = indoorStartRelX + 2.8;
      var badZOffsets = [0.5, 2.7];
      for (var bi = 0; bi < badZOffsets.length; bi++) {
        var cz = badZOffsets[bi];
        var bNetGeo = new THREE.BoxGeometry(0.04, 0.12, 2.0);
        var bNet = new THREE.Mesh(bNetGeo, netMat);
        bNet.position.set(badNet12RelX, 0.51, cz);
        scene.add(bNet);
        scene.add(createPipe([badNet12RelX, 0, cz - 1.0], [badNet12RelX, 0.55, cz - 1.0], 0.03, postMat));
        scene.add(createPipe([badNet12RelX, 0, cz + 1.0], [badNet12RelX, 0.55, cz + 1.0], 0.03, postMat));
      }

      // [SWAPPED] Bottom-Right: Badminton Courts 3 & 4 Nets (across X = indoorStartRelX + 7.4, swapped from amenities)
      var badNet34RelX = indoorStartRelX + 7.4;
      for (var bi2 = 0; bi2 < badZOffsets.length; bi2++) {
        var cz2 = badZOffsets[bi2];
        var bNetGeo2 = new THREE.BoxGeometry(0.04, 0.12, 2.0);
        var bNet2 = new THREE.Mesh(bNetGeo2, netMat);
        bNet2.position.set(badNet34RelX, 0.51, cz2);
        scene.add(bNet2);
        scene.add(createPipe([badNet34RelX, 0, cz2 - 1.0], [badNet34RelX, 0.55, cz2 - 1.0], 0.03, postMat));
        scene.add(createPipe([badNet34RelX, 0, cz2 + 1.0], [badNet34RelX, 0.55, cz2 + 1.0], 0.03, postMat));
      }
    }


    // Overhead Roof Netting
      // Note: Kept open in 3D visualizer to provide a crystal-clear, unobstructed view of the grass turf bed and pitch without top haze or shining artifacts.
    }

    // ==========================================
    // REALISTIC 3D CRICKET PITCH & EQUIPMENT
    // ==========================================
    // Dedicated 3D Cricket Wickets, Bat & Ball in Zone 1 for 185x60 Arena
    if (is185x60 && showPitch) {
      var stumpMat185 = new THREE.MeshStandardMaterial({ color: 0xb45309, roughness: 0.4, metalness: 0.1 });
      var stumpRadius = 0.012;
      var stumpHeight = 0.28;

      var addWicketSet185 = function(stumpX, centerZ) {
        var stumpOffsets = [-0.06, 0, 0.06];
        for (var si = 0; si < stumpOffsets.length; si++) {
          var stumpGeo = new THREE.CylinderGeometry(stumpRadius, stumpRadius, stumpHeight, 8);
          var stump = new THREE.Mesh(stumpGeo, stumpMat185);
          stump.position.set(stumpX, stumpHeight / 2 + 0.007, centerZ + stumpOffsets[si]);
          scene.add(stump);
        }
        var bail = new THREE.Mesh(new THREE.BoxGeometry(0.015, 0.01, 0.16), stumpMat185);
        bail.position.set(stumpX, stumpHeight + 0.012, centerZ);
        scene.add(bail);
      };

      // Zone 1 Pitch runs from x = -8.4 to x = -0.1 (83 FT centered at x = -4.25)
      addWicketSet185(-8.2, 0); // Batting end wickets
      addWicketSet185(-0.3, 0); // Bowling end wickets

      // 3D Cricket Bat standing at batting end
      var batGroup185 = new THREE.Group();
      var blade185 = new THREE.Mesh(new THREE.BoxGeometry(0.03, 0.26, 0.08), new THREE.MeshStandardMaterial({ color: 0xd97706, roughness: 0.5 }));
      blade185.position.set(0, 0.13, 0);
      batGroup185.add(blade185);
      var handle185 = new THREE.Mesh(new THREE.CylinderGeometry(0.012, 0.012, 0.12, 8), new THREE.MeshStandardMaterial({ color: 0xdc2626, roughness: 0.6 }));
      handle185.position.set(0, 0.32, 0);
      batGroup185.add(handle185);
      batGroup185.rotation.z = -0.25;
      batGroup185.position.set(-8.0, 0.006, 0.18);
      scene.add(batGroup185);

      // 3D Red Leather Cricket Ball
      var ball185 = new THREE.Mesh(new THREE.SphereGeometry(0.024, 16, 16), new THREE.MeshStandardMaterial({ color: 0x991b1b, roughness: 0.35, metalness: 0.1 }));
      ball185.position.set(-7.9, 0.024 + 0.006, -0.10);
      scene.add(ball185);
    }

    if (isCricket && showPitch && sport !== 'multisport_complex_105x170' && !(L_ft === 170 && W_ft === 105) && !is185x60) {
      var pitchL_ft = parseFloat(config.pitch_length) || 66;
      var pitchL = Math.min(pitchL_ft * 0.1, L * 0.95);
      var pitchW = 0.65; // 6.5 FT standard width (2.0M roll)

      var pitchZPositions = [0];
      if (is2Lane) pitchZPositions = [-halfW / 2, halfW / 2];
      else if (is3Lane) pitchZPositions = [-W / 3, 0, W / 3];

      for (var pzi = 0; pzi < pitchZPositions.length; pzi++) {
        var pz = pitchZPositions[pzi];

        // Authentic Wicket Pitch Mat (Natural Clay / Curled Cricket Turf)
        var pitchGeo = new THREE.PlaneGeometry(pitchL, pitchW);
        var pitchMat = new THREE.MeshStandardMaterial({
          color: 0xdeb887, // Natural clay wicket tone
          roughness: 0.85,
          metalness: 0.05
        });
        var pitch = new THREE.Mesh(pitchGeo, pitchMat);
        pitch.rotation.x = -Math.PI / 2;
        pitch.position.set(0, 0.006, pz);
        scene.add(pitch);

        // Dark Border Frame for Pitch
        var borderGeo = new THREE.EdgesGeometry(pitchGeo);
        var borderMat = new THREE.LineBasicMaterial({ color: 0x854d0e, linewidth: 2 });
        var borderLine = new THREE.LineSegments(borderGeo, borderMat);
        borderLine.rotation.x = -Math.PI / 2;
        borderLine.position.set(0, 0.007, pz);
        scene.add(borderLine);

        // Regulation White Creases
        var creaseMat = new THREE.MeshBasicMaterial({ color: 0xffffff });

        // Bowling Creases (1.2 FT inside pitch ends)
        var crease1 = new THREE.Mesh(new THREE.PlaneGeometry(0.035, pitchW * 0.95), creaseMat);
        crease1.rotation.x = -Math.PI / 2;
        crease1.position.set(-pitchL / 2 + 0.12, 0.008, pz);
        scene.add(crease1);

        var crease2 = new THREE.Mesh(new THREE.PlaneGeometry(0.035, pitchW * 0.95), creaseMat);
        crease2.rotation.x = -Math.PI / 2;
        crease2.position.set(pitchL / 2 - 0.12, 0.008, pz);
        scene.add(crease2);

        // Popping Creases (4 FT ahead of bowling crease)
        var pop1 = new THREE.Mesh(new THREE.PlaneGeometry(0.03, pitchW * 0.88), creaseMat);
        pop1.rotation.x = -Math.PI / 2;
        pop1.position.set(-pitchL / 2 + 0.24, 0.008, pz);
        scene.add(pop1);

        var pop2 = new THREE.Mesh(new THREE.PlaneGeometry(0.03, pitchW * 0.88), creaseMat);
        pop2.rotation.x = -Math.PI / 2;
        pop2.position.set(pitchL / 2 - 0.24, 0.008, pz);
        scene.add(pop2);

        // 3D Wooden Wickets & Bails at both ends
        var stumpMat = new THREE.MeshStandardMaterial({ color: 0xb45309, roughness: 0.4, metalness: 0.1 });
        var stumpRadius = 0.012;
        var stumpHeight = 0.28; // 28 inches (~2.33 FT)

        var addWicketSet = function(stumpX, centerZ) {
          var stumpOffsets = [-0.06, 0, 0.06]; // 3 stumps: Off, Middle, Leg
          for (var si = 0; si < stumpOffsets.length; si++) {
            var stumpGeo = new THREE.CylinderGeometry(stumpRadius, stumpRadius, stumpHeight, 8);
            var stump = new THREE.Mesh(stumpGeo, stumpMat);
            stump.position.set(stumpX, stumpHeight / 2 + 0.007, centerZ + stumpOffsets[si]);
            scene.add(stump);
          }
          // Bails on top
          var bailGeo = new THREE.BoxGeometry(0.015, 0.01, 0.16);
          var bail = new THREE.Mesh(bailGeo, stumpMat);
          bail.position.set(stumpX, stumpHeight + 0.012, centerZ);
          scene.add(bail);
        };

        addWicketSet(-pitchL / 2 + 0.12, pz); // Batting end wickets
        addWicketSet(pitchL / 2 - 0.12, pz);  // Bowling end wickets

        // Real 3D Cricket Bat standing at batting end
        var batGroup = new THREE.Group();
        var bladeGeo = new THREE.BoxGeometry(0.03, 0.26, 0.08);
        var bladeMat = new THREE.MeshStandardMaterial({ color: 0xd97706, roughness: 0.5 });
        var blade = new THREE.Mesh(bladeGeo, bladeMat);
        blade.position.set(0, 0.13, 0);
        batGroup.add(blade);
        // Handle with red rubber grip
        var handleGeo = new THREE.CylinderGeometry(0.012, 0.012, 0.12, 8);
        var handleMat = new THREE.MeshStandardMaterial({ color: 0xdc2626, roughness: 0.6 });
        var handle = new THREE.Mesh(handleGeo, handleMat);
        handle.position.set(0, 0.32, 0);
        batGroup.add(handle);

        batGroup.rotation.z = -0.25;
        batGroup.position.set(-pitchL / 2 + 0.30, 0.006, pz + 0.18);
        scene.add(batGroup);

        // Real 3D Red Leather Cricket Ball
        var ballGeo = new THREE.SphereGeometry(0.024, 16, 16);
        var ballMat = new THREE.MeshStandardMaterial({ color: 0x991b1b, roughness: 0.35, metalness: 0.1 });
        var ball = new THREE.Mesh(ballGeo, ballMat);
        ball.position.set(-pitchL / 2 + 0.38, 0.024 + 0.006, pz - 0.10);
        scene.add(ball);
      }
    }

    // Football & Futsal Elements
    var hasFootball = (config && config.equipment && config.equipment.football_goals !== undefined)
      ? !!config.equipment.football_goals
      : (sport.indexOf('football') !== -1 || sport === 'futsal' || sport === 'multi_sport' || sport === 'multi_futsal_basket' || sport === 'multisport_4in1_100x60' || (config && (config.id === 'EST-202610-026' || config.doc_id === 'EST-202610-026')));
    if (hasFootball) {
      var gpW = (sport === 'football_11a') ? 2.4 : ((sport === 'football_7a' || sport === 'football_9a') ? 1.6 : 1.0);
      var gpH = (sport === 'football_11a') ? 0.8 : ((sport === 'football_7a' || sport === 'football_9a') ? 0.7 : 0.65);
      var gpD = 0.35;

      function createGoalPost(posX, facingLeft) {
        var sign = facingLeft ? -1 : 1;
        scene.add(createPipe([posX, 0, -gpW / 2], [posX, gpH, -gpW / 2], 0.025, whiteMat));
        scene.add(createPipe([posX, 0, gpW / 2], [posX, gpH, gpW / 2], 0.025, whiteMat));
        scene.add(createPipe([posX, gpH, -gpW / 2], [posX, gpH, gpW / 2], 0.025, whiteMat));
        var rx = posX + (sign * gpD);
        scene.add(createPipe([posX, gpH, -gpW / 2], [rx, 0, -gpW / 2], 0.015, whiteMat));
        scene.add(createPipe([posX, gpH, gpW / 2], [rx, 0, gpW / 2], 0.015, whiteMat));
        scene.add(createPipe([rx, 0, -gpW / 2], [rx, 0, gpW / 2], 0.015, whiteMat));
      }

      createGoalPost(-halfL + 0.05, false);
      createGoalPost(halfL - 0.05, true);

      var centerCircle = new THREE.Mesh(new THREE.RingGeometry(0.8, 0.85, 32), whiteMat);
      centerCircle.rotation.x = -Math.PI / 2;
      centerCircle.position.set(0, 0.003, 0);
      scene.add(centerCircle);

      var halfLine = new THREE.Mesh(new THREE.PlaneGeometry(0.04, W), whiteMat);
      halfLine.rotation.x = -Math.PI / 2;
      halfLine.position.set(0, 0.002, 0);
      scene.add(halfLine);
    }

    // Badminton Court Elements
    if (sport === 'badminton' || sport === 'badminton_multi' || sport === 'hybrid_badminton_pickleball' || (config.zones && config.zones.badminton)) {
      var badNetW = Math.min(2.0, W * 0.8);
      var badNetH = 0.155;
      var badX = (config.zones && config.zones.badminton) ? (-halfL + (config.zones.cricket ? config.zones.cricket.length * 0.1 : 0) + (config.zones.badminton.length * 0.05)) : 0;
      scene.add(createPipe([badX, 0, -badNetW / 2], [badX, badNetH, -badNetW / 2], 0.02, steelMat));
      scene.add(createPipe([badX, 0, badNetW / 2], [badX, badNetH, badNetW / 2], 0.02, steelMat));
      var badNetMesh = new THREE.Mesh(
        new THREE.PlaneGeometry(badNetW, badNetH * 0.7),
        new THREE.MeshBasicMaterial({ color: 0x991b1b, side: THREE.DoubleSide, transparent: true, opacity: 0.75 })
      );
      badNetMesh.rotation.y = Math.PI / 2;
      badNetMesh.position.set(badX, badNetH * 0.65, 0);
      scene.add(badNetMesh);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3D Volleyball & Badminton Elements for 4-in-1 Multi-Sport Ground
    // ─────────────────────────────────────────────────────────────────────────
    var hasVolleyball = (config && config.equipment && config.equipment.volleyball_net !== undefined)
      ? !!config.equipment.volleyball_net
      : (sport === 'volleyball' || sport === 'multisport_4in1_100x60' || (config && (config.id === 'EST-202610-026' || config.doc_id === 'EST-202610-026')));
    if (hasVolleyball) {
      var vbW = 2.95; // 29.5 FT width (regulation 9m)
      var vbL = 5.90; // 59 FT length (regulation 18m)
      var vbPostH = 0.80; // 8 FT regulation upright post height (2.43m)
      var vbNetW = 3.20; // 32 FT net length

      // Yellow Court Boundary (Inlaid Yellow Lines)
      var vbBorderGeo = new THREE.EdgesGeometry(new THREE.PlaneGeometry(vbL, vbW));
      var vbBorder = new THREE.LineSegments(vbBorderGeo, new THREE.LineBasicMaterial({ color: 0xfacc15, linewidth: 2 }));
      vbBorder.rotation.x = -Math.PI / 2;
      vbBorder.position.set(0, 0.003, 0);
      scene.add(vbBorder);

      // 3M Attack Lines (±1.0 unit = 10 FT from center net line)
      var yellowLineMat = new THREE.MeshBasicMaterial({ color: 0xfacc15, side: THREE.DoubleSide });
      var attackLine1 = new THREE.Mesh(new THREE.PlaneGeometry(0.025, vbW), yellowLineMat);
      attackLine1.rotation.x = -Math.PI / 2;
      attackLine1.position.set(-1.0, 0.003, 0);
      scene.add(attackLine1);

      var attackLine2 = new THREE.Mesh(new THREE.PlaneGeometry(0.025, vbW), yellowLineMat);
      attackLine2.rotation.x = -Math.PI / 2;
      attackLine2.position.set(1.0, 0.003, 0);
      scene.add(attackLine2);

      // Heavy-Duty Volleyball Upright Posts at Sidelines
      var vbPostZ1 = -vbNetW / 2;
      var vbPostZ2 = vbNetW / 2;
      scene.add(createPipe([0, 0, vbPostZ1], [0, vbPostH, vbPostZ1], 0.028, steelMat));
      scene.add(createPipe([0, 0, vbPostZ2], [0, vbPostH, vbPostZ2], 0.028, steelMat));

      // Protective Post Safety Pads (Vibrant Blue/Yellow)
      var padMat = new THREE.MeshStandardMaterial({ color: 0x1d4ed8, roughness: 0.4 });
      var pad1 = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.45, 0.08), padMat);
      pad1.position.set(0, 0.225, vbPostZ1);
      scene.add(pad1);
      var pad2 = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.45, 0.08), padMat);
      pad2.position.set(0, 0.225, vbPostZ2);
      scene.add(pad2);

      // Tensioned Competition Volleyball Net
      var vbNetMesh = new THREE.Mesh(
        new THREE.PlaneGeometry(vbNetW, 0.33), // 1m / 3.28 FT net depth
        new THREE.MeshBasicMaterial({ color: 0x1e293b, side: THREE.DoubleSide, transparent: true, opacity: 0.70 })
      );
      vbNetMesh.rotation.y = Math.PI / 2;
      vbNetMesh.position.set(0, vbPostH - 0.165, 0);
      scene.add(vbNetMesh);

      // Top White Vinyl Headband
      var vbTopBand = new THREE.Mesh(
        new THREE.PlaneGeometry(vbNetW, 0.025),
        whiteMat
      );
      vbTopBand.rotation.y = Math.PI / 2;
      vbTopBand.position.set(0, vbPostH - 0.012, 0);
      scene.add(vbTopBand);

      // Dual Red Antennas
      var antMat = new THREE.MeshBasicMaterial({ color: 0xdc2626 });
      scene.add(createPipe([0, vbPostH - 0.33, -vbW / 2], [0, vbPostH + 0.25, -vbW / 2], 0.008, antMat));
      scene.add(createPipe([0, vbPostH - 0.33, vbW / 2], [0, vbPostH + 0.25, vbW / 2], 0.008, antMat));
    }

    var hasBadminton = (config && config.equipment && config.equipment.badminton_net !== undefined)
      ? !!config.equipment.badminton_net
      : (sport === 'badminton' || sport === 'badminton_multi' || sport === 'hybrid_badminton_pickleball' || sport === 'multisport_4in1_100x60' || (config && (config.id === 'EST-202610-026' || config.doc_id === 'EST-202610-026')) || (config.zones && config.zones.badminton));
    if (hasBadminton) {
      var bmW = 2.0; // 20 FT doubles width
      var bmL = 4.4; // 44 FT doubles length
      var bmPostH = 0.155; // 5 FT 1 in (1.55m)
      var cyanMat = new THREE.MeshBasicMaterial({ color: 0x06b6d4, side: THREE.DoubleSide });

      // Outer BWF Doubles Boundary (Cyan)
      var bmBorderGeo = new THREE.EdgesGeometry(new THREE.PlaneGeometry(bmL, bmW));
      var bmBorder = new THREE.LineSegments(bmBorderGeo, new THREE.LineBasicMaterial({ color: 0x06b6d4, linewidth: 2 }));
      bmBorder.rotation.x = -Math.PI / 2;
      bmBorder.position.set(0, 0.0035, 0);
      scene.add(bmBorder);

      // Singles sidelines (17 FT width = 1.7 units)
      var sLine1 = new THREE.Mesh(new THREE.PlaneGeometry(bmL, 0.015), cyanMat);
      sLine1.rotation.x = -Math.PI / 2;
      sLine1.position.set(0, 0.0035, -0.85);
      scene.add(sLine1);
      var sLine2 = new THREE.Mesh(new THREE.PlaneGeometry(bmL, 0.015), cyanMat);
      sLine2.rotation.x = -Math.PI / 2;
      sLine2.position.set(0, 0.0035, 0.85);
      scene.add(sLine2);

      // Short service lines (6.5 FT = 0.65 units from net)
      var ssLine1 = new THREE.Mesh(new THREE.PlaneGeometry(0.015, bmW), cyanMat);
      ssLine1.rotation.x = -Math.PI / 2;
      ssLine1.position.set(-0.65, 0.0035, 0);
      scene.add(ssLine1);
      var ssLine2 = new THREE.Mesh(new THREE.PlaneGeometry(0.015, bmW), cyanMat);
      ssLine2.rotation.x = -Math.PI / 2;
      ssLine2.position.set(0.65, 0.0035, 0);
      scene.add(ssLine2);

      // Movable Cast-Iron Badminton Posts
      var bmPostZ1 = -bmW / 2;
      var bmPostZ2 = bmW / 2;
      scene.add(createPipe([0, 0, bmPostZ1], [0, bmPostH, bmPostZ1], 0.015, steelMat));
      scene.add(createPipe([0, 0, bmPostZ2], [0, bmPostH, bmPostZ2], 0.015, steelMat));

      // Cast-iron weighted wheel bases
      var baseMat = new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.6 });
      var bBase1 = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.03, 0.08), baseMat);
      bBase1.position.set(0, 0.015, bmPostZ1);
      scene.add(bBase1);
      var bBase2 = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.03, 0.08), baseMat);
      bBase2.position.set(0, 0.015, bmPostZ2);
      scene.add(bBase2);

      // Regulation BWF Tournament Net (20 FT wide)
      var badNetMesh = new THREE.Mesh(
        new THREE.PlaneGeometry(bmW, 0.076), // 2.5 FT / 0.76m net depth
        new THREE.MeshBasicMaterial({ color: 0x881337, side: THREE.DoubleSide, transparent: true, opacity: 0.75 })
      );
      badNetMesh.rotation.y = Math.PI / 2;
      badNetMesh.position.set(0, bmPostH - 0.038, 0);
      scene.add(badNetMesh);

      // Top white band
      var bmTopBand = new THREE.Mesh(
        new THREE.PlaneGeometry(bmW, 0.015),
        whiteMat
      );
      bmTopBand.rotation.y = Math.PI / 2;
      bmTopBand.position.set(0, bmPostH - 0.007, 0);
      scene.add(bmTopBand);
    }

// Basketball Elements - Exactly 10 FT (3.05m) Regulation Rim Height (1 FT = 0.1 unit -> 10 FT = 1.0 unit)
    if (sport === 'basketball_half' || sport === 'basketball_full' || sport === 'multi_futsal_basket' || sport === 'outdoor_multi_sports_120x60') {
      function createHoop(hx, hz, rotY) {
        var rimH = 1.0; // 10 FT regulation rim height
        var poleH = 1.15; // Heavy 6" MS pole rises to 11.5 FT
        var isLeft = (hx < 0);

        // 6" MS Steel Main Upright Pole (Radius 0.025)
        var poleMesh = createPipe([hx, 0, hz], [hx, poleH, hz], 0.025, steelMat);
        scene.add(poleMesh);

        // Protective Base Safety Padding (Vibrant Red, matching reference photos)
        var padGeo = new THREE.BoxGeometry(0.09, 0.38, 0.09);
        var padMat = new THREE.MeshStandardMaterial({ color: 0xdc2626, roughness: 0.45 });
        var padMesh = new THREE.Mesh(padGeo, padMat);
        padMesh.position.set(hx, 0.19, hz);
        scene.add(padMesh);

        // 1.2m Extension Gooseneck Overhang Arm (4 FT = 0.40 units into court)
        var overhang = 0.40;
        var boardX = isLeft ? (hx + overhang) : (hx - overhang);
        var boomPipe = createPipe([hx, poleH, hz], [boardX, poleH - 0.08, hz], 0.022, steelMat);
        scene.add(boomPipe);

        // Diagonal Support Struts
        var strutTop = createPipe([hx + (isLeft ? 0.15 : -0.15), poleH - 0.02, hz], [boardX, poleH + 0.09, hz], 0.012, steelMat);
        scene.add(strutTop);
        var strutBot = createPipe([hx + (isLeft ? 0.15 : -0.15), poleH - 0.02, hz], [boardX, poleH - 0.22, hz], 0.012, steelMat);
        scene.add(strutBot);

        // 20mm Imported Acrylic Backboard (180cm x 105cm = 0.60m wide x 0.35m high)
        // Bottom of board at 9 FT (0.90), Top at 12.5 FT (1.25) -> Center at 1.075
        var boardGeo = new THREE.BoxGeometry(0.015, 0.35, 0.60);
        var acrylicMat = new THREE.MeshStandardMaterial({
          color: 0xf8fafc,
          transparent: true,
          opacity: 0.88,
          roughness: 0.1,
          metalness: 0.1
        });
        var board = new THREE.Mesh(boardGeo, acrylicMat);
        board.position.set(boardX, 1.075, hz);
        scene.add(board);

        // Backboard White Border Frame
        var boardFrame = new THREE.LineSegments(
          new THREE.EdgesGeometry(boardGeo),
          new THREE.LineBasicMaterial({ color: 0xffffff, linewidth: 2.5 })
        );
        boardFrame.position.copy(board.position);
        scene.add(boardFrame);

        // Inner Target Box (59cm x 45cm = 0.20m x 0.15m)
        var targetGeo = new THREE.PlaneGeometry(0.20, 0.15);
        var targetLines = new THREE.LineSegments(
          new THREE.EdgesGeometry(targetGeo),
          new THREE.LineBasicMaterial({ color: 0xea580c, linewidth: 2 })
        );
        targetLines.rotation.y = Math.PI / 2;
        targetLines.position.set(boardX + (isLeft ? 0.01 : -0.01), rimH + 0.075, hz);
        scene.add(targetLines);

        // Olympian Breakaway Rim (Exactly 10 FT Height = Y: 1.00)
        var rimRadius = 0.045; // 45cm (18") diameter
        var rimGeo = new THREE.TorusGeometry(rimRadius, 0.006, 8, 24);
        var rimMat = new THREE.MeshStandardMaterial({ color: 0xea580c, roughness: 0.3, metalness: 0.6 });
        var rim = new THREE.Mesh(rimGeo, rimMat);
        rim.rotation.x = Math.PI / 2;
        var rimX = boardX + (isLeft ? (rimRadius + 0.02) : -(rimRadius + 0.02));
        rim.position.set(rimX, rimH, hz);
        scene.add(rim);

        // Heavy Rim Mounting Bracket
        var bracketGeo = new THREE.BoxGeometry(0.04, 0.02, 0.04);
        var bracket = new THREE.Mesh(bracketGeo, rimMat);
        bracket.position.set(boardX + (isLeft ? 0.02 : -0.02), rimH, hz);
        scene.add(bracket);

        // All-Weather Net hanging down from rim
        var netGeo = new THREE.CylinderGeometry(rimRadius, rimRadius * 0.45, 0.14, 12, 2, true);
        var netMat = new THREE.MeshBasicMaterial({ color: 0xffffff, wireframe: true });
        var netMesh = new THREE.Mesh(netGeo, netMat);
        netMesh.position.set(rimX, rimH - 0.07, hz);
        scene.add(netMesh);
      }

      var hoopBaseX = halfL - 0.25;
      createHoop(-hoopBaseX, 0, true);
      if (sport === 'basketball_full' || sport === 'outdoor_multi_sports_120x60') {
        createHoop(hoopBaseX, 0, false);
      }
    }

    // Tennis / Badminton Tournament Nets (with Red Padded Weighted Bases)
    if (sport === 'tennis' || sport === 'pickleball' || sport === 'pickleball_multi' || sport === 'outdoor_multi_sports_120x60') {
      var netH2 = (sport === 'tennis') ? 0.36 : 0.38; // 3.8 FT net height
      var netW2 = W * 0.92;
      
      // Fixed 80x80mm MS Posts
      scene.add(createPipe([0, 0, -netW2 / 2], [0, netH2 + 0.05, -netW2 / 2], 0.025, steelMat));
      scene.add(createPipe([0, 0, netW2 / 2], [0, netH2 + 0.05, netW2 / 2], 0.025, steelMat));
      
      // Heavy Red Weighted Bases (matching photo 1 & 2)
      var redPadMat = new THREE.MeshStandardMaterial({ color: 0xdc2626, roughness: 0.4 });
      var baseL = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.18, 0.12), redPadMat);
      baseL.position.set(0, 0.09, -netW2 / 2);
      scene.add(baseL);
      var baseR = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.18, 0.12), redPadMat);
      baseR.position.set(0, 0.09, netW2 / 2);
      scene.add(baseR);

      // Top White Headband of Net
      scene.add(createPipe([0, netH2, -netW2 / 2], [0, netH2, netW2 / 2], 0.008, whiteMat));

      // Tournament Net Mesh
      var tNet = new THREE.Mesh(
        new THREE.PlaneGeometry(netW2, netH2 * 0.9),
        new THREE.MeshBasicMaterial({ color: 0x0f172a, side: THREE.DoubleSide, transparent: true, opacity: 0.85 })
      );
      tNet.rotation.y = Math.PI / 2;
      tNet.position.set(0, netH2 * 0.5, 0);
      scene.add(tNet);
    }

    // Lighting fixtures removed from 3D view per user requirement

    // Animation Loop
    var animId = null;
    function animate() {
      animId = requestAnimationFrame(animate);
      if (controls) controls.update();
      renderer.render(scene, camera);
    }
    animate();

    function updateSnapshot() {
      try {
        if (controls) controls.update();
        renderer.render(scene, camera);
        var dataUrl = renderer.domElement.toDataURL('image/png');
        var snapImgId = (config && config.snapshot_img_id) ? config.snapshot_img_id : 'turnkey-3d-print-img';
        var snapImg = document.getElementById(snapImgId);
        if (snapImg) {
          snapImg.src = dataUrl;
        }
        var snapInput = document.getElementById('turnkey_3d_snapshot');
        if (snapInput) {
          snapInput.value = dataUrl;
        }
      } catch (e) {}
    }

    setTimeout(updateSnapshot, 150);
    setTimeout(updateSnapshot, 500);
    setTimeout(updateSnapshot, 1200);
    window.addEventListener('beforeprint', updateSnapshot);
    if (controls) {
      controls.addEventListener('change', function() {
        clearTimeout(updateSnapshot._t);
        updateSnapshot._t = setTimeout(updateSnapshot, 250);
      });
    }

    if (typeof ResizeObserver !== 'undefined') {
      var resizeObserver = new ResizeObserver(function() {
        if (!container) return;
        var w = container.clientWidth;
        var h = container.clientHeight;
        if (w > 0 && h > 0) {
          camera.aspect = w / h;
          camera.updateProjectionMatrix();
          renderer.setSize(w, h);
          updateSnapshot();
        }
      });
      resizeObserver.observe(container);
    }

    function setCameraAngle(angle) {
      if (!camera) return;
      var curDist = Math.max(courtDiag * 1.15, maxDim * 1.10, H * 2.2);
      var curElev = Math.max(H * 1.45, curDist * 0.38);
      var curX = -Math.max(L * 0.72, curDist * 0.62);
      var curZ = Math.max(W * 1.45, curDist * 0.52);
      var cTargetY = is185x60 ? (H * 0.35) : targetY;
      var cTargetX = targetX;

      if (angle === 'front') {
        // Front 3/4 entrance view looking down the court length in true proportion
        if (is185x60) {
          camera.position.set(-L * 0.85, H * 1.35, W * 1.45);
        } else if (is105x170) {
          camera.position.set(-L * 0.90, H * 1.40, W * 1.40);
        } else {
          camera.position.set(curX, curElev, curZ);
        }
        if (controls) {
          controls.target.set(cTargetX, cTargetY, 0);
          controls.update();
        }
      } else if (angle === 'side') {
        // Side Elevation View: ensures the FULL length fits with comfortable margins
        var sideZ = Math.max(L * 0.95, W * 1.85, curDist * 0.85);
        var sideY = Math.max(H * 0.95, curDist * 0.28);
        camera.position.set(cTargetX, sideY, sideZ);
        if (controls) {
          controls.target.set(cTargetX, cTargetY, 0);
          controls.update();
        }
      } else if (angle === 'top') {
        // Top Bird's Eye View looking straight down at the court
        camera.position.set(cTargetX, curDist * 1.25, 0.01);
        if (controls) {
          controls.target.set(cTargetX, 0, 0);
          controls.update();
        }
      } else if (angle === 'back') {
        // Back View looking from opposite baseline
        if (is185x60) {
          camera.position.set(L * 0.85, H * 1.35, -W * 1.45);
        } else if (is105x170) {
          camera.position.set(L * 0.90, H * 1.40, -W * 1.40);
        } else {
          camera.position.set(-curX, curElev, -curZ);
        }
        if (controls) {
          controls.target.set(cTargetX, cTargetY, 0);
          controls.update();
        }
      }
      updateSnapshot();

      // Switch to 3D tab if user was in 2D
      if (typeof switchVisualizerTab === 'function') {
        if (typeof currentVisualizerTab !== 'undefined' && currentVisualizerTab !== '3d') {
          switchVisualizerTab('3d', true);
        }
      }

      // Update button styling in toolbar
      if (renderer && scene) { renderer.render(scene, camera); }
      var bFront = document.getElementById("btnCamFront") || document.querySelector("button[onclick*=\"front\"]");
      var bSide  = document.getElementById("btnCamSide")  || document.querySelector("button[onclick*=\"side\"]");
      var bTop   = document.getElementById("btnCamTop")   || document.querySelector("button[onclick*=\"top\"]");
      var bBack  = document.getElementById("btnCamBack")  || document.querySelector("button[onclick*=\"back\"]");
      [bFront, bSide, bTop, bBack].forEach(function(b) {
        if (b) {
          b.className = 'btn btn-xs btn-outline-light py-1 px-2';
        }
      });
      var activeBtn = (angle === 'front') ? bFront : ((angle === 'side') ? bSide : ((angle === 'top') ? bTop : bBack));
      if (activeBtn) {
        activeBtn.className = 'btn btn-xs btn-info text-dark fw-bold py-1 px-2';
      }
    }

    window.setCourtCameraAngle = setCameraAngle;
    window._activeCourtCameraAngle = setCameraAngle;
    if (window.TurnkeyViewer) {
      window.TurnkeyViewer._activeSetCameraAngle = setCameraAngle;
    }

    return {
      scene: scene,
      camera: camera,
      renderer: renderer,
      controls: controls,
      setCameraAngle: setCameraAngle,
      toggleRoof: function(visible) {
        if (megaRoofGroup) {
          megaRoofGroup.visible = !!visible;
        }
        if (renderer && scene && camera) {
          renderer.render(scene, camera);
        }
      }
    };
  }

  // ==========================================
  // 2D Architectural Floor Plan CAD Generator
  // ==========================================
    // =========================================================================
  // DEDICATED ARCHITECTURAL CAD FLOOR PLAN: 16,000 SFT MEGA SPORTS COMPLEX (105 FT × 170 FT)
  // 11,000 SFT Indoor Hall (4 Badminton + 2 Pickleball Under GI Roof) + 4,500 SFT Outdoor Cricket + Parking
  // =========================================================================
    // =========================================================================
  // DEDICATED ARCHITECTURAL CAD FLOOR PLAN: 185 FT × 60 FT MULTI-SPORT ARENA
  // EXACT REPLICA OF APPROVED PDF (PAGE 5): ZONE 1 CRICKET, ZONE 2 BADMINTON, ZONE 3 LOUNGE
  // NOTE: Metal fabrication & columns strictly on Zones 1 & 2 (145 FT); Zone 3 is Client Scope
  // =========================================================================
    // =========================================================================
  // DEDICATED ARCHITECTURAL CAD FLOOR PLAN: 185 FT × 60 FT MULTI-SPORT ARENA
  // 160 FT BUILT-UP METAL STRUCTURE (EST-202609-078): 100 FT CRICKET + 60×60 FT INDOOR BADMINTON & AMENITIES + 25 FT OPEN
  // STAGGERED ARCHITECTURAL LAYOUT:
  // North Bay: [O] Office Room (15×18) + [LT] Ladies Toilet (15×12) at 100-115 FT; Badminton Court 1 at 115-160 FT
  // South Bay: Badminton Court 2 at 100-145 FT; [GT] Gents Toilet (15×12) + [L] Reception Lobby (15×18) at 145-160 FT
  // Main Entrance [E] (6×7 FT) on 160 FT Front Wall leading into Reception Lobby
  // =========================================================================
  
  // =========================================================================
  // 4-IN-1 MULTI-SPORT ARENA (100 FT x 60 FT = 6,000 SFT)
  // CRICKET • MINI FOOTBALL • VOLLEYBALL • BADMINTON (30MM NON-INFILL GRASS)
  // =========================================================================
  function renderMultiSportArena100x60FloorPlan(container, config) {
    if (!container) return;
    var svgW = 1600;
    var svgH = 900;
    var scale = 11.8;
    var plotW_px = 100 * scale;
    var plotH_px = 60 * scale;
    var cx = (svgW - plotW_px) / 2;
    var cy = 82;

    var svg = [];
    svg.push('<svg viewBox="0 0 ' + svgW + ' ' + svgH + '" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;background:#ffffff;font-family:Inter,Segoe UI,sans-serif;" preserveAspectRatio="xMidYMid meet">');
    svg.push('<defs>');
    svg.push('<marker id="cadArr100" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M 0 1.5 L 10 5 L 0 8.5 z" fill="#0f172a"/></marker>');
    svg.push('<marker id="dimArr100" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M 0 1.5 L 10 5 L 0 8.5 z" fill="#334155"/></marker>');
    svg.push('<pattern id="netMeshPat" width="6" height="6" patternUnits="userSpaceOnUse"><path d="M 0 3 L 6 3 M 3 0 L 3 6" fill="none" stroke="#cbd5e1" stroke-width="0.75"/></pattern>');
    svg.push('</defs>');

    // Title Sheet Header Bar
    svg.push('<rect x="' + (cx - 16) + '" y="12" width="' + (plotW_px + 32) + '" height="54" rx="6" fill="#0f172a" stroke="#1e293b" stroke-width="2"/>');
    svg.push('<text x="' + (svgW / 2) + '" y="' + 36 + '" text-anchor="middle" font-size="15" font-weight="900" fill="#ffffff" letter-spacing="1">ARCHITECTURAL 2D FLOOR PLAN • 100 FT × 60 FT (6,000 SFT) MULTI-SPORT ARENA</text>');
    svg.push('<text x="' + (svgW / 2) + '" y="' + 54 + '" text-anchor="middle" font-size="11" font-weight="700" fill="#38bdf8" letter-spacing="0.5">4-IN-1 INTEGRATED ARENA: CRICKET • MINI FOOTBALL • VOLLEYBALL • BADMINTON (30MM NON-INFILL GRASS)</text>');

    // Master Outer Sub-Base Bed
    svg.push('<rect x="' + (cx - 14) + '" y="' + (cy - 14) + '" width="' + (plotW_px + 28) + '" height="' + (plotH_px + 28) + '" rx="8" fill="#f8fafc" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6,4"/>');
    svg.push('<text x="' + (cx + 10) + '" y="' + (cy - 20) + '" font-size="10" font-weight="bold" fill="#64748b">4 INCH COMPACTED WMM BASE + 1 INCH STONE DUST LAYER (NATURAL PERCOLATING DRAINAGE)</text>');

    // Main 30mm Non-Infill Sports Grass Bed
    svg.push('<rect x="' + cx + '" y="' + cy + '" width="' + plotW_px + '" height="' + plotH_px + '" fill="#15803d" stroke="#166534" stroke-width="3"/>');

    // Lawn Stripes
    for (var ts = cx; ts < cx + plotW_px; ts += 50) {
      var wStrip = Math.min(50, (cx + plotW_px) - ts);
      if (Math.floor((ts - cx) / 50) % 2 === 1) {
        svg.push('<rect x="' + ts + '" y="' + cy + '" width="' + wStrip + '" height="' + plotH_px + '" fill="#16a34a" fill-opacity="0.30"/>');
      }
    }

    var fbOffset = 18;
    var fbW = plotW_px - (fbOffset * 2);
    var fbH = plotH_px - (fbOffset * 2);
    var fbX = cx + fbOffset;
    var fbY = cy + fbOffset;
    var midX = cx + (plotW_px / 2);
    var midY = cy + (plotH_px / 2);

    var equip = config.equipment || {};
    var hasFootball = (equip.football_goals !== undefined) ? !!equip.football_goals : true;
    var hasVolleyball = (equip.volleyball_net !== undefined) ? !!equip.volleyball_net : true;
    var hasBadminton = (equip.badminton_net !== undefined) ? !!equip.badminton_net : true;
    var hasCricket = (equip.cricket_pitch !== undefined) ? !!equip.cricket_pitch : true;
    var hasMarkings = (equip.multisport_markings !== undefined) ? !!equip.multisport_markings : true;

    // Football Boundary Line & Elements
    if (hasFootball) {
      if (hasMarkings) {
        svg.push('<rect x="' + fbX + '" y="' + fbY + '" width="' + fbW + '" height="' + fbH + '" fill="none" stroke="#ffffff" stroke-width="2.5"/>');
        svg.push('<line x1="' + midX + '" y1="' + fbY + '" x2="' + midX + '" y2="' + (fbY + fbH) + '" stroke="#ffffff" stroke-width="2.5"/>');

        // Center Circle (24 FT Diameter)
        var fbCenterR = 12 * scale;
        svg.push('<circle cx="' + midX + '" cy="' + midY + '" r="' + fbCenterR + '" fill="none" stroke="#ffffff" stroke-width="2.5"/>');
        svg.push('<circle cx="' + midX + '" cy="' + midY + '" r="5" fill="#ffffff"/>');

        // Corner Kick Arcs
        var cArcR = 3 * scale;
        svg.push('<path d="M ' + (fbX + cArcR) + ' ' + fbY + ' A ' + cArcR + ' ' + cArcR + ' 0 0 1 ' + fbX + ' ' + (fbY + cArcR) + '" fill="none" stroke="#ffffff" stroke-width="2"/>');
        svg.push('<path d="M ' + (fbX + fbW - cArcR) + ' ' + fbY + ' A ' + cArcR + ' ' + cArcR + ' 0 0 0 ' + (fbX + fbW) + ' ' + (fbY + cArcR) + '" fill="none" stroke="#ffffff" stroke-width="2"/>');
        svg.push('<path d="M ' + (fbX + cArcR) + ' ' + (fbY + fbH) + ' A ' + cArcR + ' ' + cArcR + ' 0 0 0 ' + fbX + ' ' + (fbY + fbH - cArcR) + '" fill="none" stroke="#ffffff" stroke-width="2"/>');
        svg.push('<path d="M ' + (fbX + fbW - cArcR) + ' ' + (fbY + fbH) + ' A ' + cArcR + ' ' + cArcR + ' 0 0 1 ' + (fbX + fbW) + ' ' + (fbY + fbH - cArcR) + '" fill="none" stroke="#ffffff" stroke-width="2"/>');

        // Penalty Boxes
        var penW = 24 * scale;
        var penD = 14 * scale;
        var penY = midY - (penW / 2);

        svg.push('<rect x="' + fbX + '" y="' + penY + '" width="' + penD + '" height="' + penW + '" fill="none" stroke="#ffffff" stroke-width="2"/>');
        svg.push('<circle cx="' + (fbX + 20 * scale) + '" cy="' + midY + '" r="4" fill="#ffffff"/>');

        svg.push('<rect x="' + (fbX + fbW - penD) + '" y="' + penY + '" width="' + penD + '" height="' + penW + '" fill="none" stroke="#ffffff" stroke-width="2"/>');
        svg.push('<circle cx="' + (fbX + fbW - 20 * scale) + '" cy="' + midY + '" r="4" fill="#ffffff"/>');
      }

      // Mini Football Goals (10 FT Wide x 6.5 FT High)
      var gpW = 10 * scale;
      var gpD = 3.5 * scale;
      var gpY = midY - (gpW / 2);

      svg.push('<rect x="' + (fbX - gpD) + '" y="' + gpY + '" width="' + gpD + '" height="' + gpW + '" fill="url(#netMeshPat)" stroke="#dc2626" stroke-width="3" rx="2"/>');
      svg.push('<rect x="' + (fbX - 3) + '" y="' + (gpY - 3) + '" width="6" height="' + (gpW + 6) + '" fill="#ffffff" stroke="#dc2626" stroke-width="2"/>');
      svg.push('<text x="' + (fbX - gpD - 8) + '" y="' + midY + '" transform="rotate(-90 ' + (fbX - gpD - 8) + ' ' + midY + ')" text-anchor="middle" font-size="9.5" font-weight="900" fill="#dc2626">MINI FOOTBALL GOAL 1 (10 FT x 6.5 FT)</text>');

      svg.push('<rect x="' + (fbX + fbW) + '" y="' + gpY + '" width="' + gpD + '" height="' + gpW + '" fill="url(#netMeshPat)" stroke="#dc2626" stroke-width="3" rx="2"/>');
      svg.push('<rect x="' + (fbX + fbW - 3) + '" y="' + (gpY - 3) + '" width="6" height="' + (gpW + 6) + '" fill="#ffffff" stroke="#dc2626" stroke-width="2"/>');
      svg.push('<text x="' + (fbX + fbW + gpD + 16) + '" y="' + midY + '" transform="rotate(90 ' + (fbX + fbW + gpD + 16) + ' ' + midY + ')" text-anchor="middle" font-size="9.5" font-weight="900" fill="#dc2626">MINI FOOTBALL GOAL 2 (10 FT x 6.5 FT)</text>');
    }

    // Volleyball Court (59 FT x 29.5 FT)
    if (hasVolleyball) {
      var vbL_px = 59 * scale;
      var vbW_px = 29.5 * scale;
      var vbX = midX - (vbL_px / 2);
      var vbY = midY - (vbW_px / 2);

      if (hasMarkings) {
        svg.push('<rect x="' + vbX + '" y="' + vbY + '" width="' + vbL_px + '" height="' + vbW_px + '" fill="none" stroke="#facc15" stroke-width="2.5" stroke-dasharray="8,4"/>');

        var vbAttDist = 9.84 * scale;
        svg.push('<line x1="' + (midX - vbAttDist) + '" y1="' + vbY + '" x2="' + (midX - vbAttDist) + '" y2="' + (vbY + vbW_px) + '" stroke="#facc15" stroke-width="2" stroke-dasharray="5,3"/>');
        svg.push('<line x1="' + (midX + vbAttDist) + '" y1="' + vbY + '" x2="' + (midX + vbAttDist) + '" y2="' + (vbY + vbW_px) + '" stroke="#facc15" stroke-width="2" stroke-dasharray="5,3"/>');
      }

      var vbNetLen_px = 32 * scale;
      var vbNetY1 = midY - (vbNetLen_px / 2);
      var vbNetY2 = midY + (vbNetLen_px / 2);

      svg.push('<line x1="' + midX + '" y1="' + vbNetY1 + '" x2="' + midX + '" y2="' + vbNetY2 + '" stroke="#eab308" stroke-width="4.5"/>');
      svg.push('<line x1="' + midX + '" y1="' + vbNetY1 + '" x2="' + midX + '" y2="' + vbNetY2 + '" stroke="#ffffff" stroke-width="2" stroke-dasharray="4,4"/>');

      svg.push('<rect x="' + (midX - 7) + '" y="' + (vbNetY1 - 14) + '" width="14" height="14" rx="3" fill="#1d4ed8" stroke="#facc15" stroke-width="2"/>');
      svg.push('<rect x="' + (midX - 7) + '" y="' + vbNetY2 + '" width="14" height="14" rx="3" fill="#1d4ed8" stroke="#facc15" stroke-width="2"/>');
      svg.push('<text x="' + midX + '" y="' + (vbNetY1 - 18) + '" text-anchor="middle" font-size="9" font-weight="900" fill="#eab308">VB POST 1 (8 FT H)</text>');
      svg.push('<text x="' + midX + '" y="' + (vbNetY2 + 24) + '" text-anchor="middle" font-size="9" font-weight="900" fill="#eab308">VB POST 2 (8 FT H)</text>');
    }

    // Badminton Court (44 FT x 20 FT)
    if (hasBadminton) {
      var bmL_px = 44 * scale;
      var bmW_px = 20 * scale;
      var bmX = midX - (bmL_px / 2);
      var bmY = midY - (bmW_px / 2);

      if (hasMarkings) {
        svg.push('<rect x="' + bmX + '" y="' + bmY + '" width="' + bmL_px + '" height="' + bmW_px + '" fill="none" stroke="#06b6d4" stroke-width="2"/>');

        var bmSinglesW = 17 * scale;
        var bmSinglesY = midY - (bmSinglesW / 2);
        svg.push('<line x1="' + bmX + '" y1="' + bmSinglesY + '" x2="' + (bmX + bmL_px) + '" y2="' + bmSinglesY + '" stroke="#06b6d4" stroke-width="1.5"/>');
        svg.push('<line x1="' + bmX + '" y1="' + (bmSinglesY + bmSinglesW) + '" x2="' + (bmX + bmL_px) + '" y2="' + (bmSinglesY + bmSinglesW) + '" stroke="#06b6d4" stroke-width="1.5"/>');

        var bmSSDist = 6.5 * scale;
        svg.push('<line x1="' + (midX - bmSSDist) + '" y1="' + bmY + '" x2="' + (midX - bmSSDist) + '" y2="' + (bmY + bmW_px) + '" stroke="#06b6d4" stroke-width="1.8"/>');
        svg.push('<line x1="' + (midX + bmSSDist) + '" y1="' + bmY + '" x2="' + (midX + bmSSDist) + '" y2="' + (bmY + bmW_px) + '" stroke="#06b6d4" stroke-width="1.8"/>');

        var bmLSDist = 2.5 * scale;
        svg.push('<line x1="' + (bmX + bmLSDist) + '" y1="' + bmY + '" x2="' + (bmX + bmLSDist) + '" y2="' + (bmY + bmW_px) + '" stroke="#06b6d4" stroke-width="1.5"/>');
        svg.push('<line x1="' + (bmX + bmL_px - bmLSDist) + '" y1="' + bmY + '" x2="' + (bmX + bmL_px - bmLSDist) + '" y2="' + (bmY + bmW_px) + '" stroke="#06b6d4" stroke-width="1.5"/>');

        svg.push('<line x1="' + bmX + '" y1="' + midY + '" x2="' + (midX - bmSSDist) + '" y2="' + midY + '" stroke="#06b6d4" stroke-width="1.5"/>');
        svg.push('<line x1="' + (midX + bmSSDist) + '" y1="' + midY + '" x2="' + (bmX + bmL_px) + '" y2="' + midY + '" stroke="#06b6d4" stroke-width="1.5"/>');
      }

      svg.push('<line x1="' + midX + '" y1="' + bmY + '" x2="' + midX + '" y2="' + (bmY + bmW_px) + '" stroke="#881337" stroke-width="3.5"/>');
      svg.push('<circle cx="' + midX + '" cy="' + bmY + '" r="5.5" fill="#334155" stroke="#06b6d4" stroke-width="2"/>');
      svg.push('<circle cx="' + midX + '" cy="' + (bmY + bmW_px) + '" r="5.5" fill="#334155" stroke="#06b6d4" stroke-width="2"/>');
    }

    // Cricket Pitch (41 FT x 6.5 FT)
    if (hasCricket) {
      var pL_px = 41 * scale;
      var pW_px = 6.5 * scale;
      var pX = midX - (pL_px / 2);
      var pY = midY - (pW_px / 2);

      svg.push('<rect x="' + pX + '" y="' + pY + '" width="' + pL_px + '" height="' + pW_px + '" rx="3" fill="#deb887" stroke="#854d0e" stroke-width="2.2"/>');

      svg.push('<g stroke="#ffffff" stroke-width="2.2">');
      svg.push('<line x1="' + (pX + 18) + '" y1="' + pY + '" x2="' + (pX + 18) + '" y2="' + (pY + pW_px) + '"/>');
      svg.push('<line x1="' + (pX + pL_px - 18) + '" y1="' + pY + '" x2="' + (pX + pL_px - 18) + '" y2="' + (pY + pW_px) + '"/>');
      var popOffset = 4 * scale;
      svg.push('<line x1="' + (pX + 18 + popOffset) + '" y1="' + (pY - 6) + '" x2="' + (pX + 18 + popOffset) + '" y2="' + (pY + pW_px + 6) + '"/>');
      svg.push('<line x1="' + (pX + pL_px - 18 - popOffset) + '" y1="' + (pY - 6) + '" x2="' + (pX + pL_px - 18 - popOffset) + '" y2="' + (pY + pW_px + 6) + '"/>');
      svg.push('</g>');

      svg.push('<circle cx="' + (pX + 18) + '" cy="' + (midY - 8) + '" r="2.5" fill="#facc15" stroke="#713f12" stroke-width="1"/>');
      svg.push('<circle cx="' + (pX + 18) + '" cy="' + midY + '" r="2.5" fill="#facc15" stroke="#713f12" stroke-width="1"/>');
      svg.push('<circle cx="' + (pX + 18) + '" cy="' + (midY + 8) + '" r="2.5" fill="#facc15" stroke="#713f12" stroke-width="1"/>');

      svg.push('<circle cx="' + (pX + pL_px - 18) + '" cy="' + (midY - 8) + '" r="2.5" fill="#facc15" stroke="#713f12" stroke-width="1"/>');
      svg.push('<circle cx="' + (pX + pL_px - 18) + '" cy="' + midY + '" r="2.5" fill="#facc15" stroke="#713f12" stroke-width="1"/>');
      svg.push('<circle cx="' + (pX + pL_px - 18) + '" cy="' + (midY + 8) + '" r="2.5" fill="#facc15" stroke="#713f12" stroke-width="1"/>');

      svg.push('<rect x="' + (midX - 145) + '" y="' + (midY - 11) + '" width="290" height="22" rx="3" fill="#0f172a" fill-opacity="0.88"/>');
      svg.push('<text x="' + midX + '" y="' + (midY + 4) + '" text-anchor="middle" font-size="9" font-weight="900" fill="#fde047" letter-spacing="0.5">CRICKET: 41 FT x 6.5 FT CURLED MATCH PITCH TURF</text>');
    }

    // Columns
    var xColSpans = [0, 14.3, 28.6, 42.9, 57.1, 71.4, 85.7, 100];
    var yColSpans = [0, 15, 30, 45, 60];

    for (var xi = 0; xi < xColSpans.length; xi++) {
      var colX = cx + (xColSpans[xi] * scale);
      svg.push('<rect x="' + (colX - 5) + '" y="' + (cy - 5) + '" width="10" height="10" rx="1.5" fill="#1e3a8a" stroke="#ffffff" stroke-width="1.5"/>');
      svg.push('<rect x="' + (colX - 5) + '" y="' + (cy + plotH_px - 5) + '" width="10" height="10" rx="1.5" fill="#1e3a8a" stroke="#ffffff" stroke-width="1.5"/>');
    }
    for (var yi = 1; yi < yColSpans.length - 1; yi++) {
      var colY = cy + (yColSpans[yi] * scale);
      svg.push('<rect x="' + (cx - 5) + '" y="' + (colY - 5) + '" width="10" height="10" rx="1.5" fill="#1e3a8a" stroke="#ffffff" stroke-width="1.5"/>');
      svg.push('<rect x="' + (cx + plotW_px - 5) + '" y="' + (colY - 5) + '" width="10" height="10" rx="1.5" fill="#1e3a8a" stroke="#ffffff" stroke-width="1.5"/>');
    }

    // Access Door
    var doorX = cx + (73.8 * scale);
    svg.push('<rect x="' + (doorX - 24) + '" y="' + (cy + plotH_px - 4) + '" width="48" height="8" fill="#22c55e" stroke="#ffffff" stroke-width="2" rx="2"/>');
    svg.push('<path d="M ' + (doorX - 24) + ' ' + (cy + plotH_px) + ' A 48 48 0 0 1 ' + (doorX + 24) + ' ' + (cy + plotH_px + 36) + '" fill="none" stroke="#22c55e" stroke-width="1.5" stroke-dasharray="3,3"/>');
    svg.push('<text x="' + doorX + '" y="' + (cy + plotH_px + 28) + '" text-anchor="middle" font-size="9.5" font-weight="bold" fill="#15803d">Player Access Gate (4 FT x 7 FT)</text>');

    // Dimensions
    var dimTopY = cy - 35;
    svg.push('<line x1="' + cx + '" y1="' + dimTopY + '" x2="' + (cx + plotW_px) + '" y2="' + dimTopY + '" stroke="#0f172a" stroke-width="2" marker-start="url(#dimArr100)" marker-end="url(#dimArr100)"/>');
    svg.push('<line x1="' + cx + '" y1="' + (dimTopY - 6) + '" x2="' + cx + '" y2="' + (cy - 6) + '" stroke="#94a3b8" stroke-width="1"/>');
    svg.push('<line x1="' + (cx + plotW_px) + '" y1="' + (dimTopY - 6) + '" x2="' + (cx + plotW_px) + '" y2="' + (cy - 6) + '" stroke="#94a3b8" stroke-width="1"/>');
    svg.push('<rect x="' + (midX - 110) + '" y="' + (dimTopY - 12) + '" width="220" height="22" rx="3" fill="#ffffff" stroke="#0f172a" stroke-width="1.5"/>');
    svg.push('<text x="' + midX + '" y="' + (dimTopY + 3.5) + '" text-anchor="middle" font-size="11.5" font-weight="900" fill="#0f172a">TOTAL LENGTH: 100 FT-0" (7 BAYS @ ~14.3 FT)</text>');

    var dimLeftX = cx - 55;
    svg.push('<line x1="' + dimLeftX + '" y1="' + cy + '" x2="' + dimLeftX + '" y2="' + (cy + plotH_px) + '" stroke="#0f172a" stroke-width="2" marker-start="url(#dimArr100)" marker-end="url(#dimArr100)"/>');
    svg.push('<line x1="' + (dimLeftX - 6) + '" y1="' + cy + '" x2="' + (cx - 6) + '" y2="' + cy + '" stroke="#94a3b8" stroke-width="1"/>');
    svg.push('<line x1="' + (dimLeftX - 6) + '" y1="' + (cy + plotH_px) + '" x2="' + (cx - 6) + '" y2="' + (cy + plotH_px) + '" stroke="#94a3b8" stroke-width="1"/>');
    svg.push('<rect x="' + (dimLeftX - 12) + '" y="' + (midY - 80) + '" width="24" height="160" rx="3" fill="#ffffff" stroke="#0f172a" stroke-width="1.5"/>');
    svg.push('<text x="' + (dimLeftX + 4.5) + '" y="' + midY + '" transform="rotate(-90 ' + (dimLeftX + 4.5) + ' ' + midY + ')" text-anchor="middle" font-size="11.5" font-weight="900" fill="#0f172a">WIDTH: 60 FT-0" (4 BAYS @ 15 FT)</text>');

    // Legend Bar
    var legY = cy + plotH_px + 44;
    svg.push('<rect x="' + (cx - 16) + '" y="' + legY + '" width="' + (plotW_px + 32) + '" height="52" rx="6" fill="#0f172a" stroke="#1e293b" stroke-width="1.5"/>');

    var bW = 226;
    var bH = 34;
    var bY = legY + 9;
    var bGap = 16;
    var bStartX = cx - 4;

    // Badge 1: 30mm Grass
    svg.push('<rect x="' + bStartX + '" y="' + bY + '" width="' + bW + '" height="' + bH + '" rx="4" fill="#14532d" stroke="#22c55e" stroke-width="1.5"/>');
    svg.push('<text x="' + (bStartX + bW/2) + '" y="' + (bY + 14) + '" text-anchor="middle" font-size="9" font-weight="900" fill="#86efac">UNIFIED PLAYING BED</text>');
    svg.push('<text x="' + (bStartX + bW/2) + '" y="' + (bY + 27) + '" text-anchor="middle" font-size="8" font-weight="700" fill="#ffffff">30mm Non-Infill Sports Grass</text>');

    // Badge 2: Cricket
    var bx2 = bStartX + bW + bGap;
    svg.push('<rect x="' + bx2 + '" y="' + bY + '" width="' + bW + '" height="' + bH + '" rx="4" fill="#78350f" stroke="#f59e0b" stroke-width="1.5"/>');
    svg.push('<text x="' + (bx2 + bW/2) + '" y="' + (bY + 14) + '" text-anchor="middle" font-size="9" font-weight="900" fill="#fde68a">BOX CRICKET</text>');
    svg.push('<text x="' + (bx2 + bW/2) + '" y="' + (bY + 27) + '" text-anchor="middle" font-size="8" font-weight="700" fill="#ffffff">41 FT x 6.5 FT Pitch + Stumps</text>');

    // Badge 3: Football
    var bx3 = bx2 + bW + bGap;
    svg.push('<rect x="' + bx3 + '" y="' + bY + '" width="' + bW + '" height="' + bH + '" rx="4" fill="#831843" stroke="#f43f5e" stroke-width="1.5"/>');
    svg.push('<text x="' + (bx3 + bW/2) + '" y="' + (bY + 14) + '" text-anchor="middle" font-size="9" font-weight="900" fill="#fecdd3">MINI FOOTBALL / FUTSAL</text>');
    svg.push('<text x="' + (bx3 + bW/2) + '" y="' + (bY + 27) + '" text-anchor="middle" font-size="8" font-weight="700" fill="#ffffff">Inlaid Lines + 1 Pair 10x6.5 FT Goals</text>');

    // Badge 4: Volleyball
    var bx4 = bx3 + bW + bGap;
    svg.push('<rect x="' + bx4 + '" y="' + bY + '" width="' + bW + '" height="' + bH + '" rx="4" fill="#713f12" stroke="#eab308" stroke-width="1.5"/>');
    svg.push('<text x="' + (bx4 + bW/2) + '" y="' + (bY + 14) + '" text-anchor="middle" font-size="9" font-weight="900" fill="#fef08a">VOLLEYBALL COURT</text>');
    svg.push('<text x="' + (bx4 + bW/2) + '" y="' + (bY + 27) + '" text-anchor="middle" font-size="8" font-weight="700" fill="#ffffff">59x29.5 FT Yellow Court + 8 FT Net</text>');

    // Badge 5: Badminton
    var bx5 = bx4 + bW + bGap;
    svg.push('<rect x="' + bx5 + '" y="' + bY + '" width="' + bW + '" height="' + bH + '" rx="4" fill="#083344" stroke="#06b6d4" stroke-width="1.5"/>');
    svg.push('<text x="' + (bx5 + bW/2) + '" y="' + (bY + 14) + '" text-anchor="middle" font-size="9" font-weight="900" fill="#a5f3fc">BADMINTON COURT</text>');
    svg.push('<text x="' + (bx5 + bW/2) + '" y="' + (bY + 27) + '" text-anchor="middle" font-size="8" font-weight="700" fill="#ffffff">44x20 FT Cyan Court + 5.1 FT Net</text>');

    svg.push('</svg>');
    container.innerHTML = svg.join('');
  }

  function renderMultiSportArena100x60SideElevation(container, config) {
    if (!container) return;
    var svgW = 1600;
    var svgH = 900;
    var scale = 11.8;
    var plotW_px = 100 * scale;
    var cx = (svgW - plotW_px) / 2;
    var groundY = 720;
    var H_ft = parseFloat(config.height) || 30;
    var hScale = 16.0;
    var structH_px = H_ft * hScale;
    var topY = groundY - structH_px;

    var svg = [];
    svg.push('<svg viewBox="0 0 ' + svgW + ' ' + svgH + '" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;background:#ffffff;font-family:Inter,Segoe UI,sans-serif;" preserveAspectRatio="xMidYMid meet">');
    svg.push('<defs>');
    svg.push('<marker id="dimArr100SE" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M 0 1.5 L 10 5 L 0 8.5 z" fill="#334155"/></marker>');
    svg.push('</defs>');

    // Title Bar
    svg.push('<rect x="' + (cx - 16) + '" y="16" width="' + (plotW_px + 32) + '" height="44" rx="6" fill="#0f172a" stroke="#1e293b" stroke-width="2"/>');
    svg.push('<text x="' + (svgW / 2) + '" y="' + 43 + '" text-anchor="middle" font-size="14.5" font-weight="900" fill="#ffffff" letter-spacing="1">2D LONGITUDINAL SIDE ELEVATION • 100 FT LENGTH SPAN × 30 FT CLEAR HEIGHT (16:9 RATIO)</text>');

    // Ground Line & Civil Bed
    svg.push('<rect x="' + (cx - 20) + '" y="' + groundY + '" width="' + (plotW_px + 40) + '" height="50" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="2"/>');
    svg.push('<line x1="120" y1="' + groundY + '" x2="1480" y2="' + groundY + '" stroke="#334155" stroke-width="3.5"/>');
    svg.push('<text x="140" y="' + (groundY + 30) + '" font-size="11" font-weight="bold" fill="#64748b">GROUND LEVEL &bull; 4 INCH COMPACTED WMM BASE + 1 INCH STONE DUST + 30mm NON-INFILL TURF</text>');

    // 2.5mm HDPE Netting Backdrop
    svg.push('<rect x="' + cx + '" y="' + topY + '" width="' + plotW_px + '" height="' + structH_px + '" fill="#ecfdf5" fill-opacity="0.6" stroke="#94a3b8" stroke-width="1.5" stroke-dasharray="4,4"/>');

    // 8 Columns along Length
    var xBays = [0, 14.3, 28.6, 42.9, 57.1, 71.4, 85.7, 100];
    for (var bi = 0; bi < xBays.length; bi++) {
      var px = cx + (xBays[bi] * scale);
      svg.push('<line x1="' + (px - 4) + '" y1="' + topY + '" x2="' + (px - 4) + '" y2="' + groundY + '" stroke="#0f172a" stroke-width="3"/>');
      svg.push('<line x1="' + (px + 4) + '" y1="' + topY + '" x2="' + (px + 4) + '" y2="' + groundY + '" stroke="#0f172a" stroke-width="3"/>');
      for (var ry = topY + 24; ry < groundY; ry += 24) {
        svg.push('<line x1="' + (px - 4) + '" y1="' + ry + '" x2="' + (px + 4) + '" y2="' + ry + '" stroke="#0f172a" stroke-width="1.8"/>');
      }
      svg.push('<rect x="' + (px - 14) + '" y="' + groundY + '" width="28" height="24" fill="#64748b" stroke="#334155" stroke-width="1.5"/>');
    }

    // 5 Horizontal Tiers
    var tiersH = [0.5, 7.9, 15.3, 22.6, 30.0];
    for (var ti = 0; ti < tiersH.length; ti++) {
      var ty = groundY - (tiersH[ti] * hScale);
      svg.push('<line x1="' + cx + '" y1="' + ty + '" x2="' + (cx + plotW_px) + '" y2="' + ty + '" stroke="#2563eb" stroke-width="2.5"/>');
      svg.push('<text x="' + (cx + plotW_px + 8) + '" y="' + (ty + 3.5) + '" font-size="8.5" font-weight="bold" fill="#1d4ed8">Tier ' + (ti + 1) + ' (' + tiersH[ti] + ' FT)</text>');
    }

    // Floodlights
    for (var li = 0; li < xBays.length; li++) {
      var lx = cx + (xBays[li] * scale);
      svg.push('<rect x="' + (lx - 8) + '" y="' + (topY - 14) + '" width="16" height="12" rx="2" fill="#facc15" stroke="#713f12" stroke-width="1.5"/>');
      svg.push('<polygon points="' + (lx - 20) + ',' + (topY + 30) + ' ' + (lx + 20) + ',' + (topY + 30) + ' ' + lx + ',' + (topY - 2) + '" fill="#fef08a" fill-opacity="0.35"/>');
    }

    // Pitch Profile
    var pL_px = 41 * scale;
    var midX = cx + (plotW_px / 2);
    var pStartX = midX - (pL_px / 2);
    svg.push('<rect x="' + pStartX + '" y="' + (groundY - 6) + '" width="' + pL_px + '" height="6" fill="#deb887" stroke="#854d0e" stroke-width="1.5"/>');
    svg.push('<text x="' + midX + '" y="' + (groundY + 18) + '" text-anchor="middle" font-size="10" font-weight="bold" fill="#854d0e">41 FT x 6.5 FT Cricket Pitch Strip (15mm Curled Wicket Turf)</text>');

    // Goal Post Profiles
    var gpH_px = 6.5 * hScale;
    var gpD_px = 3.5 * scale;
    svg.push('<path d="M ' + (cx + 18) + ' ' + groundY + ' L ' + (cx + 18) + ' ' + (groundY - gpH_px) + ' L ' + (cx + 18 + gpD_px) + ' ' + groundY + ' Z" fill="#fee2e2" stroke="#dc2626" stroke-width="2.5"/>');
    svg.push('<text x="' + (cx + 18 + gpD_px / 2) + '" y="' + (groundY - gpH_px - 8) + '" text-anchor="middle" font-size="9" font-weight="bold" fill="#dc2626">Goal 1 (6.5 FT H)</text>');

    svg.push('<path d="M ' + (cx + plotW_px - 18) + ' ' + groundY + ' L ' + (cx + plotW_px - 18) + ' ' + (groundY - gpH_px) + ' L ' + (cx + plotW_px - 18 - gpD_px) + ' ' + groundY + ' Z" fill="#fee2e2" stroke="#dc2626" stroke-width="2.5"/>');
    svg.push('<text x="' + (cx + plotW_px - 18 - gpD_px / 2) + '" y="' + (groundY - gpH_px - 8) + '" text-anchor="middle" font-size="9" font-weight="bold" fill="#dc2626">Goal 2 (6.5 FT H)</text>');

    // Volleyball Net Side Profile
    var vbH_px = 8.0 * hScale;
    svg.push('<line x1="' + midX + '" y1="' + groundY + '" x2="' + midX + '" y2="' + (groundY - vbH_px) + '" stroke="#eab308" stroke-width="4"/>');
    svg.push('<rect x="' + (midX - 4) + '" y="' + (groundY - vbH_px) + '" width="8" height="52" fill="#1e293b" stroke="#ffffff" stroke-width="1.5"/>');
    svg.push('<text x="' + (midX - 12) + '" y="' + (groundY - vbH_px - 8) + '" text-anchor="end" font-size="9" font-weight="bold" fill="#eab308">VB Net (8 FT H)</text>');

    // Badminton Net Side Profile
    var bmH_px = 5.08 * hScale;
    svg.push('<line x1="' + (midX + 28) + '" y1="' + groundY + '" x2="' + (midX + 28) + '" y2="' + (groundY - bmH_px) + '" stroke="#06b6d4" stroke-width="3"/>');
    svg.push('<rect x="' + (midX + 25) + '" y="' + (groundY - bmH_px) + '" width="6" height="40" fill="#881337" stroke="#ffffff" stroke-width="1"/>');
    svg.push('<text x="' + (midX + 38) + '" y="' + (groundY - bmH_px - 8) + '" text-anchor="start" font-size="9" font-weight="bold" fill="#06b6d4">BM Net (5.1 FT H)</text>');

    // Height Dimension
    var dimHX = cx - 60;
    svg.push('<line x1="' + dimHX + '" y1="' + groundY + '" x2="' + dimHX + '" y2="' + topY + '" stroke="#0f172a" stroke-width="2" marker-start="url(#dimArr100SE)" marker-end="url(#dimArr100SE)"/>');
    svg.push('<rect x="' + (dimHX - 10) + '" y="' + (groundY - structH_px / 2 - 35) + '" width="20" height="70" rx="3" fill="#ffffff" stroke="#0f172a" stroke-width="1.5"/>');
    svg.push('<text x="' + dimHX + '" y="' + (groundY - structH_px / 2 + 4) + '" transform="rotate(-90 ' + dimHX + ' ' + (groundY - structH_px / 2) + ')" text-anchor="middle" font-size="11" font-weight="900" fill="#0f172a">30 FT CLEAR HEIGHT</text>');

    // Bottom Dimension
    var dimBotY = groundY + 54;
    svg.push('<line x1="' + cx + '" y1="' + dimBotY + '" x2="' + (cx + plotW_px) + '" y2="' + dimBotY + '" stroke="#0f172a" stroke-width="2" marker-start="url(#dimArr100SE)" marker-end="url(#dimArr100SE)"/>');
    svg.push('<text x="' + (svgW / 2) + '" y="' + (dimBotY + 22) + '" text-anchor="middle" font-size="12" font-weight="bold" fill="#0f172a">TOTAL SPAN LENGTH: 100 FT-0" (7 BAYS @ ~14.3 FT c/c)</text>');

    svg.push('</svg>');
    container.innerHTML = svg.join('');
  }

  function renderMultiSportArena100x60CrossElevation(container, config) {
    if (!container) return;
    var svgW = 1600;
    var svgH = 900;
    var scale = 14.5;
    var W_ft = parseFloat(config.width) || 60;
    var plotW_px = W_ft * scale;
    var cx = (svgW - plotW_px) / 2;
    var groundY = 720;
    var H_ft = parseFloat(config.height) || 30;
    var hScale = 16.0;
    var structH_px = H_ft * hScale;
    var topY = groundY - structH_px;

    var svg = [];
    svg.push('<svg viewBox="0 0 ' + svgW + ' ' + svgH + '" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;background:#ffffff;font-family:Inter,Segoe UI,sans-serif;" preserveAspectRatio="xMidYMid meet">');
    svg.push('<defs>');
    svg.push('<marker id="dimArr100CE" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M 0 1.5 L 10 5 L 0 8.5 z" fill="#334155"/></marker>');
    svg.push('</defs>');

    // Title Bar
    svg.push('<rect x="' + (cx - 16) + '" y="16" width="' + (plotW_px + 32) + '" height="44" rx="6" fill="#0f172a" stroke="#1e293b" stroke-width="2"/>');
    svg.push('<text x="' + (svgW / 2) + '" y="' + 43 + '" text-anchor="middle" font-size="14.5" font-weight="900" fill="#ffffff" letter-spacing="1">2D CROSS SIDE ELEVATION • 60 FT WIDTH SPAN × 30 FT CLEAR HEIGHT (16:9 RATIO)</text>');

    // Ground Line
    svg.push('<rect x="' + (cx - 20) + '" y="' + groundY + '" width="' + (plotW_px + 40) + '" height="50" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="2"/>');
    svg.push('<line x1="240" y1="' + groundY + '" x2="1360" y2="' + groundY + '" stroke="#334155" stroke-width="3.5"/>');
    svg.push('<text x="260" y="' + (groundY + 30) + '" font-size="11" font-weight="bold" fill="#64748b">GROUND LEVEL &bull; 4 INCH COMPACTED WMM BASE &bull; 30mm NON-INFILL MULTI-SPORT TURF</text>');

    // Netting Screen Backdrop
    svg.push('<rect x="' + cx + '" y="' + topY + '" width="' + plotW_px + '" height="' + structH_px + '" fill="#ecfdf5" fill-opacity="0.6" stroke="#94a3b8" stroke-width="1.5" stroke-dasharray="4,4"/>');

    // 5 Columns along Width
    var yBays = [0, 15, 30, 45, 60];
    for (var bi = 0; bi < yBays.length; bi++) {
      var px = cx + (yBays[bi] * scale);
      svg.push('<line x1="' + (px - 4) + '" y1="' + topY + '" x2="' + (px - 4) + '" y2="' + groundY + '" stroke="#0f172a" stroke-width="3"/>');
      svg.push('<line x1="' + (px + 4) + '" y1="' + topY + '" x2="' + (px + 4) + '" y2="' + groundY + '" stroke="#0f172a" stroke-width="3"/>');
      for (var ry = topY + 24; ry < groundY; ry += 24) {
        svg.push('<line x1="' + (px - 4) + '" y1="' + ry + '" x2="' + (px + 4) + '" y2="' + ry + '" stroke="#0f172a" stroke-width="1.8"/>');
      }
      svg.push('<rect x="' + (px - 14) + '" y="' + groundY + '" width="28" height="24" fill="#64748b" stroke="#334155" stroke-width="1.5"/>');
    }

    // 5 Horizontal Tiers
    var tiersH = [0.5, 7.9, 15.3, 22.6, 30.0];
    for (var ti = 0; ti < tiersH.length; ti++) {
      var ty = groundY - (tiersH[ti] * hScale);
      svg.push('<line x1="' + cx + '" y1="' + ty + '" x2="' + (cx + plotW_px) + '" y2="' + ty + '" stroke="#2563eb" stroke-width="2.5"/>');
      svg.push('<text x="' + (cx + plotW_px + 8) + '" y="' + (ty + 3.5) + '" font-size="8.5" font-weight="bold" fill="#1d4ed8">Tier ' + (ti + 1) + ' (' + tiersH[ti] + ' FT)</text>');
    }

    // Floodlights
    for (var li = 0; li < yBays.length; li++) {
      var lx = cx + (yBays[li] * scale);
      svg.push('<rect x="' + (lx - 8) + '" y="' + (topY - 14) + '" width="16" height="12" rx="2" fill="#facc15" stroke="#713f12" stroke-width="1.5"/>');
    }

    // Mini Football Goal Post (Front View)
    var midX = cx + (plotW_px / 2);
    var gpW_px = 10 * scale;
    var gpH_px = 6.5 * hScale;
    var gpLeftX = midX - (gpW_px / 2);

    svg.push('<rect x="' + gpLeftX + '" y="' + (groundY - gpH_px) + '" width="' + gpW_px + '" height="' + gpH_px + '" fill="#fee2e2" fill-opacity="0.8" stroke="#dc2626" stroke-width="3" rx="2"/>');
    svg.push('<text x="' + midX + '" y="' + (groundY - gpH_px / 2 + 4) + '" text-anchor="middle" font-size="11" font-weight="900" fill="#dc2626">MINI FOOTBALL GOAL (10 FT W x 6.5 FT H)</text>');

    // Volleyball Net (Front View)
    var vbW_px = 32 * scale;
    var vbH_px = 8.0 * hScale;
    var vbLeftX = midX - (vbW_px / 2);

    svg.push('<line x1="' + vbLeftX + '" y1="' + groundY + '" x2="' + vbLeftX + '" y2="' + (groundY - vbH_px) + '" stroke="#eab308" stroke-width="4"/>');
    svg.push('<line x1="' + (vbLeftX + vbW_px) + '" y1="' + groundY + '" x2="' + (vbLeftX + vbW_px) + '" y2="' + (groundY - vbH_px) + '" stroke="#eab308" stroke-width="4"/>');
    svg.push('<rect x="' + vbLeftX + '" y="' + (groundY - vbH_px) + '" width="' + vbW_px + '" height="52" fill="#1e293b" fill-opacity="0.5" stroke="#eab308" stroke-width="2"/>');
    svg.push('<line x1="' + vbLeftX + '" y1="' + (groundY - vbH_px) + '" x2="' + (vbLeftX + vbW_px) + '" y2="' + (groundY - vbH_px) + '" stroke="#ffffff" stroke-width="3"/>');
    svg.push('<text x="' + midX + '" y="' + (groundY - vbH_px - 8) + '" text-anchor="middle" font-size="10" font-weight="900" fill="#eab308">REGULATION VOLLEYBALL NET (32 FT SPAN @ 8 FT HEIGHT)</text>');

    // Height Dimension
    var dimHX = cx - 60;
    svg.push('<line x1="' + dimHX + '" y1="' + groundY + '" x2="' + dimHX + '" y2="' + topY + '" stroke="#0f172a" stroke-width="2" marker-start="url(#dimArr100CE)" marker-end="url(#dimArr100CE)"/>');
    svg.push('<text x="' + dimHX + '" y="' + (groundY - structH_px / 2) + '" transform="rotate(-90 ' + dimHX + ' ' + (groundY - structH_px / 2) + ')" text-anchor="middle" font-size="11" font-weight="900" fill="#0f172a">30 FT CLEAR HEIGHT</text>');

    // Width Dimension
    var dimBotY = groundY + 54;
    svg.push('<line x1="' + cx + '" y1="' + dimBotY + '" x2="' + (cx + plotW_px) + '" y2="' + dimBotY + '" stroke="#0f172a" stroke-width="2" marker-start="url(#dimArr100CE)" marker-end="url(#dimArr100CE)"/>');
    svg.push('<text x="' + (svgW / 2) + '" y="' + (dimBotY + 22) + '" text-anchor="middle" font-size="12" font-weight="bold" fill="#0f172a">TOTAL SPAN WIDTH: 60 FT-0" (4 BAYS @ 15 FT c/c)</text>');

    svg.push('</svg>');
    container.innerHTML = svg.join('');
  }

  function renderMultiSportArena185x60FloorPlan160(container, config) {
    if (!container) return;
    var svgW = 1600;
    var svgH = 750;
    var scale = 7.2; // 7.2 px per foot (185 FT * 7.2 = 1332 px, 60 FT * 7.2 = 432 px)
    var plotW_px = 185 * scale; // 1332 px
    var plotH_px = 60 * scale;  // 432 px
    var cx = (svgW - plotW_px) / 2; // 134 px
    var cy = (svgH - plotH_px) / 2 + 30; // 174 px

    var svg = [];
    svg.push('<svg viewBox="0 0 ' + svgW + ' ' + svgH + '" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;background:#ffffff;font-family:Inter,Segoe UI,sans-serif;" preserveAspectRatio="xMidYMid meet">');
    svg.push('<defs>');
    svg.push('<marker id="cadArr185_160" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M 0 1.5 L 10 5 L 0 8.5 z" fill="#0f172a"/></marker>');
    svg.push('<marker id="dimArr185_160" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M 0 1.5 L 10 5 L 0 8.5 z" fill="#334155"/></marker>');
    svg.push('</defs>');

    // Title Sheet Header Bar
    svg.push('<rect x="' + cx + '" y="16" width="' + plotW_px + '" height="44" rx="5" fill="#0f172a" stroke="#1e293b" stroke-width="2"/>');
    svg.push('<text x="' + (svgW / 2) + '" y="' + 43 + '" text-anchor="middle" font-size="15.5" font-weight="900" fill="#ffffff" letter-spacing="1">ARCHITECTURAL 2D FLOOR PLAN • 185 FT × 60 FT ARENA (160×60 FT COMPLEX + 25 FT OPEN SPACE)</text>');

    // Master Outer Boundary Bed
    svg.push('<rect x="' + (cx - 10) + '" y="' + (cy - 10) + '" width="' + (plotW_px + 20) + '" height="' + (plotH_px + 20) + '" rx="6" fill="#f8fafc" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6,4"/>');

    // ─────────────────────────────────────────────────────────────────────────
    // ZONE 1: 100 FT × 60 FT BOX CRICKET GROUND (6,000 SFT)
    // ─────────────────────────────────────────────────────────────────────────
    var z1W_px = 100 * scale; // 720 px
    svg.push('<rect x="' + cx + '" y="' + cy + '" width="' + z1W_px + '" height="' + plotH_px + '" fill="#15803d" stroke="#166534" stroke-width="2.5"/>');

    // Alternating Turf Stripes (40mm Landscape Zero Infill Outfield)
    for (var ts = cx; ts < cx + z1W_px; ts += 45) {
      var wStrip = Math.min(45, (cx + z1W_px) - ts);
      if (Math.floor((ts - cx) / 45) % 2 === 1) {
        svg.push('<rect x="' + ts + '" y="' + cy + '" width="' + wStrip + '" height="' + plotH_px + '" fill="#16a34a" fill-opacity="0.35"/>');
      }
    }

    // Cricket Perimeter Boundary Lines
    svg.push('<rect x="' + (cx + 12) + '" y="' + (cy + 12) + '" width="' + (z1W_px - 24) + '" height="' + (plotH_px - 24) + '" fill="none" stroke="#ffffff" stroke-width="2" stroke-dasharray="8,6"/>');

    // Top Badge: BED & OPEN-TOP 1.5mm 40mm MESH
    svg.push('<rect x="' + (cx + z1W_px / 2 - 210) + '" y="' + (cy + 14) + '" width="420" height="25" rx="4" fill="#0f172a" stroke="#eab308" stroke-width="1.8"/>');
    svg.push('<text x="' + (cx + z1W_px / 2) + '" y="' + (cy + 31) + '" text-anchor="middle" font-size="10" font-weight="900" fill="#facc15" letter-spacing="0.5">BED: 4\" PCC M15 • OPEN-TOP 1.5mm 40mm MESH (WIRE ROPE GRID — NO TOP STEEL)</text>');

    // 83 FT × 6.5 FT Centered Match Pitch Concrete Pad + Curled Turf
    var pL_px = 83 * scale; // 597.6 px
    var pW_px = 6.5 * scale; // 46.8 px
    var pX = cx + (z1W_px - pL_px) / 2;
    var pY = cy + (plotH_px - pW_px) / 2;

    svg.push('<rect x="' + pX + '" y="' + pY + '" width="' + pL_px + '" height="' + pW_px + '" rx="2" fill="#deb887" stroke="#854d0e" stroke-width="2"/>');

    // Regulation White Creases
    svg.push('<g stroke="#ffffff" stroke-width="2">');
    svg.push('<line x1="' + (pX + 16) + '" y1="' + pY + '" x2="' + (pX + 16) + '" y2="' + (pY + pW_px) + '"/>');
    svg.push('<line x1="' + (pX + pL_px - 16) + '" y1="' + pY + '" x2="' + (pX + pL_px - 16) + '" y2="' + (pY + pW_px) + '"/>');
    var popDist = 4 * scale;
    svg.push('<line x1="' + (pX + 16 + popDist) + '" y1="' + (pY - 4) + '" x2="' + (pX + 16 + popDist) + '" y2="' + (pY + pW_px + 4) + '"/>');
    svg.push('<line x1="' + (pX + pL_px - 16 - popDist) + '" y1="' + (pY - 4) + '" x2="' + (pX + pL_px - 16 - popDist) + '" y2="' + (pY + pW_px + 4) + '"/>');
    svg.push('</g>');

    // Stumps
    svg.push('<circle cx="' + (pX + 16) + '" cy="' + (pY + pW_px / 2) + '" r="3.5" fill="#facc15" stroke="#713f12" stroke-width="1"/>');
    svg.push('<circle cx="' + (pX + pL_px - 16) + '" cy="' + (pY + pW_px / 2) + '" r="3.5" fill="#facc15" stroke="#713f12" stroke-width="1"/>');

    // Pitch Label
    svg.push('<text x="' + (cx + z1W_px / 2) + '" y="' + (cy + plotH_px / 2 + 4.5) + '" text-anchor="middle" font-size="11" font-weight="900" fill="#713f12" letter-spacing="0.5">83 FT × 6.5 FT CURLED MATCH PITCH TURF</text>');

    // Bottom Label
    svg.push('<rect x="' + (cx + 18) + '" y="' + (cy + plotH_px - 36) + '" width="' + (z1W_px - 36) + '" height="26" rx="4" fill="#0f172a" fill-opacity="0.9"/>');
    svg.push('<text x="' + (cx + z1W_px / 2) + '" y="' + (cy + plotH_px - 19) + '" text-anchor="middle" font-size="11" font-weight="900" fill="#ffffff">ZONE 1: 100 FT × 60 FT BOX CRICKET GROUND (6,000 SFT) • 40mm LANDSCAPE OUTFIELD (ZERO INFILL)</text>');

    // Door Callout: Cricket Sideline Player Door (4×7 FT)
    var d1X = cx + 260;
    svg.push('<rect x="' + (d1X - 110) + '" y="' + (cy - 38) + '" width="220" height="24" rx="4" fill="#fef08a" stroke="#ca8a04" stroke-width="1.5"/>');
    svg.push('<text x="' + d1X + '" y="' + (cy - 22) + '" text-anchor="middle" font-size="10.5" font-weight="bold" fill="#854d0e">🚪 Cricket Sideline Player Door (4×7 FT)</text>');
    svg.push('<line x1="' + d1X + '" y1="' + (cy - 14) + '" x2="' + d1X + '" y2="' + cy + '" stroke="#ca8a04" stroke-width="2"/>');

    // ─────────────────────────────────────────────────────────────────────────
    // ZONE 2: BADMINTON & AMENITIES COMPLEX (100 to 160 FT = 60×60 FT = 3,600 SFT)
    // 0.50mm PPGL ROOF + 0.50mm GI WALL CLADDING (ALL 4 SIDES)
    // ─────────────────────────────────────────────────────────────────────────
    var z2X = cx + z1W_px;
    var z2W_px = 60 * scale; // 432 px
    var courtW_px = 30 * scale; // 216 px

    // Zone 2 Blue Flooring
    svg.push('<rect x="' + z2X + '" y="' + cy + '" width="' + z2W_px + '" height="' + plotH_px + '" fill="#0284c7" stroke="#0369a1" stroke-width="2"/>');

    // Perimeter Wall Cladding Band (All 4 Sides, 240 RFT)
    svg.push('<rect x="' + (z2X + 3) + '" y="' + (cy + 3) + '" width="' + (z2W_px - 6) + '" height="' + (plotH_px - 6) + '" fill="none" stroke="#1e3a8a" stroke-width="7"/>');
    svg.push('<rect x="' + (z2X + 6) + '" y="' + (cy + 6) + '" width="' + (z2W_px - 12) + '" height="' + (plotH_px - 12) + '" fill="none" stroke="#60a5fa" stroke-width="2" stroke-dasharray="6,4"/>');

    // Center dividing line between North bay and South bay
    svg.push('<line x1="' + z2X + '" y1="' + (cy + courtW_px) + '" x2="' + (z2X + z2W_px) + '" y2="' + (cy + courtW_px) + '" stroke="#0369a1" stroke-width="2.5" stroke-dasharray="8,4"/>');

    // Top Callout Badge for Zone 2
    svg.push('<rect x="' + (z2X + 16) + '" y="' + (cy + 12) + '" width="' + (z2W_px - 32) + '" height="24" rx="4" fill="#0f172a" fill-opacity="0.95" stroke="#38bdf8" stroke-width="1.5"/>');
    svg.push('<text x="' + (z2X + z2W_px / 2) + '" y="' + (cy + 28) + '" text-anchor="middle" font-size="9" font-weight="900" fill="#38bdf8">0.50mm PPGL METAL ROOF + GI WALL CLADDING (ALL 4 SIDES) • 60 FT × 60 FT COMPLEX</text>');

    // ═════════════════════════════════════════════════════════════════════════
    // NORTH BAY: TOP HALF (30 FT WIDTH, Y = cy to cy + 216 px)
    // 1. [LT] Ladies Toilet (15×12 FT) at X: 100 to 115 FT (Outer North Wall for easy ventilation)
    // 2. [O] Office Room (15×18 FT) at X: 100 to 115 FT (Inner side near center)
    // 3. Badminton Court 1 (45×30 FT bay, 44×20 FT court) at X: 115 to 160 FT
    // ═════════════════════════════════════════════════════════════════════════
    var offW_px = 15 * scale; // 108 px (15 FT)
    var ltH_px  = 12 * scale; // 86.4 px (12 FT)
    var offH_px = 18 * scale; // 129.6 px (18 FT)

    // [LT] Ladies Toilet (15 FT × 12 FT = 180 SFT) at OUTER NORTH WALL for easy natural ventilation
    svg.push('<rect x="' + (z2X + 6) + '" y="' + (cy + 6) + '" width="' + (offW_px - 8) + '" height="' + (ltH_px - 6) + '" fill="#fdf2f8" stroke="#be185d" stroke-width="3"/>');
    // External Wall Ventilation Louvers on North Wall
    svg.push('<rect x="' + (z2X + 22) + '" y="' + (cy + 1) + '" width="64" height="6" fill="#be185d" rx="1"/>');
    svg.push('<line x1="' + (z2X + 32) + '" y1="' + cy + '" x2="' + (z2X + 32) + '" y2="' + (cy + 7) + '" stroke="#ffffff" stroke-width="1.5"/>');
    svg.push('<line x1="' + (z2X + 48) + '" y1="' + cy + '" x2="' + (z2X + 48) + '" y2="' + (cy + 7) + '" stroke="#ffffff" stroke-width="1.5"/>');
    svg.push('<line x1="' + (z2X + 64) + '" y1="' + cy + '" x2="' + (z2X + 64) + '" y2="' + (cy + 7) + '" stroke="#ffffff" stroke-width="1.5"/>');
    // Vanity Counter
    svg.push('<rect x="' + (z2X + 14) + '" y="' + (cy + 14) + '" width="24" height="34" rx="3" fill="#fbcfe8" stroke="#db2777" stroke-width="1.5"/>');
    svg.push('<ellipse cx="' + (z2X + 26) + '" cy="' + (cy + 31) + '" rx="7" ry="9" fill="#ffffff" stroke="#db2777" stroke-width="1"/>');
    // WC Cubicles
    svg.push('<rect x="' + (z2X + 50) + '" y="' + (cy + 8) + '" width="46" height="34" fill="none" stroke="#f472b6" stroke-width="1.2"/>');
    svg.push('<circle cx="' + (z2X + 74) + '" cy="' + (cy + 25) + '" r="7" fill="#ffffff" stroke="#db2777" stroke-width="1.5"/>');
    svg.push('<rect x="' + (z2X + 50) + '" y="' + (cy + 46) + '" width="46" height="34" fill="none" stroke="#f472b6" stroke-width="1.2"/>');
    svg.push('<circle cx="' + (z2X + 74) + '" cy="' + (cy + 63) + '" r="7" fill="#ffffff" stroke="#db2777" stroke-width="1.5"/>');
    // Clean Badged Labels
    svg.push('<rect x="' + (z2X + 10) + '" y="' + (cy + 20) + '" width="' + (offW_px - 20) + '" height="52" rx="4" fill="#ffffff" fill-opacity="0.94" stroke="#db2777" stroke-width="1"/>');
    svg.push('<text x="' + (z2X + offW_px / 2) + '" y="' + (cy + 36) + '" text-anchor="middle" font-size="9.5" font-weight="900" fill="#9d174d">[LT] LADIES TOILET</text>');
    svg.push('<text x="' + (z2X + offW_px / 2) + '" y="' + (cy + 50) + '" text-anchor="middle" font-size="8" font-weight="bold" fill="#db2777">15 FT × 12 FT (180 SFT)</text>');
    svg.push('<text x="' + (z2X + offW_px / 2) + '" y="' + (cy + 64) + '" text-anchor="middle" font-size="7" font-weight="900" fill="#be185d">💨 OUTER VENTILATION</text>');

    // [O] Office Room (15 FT × 18 FT = 270 SFT) on INNER SIDE (touching center dividing line)
    svg.push('<rect x="' + (z2X + 6) + '" y="' + (cy + ltH_px + 2) + '" width="' + (offW_px - 8) + '" height="' + (offH_px - 6) + '" fill="#f8fafc" stroke="#1e293b" stroke-width="3"/>');
    // Office Furniture
    svg.push('<rect x="' + (z2X + 22) + '" y="' + (cy + ltH_px + 16) + '" width="54" height="24" rx="3" fill="#cbd5e1" stroke="#475569" stroke-width="1.5"/>');
    svg.push('<rect x="' + (z2X + 38) + '" y="' + (cy + ltH_px + 20) + '" width="22" height="7" rx="1" fill="#0f172a"/>');
    svg.push('<circle cx="' + (z2X + 49) + '" cy="' + (cy + ltH_px + 9) + '" r="6.5" fill="#2563eb" stroke="#1d4ed8" stroke-width="1"/>');
    svg.push('<rect x="' + (z2X + 28) + '" y="' + (cy + ltH_px + 48) + '" width="18" height="12" rx="2" fill="#94a3b8"/>');
    svg.push('<rect x="' + (z2X + 54) + '" y="' + (cy + ltH_px + 48) + '" width="18" height="12" rx="2" fill="#94a3b8"/>');
    // Door swing
    svg.push('<path d="M ' + (z2X + offW_px - 2) + ' ' + (cy + ltH_px + 78) + ' A 24 24 0 0 0 ' + (z2X + offW_px - 26) + ' ' + (cy + ltH_px + 102) + '" fill="none" stroke="#2563eb" stroke-width="1.5" stroke-dasharray="3,2"/>');
    svg.push('<line x1="' + (z2X + offW_px - 2) + '" y1="' + (cy + ltH_px + 78) + '" x2="' + (z2X + offW_px - 26) + '" y2="' + (cy + ltH_px + 102) + '" stroke="#2563eb" stroke-width="2"/>');
    // Clean Badged Labels
    svg.push('<rect x="' + (z2X + 10) + '" y="' + (cy + ltH_px + 48) + '" width="' + (offW_px - 20) + '" height="42" rx="4" fill="#ffffff" fill-opacity="0.94" stroke="#1e293b" stroke-width="1"/>');
    svg.push('<text x="' + (z2X + offW_px / 2) + '" y="' + (cy + ltH_px + 66) + '" text-anchor="middle" font-size="10" font-weight="900" fill="#0f172a">[O] OFFICE ROOM</text>');
    svg.push('<text x="' + (z2X + offW_px / 2) + '" y="' + (cy + ltH_px + 80) + '" text-anchor="middle" font-size="8" font-weight="bold" fill="#475569">15 FT × 18 FT (270 SFT)</text>');

    // Badminton Court 1 (①) at X: 115 to 160 FT (45 FT = 324 px)
    var c1BayX = z2X + offW_px;
    var badL_px = 44 * scale; // 316.8 px
    var badW_px = 20 * scale; // 144 px
    var badX1 = c1BayX + (45 * scale - badL_px) / 2;
    var badY1 = cy + (courtW_px - badW_px) / 2;

    // Court 1 Mat
    svg.push('<rect x="' + badX1 + '" y="' + badY1 + '" width="' + badL_px + '" height="' + badW_px + '" fill="#059669" stroke="#ffffff" stroke-width="2.5"/>');
    // Center Net Line
    var midNetX1 = badX1 + badL_px / 2;
    svg.push('<line x1="' + midNetX1 + '" y1="' + badY1 + '" x2="' + midNetX1 + '" y2="' + (badY1 + badW_px) + '" stroke="#dc2626" stroke-width="3.5"/>');
    // Net Posts
    svg.push('<circle cx="' + midNetX1 + '" cy="' + (badY1 - 2) + '" r="4" fill="#facc15" stroke="#854d0e" stroke-width="1"/>');
    svg.push('<circle cx="' + midNetX1 + '" cy="' + (badY1 + badW_px + 2) + '" r="4" fill="#facc15" stroke="#854d0e" stroke-width="1"/>');
    // Lines
    var sServDist = 6.5 * scale;
    var dServDist = 2.5 * scale;
    var sSideDist = 1.5 * scale;
    svg.push('<line x1="' + (midNetX1 - sServDist) + '" y1="' + badY1 + '" x2="' + (midNetX1 - sServDist) + '" y2="' + (badY1 + badW_px) + '" stroke="#ffffff" stroke-width="1.8"/>');
    svg.push('<line x1="' + (midNetX1 + sServDist) + '" y1="' + badY1 + '" x2="' + (midNetX1 + sServDist) + '" y2="' + (badY1 + badW_px) + '" stroke="#ffffff" stroke-width="1.8"/>');
    svg.push('<line x1="' + (badX1 + dServDist) + '" y1="' + badY1 + '" x2="' + (badX1 + dServDist) + '" y2="' + (badY1 + badW_px) + '" stroke="#ffffff" stroke-width="1.8"/>');
    svg.push('<line x1="' + (badX1 + badL_px - dServDist) + '" y1="' + badY1 + '" x2="' + (badX1 + badL_px - dServDist) + '" y2="' + (badY1 + badW_px) + '" stroke="#ffffff" stroke-width="1.8"/>');
    svg.push('<line x1="' + badX1 + '" y1="' + (badY1 + sSideDist) + '" x2="' + (badX1 + badL_px) + '" y2="' + (badY1 + sSideDist) + '" stroke="#ffffff" stroke-width="1.8"/>');
    svg.push('<line x1="' + badX1 + '" y1="' + (badY1 + badW_px - sSideDist) + '" x2="' + (badX1 + badL_px) + '" y2="' + (badY1 + badW_px - sSideDist) + '" stroke="#ffffff" stroke-width="1.8"/>');
    var midY1 = badY1 + badW_px / 2;
    svg.push('<line x1="' + badX1 + '" y1="' + midY1 + '" x2="' + (midNetX1 - sServDist) + '" y2="' + midY1 + '" stroke="#ffffff" stroke-width="1.8"/>');
    svg.push('<line x1="' + (midNetX1 + sServDist) + '" y1="' + midY1 + '" x2="' + (badX1 + badL_px) + '" y2="' + midY1 + '" stroke="#ffffff" stroke-width="1.8"/>');
    // Court 1 Label
    svg.push('<rect x="' + (badX1 + badL_px / 2 - 120) + '" y="' + (badY1 + badW_px / 2 - 13) + '" width="240" height="26" rx="4" fill="#0f172a" fill-opacity="0.9" stroke="#38bdf8" stroke-width="1"/>');
    svg.push('<text x="' + (badX1 + badL_px / 2) + '" y="' + (badY1 + badW_px / 2 + 4) + '" text-anchor="middle" font-size="10.5" font-weight="900" fill="#ffffff" letter-spacing="1">🏸 COURT ① (44 FT × 20 FT BWF DOUBLES)</text>');

    // ═════════════════════════════════════════════════════════════════════════
    // SOUTH BAY: BOTTOM HALF (30 FT WIDTH, Y = cy + 216 px to cy + 432 px)
    // 1. Badminton Court 2 (45×30 FT bay, 44×20 FT court) at X: 100 to 145 FT
    // 2. [L] Reception Lobby (15×18 FT) at X: 145 to 160 FT (Inner/Center side with Main Entrance)
    // 3. [GT] Gents Toilet (15×12 FT) at X: 145 to 160 FT (Outer South Wall for easy ventilation)
    // ═════════════════════════════════════════════════════════════════════════
    var badX2 = z2X + (45 * scale - badL_px) / 2;
    var badY2 = cy + courtW_px + (courtW_px - badW_px) / 2;

    // Court 2 Mat
    svg.push('<rect x="' + badX2 + '" y="' + badY2 + '" width="' + badL_px + '" height="' + badW_px + '" fill="#059669" stroke="#ffffff" stroke-width="2.5"/>');
    var midNetX2 = badX2 + badL_px / 2;
    svg.push('<line x1="' + midNetX2 + '" y1="' + badY2 + '" x2="' + midNetX2 + '" y2="' + (badY2 + badW_px) + '" stroke="#dc2626" stroke-width="3.5"/>');
    svg.push('<circle cx="' + midNetX2 + '" cy="' + (badY2 - 2) + '" r="4" fill="#facc15" stroke="#854d0e" stroke-width="1"/>');
    svg.push('<circle cx="' + midNetX2 + '" cy="' + (badY2 + badW_px + 2) + '" r="4" fill="#facc15" stroke="#854d0e" stroke-width="1"/>');
    svg.push('<line x1="' + (midNetX2 - sServDist) + '" y1="' + badY2 + '" x2="' + (midNetX2 - sServDist) + '" y2="' + (badY2 + badW_px) + '" stroke="#ffffff" stroke-width="1.8"/>');
    svg.push('<line x1="' + (midNetX2 + sServDist) + '" y1="' + badY2 + '" x2="' + (midNetX2 + sServDist) + '" y2="' + (badY2 + badW_px) + '" stroke="#ffffff" stroke-width="1.8"/>');
    svg.push('<line x1="' + (badX2 + dServDist) + '" y1="' + badY2 + '" x2="' + (badX2 + dServDist) + '" y2="' + (badY2 + badW_px) + '" stroke="#ffffff" stroke-width="1.8"/>');
    svg.push('<line x1="' + (badX2 + badL_px - dServDist) + '" y1="' + badY2 + '" x2="' + (badX2 + badL_px - dServDist) + '" y2="' + (badY2 + badW_px) + '" stroke="#ffffff" stroke-width="1.8"/>');
    svg.push('<line x1="' + badX2 + '" y1="' + (badY2 + sSideDist) + '" x2="' + (badX2 + badL_px) + '" y2="' + (badY2 + sSideDist) + '" stroke="#ffffff" stroke-width="1.8"/>');
    svg.push('<line x1="' + badX2 + '" y1="' + (badY2 + badW_px - sSideDist) + '" x2="' + (badX2 + badL_px) + '" y2="' + (badY2 + badW_px - sSideDist) + '" stroke="#ffffff" stroke-width="1.8"/>');
    var midY2 = badY2 + badW_px / 2;
    svg.push('<line x1="' + badX2 + '" y1="' + midY2 + '" x2="' + (midNetX2 - sServDist) + '" y2="' + midY2 + '" stroke="#ffffff" stroke-width="1.8"/>');
    svg.push('<line x1="' + (midNetX2 + sServDist) + '" y1="' + midY2 + '" x2="' + (badX2 + badL_px) + '" y2="' + midY2 + '" stroke="#ffffff" stroke-width="1.8"/>');
    // Court 2 Label
    svg.push('<rect x="' + (badX2 + badL_px / 2 - 120) + '" y="' + (badY2 + badW_px / 2 - 13) + '" width="240" height="26" rx="4" fill="#0f172a" fill-opacity="0.9" stroke="#38bdf8" stroke-width="1"/>');
    svg.push('<text x="' + (badX2 + badL_px / 2) + '" y="' + (badY2 + badW_px / 2 + 4) + '" text-anchor="middle" font-size="10.5" font-weight="900" fill="#ffffff" letter-spacing="1">🏸 COURT ② (44 FT × 20 FT BWF DOUBLES)</text>');

    // [L] Reception Lobby & [GT] Gents Toilet at X: 145 to 160 FT
    var amenX = z2X + 45 * scale;
    var lobH_px = 18 * scale; // 129.6 px (18 FT)
    var gtH_px  = 12 * scale; // 86.4 px (12 FT)

    // [L] Reception Lobby (15 FT × 18 FT = 270 SFT) at INNER / CENTER SIDE (Direct Main Entrance)
    svg.push('<rect x="' + (amenX + 2) + '" y="' + (cy + courtW_px + 2) + '" width="' + (offW_px - 8) + '" height="' + (lobH_px - 6) + '" fill="#fefce8" stroke="#ca8a04" stroke-width="3"/>');
    // Reception Counter
    svg.push('<rect x="' + (amenX + 12) + '" y="' + (cy + courtW_px + 14) + '" width="46" height="20" rx="3" fill="#fde047" stroke="#ca8a04" stroke-width="1.5"/>');
    svg.push('<circle cx="' + (amenX + 35) + '" cy="' + (cy + courtW_px + 42) + '" r="6" fill="#ca8a04"/>');
    // Waiting Lounge Sofa
    svg.push('<rect x="' + (amenX + 12) + '" y="' + (cy + courtW_px + 62) + '" width="54" height="16" rx="3" fill="#cbd5e1" stroke="#64748b"/>');
    svg.push('<rect x="' + (amenX + 12) + '" y="' + (cy + courtW_px + 88) + '" width="44" height="14" rx="2" fill="#e2e8f0" stroke="#94a3b8"/>');
    // Clean Badged Labels
    svg.push('<rect x="' + (amenX + 8) + '" y="' + (cy + courtW_px + 36) + '" width="' + (offW_px - 16) + '" height="54" rx="4" fill="#ffffff" fill-opacity="0.94" stroke="#ca8a04" stroke-width="1"/>');
    svg.push('<text x="' + (amenX + offW_px / 2) + '" y="' + (cy + courtW_px + 52) + '" text-anchor="middle" font-size="9.5" font-weight="900" fill="#854d0e">[L] RECEPTION LOBBY</text>');
    svg.push('<text x="' + (amenX + offW_px / 2) + '" y="' + (cy + courtW_px + 66) + '" text-anchor="middle" font-size="8" font-weight="bold" fill="#b45309">15 FT × 18 FT (270 SFT)</text>');
    svg.push('<text x="' + (amenX + offW_px / 2) + '" y="' + (cy + courtW_px + 80) + '" text-anchor="middle" font-size="7" font-weight="900" fill="#a16207">★ DIRECT ENTRY ★</text>');

    // [GT] Gents Toilet (15 FT × 12 FT = 180 SFT) at OUTER SOUTH WALL for easy natural ventilation
    svg.push('<rect x="' + (amenX + 2) + '" y="' + (cy + courtW_px + lobH_px + 2) + '" width="' + (offW_px - 8) + '" height="' + (gtH_px - 6) + '" fill="#f0f9ff" stroke="#0284c7" stroke-width="3"/>');
    // External Wall Ventilation Louvers on South Wall
    svg.push('<rect x="' + (amenX + 22) + '" y="' + (cy + plotH_px - 7) + '" width="64" height="6" fill="#0284c7" rx="1"/>');
    svg.push('<line x1="' + (amenX + 32) + '" y1="' + (cy + plotH_px - 8) + '" x2="' + (amenX + 32) + '" y2="' + (cy + plotH_px - 1) + '" stroke="#ffffff" stroke-width="1.5"/>');
    svg.push('<line x1="' + (amenX + 48) + '" y1="' + (cy + plotH_px - 8) + '" x2="' + (amenX + 48) + '" y2="' + (cy + plotH_px - 1) + '" stroke="#ffffff" stroke-width="1.5"/>');
    svg.push('<line x1="' + (amenX + 64) + '" y1="' + (cy + plotH_px - 8) + '" x2="' + (amenX + 64) + '" y2="' + (cy + plotH_px - 1) + '" stroke="#ffffff" stroke-width="1.5"/>');
    // Urinals
    svg.push('<rect x="' + (amenX + 12) + '" y="' + (cy + courtW_px + lobH_px + 10) + '" width="14" height="20" rx="3" fill="#38bdf8" stroke="#0284c7" stroke-width="1"/>');
    svg.push('<rect x="' + (amenX + 32) + '" y="' + (cy + courtW_px + lobH_px + 10) + '" width="14" height="20" rx="3" fill="#38bdf8" stroke="#0284c7" stroke-width="1"/>');
    // Washbasin
    svg.push('<rect x="' + (amenX + 12) + '" y="' + (cy + courtW_px + lobH_px + 44) + '" width="24" height="22" rx="3" fill="#bae6fd" stroke="#0284c7" stroke-width="1"/>');
    // WC
    svg.push('<rect x="' + (amenX + 54) + '" y="' + (cy + courtW_px + lobH_px + 8) + '" width="42" height="68" fill="none" stroke="#7dd3fc" stroke-width="1.2"/>');
    svg.push('<circle cx="' + (amenX + 75) + '" cy="' + (cy + courtW_px + lobH_px + 42) + '" r="7.5" fill="#ffffff" stroke="#0284c7" stroke-width="1.5"/>');
    // Clean Badged Labels
    svg.push('<rect x="' + (amenX + 10) + '" y="' + (cy + courtW_px + lobH_px + 20) + '" width="' + (offW_px - 20) + '" height="52" rx="4" fill="#ffffff" fill-opacity="0.94" stroke="#0284c7" stroke-width="1"/>');
    svg.push('<text x="' + (amenX + offW_px / 2) + '" y="' + (cy + courtW_px + lobH_px + 36) + '" text-anchor="middle" font-size="9.5" font-weight="900" fill="#0369a1">[GT] GENTS TOILET</text>');
    svg.push('<text x="' + (amenX + offW_px / 2) + '" y="' + (cy + courtW_px + lobH_px + 50) + '" text-anchor="middle" font-size="8" font-weight="bold" fill="#0284c7">15 FT × 12 FT (180 SFT)</text>');
    svg.push('<text x="' + (amenX + offW_px / 2) + '" y="' + (cy + courtW_px + lobH_px + 64) + '" text-anchor="middle" font-size="7" font-weight="900" fill="#0369a1">💨 OUTER VENTILATION</text>');

    // [E] Main Entrance Door on 160 FT Wall AT THE EXACT CENTER (Direct Entrance Way!)
    var entryDoorY = cy + courtW_px; // Exact center of 60 FT arena width (Y = 30 FT)
    svg.push('<rect x="' + (z2X + z2W_px - 4) + '" y="' + (entryDoorY - 22) + '" width="8" height="44" fill="#dc2626" stroke="#ffffff" stroke-width="1.5"/>');
    // Door swings inwards (double doors open into central entrance & reception lobby)
    svg.push('<path d="M ' + (z2X + z2W_px) + ' ' + (entryDoorY - 22) + ' A 22 22 0 0 1 ' + (z2X + z2W_px - 22) + ' ' + entryDoorY + '" fill="none" stroke="#dc2626" stroke-width="1.5" stroke-dasharray="3,2"/>');
    svg.push('<path d="M ' + (z2X + z2W_px) + ' ' + (entryDoorY + 22) + ' A 22 22 0 0 0 ' + (z2X + z2W_px - 22) + ' ' + entryDoorY + '" fill="none" stroke="#dc2626" stroke-width="1.5" stroke-dasharray="3,2"/>');
    // Entrance threshold opening connecting directly into Reception Lobby
    svg.push('<line x1="' + (z2X + z2W_px - 6) + '" y1="' + (cy + courtW_px + 2) + '" x2="' + (z2X + z2W_px - 36) + '" y2="' + (cy + courtW_px + 2) + '" stroke="#fefce8" stroke-width="5"/>');
    // Entrance Callout Badge
    svg.push('<rect x="' + (z2X + z2W_px + 8) + '" y="' + (entryDoorY - 14) + '" width="154" height="28" rx="4" fill="#fef2f2" stroke="#dc2626" stroke-width="1.5"/>');
    svg.push('<text x="' + (z2X + z2W_px + 85) + '" y="' + (entryDoorY + 4) + '" text-anchor="middle" font-size="8.5" font-weight="900" fill="#991b1b">🚪 [E] MAIN ENTRANCE (6×7 FT)</text>');

    // Inside Partition Door Callout (Cricket <-> Badminton)
    var midDoorY = cy + plotH_px / 2;
    svg.push('<rect x="' + (z2X - 4) + '" y="' + (midDoorY - 20) + '" width="8" height="40" fill="#0284c7" stroke="#ffffff" stroke-width="1.5"/>');
    svg.push('<rect x="' + (z2X - 110) + '" y="' + (cy - 38) + '" width="220" height="24" rx="4" fill="#e0f2fe" stroke="#0284c7" stroke-width="1.5"/>');
    svg.push('<text x="' + z2X + '" y="' + (cy - 22) + '" text-anchor="middle" font-size="10" font-weight="bold" fill="#0369a1">🔄 Inside Inter-Zone Gate (4×7 FT)</text>');
    svg.push('<line x1="' + z2X + '" y1="' + (cy - 14) + '" x2="' + z2X + '" y2="' + (midDoorY - 20) + '" stroke="#0284c7" stroke-width="1.5" stroke-dasharray="4,2"/>');

    // Bottom Badge for Zone 2
    svg.push('<rect x="' + (z2X + 16) + '" y="' + (cy + plotH_px - 36) + '" width="' + (z2W_px - 32) + '" height="26" rx="4" fill="#0f172a" fill-opacity="0.95" stroke="#38bdf8" stroke-width="1.5"/>');
    svg.push('<text x="' + (z2X + z2W_px / 2) + '" y="' + (cy + plotH_px - 19) + '" text-anchor="middle" font-size="10" font-weight="900" fill="#38bdf8">ZONE 2: 60 FT × 60 FT COMPLEX (3,600 SFT) • 2 COURTS + OFFICE + LOBBY + TOILETS</text>');

    // ─────────────────────────────────────────────────────────────────────────
    // ZONE 3: REMAINING OPEN SPACE (160 to 185 FT = 25×60 FT = 1,500 SFT)
    // ZERO STEEL STRUCTURE • OPEN TO SKY • ZERO PLANNING / NOT IN SCOPE
    // ─────────────────────────────────────────────────────────────────────────
    var z3X = z2X + z2W_px; // 160 FT = 1152 px from cx
    var z3W_px = 25 * scale; // 180 px

    svg.push('<rect x="' + z3X + '" y="' + cy + '" width="' + z3W_px + '" height="' + plotH_px + '" fill="#f8fafc" stroke="#cbd5e1" stroke-width="2" stroke-dasharray="6,4"/>');

    // Clean Information Box in Upper Half of Zone 3 (preventing any overlap with center entrance)
    var z3BoxY = cy + 45;
    svg.push('<rect x="' + (z3X + 10) + '" y="' + z3BoxY + '" width="' + (z3W_px - 20) + '" height="64" rx="5" fill="#ffffff" stroke="#94a3b8" stroke-width="1.5"/>');
    svg.push('<text x="' + (z3X + z3W_px / 2) + '" y="' + (z3BoxY + 18) + '" text-anchor="middle" font-size="10" font-weight="900" fill="#475569" letter-spacing="0.5">ZONE 3: OPEN SPACE</text>');
    svg.push('<text x="' + (z3X + z3W_px / 2) + '" y="' + (z3BoxY + 35) + '" text-anchor="middle" font-size="9" font-weight="bold" fill="#64748b">25 FT × 60 FT (1,500 SFT)</text>');
    svg.push('<text x="' + (z3X + z3W_px / 2) + '" y="' + (z3BoxY + 50) + '" text-anchor="middle" font-size="8" font-weight="bold" fill="#94a3b8">OPEN GROUND / PARKING</text>');

    // Right Boundary Indicator at 185 FT
    svg.push('<line x1="' + (cx + plotW_px) + '" y1="' + cy + '" x2="' + (cx + plotW_px) + '" y2="' + (cy + plotH_px) + '" stroke="#64748b" stroke-width="3" stroke-dasharray="8,4"/>');
    svg.push('<text x="' + (cx + plotW_px - 10) + '" y="' + (cy + plotH_px / 2) + '" transform="rotate(90 ' + (cx + plotW_px - 10) + ' ' + (cy + plotH_px / 2) + ')" text-anchor="middle" font-size="10" font-weight="900" fill="#64748b" letter-spacing="2">PLOT BOUNDARY (60 FT)</text>');

    // Bottom Badge for Zone 3
    svg.push('<rect x="' + (z3X + 8) + '" y="' + (cy + plotH_px - 34) + '" width="' + (z3W_px - 16) + '" height="24" rx="4" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="1"/>');
    svg.push('<text x="' + (z3X + z3W_px / 2) + '" y="' + (cy + plotH_px - 18) + '" text-anchor="middle" font-size="8" font-weight="bold" fill="#64748b">ZONE 3: 25 FT VACANT OPEN SPACE</text>');

    // ─────────────────────────────────────────────────────────────────────────
    // STRUCTURAL COLUMNS ALONG PERIMETER (30 Columns Total strictly 0 to 160 FT)
    // ─────────────────────────────────────────────────────────────────────────
    var cadFrameXs = [
      cx,
      cx + (z1W_px / 6) * 1,
      cx + (z1W_px / 6) * 2,
      cx + (z1W_px / 6) * 3,
      cx + (z1W_px / 6) * 4,
      cx + (z1W_px / 6) * 5,
      z2X,
      z2X + 15 * scale,
      z2X + 30 * scale,
      z2X + 45 * scale,
      z3X // 160 FT
    ];
    for (var fi = 0; fi < cadFrameXs.length; fi++) {
      var colX = cadFrameXs[fi];
      svg.push('<rect x="' + (colX - 5) + '" y="' + (cy - 5) + '" width="10" height="10" fill="#0f172a" stroke="#ffffff" stroke-width="1"/>');
      svg.push('<rect x="' + (colX - 5) + '" y="' + (cy + plotH_px - 5) + '" width="10" height="10" fill="#0f172a" stroke="#ffffff" stroke-width="1"/>');
    }
    // Left end wall (0 FT) inner columns
    for (var ciy = cy + plotH_px / 4; ciy <= cy + plotH_px * 0.75; ciy += plotH_px / 4) {
      svg.push('<rect x="' + (cx - 5) + '" y="' + (ciy - 5) + '" width="10" height="10" fill="#0f172a" stroke="#ffffff" stroke-width="1"/>');
    }
    // East front wall (160 FT) structural columns:
    // Center column shifted 3.5 FT away from center to Y=26.5 FT (North Door Jamb) & Y=33.5 FT (South Door Jamb)
    // ZERO POLES inside the 6 FT center entrance doorway!
    var frontInnerColsY = [
      cy + 15 * scale,   // 15 FT
      cy + 26.5 * scale, // 26.5 FT (North Door Jamb Column — shifted 3.5 FT from center to clear gate!)
      cy + 33.5 * scale, // 33.5 FT (South Door Jamb Column — framing gate!)
      cy + 45 * scale    // 45 FT
    ];
    for (var fci = 0; fci < frontInnerColsY.length; fci++) {
      var ciy = frontInnerColsY[fci];
      svg.push('<rect x="' + (z3X - 5) + '" y="' + (ciy - 5) + '" width="10" height="10" fill="#0f172a" stroke="#ffffff" stroke-width="1"/>');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TOP INDICATORS: ZONE 1 (NET), ZONE 2 (ROOF), ZONE 3 (PARKING)
    // ─────────────────────────────────────────────────────────────────────────
    var topDimY = cy - 48;
    svg.push('<line x1="' + cx + '" y1="' + topDimY + '" x2="' + z2X + '" y2="' + topDimY + '" stroke="#16a34a" stroke-width="2.5" marker-start="url(#dimArr185_160)" marker-end="url(#dimArr185_160)"/>');
    svg.push('<text x="' + (cx + z1W_px / 2) + '" y="' + (topDimY - 8) + '" text-anchor="middle" font-size="10" font-weight="900" fill="#15803d">◄── OPEN-TOP 1.5mm 40mm TWISTED HDPE MESH (WIRE ROPE GRID — NO TOP STEEL) ──►</text>');

    svg.push('<line x1="' + z2X + '" y1="' + topDimY + '" x2="' + z3X + '" y2="' + topDimY + '" stroke="#0284c7" stroke-width="2.5" marker-start="url(#dimArr185_160)" marker-end="url(#dimArr185_160)"/>');
    svg.push('<text x="' + (z2X + z2W_px / 2) + '" y="' + (topDimY - 8) + '" text-anchor="middle" font-size="10" font-weight="900" fill="#0284c7">◄── 100% RAINPROOF 0.50mm PPGL ROOF &amp; WALLS (60 FT COMPLEX) ──►</text>');

    svg.push('<line x1="' + z3X + '" y1="' + topDimY + '" x2="' + (cx + plotW_px) + '" y2="' + topDimY + '" stroke="#d97706" stroke-width="2" stroke-dasharray="6,4" marker-start="url(#dimArr185_160)" marker-end="url(#dimArr185_160)"/>');
    svg.push('<text x="' + (z3X + z3W_px / 2) + '" y="' + (topDimY - 8) + '" text-anchor="middle" font-size="9.5" font-weight="900" fill="#d97706">◄── 25 FT OPEN SPACE (OUTSIDE STRUCTURE) ──►</text>');

    // ─────────────────────────────────────────────────────────────────────────
    // DIMENSIONS: 60 FT WIDTH & 185 FT OVERALL LENGTH
    // ─────────────────────────────────────────────────────────────────────────
    var dimLeftX = cx - 40;
    svg.push('<line x1="' + dimLeftX + '" y1="' + cy + '" x2="' + dimLeftX + '" y2="' + (cy + plotH_px) + '" stroke="#0f172a" stroke-width="2" marker-start="url(#cadArr185_160)" marker-end="url(#cadArr185_160)"/>');
    svg.push('<text x="' + (dimLeftX - 12) + '" y="' + (cy + plotH_px / 2) + '" transform="rotate(-90 ' + (dimLeftX - 12) + ' ' + (cy + plotH_px / 2) + ')" text-anchor="middle" font-size="13" font-weight="900" fill="#0f172a" letter-spacing="1">ARENA WIDTH: 60 FT</text>');

    var dimBotY = cy + plotH_px + 36;
    svg.push('<line x1="' + cx + '" y1="' + dimBotY + '" x2="' + (cx + plotW_px) + '" y2="' + dimBotY + '" stroke="#0f172a" stroke-width="2.5" marker-start="url(#cadArr185_160)" marker-end="url(#cadArr185_160)"/>');
    svg.push('<text x="' + (cx + plotW_px / 2) + '" y="' + (dimBotY + 20) + '" text-anchor="middle" font-size="14" font-weight="900" fill="#0f172a" letter-spacing="1">OVERALL PLOT LENGTH: 185 FT • 160 FT ARENA STRUCTURE (CRICKET + BADMINTON &amp; AMENITIES) + 25 FT OPEN SPACE</text>');

    svg.push('</svg>');
    container.innerHTML = svg.join('');
  }

  // =========================================================================
  // DEDICATED ARCHITECTURAL CAD SIDE ELEVATION: 185 FT × 60 FT MULTI-SPORT ARENA
  // 160 FT BUILT-UP METAL STRUCTURE (EST-202609-078): 100 FT CRICKET + 60 FT BADMINTON & AMENITIES + 25 FT OPEN
  // =========================================================================
  function renderMultiSportArena185x60SideElevation160(container, config) {
    if (!container) return;
    var svgW = 1600;
    var svgH = 850;
    var scale = 7.2;
    var plotW_px = 185 * scale; // 1332 px
    var eaveH_px = 30 * scale;  // 216 px
    var apexH_px = 34 * scale;  // 244.8 px
    var cx = (svgW - plotW_px) / 2;
    var groundY = svgH - 140;

    var svg = [];
    svg.push('<svg viewBox="0 0 ' + svgW + ' ' + svgH + '" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;background:#ffffff;font-family:Inter,Segoe UI,sans-serif;" preserveAspectRatio="xMidYMid meet">');
    svg.push('<defs>');
    svg.push('<marker id="sideArr185_160" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M 0 1.5 L 10 5 L 0 8.5 z" fill="#0f172a"/></marker>');
    svg.push('</defs>');

    // Title Sheet Header Bar
    svg.push('<rect x="' + cx + '" y="16" width="' + plotW_px + '" height="44" rx="5" fill="#0f172a" stroke="#1e293b" stroke-width="2"/>');
    svg.push('<text x="' + (svgW / 2) + '" y="' + 43 + '" text-anchor="middle" font-size="15.5" font-weight="900" fill="#ffffff" letter-spacing="1">ARCHITECTURAL 2D SIDE ELEVATION • 185 FT × 30 FT CLEAR HEIGHT (160 FT STRUCTURE + 25 FT OPEN)</text>');

    // Ground Line
    svg.push('<line x1="' + (cx - 20) + '" y1="' + groundY + '" x2="' + (cx + plotW_px + 20) + '" y2="' + groundY + '" stroke="#0f172a" stroke-width="3"/>');

    // ─────────────────────────────────────────────────────────────────────────
    // ZONE 1: CRICKET (100 FT = 720 px)
    // ─────────────────────────────────────────────────────────────────────────
    var z1W_px = 100 * scale; // 720 px
    svg.push('<rect x="' + cx + '" y="' + (groundY - eaveH_px) + '" width="' + z1W_px + '" height="' + eaveH_px + '" fill="#15803d" fill-opacity="0.15" stroke="#16a34a" stroke-width="2"/>');
    svg.push('<text x="' + (cx + z1W_px / 2) + '" y="' + (groundY - eaveH_px / 2 - 20) + '" text-anchor="middle" font-size="13" font-weight="900" fill="#166534">ZONE 1: BOX CRICKET (100 FT)</text>');
    svg.push('<text x="' + (cx + z1W_px / 2) + '" y="' + (groundY - eaveH_px / 2) + '" text-anchor="middle" font-size="11" font-weight="bold" fill="#0f172a">2.5mm HDPE BRAIDED SIDE NETTING (30 FT H)</text>');
    svg.push('<text x="' + (cx + z1W_px / 2) + '" y="' + (groundY - eaveH_px / 2 + 18) + '" text-anchor="middle" font-size="10" font-weight="900" fill="#15803d">1.5mm 40mm OVERHEAD TOP NETTING (WIRE ROPE GRID — NO TOP STEEL)</text>');

    // Top Overhead Netting line
    svg.push('<line x1="' + cx + '" y1="' + (groundY - eaveH_px) + '" x2="' + (cx + z1W_px) + '" y2="' + (groundY - eaveH_px) + '" stroke="#16a34a" stroke-width="3" stroke-dasharray="6,3"/>');

    // Sideline Player Door (4×7 FT)
    var dSideX = cx + 260;
    svg.push('<rect x="' + (dSideX - 14) + '" y="' + (groundY - 50) + '" width="28" height="50" fill="#facc15" stroke="#ca8a04" stroke-width="2"/>');
    svg.push('<rect x="' + (dSideX - 110) + '" y="' + (groundY - 60) + '" width="220" height="24" rx="4" fill="#fef08a" stroke="#ca8a04" stroke-width="1.5"/>');
    svg.push('<text x="' + dSideX + '" y="' + (groundY - 44) + '" text-anchor="middle" font-size="10.5" font-weight="bold" fill="#854d0e">🚪 Cricket Sideline Player Door (4×7 FT)</text>');

    // ─────────────────────────────────────────────────────────────────────────
    // ZONE 2: BADMINTON & AMENITIES (60 FT = 432 px, 100 to 160 FT)
    // 4.5 FT BASE WALL + 0.50mm PPGL METAL CLADDING (ALL 4 SIDES)
    // + 100% RAINPROOF 0.50mm PPGL ROOF CANOPY (60 FT SPAN)
    // ─────────────────────────────────────────────────────────────────────────
    var z2X = cx + z1W_px;
    var z2W_px = 60 * scale; // 432 px
    var z3X = z2X + z2W_px; // 160 FT = 1152 px from cx
    var baseWallH_px = 4.5 * scale; // 32.4 px
    var netH_px = eaveH_px - baseWallH_px; // 183.6 px

    // 4.5 FT Solid Masonry Base Wall
    svg.push('<rect x="' + z2X + '" y="' + (groundY - baseWallH_px) + '" width="' + z2W_px + '" height="' + baseWallH_px + '" fill="#334155" stroke="#1e293b" stroke-width="2"/>');
    svg.push('<rect x="' + z2X + '" y="' + (groundY - baseWallH_px - 3) + '" width="' + z2W_px + '" height="3" fill="#64748b"/>');

    // 0.50mm PPGL Metal Wall Cladding (from 4.5 FT up to 30 FT eave height)
    svg.push('<rect x="' + z2X + '" y="' + (groundY - eaveH_px) + '" width="' + z2W_px + '" height="' + netH_px + '" fill="#1e3a8a" fill-opacity="0.85" stroke="#1e3a8a" stroke-width="2"/>');

    // Vertical corrugated profile lines
    for (var clx = z2X + 18; clx < z3X; clx += 18) {
      svg.push('<line x1="' + clx + '" y1="' + (groundY - eaveH_px) + '" x2="' + clx + '" y2="' + (groundY - baseWallH_px) + '" stroke="#60a5fa" stroke-width="1" stroke-opacity="0.5"/>');
    }

    // Horizontal Wall Girts on Zone 2
    for (var hg = 1; hg <= 5; hg++) {
      var hgy = groundY - (baseWallH_px + hg * (netH_px / 6));
      svg.push('<line x1="' + z2X + '" y1="' + hgy + '" x2="' + z3X + '" y2="' + hgy + '" stroke="#93c5fd" stroke-width="1.5" stroke-dasharray="6,3"/>');
    }

    // 100% Rainproof 0.50mm PPGL Roof Canopy on Zone 2
    svg.push('<polygon points="' + z2X + ',' + (groundY - eaveH_px) + ' ' + (z2X + z2W_px / 2) + ',' + (groundY - apexH_px) + ' ' + z3X + ',' + (groundY - eaveH_px) + '" fill="#1e3a8a" stroke="#0f172a" stroke-width="3"/>');
    svg.push('<line x1="' + z2X + '" y1="' + (groundY - eaveH_px) + '" x2="' + (z2X + z2W_px / 2) + '" y2="' + (groundY - apexH_px) + '" stroke="#60a5fa" stroke-width="3"/>');
    svg.push('<line x1="' + (z2X + z2W_px / 2) + '" y1="' + (groundY - apexH_px) + '" x2="' + z3X + '" y2="' + (groundY - eaveH_px) + '" stroke="#60a5fa" stroke-width="3"/>');

    // Roof Apex Kingpost & Ridge Cap
    svg.push('<line x1="' + (z2X + z2W_px / 2) + '" y1="' + (groundY - eaveH_px) + '" x2="' + (z2X + z2W_px / 2) + '" y2="' + (groundY - apexH_px) + '" stroke="#ffffff" stroke-width="2"/>');

    // Labels inside Zone 2
    svg.push('<rect x="' + (z2X + 24) + '" y="' + (groundY - eaveH_px / 2 - 38) + '" width="' + (z2W_px - 48) + '" height="76" rx="5" fill="#0f172a" fill-opacity="0.92" stroke="#38bdf8" stroke-width="1.5"/>');
    svg.push('<text x="' + (z2X + z2W_px / 2) + '" y="' + (groundY - eaveH_px / 2 - 18) + '" text-anchor="middle" font-size="12" font-weight="900" fill="#38bdf8">ZONE 2: 60 FT × 60 FT BADMINTON &amp; AMENITIES COMPLEX (3,600 SFT)</text>');
    svg.push('<text x="' + (z2X + z2W_px / 2) + '" y="' + (groundY - eaveH_px / 2) + '" text-anchor="middle" font-size="10.5" font-weight="bold" fill="#ffffff">0.50mm PPGL ROOF &amp; GI METAL CLADDING (ALL 4 SIDES • 30 FT H)</text>');
    svg.push('<text x="' + (z2X + z2W_px / 2) + '" y="' + (groundY - eaveH_px / 2 + 18) + '" text-anchor="middle" font-size="9.5" font-weight="bold" fill="#93c5fd">TWO BWF REGULATION COURTS • OFFICE ROOM • LOBBY • TOILETS</text>');

    // [E] Main Reception Entrance Door on Front Wall at 160 FT (z3X)
    svg.push('<rect x="' + (z3X - 16) + '" y="' + (groundY - 50) + '" width="32" height="50" fill="#ef4444" stroke="#b91c1c" stroke-width="2"/>');
    svg.push('<rect x="' + (z3X - 110) + '" y="' + (groundY - 62) + '" width="220" height="24" rx="4" fill="#fef2f2" stroke="#dc2626" stroke-width="1.5"/>');
    svg.push('<text x="' + z3X + '" y="' + (groundY - 46) + '" text-anchor="middle" font-size="10.5" font-weight="bold" fill="#991b1b">🚪 [E] Main Reception Entrance (6×7 FT)</text>');

    // ─────────────────────────────────────────────────────────────────────────
    // ZONE 3: REMAINING OPEN SPACE (160 to 185 FT = 25 FT = 180 px)
    // ─────────────────────────────────────────────────────────────────────────
    var z3W_px = 25 * scale; // 180 px
    svg.push('<rect x="' + z3X + '" y="' + (groundY - eaveH_px) + '" width="' + z3W_px + '" height="' + eaveH_px + '" fill="#f8fafc" stroke="#cbd5e1" stroke-width="2" stroke-dasharray="6,4"/>');
    svg.push('<rect x="' + (z3X + 10) + '" y="' + (groundY - eaveH_px / 2 - 28) + '" width="' + (z3W_px - 20) + '" height="56" rx="4" fill="#ffffff" stroke="#94a3b8" stroke-width="1"/>');
    svg.push('<text x="' + (z3X + z3W_px / 2) + '" y="' + (groundY - eaveH_px / 2 - 8) + '" text-anchor="middle" font-size="10.5" font-weight="900" fill="#475569">ZONE 3: 25 FT OPEN SPACE</text>');
    svg.push('<text x="' + (z3X + z3W_px / 2) + '" y="' + (groundY - eaveH_px / 2 + 10) + '" text-anchor="middle" font-size="8.5" font-weight="bold" fill="#64748b">OUTSIDE METAL STRUCTURE</text>');

    // ─────────────────────────────────────────────────────────────────────────
    // STRUCTURAL LADDER COLUMNS (11 Frame Lines strictly 0 to 160 FT)
    // ─────────────────────────────────────────────────────────────────────────
    var sideFrameXs = [
      cx,
      cx + (z1W_px / 6) * 1,
      cx + (z1W_px / 6) * 2,
      cx + (z1W_px / 6) * 3,
      cx + (z1W_px / 6) * 4,
      cx + (z1W_px / 6) * 5,
      z2X,
      z2X + 15 * scale,
      z2X + 30 * scale,
      z2X + 45 * scale,
      z3X // 160 FT
    ];
    for (var sfi = 0; sfi < sideFrameXs.length; sfi++) {
      var colX = sideFrameXs[sfi];
      svg.push('<line x1="' + colX + '" y1="' + groundY + '" x2="' + colX + '" y2="' + (groundY - eaveH_px) + '" stroke="#0f172a" stroke-width="3"/>');
    }

    // Horizontal Tie Framework Tiers strictly 0 to 160 FT (cx to z3X)
    for (var ti = 1; ti <= 7; ti++) {
      var ty = groundY - (ti * (eaveH_px / 7.5));
      svg.push('<line x1="' + cx + '" y1="' + ty + '" x2="' + z3X + '" y2="' + ty + '" stroke="#64748b" stroke-width="1.2" stroke-dasharray="8,4"/>');
    }

    // Left Height Dimension: CLEAR HEIGHT: 30 FT
    var dimLeftX = cx - 40;
    svg.push('<line x1="' + dimLeftX + '" y1="' + groundY + '" x2="' + dimLeftX + '" y2="' + (groundY - eaveH_px) + '" stroke="#0f172a" stroke-width="2" marker-start="url(#sideArr185_160)" marker-end="url(#sideArr185_160)"/>');
    svg.push('<text x="' + (dimLeftX - 14) + '" y="' + (groundY - eaveH_px / 2) + '" transform="rotate(-90 ' + (dimLeftX - 14) + ' ' + (groundY - eaveH_px / 2) + ')" text-anchor="middle" font-size="13" font-weight="900" fill="#0f172a">CLEAR HEIGHT: 30 FT</text>');

    // Bottom Dimension: OVERALL ARENA LENGTH: 185 FT
    var dimBotY = groundY + 45;
    svg.push('<line x1="' + cx + '" y1="' + dimBotY + '" x2="' + (cx + plotW_px) + '" y2="' + dimBotY + '" stroke="#0f172a" stroke-width="2" marker-start="url(#sideArr185_160)" marker-end="url(#sideArr185_160)"/>');
    svg.push('<text x="' + (cx + plotW_px / 2) + '" y="' + (dimBotY + 22) + '" text-anchor="middle" font-size="14" font-weight="900" fill="#0f172a">OVERALL PLOT: 185 FT • 160 FT METAL STRUCTURE (CRICKET + BADMINTON &amp; AMENITIES) + 25 FT OPEN PARKING</text>');

    svg.push('</svg>');
    container.innerHTML = svg.join('');
  }

function renderMultiSportArena185x60FloorPlan(container, config) {
    if (!container) return;
    if (checkIs160Builtup(config)) {
      renderMultiSportArena185x60FloorPlan160(container, config);
      return;
    }
    var svgW = 1600;
    var svgH = 750;
    var scale = 7.2; // 7.2 px per foot (185 FT * 7.2 = 1332 px, 60 FT * 7.2 = 432 px)
    var plotW_px = 185 * scale; // 1332 px
    var plotH_px = 60 * scale;  // 432 px
    var cx = (svgW - plotW_px) / 2; // 134 px
    var cy = (svgH - plotH_px) / 2 + 30; // 174 px

    var svg = [];
    svg.push('<svg viewBox="0 0 ' + svgW + ' ' + svgH + '" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;background:#ffffff;font-family:Inter,Segoe UI,sans-serif;" preserveAspectRatio="xMidYMid meet">');
    svg.push('<defs>');
    svg.push('<marker id="cadArr185" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M 0 1.5 L 10 5 L 0 8.5 z" fill="#0f172a"/></marker>');
    svg.push('<marker id="dimArr185" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M 0 1.5 L 10 5 L 0 8.5 z" fill="#334155"/></marker>');
    svg.push('<pattern id="tilePat185" width="20" height="20" patternUnits="userSpaceOnUse"><rect width="20" height="20" fill="#f8fafc"/><path d="M 0 20 L 20 20 M 20 0 L 20 20" stroke="#cbd5e1" stroke-width="0.8"/></pattern>');
    svg.push('<pattern id="paverPat185" width="16" height="12" patternUnits="userSpaceOnUse"><rect width="16" height="12" fill="#e2e8f0"/><path d="M 0 6 L 16 6 M 8 0 L 8 6 M 0 12 L 16 12 M 16 6 L 16 12" stroke="#94a3b8" stroke-width="0.8"/></pattern>');
    svg.push('</defs>');

    // Title Sheet Header Bar
    svg.push('<rect x="' + cx + '" y="16" width="' + plotW_px + '" height="44" rx="5" fill="#0f172a" stroke="#1e293b" stroke-width="2"/>');
    svg.push('<text x="' + (svgW / 2) + '" y="' + 43 + '" text-anchor="middle" font-size="15.5" font-weight="900" fill="#ffffff" letter-spacing="1">ARCHITECTURAL 2D FLOOR PLAN • 185 FT × 60 FT MULTI-SPORT ARENA COMPLEX</text>');

    // Master Outer Boundary Bed
    svg.push('<rect x="' + (cx - 10) + '" y="' + (cy - 10) + '" width="' + (plotW_px + 20) + '" height="' + (plotH_px + 20) + '" rx="6" fill="#f8fafc" stroke="#94a3b8" stroke-width="2" stroke-dasharray="6,4"/>');

    // ─────────────────────────────────────────────────────────────────────────
    // ZONE 1: 100 FT × 60 FT BOX CRICKET GROUND (6,000 SFT)
    // ─────────────────────────────────────────────────────────────────────────
    var z1W_px = 100 * scale; // 720 px
    svg.push('<rect x="' + cx + '" y="' + cy + '" width="' + z1W_px + '" height="' + plotH_px + '" fill="#15803d" stroke="#166534" stroke-width="2.5"/>');

    // Alternating Turf Stripes (40mm Landscape Zero Infill Outfield)
    for (var ts = cx; ts < cx + z1W_px; ts += 45) {
      var wStrip = Math.min(45, (cx + z1W_px) - ts);
      if (Math.floor((ts - cx) / 45) % 2 === 1) {
        svg.push('<rect x="' + ts + '" y="' + cy + '" width="' + wStrip + '" height="' + plotH_px + '" fill="#16a34a" fill-opacity="0.35"/>');
      }
    }

    // Cricket Perimeter Boundary Lines
    svg.push('<rect x="' + (cx + 12) + '" y="' + (cy + 12) + '" width="' + (z1W_px - 24) + '" height="' + (plotH_px - 24) + '" fill="none" stroke="#ffffff" stroke-width="2" stroke-dasharray="8,6"/>');

    // Top Badge: BED & OPEN-TOP 1.5mm 40mm MESH
    svg.push('<rect x="' + (cx + z1W_px / 2 - 210) + '" y="' + (cy + 14) + '" width="420" height="25" rx="4" fill="#0f172a" stroke="#eab308" stroke-width="1.8"/>');
    svg.push('<text x="' + (cx + z1W_px / 2) + '" y="' + (cy + 31) + '" text-anchor="middle" font-size="10" font-weight="900" fill="#facc15" letter-spacing="0.5">BED: 4" PCC M15 • OPEN-TOP 1.5mm 40mm MESH (WIRE ROPE GRID — NO TOP STEEL)</text>');

    // 83 FT × 6.5 FT Centered Match Pitch Concrete Pad + Curled Turf
    var pL_px = 83 * scale; // 597.6 px
    var pW_px = 6.5 * scale; // 46.8 px
    var pX = cx + (z1W_px - pL_px) / 2;
    var pY = cy + (plotH_px - pW_px) / 2;

    svg.push('<rect x="' + pX + '" y="' + pY + '" width="' + pL_px + '" height="' + pW_px + '" rx="2" fill="#deb887" stroke="#854d0e" stroke-width="2"/>');

    // Regulation White Creases
    svg.push('<g stroke="#ffffff" stroke-width="2">');
    svg.push('<line x1="' + (pX + 16) + '" y1="' + pY + '" x2="' + (pX + 16) + '" y2="' + (pY + pW_px) + '"/>');
    svg.push('<line x1="' + (pX + pL_px - 16) + '" y1="' + pY + '" x2="' + (pX + pL_px - 16) + '" y2="' + (pY + pW_px) + '"/>');
    var popDist = 4 * scale;
    svg.push('<line x1="' + (pX + 16 + popDist) + '" y1="' + (pY - 4) + '" x2="' + (pX + 16 + popDist) + '" y2="' + (pY + pW_px + 4) + '"/>');
    svg.push('<line x1="' + (pX + pL_px - 16 - popDist) + '" y1="' + (pY - 4) + '" x2="' + (pX + pL_px - 16 - popDist) + '" y2="' + (pY + pW_px + 4) + '"/>');
    svg.push('</g>');

    // Stumps
    svg.push('<circle cx="' + (pX + 16) + '" cy="' + (pY + pW_px / 2) + '" r="3.5" fill="#facc15" stroke="#713f12" stroke-width="1"/>');
    svg.push('<circle cx="' + (pX + pL_px - 16) + '" cy="' + (pY + pW_px / 2) + '" r="3.5" fill="#facc15" stroke="#713f12" stroke-width="1"/>');

    // Pitch Label
    svg.push('<text x="' + (cx + z1W_px / 2) + '" y="' + (cy + plotH_px / 2 + 4.5) + '" text-anchor="middle" font-size="11" font-weight="900" fill="#713f12" letter-spacing="0.5">83 FT × 6.5 FT CURLED MATCH PITCH TURF</text>');

    // Bottom Label
    svg.push('<rect x="' + (cx + 18) + '" y="' + (cy + plotH_px - 36) + '" width="' + (z1W_px - 36) + '" height="26" rx="4" fill="#0f172a" fill-opacity="0.9"/>');
    svg.push('<text x="' + (cx + z1W_px / 2) + '" y="' + (cy + plotH_px - 19) + '" text-anchor="middle" font-size="11" font-weight="900" fill="#ffffff">ZONE 1: 100 FT × 60 FT BOX CRICKET GROUND (6,000 SFT) • 40mm LANDSCAPE OUTFIELD (ZERO INFILL)</text>');

    // Door Callout: Cricket Sideline Player Door (4×7 FT)
    var d1X = cx + 260;
    svg.push('<rect x="' + (d1X - 110) + '" y="' + (cy - 38) + '" width="220" height="24" rx="4" fill="#fef08a" stroke="#ca8a04" stroke-width="1.5"/>');
    svg.push('<text x="' + d1X + '" y="' + (cy - 22) + '" text-anchor="middle" font-size="10.5" font-weight="bold" fill="#854d0e">🚪 Cricket Sideline Player Door (4×7 FT)</text>');
    svg.push('<line x1="' + d1X + '" y1="' + (cy - 14) + '" x2="' + d1X + '" y2="' + cy + '" stroke="#ca8a04" stroke-width="2"/>');

    // ─────────────────────────────────────────────────────────────────────────
    // ZONE 2: BADMINTON COMPLEX (100 to 145 FT = 45×60 FT = 2,700 SFT)
    // 0.50mm PPGL ROOF + 0.50mm GI WALL CLADDING (ALL 4 SIDES — NO SHADE NET)
    // ─────────────────────────────────────────────────────────────────────────
    var z2X = cx + z1W_px;
    var z2W_px = 45 * scale; // 324 px
    var courtW_px = 30 * scale; // 216 px height per court

    // Zone 2 Blue Flooring (BWF-Grade Synthetic Surface)
    svg.push('<rect x="' + z2X + '" y="' + cy + '" width="' + z2W_px + '" height="' + plotH_px + '" fill="#0284c7" stroke="#0369a1" stroke-width="2"/>');

    // 0.50mm PPGL Corrugated Metal Wall Cladding Perimeter Band (All 4 Sides, 210 RFT)
    svg.push('<rect x="' + (z2X + 3) + '" y="' + (cy + 3) + '" width="' + (z2W_px - 6) + '" height="' + (plotH_px - 6) + '" fill="none" stroke="#1e3a8a" stroke-width="7"/>');
    svg.push('<rect x="' + (z2X + 6) + '" y="' + (cy + 6) + '" width="' + (z2W_px - 12) + '" height="' + (plotH_px - 12) + '" fill="none" stroke="#60a5fa" stroke-width="2" stroke-dasharray="6,4"/>');

    // Top Callout Badge for Zone 2
    svg.push('<rect x="' + (z2X + 10) + '" y="' + (cy + 12) + '" width="' + (z2W_px - 20) + '" height="24" rx="4" fill="#0f172a" fill-opacity="0.95" stroke="#38bdf8" stroke-width="1.5"/>');
    svg.push('<text x="' + (z2X + z2W_px / 2) + '" y="' + (cy + 28) + '" text-anchor="middle" font-size="8.5" font-weight="900" fill="#38bdf8">0.50mm PPGL METAL ROOF + GI WALL CLADDING (ALL 4 SIDES)</text>');

    // Two Side-by-Side Courts
    for (var bi = 0; bi < 2; bi++) {
      var cY = cy + bi * courtW_px;
      var badL_px = 44 * scale; // 316.8 px
      var badW_px = 20 * scale; // 144 px
      var badX = z2X + (z2W_px - badL_px) / 2;
      var badY = cY + (courtW_px - badW_px) / 2;

      // Court Green Mat
      svg.push('<rect x="' + badX + '" y="' + badY + '" width="' + badL_px + '" height="' + badW_px + '" fill="#059669" stroke="#ffffff" stroke-width="2.5"/>');

      // Center Net Line
      var midNetX = badX + badL_px / 2;
      svg.push('<line x1="' + midNetX + '" y1="' + badY + '" x2="' + midNetX + '" y2="' + (badY + badW_px) + '" stroke="#dc2626" stroke-width="3"/>');

      // Short Service Lines (6.5 FT from center net)
      var sServDist = 6.5 * scale;
      svg.push('<line x1="' + (midNetX - sServDist) + '" y1="' + badY + '" x2="' + (midNetX - sServDist) + '" y2="' + (badY + badW_px) + '" stroke="#ffffff" stroke-width="1.8"/>');
      svg.push('<line x1="' + (midNetX + sServDist) + '" y1="' + badY + '" x2="' + (midNetX + sServDist) + '" y2="' + (badY + badW_px) + '" stroke="#ffffff" stroke-width="1.8"/>');

      // Doubles Long Service Lines (2.5 FT from baseline)
      var dServDist = 2.5 * scale;
      svg.push('<line x1="' + (badX + dServDist) + '" y1="' + badY + '" x2="' + (badX + dServDist) + '" y2="' + (badY + badW_px) + '" stroke="#ffffff" stroke-width="1.8"/>');
      svg.push('<line x1="' + (badX + badL_px - dServDist) + '" y1="' + badY + '" x2="' + (badX + badL_px - dServDist) + '" y2="' + (badY + badW_px) + '" stroke="#ffffff" stroke-width="1.8"/>');

      // Singles Side Lines (1.5 FT from doubles sideline)
      var sSideDist = 1.5 * scale;
      svg.push('<line x1="' + badX + '" y1="' + (badY + sSideDist) + '" x2="' + (badX + badL_px) + '" y2="' + (badY + sSideDist) + '" stroke="#ffffff" stroke-width="1.8"/>');
      svg.push('<line x1="' + badX + '" y1="' + (badY + badW_px - sSideDist) + '" x2="' + (badX + badL_px) + '" y2="' + (badY + badW_px - sSideDist) + '" stroke="#ffffff" stroke-width="1.8"/>');

      // Center Service Line (From short service line to baseline on each side)
      var midY = badY + badW_px / 2;
      svg.push('<line x1="' + badX + '" y1="' + midY + '" x2="' + (midNetX - sServDist) + '" y2="' + midY + '" stroke="#ffffff" stroke-width="1.8"/>');
      svg.push('<line x1="' + (midNetX + sServDist) + '" y1="' + midY + '" x2="' + (badX + badL_px) + '" y2="' + midY + '" stroke="#ffffff" stroke-width="1.8"/>');

      // Court Label
      svg.push('<text x="' + (badX + badL_px / 2) + '" y="' + (badY + badW_px / 2 + 4) + '" text-anchor="middle" font-size="10" font-weight="900" fill="#ffffff" letter-spacing="1">COURT ' + (bi + 1) + ' (44 FT × 20 FT BWF DOUBLES)</text>');
    }

    // Inside Partition Door Callout (Cricket <-> Badminton)
    var midDoorY = cy + plotH_px / 2;
    svg.push('<rect x="' + (z2X - 4) + '" y="' + (midDoorY - 20) + '" width="8" height="40" fill="#0284c7" stroke="#ffffff" stroke-width="1.5"/>');
    svg.push('<rect x="' + (z2X - 110) + '" y="' + (cy - 38) + '" width="220" height="24" rx="4" fill="#e0f2fe" stroke="#0284c7" stroke-width="1.5"/>');
    svg.push('<text x="' + z2X + '" y="' + (cy - 22) + '" text-anchor="middle" font-size="10" font-weight="bold" fill="#0369a1">🔄 Inside Inter-Zone Gate (4×7 FT)</text>');
    svg.push('<line x1="' + z2X + '" y1="' + (cy - 14) + '" x2="' + z2X + '" y2="' + (midDoorY - 20) + '" stroke="#0284c7" stroke-width="1.5" stroke-dasharray="4,2"/>');

    // ─────────────────────────────────────────────────────────────────────────
    // ZONE C: REMAINING OPEN SPACE (145 to 185 FT = 40×60 FT = 2,400 SFT)
    // ZERO STEEL STRUCTURE • OPEN TO SKY • ZERO PLANNING / NOT IN SCOPE
    // ─────────────────────────────────────────────────────────────────────────
    var z3X = z2X + z2W_px; // 145 FT = 1044 px from cx
    var z3W_px = 40 * scale; // 288 px

    // Zone C Plain Neutral Open Ground (Zero construction)
    svg.push('<rect x="' + z3X + '" y="' + cy + '" width="' + z3W_px + '" height="' + plotH_px + '" fill="#f8fafc" stroke="#cbd5e1" stroke-width="2" stroke-dasharray="6,4"/>');

    // Clean Information Box in Upper Half of Zone C (preventing any overlap with center entrance)
    var z3BoxY = cy + 45;
    svg.push('<rect x="' + (z3X + 16) + '" y="' + z3BoxY + '" width="' + (z3W_px - 32) + '" height="64" rx="5" fill="#ffffff" stroke="#94a3b8" stroke-width="1.5"/>');
    svg.push('<text x="' + (z3X + z3W_px / 2) + '" y="' + (z3BoxY + 18) + '" text-anchor="middle" font-size="10.5" font-weight="900" fill="#475569" letter-spacing="1">ZONE C: REMAINING OPEN SPACE</text>');
    svg.push('<text x="' + (z3X + z3W_px / 2) + '" y="' + (z3BoxY + 35) + '" text-anchor="middle" font-size="9.5" font-weight="bold" fill="#64748b">40 FT × 60 FT (2,400 SFT)</text>');
    svg.push('<text x="' + (z3X + z3W_px / 2) + '" y="' + (z3BoxY + 50) + '" text-anchor="middle" font-size="8.5" font-weight="bold" fill="#94a3b8">OPEN GROUND • ZERO CONSTRUCTION IN SCOPE</text>');

    // [E] Main Entrance Door on 145 FT Wall AT THE EXACT CENTER (Direct Entrance Way — Exactly like EST-078!)
    var entryDoorY = cy + plotH_px / 2; // Exact center of 60 FT arena width (Y = 30 FT)
    svg.push('<rect x="' + (z3X - 4) + '" y="' + (entryDoorY - 22) + '" width="8" height="44" fill="#dc2626" stroke="#ffffff" stroke-width="1.5"/>');
    // Door swings inwards
    svg.push('<path d="M ' + z3X + ' ' + (entryDoorY - 22) + ' A 22 22 0 0 1 ' + (z3X - 22) + ' ' + entryDoorY + '" fill="none" stroke="#dc2626" stroke-width="1.5" stroke-dasharray="3,2"/>');
    svg.push('<path d="M ' + z3X + ' ' + (entryDoorY + 22) + ' A 22 22 0 0 0 ' + (z3X - 22) + ' ' + entryDoorY + '" fill="none" stroke="#dc2626" stroke-width="1.5" stroke-dasharray="3,2"/>');
    // Entrance Callout Badge
    svg.push('<rect x="' + (z3X + 8) + '" y="' + (entryDoorY - 14) + '" width="154" height="28" rx="4" fill="#fef2f2" stroke="#dc2626" stroke-width="1.5"/>');
    svg.push('<text x="' + (z3X + 85) + '" y="' + (entryDoorY + 4) + '" text-anchor="middle" font-size="8.5" font-weight="900" fill="#991b1b">🚪 [E] MAIN ENTRANCE (6×7 FT)</text>');

    // Right Boundary Indicator at 185 FT (site boundary)
    svg.push('<line x1="' + (cx + plotW_px) + '" y1="' + cy + '" x2="' + (cx + plotW_px) + '" y2="' + (cy + plotH_px) + '" stroke="#64748b" stroke-width="3" stroke-dasharray="8,4"/>');
    svg.push('<text x="' + (cx + plotW_px - 10) + '" y="' + (cy + plotH_px / 2) + '" transform="rotate(90 ' + (cx + plotW_px - 10) + ' ' + (cy + plotH_px / 2) + ')" text-anchor="middle" font-size="10" font-weight="900" fill="#64748b" letter-spacing="2">PLOT BOUNDARY (60 FT)</text>');

    // Bottom Badge for Zone C
    svg.push('<rect x="' + (z3X + 10) + '" y="' + (cy + plotH_px - 34) + '" width="' + (z3W_px - 20) + '" height="24" rx="4" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="1"/>');
    svg.push('<text x="' + (z3X + z3W_px / 2) + '" y="' + (cy + plotH_px - 18) + '" text-anchor="middle" font-size="8.5" font-weight="bold" fill="#64748b">ZONE C: 40 FT VACANT OPEN SPACE (OUTSIDE STRUCTURE)</text>');

    // ─────────────────────────────────────────────────────────────────────────
    // STRUCTURAL COLUMNS ALONG PERIMETER (28 Columns Total strictly 0 to 145 FT)
    // ─────────────────────────────────────────────────────────────────────────
    var cadFrameXs = [
      cx,
      cx + (z1W_px / 6) * 1,
      cx + (z1W_px / 6) * 2,
      cx + (z1W_px / 6) * 3,
      cx + (z1W_px / 6) * 4,
      cx + (z1W_px / 6) * 5,
      z2X,
      z2X + (z2W_px / 3) * 1,
      z2X + (z2W_px / 3) * 2,
      z3X // 145 FT (Stops at front wall of Badminton complex)
    ];
    for (var fi = 0; fi < cadFrameXs.length; fi++) {
      var colX = cadFrameXs[fi];
      svg.push('<rect x="' + (colX - 5) + '" y="' + (cy - 5) + '" width="10" height="10" fill="#0f172a" stroke="#ffffff" stroke-width="1"/>');
      svg.push('<rect x="' + (colX - 5) + '" y="' + (cy + plotH_px - 5) + '" width="10" height="10" fill="#0f172a" stroke="#ffffff" stroke-width="1"/>');
    }
    // Left end wall (0 FT) inner columns
    for (var ciy = cy + plotH_px / 4; ciy <= cy + plotH_px * 0.75; ciy += plotH_px / 4) {
      svg.push('<rect x="' + (cx - 5) + '" y="' + (ciy - 5) + '" width="10" height="10" fill="#0f172a" stroke="#ffffff" stroke-width="1"/>');
    }
    // East front wall (145 FT) structural columns:
    // Center column shifted 3.5 FT away from center to Y=26.5 FT (North Door Jamb) & Y=33.5 FT (South Door Jamb)
    // ZERO POLES inside the 6 FT center entrance doorway!
    var frontInnerColsY = [
      cy + 15 * scale,   // 15 FT
      cy + 26.5 * scale, // 26.5 FT (North Door Jamb Column — shifted 3.5 FT from center to clear gate!)
      cy + 33.5 * scale, // 33.5 FT (South Door Jamb Column — framing gate!)
      cy + 45 * scale    // 45 FT
    ];
    for (var fci = 0; fci < frontInnerColsY.length; fci++) {
      var ciy = frontInnerColsY[fci];
      svg.push('<rect x="' + (z3X - 5) + '" y="' + (ciy - 5) + '" width="10" height="10" fill="#0f172a" stroke="#ffffff" stroke-width="1"/>');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TOP INDICATORS: ZONE 1 (NET), ZONE 2 (ROOF), ZONE 3 (PARKING)
    // ─────────────────────────────────────────────────────────────────────────
    var topDimY = cy - 48;
    // Zone 1 indicator: Open-Top 1.5mm 40mm Mesh (Wire Rope Grid)
    svg.push('<line x1="' + cx + '" y1="' + topDimY + '" x2="' + z2X + '" y2="' + topDimY + '" stroke="#16a34a" stroke-width="2.5" marker-start="url(#dimArr185)" marker-end="url(#dimArr185)"/>');
    svg.push('<text x="' + (cx + z1W_px / 2) + '" y="' + (topDimY - 8) + '" text-anchor="middle" font-size="10" font-weight="900" fill="#15803d">◄── OPEN-TOP 1.5mm 40mm TWISTED HDPE MESH (WIRE ROPE GRID — NO TOP STEEL) ──►</text>');

    // Zone 2 indicator: 100% Rainproof 0.50mm PPGL Roof
    svg.push('<line x1="' + z2X + '" y1="' + topDimY + '" x2="' + z3X + '" y2="' + topDimY + '" stroke="#0284c7" stroke-width="2.5" marker-start="url(#dimArr185)" marker-end="url(#dimArr185)"/>');
    svg.push('<text x="' + (z2X + z2W_px / 2) + '" y="' + (topDimY - 8) + '" text-anchor="middle" font-size="10" font-weight="900" fill="#0284c7">◄── 100% RAINPROOF 0.50mm PPGL ROOF (45 FT) ──►</text>');

    // Zone 3 indicator: Open Parking Outside Metal Structure
    svg.push('<line x1="' + z3X + '" y1="' + topDimY + '" x2="' + (cx + plotW_px) + '" y2="' + topDimY + '" stroke="#d97706" stroke-width="2" stroke-dasharray="6,4" marker-start="url(#dimArr185)" marker-end="url(#dimArr185)"/>');
    svg.push('<text x="' + (z3X + z3W_px / 2) + '" y="' + (topDimY - 8) + '" text-anchor="middle" font-size="9.5" font-weight="900" fill="#d97706">◄── 40 FT OPEN SPACE (OUTSIDE STRUCTURE) ──►</text>');

    // ─────────────────────────────────────────────────────────────────────────
    // DIMENSIONS: 60 FT WIDTH & 185 FT OVERALL LENGTH
    // ─────────────────────────────────────────────────────────────────────────
    var dimLeftX = cx - 40;
    svg.push('<line x1="' + dimLeftX + '" y1="' + cy + '" x2="' + dimLeftX + '" y2="' + (cy + plotH_px) + '" stroke="#0f172a" stroke-width="2" marker-start="url(#cadArr185)" marker-end="url(#cadArr185)"/>');
    svg.push('<text x="' + (dimLeftX - 12) + '" y="' + (cy + plotH_px / 2) + '" transform="rotate(-90 ' + (dimLeftX - 12) + ' ' + (cy + plotH_px / 2) + ')" text-anchor="middle" font-size="13" font-weight="900" fill="#0f172a" letter-spacing="1">ARENA WIDTH: 60 FT</text>');

    var dimBotY = cy + plotH_px + 36;
    svg.push('<line x1="' + cx + '" y1="' + dimBotY + '" x2="' + (cx + plotW_px) + '" y2="' + dimBotY + '" stroke="#0f172a" stroke-width="2.5" marker-start="url(#cadArr185)" marker-end="url(#cadArr185)"/>');
    svg.push('<text x="' + (cx + plotW_px / 2) + '" y="' + (dimBotY + 20) + '" text-anchor="middle" font-size="14" font-weight="900" fill="#0f172a" letter-spacing="1">OVERALL PLOT LENGTH: 185 FT • 145 FT ARENA STRUCTURE (CRICKET + BADMINTON) + 40 FT OPEN SPACE</text>');

    svg.push('</svg>');
    container.innerHTML = svg.join('');
  }
  function renderMultiSportArena185x60SideElevation(container, config) {
    if (!container) return;
    if (checkIs160Builtup(config)) {
      renderMultiSportArena185x60SideElevation160(container, config);
      return;
    }
    var svgW = 1600;
    var svgH = 850;
    var scale = 7.2;
    var plotW_px = 185 * scale; // 1332 px
    var eaveH_px = 30 * scale;  // 216 px
    var apexH_px = 34 * scale;  // 244.8 px (34 FT apex)
    var cx = (svgW - plotW_px) / 2;
    var groundY = svgH - 140;

    var svg = [];
    svg.push('<svg viewBox="0 0 ' + svgW + ' ' + svgH + '" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;background:#ffffff;font-family:Inter,Segoe UI,sans-serif;" preserveAspectRatio="xMidYMid meet">');
    svg.push('<defs>');
    svg.push('<marker id="sideArr185" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M 0 1.5 L 10 5 L 0 8.5 z" fill="#0f172a"/></marker>');
    svg.push('</defs>');

    // Title Sheet Header Bar
    svg.push('<rect x="' + cx + '" y="16" width="' + plotW_px + '" height="44" rx="5" fill="#0f172a" stroke="#1e293b" stroke-width="2"/>');
    svg.push('<text x="' + (svgW / 2) + '" y="' + 43 + '" text-anchor="middle" font-size="15.5" font-weight="900" fill="#ffffff" letter-spacing="1">ARCHITECTURAL 2D SIDE ELEVATION • 185 FT × 30 FT CLEAR HEIGHT</text>');

    // Ground Line
    svg.push('<line x1="' + (cx - 20) + '" y1="' + groundY + '" x2="' + (cx + plotW_px + 20) + '" y2="' + groundY + '" stroke="#0f172a" stroke-width="3"/>');

    // ─────────────────────────────────────────────────────────────────────────
    // ZONE 1: CRICKET (100 FT = 720 px)
    // Side Ball-Stop Netting + 1.5mm 40mm Top Netting (Wire Rope Grid — Zero Top Steel)
    // ─────────────────────────────────────────────────────────────────────────
    var z1W_px = 100 * scale; // 720 px
    svg.push('<rect x="' + cx + '" y="' + (groundY - eaveH_px) + '" width="' + z1W_px + '" height="' + eaveH_px + '" fill="#15803d" fill-opacity="0.15" stroke="#16a34a" stroke-width="2"/>');
    svg.push('<text x="' + (cx + z1W_px / 2) + '" y="' + (groundY - eaveH_px / 2 - 20) + '" text-anchor="middle" font-size="13" font-weight="900" fill="#166534">ZONE 1: BOX CRICKET (100 FT)</text>');
    svg.push('<text x="' + (cx + z1W_px / 2) + '" y="' + (groundY - eaveH_px / 2) + '" text-anchor="middle" font-size="11" font-weight="bold" fill="#0f172a">2.5mm HDPE BRAIDED SIDE NETTING (30 FT H)</text>');
    svg.push('<text x="' + (cx + z1W_px / 2) + '" y="' + (groundY - eaveH_px / 2 + 18) + '" text-anchor="middle" font-size="10" font-weight="900" fill="#15803d">1.5mm 40mm OVERHEAD TOP NETTING (WIRE ROPE GRID — NO TOP STEEL)</text>');

    // Top Overhead Netting line
    svg.push('<line x1="' + cx + '" y1="' + (groundY - eaveH_px) + '" x2="' + (cx + z1W_px) + '" y2="' + (groundY - eaveH_px) + '" stroke="#16a34a" stroke-width="3" stroke-dasharray="6,3"/>');

    // Sideline Player Door (4×7 FT)
    var dSideX = cx + 260;
    svg.push('<rect x="' + (dSideX - 14) + '" y="' + (groundY - 50) + '" width="28" height="50" fill="#facc15" stroke="#ca8a04" stroke-width="2"/>');
    svg.push('<rect x="' + (dSideX - 110) + '" y="' + (groundY - 60) + '" width="220" height="24" rx="4" fill="#fef08a" stroke="#ca8a04" stroke-width="1.5"/>');
    svg.push('<text x="' + dSideX + '" y="' + (groundY - 44) + '" text-anchor="middle" font-size="10.5" font-weight="bold" fill="#854d0e">🚪 Cricket Sideline Player Door (4×7 FT)</text>');

    // ─────────────────────────────────────────────────────────────────────────
    // ZONE 2: BADMINTON (45 FT = 324 px)
    // 4.5 FT BASE WALL + 0.50mm PPGL CORRUGATED METAL WALL CLADDING (ALL 4 SIDES)
    // + 100% RAINPROOF 0.50mm PPGL ROOF CANOPY (45 FT SPAN ONLY)
    // ─────────────────────────────────────────────────────────────────────────
    var z2X = cx + z1W_px;
    var z2W_px = 45 * scale; // 324 px
    var z3X = z2X + z2W_px; // 145 FT = 1044 px
    var baseWallH_px = 4.5 * scale; // 32.4 px
    var netH_px = eaveH_px - baseWallH_px; // 183.6 px

    // 1. 0.50mm PPGL Corrugated Metal Wall Cladding (All 4 Sides, from 4.5 FT to 30 FT H)
    svg.push('<rect x="' + z2X + '" y="' + (groundY - eaveH_px) + '" width="' + z2W_px + '" height="' + netH_px + '" fill="#1e3a8a" stroke="#0f172a" stroke-width="2"/>');
    // Horizontal Wall Girt lines @ 5 FT c/c
    for (var gti = 1; gti <= 5; gti++) {
      var gy_px = groundY - baseWallH_px - (gti * (netH_px / 5.5));
      svg.push('<line x1="' + z2X + '" y1="' + gy_px + '" x2="' + (z2X + z2W_px) + '" y2="' + gy_px + '" stroke="#93c5fd" stroke-width="1.2" stroke-dasharray="8,4"/>');
    }
    svg.push('<text x="' + (z2X + z2W_px / 2) + '" y="' + (groundY - eaveH_px / 2 - 18) + '" text-anchor="middle" font-size="12" font-weight="900" fill="#ffffff">ZONE 2: BADMINTON (45 FT)</text>');
    svg.push('<text x="' + (z2X + z2W_px / 2) + '" y="' + (groundY - eaveH_px / 2 + 2) + '" text-anchor="middle" font-size="10.5" font-weight="900" fill="#93c5fd">0.50mm PPGL CORRUGATED METAL WALL CLADDING</text>');
    svg.push('<text x="' + (z2X + z2W_px / 2) + '" y="' + (groundY - eaveH_px / 2 + 20) + '" text-anchor="middle" font-size="9" font-weight="bold" fill="#dbeafe">ALL 4 SIDES ENCLOSED UP TO 30 FT H (NO SHADE NET)</text>');

    // 2. 4.5 FT Solid Perimeter Base Wall (Masonry Plinth)
    svg.push('<rect x="' + z2X + '" y="' + (groundY - baseWallH_px) + '" width="' + z2W_px + '" height="' + baseWallH_px + '" fill="#334155" stroke="#1e293b" stroke-width="2"/>');
    svg.push('<line x1="' + z2X + '" y1="' + (groundY - baseWallH_px) + '" x2="' + (z2X + z2W_px) + '" y2="' + (groundY - baseWallH_px) + '" stroke="#94a3b8" stroke-width="2.5"/>');
    svg.push('<text x="' + (z2X + z2W_px / 2) + '" y="' + (groundY - baseWallH_px / 2 + 4) + '" text-anchor="middle" font-size="9.5" font-weight="900" fill="#f1f5f9" letter-spacing="0.5">🧱 4.5 FT SOLID PERIMETER BASE WALL (210 RFT)</text>');

    // 3. 0.50mm PPGL Galvalume Pitched Metal Roof (STRICTLY over Zone 2: 45 FT)
    svg.push('<polygon points="' + (z2X - 4) + ',' + (groundY - eaveH_px) + ' ' + (z2X + z2W_px / 2) + ',' + (groundY - apexH_px) + ' ' + (z3X + 4) + ',' + (groundY - eaveH_px) + '" fill="#1e3a8a" stroke="#0f172a" stroke-width="2.5"/>');
    svg.push('<text x="' + (z2X + z2W_px / 2) + '" y="' + (groundY - apexH_px - 8) + '" text-anchor="middle" font-size="10.5" font-weight="900" fill="#0284c7">100% RAINPROOF 0.50mm PPGL ROOF (45 FT SPAN ONLY)</text>');

    // ─────────────────────────────────────────────────────────────────────────
    // ZONE C: REMAINING OPEN SPACE (40 FT = 288 px OUTSIDE METAL STRUCTURE)
    // ZERO STEEL COLUMNS, ZERO RAFTERS, ZERO ROOF!
    // ─────────────────────────────────────────────────────────────────────────
    var z3W_px = 40 * scale; // 288 px
    svg.push('<rect x="' + z3X + '" y="' + (groundY - 8) + '" width="' + z3W_px + '" height="8" fill="#e2e8f0" stroke="#cbd5e1" stroke-width="1"/>');
    svg.push('<text x="' + (z3X + z3W_px / 2) + '" y="' + (groundY - eaveH_px / 2 - 10) + '" text-anchor="middle" font-size="11" font-weight="bold" fill="#64748b">ZONE C: REMAINING OPEN SPACE (40 FT)</text>');
    svg.push('<text x="' + (z3X + z3W_px / 2) + '" y="' + (groundY - eaveH_px / 2 + 10) + '" text-anchor="middle" font-size="9.5" fill="#94a3b8">OPEN GROUND • ZERO CONSTRUCTION IN SCOPE</text>');

    // 10 Vertical Ladder Columns spanning STRICTLY 0 to 145 FT (28 Columns Total)
    var sideFrameXs = [
      cx,
      cx + (z1W_px / 6) * 1,
      cx + (z1W_px / 6) * 2,
      cx + (z1W_px / 6) * 3,
      cx + (z1W_px / 6) * 4,
      cx + (z1W_px / 6) * 5,
      z2X,
      z2X + (z2W_px / 3) * 1,
      z2X + (z2W_px / 3) * 2,
      z3X // 145 FT (Stops at front wall of Badminton complex)
    ];
    for (var sfi = 0; sfi < sideFrameXs.length; sfi++) {
      var colX = sideFrameXs[sfi];
      svg.push('<line x1="' + colX + '" y1="' + groundY + '" x2="' + colX + '" y2="' + (groundY - eaveH_px) + '" stroke="#0f172a" stroke-width="3"/>');
    }

    // 7 Horizontal Tie Framework Tiers across STRICTLY 145 FT (cx to z3X)
    for (var ti = 1; ti <= 7; ti++) {
      var ty = groundY - (ti * (eaveH_px / 7.5));
      svg.push('<line x1="' + cx + '" y1="' + ty + '" x2="' + z3X + '" y2="' + ty + '" stroke="#64748b" stroke-width="1.2" stroke-dasharray="8,4"/>');
    }

    // Left Height Dimension: CLEAR HEIGHT: 30 FT
    var dimLeftX = cx - 40;
    svg.push('<line x1="' + dimLeftX + '" y1="' + groundY + '" x2="' + dimLeftX + '" y2="' + (groundY - eaveH_px) + '" stroke="#0f172a" stroke-width="2" marker-start="url(#sideArr185)" marker-end="url(#sideArr185)"/>');
    svg.push('<text x="' + (dimLeftX - 14) + '" y="' + (groundY - eaveH_px / 2) + '" transform="rotate(-90 ' + (dimLeftX - 14) + ' ' + (groundY - eaveH_px / 2) + ')" text-anchor="middle" font-size="13" font-weight="900" fill="#0f172a">CLEAR HEIGHT: 30 FT</text>');

    // Bottom Dimension: OVERALL ARENA LENGTH: 185 FT
    var dimBotY = groundY + 45;
    svg.push('<line x1="' + cx + '" y1="' + dimBotY + '" x2="' + (cx + plotW_px) + '" y2="' + dimBotY + '" stroke="#0f172a" stroke-width="2" marker-start="url(#sideArr185)" marker-end="url(#sideArr185)"/>');
    svg.push('<text x="' + (cx + plotW_px / 2) + '" y="' + (dimBotY + 22) + '" text-anchor="middle" font-size="14" font-weight="900" fill="#0f172a">OVERALL PLOT: 185 FT • 145 FT METAL STRUCTURE (CRICKET + BADMINTON) + 40 FT OPEN PARKING</text>');

    svg.push('</svg>');
    container.innerHTML = svg.join('');
  }
  function renderMultiSportArena185x60CrossElevation(container, config) {
    if (!container) return;
    var svgW = 1600;
    var svgH = 850;
    var scale = 14.0; // 14 px per foot (60 FT * 14 = 840 px)
    var plotW_px = 60 * scale; // 840 px
    var eaveH_px = 30 * scale; // 420 px
    var apexH_px = 34 * scale; // 476 px (34 FT apex)
    var cx = (svgW - plotW_px) / 2; // 380 px
    var groundY = svgH - 140;

    var svg = [];
    svg.push('<svg viewBox="0 0 ' + svgW + ' ' + svgH + '" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;background:#ffffff;font-family:Inter,Segoe UI,sans-serif;" preserveAspectRatio="xMidYMid meet">');
    svg.push('<defs><marker id="crossArr185" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M 0 1.5 L 10 5 L 0 8.5 z" fill="#0f172a"/></marker></defs>');

    // Title Sheet Header Bar
    svg.push('<rect x="' + cx + '" y="20" width="' + plotW_px + '" height="44" rx="5" fill="#0f172a" stroke="#1e293b" stroke-width="2"/>');
    svg.push('<text x="' + (svgW / 2) + '" y="' + 47 + '" text-anchor="middle" font-size="15.5" font-weight="900" fill="#ffffff" letter-spacing="1">ARCHITECTURAL 2D CROSS SECTION • 60 FT SPAN • 30 FT EAVES HEIGHT</text>');

    // Ground Line
    svg.push('<line x1="' + (cx - 40) + '" y1="' + groundY + '" x2="' + (cx + plotW_px + 40) + '" y2="' + groundY + '" stroke="#0f172a" stroke-width="4"/>');
    svg.push('<text x="' + (cx - 20) + '" y="' + (groundY + 22) + '" font-size="11.5" font-weight="bold" fill="#64748b">NATURAL GROUND LEVEL (GL ± 0.00)</text>');

    // Left & Right Structural Ladder Columns
    var leftX = cx;
    var rightX = cx + plotW_px;
    var eaveY = groundY - eaveH_px;
    var apexY = groundY - apexH_px;
    var midX = cx + plotW_px / 2;

    svg.push('<line x1="' + leftX + '" y1="' + groundY + '" x2="' + leftX + '" y2="' + eaveY + '" stroke="#0f172a" stroke-width="5"/>');
    svg.push('<line x1="' + rightX + '" y1="' + groundY + '" x2="' + rightX + '" y2="' + eaveY + '" stroke="#0f172a" stroke-width="5"/>');

    // Bottom Tie Beam of Truss
    svg.push('<line x1="' + leftX + '" y1="' + eaveY + '" x2="' + rightX + '" y2="' + eaveY + '" stroke="#0f172a" stroke-width="3"/>');

    // Triangular Gable Pitch Rafters
    svg.push('<line x1="' + leftX + '" y1="' + eaveY + '" x2="' + midX + '" y2="' + apexY + '" stroke="#1e3a8a" stroke-width="5"/>');
    svg.push('<line x1="' + rightX + '" y1="' + eaveY + '" x2="' + midX + '" y2="' + apexY + '" stroke="#1e3a8a" stroke-width="5"/>');

    // Center Vertical Kingpost
    svg.push('<line x1="' + midX + '" y1="' + eaveY + '" x2="' + midX + '" y2="' + apexY + '" stroke="#0f172a" stroke-width="3"/>');

    // Web Members / Truss Bracing
    for (var wstep = 1; wstep <= 4; wstep++) {
      var wxL = leftX + (wstep * (plotW_px / 10));
      var wyL = eaveY - ((apexH_px - eaveH_px) * (wstep / 5));
      svg.push('<line x1="' + wxL + '" y1="' + eaveY + '" x2="' + wxL + '" y2="' + wyL + '" stroke="#64748b" stroke-width="2"/>');
      svg.push('<line x1="' + wxL + '" y1="' + eaveY + '" x2="' + (wxL + (plotW_px / 10)) + '" y2="' + (wyL - ((apexH_px - eaveH_px) / 5)) + '" stroke="#94a3b8" stroke-width="1.5" stroke-dasharray="4,2"/>');

      var wxR = rightX - (wstep * (plotW_px / 10));
      svg.push('<line x1="' + wxR + '" y1="' + eaveY + '" x2="' + wxR + '" y2="' + wyL + '" stroke="#64748b" stroke-width="2"/>');
      svg.push('<line x1="' + wxR + '" y1="' + eaveY + '" x2="' + (wxR - (plotW_px / 10)) + '" y2="' + (wyL - ((apexH_px - eaveH_px) / 5)) + '" stroke="#94a3b8" stroke-width="1.5" stroke-dasharray="4,2"/>');
    }

    // Top Peak Apex Dimension Label
    svg.push('<line x1="' + (midX - 120) + '" y1="' + (apexY - 10) + '" x2="' + (midX + 120) + '" y2="' + (apexY - 10) + '" stroke="#ea580c" stroke-width="1.8"/>');
    svg.push('<text x="' + midX + '" y="' + (apexY - 18) + '" text-anchor="middle" font-size="11.5" font-weight="900" fill="#c2410c">PEAK APEX HEIGHT: 34 FT (SLOPE PITCH 1:7)</text>');

    // Eaves Height Dimension (Left side)
    var dimLeftX = leftX - 45;
    svg.push('<line x1="' + dimLeftX + '" y1="' + groundY + '" x2="' + dimLeftX + '" y2="' + eaveY + '" stroke="#0f172a" stroke-width="2" marker-start="url(#crossArr185)" marker-end="url(#crossArr185)"/>');
    svg.push('<text x="' + (dimLeftX - 14) + '" y="' + (groundY - eaveH_px / 2) + '" transform="rotate(-90 ' + (dimLeftX - 14) + ' ' + (groundY - eaveH_px / 2) + ')" text-anchor="middle" font-size="13" font-weight="900" fill="#0f172a">EAVES HEIGHT: 30 FT</text>');

    // Clear Span Width Dimension (Bottom)
    var dimBotY = groundY + 45;
    svg.push('<line x1="' + leftX + '" y1="' + dimBotY + '" x2="' + rightX + '" y2="' + dimBotY + '" stroke="#0f172a" stroke-width="2" marker-start="url(#crossArr185)" marker-end="url(#crossArr185)"/>');
    svg.push('<text x="' + midX + '" y="' + (dimBotY + 22) + '" text-anchor="middle" font-size="14" font-weight="900" fill="#0f172a">CLEAR SPAN WIDTH: 60 FT</text>');

    svg.push('</svg>');
    container.innerHTML = svg.join('');
  }


  function renderMegaSportsComplex105x170(container, config) {
    var svgW = 1600;
    var svgH = 900;
    var svg = [];

    // Scale and offsets for 105 FT Width × 170 FT Length in 16:9 widescreen CAD
    // Drawn in horizontal orientation (X: Length 170 FT, Y: Width 105 FT)
    var padX = 90;
    var padY = 55;
    var drawW = svgW - (padX * 2); // 1420 px
    var drawH = svgH - (padY * 2); // 790 px
    var scale = Math.min(drawW / 170, drawH / 105); // ~7.52 px/ft

    var campusW_px = 170 * scale; // 1278 px
    var campusH_px = 105 * scale; // 790 px
    var cx = (svgW - campusW_px) / 2; // ~161 px
    var cy = (svgH - campusH_px) / 2; // ~55 px

    svg.push('<svg viewBox="0 0 ' + svgW + ' ' + svgH + '" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;background:#ffffff;font-family:Inter,Segoe UI,sans-serif;">');
    svg.push('<defs>');
    svg.push('<marker id="cadArr" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M 0 1.5 L 10 5 L 0 8.5 z" fill="#0f172a"/></marker>');
    svg.push('<pattern id="pavePat" width="20" height="20" patternUnits="userSpaceOnUse"><rect width="20" height="20" fill="#f1f5f9"/><path d="M 0 10 L 20 10 M 10 0 L 10 20" stroke="#cbd5e1" stroke-width="1"/></pattern>');
    svg.push('</defs>');

    // Outer Master Boundary Foundation
    svg.push('<rect x="' + (cx - 10) + '" y="' + (cy - 10) + '" width="' + (campusW_px + 20) + '" height="' + (campusH_px + 20) + '" rx="4" fill="#ffffff" stroke="#94a3b8" stroke-width="1.5" stroke-dasharray="4,4"/>');

    // ─────────────────────────────────────────────────────────────────────────
    // ZONE 1: FRONT PARKING AREA (105 FT × 18 FT = 1,890 SFT)
    // ─────────────────────────────────────────────────────────────────────────
    var parkW_px = 18 * scale;
    svg.push('<rect x="' + cx + '" y="' + cy + '" width="' + parkW_px + '" height="' + campusH_px + '" fill="url(#pavePat)" stroke="#64748b" stroke-width="2"/>');
    svg.push('<text x="' + (cx + parkW_px / 2) + '" y="' + (cy + campusH_px / 2) + '" transform="rotate(-90 ' + (cx + parkW_px / 2) + ' ' + (cy + campusH_px / 2) + ')" text-anchor="middle" font-size="13" font-weight="900" fill="#475569" letter-spacing="1">OUTSIDE VEHICLE PARKING (105 FT × 18 FT — 1,890 SFT) • OPEN-AIR PAVER BLOCKS (OUTSIDE METAL STRUCTURE)</text>');

    // Single Main Campus Entrance Gate on Left Side at Front of Metal Structure (X = outdoorX = 18 FT mark)
    // Providing grand entry from the outside open-air parking lot into the 15 FT connecting pathway
    var gH_px = 12 * scale; // 12 FT gate
    svg.push('<rect x="' + (outdoorX - 6) + '" y="' + (cy + 1.5 * scale) + '" width="12" height="' + gH_px + '" fill="#f97316" stroke="#c2410c" stroke-width="2"/>');
    svg.push('<text x="' + (outdoorX - 12) + '" y="' + (cy + 7.5 * scale) + '" transform="rotate(-90 ' + (outdoorX - 12) + ' ' + (cy + 7.5 * scale) + ')" text-anchor="middle" font-size="10" font-weight="900" fill="#c2410c">MAIN ENTRANCE GATE (12×8 FT)</text>');

    // Indoor Arena Main Access Door (from 15 FT Pathway at X = indoorX)
    var indDoorH = 6 * scale;
    svg.push('<rect x="' + (indoorX - 6) + '" y="' + (cy + 4.5 * scale) + '" width="12" height="' + indDoorH + '" fill="#0284c7" stroke="#0369a1" stroke-width="2"/>');
    svg.push('<text x="' + (indoorX - 12) + '" y="' + (cy + 7.5 * scale) + '" transform="rotate(-90 ' + (indoorX - 12) + ' ' + (cy + 7.5 * scale) + ')" text-anchor="middle" font-size="9.5" font-weight="900" fill="#0284c7">INDOOR ARENA ENTRY DOOR (6×8 FT)</text>');

    // Note: Unwanted cricket gate removed from middle of cricket/pathway as requested by user.

    // Emergency Exit Door on Rear Wall
    svg.push('<rect x="' + (indoorX + indoorW_px - 4) + '" y="' + (cy + campusH_px / 2 - 2 * scale) + '" width="8" height="' + (4 * scale) + '" fill="#eab308" stroke="#ca8a04" stroke-width="1.5"/>');
    svg.push('<text x="' + (indoorX + indoorW_px + 12) + '" y="' + (cy + campusH_px / 2) + '" transform="rotate(90 ' + (indoorX + indoorW_px + 12) + ' ' + (cy + campusH_px / 2) + ')" text-anchor="middle" font-size="9" font-weight="900" fill="#ca8a04">EMERGENCY EXIT (4×7 FT)</text>');

    // ─────────────────────────────────────────────────────────────────────────
    // ZONE 2: OUTDOOR BOX CRICKET GROUND (90 FT × 50 FT = 4,500 SFT) & 15 FT PATHWAY
    // ─────────────────────────────────────────────────────────────────────────
    var outdoorX = cx + parkW_px;
    var outdoorW_px = 50 * scale;

    // 15 FT Connecting Pathway (along left side / Y: 0 to 15 FT)
    var pathH_px = 15 * scale;
    svg.push('<rect x="' + outdoorX + '" y="' + cy + '" width="' + outdoorW_px + '" height="' + pathH_px + '" fill="#fef3c7" stroke="#d97706" stroke-width="1.5"/>');
    svg.push('<text x="' + (outdoorX + outdoorW_px / 2) + '" y="' + (cy + pathH_px / 2 + 4) + '" text-anchor="middle" font-size="10" font-weight="900" fill="#b45309">15 FT CONNECTING PATHWAY (ACCESS TO INDOOR ARENA) ➔</text>');

    // Box Cricket Ground (Y: 15 to 105 FT = 90 FT Width)
    var cricY = cy + pathH_px;
    var cricH_px = 90 * scale;
    svg.push('<rect x="' + outdoorX + '" y="' + cricY + '" width="' + outdoorW_px + '" height="' + cricH_px + '" fill="#15803d" stroke="#166534" stroke-width="3"/>');
    svg.push('<rect x="' + (outdoorX + 6) + '" y="' + (cricY + 6) + '" width="' + (outdoorW_px - 12) + '" height="' + (cricH_px - 12) + '" fill="none" stroke="#ffffff" stroke-width="2" stroke-dasharray="8,6"/>');

    // Prominent Open-Top Outdoor Label
    svg.push('<rect x="' + (outdoorX + 12) + '" y="' + (cricY + 12) + '" width="' + (outdoorW_px - 24) + '" height="32" rx="4" fill="#ffffff" fill-opacity="0.92" stroke="#16a34a" stroke-width="1.5"/>');
    svg.push('<text x="' + (outdoorX + outdoorW_px / 2) + '" y="' + (cricY + 32) + '" text-anchor="middle" font-size="11" font-weight="900" fill="#15803d">🏏 OUTDOOR BOX CRICKET (90×50 FT) — OPEN TOP / RUSSEA™ NYLON NETS (NO GI ROOF)</text>');

    // Centered 66 FT × 10 FT Cricket Pitch
    var pDepth_px = 10 * scale;
    var pLen_px = Math.min(66 * scale, cricH_px * 0.85);
    var pitchX = outdoorX + (outdoorW_px - pDepth_px) / 2;
    var pitchY = cricY + (cricH_px - pLen_px) / 2;

    svg.push('<rect x="' + pitchX + '" y="' + pitchY + '" width="' + pDepth_px + '" height="' + pLen_px + '" fill="#fef3c7" stroke="#b45309" stroke-width="2"/>');
    svg.push('<line x1="' + pitchX + '" y1="' + (pitchY + 12) + '" x2="' + (pitchX + pDepth_px) + '" y2="' + (pitchY + 12) + '" stroke="#ffffff" stroke-width="2.5"/>');
    svg.push('<line x1="' + pitchX + '" y1="' + (pitchY + pLen_px - 12) + '" x2="' + (pitchX + pDepth_px) + '" y2="' + (pitchY + pLen_px - 12) + '" stroke="#ffffff" stroke-width="2.5"/>');
    svg.push('<text x="' + (pitchX + pDepth_px / 2) + '" y="' + (pitchY + pLen_px / 2) + '" transform="rotate(-90 ' + (pitchX + pDepth_px / 2) + ' ' + (pitchY + pLen_px / 2) + ')" text-anchor="middle" font-size="9.5" font-weight="bold" fill="#713f12">MATCH PITCH (66 FT × 10 FT)</text>');

    // ─────────────────────────────────────────────────────────────────────────
    // ZONE 3: INDOOR SPORTS COMPLEX (105 FT × 102 FT = 10,710 SFT ~ 11,000 SFT)
    // ENCLOSED IN THICK SOLID WALLS WITH GI PROFILE SHEET ROOF
    // ─────────────────────────────────────────────────────────────────────────
    var indoorX = outdoorX + outdoorW_px;
    var indoorW_px = 102 * scale;

    // Civil Concrete Sub-Base Foundation Bed
    svg.push('<rect x="' + indoorX + '" y="' + cy + '" width="' + indoorW_px + '" height="' + campusH_px + '" fill="#f8fafc" stroke="#334155" stroke-width="1"/>');

    // HEAVY THICK PERIMETER WALLS (10 FT Brickwork / PEB Portal Columns)
    var wallT = 10;
    svg.push('<rect x="' + (indoorX - wallT/2) + '" y="' + (cy - wallT/2) + '" width="' + (indoorW_px + wallT) + '" height="' + (campusH_px + wallT) + '" fill="none" stroke="#0f172a" stroke-width="' + wallT + '" stroke-linejoin="round"/>');
    svg.push('<rect x="' + (indoorX - wallT/2) + '" y="' + (cy - wallT/2) + '" width="' + (indoorW_px + wallT) + '" height="' + (campusH_px + wallT) + '" fill="none" stroke="#64748b" stroke-width="1.5" stroke-dasharray="4,4"/>');

    // Prominent Indoor GI Roof Header Banner
    svg.push('<rect x="' + (indoorX + 16) + '" y="' + (cy + 10) + '" width="' + (indoorW_px - 32) + '" height="38" rx="6" fill="#1e3a8a" stroke="#3b82f6" stroke-width="2"/>');
    svg.push('<text x="' + (indoorX + indoorW_px / 2) + '" y="' + (cy + 34) + '" text-anchor="middle" font-size="13" font-weight="900" fill="#ffffff" letter-spacing="0.5">🏠 INDOOR SPORTS COMPLEX (11,000 SFT) — 4.5 FT BRICK BASE + 0.50mm PPGL GI WALL CLADDING (TO 35 FT) &amp; INSULATED ROOF</text>');

    var badZoneX = indoorX + 24;
    var badZoneY = cy + 58;
    var badW_px = 44 * scale; // 44 FT length
    var badH_px = 20 * scale; // 20 FT width
    var badSpacing = 3.5 * scale;
    var pklX = indoorX + badW_px + 45;
    var pklW_px = 44 * scale;
    var pklH_px = 20 * scale;

    // ── [REDESIGNED 2-TIER] 1. AMENITIES & RECEPTION SUITE ON TOP-LEFT
    // Front half: Main Lobby & Office facing 15 FT Pathway
    // Inner half: Gents & Ladies Washrooms/Changing Rooms facing directly towards the play areas
    var amenX = badZoneX;
    var amenY = badZoneY;
    var amenW_px = badW_px;
    var amenH_px = (2 * badH_px) + badSpacing;
    var frontH = amenH_px * 0.46; // Front zone depth (~20 FT)
    var innerH = amenH_px - frontH; // Inner zone depth (~23.5 FT)
    var innerY = amenY + frontH;

    svg.push('<rect x="' + amenX + '" y="' + amenY + '" width="' + amenW_px + '" height="' + amenH_px + '" rx="5" fill="#f8fafc" stroke="#334155" stroke-width="2.5"/>');

    // 1A. North Entry Banner (Facing 15 FT Pathway)
    svg.push('<rect x="' + amenX + '" y="' + (amenY - 18) + '" width="' + amenW_px + '" height="18" rx="3" fill="#0284c7"/>');
    svg.push('<text x="' + (amenX + amenW_px / 2) + '" y="' + (amenY - 5) + '" text-anchor="middle" font-size="8.5" font-weight="900" fill="#ffffff">➔ GUEST / VISITOR ENTRY FROM 15 FT CONNECTING PATHWAY ➔</text>');

    // 1B. FRONT ZONE - MAIN LOBBY & RECEPTION FOYER (North-West)
    var lobbyW = amenW_px * 0.56;
    svg.push('<rect x="' + amenX + '" y="' + amenY + '" width="' + lobbyW + '" height="' + frontH + '" fill="#e0f2fe" stroke="#38bdf8" stroke-width="1.5"/>');
    svg.push('<text x="' + (amenX + lobbyW / 2) + '" y="' + (amenY + frontH / 2 - 8) + '" text-anchor="middle" font-size="10.5" font-weight="900" fill="#0369a1">🚪 MAIN LOBBY</text>');
    svg.push('<text x="' + (amenX + lobbyW / 2) + '" y="' + (amenY + frontH / 2 + 6) + '" text-anchor="middle" font-size="8" font-weight="bold" fill="#0284c7">ENTRANCE FOYER &amp; RECEPTION</text>');
    svg.push('<text x="' + (amenX + lobbyW / 2) + '" y="' + (amenY + frontH / 2 + 18) + '" text-anchor="middle" font-size="7.5" fill="#0369a1">VISITOR LOUNGE • CHECK-IN DESK</text>');

    // 1C. FRONT ZONE - OFFICE ROOM & BILLING DESK (North-East)
    var offW = amenW_px - lobbyW;
    svg.push('<rect x="' + (amenX + lobbyW) + '" y="' + amenY + '" width="' + offW + '" height="' + frontH + '" fill="#ffffff" stroke="#cbd5e1" stroke-width="1.5"/>');
    svg.push('<text x="' + (amenX + lobbyW + offW / 2) + '" y="' + (amenY + frontH / 2 - 8) + '" text-anchor="middle" font-size="10" font-weight="900" fill="#1e293b">🏢 OFFICE ROOM</text>');
    svg.push('<text x="' + (amenX + lobbyW + offW / 2) + '" y="' + (amenY + frontH / 2 + 6) + '" text-anchor="middle" font-size="8" font-weight="bold" fill="#475569">&amp; BILLING DESK</text>');
    svg.push('<text x="' + (amenX + lobbyW + offW / 2) + '" y="' + (amenY + frontH / 2 + 18) + '" text-anchor="middle" font-size="7.5" fill="#64748b">MANAGER CABIN • POS</text>');

    // 1D. INNER ZONE - GENTS WASHROOMS & CHANGING SUITE (South-West, Facing Courts)
    var washW = amenW_px / 2;
    svg.push('<rect x="' + amenX + '" y="' + innerY + '" width="' + washW + '" height="' + innerH + '" fill="#f1f5f9" stroke="#94a3b8" stroke-width="1.5"/>');
    svg.push('<text x="' + (amenX + washW / 2) + '" y="' + (innerY + innerH / 2 - 10) + '" text-anchor="middle" font-size="10" font-weight="900" fill="#1e293b">🚹 GENTS CHANGING</text>');
    svg.push('<text x="' + (amenX + washW / 2) + '" y="' + (innerY + innerH / 2 + 4) + '" text-anchor="middle" font-size="8" font-weight="bold" fill="#475569">&amp; WASHROOMS</text>');
    svg.push('<text x="' + (amenX + washW / 2) + '" y="' + (innerY + innerH / 2 + 16) + '" text-anchor="middle" font-size="7.5" fill="#64748b">LOCKERS • SHOWERS • WC</text>');

    // 1E. INNER ZONE - LADIES WASHROOMS & CHANGING SUITE (South-East, Facing Courts)
    svg.push('<rect x="' + (amenX + washW) + '" y="' + innerY + '" width="' + washW + '" height="' + innerH + '" fill="#fdf2f8" stroke="#f472b6" stroke-width="1.5"/>');
    svg.push('<text x="' + (amenX + washW * 1.5) + '" y="' + (innerY + innerH / 2 - 10) + '" text-anchor="middle" font-size="10" font-weight="900" fill="#9d174d">🚺 LADIES CHANGING</text>');
    svg.push('<text x="' + (amenX + washW * 1.5) + '" y="' + (innerY + innerH / 2 + 4) + '" text-anchor="middle" font-size="8" font-weight="bold" fill="#be185d">&amp; WASHROOMS</text>');
    svg.push('<text x="' + (amenX + washW * 1.5) + '" y="' + (innerY + innerH / 2 + 16) + '" text-anchor="middle" font-size="7.5" fill="#db2777">LOCKERS • SHOWERS • VANITY</text>');

    // 1F. South Player Direct Access Banner (Directly facing Badminton & Pickleball courts!)
    svg.push('<rect x="' + amenX + '" y="' + (amenY + amenH_px) + '" width="' + amenW_px + '" height="18" rx="3" fill="#059669"/>');
    svg.push('<text x="' + (amenX + amenW_px / 2) + '" y="' + (amenY + amenH_px + 13) + '" text-anchor="middle" font-size="8" font-weight="900" fill="#ffffff">➔ DIRECT PLAYER ACCESS TO BADMINTON &amp; PICKLEBALL COURTS ➔</text>');

    // ── 2. 2 REGULATION PICKLEBALL COURTS (TOP-RIGHT)
    for (var p = 0; p < 2; p++) {
      var py = badZoneY + (p * (pklH_px + badSpacing * 1.5));
      svg.push('<rect x="' + pklX + '" y="' + py + '" width="' + pklW_px + '" height="' + pklH_px + '" rx="3" fill="#1e40af" stroke="#1e3a8a" stroke-width="2"/>');
      var pNetX = pklX + pklW_px / 2;
      var nvDist = 7 * scale;
      svg.push('<rect x="' + (pNetX - nvDist) + '" y="' + (py + 2) + '" width="' + (nvDist * 2) + '" height="' + (pklH_px - 4) + '" fill="#0284c7" fill-opacity="0.8"/>');
      svg.push('<rect x="' + (pklX + 2) + '" y="' + (py + 2) + '" width="' + (pklW_px - 4) + '" height="' + (pklH_px - 4) + '" fill="none" stroke="#ffffff" stroke-width="1.5"/>');
      svg.push('<line x1="' + pNetX + '" y1="' + (py - 2) + '" x2="' + pNetX + '" y2="' + (py + pklH_px + 2) + '" stroke="#dc2626" stroke-width="3"/>');
      svg.push('<line x1="' + (pNetX - nvDist) + '" y1="' + py + '" x2="' + (pNetX - nvDist) + '" y2="' + (py + pklH_px) + '" stroke="#ffffff" stroke-width="1.5"/>');
      svg.push('<line x1="' + (pNetX + nvDist) + '" y1="' + py + '" x2="' + (pNetX + nvDist) + '" y2="' + (py + pklH_px) + '" stroke="#ffffff" stroke-width="1.5"/>');
      svg.push('<line x1="' + (pNetX - nvDist) + '" y1="' + (py + pklH_px / 2) + '" x2="' + pklX + '" y2="' + (py + pklH_px / 2) + '" stroke="#ffffff" stroke-width="1.2"/>');
      svg.push('<line x1="' + (pNetX + nvDist) + '" y1="' + (py + pklH_px / 2) + '" x2="' + (pklX + pklW_px) + '" y2="' + (py + pklH_px / 2) + '" stroke="#ffffff" stroke-width="1.2"/>');
      svg.push('<text x="' + (pklX + 12) + '" y="' + (py + pklH_px / 2 + 4) + '" font-size="10" font-weight="900" fill="#ffffff">PICKLEBALL CT ' + (p + 1) + ' (44×20 FT)</text>');
      svg.push('<text x="' + (pklX + pklW_px - 12) + '" y="' + (py + pklH_px / 2 + 4) + '" text-anchor="end" font-size="9" font-weight="bold" fill="#bae6fd">8-LAYER ACRYLIC</text>');
    }

    // Helper to render 2D Badminton Court
    var renderBadmintonCourtSvg = function(bx, by, ctNum) {
      svg.push('<rect x="' + bx + '" y="' + by + '" width="' + badW_px + '" height="' + badH_px + '" rx="3" fill="#047857" stroke="#065f46" stroke-width="2"/>');
      svg.push('<rect x="' + (bx + 2) + '" y="' + (by + 2) + '" width="' + (badW_px - 4) + '" height="' + (badH_px - 4) + '" fill="none" stroke="#ffffff" stroke-width="1.5"/>');
      var netX = bx + badW_px / 2;
      svg.push('<line x1="' + netX + '" y1="' + (by - 2) + '" x2="' + netX + '" y2="' + (by + badH_px + 2) + '" stroke="#dc2626" stroke-width="3"/>');
      var sslDist = 6.5 * scale;
      svg.push('<line x1="' + (netX - sslDist) + '" y1="' + by + '" x2="' + (netX - sslDist) + '" y2="' + (by + badH_px) + '" stroke="#ffffff" stroke-width="1.2"/>');
      svg.push('<line x1="' + (netX + sslDist) + '" y1="' + by + '" x2="' + (netX + sslDist) + '" y2="' + (by + badH_px) + '" stroke="#ffffff" stroke-width="1.2"/>');
      svg.push('<line x1="' + (netX - sslDist) + '" y1="' + (by + badH_px / 2) + '" x2="' + bx + '" y2="' + (by + badH_px / 2) + '" stroke="#ffffff" stroke-width="1"/>');
      svg.push('<line x1="' + (netX + sslDist) + '" y1="' + (by + badH_px / 2) + '" x2="' + (bx + badW_px) + '" y2="' + (by + badH_px / 2) + '" stroke="#ffffff" stroke-width="1"/>');
      svg.push('<text x="' + (bx + 12) + '" y="' + (by + badH_px / 2 + 4) + '" font-size="10" font-weight="900" fill="#ffffff">BADMINTON CT ' + ctNum + ' (44×20 FT)</text>');
      svg.push('<text x="' + (bx + badW_px - 12) + '" y="' + (by + badH_px / 2 + 4) + '" text-anchor="end" font-size="9" font-weight="bold" fill="#a7f3d0">4.5mm BWF VINYL</text>');
    };

    // ── 3. BADMINTON COURTS 1 & 2 on BOTTOM-LEFT
    renderBadmintonCourtSvg(badZoneX, badZoneY + (2 * (badH_px + badSpacing)), 1);
    renderBadmintonCourtSvg(badZoneX, badZoneY + (3 * (badH_px + badSpacing)), 2);

    // ── [SWAPPED] 4. BADMINTON COURTS 3 & 4 on BOTTOM-RIGHT (swapped from amenities)
    renderBadmintonCourtSvg(pklX, badZoneY + (2 * (badH_px + badSpacing)), 3);
    renderBadmintonCourtSvg(pklX, badZoneY + (3 * (badH_px + badSpacing)), 4);

    // ── MAIN DIMENSION ARROWS & ANNOTATIONS
    var dimTopY = cy - 28;
    svg.push('<line x1="' + cx + '" y1="' + dimTopY + '" x2="' + (cx + campusW_px) + '" y2="' + dimTopY + '" stroke="#0f172a" stroke-width="2" marker-start="url(#cadArr)" marker-end="url(#cadArr)"/>');
    svg.push('<text x="' + (cx + campusW_px / 2) + '" y="' + (dimTopY - 8) + '" text-anchor="middle" font-size="13" font-weight="900" fill="#0f172a">TOTAL PLOT LENGTH: 170 FT-0" (PARKING: 18 FT • CRICKET: 50 FT • INDOOR HALL: 102 FT)</text>');

    var dimLeftX = cx - 35;
    svg.push('<line x1="' + dimLeftX + '" y1="' + cy + '" x2="' + dimLeftX + '" y2="' + (cy + campusH_px) + '" stroke="#0f172a" stroke-width="2" marker-start="url(#cadArr)" marker-end="url(#cadArr)"/>');
    svg.push('<text x="' + (dimLeftX - 10) + '" y="' + (cy + campusH_px / 2) + '" transform="rotate(-90 ' + (dimLeftX - 10) + ' ' + (cy + campusH_px / 2) + ')" text-anchor="middle" font-size="13" font-weight="900" fill="#0f172a">TOTAL PLOT WIDTH: 105 FT-0"</text>');

    var dimBotY = cy + campusH_px + 28;
    svg.push('<line x1="' + cx + '" y1="' + dimBotY + '" x2="' + (cx + parkW_px) + '" y2="' + dimBotY + '" stroke="#64748b" stroke-width="1.5" marker-start="url(#cadArr)" marker-end="url(#cadArr)"/>');
    svg.push('<text x="' + (cx + parkW_px / 2) + '" y="' + (dimBotY + 16) + '" text-anchor="middle" font-size="11" font-weight="bold" fill="#64748b">PARKING: 18 FT</text>');

    svg.push('<line x1="' + outdoorX + '" y1="' + dimBotY + '" x2="' + (outdoorX + outdoorW_px) + '" y2="' + dimBotY + '" stroke="#16a34a" stroke-width="1.5" marker-start="url(#cadArr)" marker-end="url(#cadArr)"/>');
    svg.push('<text x="' + (outdoorX + outdoorW_px / 2) + '" y="' + (dimBotY + 16) + '" text-anchor="middle" font-size="11" font-weight="bold" fill="#16a34a">OUTDOOR CRICKET: 50 FT</text>');

    svg.push('<line x1="' + indoorX + '" y1="' + dimBotY + '" x2="' + (indoorX + indoorW_px) + '" y2="' + dimBotY + '" stroke="#1e3a8a" stroke-width="1.5" marker-start="url(#cadArr)" marker-end="url(#cadArr)"/>');
    svg.push('<text x="' + (indoorX + indoorW_px / 2) + '" y="' + (dimBotY + 16) + '" text-anchor="middle" font-size="11" font-weight="bold" fill="#1e3a8a">INDOOR PEB HALL: 102 FT (4.5 FT BRICK BASE + 0.50mm GI WALL SHEETING &amp; ROOF)</text>');

    svg.push('</svg>');
    container.innerHTML = svg.join('');
  }


  function render2DFloorPlan(svgContainerId, config) {
    var container = (typeof svgContainerId === 'string') ? document.getElementById(svgContainerId) : svgContainerId;
    if (!container) return;

    var sport = config.sport_type || 'box_cricket';
    var L_ft = parseFloat(config.length) || 90;
    var W_ft = parseFloat(config.width) || 60;

    if (sport === 'multisport_4in1_100x60' || (config && (config.id === 'EST-202610-026' || config.doc_id === 'EST-202610-026')) || (L_ft === 100 && W_ft === 60 && (sport === 'multisport_4in1' || config.is_multi_4in1))) {
      renderMultiSportArena100x60FloorPlan(container, config);
      return;
    }

    if (sport === 'multisport_arena_complex_185x60' || (L_ft === 185 && W_ft === 60)) {
      renderMultiSportArena185x60FloorPlan(container, config);
      return;
    }

    var sport = config.sport_type || 'box_cricket';
    var L_ft = parseFloat(config.length) || 90;
    var W_ft = parseFloat(config.width) || 60;

    if (sport === 'multisport_complex_105x170' || (L_ft === 170 && W_ft === 105) || (config.zones && config.zones.indoor_badminton)) {
      renderMegaSportsComplex105x170(container, config);
      return;
    }

    var L_ft = parseFloat(config.length) || 90;
    var W_ft = parseFloat(config.width) || 60;
    var sport = config.sport_type || 'box_cricket';
    var isCricket = checkIsCricket(sport, config);
    var isCircular = (sport === 'cricket_circular');
    var isOval = (sport === 'cricket_oval');

    var layers = config.layers || {};
    if (typeof layers === 'string') {
      try { layers = JSON.parse(layers); } catch(e) { layers = {}; }
    }
    var showGates = (layers.gates !== false && parseInt(config.gate_count || 1) > 0);

    var svgW = 1600;
    var svgH = 900;
    var padX = 180;
    var padY = 120;
    var maxDrawW = svgW - (padX * 2);
    var maxDrawH = svgH - (padY * 2);
    var scale = Math.min(maxDrawW / L_ft, maxDrawH / W_ft);

    var courtW = L_ft * scale;
    var courtH = W_ft * scale;
    var cx = (svgW - courtW) / 2;
    var cy = (svgH - courtH) / 2;

    var fieldFill = (sport === 'tennis' || sport.indexOf('basket') !== -1 || sport.indexOf('pickle') !== -1) ? '#eff6ff' : '#f0fdf4';
    var fieldStroke = (sport === 'tennis' || sport.indexOf('basket') !== -1) ? '#2563eb' : '#15803d';

    var isTrainingNet = (sport === 'training_net' || sport.indexOf('cricket_training') !== -1 || sport.indexOf('training_net') !== -1 || (config.is_training_net === true) || (config.shape === 'training_net') || (config.is_front_open === true) || (config.open_front === true) || (config.open_sides && config.open_sides.front === true));
    var is2Lane = (sport === 'training_net_2lane');
    var is3Lane = (sport === 'training_net_3lane');

    var svg = [];
    svg.push('<svg viewBox="0 0 ' + svgW + ' ' + svgH + '" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;background:#ffffff;font-family:Inter,Segoe UI,sans-serif;">');
    svg.push('<defs><marker id="arrowCAD" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M 0 1.5 L 10 5 L 0 8.5 z" fill="#0f172a"/></marker></defs>');

    // Civil Sub-Base Slab
    svg.push('<rect x="' + (cx - 15) + '" y="' + (cy - 15) + '" width="' + (courtW + 30) + '" height="' + (courtH + 30) + '" rx="8" fill="#f8fafc" stroke="#cbd5e1" stroke-width="2" stroke-dasharray="6,4"/>');
    svg.push('<text x="' + (cx + 10) + '" y="' + (cy - 22) + '" font-size="11" font-weight="bold" fill="#64748b">REINFORCED CIVIL BED / ASPHALT BASE (SLOPE 1:100)</text>');

    // Turf Ground
    if (isCircular) {
      svg.push('<ellipse cx="' + (svgW / 2) + '" cy="' + (svgH / 2) + '" rx="' + (courtW / 2) + '" ry="' + (courtH / 2) + '" fill="' + fieldFill + '" stroke="' + fieldStroke + '" stroke-width="3"/>');
    } else if (isOval) {
      svg.push('<ellipse cx="' + (svgW / 2) + '" cy="' + (svgH / 2) + '" rx="' + (courtW / 2) + '" ry="' + (courtH / 2) + '" fill="' + fieldFill + '" stroke="' + fieldStroke + '" stroke-width="3"/>');
    } else if (isTrainingNet) {
      svg.push('<path d="M ' + cx + ' ' + cy + ' L ' + (cx + courtW) + ' ' + cy + ' L ' + (cx + courtW) + ' ' + (cy + courtH) + ' L ' + cx + ' ' + (cy + courtH) + '" fill="' + fieldFill + '" stroke="' + fieldStroke + '" stroke-width="3"/>');
      svg.push('<line x1="' + cx + '" y1="' + cy + '" x2="' + cx + '" y2="' + (cy + courtH) + '" stroke="#16a34a" stroke-width="2" stroke-dasharray="4,4"/>');
      svg.push('<text x="' + (cx - 20) + '" y="' + (svgH / 2 + 4) + '" transform="rotate(-90 ' + (cx - 20) + ' ' + (svgH / 2) + ')" text-anchor="middle" font-size="11" font-weight="bold" fill="#16a34a">OPEN BOWLER PORTAL (RUN-UP)</text>');

      if (is2Lane) {
        svg.push('<line x1="' + cx + '" y1="' + (cy + courtH / 2) + '" x2="' + (cx + courtW) + '" y2="' + (cy + courtH / 2) + '" stroke="#64748b" stroke-width="2.5" stroke-dasharray="6,4"/>');
        svg.push('<text x="' + (cx + courtW * 0.5) + '" y="' + (cy + courtH / 2 - 6) + '" text-anchor="middle" font-size="10" font-weight="bold" fill="#475569">INTERNAL DIVIDER NETTING CURTAIN</text>');
      } else if (is3Lane) {
        svg.push('<line x1="' + cx + '" y1="' + (cy + courtH / 3) + '" x2="' + (cx + courtW) + '" y2="' + (cy + courtH / 3) + '" stroke="#64748b" stroke-width="2.5" stroke-dasharray="6,4"/>');
        svg.push('<line x1="' + cx + '" y1="' + (cy + 2 * courtH / 3) + '" x2="' + (cx + courtW) + '" y2="' + (cy + 2 * courtH / 3) + '" stroke="#64748b" stroke-width="2.5" stroke-dasharray="6,4"/>');
      }
    } else {
      svg.push('<rect x="' + cx + '" y="' + cy + '" width="' + courtW + '" height="' + courtH + '" rx="6" fill="' + fieldFill + '" stroke="' + fieldStroke + '" stroke-width="3"/>');
    }

    // Cricket Pitch Markings
    if (isCricket) {
      var pitchL_ft = parseFloat(config.pitch_length) || 66;
      var pitchW_ft = 6.5;
      var pW_px = Math.min(pitchL_ft * scale, courtW * 0.95);
      var pH_px = pitchW_ft * scale;
      var pxX = (svgW - pW_px) / 2;

      var pitchYPositions = [(svgH - pH_px) / 2];
      if (is2Lane) {
        pitchYPositions = [cy + (courtH / 4) - (pH_px / 2), cy + (3 * courtH / 4) - (pH_px / 2)];
      } else if (is3Lane) {
        pitchYPositions = [cy + (courtH / 6) - (pH_px / 2), cy + (courtH / 2) - (pH_px / 2), cy + (5 * courtH / 6) - (pH_px / 2)];
      }

      for (var pyi = 0; pyi < pitchYPositions.length; pyi++) {
        var pxY = pitchYPositions[pyi];
        svg.push('<rect x="' + pxX + '" y="' + pxY + '" width="' + pW_px + '" height="' + pH_px + '" rx="2" fill="#fef3c7" stroke="#b45309" stroke-width="2"/>');
        svg.push('<line x1="' + (pxX + 16) + '" y1="' + pxY + '" x2="' + (pxX + 16) + '" y2="' + (pxY + pH_px) + '" stroke="#ffffff" stroke-width="2.5"/>');
        svg.push('<line x1="' + (pxX + pW_px - 16) + '" y1="' + pxY + '" x2="' + (pxX + pW_px - 16) + '" y2="' + (pxY + pH_px) + '" stroke="#ffffff" stroke-width="2.5"/>');
        svg.push('<circle cx="' + (pxX + 16) + '" cy="' + (pxY + pH_px / 2) + '" r="3.5" fill="#ca8a04" stroke="#713f12" stroke-width="1"/>');
        svg.push('<circle cx="' + (pxX + pW_px - 16) + '" cy="' + (pxY + pH_px / 2) + '" r="3.5" fill="#ca8a04" stroke="#713f12" stroke-width="1"/>');
        svg.push('<text x="' + (svgW / 2) + '" y="' + (pxY + pH_px / 2 + 4) + '" text-anchor="middle" font-size="11" font-weight="bold" fill="#713f12">CRICKET PITCH (' + pitchL_ft + ' FT × 6.5 FT)</text>');
      }
    }

    if (sport.indexOf('football') !== -1 || sport === 'futsal' || sport === 'multi_sport' || sport === 'multi_futsal_basket') {
      svg.push('<line x1="' + (svgW / 2) + '" y1="' + cy + '" x2="' + (svgW / 2) + '" y2="' + (cy + courtH) + '" stroke="#15803d" stroke-width="2" stroke-dasharray="6,4"/>');
      svg.push('<circle cx="' + (svgW / 2) + '" cy="' + (svgH / 2) + '" r="' + (40 * scale / 5) + '" fill="none" stroke="#15803d" stroke-width="2"/>');
      var gW_px = 35 * scale / 5;
      svg.push('<rect x="' + (cx - 14) + '" y="' + (cy + (courtH - gW_px) / 2) + '" width="14" height="' + gW_px + '" fill="#fee2e2" stroke="#dc2626" stroke-width="2"/>');
      svg.push('<rect x="' + (cx + courtW) + '" y="' + (cy + (courtH - gW_px) / 2) + '" width="14" height="' + gW_px + '" fill="#fee2e2" stroke="#dc2626" stroke-width="2"/>');
      svg.push('<text x="' + (cx - 20) + '" y="' + (cy + courtH / 2 + 4) + '" transform="rotate(-90 ' + (cx - 20) + ' ' + (cy + courtH / 2) + ')" text-anchor="middle" font-size="11" font-weight="bold" fill="#dc2626">GOAL 1</text>');
      svg.push('<text x="' + (cx + courtW + 28) + '" y="' + (cy + courtH / 2 + 4) + '" transform="rotate(90 ' + (cx + courtW + 28) + ' ' + (cy + courtH / 2) + ')" text-anchor="middle" font-size="11" font-weight="bold" fill="#dc2626">GOAL 2</text>');
    }

    if (sport === 'outdoor_multi_sports_120x60') {
      var inW = courtW * 0.88;
      var inH = courtH * 0.86;
      var inX = cx + (courtW - inW) / 2;
      var inY = cy + (courtH - inH) / 2;

      // 1. Run-Off Apron (Forest Green)
      svg.push('<rect x="' + cx + '" y="' + cy + '" width="' + courtW + '" height="' + courtH + '" fill="#15803d" stroke="#0f172a" stroke-width="2"/>');

      // 2. Main Basketball Court (Royal Blue)
      svg.push('<rect x="' + inX + '" y="' + inY + '" width="' + inW + '" height="' + inH + '" fill="#1d4ed8" stroke="#ffffff" stroke-width="3"/>');

      var midX = svgW / 2;
      var midY = svgH / 2;

      // Center Division Line (White)
      svg.push('<line x1="' + midX + '" y1="' + inY + '" x2="' + midX + '" y2="' + (inY + inH) + '" stroke="#ffffff" stroke-width="2.5"/>');

      // Center Jump Circle (Terracotta Red, 12 FT diameter)
      var cRadius = Math.min(32, inH * 0.20);
      svg.push('<circle cx="' + midX + '" cy="' + midY + '" r="' + cRadius + '" fill="#b91c1c" stroke="#ffffff" stroke-width="2.5"/>');

      // Basketball Keys (Terracotta Red) & 3-Point Arcs
      var kw = inW * 0.18;
      var kh = inH * 0.36;
      var ky = (svgH - kh) / 2;
      var arcRadius = inH * 0.44;

      // Left Key & 3-Point Arc
      svg.push('<rect x="' + inX + '" y="' + ky + '" width="' + kw + '" height="' + kh + '" fill="#b91c1c" stroke="#ffffff" stroke-width="2"/>');
      // Free throw circle at left key
      svg.push('<path d="M ' + (inX + kw) + ' ' + ky + ' A ' + (kh/2) + ' ' + (kh/2) + ' 0 0 1 ' + (inX + kw) + ' ' + (ky + kh) + '" fill="none" stroke="#ffffff" stroke-width="2.5"/>');
      svg.push('<path d="M ' + (inX + kw) + ' ' + ky + ' A ' + (kh/2) + ' ' + (kh/2) + ' 0 0 0 ' + (inX + kw) + ' ' + (ky + kh) + '" fill="none" stroke="#ffffff" stroke-width="2" stroke-dasharray="5,4"/>');
      // Left 3-point arc
      svg.push('<path d="M ' + inX + ' ' + (inY + 16) + ' L ' + (inX + kw * 0.72) + ' ' + (inY + 16) + ' A ' + arcRadius + ' ' + arcRadius + ' 0 0 1 ' + (inX + kw * 0.72) + ' ' + (inY + inH - 16) + ' L ' + inX + ' ' + (inY + inH - 16) + '" fill="none" stroke="#ffffff" stroke-width="2.5"/>');

      // Right Key & 3-Point Arc
      svg.push('<rect x="' + (inX + inW - kw) + '" y="' + ky + '" width="' + kw + '" height="' + kh + '" fill="#b91c1c" stroke="#ffffff" stroke-width="2"/>');
      // Free throw circle at right key
      svg.push('<path d="M ' + (inX + inW - kw) + ' ' + ky + ' A ' + (kh/2) + ' ' + (kh/2) + ' 0 0 0 ' + (inX + inW - kw) + ' ' + (ky + kh) + '" fill="none" stroke="#ffffff" stroke-width="2.5"/>');
      svg.push('<path d="M ' + (inX + inW - kw) + ' ' + ky + ' A ' + (kh/2) + ' ' + (kh/2) + ' 0 0 1 ' + (inX + inW - kw) + ' ' + (ky + kh) + '" fill="none" stroke="#ffffff" stroke-width="2" stroke-dasharray="5,4"/>');
      // Right 3-point arc
      svg.push('<path d="M ' + (inX + inW) + ' ' + (inY + 16) + ' L ' + (inX + inW - kw * 0.72) + ' ' + (inY + 16) + ' A ' + arcRadius + ' ' + arcRadius + ' 0 0 0 ' + (inX + inW - kw * 0.72) + ' ' + (inY + inH - 16) + ' L ' + (inX + inW) + ' ' + (inY + inH - 16) + '" fill="none" stroke="#ffffff" stroke-width="2.5"/>');

      // 3. 1 Pair 10 FT Basketball Poles with 1.2m Extension Overhang & 20mm Acrylic Backboard
      var bh = Math.min(32, inH * 0.30);
      // Left Basketball Post
      svg.push('<rect x="' + (inX - 2) + '" y="' + (midY - 6) + '" width="12" height="12" fill="#0f172a" stroke="#ffffff" stroke-width="1.5"/>');
      svg.push('<line x1="' + (inX + 10) + '" y1="' + midY + '" x2="' + (inX + 28) + '" y2="' + midY + '" stroke="#0f172a" stroke-width="4"/>');
      svg.push('<rect x="' + (inX + 28) + '" y="' + (midY - bh / 2) + '" width="5" height="' + bh + '" fill="#ffffff" stroke="#0284c7" stroke-width="2" rx="1"/>');
      svg.push('<circle cx="' + (inX + 38) + '" cy="' + midY + '" r="7.5" fill="none" stroke="#ea580c" stroke-width="3"/>');
      svg.push('<text x="' + (inX + 48) + '" y="' + (midY - 10) + '" font-size="9.5" font-weight="bold" fill="#ffffff">BASKETBALL HOOP 1</text>');
      svg.push('<text x="' + (inX + 48) + '" y="' + (midY + 7) + '" font-size="8" font-weight="bold" fill="#fef08a">10 FT Height • 1.2m Overhang • 20mm Acrylic Board</text>');

      // Right Basketball Post
      svg.push('<rect x="' + (inX + inW - 10) + '" y="' + (midY - 6) + '" width="12" height="12" fill="#0f172a" stroke="#ffffff" stroke-width="1.5"/>');
      svg.push('<line x1="' + (inX + inW - 10) + '" y1="' + midY + '" x2="' + (inX + inW - 28) + '" y2="' + midY + '" stroke="#0f172a" stroke-width="4"/>');
      svg.push('<rect x="' + (inX + inW - 33) + '" y="' + (midY - bh / 2) + '" width="5" height="' + bh + '" fill="#ffffff" stroke="#0284c7" stroke-width="2" rx="1"/>');
      svg.push('<circle cx="' + (inX + inW - 43) + '" cy="' + midY + '" r="7.5" fill="none" stroke="#ea580c" stroke-width="3"/>');
      svg.push('<text x="' + (inX + inW - 55) + '" y="' + (midY - 10) + '" text-anchor="end" font-size="9.5" font-weight="bold" fill="#ffffff">BASKETBALL HOOP 2</text>');
      svg.push('<text x="' + (inX + inW - 55) + '" y="' + (midY + 7) + '" text-anchor="end" font-size="8" font-weight="bold" fill="#fef08a">10 FT Height • 1.2m Overhang • 20mm Acrylic Board</text>');

      // 4. Center Tennis / Badminton Tournament Net & Posts (with Red Base Sleeves)
      svg.push('<line x1="' + midX + '" y1="' + (inY - 12) + '" x2="' + midX + '" y2="' + (inY + inH + 12) + '" stroke="#ef4444" stroke-width="4.5" stroke-dasharray="8,3"/>');
      svg.push('<rect x="' + (midX - 8) + '" y="' + (inY - 18) + '" width="16" height="16" fill="#dc2626" stroke="#ffffff" stroke-width="2" rx="3"/>');
      svg.push('<rect x="' + (midX - 8) + '" y="' + (inY + inH + 2) + '" width="16" height="16" fill="#dc2626" stroke="#ffffff" stroke-width="2" rx="3"/>');
      svg.push('<text x="' + midX + '" y="' + (inY - 24) + '" text-anchor="middle" font-size="11" font-weight="bold" fill="#dc2626">CENTER BADMINTON / TENNIS TOURNAMENT NET &amp; POSTS</text>');

      // 5. Regulation Badminton Court Markings (High-Contrast Yellow Lines)
      // BWF 44 FT x 20 FT Doubles, 17 FT Singles
      var badW = inW * 0.46;
      var badH = inH * 0.54;
      var badX = midX - (badW / 2);
      var badY = midY - (badH / 2);
      var badSinglesH = badH * (17 / 20);
      var badSinglesY = midY - (badSinglesH / 2);

      // Doubles Outer Boundary (44 FT x 20 FT)
      svg.push('<rect x="' + badX + '" y="' + badY + '" width="' + badW + '" height="' + badH + '" fill="none" stroke="#fde047" stroke-width="2.5"/>');

      // Singles Inner Sidelines (17 FT)
      svg.push('<line x1="' + badX + '" y1="' + badSinglesY + '" x2="' + (badX + badW) + '" y2="' + badSinglesY + '" stroke="#fde047" stroke-width="1.8"/>');
      svg.push('<line x1="' + badX + '" y1="' + (badSinglesY + badSinglesH) + '" x2="' + (badX + badW) + '" y2="' + (badSinglesY + badSinglesH) + '" stroke="#fde047" stroke-width="1.8"/>');

      // Short Service Lines (6.5 FT from net on each side)
      var sDist = badW * (6.5 / 44);
      svg.push('<line x1="' + (midX - sDist) + '" y1="' + badY + '" x2="' + (midX - sDist) + '" y2="' + (badY + badH) + '" stroke="#fde047" stroke-width="2"/>');
      svg.push('<line x1="' + (midX + sDist) + '" y1="' + badY + '" x2="' + (midX + sDist) + '" y2="' + (badY + badH) + '" stroke="#fde047" stroke-width="2"/>');

      // Doubles Long Service Lines (2.5 FT inside baselines)
      var lDist = badW * (2.5 / 44);
      svg.push('<line x1="' + (badX + lDist) + '" y1="' + badY + '" x2="' + (badX + lDist) + '" y2="' + (badY + badH) + '" stroke="#fde047" stroke-width="1.8" stroke-dasharray="5,3"/>');
      svg.push('<line x1="' + (badX + badW - lDist) + '" y1="' + badY + '" x2="' + (badX + badW - lDist) + '" y2="' + (badY + badH) + '" stroke="#fde047" stroke-width="1.8" stroke-dasharray="5,3"/>');

      // Center Service Line (from short service line to back baseline)
      svg.push('<line x1="' + badX + '" y1="' + midY + '" x2="' + (midX - sDist) + '" y2="' + midY + '" stroke="#fde047" stroke-width="2"/>');
      svg.push('<line x1="' + (midX + sDist) + '" y1="' + midY + '" x2="' + (badX + badW) + '" y2="' + midY + '" stroke="#fde047" stroke-width="2"/>');

      // Badges / Game Legend
      var legendY = cy + courtH - 18;
      svg.push('<rect x="' + (cx + 20) + '" y="' + legendY + '" width="' + 380 + '" height="22" rx="3" fill="rgba(15,23,42,0.85)"/>');
      svg.push('<text x="' + (cx + 210) + '" y="' + (legendY + 15) + '" text-anchor="middle" font-size="9.5" font-weight="bold" fill="#ffffff">🏀 GAME 1: BASKETBALL COURT (WHITE LINES • 10 FT HOOPS)</text>');

      svg.push('<rect x="' + (cx + courtW - 400) + '" y="' + legendY + '" width="' + 380 + '" height="22" rx="3" fill="rgba(15,23,42,0.85)"/>');
      svg.push('<text x="' + (cx + courtW - 210) + '" y="' + (legendY + 15) + '" text-anchor="middle" font-size="9.5" font-weight="bold" fill="#fef08a">🏸 GAME 2: BADMINTON COURT (YELLOW LINES • 44×20 FT BWF)</text>');

      // Perimeter Saucer Drain
      svg.push('<rect x="' + (cx - 8) + '" y="' + (cy - 8) + '" width="' + (courtW + 16) + '" height="' + (courtH + 16) + '" fill="none" stroke="#0284c7" stroke-width="2" stroke-dasharray="4,4" rx="4"/>');
      svg.push('<text x="' + (svgW / 2) + '" y="' + (cy + courtH + 34) + '" text-anchor="middle" font-size="10.5" font-weight="bold" fill="#0284c7">120 RFT REINFORCED CONCRETE OPEN SAUCER DRAIN (PERIMETER DISCHARGE)</text>');
    }

    if (sport === 'badminton' || sport === 'badminton_multi' || sport === 'hybrid_badminton_pickleball') {
      svg.push('<line x1="' + (svgW / 2) + '" y1="' + (cy + 15) + '" x2="' + (svgW / 2) + '" y2="' + (cy + courtH - 15) + '" stroke="#dc2626" stroke-width="3"/>');
      svg.push('<text x="' + (svgW / 2) + '" y="' + (cy + 8) + '" text-anchor="middle" font-size="11" font-weight="bold" fill="#dc2626">BWF TOURNAMENT NET (20 FT)</text>');
    }

    if (sport === 'tennis' || sport === 'pickleball') {
      svg.push('<line x1="' + (svgW / 2) + '" y1="' + cy + '" x2="' + (svgW / 2) + '" y2="' + (cy + courtH) + '" stroke="#dc2626" stroke-width="3"/>');
      svg.push('<text x="' + (svgW / 2) + '" y="' + (cy - 10) + '" text-anchor="middle" font-size="11" font-weight="bold" fill="#dc2626">REGULATION TOURNAMENT NET</text>');
    }

    // Structural Columns
    var lenSpans = getIntermediateSpans(L_ft, 15, 12);
    var widSpans = getIntermediateSpans(W_ft, 15, 12);
    var poleStyleCAD = config.pole_style || (config.pole_profile && (config.pole_profile.type || config.pole_profile.pole_type)) || 'ladder_2line';
    var isLadderCAD = (poleStyleCAD === 'ladder_2line' || poleStyleCAD === 's3_ladder_truss' || (typeof poleStyleCAD === 'string' && poleStyleCAD.indexOf('ladder') !== -1));

    function addColumnMarker(px, py, label, isLengthWall) {
      if (isLadderCAD) {
        if (isLengthWall) {
          svg.push('<rect x="' + (px - 9) + '" y="' + (py - 5) + '" width="7" height="10" fill="#0f172a" stroke="#ffffff" stroke-width="1"/>');
          svg.push('<rect x="' + (px + 2) + '" y="' + (py - 5) + '" width="7" height="10" fill="#0f172a" stroke="#ffffff" stroke-width="1"/>');
          svg.push('<line x1="' + (px - 2) + '" y1="' + py + '" x2="' + (px + 2) + '" y2="' + py + '" stroke="#94a3b8" stroke-width="2"/>');
        } else {
          svg.push('<rect x="' + (px - 5) + '" y="' + (py - 9) + '" width="10" height="7" fill="#0f172a" stroke="#ffffff" stroke-width="1"/>');
          svg.push('<rect x="' + (px - 5) + '" y="' + (py + 2) + '" width="10" height="7" fill="#0f172a" stroke="#ffffff" stroke-width="1"/>');
          svg.push('<line x1="' + px + '" y1="' + (py - 2) + '" x2="' + px + '" y2="' + (py + 2) + '" stroke="#94a3b8" stroke-width="2"/>');
        }
      } else {
        svg.push('<rect x="' + (px - 6) + '" y="' + (py - 6) + '" width="12" height="12" fill="#0f172a" stroke="#ffffff" stroke-width="1.5"/>');
      }
      if (label) {
        svg.push('<text x="' + px + '" y="' + (py - 10) + '" text-anchor="middle" font-size="9" font-weight="bold" fill="#0f172a">' + label + '</text>');
      }
    }

    var showStructure = (layers.structure !== false);
    if (showStructure) {
      addColumnMarker(cx, cy, 'C1', true);
      addColumnMarker(cx + courtW, cy, 'C2', true);
      addColumnMarker(cx + courtW, cy + courtH, 'C3', true);
      addColumnMarker(cx, cy + courtH, 'C4', true);

      for (var i = 0; i < lenSpans.positions.length; i++) {
        var x = cx + (lenSpans.positions[i] * scale);
        addColumnMarker(x, cy, null, true);
        addColumnMarker(x, cy + courtH, null, true);
      }

      if (!isTrainingNet) {
        for (var j = 0; j < widSpans.positions.length; j++) {
          var y = cy + (widSpans.positions[j] * scale);
          addColumnMarker(cx, y, null, false);
          addColumnMarker(cx + courtW, y, null, false);
        }
      }
    }

    // Dynamic Gates Rendering
    var resolvedGatesCAD = (showGates) ? getResolvedGates(config, L_ft, W_ft, 25) : [];
    if (resolvedGatesCAD.length > 0) {
      resolvedGatesCAD.forEach(function(g, idx) {
        var gColor = g.color || "#dc2626";
        var equipW_px = g.width_ft * scale;
        var equipH_px = 12;

        var gx = 0, gy = 0;
        if (g.wall === "right_length") {
          gx = cx + (g.pos_ft * scale) - (equipW_px / 2);
          gy = cy + courtH - (equipH_px / 2);
        } else if (g.wall === "left_length") {
          gx = cx + (g.pos_ft * scale) - (equipW_px / 2);
          gy = cy - (equipH_px / 2);
        } else if (g.wall === "front_width") {
          gx = cx - (equipH_px / 2);
          gy = cy + (g.pos_ft * scale) - (equipW_px / 2);
        } else if (g.wall === "back_width") {
          gx = cx + courtW - (equipH_px / 2);
          gy = cy + (g.pos_ft * scale) - (equipW_px / 2);
        }

        if (g.wall === "right_length" || g.wall === "left_length") {
          svg.push('<rect x="' + gx + '" y="' + gy + '" width="' + equipW_px + '" height="' + equipH_px + '" rx="2" fill="rgba(254, 226, 226, 0.95)" stroke="' + gColor + '" stroke-width="2.5"/>');
          svg.push('<line x1="' + gx + '" y1="' + (gy + equipH_px / 2) + '" x2="' + (gx + equipW_px) + '" y2="' + (gy + equipH_px / 2) + '" stroke="' + gColor + '" stroke-width="1.5" stroke-dasharray="3,3"/>');
          var textY = (g.wall === "right_length") ? (gy + 24) : (gy - 14);
          svg.push('<text x="' + (gx + equipW_px / 2) + '" y="' + textY + '" text-anchor="middle" font-size="10.5" font-weight="bold" fill="' + gColor + '">🚪 ' + g.name.substring(0, 16) + ' (' + g.width_ft + '\'W)</text>');
        } else {
          svg.push('<rect x="' + gx + '" y="' + gy + '" width="' + equipH_px + '" height="' + equipW_px + '" rx="2" fill="rgba(254, 226, 226, 0.95)" stroke="' + gColor + '" stroke-width="2.5"/>');
          svg.push('<line x1="' + (gx + equipH_px / 2) + '" y1="' + gy + '" x2="' + (gx + equipH_px / 2) + '" y2="' + (gy + equipW_px) + '" stroke="' + gColor + '" stroke-width="1.5" stroke-dasharray="3,3"/>');
          var textX = (g.wall === "front_width") ? (gx - 18) : (gx + 26);
          svg.push('<text x="' + textX + '" y="' + (gy + equipW_px / 2 + 4) + '" text-anchor="middle" font-size="10.5" font-weight="bold" fill="' + gColor + '" transform="rotate(-90 ' + textX + ' ' + (gy + equipW_px / 2) + ')">🚪 ' + g.name.substring(0, 16) + '</text>');
        }
      });
    }

    // Outer Dimension Lines
    var topDimY = cy - 45;
    svg.push('<line x1="' + cx + '" y1="' + topDimY + '" x2="' + (cx + courtW) + '" y2="' + topDimY + '" stroke="#0f172a" stroke-width="2" marker-start="url(#arrowCAD)" marker-end="url(#arrowCAD)"/>');
    svg.push('<text x="' + (svgW / 2) + '" y="' + (topDimY - 10) + '" text-anchor="middle" font-size="15" font-weight="bold" fill="#0f172a">ARENA LENGTH: ' + L_ft + ' FT-0" (' + (L_ft * 0.3048).toFixed(2) + ' M)</text>');

    var leftDimX = cx - 55;
    svg.push('<line x1="' + leftDimX + '" y1="' + cy + '" x2="' + leftDimX + '" y2="' + (cy + courtH) + '" stroke="#0f172a" stroke-width="2" marker-start="url(#arrowCAD)" marker-end="url(#arrowCAD)"/>');
    svg.push('<text x="' + (leftDimX - 15) + '" y="' + (svgH / 2) + '" transform="rotate(-90 ' + (leftDimX - 15) + ' ' + (svgH / 2) + ')" text-anchor="middle" font-size="15" font-weight="bold" fill="#0f172a">ARENA WIDTH: ' + W_ft + ' FT-0" (' + (W_ft * 0.3048).toFixed(2) + ' M)</text>');

    svg.push('</svg>');
    container.innerHTML = svg.join('');
  }

  // ==========================================
  // 2D Longitudinal Side Elevation SVG Generator
  // ==========================================
    // =========================================================================
  // DEDICATED 2D LONGITUDINAL SIDE ELEVATION: 105 FT × 170 FT CAMPUS
  // Shows 18 FT Parking -> 50 FT Outdoor Cricket (No Roof) -> 102 FT Indoor PEB Shed (GI Roof @ 35 FT H)
  // =========================================================================
  function renderMegaSportsComplex105x170SideElevation(container, config) {
    var svgW = 1600;
    var svgH = 900;
    var padX = 100;
    var padY = 80;
    var drawW = svgW - (padX * 2);
    var scale = drawW / 170; // px per foot

    var groundY = svgH - padY - 60;
    var cx = padX;

    var svg = [];
    svg.push('<svg viewBox="0 0 ' + svgW + ' ' + svgH + '" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;background:#ffffff;font-family:Inter,Segoe UI,sans-serif;">');
    svg.push('<defs><marker id="sideArr" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M 0 1.5 L 10 5 L 0 8.5 z" fill="#0f172a"/></marker></defs>');

    // Ground line
    svg.push('<line x1="' + (cx - 30) + '" y1="' + groundY + '" x2="' + (cx + drawW + 30) + '" y2="' + groundY + '" stroke="#0f172a" stroke-width="4"/>');

    // Section 1: Front Parking (18 FT) — OUTSIDE METAL STRUCTURE
    var parkW_px = 18 * scale;
    svg.push('<rect x="' + cx + '" y="' + (groundY - 6) + '" width="' + parkW_px + '" height="6" fill="#94a3b8"/>');
    svg.push('<text x="' + (cx + parkW_px / 2) + '" y="' + (groundY - 18) + '" text-anchor="middle" font-size="11" font-weight="900" fill="#475569">OPEN-AIR PARKING (18 FT)</text>');
    svg.push('<text x="' + (cx + parkW_px / 2) + '" y="' + (groundY - 5) + '" text-anchor="middle" font-size="8.5" font-weight="bold" fill="#64748b">(OUTSIDE METAL STRUCTURE)</text>');

    // Main Entrance Gate (12 FT × 8 FT) located at X = 18 FT (Front Wall of Metal Structure entering 15 FT pathway)
    svg.push('<rect x="' + (cx + parkW_px - 4) + '" y="' + (groundY - 12 * scale) + '" width="8" height="' + (12 * scale) + '" fill="#f97316" stroke="#c2410c" stroke-width="2"/>');
    svg.push('<text x="' + (cx + parkW_px) + '" y="' + (groundY - 12 * scale - 6) + '" text-anchor="middle" font-size="9.5" font-weight="bold" fill="#c2410c">MAIN ENTRANCE GATE (12×8 FT)</text>');

    // Section 2: Outdoor Box Cricket Arena (50 FT) — 30 FT High Frame, OPEN TOP / NO GI ROOF
    var cricX = cx + parkW_px;
    var cricW_px = 50 * scale;
    var cricH_px = 30 * scale;

    svg.push('<rect x="' + cricX + '" y="' + (groundY - cricH_px) + '" width="' + cricW_px + '" height="' + cricH_px + '" fill="#f0fdf4" fill-opacity="0.6" stroke="#16a34a" stroke-width="2.5"/>');
    for (var t = 1; t <= 5; t++) {
      var ty = groundY - (t * 5 * scale);
      svg.push('<line x1="' + cricX + '" y1="' + ty + '" x2="' + (cricX + cricW_px) + '" y2="' + ty + '" stroke="#15803d" stroke-width="1.5" stroke-dasharray="4,4"/>');
    }
    // Columns
    svg.push('<line x1="' + cricX + '" y1="' + groundY + '" x2="' + cricX + '" y2="' + (groundY - cricH_px) + '" stroke="#0f172a" stroke-width="4"/>');
    svg.push('<line x1="' + (cricX + cricW_px / 2) + '" y1="' + groundY + '" x2="' + (cricX + cricW_px / 2) + '" y2="' + (groundY - cricH_px) + '" stroke="#0f172a" stroke-width="3"/>');
    svg.push('<line x1="' + (cricX + cricW_px) + '" y1="' + groundY + '" x2="' + (cricX + cricW_px) + '" y2="' + (groundY - cricH_px) + '" stroke="#0f172a" stroke-width="4"/>');

    svg.push('<rect x="' + (cricX + 10) + '" y="' + (groundY - cricH_px + 10) + '" width="' + (cricW_px - 20) + '" height="32" rx="4" fill="#ffffff" stroke="#16a34a" stroke-width="1.5"/>');
    svg.push('<text x="' + (cricX + cricW_px / 2) + '" y="' + (groundY - cricH_px + 30) + '" text-anchor="middle" font-size="11" font-weight="900" fill="#15803d">🏏 OUTDOOR CRICKET (30 FT HIGH) • OPEN TOP (NO GI ROOF)</text>');

    // Section 3: Indoor PEB Sports Hall (102 FT) — 35 FT Apex Height with Gabled GI Sheet Roof
    var indX = cricX + cricW_px;
    var indW_px = 102 * scale;
    var eaveH_px = 30 * scale;
    var apexH_px = 35 * scale;

    // 4.5 FT Base Brickwork Wall (Plinth Perimeter Dado)
    var brickH_px = 4.5 * scale;
    svg.push('<rect x="' + indX + '" y="' + (groundY - brickH_px) + '" width="' + indW_px + '" height="' + brickH_px + '" fill="#cbd5e1" stroke="#475569" stroke-width="2"/>');
    svg.push('<text x="' + (indX + indW_px / 2) + '" y="' + (groundY - brickH_px / 2 + 4) + '" text-anchor="middle" font-size="9" font-weight="900" fill="#1e293b">4.5 FT SOLID BRICKWORK BASE PERIMETER WALL</text>');

    // Upper PEB Wall & Pitched Roof Profile
    var apexX = indX + indW_px / 2;
    var roofPath = 'M ' + indX + ' ' + (groundY - eaveH_px) + ' L ' + apexX + ' ' + (groundY - apexH_px) + ' L ' + (indX + indW_px) + ' ' + (groundY - eaveH_px) + ' L ' + (indX + indW_px) + ' ' + (groundY - brickH_px) + ' L ' + indX + ' ' + (groundY - brickH_px) + ' Z';
    svg.push('<path d="' + roofPath + '" fill="#eff6ff" stroke="#1e3a8a" stroke-width="3"/>');

    // Roof Trusses & Sheets Profile
    svg.push('<line x1="' + indX + '" y1="' + (groundY - eaveH_px) + '" x2="' + apexX + '" y2="' + (groundY - apexH_px) + '" stroke="#0f172a" stroke-width="5"/>');
    svg.push('<line x1="' + apexX + '" y1="' + (groundY - apexH_px) + '" x2="' + (indX + indW_px) + '" y2="' + (groundY - eaveH_px) + '" stroke="#0f172a" stroke-width="5"/>');

    // Turbo Ventilators on Ridge
    for (var tv = 1; tv <= 6; tv++) {
      var tx = indX + (tv * (indW_px / 7));
      svg.push('<rect x="' + (tx - 5) + '" y="' + (groundY - apexH_px - 14) + '" width="10" height="14" fill="#94a3b8" stroke="#334155" stroke-width="1.5"/>');
      svg.push('<circle cx="' + tx + '" cy="' + (groundY - apexH_px - 14) + '" r="7" fill="#cbd5e1" stroke="#334155" stroke-width="1.5"/>');
    }

    svg.push('<rect x="' + (indX + 15) + '" y="' + (groundY - eaveH_px + 20) + '" width="' + (indW_px - 30) + '" height="44" rx="6" fill="#1e3a8a" stroke="#38bdf8" stroke-width="2"/>');
    svg.push('<text x="' + (indX + indW_px / 2) + '" y="' + (groundY - eaveH_px + 40) + '" text-anchor="middle" font-size="11.5" font-weight="900" fill="#ffffff">🏠 4.5 FT BRICK BASE + 0.50mm PPGL GI WALL CLADDING (TO 35 FT) &amp; INSULATED ROOF</text>');
    svg.push('<text x="' + (indX + indW_px / 2) + '" y="' + (groundY - eaveH_px + 56) + '" text-anchor="middle" font-size="10" font-weight="bold" fill="#93c5fd">4 BWF BADMINTON COURTS + 2 PICKLEBALL COURTS • 100% ALL-WEATHER ENCLOSED</text>');

    // Dimension lines
    var dimY = groundY + 40;
    svg.push('<line x1="' + cx + '" y1="' + dimY + '" x2="' + (cx + parkW_px) + '" y2="' + dimY + '" stroke="#64748b" stroke-width="1.5" marker-start="url(#sideArr)" marker-end="url(#sideArr)"/>');
    svg.push('<text x="' + (cx + parkW_px / 2) + '" y="' + (dimY + 16) + '" text-anchor="middle" font-size="10.5" font-weight="bold" fill="#64748b">PARKING: 18 FT</text>');

    svg.push('<line x1="' + cricX + '" y1="' + dimY + '" x2="' + (cricX + cricW_px) + '" y2="' + dimY + '" stroke="#16a34a" stroke-width="1.5" marker-start="url(#sideArr)" marker-end="url(#sideArr)"/>');
    svg.push('<text x="' + (cricX + cricW_px / 2) + '" y="' + (dimY + 16) + '" text-anchor="middle" font-size="10.5" font-weight="bold" fill="#16a34a">OUTDOOR CRICKET: 50 FT</text>');

    svg.push('<line x1="' + indX + '" y1="' + dimY + '" x2="' + (indX + indW_px) + '" y2="' + dimY + '" stroke="#1e3a8a" stroke-width="1.5" marker-start="url(#sideArr)" marker-end="url(#sideArr)"/>');
    svg.push('<text x="' + (indX + indW_px / 2) + '" y="' + (dimY + 16) + '" text-anchor="middle" font-size="10.5" font-weight="bold" fill="#1e3a8a">INDOOR PEB HALL: 102 FT (4.5 FT BRICK BASE + 30.5 FT GI WALL CLADDING &amp; ROOF)</text>');

    // Height Dimensions
    var hDimX = cx + drawW + 25;
    svg.push('<line x1="' + hDimX + '" y1="' + groundY + '" x2="' + hDimX + '" y2="' + (groundY - apexH_px) + '" stroke="#1e3a8a" stroke-width="2" marker-start="url(#sideArr)" marker-end="url(#sideArr)"/>');
    svg.push('<text x="' + (hDimX + 15) + '" y="' + (groundY - apexH_px / 2) + '" transform="rotate(90 ' + (hDimX + 15) + ' ' + (groundY - apexH_px / 2) + ')" text-anchor="middle" font-size="11" font-weight="900" fill="#1e3a8a">35 FT APEX HEIGHT</text>');

    svg.push('</svg>');
    container.innerHTML = svg.join('');
  }


  function render2DSideElevation(svgContainerId, config) {
    var container = (typeof svgContainerId === 'string') ? document.getElementById(svgContainerId) : svgContainerId;
    if (!container) return;

    var sport = config.sport_type || 'box_cricket';
    var L_ft = parseFloat(config.length) || 90;
    var W_ft = parseFloat(config.width) || 60;

    if (sport === 'multisport_4in1_100x60' || (config && (config.id === 'EST-202610-026' || config.doc_id === 'EST-202610-026')) || (L_ft === 100 && W_ft === 60 && (sport === 'multisport_4in1' || config.is_multi_4in1))) {
      renderMultiSportArena100x60SideElevation(container, config);
      return;
    }

    if (sport === 'multisport_arena_complex_185x60' || (L_ft === 185 && W_ft === 60)) {
      renderMultiSportArena185x60SideElevation(container, config);
      return;
    }

    var sport = config.sport_type || 'box_cricket';
    var L_ft = parseFloat(config.length) || 90;
    var W_ft = parseFloat(config.width) || 60;
    if (sport === 'multisport_complex_105x170' || (L_ft === 170 && W_ft === 105)) {
      renderMegaSportsComplex105x170SideElevation(container, config);
      return;
    }

    var L_ft = parseFloat(config.length) || 90;
    var W_ft = parseFloat(config.width) || 60;
    var H_ft = parseFloat(config.height) || 25;
    var sport = config.sport_type || 'box_cricket';
    var isCricket = checkIsCricket(sport, config);
    var roofType = config.roof_type || 'light_cable_top';

    var layers = config.layers || {};
    if (typeof layers === 'string') {
      try { layers = JSON.parse(layers); } catch(e) { layers = {}; }
    }
    var showGates = (layers.gates !== false && parseInt(config.gate_count || 1) > 0);

    var svgW = 1600;
    var svgH = 900;
    var groundY = 720;
    var padX = 220;
    var maxDrawW = svgW - (padX * 2);
    var scale = Math.min(maxDrawW / L_ft, 420 / H_ft);

    var courtW = L_ft * scale;
    var courtH = H_ft * scale;
    var cx = (svgW - courtW) / 2;

    var isTrainingNet = (sport === 'training_net' || sport.indexOf('cricket_training') !== -1 || sport.indexOf('training_net') !== -1 || (config.is_training_net === true) || (config.shape === 'training_net') || (config.is_front_open === true) || (config.open_front === true) || (config.open_sides && config.open_sides.front === true) || roofType === 'training_net_open_front');

    var svg = [];
    svg.push('<svg viewBox="0 0 ' + svgW + ' ' + svgH + '" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;background:#ffffff;font-family:Inter,Segoe UI,sans-serif;">');
    svg.push('<defs><marker id="arrowSide" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M 0 1.5 L 10 5 L 0 8.5 z" fill="#0f172a"/></marker></defs>');

    // Ground Line & Civil Bed
    svg.push('<line x1="100" y1="' + groundY + '" x2="1500" y2="' + groundY + '" stroke="#0f172a" stroke-width="4"/>');
    svg.push('<rect x="' + cx + '" y="' + groundY + '" width="' + courtW + '" height="25" fill="#e2e8f0" stroke="#94a3b8" stroke-width="1.5"/>');
    svg.push('<text x="' + (svgW / 2) + '" y="' + (groundY + 18) + '" text-anchor="middle" font-size="11" font-weight="bold" fill="#475569">ENGINEERED CIVIL SUB-BASE &amp; DRAINAGE</text>');

    // Cricket Pitch Profile on Ground
    if (isCricket) {
      var pitchL_ft = parseFloat(config.pitch_length) || 66;
      var pW_px = Math.min(pitchL_ft * scale, courtW * 0.95);
      var pxX = (svgW - pW_px) / 2;
      svg.push('<rect x="' + pxX + '" y="' + (groundY - 6) + '" width="' + pW_px + '" height="6" fill="#fef08a" stroke="#ca8a04" stroke-width="1.5"/>');
      var stumpH = 28 * scale / 12;
      svg.push('<line x1="' + (pxX + 16) + '" y1="' + (groundY - 6) + '" x2="' + (pxX + 16) + '" y2="' + (groundY - 6 - stumpH) + '" stroke="#ca8a04" stroke-width="3"/>');
      svg.push('<line x1="' + (pxX + pW_px - 16) + '" y1="' + (groundY - 6) + '" x2="' + (pxX + pW_px - 16) + '" y2="' + (groundY - 6 - stumpH) + '" stroke="#ca8a04" stroke-width="3"/>');
      svg.push('<text x="' + (svgW / 2) + '" y="' + (groundY - 12) + '" text-anchor="middle" font-size="11" font-weight="bold" fill="#713f12">CRICKET PITCH (' + pitchL_ft + ' FT STRIP)</text>');
    }

    var showStructure = (layers.structure !== false);
    if (showStructure) {
      // Columns along length
      var lenSpans = getIntermediateSpans(L_ft, 15, 12);
      var colXs = [cx];
      for (var i = 0; i < lenSpans.positions.length; i++) {
        colXs.push(cx + (lenSpans.positions[i] * scale));
      }
      colXs.push(cx + courtW);

      var poleStyleSide = config.pole_style || (config.pole_profile && (config.pole_profile.type || config.pole_profile.pole_type)) || 'ladder_2line';
      var isLadderSide = (poleStyleSide === 'ladder_2line' || poleStyleSide === 's3_ladder_truss' || (typeof poleStyleSide === 'string' && poleStyleSide.indexOf('ladder') !== -1));

      for (var ci = 0; ci < colXs.length; ci++) {
        var colX = colXs[ci];
        if (isLadderSide) {
          var ladW_px = Math.max(8, 1.2 * scale);
          var halfLad = ladW_px / 2;
          svg.push('<line x1="' + (colX - halfLad) + '" y1="' + groundY + '" x2="' + (colX - halfLad) + '" y2="' + (groundY - courtH) + '" stroke="#1e293b" stroke-width="2.5"/>');
          svg.push('<line x1="' + (colX + halfLad) + '" y1="' + groundY + '" x2="' + (colX + halfLad) + '" y2="' + (groundY - courtH) + '" stroke="#1e293b" stroke-width="2.5"/>');
          for (var rFt = 1.5; rFt < H_ft; rFt += 1.5) {
            var ry_px = groundY - (rFt * scale);
            svg.push('<line x1="' + (colX - halfLad) + '" y1="' + ry_px + '" x2="' + (colX + halfLad) + '" y2="' + ry_px + '" stroke="#64748b" stroke-width="1.2"/>');
          }
        } else {
          svg.push('<line x1="' + colX + '" y1="' + groundY + '" x2="' + colX + '" y2="' + (groundY - courtH) + '" stroke="#1e293b" stroke-width="4"/>');
        }
        svg.push('<rect x="' + (colX - 12) + '" y="' + groundY + '" width="24" height="28" fill="#cbd5e1" stroke="#475569" stroke-width="1.5"/>');
      }
    }

    // Resolve Gates on Sideline
    var resolvedGatesSide = (showGates) ? getResolvedGates(config, L_ft, W_ft, H_ft) : [];
    var sideGates = resolvedGatesSide.filter(function(g) { return g.wall === "right_length" || g.wall === "left_length"; });

    // Horizontal Tiers Segmented
    var tiersFt = getTurnkeyTierHeights(H_ft, config);
    if (showStructure) {
    for (var t = 0; t < tiersFt.length; t++) {
      var tierY = groundY - (tiersFt[t] * scale);
      var tierFt = tiersFt[t];

      var wallGates = sideGates.filter(function(g) { return tierFt <= g.height_ft; });
      if (wallGates.length === 0) {
        svg.push('<line x1="' + cx + '" y1="' + tierY + '" x2="' + (cx + courtW) + '" y2="' + tierY + '" stroke="#475569" stroke-width="2.5"/>');
      } else {
        var intervals = [];
        wallGates.forEach(function(g) {
          var halfDoor = (g.width_ft * scale) / 2;
          var gMid = cx + (g.pos_ft * scale);
          intervals.push({ start: Math.max(cx, gMid - halfDoor), end: Math.min(cx + courtW, gMid + halfDoor) });
        });
        intervals.sort(function(a, b) { return a.start - b.start; });

        var curX = cx;
        intervals.forEach(function(iv) {
          if (iv.start > curX + 2) {
            svg.push('<line x1="' + curX + '" y1="' + tierY + '" x2="' + iv.start + '" y2="' + tierY + '" stroke="#475569" stroke-width="2.5"/>');
          }
          curX = Math.max(curX, iv.end);
        });
        if (curX < cx + courtW - 2) {
          svg.push('<line x1="' + curX + '" y1="' + tierY + '" x2="' + (cx + courtW) + '" y2="' + tierY + '" stroke="#475569" stroke-width="2.5"/>');
        }
      }
      svg.push('<text x="' + (cx + courtW + 10) + '" y="' + (tierY + 4) + '" font-size="10.5" font-weight="bold" fill="#64748b">T' + (t + 1) + ' (' + tiersFt[t] + '\')</text>');
    }
    }

    // Render Side Door(s)
    if (sideGates.length > 0) {
      sideGates.forEach(function(g, idx) {
        var gColor = g.color || "#dc2626";
        var gateW_px = g.width_ft * scale;
        var gateH_px = g.height_ft * scale;
        var gx = cx + (g.pos_ft * scale) - (gateW_px / 2);

        svg.push('<rect x="' + gx + '" y="' + (groundY - gateH_px) + '" width="' + gateW_px + '" height="' + gateH_px + '" fill="rgba(254, 226, 226, 0.85)" stroke="' + gColor + '" stroke-width="3.5" rx="3"/>');
        svg.push('<line x1="' + gx + '" y1="' + groundY + '" x2="' + (gx + gateW_px) + '" y2="' + (groundY - gateH_px) + '" stroke="' + gColor + '" stroke-width="1.8" stroke-dasharray="4,3"/>');
        svg.push('<line x1="' + gx + '" y1="' + (groundY - gateH_px) + '" x2="' + (gx + gateW_px) + '" y2="' + groundY + '" stroke="' + gColor + '" stroke-width="1.8" stroke-dasharray="4,3"/>');
        svg.push('<rect x="' + (gx + gateW_px - 7) + '" y="' + (groundY - gateH_px * 0.5 - 6) + '" width="4" height="12" rx="1" fill="#0f172a"/>');
        svg.push('<circle cx="' + (gx + gateW_px - 5) + '" cy="' + (groundY - gateH_px * 0.5) + '" r="3" fill="' + gColor + '"/>');

        var labelW = 160;
        var labelH = 22;
        var badgeY = (idx % 2 === 0) ? (groundY - gateH_px - 44) : (groundY - gateH_px - 70);
        svg.push('<line x1="' + (gx + gateW_px / 2) + '" y1="' + (groundY - gateH_px) + '" x2="' + (gx + gateW_px / 2) + '" y2="' + (badgeY + labelH) + '" stroke="' + gColor + '" stroke-width="1.5"/>');
        svg.push('<rect x="' + (gx + (gateW_px / 2) - (labelW / 2)) + '" y="' + badgeY + '" width="' + labelW + '" height="' + labelH + '" rx="4" fill="' + gColor + '"/>');
        svg.push('<text x="' + (gx + gateW_px / 2) + '" y="' + (badgeY + 15) + '" text-anchor="middle" font-size="10.5" font-weight="bold" fill="#ffffff">🚪 ' + g.name.substring(0, 16) + ' (' + g.width_ft + '×' + g.height_ft + ')</text>');
      });
    }

    // 10 FT Basketball Hoops & Center Net in 2D Side Elevation
    if (sport === 'outdoor_multi_sports_120x60' || sport.indexOf('basketball') !== -1) {
      var rimH_px = 10 * scale; // Exactly 10 FT height above ground
      var rimY = groundY - rimH_px;
      var boardTopY = groundY - (12.5 * scale);
      var boardBotY = groundY - (9.0 * scale);
      var boardH_px = boardBotY - boardTopY;
      var overHang_px = 4.0 * scale; // 1.2m (4 FT) extension

      // Left Basketball Post
      var leftPostX = cx + 8;
      var leftBoomEnd = leftPostX + overHang_px;
      svg.push('<line x1="' + leftPostX + '" y1="' + groundY + '" x2="' + leftPostX + '" y2="' + (groundY - 11.5 * scale) + '" stroke="#ffffff" stroke-width="7"/>');
      svg.push('<line x1="' + leftPostX + '" y1="' + groundY + '" x2="' + leftPostX + '" y2="' + (groundY - 11.5 * scale) + '" stroke="#0f172a" stroke-width="5"/>');
      svg.push('<rect x="' + (leftPostX - 8) + '" y="' + (groundY - 3.8 * scale) + '" width="16" height="' + (3.8 * scale) + '" fill="#dc2626" stroke="#991b1b" stroke-width="1.5" rx="2"/>');
      svg.push('<line x1="' + leftPostX + '" y1="' + (groundY - 11.5 * scale) + '" x2="' + leftBoomEnd + '" y2="' + (groundY - 10.5 * scale) + '" stroke="#0f172a" stroke-width="4.5"/>');
      svg.push('<line x1="' + (leftPostX + overHang_px * 0.4) + '" y1="' + (groundY - 11.1 * scale) + '" x2="' + leftBoomEnd + '" y2="' + boardTopY + '" stroke="#64748b" stroke-width="2"/>');
      svg.push('<line x1="' + (leftPostX + overHang_px * 0.4) + '" y1="' + (groundY - 11.1 * scale) + '" x2="' + leftBoomEnd + '" y2="' + boardBotY + '" stroke="#64748b" stroke-width="2"/>');
      svg.push('<rect x="' + leftBoomEnd + '" y="' + boardTopY + '" width="4" height="' + boardH_px + '" fill="#ffffff" stroke="#0284c7" stroke-width="1.5"/>');
      svg.push('<line x1="' + (leftBoomEnd + 4) + '" y1="' + rimY + '" x2="' + (leftBoomEnd + 1.5 * scale) + '" y2="' + rimY + '" stroke="#ea580c" stroke-width="4"/>');
      svg.push('<polygon points="' + (leftBoomEnd + 4) + ',' + rimY + ' ' + (leftBoomEnd + 1.5 * scale) + ',' + rimY + ' ' + (leftBoomEnd + 1.2 * scale) + ',' + (rimY + 1.2 * scale) + ' ' + (leftBoomEnd + 0.7 * scale) + ',' + (rimY + 1.2 * scale) + '" fill="rgba(255,255,255,0.7)" stroke="#cbd5e1" stroke-width="1"/>');
      
      // Dimension Marker for 10 FT Height
      var dimHoopX = leftPostX + overHang_px + 32;
      svg.push('<line x1="' + dimHoopX + '" y1="' + rimY + '" x2="' + dimHoopX + '" y2="' + groundY + '" stroke="#ea580c" stroke-width="2" marker-start="url(#arrowSide)" marker-end="url(#arrowSide)"/>');
      svg.push('<rect x="' + (dimHoopX + 8) + '" y="' + (rimY + (groundY - rimY)/2 - 12) + '" width="180" height="24" rx="4" fill="#ea580c"/>');
      svg.push('<text x="' + (dimHoopX + 98) + '" y="' + (rimY + (groundY - rimY)/2 + 5) + '" text-anchor="middle" font-size="10.5" font-weight="bold" fill="#ffffff">★ 10 FT (3.05M) RIM HEIGHT</text>');

      // Right Basketball Post
      var rightPostX = cx + courtW - 8;
      var rightBoomEnd = rightPostX - overHang_px;
      svg.push('<line x1="' + rightPostX + '" y1="' + groundY + '" x2="' + rightPostX + '" y2="' + (groundY - 11.5 * scale) + '" stroke="#ffffff" stroke-width="7"/>');
      svg.push('<line x1="' + rightPostX + '" y1="' + groundY + '" x2="' + rightPostX + '" y2="' + (groundY - 11.5 * scale) + '" stroke="#0f172a" stroke-width="5"/>');
      svg.push('<rect x="' + (rightPostX - 8) + '" y="' + (groundY - 3.8 * scale) + '" width="16" height="' + (3.8 * scale) + '" fill="#dc2626" stroke="#991b1b" stroke-width="1.5" rx="2"/>');
      svg.push('<line x1="' + rightPostX + '" y1="' + (groundY - 11.5 * scale) + '" x2="' + rightBoomEnd + '" y2="' + (groundY - 10.5 * scale) + '" stroke="#0f172a" stroke-width="4.5"/>');
      svg.push('<line x1="' + (rightPostX - overHang_px * 0.4) + '" y1="' + (groundY - 11.1 * scale) + '" x2="' + rightBoomEnd + '" y2="' + boardTopY + '" stroke="#64748b" stroke-width="2"/>');
      svg.push('<line x1="' + (rightPostX - overHang_px * 0.4) + '" y1="' + (groundY - 11.1 * scale) + '" x2="' + rightBoomEnd + '" y2="' + boardBotY + '" stroke="#64748b" stroke-width="2"/>');
      svg.push('<rect x="' + (rightBoomEnd - 4) + '" y="' + boardTopY + '" width="4" height="' + boardH_px + '" fill="#ffffff" stroke="#0284c7" stroke-width="1.5"/>');
      svg.push('<line x1="' + (rightBoomEnd - 4) + '" y1="' + rimY + '" x2="' + (rightBoomEnd - 1.5 * scale) + '" y2="' + rimY + '" stroke="#ea580c" stroke-width="4"/>');
      svg.push('<polygon points="' + (rightBoomEnd - 4) + ',' + rimY + ' ' + (rightBoomEnd - 1.5 * scale) + ',' + rimY + ' ' + (rightBoomEnd - 1.2 * scale) + ',' + (rimY + 1.2 * scale) + ' ' + (rightBoomEnd - 0.7 * scale) + ',' + (rimY + 1.2 * scale) + '" fill="rgba(255,255,255,0.7)" stroke="#cbd5e1" stroke-width="1"/>');

      // Center Badminton / Tennis Tournament Net
      var netMidX = svgW / 2;
      var netH_px = 3.5 * scale;
      svg.push('<line x1="' + netMidX + '" y1="' + groundY + '" x2="' + netMidX + '" y2="' + (groundY - netH_px) + '" stroke="#dc2626" stroke-width="5"/>');
      svg.push('<rect x="' + (netMidX - 6) + '" y="' + (groundY - 16) + '" width="12" height="16" fill="#dc2626" rx="2"/>');
      svg.push('<rect x="' + (netMidX - 2) + '" y="' + (groundY - netH_px) + '" width="4" height="' + netH_px + '" fill="rgba(15,23,42,0.7)"/>');
      svg.push('<text x="' + netMidX + '" y="' + (groundY - netH_px - 8) + '" text-anchor="middle" font-size="10.5" font-weight="bold" fill="#dc2626">CENTER TOURNAMENT NET</text>');
    }

    // Dimensions
    if (showStructure) {
      var dimX = cx + courtW + 35;
      svg.push('<line x1="' + dimX + '" y1="' + (groundY - courtH) + '" x2="' + dimX + '" y2="' + groundY + '" stroke="#0f172a" stroke-width="2" marker-start="url(#arrowSide)" marker-end="url(#arrowSide)"/>');
      svg.push('<text x="' + (dimX + 22) + '" y="' + (groundY - courtH / 2) + '" transform="rotate(90 ' + (dimX + 22) + ' ' + (groundY - courtH / 2) + ')" text-anchor="middle" font-size="16" font-weight="bold" fill="#0f172a">CLEAR HEIGHT: ' + H_ft + ' FT-0"</text>');
    }

    var botDimY = groundY + 55;
    svg.push('<line x1="' + cx + '" y1="' + botDimY + '" x2="' + (cx + courtW) + '" y2="' + botDimY + '" stroke="#0f172a" stroke-width="2" marker-start="url(#arrowSide)" marker-end="url(#arrowSide)"/>');
    svg.push('<text x="' + (svgW / 2) + '" y="' + (botDimY + 22) + '" text-anchor="middle" font-size="16" font-weight="bold" fill="#0f172a">TOTAL LENGTH: ' + L_ft + ' FT-0"</text>');

    svg.push('</svg>');
    container.innerHTML = svg.join('');
  }

  // ==========================================
  // 2D Cross Side Elevation SVG Generator
  // ==========================================
    // =========================================================================
  // DEDICATED 2D TRANSVERSE CROSS ELEVATION: 105 FT × 170 FT INDOOR COMPLEX
  // 105 FT SPAN × 35 FT EAVE HEIGHT × 38.5 FT APEX RIDGE HEIGHT
  // SHOWS 0.50mm PPGL GI PROFILE WALL CLADDING (35 FT HEIGHT) & ROOF
  // =========================================================================
  function renderMegaSportsComplex105x170CrossElevation(container, config) {
    if (!container) return;
    var svgW = 1600;
    var svgH = 900;
    var scale = 10.5; // px per foot (105 FT * 10.5 = 1102.5 px)
    var plotW_px = 105 * scale; // 1102.5 px
    var eaveH_px = 35 * scale;  // 367.5 px
    var apexH_px = 38.5 * scale;// 404.25 px
    var cx = (svgW - plotW_px) / 2; // 248.75 px
    var groundY = 720;
    var eaveY = groundY - eaveH_px;
    var apexY = groundY - apexH_px;
    var apexX = cx + (plotW_px / 2);

    var svg = [];
    svg.push('<svg viewBox="0 0 ' + svgW + ' ' + svgH + '" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;background:#ffffff;font-family:Inter,Segoe UI,sans-serif;" preserveAspectRatio="xMidYMid meet">');
    svg.push('<defs><marker id="crossArr105" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M 0 1.5 L 10 5 L 0 8.5 z" fill="#0f172a"/></marker></defs>');

    // Title Sheet Header Bar
    svg.push('<rect x="50" y="30" width="' + (svgW - 100) + '" height="52" rx="6" fill="#1e3a8a" stroke="#3b82f6" stroke-width="2"/>');
    svg.push('<text x="' + (svgW / 2) + '" y="' + 58 + '" text-anchor="middle" font-size="16" font-weight="900" fill="#ffffff" letter-spacing="0.5">TRANSVERSE CROSS SECTION: 105 FT INDOOR SPORTS COMPLEX (35 FT EAVE / 38.5 FT APEX)</text>');
    svg.push('<text x="' + (svgW / 2) + '" y="' + 74 + '" text-anchor="middle" font-size="11" font-weight="bold" fill="#93c5fd">FULL-HEIGHT 35 FT 0.50mm PPGL GI WALL CLADDING • 0.50mm PPGI ROOF + 50mm THERMAL INSULATION • 4 BADMINTON + 2 PICKLEBALL</text>');

    // Ground line & Civil Bed
    svg.push('<line x1="120" y1="' + groundY + '" x2="' + (svgW - 120) + '" y2="' + groundY + '" stroke="#0f172a" stroke-width="4"/>');
    svg.push('<rect x="' + cx + '" y="' + groundY + '" width="' + plotW_px + '" height="24" fill="#cbd5e1" stroke="#475569" stroke-width="1.5"/>');
    svg.push('<text x="' + (cx + plotW_px / 2) + '" y="' + (groundY + 16) + '" text-anchor="middle" font-size="10" font-weight="bold" fill="#334155">100mm PCC M15 CIVIL BED / PCC SUB-BASE (105 FT WIDTH)</text>');

    // Solid 4.5 FT Base Brickwork Perimeter Wall
    var brickH_px = 4.5 * scale;
    var brickY = groundY - brickH_px;
    svg.push('<rect x="' + (cx - 14) + '" y="' + brickY + '" width="28" height="' + brickH_px + '" fill="#cbd5e1" stroke="#475569" stroke-width="2"/>');
    svg.push('<rect x="' + (cx + plotW_px - 14) + '" y="' + brickY + '" width="28" height="' + brickH_px + '" fill="#cbd5e1" stroke="#475569" stroke-width="2"/>');

    // 0.50mm PPGL Profile Metal Wall Cladding (From 4.5 FT Brick Top to 35 FT Eave)
    var wallCladH_px = eaveH_px - brickH_px;
    svg.push('<rect x="' + (cx - 14) + '" y="' + eaveY + '" width="28" height="' + wallCladH_px + '" fill="#334155" stroke="#1e293b" stroke-width="2"/>');
    svg.push('<rect x="' + (cx + plotW_px - 14) + '" y="' + eaveY + '" width="28" height="' + wallCladH_px + '" fill="#334155" stroke="#1e293b" stroke-width="2"/>');

    // Corrugated vertical flute profile lines on side wall cladding
    for (var fl = cx - 12; fl <= cx + 12; fl += 6) {
      svg.push('<line x1="' + fl + '" y1="' + eaveY + '" x2="' + fl + '" y2="' + brickY + '" stroke="#94a3b8" stroke-width="1"/>');
    }
    for (var fr = cx + plotW_px - 12; fr <= cx + plotW_px + 12; fr += 6) {
      svg.push('<line x1="' + fr + '" y1="' + eaveY + '" x2="' + fr + '" y2="' + brickY + '" stroke="#94a3b8" stroke-width="1"/>');
    }

    // Callout badge for GI Wall Cladding & 4.5 FT Brick Base
    svg.push('<rect x="18" y="' + (groundY - 265) + '" width="165" height="42" rx="4" fill="#0f172a" stroke="#38bdf8" stroke-width="1.5"/>');
    svg.push('<text x="100" y="' + (groundY - 248) + '" text-anchor="middle" font-size="9" font-weight="900" fill="#38bdf8">0.50mm PPGL GI SHEETS</text>');
    svg.push('<text x="100" y="' + (groundY - 234) + '" text-anchor="middle" font-size="8" fill="#ffffff">CLADDING: 4.5 FT TO 35 FT</text>');
    svg.push('<line x1="183" y1="' + (groundY - 244) + '" x2="' + (cx - 14) + '" y2="' + (eaveY + wallCladH_px / 2) + '" stroke="#38bdf8" stroke-width="1.5" stroke-dasharray="3,3"/>');

    // Callout badge for 4.5 FT Solid Brick Base Wall
    svg.push('<rect x="18" y="' + (groundY - 140) + '" width="165" height="38" rx="4" fill="#0f172a" stroke="#94a3b8" stroke-width="1.5"/>');
    svg.push('<text x="100" y="' + (groundY - 124) + '" text-anchor="middle" font-size="9" font-weight="900" fill="#f8fafc">4.5 FT SOLID BRICK WALL</text>');
    svg.push('<text x="100" y="' + (groundY - 110) + '" text-anchor="middle" font-size="8" fill="#94a3b8">PLINTH PERIMETER DADO</text>');
    svg.push('<line x1="183" y1="' + (groundY - 121) + '" x2="' + (cx - 14) + '" y2="' + (groundY - brickH_px / 2) + '" stroke="#94a3b8" stroke-width="1.5" stroke-dasharray="3,3"/>');

    // PEB Structural Columns across width (7 bays @ 15 FT)
    var colXs = [];
    for (var colI = 0; colI <= 7; colI++) {
      colXs.push(cx + (colI * 15 * scale));
    }
    colXs.forEach(function(colX, idx) {
      var isEdge = (idx === 0 || idx === colXs.length - 1);
      svg.push('<line x1="' + colX + '" y1="' + groundY + '" x2="' + colX + '" y2="' + eaveY + '" stroke="#0f172a" stroke-width="' + (isEdge ? '6' : '3.5') + '"/>');
      svg.push('<rect x="' + (colX - 8) + '" y="' + (groundY - 14) + '" width="16" height="14" fill="#475569" stroke="#0f172a" stroke-width="1.5"/>');
    });

    // Horizontal Structural Wall Ties / Girts (6 tiers spaced @ 7 FT)
    for (var ti = 1; ti <= 5; ti++) {
      var tieY = groundY - (ti * 7 * scale);
      svg.push('<line x1="' + cx + '" y1="' + tieY + '" x2="' + (cx + plotW_px) + '" y2="' + tieY + '" stroke="#64748b" stroke-width="1.5" stroke-dasharray="4,4"/>');
    }

    // PEB Gabled Roof Truss (Eave to Apex)
    svg.push('<line x1="' + cx + '" y1="' + eaveY + '" x2="' + (cx + plotW_px) + '" y2="' + eaveY + '" stroke="#0f172a" stroke-width="4"/>');
    svg.push('<line x1="' + cx + '" y1="' + eaveY + '" x2="' + apexX + '" y2="' + apexY + '" stroke="#1e3a8a" stroke-width="5"/>');
    svg.push('<line x1="' + (cx + plotW_px) + '" y1="' + eaveY + '" x2="' + apexX + '" y2="' + apexY + '" stroke="#1e3a8a" stroke-width="5"/>');

    // Roof Truss Internal Web Webbing
    for (var wI = 1; wI <= 6; wI++) {
      var nodeX1 = cx + (wI * (plotW_px / 7));
      var nodeYRoof = (nodeX1 <= apexX) ?
        (eaveY - ((apexH_px - eaveH_px) * ((nodeX1 - cx) / (plotW_px / 2)))) :
        (apexY + ((apexH_px - eaveH_px) * ((nodeX1 - apexX) / (plotW_px / 2))));
      svg.push('<line x1="' + nodeX1 + '" y1="' + eaveY + '" x2="' + nodeX1 + '" y2="' + nodeYRoof + '" stroke="#475569" stroke-width="2"/>');
    }

    // 0.50mm PPGI Roof Sheets Covering Profile (Top Layer)
    svg.push('<line x1="' + (cx - 10) + '" y1="' + (eaveY - 6) + '" x2="' + apexX + '" y2="' + (apexY - 6) + '" stroke="#0284c7" stroke-width="4"/>');
    svg.push('<line x1="' + (cx + plotW_px + 10) + '" y1="' + (eaveY - 6) + '" x2="' + apexX + '" y2="' + (apexY - 6) + '" stroke="#0284c7" stroke-width="4"/>');

    // Turbo Ventilators on Ridge Apex
    svg.push('<rect x="' + (apexX - 8) + '" y="' + (apexY - 24) + '" width="16" height="18" fill="#94a3b8" stroke="#334155" stroke-width="1.5"/>');
    svg.push('<circle cx="' + apexX + '" cy="' + (apexY - 24) + '" r="11" fill="#cbd5e1" stroke="#334155" stroke-width="1.5"/>');

    // Interior Court Playing Zones Cross-Section Representation
    // Bay 1: Badminton / Amenities Bay (Width: 44 FT)
    var bay1W_px = 44 * scale;
    var bay1X = cx + (6 * scale);
    svg.push('<rect x="' + bay1X + '" y="' + (groundY - 10) + '" width="' + bay1W_px + '" height="10" fill="#047857" stroke="#065f46" stroke-width="1.5"/>');
    svg.push('<text x="' + (bay1X + bay1W_px / 2) + '" y="' + (groundY - 20) + '" text-anchor="middle" font-size="11" font-weight="900" fill="#047857">BADMINTON BAYS (44 FT / 4.5mm BWF VINYL)</text>');

    // Bay 2: Pickleball / Badminton Bay 2 (Width: 44 FT)
    var bay2W_px = 44 * scale;
    var bay2X = cx + plotW_px - (6 * scale) - bay2W_px;
    svg.push('<rect x="' + bay2X + '" y="' + (groundY - 10) + '" width="' + bay2W_px + '" height="10" fill="#1e40af" stroke="#1e3a8a" stroke-width="1.5"/>');
    svg.push('<text x="' + (bay2X + bay2W_px / 2) + '" y="' + (groundY - 20) + '" text-anchor="middle" font-size="11" font-weight="900" fill="#1e40af">PICKLEBALL BAYS (44 FT / 8-LAYER ACRYLIC)</text>');

    // Dimension Lines
    // 1. Total Width: 105 FT
    var dimBotY = groundY + 45;
    svg.push('<line x1="' + cx + '" y1="' + dimBotY + '" x2="' + (cx + plotW_px) + '" y2="' + dimBotY + '" stroke="#0f172a" stroke-width="2" marker-start="url(#crossArr105)" marker-end="url(#crossArr105)"/>');
    svg.push('<text x="' + (cx + plotW_px / 2) + '" y="' + (dimBotY + 20) + '" text-anchor="middle" font-size="14" font-weight="900" fill="#0f172a">TOTAL SPAN WIDTH: 105 FT-0"</text>');

    // 2. Clear Eave Height: 35 FT (Right Dimension)
    var dimRX = cx + plotW_px + 35;
    svg.push('<line x1="' + dimRX + '" y1="' + eaveY + '" x2="' + dimRX + '" y2="' + groundY + '" stroke="#1e3a8a" stroke-width="2" marker-start="url(#crossArr105)" marker-end="url(#crossArr105)"/>');
    svg.push('<text x="' + (dimRX + 20) + '" y="' + (groundY - eaveH_px / 2) + '" transform="rotate(90 ' + (dimRX + 20) + ' ' + (groundY - eaveH_px / 2) + ')" text-anchor="middle" font-size="13" font-weight="900" fill="#1e3a8a">35 FT CLEAR EAVE HEIGHT</text>');

    // 3. Apex Ridge Height: 38.5 FT (Left Dimension)
    var dimLX = cx - 35;
    svg.push('<line x1="' + dimLX + '" y1="' + apexY + '" x2="' + dimLX + '" y2="' + groundY + '" stroke="#0f172a" stroke-width="2" marker-start="url(#crossArr105)" marker-end="url(#crossArr105)"/>');
    svg.push('<text x="' + (dimLX - 15) + '" y="' + (groundY - apexH_px / 2) + '" transform="rotate(-90 ' + (dimLX - 15) + ' ' + (groundY - apexH_px / 2) + ')" text-anchor="middle" font-size="13" font-weight="900" fill="#0f172a">38.5 FT APEX RIDGE HEIGHT</text>');

    svg.push('</svg>');
    container.innerHTML = svg.join('');
  }

  function render2DCrossElevation(svgContainerId, config) {
    var container = (typeof svgContainerId === 'string') ? document.getElementById(svgContainerId) : svgContainerId;
    if (!container) return;

    var sport = config.sport_type || 'box_cricket';
    var L_ft = parseFloat(config.length) || 90;
    var W_ft = parseFloat(config.width) || 60;

    if (sport === 'multisport_4in1_100x60' || (config && (config.id === 'EST-202610-026' || config.doc_id === 'EST-202610-026')) || (L_ft === 100 && W_ft === 60 && (sport === 'multisport_4in1' || config.is_multi_4in1))) {
      renderMultiSportArena100x60CrossElevation(container, config);
      return;
    }

    if (sport === 'multisport_complex_105x170' || (L_ft === 170 && W_ft === 105)) {
      renderMegaSportsComplex105x170CrossElevation(container, config);
      return;
    }

    if (sport === 'multisport_arena_complex_185x60' || (L_ft === 185 && W_ft === 60)) {
      renderMultiSportArena185x60CrossElevation(container, config);
      return;
    }


    var L_ft = parseFloat(config.length) || 90;
    var W_ft = parseFloat(config.width) || 60;
    var H_ft = parseFloat(config.height) || 25;
    var sport = config.sport_type || 'box_cricket';
    var isCricket = checkIsCricket(sport, config);
    var roofType = config.roof_type || 'light_cable_top';

    var layers = config.layers || {};
    if (typeof layers === 'string') {
      try { layers = JSON.parse(layers); } catch(e) { layers = {}; }
    }
    var showGates = (layers.gates !== false && parseInt(config.gate_count || 1) > 0);

    var svgW = 1600;
    var svgH = 900;
    var groundY = 720;
    var padX = 320;
    var maxDrawW = svgW - (padX * 2);
    var scale = Math.min(maxDrawW / W_ft, 420 / H_ft);

    var courtW = W_ft * scale;
    var courtH = H_ft * scale;
    var cx = (svgW - courtW) / 2;

    var isTrainingNet = (sport === 'training_net' || sport.indexOf('cricket_training') !== -1 || sport.indexOf('training_net') !== -1 || (config.is_training_net === true) || (config.shape === 'training_net') || (config.is_front_open === true) || (config.open_front === true) || (config.open_sides && config.open_sides.front === true) || roofType === 'training_net_open_front');

    var svg = [];
    svg.push('<svg viewBox="0 0 ' + svgW + ' ' + svgH + '" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;background:#ffffff;font-family:Inter,Segoe UI,sans-serif;">');
    svg.push('<defs><marker id="arrowCross" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M 0 1.5 L 10 5 L 0 8.5 z" fill="#0f172a"/></marker></defs>');

    // Ground line
    svg.push('<line x1="200" y1="' + groundY + '" x2="1400" y2="' + groundY + '" stroke="#0f172a" stroke-width="4"/>');
    svg.push('<rect x="' + cx + '" y="' + groundY + '" width="' + courtW + '" height="25" fill="#e2e8f0" stroke="#94a3b8" stroke-width="1.5"/>');

    var showStructure = (layers.structure !== false);
    if (showStructure) {
      // Columns across width
      var widSpans = getIntermediateSpans(W_ft, 15, 12);
      var colXs = [cx];
      if (!isTrainingNet && !config.open_front && !(config.open_sides && config.open_sides.front)) {
        for (var i = 0; i < widSpans.positions.length; i++) {
          colXs.push(cx + (widSpans.positions[i] * scale));
        }
      }
      colXs.push(cx + courtW);

      for (var ci = 0; ci < colXs.length; ci++) {
        var colX = colXs[ci];
        svg.push('<line x1="' + colX + '" y1="' + groundY + '" x2="' + colX + '" y2="' + (groundY - courtH) + '" stroke="#1e293b" stroke-width="4"/>');
        svg.push('<rect x="' + (colX - 12) + '" y="' + groundY + '" width="24" height="28" fill="#cbd5e1" stroke="#475569" stroke-width="1.5"/>');
      }
    }

    // Resolve Gates on Cross Width Wall
    var resolvedGatesCross = (showGates) ? getResolvedGates(config, L_ft, W_ft, H_ft) : [];
    var crossGates = resolvedGatesCross.filter(function(g) { return g.wall === "front_width" || g.wall === "back_width"; });

    // Horizontal Tiers
    if (showStructure) {
      if (isTrainingNet) {
        svg.push('<line x1="' + cx + '" y1="' + (groundY - courtH) + '" x2="' + (cx + courtW) + '" y2="' + (groundY - courtH) + '" stroke="#475569" stroke-width="3"/>');
      } else {
        var tiersCrossFt = getTurnkeyTierHeights(H_ft, config);
        for (var ct = 0; ct < tiersCrossFt.length; ct++) {
          var tyCross = groundY - (tiersCrossFt[ct] * scale);
          var tierFt2 = tiersCrossFt[ct];
          var wallCrossGates = crossGates.filter(function(g) { return tierFt2 <= g.height_ft; });

          if (wallCrossGates.length === 0) {
            svg.push('<line x1="' + cx + '" y1="' + tyCross + '" x2="' + (cx + courtW) + '" y2="' + tyCross + '" stroke="#475569" stroke-width="2.5"/>');
          } else {
            var intervals2 = [];
            wallCrossGates.forEach(function(g) {
              var halfDoor2 = (g.width_ft * scale) / 2;
              var gzMid = cx + (g.pos_ft * scale);
              intervals2.push({ start: Math.max(cx, gzMid - halfDoor2), end: Math.min(cx + courtW, gzMid + halfDoor2) });
            });
            intervals2.sort(function(a, b) { return a.start - b.start; });

            var curX2 = cx;
            intervals2.forEach(function(iv) {
              if (iv.start > curX2 + 2) {
                svg.push('<line x1="' + curX2 + '" y1="' + tyCross + '" x2="' + iv.start + '" y2="' + tyCross + '" stroke="#475569" stroke-width="2.5"/>');
              }
              curX2 = Math.max(curX2, iv.end);
            });
            if (curX2 < cx + courtW - 2) {
              svg.push('<line x1="' + curX2 + '" y1="' + tyCross + '" x2="' + (cx + courtW) + '" y2="' + tyCross + '" stroke="#475569" stroke-width="2.5"/>');
            }
          }
        }
      }
    }

    // Equipment Profile on Cross Section
    if (sport.indexOf("football") !== -1 || sport === "futsal") {
      var gpW = 60 * scale / 5;
      var gpH = 35 * scale / 5;
      svg.push('<rect x="' + (svgW / 2 - gpW / 2) + '" y="' + (groundY - gpH) + '" width="' + gpW + '" height="' + gpH + '" fill="#fee2e2" stroke="#dc2626" stroke-width="3"/>');
      svg.push('<text x="' + (svgW / 2) + '" y="' + (groundY - gpH - 10) + '" text-anchor="middle" font-size="12" font-weight="bold" fill="#dc2626">REGULATION GOALPOST</text>');
    } else if (isCricket) {
      var pitchW_px = 6.5 * scale;
      svg.push('<rect x="' + (svgW / 2 - pitchW_px / 2) + '" y="' + (groundY - 8) + '" width="' + pitchW_px + '" height="8" fill="#fef08a" stroke="#ca8a04" stroke-width="1.5"/>');
      svg.push('<text x="' + (svgW / 2) + '" y="' + (groundY - 14) + '" text-anchor="middle" font-size="12" font-weight="bold" fill="#713f12">CRICKET PITCH (6.5 FT WIDTH)</text>');
    } else if (sport === 'outdoor_multi_sports_120x60' || sport.indexOf('basketball') !== -1) {
      var midX = svgW / 2;
      var rimH_px = 10 * scale; // Exactly 10 FT rim height
      var rimY = groundY - rimH_px;
      var boardW_px = 6.0 * scale; // 180cm (6 FT)
      var boardH_px = 3.5 * scale; // 105cm (3.5 FT)
      var boardTopY = groundY - (12.5 * scale);

      // 6" MS Main Column & Red Base Pad
      svg.push('<line x1="' + midX + '" y1="' + groundY + '" x2="' + midX + '" y2="' + (groundY - 11.5 * scale) + '" stroke="#0f172a" stroke-width="7"/>');
      svg.push('<rect x="' + (midX - 10) + '" y="' + (groundY - 3.8 * scale) + '" width="20" height="' + (3.8 * scale) + '" fill="#dc2626" stroke="#991b1b" stroke-width="1.5" rx="3"/>');

      // 20mm Imported Acrylic Backboard (180cm x 105cm)
      svg.push('<rect x="' + (midX - boardW_px / 2) + '" y="' + boardTopY + '" width="' + boardW_px + '" height="' + boardH_px + '" fill="rgba(241, 245, 249, 0.9)" stroke="#0284c7" stroke-width="3" rx="2"/>');
      // Inner Target Box (24" x 18" = 2.0ft x 1.5ft)
      var tW_px = 2.0 * scale;
      var tH_px = 1.5 * scale;
      svg.push('<rect x="' + (midX - tW_px / 2) + '" y="' + (rimY - tH_px) + '" width="' + tW_px + '" height="' + tH_px + '" fill="none" stroke="#ea580c" stroke-width="2.5"/>');

      // Olympian Breakaway Ring at 10 FT
      var ringW_px = 1.5 * scale; // 18 inches
      svg.push('<ellipse cx="' + midX + '" cy="' + rimY + '" rx="' + (ringW_px / 2) + '" ry="4.5" fill="none" stroke="#ea580c" stroke-width="4"/>');
      // All-Weather Net hanging down
      svg.push('<polygon points="' + (midX - ringW_px / 2) + ',' + rimY + ' ' + (midX + ringW_px / 2) + ',' + rimY + ' ' + (midX + ringW_px * 0.3) + ',' + (rimY + 1.2 * scale) + ' ' + (midX - ringW_px * 0.3) + ',' + (rimY + 1.2 * scale) + '" fill="rgba(255,255,255,0.75)" stroke="#94a3b8" stroke-width="1.5"/>');

      // Dimension Line & Label for 10 FT Rim Height
      var dimRimX = midX + (boardW_px / 2) + 38;
      svg.push('<line x1="' + dimRimX + '" y1="' + rimY + '" x2="' + dimRimX + '" y2="' + groundY + '" stroke="#ea580c" stroke-width="2" marker-start="url(#arrowCross)" marker-end="url(#arrowCross)"/>');
      svg.push('<rect x="' + (dimRimX + 8) + '" y="' + (rimY + (groundY - rimY)/2 - 12) + '" width="190" height="24" rx="4" fill="#ea580c"/>');
      svg.push('<text x="' + (dimRimX + 103) + '" y="' + (rimY + (groundY - rimY)/2 + 5) + '" text-anchor="middle" font-size="10.5" font-weight="bold" fill="#ffffff">★ 10 FT (3.05M) REGULATION RIM</text>');
    }

    // Render Gate on Cross Width View
    if (crossGates.length > 0 && !isTrainingNet) {
      crossGates.forEach(function(g) {
        var gColor = g.color || "#dc2626";
        var equipW_px = g.width_ft * scale;
        var equipH_px = g.height_ft * scale;
        var gzMid = cx + (g.pos_ft * scale);
        var g3X = gzMid - (equipW_px / 2);

        svg.push('<rect x="' + g3X + '" y="' + (groundY - equipH_px) + '" width="' + equipW_px + '" height="' + equipH_px + '" fill="rgba(254, 226, 226, 0.85)" stroke="' + gColor + '" stroke-width="3.5" rx="3"/>');
        if (g.width_ft >= 5) {
          svg.push('<line x1="' + (g3X + equipW_px / 2) + '" y1="' + groundY + '" x2="' + (g3X + equipW_px / 2) + '" y2="' + (groundY - equipH_px) + '" stroke="' + gColor + '" stroke-width="2"/>');
        } else {
          svg.push('<line x1="' + g3X + '" y1="' + groundY + '" x2="' + (g3X + equipW_px) + '" y2="' + (groundY - equipH_px) + '" stroke="' + gColor + '" stroke-width="1.8" stroke-dasharray="3,3"/>');
        }
        var labelW3 = 175;
        var labelH3 = 22;
        svg.push('<line x1="' + (g3X + equipW_px / 2) + '" y1="' + (groundY - equipH_px) + '" x2="' + (g3X + equipW_px / 2) + '" y2="' + (groundY - equipH_px - 22) + '" stroke="' + gColor + '" stroke-width="1.5"/>');
        svg.push('<rect x="' + (g3X + (equipW_px / 2) - (labelW3 / 2)) + '" y="' + (groundY - equipH_px - 44) + '" width="' + labelW3 + '" height="' + labelH3 + '" rx="4" fill="' + gColor + '"/>');
        svg.push('<text x="' + (g3X + equipW_px / 2) + '" y="' + (groundY - equipH_px - 29) + '" text-anchor="middle" font-size="10.5" font-weight="bold" fill="#ffffff">🚪 ' + g.name.substring(0, 16) + ' (' + g.width_ft + '×' + g.height_ft + ')</text>');
      });
    }

    // Dimensions
    if (showStructure) {
      var dimX = cx + courtW + 35;
      svg.push('<line x1="' + dimX + '" y1="' + (groundY - courtH) + '" x2="' + dimX + '" y2="' + groundY + '" stroke="#0f172a" stroke-width="2" marker-start="url(#arrowCross)" marker-end="url(#arrowCross)"/>');
      svg.push('<text x="' + (dimX + 22) + '" y="' + (groundY - courtH / 2) + '" transform="rotate(90 ' + (dimX + 22) + ' ' + (groundY - courtH / 2) + ')" text-anchor="middle" font-size="16" font-weight="bold" fill="#0f172a">CLEAR HEIGHT: ' + H_ft + ' FT-0"</text>');
    }

    var botDimY = groundY + 55;
    svg.push('<line x1="' + cx + '" y1="' + botDimY + '" x2="' + (cx + courtW) + '" y2="' + botDimY + '" stroke="#0f172a" stroke-width="2" marker-start="url(#arrowCross)" marker-end="url(#arrowCross)"/>');
    svg.push('<text x="' + (svgW / 2) + '" y="' + (botDimY + 22) + '" text-anchor="middle" font-size="16" font-weight="bold" fill="#0f172a">TOTAL WIDTH: ' + W_ft + ' FT-0"</text>');

    svg.push('</svg>');
    container.innerHTML = svg.join('');
  }

  function manualUpdateSnapshot(imgId, containerId) {
    try {
      var targetImg = document.getElementById(imgId || 'turnkey-3d-print-img');
      var cont = document.getElementById(containerId || 'turnkey3DContainer');
      if (cont && targetImg) {
        var canvas = cont.querySelector('canvas');
        if (canvas) {
          targetImg.src = canvas.toDataURL('image/png');
        }
      }
    } catch(e) {}
  }

  window.TurnkeyViewer = {
    init3D: init3DCourt,
    updateSnapshot: manualUpdateSnapshot,
    renderFloorPlan: render2DFloorPlan,
    renderSideElevation: render2DSideElevation,
    renderCrossElevation: render2DCrossElevation,
    setCameraAngle: function(angle) {
      if (typeof window.TurnkeyViewer._activeSetCameraAngle === 'function') {
        window.TurnkeyViewer._activeSetCameraAngle(angle);
      } else if (typeof window._activeCourtCameraAngle === 'function') {
        window._activeCourtCameraAngle(angle);
      }
    }
  };

})(window);
