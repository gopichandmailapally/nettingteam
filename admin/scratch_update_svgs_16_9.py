import re

path = '/var/www/gcm.enterprises/admin/assets/js/turnkey_court_viewer.js'
code = open(path).read()

# Replace render2DFloorPlan
fp_start = code.find('  // 2D Floor Plan (Top View) SVG Generator')
fp_end = code.find('  // 2D Side Elevation Plan SVG Generator')
assert fp_start != -1 and fp_end != -1, 'Floor plan boundaries not found'

new_fp = '''  // 2D Floor Plan (Top View) SVG Generator - Strictly 16:9 Ratio
  function render2DFloorPlan(svgContainerId, config) {
    var container = document.getElementById(svgContainerId);
    if (!container) return;

    var L_ft = parseFloat(config.length) || 90;
    var W_ft = parseFloat(config.width) || 60;
    var H_ft = parseFloat(config.height) || 24;
    var sport = config.sport_type || 'box_cricket';
    var pkg = config.package || 'commercial';

    var lenSpans = getIntermediateSpans(L_ft, 15, 12);
    var widSpans = getIntermediateSpans(W_ft, 15, 12);

    // Strictly 16:9 viewBox (1600 x 900)
    var svgW = 1600;
    var svgH = 900;
    var padX = 220;
    var padY = 90;
    var availW = svgW - 2 * padX; // 1160
    var availH = svgH - 2 * padY; // 720

    var scale = Math.min(availW / L_ft, availH / W_ft); // ~12 px/ft
    var courtW = L_ft * scale;
    var courtH = W_ft * scale;

    var cx = (svgW - courtW) / 2;
    var cy = (svgH - courtH) / 2;

    var svg = [];
    svg.push('<svg viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;aspect-ratio:16/9;font-family:Arial,sans-serif;background:#ffffff;display:block;margin:0 auto;">');

    // Defs & Gradients
    svg.push('<defs>');
    svg.push('<linearGradient id="turfGrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#16a34a"/><stop offset="100%" stop-color="#15803d"/></linearGradient>');
    svg.push('<marker id="arrow" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="#475569"/></marker>');
    svg.push('</defs>');

    // Court Turf Rectangle
    svg.push('<rect x="' + cx + '" y="' + cy + '" width="' + courtW + '" height="' + courtH + '" fill="url(#turfGrad)" stroke="#14532d" stroke-width="3"/>');

    // White Boundary Line
    var bPad = Math.round(1.5 * scale);
    svg.push('<rect x="' + (cx + bPad) + '" y="' + (cy + bPad) + '" width="' + (courtW - 2 * bPad) + '" height="' + (courtH - 2 * bPad) + '" fill="none" stroke="#ffffff" stroke-width="3"/>');

    // Sport Markings
    // Turf Roll Seam Guidelines (every 2.0 MTR / 6.5 FT along Court Width)
    var rollW_px = 6.5 * scale;
    for (var ry = cy + rollW_px; ry < cy + courtH - 8; ry += rollW_px) {
      svg.push('<line x1="' + cx + '" y1="' + ry + '" x2="' + (cx + courtW) + '" y2="' + ry + '" stroke="#ffffff" stroke-width="1" stroke-dasharray="8,8" opacity="0.45"/>');
    }

    if (sport === 'box_cricket' || sport === 'multi_sport') {
      var pitchL_ft = (config && config.pitch_length && parseFloat(config.pitch_length) > 0) ? Math.min(parseFloat(config.pitch_length), L_ft - 6) : Math.min(66, L_ft - 10);
      var pitchW_ft = 6.5; // strictly 6.5 FT (2.0 MTR) width as per turf roll standard!
      var pitchW_px = pitchL_ft * scale;
      var pitchH_px = pitchW_ft * scale;
      var pX = cx + (courtW - pitchW_px) / 2;
      var pY = cy + (courtH - pitchH_px) / 2;
      svg.push('<rect x="' + pX + '" y="' + pY + '" width="' + pitchW_px + '" height="' + pitchH_px + '" fill="#fed7aa" stroke="#ffffff" stroke-width="2"/>');
      svg.push('<text x="' + (pX + pitchW_px / 2) + '" y="' + (pY + pitchH_px / 2 + 5) + '" text-anchor="middle" font-size="14" font-weight="bold" fill="#7c2d12">CRICKET PITCH (' + pitchW_ft + ' FT / 2M × ' + pitchL_ft + ' FT)</text>');
      // Creases
      var cDist = pitchW_px * 0.08;
      svg.push('<line x1="' + (pX + cDist) + '" y1="' + pY + '" x2="' + (pX + cDist) + '" y2="' + (pY + pitchH_px) + '" stroke="#ffffff" stroke-width="2.5"/>');
      svg.push('<line x1="' + (pX + pitchW_px - cDist) + '" y1="' + pY + '" x2="' + (pX + pitchW_px - cDist) + '" y2="' + (pY + pitchH_px) + '" stroke="#ffffff" stroke-width="2.5"/>');
    }

    if (sport === 'football' || sport === 'futsal' || sport === 'multi_sport') {
      // Center line
      svg.push('<line x1="' + (cx + courtW / 2) + '" y1="' + (cy + bPad) + '" x2="' + (cx + courtW / 2) + '" y2="' + (cy + courtH - bPad) + '" stroke="#ffffff" stroke-width="2.5"/>');
      // Center circle
      var cr = Math.min(courtW, courtH) * 0.18;
      svg.push('<circle cx="' + (cx + courtW / 2) + '" cy="' + (cy + courtH / 2) + '" r="' + cr + '" fill="none" stroke="#ffffff" stroke-width="2.5"/>');
      svg.push('<circle cx="' + (cx + courtW / 2) + '" cy="' + (cy + courtH / 2) + '" r="5" fill="#ffffff"/>');
      // Goal boxes
      var gbW = 16 * scale;
      var gbH = courtH * 0.45;
      svg.push('<rect x="' + (cx + bPad) + '" y="' + (cy + (courtH - gbH) / 2) + '" width="' + gbW + '" height="' + gbH + '" fill="none" stroke="#ffffff" stroke-width="2.5"/>');
      svg.push('<rect x="' + (cx + courtW - bPad - gbW) + '" y="' + (cy + (courtH - gbH) / 2) + '" width="' + gbW + '" height="' + gbH + '" fill="none" stroke="#ffffff" stroke-width="2.5"/>');
    }

    // Perimeter French Drain Line (dashed)
    svg.push('<rect x="' + (cx - 10) + '" y="' + (cy - 10) + '" width="' + (courtW + 20) + '" height="' + (courtH + 20) + '" fill="none" stroke="#64748b" stroke-width="1.5" stroke-dasharray="6,6"/>');
    svg.push('<text x="' + (cx + courtW / 2) + '" y="' + (cy - 16) + '" text-anchor="middle" font-size="13" fill="#64748b">--- PERIMETER DRAINAGE TRENCH ---</text>');

    // Poles Coordinates & Rendering
    var xPos = [0];
    for (var i = 0; i < lenSpans.positions.length; i++) xPos.push(lenSpans.positions[i]);
    xPos.push(L_ft);

    var yPos = [0];
    for (var j = 0; j < widSpans.positions.length; j++) yPos.push(widSpans.positions[j]);
    yPos.push(W_ft);

    function drawPole(x_ft, y_ft, isCorner, isLight) {
      var px = cx + x_ft * scale;
      var py = cy + y_ft * scale;

      // Footing box
      svg.push('<rect x="' + (px - 8) + '" y="' + (py - 8) + '" width="16" height="16" fill="#e2e8f0" stroke="#475569" stroke-width="1.2"/>');
      // Column marker - STRICTLY #ff0000 for corners!
      var colColor = isLight ? '#f59e0b' : (isCorner ? '#ff0000' : '#1e293b');
      svg.push('<circle cx="' + px + '" cy="' + py + '" r="5" fill="' + colColor + '"/>');

      if (isLight) {
        svg.push('<circle cx="' + px + '" cy="' + py + '" r="10" fill="none" stroke="#f59e0b" stroke-width="1.8" stroke-dasharray="3,3"/>');
      }
    }

    // Draw Columns
    for (var xi = 0; xi < xPos.length; xi++) {
      var xVal = xPos[xi];
      var isC1 = (xi === 0 || xi === xPos.length - 1);
      var isL1 = isC1 || (xi === Math.floor(xPos.length / 2));
      drawPole(xVal, 0, isC1, isL1);
      drawPole(xVal, W_ft, isC1, isL1);
    }
    for (var yi = 1; yi < yPos.length - 1; yi++) {
      var yVal = yPos[yi];
      drawPole(0, yVal, false, false);
      drawPole(L_ft, yVal, false, false);
    }

    // Top Bay Dimensions (Cleanly spaced)
    var dimY = cy - 35;
    for (var bi = 0; bi < xPos.length - 1; bi++) {
      var bx1 = cx + xPos[bi] * scale;
      var bx2 = cx + xPos[bi + 1] * scale;
      var bayW_ft = Math.round((xPos[bi + 1] - xPos[bi]) * 10) / 10;
      svg.push('<line x1="' + bx1 + '" y1="' + dimY + '" x2="' + bx2 + '" y2="' + dimY + '" stroke="#475569" stroke-width="1.2" marker-start="url(#arrow)" marker-end="url(#arrow)"/>');
      svg.push('<text x="' + ((bx1 + bx2) / 2) + '" y="' + (dimY - 6) + '" text-anchor="middle" font-size="13" font-weight="bold" fill="#334155">' + bayW_ft + ' FT</text>');
    }

    // Bottom Dimension: Total Length
    var bDimY = cy + courtH + 35;
    svg.push('<line x1="' + cx + '" y1="' + bDimY + '" x2="' + (cx + courtW) + '" y2="' + bDimY + '" stroke="#0f172a" stroke-width="2" marker-start="url(#arrow)" marker-end="url(#arrow)"/>');
    svg.push('<text x="' + (cx + courtW / 2) + '" y="' + (bDimY + 22) + '" text-anchor="middle" font-size="16" font-weight="bold" fill="#0f172a">TOTAL LENGTH: ' + L_ft + ' FT-0" (' + lenSpans.bays + ' BAYS @ ~' + Math.round(lenSpans.bayWidth * 10) / 10 + ' FT C/C)</text>');

    // Right Dimension: Total Width
    var rDimX = cx + courtW + 35;
    svg.push('<line x1="' + rDimX + '" y1="' + cy + '" x2="' + rDimX + '" y2="' + (cy + courtH) + '" stroke="#0f172a" stroke-width="2" marker-start="url(#arrow)" marker-end="url(#arrow)"/>');
    svg.push('<text x="' + (rDimX + 22) + '" y="' + (cy + courtH / 2) + '" transform="rotate(90 ' + (rDimX + 22) + ' ' + (cy + courtH / 2) + ')" text-anchor="middle" font-size="16" font-weight="bold" fill="#0f172a">TOTAL WIDTH: ' + W_ft + ' FT-0"</text>');

    // Player Entry Gate Callout (Front Right) - STRICTLY #ff0000
    var gatePx = cx + courtW - (15 * scale);
    var gatePy = cy + courtH;
    svg.push('<g transform="translate(' + gatePx + ',' + gatePy + ')">');
    svg.push('<rect x="-16" y="-3" width="32" height="6" fill="#ff0000"/>');
    svg.push('<path d="M -16,0 A 16 16 0 0 1 0,16" fill="none" stroke="#ff0000" stroke-width="2" stroke-dasharray="3,3"/>');
    svg.push('<text x="0" y="32" text-anchor="middle" font-size="13" font-weight="bold" fill="#ff0000">🚪 ENTRY (4 FT × 7 FT)</text>');
    svg.push('</g>');

    // Bottom Legend (Single neat line, no overlap) - STRICTLY #ff0000
    var legY = svgH - 18;
    svg.push('<g transform="translate(' + (svgW / 2) + ',' + legY + ')" font-size="13" fill="#475569" text-anchor="middle">');
    svg.push('<circle cx="-380" cy="-4" r="5" fill="#ff0000"/><text x="-368" y="0" text-anchor="start">Corner Column (4)</text>');
    svg.push('<circle cx="-200" cy="-4" r="5" fill="#1e293b"/><text x="-188" y="0" text-anchor="start">Intermediate Column</text>');
    svg.push('<circle cx="0" cy="-4" r="5" fill="#f59e0b"/><circle cx="0" cy="-4" r="9" fill="none" stroke="#f59e0b" stroke-width="1.2"/><text x="14" y="0" text-anchor="start">⚡ Floodlight Mast (6)</text>');
    svg.push('<rect x="230" cy="-10" width="14" height="12" fill="#e2e8f0" stroke="#475569" stroke-width="1"/><text x="250" y="0" text-anchor="start">Concrete Footing</text>');
    svg.push('</g>');

    svg.push('</svg>');
    container.innerHTML = svg.join(\x27\n\x27);
  }
'''

# Replace render2DSideElevation
se_start = fp_end
se_end = code.find('  // Export to window')
assert se_start != -1 and se_end != -1, 'Side elevation boundaries not found'

new_se = '''  // 2D Side Elevation Plan SVG Generator - Strictly 16:9 Ratio
  function render2DSideElevation(svgContainerId, config) {
    var container = document.getElementById(svgContainerId);
    if (!container) return;

    var L_ft = parseFloat(config.length) || 90;
    var W_ft = parseFloat(config.width) || 60;
    var H_ft = parseFloat(config.height) || 24;
    var pkg = config.package || 'commercial';
    var roofType = config.roof_type || 'top_net';
    var hasRoof = (roofType !== 'open_top');

    var lenSpans = getIntermediateSpans(L_ft, 15, 12);
    var tiers = getHorizontalTiers(H_ft);

    // Strictly 16:9 viewBox (1600 x 900)
    var svgW = 1600;
    var svgH = 900;
    var margin = { top: 120, right: 170, bottom: 120, left: 170 };
    var courtW = svgW - margin.left - margin.right; // 1260

    var scale = courtW / L_ft; // 14 px/ft
    var courtH = H_ft * scale; // 336 px
    var footDepth_px = 3 * scale; // 42 px

    var cx = margin.left;
    var groundY = svgH - margin.bottom - footDepth_px - 30; // ~708 px

    var svg = [];
    svg.push('<svg viewBox="0 0 1600 900" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;aspect-ratio:16/9;font-family:Arial,sans-serif;background:#ffffff;display:block;margin:0 auto;">');

    // Defs & Markers
    svg.push('<defs>');
    svg.push('<marker id="arrow2" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="#475569"/></marker>');
    svg.push('</defs>');

    // Finished Ground line
    svg.push('<line x1="40" y1="' + groundY + '" x2="' + (svgW - 40) + '" y2="' + groundY + '" stroke="#334155" stroke-width="3"/>');
    svg.push('<text x="50" y="' + (groundY - 10) + '" font-size="14" font-weight="bold" fill="#15803d">GROUND LEVEL (±0.00)</text>');

    // Underground soil hatch
    svg.push('<rect x="' + cx + '" y="' + groundY + '" width="' + courtW + '" height="' + footDepth_px + '" fill="#f1f5f9" stroke="none"/>');

    // Netting background panel
    svg.push('<rect x="' + cx + '" y="' + (groundY - courtH) + '" width="' + courtW + '" height="' + courtH + '" fill="#dcfce7" fill-opacity="0.45" stroke="none"/>');

    // Horizontal Tiers
    for (var t = 0; t < tiers.length; t++) {
      var tierY_ft = tiers[t];
      var py = groundY - tierY_ft * scale;
      var isTop = (tierY_ft >= H_ft);
      svg.push('<line x1="' + cx + '" y1="' + py + '" x2="' + (cx + courtW) + '" y2="' + py + '" stroke="#0284c7" stroke-width="' + (isTop ? 3 : 1.8) + '"/>');
      // Label on left side
      var tierLabel = isTop ? ('ROOF (+' + tierY_ft + ' FT)') : ('TIER ' + (t + 1) + ' (+' + tierY_ft + ' FT)');
      svg.push('<text x="' + (cx - 15) + '" y="' + (py + 5) + '" text-anchor="end" font-size="13" font-weight="bold" fill="#0369a1">' + tierLabel + '</text>');
    }

    // Vertical Columns & Footing Pits
    var xVals = [0];
    for (var i = 0; i < lenSpans.positions.length; i++) xVals.push(lenSpans.positions[i]);
    xVals.push(L_ft);

    for (var k = 0; k < xVals.length; k++) {
      var colX = cx + xVals[k] * scale;
      var isEnd = (k === 0 || k === xVals.length - 1);
      var isLight = isEnd || (k === Math.floor(xVals.length / 2));

      // Underground footing (-3 ft)
      svg.push('<rect x="' + (colX - 10) + '" y="' + groundY + '" width="20" height="' + footDepth_px + '" fill="#cbd5e1" stroke="#475569" stroke-width="1.2"/>');

      // Steel column - Corner/End columns use #ff0000!
      var colStroke = isEnd ? '#ff0000' : '#1e293b';
      svg.push('<line x1="' + colX + '" y1="' + (groundY + footDepth_px) + '" x2="' + colX + '" y2="' + (groundY - courtH) + '" stroke="' + colStroke + '" stroke-width="' + (isEnd ? 4.5 : 2.5) + '"/>');

      // Floodlight head on top
      if (isLight) {
        var topY = groundY - courtH;
        svg.push('<rect x="' + (colX - 9) + '" y="' + (topY - 12) + '" width="18" height="12" fill="#f59e0b" stroke="#1e293b" stroke-width="1.2"/>');
        svg.push('<polygon points="' + (colX - 12) + ',' + topY + ' ' + (colX + 12) + ',' + topY + ' ' + (colX + 28) + ',' + (topY + 55) + ' ' + (colX - 28) + ',' + (topY + 55) + '" fill="#fef08a" fill-opacity="0.22"/>');
      }
    }

    // Roof Truss camber profile (if roof enabled)
    if (hasRoof) {
      svg.push('<path d="M ' + cx + ' ' + (groundY - courtH) + ' Q ' + (cx + courtW / 2) + ' ' + (groundY - courtH - 22) + ' ' + (cx + courtW) + ' ' + (groundY - courtH) + '" fill="none" stroke="#475569" stroke-width="2.5" stroke-dasharray="6,6"/>');
      svg.push('<text x="' + (cx + courtW / 2) + '" y="' + (groundY - courtH - 28) + '" text-anchor="middle" font-size="14" font-weight="bold" fill="#475569">OVERHEAD TRUSS CAMBER / ROOF NETTING</text>');
    }

    // Height Dimension (Right)
    var dimX = cx + courtW + 35;
    svg.push('<line x1="' + dimX + '" y1="' + (groundY - courtH) + '" x2="' + dimX + '" y2="' + groundY + '" stroke="#0f172a" stroke-width="2" marker-start="url(#arrow2)" marker-end="url(#arrow2)"/>');
    svg.push('<text x="' + (dimX + 22) + '" y="' + (groundY - courtH / 2) + '" transform="rotate(90 ' + (dimX + 22) + ' ' + (groundY - courtH / 2) + ')" text-anchor="middle" font-size="15" font-weight="bold" fill="#0f172a">CLEAR HEIGHT: ' + H_ft + ' FT-0"</text>');

    // Bottom Bay Dimensions
    var botDimY = groundY + footDepth_px + 25;
    svg.push('<line x1="' + cx + '" y1="' + botDimY + '" x2="' + (cx + courtW) + '" y2="' + botDimY + '" stroke="#475569" stroke-width="1.5" marker-start="url(#arrow2)" marker-end="url(#arrow2)"/>');
    svg.push('<text x="' + (cx + courtW / 2) + '" y="' + (botDimY + 20) + '" text-anchor="middle" font-size="14" font-weight="bold" fill="#334155">' + lenSpans.bays + ' BAYS @ ~' + Math.round(lenSpans.bayWidth * 10) / 10 + ' FT C/C = ' + L_ft + ' FT OVERALL LENGTH</text>');

    svg.push('</svg>');
    container.innerHTML = svg.join(\x27\n\x27);
  }
'''

code = code[:fp_start] + new_fp + '\n' + new_se + '\n' + code[se_end:]
open(path, 'w').write(code)
print('turnkey_court_viewer.js 16:9 SVGs updated successfully!')
