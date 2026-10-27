/**
 * NETTINGTEAM — ADVANCED AUTOCAD & AUTODESK REVIT BIM DESIGN STUDIO ENGINE
 * 
 * Provides:
 *  1. Multi-Unit Precision Drafting Engine (FT-IN, FT, M, MM, CM)
 *  2. Dimension Stretch & Grip Editing Engine (L, W, H with real-time HUD)
 *  3. Interactive 3D Raycasting & Direct Click-to-Edit (Hover & Click any element in 3D)
 *  4. On-Canvas Floating CAD Quick-Action Toolcard (Roof, Wall, Floor, Gate, Columns)
 *  5. Autodesk Revit 4-View Tiled Split Screen (Quad View: 3D + Plan + Side + Cross)
 *  6. Revit Parametric Properties Palette & BIM Family Tree (Live specs, steel MT, rates)
 *  7. Fullscreen Professional Autodesk AutoCAD & Revit BIM Studio Workspace
 *  8. Native AutoCAD (.DXF) ASCII R12/2000 Export with standard CAD layers
 */

(function(window) {
  'use strict';

  var GcmCadRevit = {
    currentUnit: 'FT_IN',
    isQuadMode: false,
    inspectorVisible: true,
    selectedBimFamily: 'columns_structure',
    selectedMesh: null,
    highlightHelper: null,
    hoveredMesh: null,
    pointerStartX: 0,
    pointerStartY: 0,
    isFullscreenStudio: false,

    init: function() {
      var self = this;
      this.updateCadHud();
      this.initBimFamilyClickEvents();
      this.init3DRaycaster();

      // Ensure properties panel is visible on load
      var panel = document.getElementById('revitPropertiesPanel');
      if (panel) {
        panel.style.display = 'block';
        this.inspectorVisible = true;
        this.inspectBimFamily('columns_structure');
      }

      // Re-initialize raycaster on viewer reload
      window.addEventListener('resize', function() {
        self.updateCadHud();
      });
    },

    // ──────────────────────────────────────────────────────────────────────────
    // 1. MULTI-UNIT PRECISION DRAFTING ENGINE
    // ──────────────────────────────────────────────────────────────────────────
    convertFtToUnit: function(feetVal) {
      var val = parseFloat(feetVal) || 0;
      switch (this.currentUnit) {
        case 'FT_IN':
          var ft = Math.floor(val);
          var inches = Math.round((val - ft) * 12);
          if (inches === 12) { ft++; inches = 0; }
          return ft + "'-" + (inches < 10 ? '0' : '') + inches + '"';

        case 'FT':
          return val.toFixed(2) + ' FT';

        case 'M':
          return (val * 0.3048).toFixed(2) + ' m';

        case 'MM':
          return Math.round(val * 304.8).toLocaleString('en-IN') + ' mm';

        case 'CM':
          return Math.round(val * 30.48).toLocaleString('en-IN') + ' cm';

        default:
          return val.toFixed(2) + ' FT';
      }
    },

    setUnit: function(newUnit) {
      this.currentUnit = newUnit;

      var unitBtns = document.querySelectorAll('.cad-unit-btn');
      unitBtns.forEach(function(b) {
        if (b.dataset.unit === newUnit) {
          b.className = 'btn btn-xs btn-primary fw-bold py-0 px-2 cad-unit-btn active';
        } else {
          b.className = 'btn btn-xs btn-outline-secondary text-light py-0 px-2 cad-unit-btn';
        }
      });

      this.updateCadHud();

      if (typeof window.refreshLiveCourtViewer === 'function') {
        window.refreshLiveCourtViewer();
      }
      if (this.isQuadMode) {
        this.refreshQuadViews();
      }
      if (typeof window.showToastNotification === 'function') {
        window.showToastNotification('Switched AutoCAD Drafting Units to ' + newUnit.replace('_', '-'));
      }
    },

    updateCadHud: function() {
      var cfg = this.getCurrentConfig();
      var L = cfg.length || 90;
      var W = cfg.width || 60;
      var H = cfg.height || 25;

      var hudCoords = document.getElementById('cadCoordsHud');
      if (hudCoords) {
        hudCoords.innerHTML = '<span class="text-info">X:</span> ' + this.convertFtToUnit(L) + ' &bull; <span class="text-success">Y:</span> ' + this.convertFtToUnit(W) + ' &bull; <span class="text-warning">Z:</span> ' + this.convertFtToUnit(H);
      }

      var hudArea = document.getElementById('cadAreaHud');
      if (hudArea) {
        var sft = L * W;
        var sqm = sft * 0.092903;
        hudArea.innerHTML = '<strong>' + Math.round(sft).toLocaleString('en-IN') + ' SFT</strong> (' + Math.round(sqm) + ' SQM)';
      }
    },

    // ──────────────────────────────────────────────────────────────────────────
    // 2. INTERACTIVE CAD DIMENSION STRETCH ENGINE
    // ──────────────────────────────────────────────────────────────────────────
    adjustDimension: function(dimName, delta) {
      var elemId = (dimName === 'length') ? 'tk_length' : ((dimName === 'width') ? 'tk_width' : 'tk_height');
      var input = document.getElementById(elemId);
      if (!input) return;

      var currentVal = parseFloat(input.value) || 0;
      var minVal = (dimName === 'height') ? 10 : 20;
      var maxVal = (dimName === 'length') ? 300 : ((dimName === 'width') ? 200 : 60);

      var newVal = Math.max(minVal, Math.min(maxVal, currentVal + delta));
      input.value = newVal;

      if (typeof window.setFtValue === 'function') {
        window.setFtValue(elemId, newVal);
      }
      if (typeof window.calcTotals === 'function') {
        window.calcTotals();
      }
      if (typeof window.refreshLiveCourtViewer === 'function') {
        window.refreshLiveCourtViewer();
      }
      if (this.isQuadMode) {
        this.refreshQuadViews();
      }

      this.updateCadHud();
      if (this.selectedBimFamily) {
        this.inspectBimFamily(this.selectedBimFamily);
      }

      if (typeof window.showToastNotification === 'function') {
        var dir = (delta > 0) ? 'Stretched' : 'Shrank';
        window.showToastNotification('AutoCAD Grip: ' + dir + ' ' + dimName + ' to ' + this.convertFtToUnit(newVal));
      }
    },

    // ──────────────────────────────────────────────────────────────────────────
    // 3. THREE.JS RAYCASTER & INTERACTIVE 3D CLICK-TO-EDIT ENGINE
    // ──────────────────────────────────────────────────────────────────────────
    init3DRaycaster: function() {
      var self = this;

      function setupContainerListeners(containerId) {
        var container = document.getElementById(containerId);
        if (!container) return;

        // Create on-screen hover tooltip badge if not exists
        var hoverTip = document.getElementById('cadRaycastHoverTip');
        if (!hoverTip) {
          hoverTip = document.createElement('div');
          hoverTip.id = 'cadRaycastHoverTip';
          hoverTip.style.cssText = 'position:fixed; z-index:999999; display:none; background:rgba(15,23,42,0.92); border:1px solid #0284c7; color:#38bdf8; padding:4px 10px; border-radius:6px; font-size:11px; font-weight:700; pointer-events:none; box-shadow:0 4px 14px rgba(0,0,0,0.4); font-family:Inter, sans-serif;';
          document.body.appendChild(hoverTip);
        }

        // Pointer down to detect click vs drag
        container.addEventListener('pointerdown', function(e) {
          self.pointerStartX = e.clientX;
          self.pointerStartY = e.clientY;
        });

        // Pointer move for hover detection
        container.addEventListener('pointermove', function(e) {
          var hitInfo = self.raycastAtPoint(e.clientX, e.clientY, container);
          if (hitInfo && hitInfo.family) {
            container.style.cursor = 'pointer';
            hoverTip.style.display = 'block';
            hoverTip.style.left = (e.clientX + 14) + 'px';
            hoverTip.style.top = (e.clientY + 14) + 'px';
            hoverTip.innerHTML = '<i class="fa fa-crosshairs text-warning me-1"></i><strong>' + hitInfo.label + '</strong> <span style="color:#94a3b8; font-weight:normal;">(Click to Edit)</span>';
          } else {
            container.style.cursor = 'default';
            hoverTip.style.display = 'none';
          }
        });

        container.addEventListener('pointerleave', function() {
          container.style.cursor = 'default';
          var hoverTip = document.getElementById('cadRaycastHoverTip');
          if (hoverTip) hoverTip.style.display = 'none';
        });

        // Click event: trigger selection and on-screen Quick Action toolcard
        container.addEventListener('click', function(e) {
          var dist = Math.hypot(e.clientX - self.pointerStartX, e.clientY - self.pointerStartY);
          if (dist > 8) return; // Ignore drag/orbit gestures

          var hitInfo = self.raycastAtPoint(e.clientX, e.clientY, container);
          if (hitInfo && hitInfo.family) {
            self.on3DElementSelected(hitInfo, e.clientX, e.clientY, container);
          }
        });
      }

      setupContainerListeners('turnkey3DContainerEdit');
      setupContainerListeners('turnkey3DContainerQuad');
    },

    raycastAtPoint: function(clientX, clientY, container) {
      if (!window.THREE) return null;
      var viewer = (container.id === 'turnkey3DContainerQuad') ? this.quad3DViewer : window.tkViewerInstance;
      if (!viewer || !viewer.scene || !viewer.camera) {
        if (window.tkViewerInstance && window.tkViewerInstance.scene) {
          viewer = window.tkViewerInstance;
        } else {
          return null;
        }
      }

      var rect = container.getBoundingClientRect();
      var mouse = new THREE.Vector2();
      mouse.x = ((clientX - rect.left) / rect.width) * 2 - 1;
      mouse.y = -((clientY - rect.top) / rect.height) * 2 + 1;

      var raycaster = new THREE.Raycaster();
      raycaster.setFromCamera(mouse, viewer.camera);

      var intersects = raycaster.intersectObjects(viewer.scene.children, true);
      if (!intersects || intersects.length === 0) return null;

      for (var i = 0; i < intersects.length; i++) {
        var obj = intersects[i].object;
        if (!obj || !obj.visible) continue;
        if (obj.type === 'AmbientLight' || obj.type === 'DirectionalLight' || obj.type === 'GridHelper') continue;

        var familyInfo = this.classifyMeshToBimFamily(obj, intersects[i].point);
        if (familyInfo) {
          return {
            object: obj,
            point: intersects[i].point,
            family: familyInfo.family,
            label: familyInfo.label,
            category: familyInfo.category
          };
        }
      }
      return null;
    },

    classifyMeshToBimFamily: function(mesh, worldPoint) {
      var cfg = this.getCurrentConfig();
      var H = parseFloat(cfg.height) || 25;
      var H_units = H * 0.1;

      // Check name or parent group
      var cur = mesh;
      while (cur) {
        var name = (cur.name || '').toLowerCase();
        if (name.indexOf('roof') !== -1 || name.indexOf('canopy') !== -1 || name.indexOf('megaroof') !== -1) {
          return { family: 'roof_canopy', label: 'Roof Canopy & Trusses', category: 'Roof Structure' };
        }
        if (name.indexOf('gate') !== -1) {
          return { family: 'gate', label: 'Entrance Access Gate', category: 'Doors & Openings' };
        }
        cur = cur.parent;
      }

      // Check elevation (Y coordinate)
      if (worldPoint && worldPoint.y >= (H_units - 0.35)) {
        return { family: 'roof_canopy', label: 'Roof Canopy & Overhead Netting', category: 'Roof Structure' };
      }

      // Ground / Turf / Pitch
      if (worldPoint && worldPoint.y <= 0.08) {
        if (Math.abs(worldPoint.z) <= 0.45 && Math.abs(worldPoint.x) <= 3.3) {
          return { family: 'cricket_pitch', label: 'Center Cricket Pitch (66×6.5 FT)', category: 'Playing Field' };
        }
        if (cfg.zones && cfg.zones.badminton) {
          return { family: 'badminton_arena', label: 'Indoor Badminton Arena Flooring', category: 'Sports Court' };
        }
        return { family: 'cricket_pitch', label: 'Sports Arena Turf & Sub-base', category: 'Playing Surface' };
      }

      // Wall Cladding / Netting
      if (mesh.geometry && (mesh.geometry.type === 'PlaneGeometry' || mesh.geometry.type === 'BoxGeometry')) {
        return { family: 'wall_cladding', label: 'Wall Enclosure & Cladding', category: 'Perimeter Walls' };
      }

      // Tubular Columns & Frame
      return { family: 'columns_structure', label: 'Structural Steel Columns (YST-310)', category: 'Structural Frame' };
    },

    on3DElementSelected: function(hitInfo, clientX, clientY, container) {
      this.selectedBimFamily = hitInfo.family;
      this.inspectBimFamily(hitInfo.family);

      // Open/Focus Revit Properties Panel
      var panel = document.getElementById('revitPropertiesPanel');
      if (panel) {
        panel.style.display = 'block';
        this.inspectorVisible = true;
      }

      // Show On-Canvas Floating Quick-Action Card
      this.show3dQuickActionCard(hitInfo, clientX, clientY, container);
    },

    show3dQuickActionCard: function(hitInfo, clientX, clientY, container) {
      var card = document.getElementById('cad3dActionCard');
      if (!card) {
        card = document.createElement('div');
        card.id = 'cad3dActionCard';
        document.body.appendChild(card);
      }

      var rect = container.getBoundingClientRect();
      var cardX = Math.min(window.innerWidth - 340, Math.max(rect.left + 10, clientX - 120));
      var cardY = Math.min(window.innerHeight - 240, Math.max(rect.top + 10, clientY - 140));

      card.style.cssText = 'position:fixed; z-index:999999; left:' + cardX + 'px; top:' + cardY + 'px; width:330px; background:#0f172a; border:2px solid #0284c7; border-radius:8px; box-shadow:0 8px 30px rgba(0,0,0,0.6); color:#ffffff; font-family:Inter, sans-serif; overflow:hidden; animation:cadPopIn 0.15s ease-out;';

      var actionsHtml = '';
      switch (hitInfo.family) {
        case 'roof_canopy':
          actionsHtml = 
            '<div class="d-flex flex-column gap-1 mt-2">' +
              '<button type="button" class="btn btn-xs btn-danger fw-bold text-start py-1" onclick="GcmCadRevit.actionRemoveRoof()"><i class="fa fa-trash me-2"></i>Delete / Remove Roof (Make Open-to-Sky)</button>' +
              '<button type="button" class="btn btn-xs btn-warning text-dark fw-bold text-start py-1" onclick="GcmCadRevit.actionSetPPGLRoof()"><i class="fa fa-warehouse me-2"></i>Switch to 0.50mm PPGL Metal Roof</button>' +
              '<button type="button" class="btn btn-xs btn-outline-info text-start py-1" onclick="toggleRoofSheetsVisualizer()"><i class="fa fa-eye me-2"></i>Toggle Look Inside (Hide Sheets)</button>' +
            '</div>';
          break;

        case 'wall_cladding':
          actionsHtml = 
            '<div class="d-flex flex-column gap-1 mt-2">' +
              '<button type="button" class="btn btn-xs btn-warning text-dark fw-bold text-start py-1" onclick="toggleAllSidesGISheets()"><i class="fa fa-sheet-plastic me-2"></i>Wrap All 4 Sides with 0.50mm PPGL Sheets</button>' +
              '<button type="button" class="btn btn-xs btn-success fw-bold text-start py-1" onclick="GcmCadRevit.actionAddGate()"><i class="fa fa-door-open me-2"></i>Place Centered Entrance Gate</button>' +
              '<button type="button" class="btn btn-xs btn-outline-info text-start py-1" onclick="toggleSideNetsComponent()"><i class="fa fa-shield me-2"></i>Toggle Side Netting (ON/OFF)</button>' +
            '</div>';
          break;

        case 'badminton_arena':
        case 'cricket_pitch':
          actionsHtml = 
            '<div class="d-flex flex-column gap-1 mt-2">' +
              '<button type="button" class="btn btn-xs btn-success fw-bold text-start py-1" onclick="toggleAttachBadminton()"><i class="fa fa-table-tennis-paddle-ball me-2"></i>Attach / Detach Badminton Arena</button>' +
              '<button type="button" class="btn btn-xs btn-outline-warning text-start py-1" onclick="setupBoxCricketOnly()"><i class="fa fa-baseball-bat-ball me-2"></i>Configure Standalone Box Cricket Ground</button>' +
              '<button type="button" class="btn btn-xs btn-outline-info text-start py-1" onclick="setupIndoorBadmintonArenaOnly()"><i class="fa fa-building-circle-check me-2"></i>Indoor Badminton Arena Style</button>' +
              '<button type="button" class="btn btn-xs btn-outline-light text-start py-1" onclick="toggleTurfComponent()"><i class="fa fa-seedling me-2"></i>Toggle Synthetic Turf (ON/OFF)</button>' +
            '</div>';
          break;

        case 'gate':
          actionsHtml = 
            '<div class="d-flex flex-column gap-1 mt-2">' +
              '<button type="button" class="btn btn-xs btn-primary fw-bold text-start py-1" onclick="GcmCadRevit.actionAddGate()"><i class="fa fa-align-center me-2"></i>Ensure Centered Main Entrance Gate</button>' +
              '<button type="button" class="btn btn-xs btn-outline-danger text-start py-1" onclick="GcmCadRevit.actionDeleteGate()"><i class="fa fa-trash me-2"></i>Remove Gate from Structure</button>' +
            '</div>';
          break;

        case 'columns_structure':
        default:
          actionsHtml = 
            '<div class="d-flex flex-column gap-1 mt-2">' +
              '<div class="d-flex gap-1">' +
                '<button type="button" class="btn btn-xs btn-outline-light flex-fill py-1" onclick="GcmCadRevit.adjustDimension(\'length\', 5)"><i class="fa fa-plus me-1"></i>Length +5\'</button>' +
                '<button type="button" class="btn btn-xs btn-outline-light flex-fill py-1" onclick="GcmCadRevit.adjustDimension(\'width\', 5)"><i class="fa fa-plus me-1"></i>Width +5\'</button>' +
                '<button type="button" class="btn btn-xs btn-outline-light flex-fill py-1" onclick="GcmCadRevit.adjustDimension(\'height\', 2)"><i class="fa fa-plus me-1"></i>Height +2\'</button>' +
              '</div>' +
              '<button type="button" class="btn btn-xs btn-outline-info text-start py-1 mt-1" onclick="GcmCadRevit.exportAutoCADDXF()"><i class="fa fa-download me-2"></i>Export AutoCAD .DXF of Structural Grid</button>' +
            '</div>';
          break;
      }

      card.innerHTML = 
        '<div style="background:#1e293b; padding:8px 12px; border-bottom:1px solid #334155; display:flex; align-items:center; justify-content:space-between;">' +
          '<div style="font-size:11px; font-weight:800; color:#38bdf8;"><i class="fa fa-cube me-1"></i>' + hitInfo.label + '</div>' +
          '<button type="button" style="background:transparent; border:none; color:#94a3b8; font-size:12px; cursor:pointer;" onclick="document.getElementById(\'cad3dActionCard\').style.display=\'none\';">&times;</button>' +
        '</div>' +
        '<div style="padding:10px 12px; font-size:11px;">' +
          '<div style="color:#94a3b8;">BIM Category: <strong class="text-light">' + hitInfo.category + '</strong></div>' +
          actionsHtml +
          '<div style="margin-top:8px; border-top:1px solid #334155; padding-top:6px; display:flex; justify-content:space-between; align-items:center;">' +
            '<span style="font-size:10px; color:#64748b;">Linked to Live BOQ</span>' +
            '<a href="#revitPropertiesPanel" style="color:#38bdf8; font-size:10px; text-decoration:none;" onclick="document.getElementById(\'cad3dActionCard\').style.display=\'none\';">Full Parametric Specs &darr;</a>' +
          '</div>' +
        '</div>';

      card.style.display = 'block';
    },

    // ──────────────────────────────────────────────────────────────────────────
    // 4. ACTION DISPATCHERS (Bidirectional Associativity)
    // ──────────────────────────────────────────────────────────────────────────
    actionRemoveRoof: function() {
      if (typeof window.turnkeySidesState !== 'undefined') {
        window.turnkeySidesState.top = true; // top open
      }
      var roofSelect = document.getElementById('tk_roof_type');
      if (roofSelect) roofSelect.value = 'light_cable_top';

      var btn = document.getElementById('btnCadTopNet');
      if (btn) btn.className = 'btn btn-xs btn-outline-secondary py-1 px-2';

      if (typeof window.calcTotals === 'function') window.calcTotals();
      if (typeof window.refreshLiveCourtViewer === 'function') window.refreshLiveCourtViewer();
      if (this.isQuadMode) this.refreshQuadViews();

      var card = document.getElementById('cad3dActionCard');
      if (card) card.style.display = 'none';

      if (typeof window.showToastNotification === 'function') {
        window.showToastNotification('Removed Roof Canopy! Facility converted to Open-to-Sky Arena.');
      }
    },

    actionSetPPGLRoof: function() {
      var roofSelect = document.getElementById('tk_roof_type');
      if (roofSelect) roofSelect.value = 'curved_truss';
      if (typeof window.turnkeySidesState !== 'undefined') {
        window.turnkeySidesState.top = false;
      }
      if (typeof window.calcTotals === 'function') window.calcTotals();
      if (typeof window.refreshLiveCourtViewer === 'function') window.refreshLiveCourtViewer();
      if (this.isQuadMode) this.refreshQuadViews();

      var card = document.getElementById('cad3dActionCard');
      if (card) card.style.display = 'none';

      if (typeof window.showToastNotification === 'function') {
        window.showToastNotification('Applied 0.50mm Tata Bluescope Colorbond PPGL Metal Roof Canopy!');
      }
    },

    actionAddGate: function() {
      var gateCount = document.getElementById('tk_gate_count');
      if (gateCount) {
        gateCount.value = 1;
      }
      if (typeof window.turnkeyGatesList !== 'undefined') {
        window.turnkeyGatesList = [{ wall: 'front_width', position: 50, width_ft: 6, height_ft: 7 }];
      }
      if (typeof window.calcTotals === 'function') window.calcTotals();
      if (typeof window.refreshLiveCourtViewer === 'function') window.refreshLiveCourtViewer();
      if (this.isQuadMode) this.refreshQuadViews();

      var card = document.getElementById('cad3dActionCard');
      if (card) card.style.display = 'none';

      if (typeof window.showToastNotification === 'function') {
        window.showToastNotification('Placed Centered Main Entrance Gate (6×7 FT) on Front Elevation!');
      }
    },

    actionDeleteGate: function() {
      var gateCount = document.getElementById('tk_gate_count');
      if (gateCount) gateCount.value = 0;
      if (typeof window.turnkeyGatesList !== 'undefined') {
        window.turnkeyGatesList = [];
      }
      if (typeof window.calcTotals === 'function') window.calcTotals();
      if (typeof window.refreshLiveCourtViewer === 'function') window.refreshLiveCourtViewer();
      if (this.isQuadMode) this.refreshQuadViews();

      var card = document.getElementById('cad3dActionCard');
      if (card) card.style.display = 'none';

      if (typeof window.showToastNotification === 'function') {
        window.showToastNotification('Removed Access Gate from Structure!');
      }
    },

    // ──────────────────────────────────────────────────────────────────────────
    // 5. REVIT 4-VIEW SPLIT SCREEN (QUAD VIEWPORT ENGINE)
    // ──────────────────────────────────────────────────────────────────────────
    toggleQuadView: function() {
      this.isQuadMode = !this.isQuadMode;
      var singleView = document.getElementById('turnkeySingleViewport');
      var quadView = document.getElementById('cadQuadContainer');
      var btn = document.getElementById('btnQuadView');

      if (this.isQuadMode) {
        if (singleView) singleView.style.display = 'none';
        if (quadView) quadView.style.display = 'block';
        if (btn) {
          btn.className = 'btn btn-sm btn-danger active px-3';
          btn.innerHTML = '<i class="fa fa-table-cells me-1"></i>Revit Quad View: ON';
        }
        this.refreshQuadViews();
        if (typeof window.showToastNotification === 'function') {
          window.showToastNotification('Activated Autodesk Revit 4-View Tiled Split Screen (3D + Plan + Elevations)!');
        }
      } else {
        if (quadView) quadView.style.display = 'none';
        if (singleView) singleView.style.display = 'block';
        if (btn) {
          btn.className = 'btn btn-sm btn-outline-info px-3';
          btn.innerHTML = '<i class="fa fa-table-cells me-1"></i>Revit Quad View';
        }
        if (typeof window.switchVisualizerTab === 'function') {
          window.switchVisualizerTab(window.currentVisualizerTab || '3d');
        }
      }
    },

    refreshQuadViews: function() {
      if (!this.isQuadMode) return;
      var config = this.getCurrentConfig();
      if (!window.TurnkeyViewer) return;

      try {
        this.quad3DViewer = window.TurnkeyViewer.init3D('turnkey3DContainerQuad', config);
        window.TurnkeyViewer.renderFloorPlan('turnkeyFloorPlanSvgQuad', config);
        window.TurnkeyViewer.renderSideElevation('turnkeySideElevationSvgQuad', config);
        window.TurnkeyViewer.renderCrossElevation('turnkeyCrossElevationSvgQuad', config);
      } catch (e) {
        console.warn('Revit Quad views refresh notice:', e);
      }
    },

    getCurrentConfig: function() {
      if (window.turnkeyViewConfig) return window.turnkeyViewConfig;
      if (typeof window.tkCfg !== 'undefined' && window.tkCfg) return window.tkCfg;

      var L = parseFloat(document.getElementById('tk_length')?.value) || 90;
      var W = parseFloat(document.getElementById('tk_width')?.value) || 60;
      var H = parseFloat(document.getElementById('tk_height')?.value) || 25;
      var sport = document.getElementById('tk_sport_type')?.value || 'box_cricket';
      var roof = document.getElementById('tk_roof_type')?.value || 'light_cable_top';
      var pole = document.getElementById('tk_pole_style')?.value || 'ladder_2line';
      var pitch = parseFloat(document.getElementById('tk_pitch_length')?.value) || 0;
      var gates = parseInt(document.getElementById('tk_gate_count')?.value) || 1;

      return {
        length: L,
        width: W,
        height: H,
        sport_type: sport,
        roof_type: roof,
        pole_style: pole,
        pitch_length: pitch,
        gate_count: gates,
        gates: (typeof window.turnkeyGatesList !== 'undefined') ? window.turnkeyGatesList : null,
        open_sides: window.turnkeySidesState || {},
        layers: window.turnkeyLayersState || {},
        has_all_sides_gi_sheets: !!window.hasAllSidesGISheets,
        wall_cladding: window.hasAllSidesGISheets ? 'all_sides_gi' : 'netting'
      };
    },

    // ──────────────────────────────────────────────────────────────────────────
    // 6. REVIT PARAMETRIC PROPERTIES PALETTE & BIM INSPECTOR
    // ──────────────────────────────────────────────────────────────────────────
    toggleInspector: function() {
      this.inspectorVisible = !this.inspectorVisible;
      var panel = document.getElementById('revitPropertiesPanel');
      var btn = document.getElementById('btnBimInspector');
      if (panel) {
        panel.style.display = this.inspectorVisible ? 'block' : 'none';
      }
      if (btn) {
        if (this.inspectorVisible) {
          btn.className = 'btn btn-sm btn-warning text-dark active px-3';
          btn.innerHTML = '<i class="fa fa-sliders me-1"></i>Revit BIM: OPEN';
        } else {
          btn.className = 'btn btn-sm btn-outline-warning px-3';
          btn.innerHTML = '<i class="fa fa-sliders me-1"></i>Revit BIM Inspector';
        }
      }
      if (this.inspectorVisible && this.selectedBimFamily) {
        this.inspectBimFamily(this.selectedBimFamily);
      }
    },

    initBimFamilyClickEvents: function() {
      var self = this;
      document.querySelectorAll('.bim-family-item').forEach(function(item) {
        item.addEventListener('click', function() {
          var family = item.dataset.family;
          if (family) self.inspectBimFamily(family);
        });
      });
    },

    inspectBimFamily: function(familyName) {
      this.selectedBimFamily = familyName;

      var cfg = this.getCurrentConfig();
      var L = cfg.length || 90;
      var W = cfg.width || 60;
      var H = cfg.height || 25;
      var sport = cfg.sport_type || 'box_cricket';

      var titleEl = document.getElementById('revitPropTitle');
      var catEl   = document.getElementById('revitPropCategory');
      var specEl  = document.getElementById('revitPropSpec');
      var dimEl   = document.getElementById('revitPropDimensions');
      var areaEl  = document.getElementById('revitPropArea');
      var steelEl = document.getElementById('revitPropSteelWeight');
      var costEl  = document.getElementById('revitPropCost');

      document.querySelectorAll('.bim-family-item').forEach(function(item) {
        if (item.dataset.family === familyName) {
          item.style.background = '#1e293b';
          item.style.borderColor = '#0284c7';
        } else {
          item.style.background = 'transparent';
          item.style.borderColor = 'transparent';
        }
      });

      switch (familyName) {
        case 'wall_cladding':
          if (titleEl) titleEl.textContent = '0.50mm Tata Bluescope Colorbond PPGL Wall Cladding';
          if (catEl)   catEl.textContent = 'BIM Category: Basic Wall & Profile Sheets';
          if (specEl)  specEl.textContent = '0.50mm TCT Pre-Painted Galvalume (AZ-150 / 550 MPa) Steel Profile Sheets';
          var wallArea = 2 * (L + W) * H;
          if (dimEl)   dimEl.textContent = 'Perimeter: ' + (2 * (L + W)) + ' RFT | Clear Eave Height: ' + H + ' FT';
          if (areaEl)  areaEl.textContent = Math.round(wallArea).toLocaleString('en-IN') + ' SQFT Total Cladding Surface Area';
          if (steelEl) steelEl.textContent = (wallArea * 0.0051).toFixed(2) + ' MT (Sheet + Purlin Support)';
          if (costEl)  costEl.textContent = 'Unit Rate: ₹110.00 / SFT • Total: ₹' + Math.round(wallArea * 110).toLocaleString('en-IN');
          break;

        case 'roof_canopy':
          if (titleEl) titleEl.textContent = 'Curved Gable Pre-Engineered Tubular Steel Roof Truss';
          if (catEl)   catEl.textContent = 'BIM Category: Roof Structure & Trusses';
          if (specEl)  specEl.textContent = '0.50mm PPGL Trapezoidal Sheets on Curved Tubular Trusses + Polycarbonate Skylights';
          var roofArea = L * W * 1.08;
          if (dimEl)   dimEl.textContent = 'Span: ' + W + ' FT | Length: ' + L + ' FT | Apex Pitch: 1:7 Gradient';
          if (areaEl)  areaEl.textContent = Math.round(roofArea).toLocaleString('en-IN') + ' SQFT Developed Surface Area';
          if (steelEl) steelEl.textContent = (roofArea * 0.0078).toFixed(2) + ' MT (Tubular Trusses + Purlins)';
          if (costEl)  costEl.textContent = 'Unit Rate: ₹240.00 / SFT • Total: ₹' + Math.round(roofArea * 240).toLocaleString('en-IN');
          break;

        case 'badminton_arena':
          if (titleEl) titleEl.textContent = 'Indoor Badminton Arena Complex (2 BWF Courts)';
          if (catEl)   catEl.textContent = 'BIM Category: Specialized Sports Facility';
          if (specEl)  specEl.textContent = '4.5mm BWF Approved Anti-Slip Vinyl Sports Flooring on Engineered Moisture-Barrier Sub-base';
          if (dimEl)   dimEl.textContent = 'Court Dimensions: 44.0 FT × 20.0 FT | Clear Height: 30.0 FT';
          if (areaEl)  areaEl.textContent = '1,760 SQFT Court Playing Area (Plus 2,000 SQFT Runoff & Viewing Gallery)';
          if (steelEl) steelEl.textContent = 'Independent Internal Gantry & Post Assemblies';
          if (costEl)  costEl.textContent = 'Unit Rate: ₹185.00 / SFT • Total: ₹3,25,600';
          break;

        case 'top_net':
          if (titleEl) titleEl.textContent = 'Overhead HDPE Ball-Stop Netting with Wire Rope Grid';
          if (catEl)   catEl.textContent = 'BIM Category: Protective Sports Netting';
          if (specEl)  specEl.textContent = '1.5mm - 2.5mm High-Tenacity UV-Stabilized Braided HDPE 40mm Mesh + 6mm Wire Ropes';
          if (dimEl)   dimEl.textContent = 'Coverage: ' + L + ' FT × ' + W + ' FT at ' + H + ' FT Elevation';
          if (areaEl)  areaEl.textContent = (L * W).toLocaleString('en-IN') + ' SQFT Top Net Coverage';
          if (steelEl) steelEl.textContent = 'Wire Rope Cables & Turnbuckles: ~180 kg';
          if (costEl)  costEl.textContent = 'Unit Rate: ₹12.50 / SFT • Total: ₹' + Math.round(L * W * 12.5).toLocaleString('en-IN');
          break;

        case 'cricket_pitch':
          if (titleEl) titleEl.textContent = 'Central Competition Cricket Pitch Pad';
          if (catEl)   catEl.textContent = 'BIM Category: Cricket Playing Field';
          var pitchL = cfg.pitch_length || 66;
          if (specEl)  specEl.textContent = 'Engineered Laser-Level Sub-base + 15mm High-Density Cricket AstroTurf Mat';
          if (dimEl)   dimEl.textContent = 'Length: ' + pitchL + ' FT | Width: 6.5 FT (2.0 MTR)';
          if (areaEl)  areaEl.textContent = Math.round(pitchL * 6.5) + ' SQFT Pitch Playing Surface';
          if (steelEl) steelEl.textContent = 'Non-structural Playing Feature';
          if (costEl)  costEl.textContent = 'Included in Turnkey Cricket Ground Package';
          break;

        case 'lighting_system':
          if (titleEl) titleEl.textContent = 'High-Lumen Stadium LED Floodlights';
          if (catEl)   catEl.textContent = 'BIM Category: Electrical & Sports Illuminance';
          if (specEl)  specEl.textContent = '150W / 200W IP66 Waterproof Toughened Glass Sports Floodlight Fixtures';
          var lightCount = (L <= 60) ? 4 : ((L <= 90) ? 6 : 8);
          if (dimEl)   dimEl.textContent = lightCount + ' Fixtures on Structural Cross-Arms at ' + H + ' FT Height';
          if (areaEl)  areaEl.textContent = 'Uniform 350-500 Lux Arena Illuminance across ' + (L * W) + ' SFT';
          if (steelEl) steelEl.textContent = 'Electrical Mounting Arms & Clamps: ~95 kg';
          if (costEl)  costEl.textContent = 'Unit Rate: ₹4,500 / Fixture • Total: ₹' + (lightCount * 4500).toLocaleString('en-IN');
          break;

        case 'columns_structure':
        default:
          if (titleEl) titleEl.textContent = 'Pre-Engineered Tubular Steel Columns & Framework';
          if (catEl)   catEl.textContent = 'BIM Category: Structural Stanchions & Columns';
          if (specEl)  specEl.textContent = 'Tata Structura / Apollo YST-310 Hollow Structural Steel Sections';
          var poleCount = Math.round((2 * Math.ceil(L / 15) + 2 * Math.ceil(W / 15)));
          if (dimEl)   dimEl.textContent = 'Height: ' + H + ' FT-0" | Bay Spacing: 15 FT-0" Centers';
          if (areaEl)  areaEl.textContent = poleCount + ' Vertical Columns with M20 Base Plates & Anchor Bolts';
          if (steelEl) steelEl.textContent = (poleCount * H * 0.0085).toFixed(2) + ' MT';
          if (costEl)  costEl.textContent = 'Included in Turnkey Structural Steelwork Package';
          break;
      }
    },

    // ──────────────────────────────────────────────────────────────────────────
    // 7. FULLSCREEN AUTOCAD & REVIT BIM STUDIO WORKSPACE
    // ──────────────────────────────────────────────────────────────────────────
    openFullscreenStudio: function() {
      var self = this;
      var studio = document.getElementById('cadRevitStudioModal');
      if (!studio) {
        studio = document.createElement('div');
        studio.id = 'cadRevitStudioModal';
        studio.style.cssText = 'position:fixed; top:0; left:0; width:100vw; height:100vh; z-index:999990; background:#0b1120; display:flex; flex-direction:column; color:#ffffff; font-family:Inter, sans-serif;';
        
        studio.innerHTML = 
          // Studio Top Ribbon
          '<div style="background:#0f172a; border-bottom:1px solid #334155; padding:8px 16px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">' +
            '<div class="d-flex align-items-center gap-3">' +
              '<div style="font-weight:900; font-size:14px; color:#38bdf8; letter-spacing:0.5px;"><i class="fa fa-compass-drafting me-2 text-danger"></i>GCM AUTOCAD &amp; REVIT BIM STUDIO</div>' +
              '<span class="badge bg-dark border border-secondary text-info">Bidirectional Associativity Active</span>' +
            '</div>' +
            '<div class="d-flex align-items-center gap-2">' +
              '<button type="button" class="btn btn-sm btn-primary" onclick="GcmCadRevit.toggleQuadView()"><i class="fa fa-table-cells me-1"></i>Revit Quad View (WT)</button>' +
              '<button type="button" class="btn btn-sm btn-success" onclick="GcmCadRevit.exportAutoCADDXF()"><i class="fa fa-download me-1"></i>Export AutoCAD .DXF</button>' +
              '<button type="button" class="btn btn-sm btn-danger px-3 fw-bold" onclick="GcmCadRevit.closeFullscreenStudio()"><i class="fa fa-check me-1"></i>Save &amp; Return to Estimation</button>' +
            '</div>' +
          '</div>' +
          // Studio Secondary Ribbon / Tool Palette
          '<div style="background:#1e293b; border-bottom:1px solid #334155; padding:6px 16px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; font-size:12px;">' +
            '<div class="d-flex align-items-center gap-2 flex-wrap">' +
              '<span class="text-secondary fw-bold">CAD TOOLS:</span>' +
              '<button type="button" class="btn btn-xs btn-outline-warning fw-bold py-1 px-2" onclick="setupBoxCricketOnly()"><i class="fa fa-baseball-bat-ball me-1"></i>Cricket Box</button>' +
              '<button type="button" class="btn btn-xs btn-outline-info fw-bold py-1 px-2" onclick="toggleAttachBadminton()"><i class="fa fa-table-tennis-paddle-ball me-1"></i>+ Attach Badminton</button>' +
              '<button type="button" class="btn btn-xs btn-outline-success fw-bold py-1 px-2" onclick="setupIndoorBadmintonArenaOnly()"><i class="fa fa-building-circle-check me-1"></i>Indoor Badminton Arena</button>' +
              '<button type="button" class="btn btn-xs btn-outline-light fw-bold py-1 px-2" onclick="toggleAllSidesGISheets()"><i class="fa fa-sheet-plastic me-1"></i>Wrap 4 Sides GI Sheets</button>' +
              '<button type="button" class="btn btn-xs btn-outline-danger fw-bold py-1 px-2" onclick="GcmCadRevit.actionRemoveRoof()"><i class="fa fa-trash me-1"></i>Remove Roof (Open-to-Sky)</button>' +
              '<button type="button" class="btn btn-xs btn-outline-success fw-bold py-1 px-2" onclick="GcmCadRevit.actionAddGate()"><i class="fa fa-door-open me-1"></i>Add Entrance Gate</button>' +
            '</div>' +
            '<div class="d-flex align-items-center gap-2">' +
              '<span class="text-secondary fw-bold">STRETCH:</span>' +
              '<button type="button" class="btn btn-xs btn-outline-light py-0 px-2" onclick="GcmCadRevit.adjustDimension(\'length\', -5)">L -5\'</button>' +
              '<button type="button" class="btn btn-xs btn-outline-light py-0 px-2" onclick="GcmCadRevit.adjustDimension(\'length\', 5)">L +5\'</button>' +
              '<button type="button" class="btn btn-xs btn-outline-light py-0 px-2" onclick="GcmCadRevit.adjustDimension(\'width\', -5)">W -5\'</button>' +
              '<button type="button" class="btn btn-xs btn-outline-light py-0 px-2" onclick="GcmCadRevit.adjustDimension(\'width\', 5)">W +5\'</button>' +
              '<button type="button" class="btn btn-xs btn-outline-light py-0 px-2" onclick="GcmCadRevit.adjustDimension(\'height\', -2)">H -2\'</button>' +
              '<button type="button" class="btn btn-xs btn-outline-light py-0 px-2" onclick="GcmCadRevit.adjustDimension(\'height\', 2)">H +2\'</button>' +
            '</div>' +
          '</div>' +
          // Studio Workspace Body: Left Tree + Center Viewport + Right Inspector
          '<div style="flex:1; display:flex; overflow:hidden;">' +
            // Left BIM Family Browser
            '<div style="width:260px; background:#0f172a; border-right:1px solid #334155; padding:12px; overflow-y:auto; font-size:12px;">' +
              '<div class="text-uppercase fw-bold text-info mb-2 pb-1 border-bottom border-secondary"><i class="fa fa-network-wired me-1"></i>Revit BIM Families</div>' +
              '<div class="d-flex flex-column gap-1">' +
                '<div class="p-2 rounded bim-family-item-studio" onclick="GcmCadRevit.inspectBimFamily(\'columns_structure\')" style="cursor:pointer; background:#1e293b; border:1px solid #0284c7;"><i class="fa fa-columns text-primary me-2"></i>Structural Frame</div>' +
                '<div class="p-2 rounded bim-family-item-studio" onclick="GcmCadRevit.inspectBimFamily(\'wall_cladding\')" style="cursor:pointer;"><i class="fa fa-sheet-plastic text-warning me-2"></i>Wall Cladding</div>' +
                '<div class="p-2 rounded bim-family-item-studio" onclick="GcmCadRevit.inspectBimFamily(\'roof_canopy\')" style="cursor:pointer;"><i class="fa fa-warehouse text-info me-2"></i>Roof Canopy</div>' +
                '<div class="p-2 rounded bim-family-item-studio" onclick="GcmCadRevit.inspectBimFamily(\'badminton_arena\')" style="cursor:pointer;"><i class="fa fa-table-tennis-paddle-ball text-success me-2"></i>Badminton Arena</div>' +
                '<div class="p-2 rounded bim-family-item-studio" onclick="GcmCadRevit.inspectBimFamily(\'cricket_pitch\')" style="cursor:pointer;"><i class="fa fa-baseball-bat-ball text-warning me-2"></i>Cricket Pitch</div>' +
                '<div class="p-2 rounded bim-family-item-studio" onclick="GcmCadRevit.inspectBimFamily(\'lighting_system\')" style="cursor:pointer;"><i class="fa fa-lightbulb text-warning me-2"></i>Stadium Lights</div>' +
              '</div>' +
            '</div>' +
            // Center Viewport Stage
            '<div style="flex:1; position:relative; background:#090d16; display:flex; flex-direction:column;">' +
              '<div style="position:absolute; top:10px; left:12px; z-index:10; background:rgba(0,0,0,0.8); border:1px solid #0284c7; border-radius:4px; padding:4px 10px; font-size:11px; color:#38bdf8; font-weight:700;">' +
                '<i class="fa fa-mouse-pointer text-warning me-1"></i>3D CLICK-TO-EDIT ACTIVE: Click any element to select and modify!' +
              '</div>' +
              '<div id="studio3dViewport" style="width:100%; height:100%;"></div>' +
            '</div>' +
          '</div>' +
          // Studio Bottom Status Bar
          '<div style="background:#090d16; border-top:1px solid #1e293b; padding:6px 16px; display:flex; align-items:center; justify-content:space-between; font-size:11px; font-family:monospace;">' +
            '<div id="studioCoordsHud" class="text-light">X: 90\'-00" • Y: 60\'-00" • Z: 25\'-00"</div>' +
            '<div class="text-secondary"><i class="fa fa-crosshairs me-1 text-danger"></i>AUTOCAD SNAP: ON &bull; ORTHO: ON &bull; SCALE: 1:100</div>' +
            '<div id="studioAreaHud" class="text-success fw-bold">5,400 SFT (502 SQM)</div>' +
          '</div>';

        document.body.appendChild(studio);
      }

      studio.style.display = 'flex';
      this.isFullscreenStudio = true;

      // Initialize Studio 3D Canvas
      var cfg = this.getCurrentConfig();
      if (window.TurnkeyViewer) {
        window.TurnkeyViewer.init3D('studio3dViewport', cfg);
        this.init3DRaycaster();
      }

      if (typeof window.showToastNotification === 'function') {
        window.showToastNotification('Opened Autodesk AutoCAD & Revit BIM Fullscreen Studio Workspace!');
      }
    },

    closeFullscreenStudio: function() {
      var studio = document.getElementById('cadRevitStudioModal');
      if (studio) studio.style.display = 'none';
      this.isFullscreenStudio = false;

      // Refresh standard visualizer
      if (typeof window.refreshLiveCourtViewer === 'function') {
        window.refreshLiveCourtViewer();
      }
      if (typeof window.showToastNotification === 'function') {
        window.showToastNotification('Saved CAD/Revit changes to estimation proposal!');
      }
    },

    // ──────────────────────────────────────────────────────────────────────────
    // 8. AUTOCAD NATIVE DXF EXPORT ENGINE (.DXF R12 / R2000 ASCII)
    // ──────────────────────────────────────────────────────────────────────────
    exportAutoCADDXF: function() {
      var cfg = this.getCurrentConfig();
      var L = parseFloat(cfg.length) || 90;
      var W = parseFloat(cfg.width) || 60;
      var H = parseFloat(cfg.height) || 25;
      var sport = cfg.sport_type || 'box_cricket';
      var docId = (typeof window.existingDocId !== 'undefined' && window.existingDocId) ? window.existingDocId : (cfg.id || 'GCM-CAD-ARENA');

      var lines = [];
      lines.push('0', 'SECTION', '2', 'HEADER', '9', '$ACADVER', '1', 'AC1009', '9', '$INSUNITS', '70', '1', '0', 'ENDSEC');

      lines.push('0', 'SECTION', '2', 'TABLES', '0', 'TABLE', '2', 'LAYER', '70', '8');
      var layersDef = [
        { name: '0', color: 7 },
        { name: 'A-GRID', color: 8 },
        { name: 'S-COLS', color: 1 },
        { name: 'A-WALLS', color: 7 },
        { name: 'A-COURT', color: 3 },
        { name: 'A-DOORS', color: 2 },
        { name: 'A-DIMS', color: 4 },
        { name: 'A-TEXT', color: 5 }
      ];
      layersDef.forEach(function(ly) {
        lines.push('0', 'LAYER', '2', ly.name, '70', '0', '62', String(ly.color), '6', 'CONTINUOUS');
      });
      lines.push('0', 'ENDTAB', '0', 'ENDSEC');

      lines.push('0', 'SECTION', '2', 'ENTITIES');

      function addDxfLine(layer, x1, y1, x2, y2) {
        lines.push('0', 'LINE', '8', layer, '10', x1.toFixed(3), '20', y1.toFixed(3), '30', '0.0', '11', x2.toFixed(3), '21', y2.toFixed(3), '31', '0.0');
      }

      function addDxfText(layer, x, y, height, text) {
        lines.push('0', 'TEXT', '8', layer, '10', x.toFixed(3), '20', y.toFixed(3), '30', '0.0', '40', height.toFixed(3), '1', text);
      }

      function addDxfRect(layer, x, y, width, height) {
        addDxfLine(layer, x, y, x + width, y);
        addDxfLine(layer, x + width, y, x + width, y + height);
        addDxfLine(layer, x + width, y + height, x, y + height);
        addDxfLine(layer, x, y + height, x, y);
      }

      // 1. Civil Sub-base & Perimeter Boundary
      addDxfRect('A-WALLS', 0, 0, L, W);

      // 2. Structural Column Markers
      var baySpacing = 15;
      for (var colX = 0; colX <= L; colX += baySpacing) {
        addDxfRect('S-COLS', colX - 0.5, -0.5, 1.0, 1.0);
        addDxfRect('S-COLS', colX - 0.5, W - 0.5, 1.0, 1.0);
      }
      for (var colY = 0; colY <= W; colY += baySpacing) {
        addDxfRect('S-COLS', -0.5, colY - 0.5, 1.0, 1.0);
        addDxfRect('S-COLS', L - 0.5, colY - 0.5, 1.0, 1.0);
      }

      // 3. Sport Specific Markings
      if (sport.indexOf('cricket') !== -1 || sport === 'box_cricket') {
        var pitchL = cfg.pitch_length || 66;
        var pX = (L - pitchL) / 2;
        var pY = (W - 6.5) / 2;
        addDxfRect('A-COURT', pX, pY, pitchL, 6.5);
        addDxfText('A-TEXT', L / 2 - 15, W / 2, 2.0, 'CRICKET PITCH (' + pitchL + ' FT x 6.5 FT)');
      }

      if (sport === 'badminton' || sport.indexOf('badminton') !== -1 || (cfg.zones && cfg.zones.badminton)) {
        var bX = (L - 44) / 2;
        var bY = (W - 20) / 2;
        addDxfRect('A-COURT', bX, bY, 44, 20);
        addDxfLine('A-COURT', bX + 22, bY, bX + 22, bY + 20);
        addDxfText('A-TEXT', bX + 22, bY + 10, 1.8, 'BWF BADMINTON COURT (44 x 20 FT)');
      }

      // 4. Central Entrance Gate
      var gateW = 6;
      var gateMidX = L;
      var gateY = (W - gateW) / 2;
      addDxfLine('A-DOORS', gateMidX, gateY, gateMidX, gateY + gateW);
      addDxfLine('A-DOORS', gateMidX, gateY, gateMidX + gateW, gateY);
      addDxfText('A-DOORS', gateMidX + 1, gateY + 3, 1.5, 'CENTER ENTRANCE GATE');

      // 5. AutoCAD Dimensions & Labels
      addDxfLine('A-DIMS', 0, -8, L, -8);
      addDxfText('A-DIMS', L / 2 - 10, -11, 2.5, 'TOTAL LENGTH: ' + L + "'-0\"");
      addDxfLine('A-DIMS', -8, 0, -8, W);
      addDxfText('A-DIMS', -12, W / 2 - 5, 2.5, 'TOTAL WIDTH: ' + W + "'-0\"");

      // Project Title Block in DXF
      addDxfText('A-TEXT', 5, W + 14, 3.5, 'NETTINGTEAM — ARCHITECTURAL CAD DRAWING');
      addDxfText('A-TEXT', 5, W + 9, 2.2, 'PROJECT: ' + docId + ' | FACILITY: ' + sport.toUpperCase());
      addDxfText('A-TEXT', 5, W + 5, 2.0, 'DIMENSIONS: ' + L + ' FT x ' + W + ' FT x ' + H + ' FT CLEAR HEIGHT | SCALE: 1:100');

      lines.push('0', 'ENDSEC', '0', 'EOF');

      var dxfString = lines.join('\n');
      var blob = new Blob([dxfString], { type: 'application/dxf;charset=utf-8' });
      var url = URL.createObjectURL(blob);
      var a = document.createElement('a');
      a.href = url;
      a.download = docId + '_AutoCAD_Drawing.dxf';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);

      if (typeof window.showToastNotification === 'function') {
        window.showToastNotification('Downloaded native AutoCAD drawing: ' + docId + '_AutoCAD_Drawing.dxf');
      }
    }
  };

  window.GcmCadRevit = GcmCadRevit;
  window.toggleRevitInspector = function() { GcmCadRevit.toggleInspector(); };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() { GcmCadRevit.init(); });
  } else {
    GcmCadRevit.init();
  }

})(window);
