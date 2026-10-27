  <!-- GCM XTREME &mdash; PARAMETRIC AI SPORTS ARCHITECTURE STUDIO (STREAMLINED 4-STEP PRECISION PROCESS) -->
  <div class="form-section mb-3 p-0" id="turnkeyPanel" style="border:none; background:transparent; <?= $estSubtype !== 'turnkey_court' ? 'display:none;' : '' ?>">
    <!-- Preserved Hidden Turnkey Config Fields -->
    <input type="hidden" name="turnkey_config" id="turnkeyConfigJson" value="<?= e(json_encode($d['turnkey_config'] ?? null)) ?>">
    <input type="hidden" name="turnkey_gates_json" id="turnkeyGatesJson" value="<?= e(json_encode($d['turnkey_config']['gates'] ?? [])) ?>">
    <input type="hidden" name="turnkey_zones_json" id="turnkeyZonesJson" value="<?= e(json_encode($d['turnkey_config']['zones'] ?? null)) ?>">
    <input type="hidden" name="turnkey_excluded_json" id="turnkeyExcludedJson" value="<?= e(json_encode($d['turnkey_config']['excluded_items'] ?? [])) ?>">
    <input type="hidden" name="tk_open_sides" id="tkOpenSidesInput" value="<?= e(json_encode($d['turnkey_config']['open_sides'] ?? null)) ?>">
    <input type="hidden" name="tk_layers" id="tkLayersInput" value="<?= e(json_encode($d['turnkey_config']['layers'] ?? null)) ?>">

    <style>
      .arena-concept-card, .enclosure-style-card {
        background: #1e293b;
        border: 1px solid #334155 !important;
        transition: all 0.2s ease;
        cursor: pointer;
      }
      .arena-concept-card:hover, .enclosure-style-card:hover {
        border-color: #38bdf8 !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(56, 189, 248, 0.15);
      }
      .arena-concept-card.active, .enclosure-style-card.active {
        background: #0f2744 !important;
        border-color: #38bdf8 !important;
        box-shadow: 0 0 0 2px #38bdf8;
      }
      .master-comp-card {
        background: #1e293b;
        border: 1px solid #334155;
        border-radius: 8px;
        padding: 10px 12px;
        transition: all 0.15s ease;
      }
      .master-comp-card.excluded {
        opacity: 0.6;
        border-color: #ef4444;
        background: #1a1e29;
      }
      .master-comp-card:hover {
        border-color: #64748b;
      }
      .cursor-pointer { cursor: pointer; }
    </style>

    <div id="gcmExtremeStudio" class="p-3 rounded-3 shadow-sm mb-3" style="background:#0f172a; border:2px solid #38bdf8; color:#f8fafc;">
      
      <!-- Top Studio Header -->
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pb-3 mb-3 border-bottom border-secondary">
        <div class="d-flex align-items-center gap-3">
          <div style="background:linear-gradient(135deg,#0284c7,#0369a1); color:#fff; width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.4rem; box-shadow:0 4px 12px rgba(2,132,199,0.4);">
            <i class="fa fa-compass-drafting"></i>
          </div>
          <div>
            <h5 class="mb-0 text-info fw-bold" style="letter-spacing:0.5px;">GCM XTREME &mdash; PARAMETRIC AI SPORTS ARCHITECTURE STUDIO</h5>
            <small class="text-light text-opacity-75" style="font-size:11px;">
              Next-Gen AI BIM Studio &bull; Multi-Sport Campus &bull; Dual-Game Overlays &bull; Triple-Click Surface HUD &bull; 281 Master Products
            </small>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <a href="cad_designer.php?edit=<?= urlencode($editId ?? ($d['id'] ?? '')) ?>" target="_blank" class="btn btn-sm btn-outline-info fw-bold py-1 px-3" title="Open Fullscreen Visual CAD &amp; Revit Drawing Board">
            <i class="fa fa-up-right-and-down-left-from-center me-1"></i>Fullscreen CAD Studio
          </a>
          <button type="button" class="btn btn-sm btn-outline-warning fw-bold py-1 px-3" onclick="if(window.GcmCadRevit){GcmCadRevit.exportAutoCADDXF();}else{if (window.GcmNotify) { GcmNotify.info('AutoCAD Engine', 'Compiling AutoCAD .DXF file...'); }}" title="Download Production AutoCAD Drawing (.DXF R12/2000)">
            <i class="fa fa-download me-1"></i>AutoCAD .DXF
          </button>
          <button type="button" class="btn btn-sm btn-success fw-bold py-1 px-3 shadow" onclick="applyExtremeStudioToDocument()">
            <i class="fa fa-check-double me-1"></i>Apply &amp; Sync BOQ
          </button>
        </div>
      </div>

      
      <!-- ═════════════════════════════════════════════════════════════════════
           GCM XTREME AI PROMPT-TO-ARENA ENGINE ("Type & Instant Generate")
           ═════════════════════════════════════════════════════════════════════ -->
      <div class="p-3 mb-4 rounded-3 border border-info border-opacity-50" style="background: linear-gradient(135deg, #0b1528 0%, #06203d 100%); box-shadow: 0 4px 20px rgba(56, 189, 248, 0.15);">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-info text-dark fw-bold px-2 py-1" style="font-size:10px;"><i class="fa fa-wand-magic-sparkles me-1"></i>AI PROMPT-TO-ARENA</span>
            <span class="text-light fw-bold" style="font-size:12.5px;">Describe your arena in plain words &bull; Auto-builds 3D/2D BIM, Multi-Courts, Dual Linings &amp; BOQ</span>
          </div>
          <span class="text-info text-opacity-75 small font-monospace" style="font-size:10.5px;">Powered by GCM XTREME Engine v2.0</span>
        </div>
        
        <div class="input-group input-group-sm mb-2">
          <span class="input-group-text bg-dark border-info text-info"><i class="fa fa-terminal"></i></span>
          <input type="text" id="xtremeAiPromptInput" class="form-control bg-dark text-light border-info py-2" placeholder="e.g. 1 Box Cricket 100x60 with 4 Badminton courts and 2 Pickleball with 1 meter walkway..." style="font-size:12.5px;" onkeydown="if(event.key==='Enter'){event.preventDefault();runXtremeAiPrompt();}">
          <button type="button" class="btn btn-info text-dark fw-bold px-4" onclick="runXtremeAiPrompt()">
            <i class="fa fa-bolt me-1"></i>Generate Arena
          </button>
        </div>

        <!-- Quick AI Prompt Presets -->
        <div class="d-flex align-items-center gap-1 flex-wrap" style="font-size:10.5px;">
          <span class="text-secondary me-1">Try quick prompts:</span>
          <button type="button" class="btn btn-xs btn-outline-secondary text-light py-0 px-2 rounded-pill" onclick="setAndRunPrompt('1 Box Cricket 100x60 with 2 Badminton Courts attached')">
            Cricket + 2 Badminton
          </button>
          <button type="button" class="btn btn-xs btn-outline-secondary text-light py-0 px-2 rounded-pill" onclick="setAndRunPrompt('Badminton and Basketball dual games in one single court')">
            Badminton + Basketball (Dual in 1 Court)
          </button>
          <button type="button" class="btn btn-xs btn-outline-secondary text-light py-0 px-2 rounded-pill" onclick="setAndRunPrompt('7-a-side Football turf 140x80 with Basketball hoop included')">
            7-A-Side Football + Basketball
          </button>
          <button type="button" class="btn btn-xs btn-outline-secondary text-light py-0 px-2 rounded-pill" onclick="setAndRunPrompt('Indoor Badminton 4 courts and 2 Pickleball with 1 meter walkway')">
            4 Badminton + 2 Pickleball (1M Walkway)
          </button>
        </div>
      </div>

      <!-- ═════════════════════════════════════════════════════════════════════════
           STEP 1: ARENA SCOPE & CONCEPT PRESET (4 Visual Cards + Dimensions)
           ═════════════════════════════════════════════════════════════════════════ -->
      <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="badge bg-primary text-uppercase px-2 py-1 fw-bold" style="font-size:10px; letter-spacing:0.5px;">
            Step 1 of 4: Select Arena Scope &amp; Sports Concept
          </span>
          <span class="text-light text-opacity-50" style="font-size:11px;">Click a concept card to auto-configure standard arena dimensions &amp; zones</span>
        </div>

        <div class="row g-2 mb-3" id="arenaConceptCards">
          <!-- Concept 1: Box Cricket Ground -->
          <div class="col-md-3">
            <div class="arena-concept-card p-3 rounded border text-start h-100 cursor-pointer" id="cardConceptCricket" onclick="selectArenaConcept('cricket')">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-bold text-success fs-6"><i class="fa fa-cricket-bat-ball me-1"></i>Box Cricket</span>
                <span class="badge bg-success" style="font-size:9px;">100×60 FT</span>
              </div>
              <div class="small text-light text-opacity-75 mb-2" style="font-size:11px; line-height:1.4;">
                Full-size box cricket ground with central 66 FT competition pitch &amp; ball-stop perimeter.
              </div>
              <div class="badge bg-dark text-info border border-secondary w-100 py-1" style="font-size:9px;">
                6,000 SFT &bull; 25' Height
              </div>
            </div>
          </div>

          <!-- Concept 2: Multi-Sport Arena Complex (Cricket + Badminton) -->
          <div class="col-md-3">
            <div class="arena-concept-card p-3 rounded border text-start h-100 cursor-pointer active" id="cardConceptMultiSport" onclick="selectArenaConcept('multisport')">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-bold text-info fs-6"><i class="fa fa-cubes me-1"></i>Multi-Sport Complex</span>
                <span class="badge bg-info text-dark" style="font-size:9px;">160×60 FT</span>
              </div>
              <div class="small text-light text-opacity-75 mb-2" style="font-size:11px; line-height:1.4;">
                Dual-Zone Arena: 100 FT Cricket Ground + 60 FT Indoor Badminton Hall (2 Courts).
              </div>
              <div class="badge bg-dark text-info border border-secondary w-100 py-1" style="font-size:9px;">
                9,600 SFT &bull; 30' Clear Height
              </div>
            </div>
          </div>

          <!-- Concept 3: Indoor Badminton Court Hall -->
          <div class="col-md-3">
            <div class="arena-concept-card p-3 rounded border text-start h-100 cursor-pointer" id="cardConceptBadminton" onclick="selectArenaConcept('badminton')">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-bold text-warning fs-6"><i class="fa fa-feather me-1"></i>Badminton Hall</span>
                <span class="badge bg-warning text-dark" style="font-size:9px;">88×44 FT</span>
              </div>
              <div class="small text-light text-opacity-75 mb-2" style="font-size:11px; line-height:1.4;">
                2 Regulation BWF Courts with M20 Laser RCC Bed &amp; 4.5mm Vinyl Sports Mat.
              </div>
              <div class="badge bg-dark text-info border border-secondary w-100 py-1" style="font-size:9px;">
                3,872 SFT &bull; 28' Height
              </div>
            </div>
          </div>

          <!-- Concept 4: Custom Sized Arena -->
          <div class="col-md-3">
            <div class="arena-concept-card p-3 rounded border text-start h-100 cursor-pointer" id="cardConceptCustom" onclick="selectArenaConcept('custom')">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-bold text-light fs-6"><i class="fa fa-pen-ruler me-1"></i>Custom Arena</span>
                <span class="badge bg-secondary" style="font-size:9px;">Any Size</span>
              </div>
              <div class="small text-light text-opacity-75 mb-2" style="font-size:11px; line-height:1.4;">
                Enter custom site length, width, and clear height for specialized facilities.
              </div>
              <div class="badge bg-dark text-info border border-secondary w-100 py-1" style="font-size:9px;">
                Fully Configurable
              </div>
            </div>
          </div>
        </div>

        <!-- 4 Dimension Inputs Bar -->
        <div class="p-2 rounded border border-secondary" style="background:#131d31;">
          <div class="row g-2 align-items-center">
            <div class="col-md-3 col-6">
              <label class="form-label text-secondary mb-1" style="font-size:11px;">Length (FT):</label>
              <div class="input-group input-group-sm">
                <input type="number" step="1" min="20" max="400" class="form-control bg-dark text-light border-secondary text-center fw-bold" id="tk_length" name="tk_length" value="<?= (float)($d['turnkey_config']['length'] ?? 160) ?>" oninput="onStudioDimensionChange()">
                <span class="input-group-text bg-secondary border-secondary text-light">FT</span>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <label class="form-label text-secondary mb-1" style="font-size:11px;">Width (FT):</label>
              <div class="input-group input-group-sm">
                <input type="number" step="1" min="15" max="300" class="form-control bg-dark text-light border-secondary text-center fw-bold" id="tk_width" name="tk_width" value="<?= (float)($d['turnkey_config']['width'] ?? 60) ?>" oninput="onStudioDimensionChange()">
                <span class="input-group-text bg-secondary border-secondary text-light">FT</span>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <label class="form-label text-secondary mb-1" style="font-size:11px;">Clear Height (FT):</label>
              <div class="input-group input-group-sm">
                <input type="number" step="1" min="12" max="60" class="form-control bg-dark text-light border-secondary text-center fw-bold" id="tk_height" name="tk_height" value="<?= (float)($d['turnkey_config']['height'] ?? 30) ?>" oninput="onStudioDimensionChange()">
                <span class="input-group-text bg-secondary border-secondary text-light">FT</span>
              </div>
              <div class="d-flex gap-1 mt-1">
                <button type="button" class="btn btn-xs btn-outline-secondary text-light py-0 px-1" onclick="setStudioHeight(22)" style="font-size:9px;">22'</button>
                <button type="button" class="btn btn-xs btn-outline-secondary text-light py-0 px-1" onclick="setStudioHeight(25)" style="font-size:9px;">25'</button>
                <button type="button" class="btn btn-xs btn-outline-secondary text-light py-0 px-1" onclick="setStudioHeight(28)" style="font-size:9px;">28'</button>
                <button type="button" class="btn btn-xs btn-outline-secondary text-light py-0 px-1" onclick="setStudioHeight(30)" style="font-size:9px;">30'</button>
                <button type="button" class="btn btn-xs btn-outline-secondary text-light py-0 px-1" onclick="setStudioHeight(35)" style="font-size:9px;">35'</button>
              </div>
            </div>
            <div class="col-md-3 col-6">
              <label class="form-label text-secondary mb-1" style="font-size:11px;">Cricket Pitch (FT):</label>
              <div class="input-group input-group-sm">
                <input type="number" step="1" min="0" max="100" class="form-control bg-dark text-light border-secondary text-center fw-bold" id="tk_pitch_length" name="tk_pitch_length" value="<?= (float)($d['turnkey_config']['pitch_length'] ?? 66) ?>" oninput="onStudioDimensionChange()">
                <span class="input-group-text bg-secondary border-secondary text-light">FT</span>
              </div>
              <div class="d-flex gap-1 mt-1">
                <button type="button" class="btn btn-xs btn-outline-secondary text-light py-0 px-1" onclick="setStudioPitch(0)" style="font-size:9px;">None</button>
                <button type="button" class="btn btn-xs btn-outline-secondary text-light py-0 px-1" onclick="setStudioPitch(66)" style="font-size:9px;">66' Full</button>
                <button type="button" class="btn btn-xs btn-outline-secondary text-light py-0 px-1" onclick="setStudioPitch(83)" style="font-size:9px;">83' Pro</button>
              </div>
            </div>
          </div>
          <!-- Real-Time Dimension Area Feedback -->
          <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top border-secondary text-secondary" style="font-size:11px;">
            <div>
              <span>Calculated Carpet Footprint: </span>
              <strong class="text-info" id="studioAreaBadge">160 × 60 FT • 9,600 SFT</strong>
            </div>
            <div>
              <span>Metric Standard: </span>
              <strong class="text-warning" id="studioMetricBadge">48.77 M × 18.29 M • 891.9 SQM</strong>
            </div>
          
        <!-- Multi-Court Attachment & Dual-Game Proximity Bar -->
        <div class="p-2 rounded border border-secondary mb-3 mt-2" style="background:#0c1626;">
          <div class="row g-2 align-items-center">
            <div class="col-md-4">
              <label class="form-label text-info fw-bold mb-1" style="font-size:11px;">
                <i class="fa fa-cubes me-1"></i>Multi-Court Campus Layout:
              </label>
              <select class="form-select form-select-sm bg-dark text-light border-secondary" id="xtremeCourtAttachment" onchange="onXtremeAttachmentChange(this.value)">
                <option value="attached">Directly Attached Adjacent (Shared Structural Columns)</option>
                <option value="gap_2ft">2 FT Clearance Gap (Dedicated Structure Lines)</option>
                <option value="gap_1m">1 Meter Spectator Walkway (3.28 FT Spacing)</option>
                <option value="gap_5ft">5 FT Equipment &amp; Drainage Corridor</option>
                <option value="single">Single Standalone Arena</option>
              </select>
            </div>
            <div class="col-md-5">
              <label class="form-label text-warning fw-bold mb-1" style="font-size:11px;">
                <i class="fa fa-layer-group me-1"></i>Dual-Sport Hybrid Linings (2 Games in 1 Court):
              </label>
              <select class="form-select form-select-sm bg-dark text-light border-secondary" id="xtremeDualSportSelect" onchange="onXtremeDualSportChange(this.value)">
                <option value="none">Standard Single Game Linings</option>
                <option value="badm_basket">🏸 Badminton + 🏀 Basketball (Dual Painted Regulations)</option>
                <option value="foot_basket">⚽ Football Futsal + 🏀 Basketball (Dual Lines + Hoops)</option>
                <option value="badm_pickle">🏸 Badminton + 🏓 Pickleball (Tournament Dual Markings)</option>
                <option value="cricket_pickle">🏏 Box Cricket + 🏓 Pickleball (Dual Turf Deck)</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label text-secondary mb-1" style="font-size:11px;">
                <i class="fa fa-hand-pointer me-1"></i>Interactive Editing Gesture:
              </label>
              <div class="badge bg-dark text-info border border-info w-100 py-2 text-wrap" style="font-size:10px;">
                <i class="fa fa-mouse-pointer me-1"></i><strong>TRIPLE-CLICK (3 Clicks)</strong> anywhere to Add/Delete/Edit!
              </div>
            </div>
          </div>
        </div>
</div>
        </div>
      </div>

      <!-- ═════════════════════════════════════════════════════════════════════════
           STEP 2: ARCHITECTURAL ENCLOSURE STYLE (4 Visual Cards)
           ═════════════════════════════════════════════════════════════════════════ -->
      <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="badge bg-info text-dark text-uppercase px-2 py-1 fw-bold" style="font-size:10px; letter-spacing:0.5px;">
            Step 2 of 4: Architectural Enclosure &amp; Roof Style
          </span>
          <span class="text-light text-opacity-50" style="font-size:11px;">Choose the weather protection &amp; structural cladding tier</span>
        </div>

        <div class="row g-2" id="enclosureStyleCards">
          <!-- Style 1: Open-to-Sky Box Arena -->
          <div class="col-md-3">
            <div class="enclosure-style-card p-3 rounded border text-start h-100 cursor-pointer" id="cardStyleOpenSky" onclick="selectEnclosureStyle('open_sky')">
              <div class="fw-bold text-light fs-6 mb-1"><i class="fa fa-sun text-warning me-1"></i>Open-to-Sky</div>
              <div class="small text-light text-opacity-75 mb-2" style="font-size:11px; line-height:1.4;">
                Perimeter ball-stop netting with heavy structural steel column framework. Completely open top.
              </div>
              <span class="badge bg-secondary" style="font-size:9px;">Economical &bull; Natural Ventilation</span>
            </div>
          </div>

          <!-- Style 2: Overhead Cable Net Arena -->
          <div class="col-md-3">
            <div class="enclosure-style-card p-3 rounded border text-start h-100 cursor-pointer" id="cardStyleCableNet" onclick="selectEnclosureStyle('cable_net')">
              <div class="fw-bold text-light fs-6 mb-1"><i class="fa fa-border-all text-info me-1"></i>Cable Net Roof</div>
              <div class="small text-light text-opacity-75 mb-2" style="font-size:11px; line-height:1.4;">
                Full 6-sided ball-retention enclosure with steel wire-rope suspended overhead mesh netting.
              </div>
              <span class="badge bg-info text-dark" style="font-size:9px;">100% Ball Retention</span>
            </div>
          </div>

          <!-- Style 3: Full PEB Steel Stadium -->
          <div class="col-md-3">
            <div class="enclosure-style-card p-3 rounded border text-start h-100 cursor-pointer active" id="cardStyleFullSteel" onclick="selectEnclosureStyle('full_steel')">
              <div class="fw-bold text-light fs-6 mb-1"><i class="fa fa-warehouse text-success me-1"></i>Full PEB Stadium</div>
              <div class="small text-light text-opacity-75 mb-2" style="font-size:11px; line-height:1.4;">
                All-weather curved arch tubular steel roof trusses + 0.50mm PPGL sheets &amp; wall cladding.
              </div>
              <span class="badge bg-success" style="font-size:9px;">100% Weatherproof &bull; Pro Quality</span>
            </div>
          </div>

          <!-- Style 4: Hybrid Clad Arena -->
          <div class="col-md-3">
            <div class="enclosure-style-card p-3 rounded border text-start h-100 cursor-pointer" id="cardStyleHybrid" onclick="selectEnclosureStyle('hybrid')">
              <div class="fw-bold text-light fs-6 mb-1"><i class="fa fa-layer-group text-warning me-1"></i>Hybrid Cladding</div>
              <div class="small text-light text-opacity-75 mb-2" style="font-size:11px; line-height:1.4;">
                Steel truss roof with lower 10 FT PPGL wall sheeting + upper high-tenacity ball-stop netting.
              </div>
              <span class="badge bg-warning text-dark" style="font-size:9px;">Balanced Airflow &amp; Shield</span>
            </div>
          </div>
        </div>
      </div>

      <!-- ═════════════════════════════════════════════════════════════════════════
           STEP 3: 8 MASTER ENGINEERING COMPONENTS (With 281 Products Dropdown)
           ═════════════════════════════════════════════════════════════════════════ -->
      <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="badge bg-success text-uppercase px-2 py-1 fw-bold" style="font-size:10px; letter-spacing:0.5px;">
            Step 3 of 4: Master Engineering Building Components &amp; Catalog Products
          </span>
          <span class="text-light text-opacity-50" style="font-size:11px;">Direct dropdown selection linked to 281 Master Products &bull; Real-time rate &amp; brand sync</span>
        </div>

        <div class="row g-2" id="masterBimComponentGrid">
          <!-- Dynamically populated by JS from window.studioState.components -->
        </div>
      </div>

      <!-- ═════════════════════════════════════════════════════════════════════════
           STEP 4: VISUAL CAD / REVIT BIM VIEWPORT & SYNCHRONIZED BOQ
           ═════════════════════════════════════════════════════════════════════════ -->
      <div>
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="badge bg-warning text-dark text-uppercase px-2 py-1 fw-bold" style="font-size:10px; letter-spacing:0.5px;">
            Step 4 of 4: Synchronized BIM Visualizer &amp; Document BOQ
          </span>
          <span class="text-light text-opacity-50" style="font-size:11px;">Interactive CAD/Revit inspection &bull; Click Apply to push items to quotation</span>
        </div>

        <div class="row g-3">
          <!-- Left: Single Integrated Visualizer Viewport -->
          <div class="col-lg-8">
            <div class="card bg-dark border-secondary overflow-hidden shadow">
              <div class="card-header bg-dark border-secondary d-flex justify-content-between align-items-center py-2 px-3 flex-wrap gap-2">
                <!-- Visualizer Tabs -->
                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-outline-info active fw-bold py-1 px-3" id="btnVisTab3d" onclick="switchStudioVisTab('3d')">
                    <i class="fa fa-cube me-1"></i>3D BIM Model
                  </button>
                  <button type="button" class="btn btn-outline-info fw-bold py-1 px-3" id="btnVisTabFloor" onclick="switchStudioVisTab('floor')">
                    <i class="fa fa-map me-1"></i>2D Floor Plan
                  </button>
                  <button type="button" class="btn btn-outline-info fw-bold py-1 px-3" id="btnVisTabElev" onclick="switchStudioVisTab('elevation')">
                    <i class="fa fa-building me-1"></i>Revit Elevations
                  </button>
                  <button type="button" class="btn btn-outline-info fw-bold py-1 px-3" id="btnVisTabQuad" onclick="switchStudioVisTab('quad')">
                    <i class="fa fa-table-cells me-1"></i>Revit Quad View
                  </button>
                </div>
                <!-- HUD Coordinates -->
                <div class="text-light text-opacity-75 small" id="studioHudCoordinates" style="font-family:monospace; font-size:11px;">
                  Length: 160 FT | Width: 60 FT | Height: 30 FT
                </div>
              </div>

              <!-- Main Viewport Box -->
              <div class="card-body p-0 position-relative" style="background:#090d16; min-height:420px; height:460px;">
                <!-- 3D Canvas Container -->
                <div id="studio3dContainer" style="width:100%; height:100%; position:absolute; top:0; left:0;"></div>
                <!-- 2D Floor Plan SVG Container -->
                <div id="studioFloorContainer" style="width:100%; height:100%; position:absolute; top:0; left:0; display:none; overflow:auto; background:#111827;"></div>
                <!-- 2D Elevation Container -->
                <div id="studioElevContainer" style="width:100%; height:100%; position:absolute; top:0; left:0; display:none; overflow:auto; background:#111827;"></div>
                <!-- Quad View Container -->
                <div id="studioQuadContainer" style="width:100%; height:100%; position:absolute; top:0; left:0; display:none; grid-template-columns:1fr 1fr; grid-template-rows:1fr 1fr; gap:2px; background:#1e293b;">
                  <div id="sQuad3d" style="background:#0b1120; position:relative; overflow:hidden;"></div>
                  <div id="sQuadFloor" style="background:#0f172a; position:relative; overflow:hidden;"></div>
                  <div id="sQuadSide" style="background:#0f172a; position:relative; overflow:hidden;"></div>
                  <div id="sQuadCross" style="background:#0f172a; position:relative; overflow:hidden;"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right: Live Turnkey BOQ Financial Summary -->
          <div class="col-lg-4">
            <div class="card bg-dark border-secondary h-100 shadow d-flex flex-column">
              <div class="card-header bg-dark border-secondary py-2 px-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold text-info" style="font-size:12px;"><i class="fa fa-list-check me-1"></i>Live Turnkey BOQ</span>
                <span class="badge bg-primary" id="studioActiveItemsBadge" style="font-size:10px;">8 Active Items</span>
              </div>
              <div class="card-body p-3 flex-grow-1 overflow-auto" id="studioBoqMiniList" style="max-height:340px; font-size:11.5px;">
                <!-- Populated dynamically with active components and line totals -->
              </div>
              <div class="card-footer bg-dark border-secondary p-3">
                <div class="d-flex justify-content-between align-items-center mb-1 text-light text-opacity-75 small">
                  <span>BOQ Subtotal:</span>
                  <strong class="text-light" id="studioSubtotalText">₹0</strong>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2 text-light text-opacity-75 small">
                  <span>GST (18%):</span>
                  <span class="text-light text-opacity-75" id="studioGstText">₹0</span>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-2 border-top border-secondary mb-3">
                  <span class="fw-bold text-warning" style="font-size:13px;">Estimated Turnkey Total:</span>
                  <strong class="text-success fs-5" id="studioGrandTotalText">₹0</strong>
                </div>
                <button type="button" class="btn btn-success w-100 fw-bold py-2 shadow" onclick="applyExtremeStudioToDocument()">
                  <i class="fa fa-check-double me-2"></i>Apply &amp; Sync BOQ to Quotation
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div> <!-- Close #gcmExtremeStudio -->
  </div> <!-- Close #turnkeyPanel -->

  <!-- Universal Component Customizer Modal -->
  <div class="modal fade" id="studioCompCustomizerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content bg-dark text-light border-info shadow-lg">
        <div class="modal-header border-secondary" style="background:#0f172a;">
          <h5 class="modal-title text-info fw-bold mb-0">
            <i class="fa fa-sliders me-2"></i><span id="mStudioCompTitle">Component Specification</span>
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="font-size:12px;">
          <div class="row g-2">
            <div class="col-md-7">
              <label class="form-label text-secondary mb-1">Product Title:</label>
              <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary fw-bold" id="mStudioInputName">
            </div>
            <div class="col-md-5">
              <label class="form-label text-secondary mb-1">Brand / Manufacturer:</label>
              <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary" id="mStudioInputBrand">
            </div>
            <div class="col-12">
              <label class="form-label text-secondary mb-1">Engineering Specifications:</label>
              <textarea class="form-control form-control-sm bg-dark text-light border-secondary" id="mStudioInputSpec" rows="2"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label text-secondary mb-1">Material Gauge / Thickness / DTEX:</label>
              <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary" id="mStudioInputGauge">
            </div>
            <div class="col-md-3">
              <label class="form-label text-secondary mb-1">Quantity:</label>
              <input type="number" step="any" class="form-control form-control-sm bg-dark text-light border-secondary text-center" id="mStudioInputQty" oninput="recalcStudioModalAmount()">
            </div>
            <div class="col-md-3">
              <label class="form-label text-secondary mb-1">Unit Rate (₹):</label>
              <input type="number" step="any" class="form-control form-control-sm bg-dark text-light border-secondary text-center fw-bold text-success" id="mStudioInputRate" oninput="recalcStudioModalAmount()">
            </div>
            <div class="col-12">
              <label class="form-label text-secondary mb-1">Proposal Bullet Points (One per line):</label>
              <textarea class="form-control form-control-sm bg-dark text-light border-secondary" id="mStudioInputBullets" rows="3"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer border-secondary justify-content-between" style="background:#0f172a;">
          <button type="button" class="btn btn-sm btn-outline-danger fw-bold" onclick="deleteStudioComponentFromModal()">
            <i class="fa fa-trash me-1"></i>Delete / Exclude from Project
          </button>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary text-light" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-sm btn-success fw-bold px-4" onclick="saveStudioCustomizerModal()">
              <i class="fa fa-check me-1"></i>Apply Changes
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
  // ═══════════════════════════════════════════════════════════════════════════
  // GCM XTREME &mdash; PARAMETRIC AI SPORTS ARCHITECTURE STUDIO JAVASCRIPT ENGINE
  // ═══════════════════════════════════════════════════════════════════════════
  (function() {
    var initDoc = <?= json_encode($d ?? null) ?>;
    var tkCfg = (initDoc && initDoc.turnkey_config) || {};

    var initL = parseFloat(tkCfg.length || 160);
    var initW = parseFloat(tkCfg.width || 60);
    var initH = parseFloat(tkCfg.height || 30);
    var initPitch = parseFloat(tkCfg.pitch_length !== undefined ? tkCfg.pitch_length : 66);
    var initSport = tkCfg.sport_type || 'multisport_arena_complex_185x60';
    var initRoof = tkCfg.roof_type || 'curved_truss';

    window.studioState = {
      length: initL,
      width: initW,
      height: initH,
      pitch_length: initPitch,
      sport_type: initSport,
      roof_type: initRoof,
      style: 'full_steel',
      currentVisTab: '3d',
      activeModalCompId: null,
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
          calcQty: function(l, w) { return l * w; }
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
          calcQty: function(l, w) { return l * w; }
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
          calcQty: function(l, w) { return l * w; }
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
          calcQty: function(l, w, h) { return 2 * (l + w) * h; }
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
          calcQty: function(l, w) { return (l >= 140 ? 100 : l) * w; }
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
          calcQty: function(l, w, h, p) { return (p || 66) * 6.5; }
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
          calcQty: function(l, w) { return (l > 120 ? (l - 100) : 0) * w; }
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
          calcQty: function(l, w) { return Math.max(6, Math.round((l * w) / 1000)); }
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
          calcQty: function() { return 2; }
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
          calcQty: function(l, w) { return 25 * w; }
        }
      }
    };

    // Override updateTurnkeyCalculations so legacy triggers smoothly sync Extreme Studio
    window.updateTurnkeyCalculations = function(immediate) {
      if (window.calcStudioBoq) window.calcStudioBoq();
      if (window.updateStudioVisualizer) window.updateStudioVisualizer();
    };

    // Concept Presets
    window.selectArenaConcept = function(concept) {
      document.querySelectorAll('.arena-concept-card').forEach(function(c) { c.classList.remove('active'); });
      var s = window.studioState;

      if (concept === 'cricket') {
        document.getElementById('cardConceptCricket').classList.add('active');
        s.length = 100;
        s.width = 60;
        s.height = 25;
        s.pitch_length = 66;
        s.sport_type = 'box_cricket_100x60';
        s.components.badminton_arena.included = false;
        s.components.cricket_pitch.included = true;
      } else if (concept === 'multisport') {
        document.getElementById('cardConceptMultiSport').classList.add('active');
        s.length = 160;
        s.width = 60;
        s.height = 30;
        s.pitch_length = 66;
        s.sport_type = 'multisport_arena_complex_185x60';
        s.components.badminton_arena.included = true;
        s.components.cricket_pitch.included = true;
      } else if (concept === 'badminton') {
        document.getElementById('cardConceptBadminton').classList.add('active');
        s.length = 88;
        s.width = 44;
        s.height = 28;
        s.pitch_length = 0;
        s.sport_type = 'badminton';
        s.components.badminton_arena.included = true;
        s.components.cricket_pitch.included = false;
        s.components.sports_flooring.included = false;
      } else if (concept === 'custom') {
        document.getElementById('cardConceptCustom').classList.add('active');
      }

      document.getElementById('tk_length').value = s.length;
      document.getElementById('tk_width').value = s.width;
      document.getElementById('tk_height').value = s.height;
      document.getElementById('tk_pitch_length').value = s.pitch_length;

      syncStudioInputs();
      renderStudioComponentCards();
      calcStudioBoq();
      updateStudioVisualizer();
    };

    // Enclosure Styles
    window.selectEnclosureStyle = function(style) {
      document.querySelectorAll('.enclosure-style-card').forEach(function(c) { c.classList.remove('active'); });
      var s = window.studioState;
      s.style = style;

      if (style === 'open_sky') {
        document.getElementById('cardStyleOpenSky').classList.add('active');
        s.roof_type = 'open_top';
        s.components.roof_canopy.included = false;
        s.components.wall_enclosures.included = true;
        s.components.wall_enclosures.name = '4.0mm High-Impact Braided HDPE Ball-Stop Netting';
        s.components.wall_enclosures.rate = 14.5;
      } else if (style === 'cable_net') {
        document.getElementById('cardStyleCableNet').classList.add('active');
        s.roof_type = 'flat_net';
        s.components.roof_canopy.included = true;
        s.components.roof_canopy.name = 'Wire-Rope Cable Suspended Overhead Ball-Stop Netting (6-Sided Caged)';
        s.components.roof_canopy.rate = 16.5;
        s.components.roof_canopy.unit = 'SQFT';
        s.components.wall_enclosures.included = true;
        s.components.wall_enclosures.name = '4.0mm High-Impact Braided HDPE Ball-Stop Netting';
        s.components.wall_enclosures.rate = 14.5;
      } else if (style === 'full_steel') {
        document.getElementById('cardStyleFullSteel').classList.add('active');
        s.roof_type = 'curved_truss';
        s.components.roof_canopy.included = true;
        s.components.roof_canopy.name = 'Curved Arch Tubular Steel Roof Truss & 0.50mm PPGL Canopy';
        s.components.roof_canopy.rate = 58;
        s.components.roof_canopy.unit = 'SQFT';
        s.components.wall_enclosures.included = true;
        s.components.wall_enclosures.name = '0.50mm PPGL Metal Profile Wall Cladding (All Sides)';
        s.components.wall_enclosures.rate = 58;
      } else if (style === 'hybrid') {
        document.getElementById('cardStyleHybrid').classList.add('active');
        s.roof_type = 'curved_truss';
        s.components.roof_canopy.included = true;
        s.components.roof_canopy.name = 'Curved Arch Tubular Steel Roof Truss & Canopy';
        s.components.roof_canopy.rate = 58;
        s.components.wall_enclosures.included = true;
        s.components.wall_enclosures.name = 'Hybrid Cladding: Lower 10 FT PPGL Sheets + Upper UV Netting';
        s.components.wall_enclosures.rate = 42;
      }

      renderStudioComponentCards();
      calcStudioBoq();
      updateStudioVisualizer();
    };

    window.setStudioHeight = function(h) {
      document.getElementById('tk_height').value = h;
      window.studioState.height = h;
      onStudioDimensionChange();
    };

    window.setStudioPitch = function(p) {
      document.getElementById('tk_pitch_length').value = p;
      window.studioState.pitch_length = p;
      window.studioState.components.cricket_pitch.included = (p > 0);
      onStudioDimensionChange();
    };

    window.onStudioDimensionChange = function() {
      var s = window.studioState;
      s.length = parseFloat(document.getElementById('tk_length').value) || 100;
      s.width = parseFloat(document.getElementById('tk_width').value) || 60;
      s.height = parseFloat(document.getElementById('tk_height').value) || 25;
      s.pitch_length = parseFloat(document.getElementById('tk_pitch_length').value) || 0;

      syncStudioInputs();
      renderStudioComponentCards();
      calcStudioBoq();
      updateStudioVisualizer();
    };

    function syncStudioInputs() {
      var s = window.studioState;
      var area = Math.round(s.length * s.width);
      var sqm = (area * 0.092903).toFixed(1);
      var lm = (s.length * 0.3048).toFixed(2);
      var wm = (s.width * 0.3048).toFixed(2);

      document.getElementById('studioAreaBadge').textContent = s.length + ' × ' + s.width + ' FT • ' + area.toLocaleString('en-IN') + ' SFT';
      document.getElementById('studioMetricBadge').textContent = lm + ' M × ' + wm + ' M • ' + sqm + ' SQM';
      document.getElementById('studioHudCoordinates').textContent = 'Length: ' + s.length + ' FT | Width: ' + s.width + ' FT | Height: ' + s.height + ' FT';
    }

    // Render Component Cards
    window.renderStudioComponentCards = function() {
      var container = document.getElementById('masterBimComponentGrid');
      if (!container) return;
      container.innerHTML = '';

      var catalog = window.MasterProductCatalog || [];
      var s = window.studioState;

      Object.keys(s.components).forEach(function(cId) {
        var comp = s.components[cId];
        var qty = Math.round(comp.calcQty ? comp.calcQty(s.length, s.width, s.height, s.pitch_length) : (comp.qty || 1));
        var lineTotal = comp.included ? Math.round(qty * (comp.rate || 0)) : 0;

        // Filter catalog for dropdown
        var catMatches = catalog.filter(function(p) {
          if (!p.category) return false;
          var pCat = p.category.toLowerCase();
          var cCat = comp.category.toLowerCase();
          if (cCat.indexOf('roof') !== -1) return pCat.indexOf('roof') !== -1;
          if (cCat.indexOf('struct') !== -1) return pCat.indexOf('struct') !== -1;
          if (cCat.indexOf('wall') !== -1) return (pCat.indexOf('wall') !== -1 || pCat.indexOf('net') !== -1);
          if (cCat.indexOf('floor') !== -1) return pCat.indexOf('floor') !== -1;
          if (cCat.indexOf('light') !== -1) return pCat.indexOf('light') !== -1;
          if (cCat.indexOf('gate') !== -1) return pCat.indexOf('gate') !== -1;
          if (cCat.indexOf('civil') !== -1) return pCat.indexOf('civil') !== -1;
          if (cCat.indexOf('amenit') !== -1) return (pCat.indexOf('amenit') !== -1 || pCat.indexOf('room') !== -1);
          return false;
        });

        var col = document.createElement('div');
        col.className = 'col-md-6 col-lg-6';

        var card = document.createElement('div');
        card.className = 'master-comp-card ' + (comp.included ? '' : 'excluded');
        card.id = 'studioCompCard_' + cId;

        // Header with title and toggle button
        var topRow = document.createElement('div');
        topRow.className = 'd-flex justify-content-between align-items-center mb-1';
        
        var titleDiv = document.createElement('div');
        titleDiv.className = 'fw-bold text-light';
        titleDiv.style.fontSize = '11.5px';
        titleDiv.innerHTML = '<i class="fa ' + (comp.icon || 'fa-cube text-info') + ' me-1"></i>' + comp.name.split('(')[0];

        var actDiv = document.createElement('div');
        actDiv.className = 'd-flex align-items-center gap-1';

        var badgeSpan = document.createElement('span');
        badgeSpan.className = comp.included ? 'badge bg-success' : 'badge bg-danger bg-opacity-75';
        badgeSpan.style.fontSize = '9.5px';
        badgeSpan.textContent = comp.included ? ('₹' + lineTotal.toLocaleString('en-IN')) : 'EXCLUDED • ₹0';

        var btnToggle = document.createElement('button');
        btnToggle.type = 'button';
        btnToggle.className = 'btn btn-xs ' + (comp.included ? 'btn-outline-danger' : 'btn-success') + ' py-0 px-2 fw-bold';
        btnToggle.innerHTML = comp.included ? '<i class="fa fa-trash me-1"></i>Exclude' : '<i class="fa fa-plus me-1"></i>Include';
        btnToggle.onclick = (function(id, nextState) {
          return function() { toggleStudioComponent(id, nextState); };
        })(cId, !comp.included);

        actDiv.appendChild(badgeSpan);
        actDiv.appendChild(btnToggle);
        topRow.appendChild(titleDiv);
        topRow.appendChild(actDiv);

        // Select dropdown
        var selWrap = document.createElement('div');
        selWrap.className = 'mb-2';

        var sel = document.createElement('select');
        sel.className = 'form-select form-select-sm bg-dark text-light border-secondary';
        sel.style.fontSize = '10.5px';

        var defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = '-- Choose Product from Master Catalog --';
        sel.appendChild(defaultOpt);

        catMatches.forEach(function(p) {
          var opt = document.createElement('option');
          opt.value = p.id;
          opt.textContent = p.name + ' (₹' + (p.default_rate || p.rate || 0) + '/' + (p.unit || 'SQFT') + ')';
          if (p.id === comp.productId || p.name === comp.name) {
            opt.selected = true;
          }
          sel.appendChild(opt);
        });

        sel.onchange = (function(id) {
          return function() { onStudioProductSelect(id, this.value); };
        })(cId);
        selWrap.appendChild(sel);

        // Footer with specs & edit button
        var footRow = document.createElement('div');
        footRow.className = 'd-flex justify-content-between align-items-center pt-1 border-top border-secondary text-secondary';
        footRow.style.fontSize = '10px';

        var specSpan = document.createElement('span');
        specSpan.innerHTML = qty.toLocaleString('en-IN') + ' ' + (comp.unit || 'SQFT') + ' @ ₹' + (comp.rate || 0) + '/' + (comp.unit || 'SQFT') + ' &bull; <strong class="text-info">' + (comp.brand || 'Standard') + '</strong>';

        var btnEdit = document.createElement('button');
        btnEdit.type = 'button';
        btnEdit.className = 'btn btn-xs btn-outline-info py-0 px-2 fw-bold';
        btnEdit.innerHTML = '<i class="fa fa-pen me-1"></i>Edit Specs';
        btnEdit.onclick = (function(id) {
          return function() { openStudioCompCustomizer(id); };
        })(cId);

        footRow.appendChild(specSpan);
        footRow.appendChild(btnEdit);

        card.appendChild(topRow);
        card.appendChild(selWrap);
        card.appendChild(footRow);

        col.appendChild(card);
        container.appendChild(col);
      });
    };

    window.toggleStudioComponent = function(cId, isInc) {
      var comp = window.studioState.components[cId];
      if (!comp) return;
      comp.included = !!isInc;
      renderStudioComponentCards();
      calcStudioBoq();
      updateStudioVisualizer();
    };

    window.onStudioProductSelect = function(cId, prodId) {
      var comp = window.studioState.components[cId];
      if (!comp || !prodId) return;
      var catalog = window.MasterProductCatalog || [];
      var p = catalog.find(function(item) { return item.id === prodId; });
      if (!p) return;

      comp.productId = p.id;
      comp.name = p.name;
      comp.spec = p.description || p.name;
      comp.rate = p.default_rate || p.rate || comp.rate;
      comp.unit = p.unit || comp.unit;
      comp.hsn = p.hsn || comp.hsn;
      comp.gst_rate = p.gst_rate || comp.gst_rate;
      comp.bullets = p.specs || [p.name];
      if (p.name.indexOf('Tata') !== -1) comp.brand = 'Tata Bluescope';
      else if (p.name.indexOf('AMNS') !== -1) comp.brand = 'AMNS PPGL';
      else if (p.name.indexOf('Apollo') !== -1) comp.brand = 'Apollo Steel';
      else if (p.name.indexOf('Garware') !== -1) comp.brand = 'Garware Technical Fibres';
      else if (p.name.indexOf('CCGrass') !== -1) comp.brand = 'CCGrass FIFA Quality';
      else if (p.name.indexOf('Gerflor') !== -1) comp.brand = 'Gerflor Taraflex BWF';
      else if (p.name.indexOf('Philips') !== -1) comp.brand = 'Philips Sports Lighting';

      renderStudioComponentCards();
      calcStudioBoq();
      updateStudioVisualizer();
    };

    window.openStudioCompCustomizer = function(cId) {
      var comp = window.studioState.components[cId];
      if (!comp) return;
      window.studioState.activeModalCompId = cId;

      document.getElementById('mStudioCompTitle').textContent = comp.name;
      document.getElementById('mStudioInputName').value = comp.name || '';
      document.getElementById('mStudioInputBrand').value = comp.brand || '';
      document.getElementById('mStudioInputSpec').value = comp.spec || '';
      document.getElementById('mStudioInputGauge').value = comp.gauge || '';
      document.getElementById('mStudioInputRate').value = comp.rate || 0;

      var s = window.studioState;
      var qty = Math.round(comp.calcQty ? comp.calcQty(s.length, s.width, s.height, s.pitch_length) : (comp.qty || 1));
      document.getElementById('mStudioInputQty').value = qty;

      document.getElementById('mStudioInputBullets').value = (comp.bullets || []).join('\n');

      var modalEl = document.getElementById('studioCompCustomizerModal');
      var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
      modal.show();
    };

    window.recalcStudioModalAmount = function() {};

    window.deleteStudioComponentFromModal = function() {
      var cId = window.studioState.activeModalCompId;
      if (!cId) return;
      toggleStudioComponent(cId, false);
      var modalEl = document.getElementById('studioCompCustomizerModal');
      var modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
    };

    window.saveStudioCustomizerModal = function() {
      var cId = window.studioState.activeModalCompId;
      var comp = window.studioState.components[cId];
      if (!comp) return;

      comp.name = document.getElementById('mStudioInputName').value.trim();
      comp.brand = document.getElementById('mStudioInputBrand').value.trim();
      comp.spec = document.getElementById('mStudioInputSpec').value.trim();
      comp.gauge = document.getElementById('mStudioInputGauge').value.trim();
      comp.rate = parseFloat(document.getElementById('mStudioInputRate').value) || 0;
      comp.qty = parseFloat(document.getElementById('mStudioInputQty').value) || 1;

      var bRaw = document.getElementById('mStudioInputBullets').value.trim();
      comp.bullets = bRaw ? bRaw.split('\n').map(function(s) { return s.trim(); }).filter(Boolean) : [];

      var modalEl = document.getElementById('studioCompCustomizerModal');
      var modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();

      renderStudioComponentCards();
      calcStudioBoq();
      updateStudioVisualizer();
    };

    // Live BOQ Calculation
    window.calcStudioBoq = function() {
      var s = window.studioState;
      var subtotal = 0;
      var activeCount = 0;
      var listContainer = document.getElementById('studioBoqMiniList');
      if (listContainer) listContainer.innerHTML = '';

      Object.keys(s.components).forEach(function(cId) {
        var comp = s.components[cId];
        if (!comp.included) return;
        activeCount++;

        var qty = Math.round(comp.calcQty ? comp.calcQty(s.length, s.width, s.height, s.pitch_length) : (comp.qty || 1));
        var amt = Math.round(qty * (comp.rate || 0));
        subtotal += amt;

        if (listContainer) {
          var row = document.createElement('div');
          row.className = 'd-flex justify-content-between align-items-center py-1 border-bottom border-secondary';
          row.innerHTML = 
            '<span class="text-truncate me-2 text-light" style="max-width:200px;">' +
              '<i class="fa ' + (comp.icon || 'fa-cube text-info') + ' me-1"></i>' + comp.name.split('(')[0] +
            '</span>' +
            '<strong class="text-success">₹' + amt.toLocaleString('en-IN') + '</strong>';
          listContainer.appendChild(row);
        }
      });

      var gst = Math.round(subtotal * 0.18);
      var grandTotal = subtotal + gst;

      var elItems = document.getElementById('studioActiveItemsBadge');
      if (elItems) elItems.textContent = activeCount + ' Active Items';
      var elSub = document.getElementById('studioSubtotalText');
      if (elSub) elSub.textContent = '₹' + subtotal.toLocaleString('en-IN');
      var elGst = document.getElementById('studioGstText');
      if (elGst) elGst.textContent = '₹' + gst.toLocaleString('en-IN');
      var elGrand = document.getElementById('studioGrandTotalText');
      if (elGrand) elGrand.textContent = '₹' + grandTotal.toLocaleString('en-IN');
    };

    // Visualizer Viewport Switcher
    window.switchStudioVisTab = function(tab) {
      window.studioState.currentVisTab = tab;
      document.querySelectorAll('#btnVisTab3d, #btnVisTabFloor, #btnVisTabElev, #btnVisTabQuad').forEach(function(b) {
        b.classList.remove('active');
      });

      document.getElementById('studio3dContainer').style.display = (tab === '3d') ? 'block' : 'none';
      document.getElementById('studioFloorContainer').style.display = (tab === 'floor') ? 'block' : 'none';
      document.getElementById('studioElevContainer').style.display = (tab === 'elevation') ? 'block' : 'none';
      document.getElementById('studioQuadContainer').style.display = (tab === 'quad') ? 'grid' : 'none';

      if (tab === '3d') document.getElementById('btnVisTab3d').classList.add('active');
      if (tab === 'floor') document.getElementById('btnVisTabFloor').classList.add('active');
      if (tab === 'elevation') document.getElementById('btnVisTabElev').classList.add('active');
      if (tab === 'quad') document.getElementById('btnVisTabQuad').classList.add('active');

      updateStudioVisualizer();
    };

    // Update 3D / 2D Visualizer
    window.updateStudioVisualizer = function() {
      if (!window.TurnkeyViewer) return;
      var s = window.studioState;

      var isRoofInc = s.components.roof_canopy && s.components.roof_canopy.included;
      var isWallInc = s.components.wall_enclosures && s.components.wall_enclosures.included;
      var isPitchInc = s.components.cricket_pitch && s.components.cricket_pitch.included;
      var isBadmInc = s.components.badminton_arena && s.components.badminton_arena.included;

      var openSides = {
        front: !isWallInc,
        back: !isWallInc,
        left: !isWallInc,
        right: !isWallInc,
        top: !isRoofInc
      };

      var cfg = {
        length: s.length,
        width: s.width,
        height: s.height,
        pitch_length: isPitchInc ? (s.pitch_length || 66) : 0,
        sport_type: (isBadmInc && !isPitchInc) ? 'badminton' : s.sport_type,
        roof_type: !isRoofInc ? 'open_top' : s.roof_type,
        package: s.package || 'commercial',
        turf_type: isBadmInc && !isPitchInc ? 'vinyl_mat_badminton' : 'sports_50mm_12000_dtex',
        has_all_sides_gi_sheets: (s.style === 'full_steel'),
        open_sides: openSides,
        zones: (isBadmInc && s.sport_type.indexOf('multisport') !== -1) ? {
          cricket: { length: 100, width: s.width, floor: 'sports_turf' },
          badminton: { length: 60, width: s.width, floor: 'vinyl_mat', courts: 2 }
        } : null
      };

      window.turnkeyViewConfig = cfg;

      if (s.currentVisTab === '3d') {
        window.studioViewer3d = window.TurnkeyViewer.init3D('studio3dContainer', cfg);
      } else if (s.currentVisTab === 'floor') {
        window.TurnkeyViewer.renderFloorPlan('studioFloorContainer', cfg);
      } else if (s.currentVisTab === 'elevation') {
        window.TurnkeyViewer.renderSideElevation('studioElevContainer', cfg);
      } else if (s.currentVisTab === 'quad') {
        window.TurnkeyViewer.init3D('sQuad3d', cfg);
        window.TurnkeyViewer.renderFloorPlan('sQuadFloor', cfg);
        window.TurnkeyViewer.renderSideElevation('sQuadSide', cfg);
        window.TurnkeyViewer.renderCrossElevation('sQuadCross', cfg);
      }
    };

    // Apply to Document Items Table
    window.applyExtremeStudioToDocument = function() {
      var s = window.studioState;
      var tbody = document.getElementById('itemsTbody');
      if (!tbody) {
        if (window.GcmNotify) { GcmNotify.error('Notice', 'Items table not found in page!'); }
        return;
      }

      tbody.innerHTML = '';
      var rowIndex = 0;

      Object.keys(s.components).forEach(function(cId) {
        var comp = s.components[cId];
        if (!comp.included) return;

        var qty = Math.round(comp.calcQty ? comp.calcQty(s.length, s.width, s.height, s.pitch_length) : (comp.qty || 1));
        var amt = Math.round(qty * (comp.rate || 0));

        var specLines = [];
        if (comp.brand) specLines.push('Brand: ' + comp.brand);
        if (comp.gauge) specLines.push('Specification: ' + comp.gauge);
        if (comp.bullets && Array.isArray(comp.bullets)) {
          specLines = specLines.concat(comp.bullets);
        }

        var itemData = {
          description: comp.name + '\n' + (comp.spec || ''),
          hsn: comp.hsn || '7308',
          qty: qty,
          unit: comp.unit || 'SQFT',
          rate: comp.rate || 0,
          amount: amt,
          gst_rate: comp.gst_rate || 18,
          specs: specLines
        };

        if (typeof getRowHtmlWithData === 'function') {
          var html = getRowHtmlWithData(rowIndex, itemData);
          tbody.insertAdjacentHTML('beforeend', html);
          rowIndex++;
        }
      });

      // Reindex rows and calculate financial totals
      if (typeof reindexRows === 'function') reindexRows();
      if (typeof calcTotals === 'function') calcTotals();

      // Sync hidden inputs for backend submission
      var tkCfgField = document.getElementById('turnkeyConfigJson');
      if (tkCfgField) {
        tkCfgField.value = JSON.stringify({
          length: s.length,
          width: s.width,
          height: s.height,
          pitch_length: s.pitch_length,
          sport_type: s.sport_type,
          roof_type: s.roof_type,
          custom_specs: s.components,
          open_sides: { top: !s.components.roof_canopy.included }
        });
      }

      if (window.GcmNotify) { GcmNotify.success('BOQ Synchronized', 'Successfully synchronized ' + rowIndex + ' master building items into the quotation! Subtotal, 18% GST and Grand Total updated.', 5000); } else { alert('Successfully synchronized ' + rowIndex + ' items'); }
    };


    // ═════════════════════════════════════════════════════════════════════════
    // GCM XTREME JAVASCRIPT CONTROLLERS
    // ═════════════════════════════════════════════════════════════════════════
    window.runXtremeAiPrompt = function() {
      var input = document.getElementById('xtremeAiPromptInput');
      if (!input || !input.value.trim()) return;
      var prompt = input.value.trim();

      if (window.GcmXtreme && window.GcmXtreme.parseArenaPrompt) {
        var res = window.GcmXtreme.parseArenaPrompt(prompt);
        if (res) {
          // Sync state to inputs
          document.getElementById('tk_length').value = res.totalLength;
          document.getElementById('tk_width').value = res.width;
          document.getElementById('tk_height').value = res.height;
          window.studioState.length = res.totalLength;
          window.studioState.width = res.width;
          window.studioState.height = res.height;
          
          if (typeof syncStudioInputs === 'function') syncStudioInputs();
          if (typeof renderStudioComponentCards === 'function') renderStudioComponentCards();
          if (typeof calcStudioBoq === 'function') calcStudioBoq();
          if (typeof updateStudioVisualizer === 'function') updateStudioVisualizer();

          if (window.GcmNotify) { GcmNotify.ai('GCM XTREME AI Studio', 'Successfully parsed and generated ' + res.courtsCount + ' court(s) [' + res.totalLength + ' × ' + res.width + ' FT] with synchronized 3D BIM, CAD floor plan & BOQ!', 6000); } else { alert('GCM XTREME AI generated ' + res.courtsCount + ' court(s)'); }
        }
      }
    };

    window.setAndRunPrompt = function(p) {
      var input = document.getElementById('xtremeAiPromptInput');
      if (input) {
        input.value = p;
        runXtremeAiPrompt();
      }
    };

    window.onXtremeAttachmentChange = function(val) {
      if (!window.GcmXtreme) return;
      var courts = window.GcmXtreme.state.courts || [];
      var gap = 0;
      if (val === 'gap_2ft') gap = 2;
      else if (val === 'gap_1m') gap = 3.28;
      else if (val === 'gap_5ft') gap = 5;

      courts.forEach(function(c, i) {
        if (i > 0) {
          c.attachment = (val === 'single') ? 'primary' : (gap > 0 ? 'offset' : 'attached');
          c.gap_distance = gap;
        }
      });
      calcStudioBoq();
      updateStudioVisualizer();
    };

    window.onXtremeDualSportChange = function(val) {
      if (!window.GcmXtreme) return;
      var courts = window.GcmXtreme.state.courts || [];
      if (courts.length > 0) {
        if (val === 'badm_basket') {
          courts[0].sport = 'badminton';
          courts[0].secondarySport = 'basketball';
        } else if (val === 'foot_basket') {
          courts[0].sport = 'football';
          courts[0].secondarySport = 'basketball';
        } else if (val === 'badm_pickle') {
          courts[0].sport = 'badminton';
          courts[0].secondarySport = 'pickleball';
        } else if (val === 'cricket_pickle') {
          courts[0].sport = 'cricket';
          courts[0].secondarySport = 'pickleball';
        } else {
          courts[0].secondarySport = null;
        }
      }
      calcStudioBoq();
      updateStudioVisualizer();
    };

    // Initialize Triple Click Inspector & Drag Gizmos on DOM load
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(function() {
        if (window.GcmXtreme) {
          window.GcmXtreme.initTripleClickInspector('studio3dContainer');
          window.GcmXtreme.initTripleClickInspector('studioFloorContainer');
          window.GcmXtreme.initGateDragGizmo('studioFloorContainer');
        }
      }, 600);
    });

        // Initialize on load
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(function() {
        syncStudioInputs();
        renderStudioComponentCards();
        calcStudioBoq();
        updateStudioVisualizer();
      }, 300);
    });
  })();
  </script>
